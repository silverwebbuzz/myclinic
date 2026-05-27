<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Core\QueryBuilder;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\CsrfService;
use App\Services\DrugService;
use App\Services\PatientService;
use App\Services\PrescriptionPdfService;
use App\Services\PrescriptionService;
use App\Services\RemedyService;
use App\Services\VisitService;
use App\Support\Layout;
use PDO;

final class PrescriptionController
{
    public function index(Request $request): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $page = max(1, (int) ($request->query['page'] ?? 1));

        $filters = [
            'q' => $request->query['q'] ?? '',
            'mode' => $request->query['mode'] ?? '',
            'patient_id' => $request->query['patient_id'] ?? '',
            'from' => $request->query['from'] ?? '',
            'to' => $request->query['to'] ?? '',
        ];

        $result = PrescriptionService::listForClinic($clinicId, $filters, $page);

        return Response::html(Layout::page('prescriptions/index', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
        ], 'Prescriptions'));
    }

    public function downloadPdf(Request $request, string $visitId): Response
    {
        $clinicId = (int) \App\Core\RequestContext::clinicId();
        $visit = VisitService::findDetailed($clinicId, (int) $visitId);
        if ($visit === null) {
            return Response::html('Visit not found', 404);
        }

        $patient = PatientService::find($clinicId, (int) $visit['patient_id']) ?? [];
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
        $lines = PrescriptionService::forVisit($clinicId, (int) $visitId);

        try {
            $rel = PrescriptionPdfService::generate($visit, $patient, $clinic, $lines);
            $absolute = dirname(__DIR__, 2) . '/public' . $rel;
            if (!is_file($absolute)) {
                return Response::redirect('/prescriptions?error=' . urlencode('PDF could not be generated'));
            }
            $filename = 'rx-' . (string) ($patient['uhid'] ?? 'patient') . '-' . date('Ymd', strtotime((string) $visit['visited_at'])) . '.pdf';
            return Response::download($absolute, $filename);
        } catch (\Throwable $e) {
            error_log('[prescription PDF] ' . $e->getMessage());
            return Response::redirect('/prescriptions?error=' . urlencode('Could not generate PDF: ' . $e->getMessage()));
        }
    }

    // =====================================================
    // Phase 3 — drug / remedy autocomplete (ranked by usage_count)
    // =====================================================

    public function drugSearchApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }
        $q = trim((string) ($request->query['q'] ?? ''));
        return Response::json(['drugs' => DrugService::search($q)]);
    }

    public function remedySearchApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }
        $q = trim((string) ($request->query['q'] ?? ''));
        return Response::json(['remedies' => RemedyService::search($q)]);
    }

    // =====================================================
    // Phase 3 — prescription templates
    // =====================================================

    /**
     * GET /api/v1/prescriptions/templates?scope=mine|clinic|all
     * Returns active templates available to this doctor + clinic, ranked
     * by use_count. Auto-discovered suggestions (is_active=0) are returned
     * in a separate `suggestions` array so the UI can offer "Save as template?"
     */
    public function templatesIndex(Request $request): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);
        $scope = (string) ($request->query['scope'] ?? 'all');

        $pdo = Database::connection();

        $where = 't.clinic_id = :c AND t.is_active = 1';
        $params = [':c' => $clinicId];
        if ($scope === 'mine') {
            $where .= ' AND t.doctor_id = :d';
            $params[':d'] = $doctorId;
        } elseif ($scope === 'clinic') {
            $where .= ' AND t.doctor_id IS NULL';
        } else {
            $where .= ' AND (t.doctor_id IS NULL OR t.doctor_id = :d)';
            $params[':d'] = $doctorId;
        }

        $stmt = $pdo->prepare(
            "SELECT t.id, t.name, t.description, t.mode, t.doctor_id, t.use_count
               FROM prescription_templates t
              WHERE $where
              ORDER BY t.use_count DESC, t.name ASC
              LIMIT 50"
        );
        $stmt->execute($params);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auto-discovered suggestions for THIS doctor only (is_active=0).
        $sug = $pdo->prepare(
            'SELECT id, name, description, mode, use_count
               FROM prescription_templates
              WHERE doctor_id = :d AND is_active = 0 AND auto_discovered = 1
              ORDER BY created_at DESC
              LIMIT 10'
        );
        $sug->execute([':d' => $doctorId]);

        return Response::json([
            'templates' => $templates,
            'suggestions' => $sug->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    /**
     * GET /api/v1/prescriptions/templates/{id}
     * Returns the template header + items, ready to be cloned into a visit.
     */
    public function templateShow(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $tid = (int) $id;

        $pdo = Database::connection();
        $hdr = $pdo->prepare(
            'SELECT * FROM prescription_templates
              WHERE id = :id AND clinic_id = :c LIMIT 1'
        );
        $hdr->execute([':id' => $tid, ':c' => $clinicId]);
        $template = $hdr->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $items = $pdo->prepare(
            'SELECT i.*,
                    d.name AS drug_name,
                    r.name AS remedy_name
               FROM prescription_template_items i
          LEFT JOIN drugs d ON d.id = i.drug_id
          LEFT JOIN remedies r ON r.id = i.remedy_id
              WHERE i.template_id = :id
              ORDER BY i.sort_order ASC'
        );
        $items->execute([':id' => $tid]);

        return Response::json([
            'template' => $template,
            'items' => $items->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    /**
     * POST /api/v1/prescriptions/templates
     * Body: { name, description?, scope: 'mine'|'clinic', mode, items: [...] }
     * Creates a doctor-personal or clinic-wide template.
     */
    public function templateCreate(Request $request): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);

        $body = json_decode($request->rawBody ?? '{}', true);
        $name = trim((string) ($body['name'] ?? ''));
        $description = trim((string) ($body['description'] ?? '')) ?: null;
        $mode = ($body['mode'] ?? 'allopathic') === 'homeopathic' ? 'homeopathic' : 'allopathic';
        $scope = ($body['scope'] ?? 'mine') === 'clinic' ? 'clinic' : 'mine';
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];

        if ($name === '') {
            return Response::json(['error' => 'name required'], 422);
        }
        if ($items === []) {
            return Response::json(['error' => 'at least one item required'], 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO prescription_templates
                    (clinic_id, doctor_id, name, description, mode,
                     use_count, is_active, auto_discovered, created_at)
                 VALUES (:c, :d, :n, :desc, :m, 0, 1, 0, NOW())'
            );
            $ins->execute([
                ':c' => $clinicId,
                ':d' => $scope === 'clinic' ? null : $doctorId,
                ':n' => mb_substr($name, 0, 120),
                ':desc' => $description ? mb_substr($description, 0, 240) : null,
                ':m' => $mode,
            ]);
            $templateId = (int) $pdo->lastInsertId();

            $insItem = $pdo->prepare(
                'INSERT INTO prescription_template_items
                    (template_id, mode, drug_id, remedy_id, potency,
                     dose_unit, dose_amount, frequency_preset, duration_days,
                     food_timing, mix_with, tapering_steps, instructions, sort_order)
                 VALUES (:t, :m, :dr, :re, :po, :du, :da, :fp, :dd, :ft, :mw, :ts, :ins, :o)'
            );

            foreach (array_values($items) as $idx => $it) {
                $insItem->execute([
                    ':t' => $templateId,
                    ':m' => $mode,
                    ':dr' => $mode === 'allopathic' ? ($it['drug_id'] ?? null) : null,
                    ':re' => $mode === 'homeopathic' ? ($it['remedy_id'] ?? null) : null,
                    ':po' => $it['potency'] ?? null,
                    ':du' => $it['dose_unit'] ?? null,
                    ':da' => isset($it['dose_amount']) && $it['dose_amount'] !== '' ? (float) $it['dose_amount'] : null,
                    ':fp' => $it['frequency_preset'] ?? null,
                    ':dd' => isset($it['duration_days']) && $it['duration_days'] !== '' ? (int) $it['duration_days'] : null,
                    ':ft' => in_array($it['food_timing'] ?? 'any', ['before','after','with','empty','bedtime','any'], true)
                              ? ($it['food_timing'] ?? 'any') : 'any',
                    ':mw' => $it['mix_with'] ?? null,
                    ':ts' => isset($it['tapering_steps']) && is_array($it['tapering_steps']) && $it['tapering_steps'] !== []
                              ? json_encode($it['tapering_steps']) : null,
                    ':ins' => $it['instructions'] ?? null,
                    ':o' => $idx,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::json(['error' => $e->getMessage()], 422);
        }

        AuditService::log($request, 'INSERT', 'prescription_templates', $templateId);
        return Response::json(['ok' => true, 'template_id' => $templateId]);
    }

    /**
     * POST /api/v1/prescriptions/templates/{id}/apply/{visitId}
     * Copies template items into the given visit as prescriptions.
     * Returns the new prescription list so the UI can refresh.
     */
    public function templateApply(Request $request, string $id, string $visitId): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);
        $tid = (int) $id;
        $vid = (int) $visitId;

        $visit = VisitService::find($clinicId, $vid);
        if ($visit === null) {
            return Response::json(['error' => 'Visit not found'], 404);
        }
        if (!VisitService::isEditable($visit)) {
            return Response::json(['error' => 'Visit is read-only'], 422);
        }

        $pdo = Database::connection();

        // Verify template belongs to this clinic + scope is allowed.
        $hdr = $pdo->prepare(
            'SELECT * FROM prescription_templates
              WHERE id = :id AND clinic_id = :c LIMIT 1'
        );
        $hdr->execute([':id' => $tid, ':c' => $clinicId]);
        $template = $hdr->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            return Response::json(['error' => 'Template not found'], 404);
        }

        $items = $pdo->prepare(
            'SELECT * FROM prescription_template_items
              WHERE template_id = :id ORDER BY sort_order ASC'
        );
        $items->execute([':id' => $tid]);
        $rows = $items->fetchAll(PDO::FETCH_ASSOC);

        // Translate template items → autosave payload.prescriptions shape,
        // then reuse VisitService::autosave to write into prescriptions table.
        $prescriptions = [];
        foreach ($rows as $r) {
            $prescriptions[] = [
                'drug_id' => $r['drug_id'] ?? null,
                'remedy_id' => $r['remedy_id'] ?? null,
                'potency' => $r['potency'] ?? null,
                'dose_unit' => $r['dose_unit'] ?? null,
                'dose_amount' => $r['dose_amount'] ?? null,
                'frequency_preset' => $r['frequency_preset'] ?? null,
                'frequency' => self::presetToLegacy($r['frequency_preset'] ?? null),
                'duration_days' => $r['duration_days'] ?? null,
                'food_timing' => $r['food_timing'] ?? 'any',
                'mix_with' => $r['mix_with'] ?? null,
                'tapering_steps' => $r['tapering_steps'] ?? null,
                'instructions' => $r['instructions'] ?? null,
            ];
        }

        try {
            VisitService::autosave($clinicId, $vid, ['prescriptions' => $prescriptions]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }

        // Bump use_count + log.
        $pdo->prepare(
            'UPDATE prescription_templates
                SET use_count = use_count + 1, last_used_at = NOW()
              WHERE id = :id'
        )->execute([':id' => $tid]);

        $pdo->prepare(
            'INSERT INTO template_usage_log
                (template_id, doctor_id, clinic_id, visit_id, applied_at)
             VALUES (:t, :d, :c, :v, NOW())'
        )->execute([
            ':t' => $tid, ':d' => $doctorId, ':c' => $clinicId, ':v' => $vid,
        ]);

        AuditService::log($request, 'UPDATE', 'prescriptions', $vid);
        return Response::json([
            'ok' => true,
            'template_id' => $tid,
            'item_count' => count($prescriptions),
        ]);
    }

    /** POST /api/v1/prescriptions/templates/{id}/activate — claim an auto-discovered suggestion. */
    public function templateActivate(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);
        $tid = (int) $id;

        $body = json_decode($request->rawBody ?? '{}', true);
        $name = trim((string) ($body['name'] ?? ''));

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE prescription_templates
                SET is_active = 1,
                    name = COALESCE(NULLIF(:n, ""), name)
              WHERE id = :id AND clinic_id = :c AND doctor_id = :d'
        );
        $stmt->execute([
            ':n' => $name,
            ':id' => $tid,
            ':c' => $clinicId,
            ':d' => $doctorId,
        ]);

        return Response::json(['ok' => true]);
    }

    /** DELETE /api/v1/prescriptions/templates/{id} */
    public function templateDelete(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);
        $tid = (int) $id;

        $pdo = Database::connection();
        // Soft-delete by setting is_active=0; templates are referenced by
        // template_usage_log so a hard delete loses analytics.
        $stmt = $pdo->prepare(
            'UPDATE prescription_templates
                SET is_active = 0
              WHERE id = :id AND clinic_id = :c
                AND (doctor_id IS NULL OR doctor_id = :d)'
        );
        $stmt->execute([':id' => $tid, ':c' => $clinicId, ':d' => $doctorId]);
        return Response::json(['ok' => true]);
    }

    private static function presetToLegacy(?string $preset): ?string
    {
        // Reverse of the SQL Block-6 migration so the legacy enum stays in sync.
        return match ($preset) {
            '1-0-0' => 'OD',
            '0-0-1' => 'OD',
            '1-0-1' => 'BD',
            '0-1-0' => 'OD',
            '1-1-1' => 'TDS',
            '1-1-1-1' => 'QID',
            'SOS' => 'SOS',
            'WEEKLY' => 'weekly',
            'MONTHLY' => 'monthly',
            default => null,
        };
    }
}
