<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Application;
use App\Core\Database;
use App\Http\Response;
use App\Services\Corporate\CorpAudit;
use App\Support\Locale\LocalDate;

/**
 * Test reports for lab / X-ray checks (Workforce Health 1D-lite). Uploaded by
 * the employee (patient panel / app) or by the clinic on the visit. Seen ONLY
 * by the examining clinic and the employee (a copy goes to their Lab reports
 * vault). The employer learns just "report received"
 * (corp_screening_cases.report_received_at). Company code may never query
 * occ_case_reports (CorpFirewallGuardTest).
 */
final class CaseReportService
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MIME_TO_EXT = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * Active reports for these checks, grouped by case id.
     *
     * @param list<int> $caseIds
     * @return array<int, list<array<string, mixed>>>
     */
    public static function forCases(int $companyId, array $caseIds): array
    {
        $caseIds = array_values(array_unique(array_filter(array_map('intval', $caseIds))));
        if ($caseIds === []) {
            return [];
        }
        $params = ['cid' => $companyId];
        $in = [];
        foreach ($caseIds as $i => $id) {
            $in[] = ':c' . $i;
            $params['c' . $i] = $id;
        }
        try {
            $st = Database::connection()->prepare(
                'SELECT id, case_id, uploaded_by, clinic_id, file_mime, original_name, lab_name, report_date, note, created_at
                   FROM occ_case_reports WHERE company_id = :cid AND is_active = 1 AND case_id IN (' . implode(',', $in) . ') ORDER BY id DESC'
            );
            $st->execute($params);
        } catch (\Throwable $e) {
            return [];   // 1D-lite patch not run yet
        }
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['case_id']][] = $r;
        }

        return $out;
    }

    /**
     * Clinic staff attach a report (e.g. the employee brought paper) to a lab / X-ray check on this visit's booking.
     *
     * @param array<string, mixed> $visit
     * @param array<string, mixed> $post lab_name, report_date, note
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, error?: string}
     */
    public static function clinicUpload(int $clinicId, int $userId, array $visit, int $caseId, array $post, ?array $file): array
    {
        $panel = OccExamService::forVisit($clinicId, $visit, $userId);
        $case = null;
        foreach ($panel['cases'] ?? [] as $c) {
            if ((int) $c['id'] === $caseId && in_array($c['kind'], ['lab_test', 'imaging'], true)) {
                $case = $c;
            }
        }
        if ($panel === null || $case === null) {
            return ['ok' => false, 'error' => 'Choose a lab test or X-ray check of this booking.'];
        }
        if (!in_array($case['status'], ['scheduled', 'not_scheduled'], true)) {
            return ['ok' => false, 'error' => 'That check is already closed.'];
        }
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Choose the report file (PDF or photo).'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File too large (max 10 MB).'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $ext = self::MIME_TO_EXT[$mime] ?? null;
        if ($ext === null) {
            return ['ok' => false, 'error' => 'Allowed: PDF, JPG, PNG, WEBP.'];
        }
        $booking = $panel['booking'];
        $companyId = (int) $booking['company_id'];
        $repo = dirname(Application::basePath());
        $dir = $repo . '/storage/occ_reports/' . $companyId;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not save the file.'];
        }
        if (!is_file($repo . '/storage/occ_reports/.htaccess')) {
            @file_put_contents($repo . '/storage/occ_reports/.htaccess', "Require all denied\n");
        }
        $name = 'rep-' . date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        $rel = 'storage/occ_reports/' . $companyId . '/' . $name;
        $lab = mb_substr(trim((string) ($post['lab_name'] ?? '')), 0, 160) ?: null;
        $date = trim((string) ($post['report_date'] ?? ''));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && $date <= date('Y-m-d') ? $date : null;
        $note = mb_substr(trim((string) ($post['note'] ?? '')), 0, 255) ?: null;
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT identity_id FROM corp_employments WHERE id = :id AND company_id = :cid');
        $st->execute(['id' => (int) $booking['employment_id'], 'cid' => $companyId]);
        $identityId = (int) ($st->fetchColumn() ?: 0);

        // Copy into the employee's own Lab reports vault (L5), as shared by the clinic.
        $vaultId = 0;
        if ($identityId > 0) {
            $vdir = $repo . '/storage/patient_labs/' . $identityId;
            $vname = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if ((is_dir($vdir) || @mkdir($vdir, 0775, true)) && @copy($dir . '/' . $name, $vdir . '/' . $vname)) {
                try {
                    $pdo->prepare(
                        "INSERT INTO patient_lab_reports (owner_identity_id, label, test_type, lab_name, doctor_name, notes, reported_on, file_path, file_mime, source, source_clinic_id)
                         VALUES (:o, :label, :type, :lab, :doc, :notes, :rep, :fp, :mime, 'clinic', :clinic)"
                    )->execute(['o' => $identityId, 'label' => mb_substr($case['exam_name'] . ' (work health check)', 0, 160), 'type' => $case['exam_name'],
                        'lab' => $lab ?? $booking['clinic_name'], 'doc' => null, 'notes' => 'Added by ' . $booking['clinic_name'] . ' for your work health check',
                        'rep' => $date, 'fp' => 'storage/patient_labs/' . $identityId . '/' . $vname, 'mime' => $mime, 'clinic' => $clinicId]);
                    $vaultId = (int) $pdo->lastInsertId();
                } catch (\Throwable $e) {
                    error_log('[CaseReportService] vault copy: ' . $e->getMessage());
                }
            }
        }
        $now = LocalDate::nowUtc();
        $pdo->prepare(
            "INSERT INTO occ_case_reports (company_id, employment_id, case_id, identity_id, uploaded_by, uploader_id, clinic_id, file_path, file_mime,
                                           original_name, lab_name, report_date, note, vault_report_id, created_at)
             VALUES (:cid, :eid, :case, :iid, 'clinic', :up, :clinic, :fp, :mime, :orig, :lab, :rep, :note, :vault, :at)"
        )->execute(['cid' => $companyId, 'eid' => (int) $booking['employment_id'], 'case' => $caseId, 'iid' => $identityId, 'up' => $userId, 'clinic' => $clinicId,
            'fp' => $rel, 'mime' => $mime, 'orig' => mb_substr((string) ($file['name'] ?? ''), 0, 190), 'lab' => $lab, 'rep' => $date, 'note' => $note,
            'vault' => $vaultId, 'at' => $now]);
        $pdo->prepare('UPDATE corp_screening_cases SET report_received_at = :at, updated_at = :up WHERE id = :id AND company_id = :cid')
            ->execute(['at' => $now, 'up' => $now, 'id' => $caseId, 'cid' => $companyId]);
        $pdo->prepare(
            "INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
             VALUES (:cid, :case, 'report_received', NULL, 'provider', :uid, :at)"
        )->execute(['cid' => $companyId, 'case' => $caseId, 'uid' => $userId, 'at' => $now]);
        CorpAudit::log($companyId, 'provider', $userId, 'report_received', 'corp_screening_case', $caseId, ['clinic' => $booking['clinic_name'], 'exam' => $case['exam_name']]);

        return ['ok' => true];
    }

    /** Stream a report the visit's clinic may see (it belongs to a check on this visit's booking). */
    public static function streamForVisit(int $clinicId, array $visit, int $userId, int $reportId): Response
    {
        $panel = OccExamService::forVisit($clinicId, $visit, $userId);
        if ($panel === null) {
            return Response::html('Not found', 404);
        }
        $caseIds = array_map(static fn ($c) => (int) $c['id'], $panel['cases']);
        $st = Database::connection()->prepare('SELECT * FROM occ_case_reports WHERE id = :id AND company_id = :cid AND is_active = 1 LIMIT 1');
        $st->execute(['id' => $reportId, 'cid' => (int) $panel['booking']['company_id']]);
        $r = $st->fetch();
        if (!$r || !in_array((int) $r['case_id'], $caseIds, true)) {
            return Response::html('Not found', 404);
        }
        $repo = realpath(dirname(Application::basePath()) . '/storage/occ_reports');
        $abs = $repo !== false ? realpath(dirname($repo, 2) . '/' . $r['file_path']) : false;
        if ($abs === false || !str_starts_with($abs, $repo . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return Response::html('File not found', 404);
        }

        return new Response((string) file_get_contents($abs), 200, [
            'Content-Type' => (string) $r['file_mime'],
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) ($r['original_name'] ?: basename($abs))) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
