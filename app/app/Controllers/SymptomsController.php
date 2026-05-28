<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\CsrfService;
use App\Services\VisitService;
use App\Support\Layout;
use App\Support\SymptomSearch;
use App\Support\View;
use PDO;

/**
 * SymptomsController — Phase 3 endpoints.
 *
 *   GET  /api/v1/symptoms/search?q=fev          — ranked autocomplete
 *   POST /api/v1/visits/{id}/symptoms           — replace visit's symptom list
 *   DEL  /api/v1/visits/{id}/symptoms/{vsId}    — remove one
 *
 *   GET  /admin/symptom-promotions              — candidates for global promotion
 *   POST /admin/symptom-promotions/promote      — promote a personal label to master
 *   POST /admin/symptom-promotions/ignore       — denylist a label
 */
final class SymptomsController
{
    // =====================================================
    // Doctor-facing endpoints
    // =====================================================

    public function searchApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $clinic = RequestContext::clinic() ?? [];

        $doctorId = (int) ($user['id'] ?? 0);
        $specialty = (string) ($clinic['specialty'] ?? '');
        $q = (string) ($request->query['q'] ?? '');

        $results = SymptomSearch::search($doctorId, $clinicId, $specialty, $q);

        return Response::json([
            'q' => $q,
            'symptoms' => $results,
        ]);
    }

    /**
     * GET /api/v1/symptoms/by-category
     *   ?cat=gi  → symptoms in that category (specialty-ranked)
     *   (no cat) → list of categories with counts, for the browse pills
     */
    public function byCategory(Request $request): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinic = RequestContext::clinic() ?? [];
        $specialty = (string) ($clinic['specialty'] ?? '');
        $cat = trim((string) ($request->query['cat'] ?? ''));
        $pdo = Database::connection();

        // No category → return the category index (label + count).
        if ($cat === '') {
            $rows = $pdo->query(
                "SELECT category, COUNT(*) AS n
                   FROM symptoms_master
                  WHERE is_active = 1 AND category IS NOT NULL AND category <> ''
                  GROUP BY category
                  ORDER BY category"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $cats = [];
            foreach ($rows as $r) {
                $key = (string) $r['category'];
                $cats[] = [
                    'key'   => $key,
                    'label' => self::CATEGORY_LABELS[$key] ?? ucfirst($key),
                    'count' => (int) $r['n'],
                ];
            }
            return Response::json(['categories' => $cats]);
        }

        // A category → its symptoms, specialty-relevant ones first.
        // NOTE: native prepares (ATTR_EMULATE_PREPARES=false) forbid reusing a
        // named placeholder, so :sp appears once — passed via bindValue twice
        // would also fail. We inject the specialty match with two distinct binds.
        $stmt = $pdo->prepare(
            "SELECT id, label,
                    CASE WHEN :sp1 <> '' AND JSON_VALID(specialties)
                              AND JSON_CONTAINS(LOWER(specialties), JSON_QUOTE(LOWER(:sp2)))
                         THEN 1 ELSE 0 END AS specialty_match
               FROM symptoms_master
              WHERE is_active = 1 AND category = :cat
              ORDER BY specialty_match DESC, global_usage_count DESC, label ASC
              LIMIT 60"
        );
        $stmt->execute([':sp1' => $specialty, ':sp2' => $specialty, ':cat' => $cat]);

        $symptoms = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $symptoms[] = [
                'label'     => (string) $r['label'],
                'master_id' => (int) $r['id'],
                'source'    => 'master',
            ];
        }

        return Response::json([
            'category' => $cat,
            'label'    => self::CATEGORY_LABELS[$cat] ?? ucfirst($cat),
            'symptoms' => $symptoms,
        ]);
    }

    /** Human labels for the symptom category keys (Review-of-Systems style). */
    private const CATEGORY_LABELS = [
        'constitutional' => 'Constitutional',
        'neuro'          => 'Neurological',
        'eye'            => 'Eyes',
        'ent'            => 'ENT',
        'respiratory'    => 'Respiratory',
        'cardio'         => 'Cardiovascular',
        'gi'             => 'Gastrointestinal',
        'gu'             => 'Genitourinary',
        'gyn'            => 'Gynecological',
        'ortho'          => 'Musculoskeletal',
        'derma'          => 'Skin',
        'endo'           => 'Endocrine',
        'dental'         => 'Dental',
        'psych'          => 'Psychological',
        'peds'           => 'Pediatric',
        'allergy'        => 'Allergy/Immune',
        'rehab'          => 'Rehab',
        'red-flag'       => 'Red flags',
        'nutrition'      => 'Nutrition',
        'preventive'     => 'Preventive',
    ];

    /**
     * POST /api/v1/visits/{id}/symptoms
     * Body: { symptoms: [{ label, severity?, duration?, master_id? }, ...] }
     *
     * Replaces the visit's symptom list atomically. Bumps usage counters
     * for personal + master matches.
     */
    public function saveForVisit(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);
        $visitId = (int) $id;

        $visit = VisitService::find($clinicId, $visitId);
        if ($visit === null) {
            return Response::json(['error' => 'Visit not found'], 404);
        }
        if (!VisitService::isEditable($visit)) {
            return Response::json(['error' => 'Visit is read-only'], 422);
        }

        $body = json_decode($request->rawBody ?? '{}', true);
        $items = is_array($body['symptoms'] ?? null) ? $body['symptoms'] : null;
        if ($items === null) {
            return Response::json(['error' => 'symptoms array required'], 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Replace strategy: delete current rows, insert new.
            $del = $pdo->prepare('DELETE FROM visit_symptoms WHERE visit_id = :v');
            $del->execute([':v' => $visitId]);

            $ins = $pdo->prepare(
                'INSERT INTO visit_symptoms
                    (visit_id, clinic_id, master_id, label, source, severity, duration, sort_order)
                 VALUES (:v, :c, :m, :l, :s, :sev, :dur, :o)'
            );

            $saved = [];
            foreach (array_values($items) as $idx => $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') continue;
                if (mb_strlen($label) > 120) $label = mb_substr($label, 0, 120);

                // Determine source + master FK
                $hintedMaster = isset($row['master_id']) && $row['master_id'] !== '' ? (int) $row['master_id'] : null;
                if ($hintedMaster) {
                    $resolved = ['master_id' => $hintedMaster, 'source' => 'master'];
                } else {
                    $resolved = SymptomSearch::resolveLabel($doctorId, $clinicId, $label);
                }

                $severity = $row['severity'] ?? null;
                if (!in_array($severity, ['mild','moderate','severe'], true)) $severity = null;

                $duration = isset($row['duration']) ? mb_substr((string) $row['duration'], 0, 40) : null;
                if ($duration === '') $duration = null;

                $ins->execute([
                    ':v' => $visitId,
                    ':c' => $clinicId,
                    ':m' => $resolved['master_id'],
                    ':l' => $label,
                    ':s' => $resolved['source'],
                    ':sev' => $severity,
                    ':dur' => $duration,
                    ':o' => $idx,
                ]);

                // Bump counters (best-effort)
                if ($resolved['source'] === 'master' && $resolved['master_id']) {
                    SymptomSearch::recordMasterUse($resolved['master_id']);
                }
                // Always remember the doctor used this label personally — even
                // for master hits, so their personal recent list stays useful.
                SymptomSearch::recordPersonalUse($doctorId, $clinicId, $label);

                $saved[] = [
                    'label' => $label,
                    'source' => $resolved['source'],
                    'master_id' => $resolved['master_id'],
                ];
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::json(['error' => $e->getMessage()], 422);
        }

        AuditService::log($request, 'UPDATE', 'visit_symptoms', $visitId);

        return Response::json(['ok' => true, 'count' => count($saved), 'saved' => $saved]);
    }

    /** GET — return current symptoms for a visit (used by the visit screen on load). */
    public function listForVisit(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }
        $visitId = (int) $id;
        $clinicId = (int) RequestContext::clinicId();

        $stmt = Database::connection()->prepare(
            'SELECT id, master_id, label, source, severity, duration, sort_order
               FROM visit_symptoms
              WHERE visit_id = :v AND clinic_id = :c
              ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':v' => $visitId, ':c' => $clinicId]);

        return Response::json(['symptoms' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // =====================================================
    // Admin promotion queue
    // =====================================================

    public function promotionsIndex(Request $request): Response
    {
        $pdo = Database::connection();

        // Candidates: same LOWER(label) used by 10+ doctors with 30+ uses,
        // never yet promoted, never in master library.
        $sql = "
            SELECT LOWER(sp.label) AS norm_label,
                   ANY_VALUE(sp.label) AS label,
                   COUNT(DISTINCT sp.doctor_id) AS doctors,
                   SUM(sp.usage_count) AS total_uses,
                   MAX(sp.last_used_at) AS last_used,
                   ANY_VALUE(sp.id) AS sample_id
              FROM symptoms_personal sp
         LEFT JOIN symptoms_master sm ON LOWER(sm.label) = LOWER(sp.label)
             WHERE sp.promoted_to_master_id IS NULL
               AND sm.id IS NULL
          GROUP BY LOWER(sp.label)
            HAVING doctors >= 10 AND total_uses >= 30
          ORDER BY doctors DESC, total_uses DESC
             LIMIT 100
        ";
        $candidates = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return Response::html(View::render('admin/symptom_promotions', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'candidates' => $candidates,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    public function promote(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/symptom-promotions');
        }
        $label = trim((string) ($request->post['label'] ?? ''));
        $category = trim((string) ($request->post['category'] ?? '')) ?: null;
        $specialtiesRaw = trim((string) ($request->post['specialties'] ?? ''));

        if ($label === '') {
            return Response::redirect('/admin/symptom-promotions?message=empty_label');
        }

        $specialties = $specialtiesRaw !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $specialtiesRaw))))
            : null;

        $slug = self::slugify($label);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO symptoms_master
                    (label, slug, synonyms, specialties, category,
                     global_usage_count, is_active)
                 VALUES (:l, :s, NULL, :sp, :cat, 0, 1)
                 ON DUPLICATE KEY UPDATE is_active = 1'
            );
            $ins->execute([
                ':l' => $label,
                ':s' => $slug,
                ':sp' => $specialties ? json_encode($specialties) : null,
                ':cat' => $category,
            ]);
            $masterId = (int) $pdo->lastInsertId();
            if ($masterId === 0) {
                // Existed already — fetch the id.
                $q = $pdo->prepare('SELECT id FROM symptoms_master WHERE slug = :s LIMIT 1');
                $q->execute([':s' => $slug]);
                $masterId = (int) $q->fetchColumn();
            }

            // Link every personal row with this label (case-insensitive)
            // to the new master row so it doesn't keep appearing as custom.
            $link = $pdo->prepare(
                'UPDATE symptoms_personal
                    SET promoted_to_master_id = :m
                  WHERE LOWER(label) = LOWER(:l)
                    AND promoted_to_master_id IS NULL'
            );
            $link->execute([':m' => $masterId, ':l' => $label]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::redirect('/admin/symptom-promotions?message=error');
        }

        return Response::redirect('/admin/symptom-promotions?message=promoted');
    }

    public function ignore(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/symptom-promotions');
        }
        $label = trim((string) ($request->post['label'] ?? ''));
        if ($label === '') {
            return Response::redirect('/admin/symptom-promotions');
        }

        // "Ignore" = create a deactivated master row so we don't keep
        // re-suggesting it. is_active=0 means it won't appear in search.
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO symptoms_master (label, slug, is_active, global_usage_count)
             VALUES (:l, :s, 0, 0)
             ON DUPLICATE KEY UPDATE is_active = 0'
        );
        $stmt->execute([':l' => $label, ':s' => self::slugify($label)]);

        // Mark personal rows as resolved so they don't bubble up again.
        $link = $pdo->prepare(
            'UPDATE symptoms_personal
                SET promoted_to_master_id = (SELECT id FROM symptoms_master WHERE slug = :s LIMIT 1)
              WHERE LOWER(label) = LOWER(:l)
                AND promoted_to_master_id IS NULL'
        );
        $link->execute([':s' => self::slugify($label), ':l' => $label]);

        return Response::redirect('/admin/symptom-promotions?message=ignored');
    }

    private static function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s === '' ? 'symptom-' . bin2hex(random_bytes(4)) : mb_substr($s, 0, 120);
    }
}
