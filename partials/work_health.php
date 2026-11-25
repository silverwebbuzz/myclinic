<?php
// =====================================================================
// partials/work_health.php — the employee's side of Workforce Health.
//
// A company adds someone (corp_employments) by mobile number; that row
// points at the person's own patient_identities row with link_status
// 'pending'. Here the PERSON sees it in their "Work health" tab and says
// "Yes, that's me" (confirmed) or "This isn't me" (declined). Until they
// confirm, nothing about them flows to the company or anywhere else.
//
// Design: document/workforce-health-phase1-design.md §2.4 (P3).
// Used by: patient.php (tab), api/work_health.php, api/mobile/v1/work_health.php.
//
// Only companies eClinicPro has approved (status 'active') are shown.
// corp_* DATETIMEs are UTC (gmdate), as everywhere in Workforce Health.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * The person's current workplaces: pending and confirmed links, people who
 * haven't left. Declined ones are not shown again.
 *
 * @return list<array<string, mixed>>
 */
function ecp_wh_list(int $identityId): array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0) {
        return [];
    }
    try {
        $st = $db->prepare(
            "SELECT e.id, e.employee_no, e.full_name, e.link_status, e.link_confirmed_at, e.employment_type, e.contractor_company,
                    e.joined_on, e.status, e.created_at,
                    COALESCE(NULLIF(c.trade_name, ''), c.legal_name) AS company_name, c.country_code AS company_country,
                    b.name AS branch_name, b.city AS branch_city, j.title AS job_title, d.name AS department_name
               FROM corp_employments e
               JOIN corp_companies c ON c.id = e.company_id AND c.status = 'active'
               LEFT JOIN corp_branches b ON b.id = e.branch_id AND b.company_id = e.company_id
               LEFT JOIN corp_job_roles j ON j.id = e.job_role_id AND j.company_id = e.company_id
               LEFT JOIN corp_departments d ON d.id = e.department_id AND d.company_id = e.company_id
              WHERE e.identity_id = :iid AND e.link_status IN ('pending', 'confirmed') AND e.status <> 'terminated'
              ORDER BY e.link_status = 'pending' DESC, e.id DESC
              LIMIT 20"
        );
        $st->execute(['iid' => $identityId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Workforce tables not created yet on this server: the tab just stays hidden.
        return [];
    }
    $bookings = ecp_wh_bookings($identityId);
    $certs = ecp_wh_certificates($identityId);
    $reports = ecp_wh_report_cases($identityId);
    foreach ($rows as &$r) {
        $r['bookings'] = $r['link_status'] === 'confirmed' ? ($bookings[(int) $r['id']] ?? []) : [];
        $r['certificates'] = $r['link_status'] === 'confirmed' ? ($certs[(int) $r['id']] ?? []) : [];
        $r['reports_needed'] = $r['link_status'] === 'confirmed' ? ($reports[(int) $r['id']] ?? []) : [];
    }
    unset($r);

    return $rows;
}

/**
 * Open lab-test / X-ray checks the employee can upload a report for (1D-lite),
 * per employment id, for CONFIRMED links, with how many reports are already in.
 *
 * @return array<int, list<array<string, mixed>>>
 */
function ecp_wh_report_cases(int $identityId): array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0) {
        return [];
    }
    try {
        $st = $db->prepare(
            "SELECT c.id, c.employment_id, c.due_on, c.status, et.name AS exam_name, et.kind,
                    (SELECT COUNT(*) FROM occ_case_reports r WHERE r.company_id = c.company_id AND r.case_id = c.id AND r.is_active = 1) AS reports,
                    c.report_received_at
               FROM corp_screening_cases c
               JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
               JOIN oh_exam_types et ON et.id = c.exam_type_id
              WHERE e.identity_id = :iid AND e.link_status = 'confirmed' AND e.status <> 'terminated'
                AND c.status IN ('not_scheduled', 'scheduled') AND et.kind IN ('lab_test', 'imaging')
              ORDER BY c.due_on
              LIMIT 40"
        );
        $st->execute(['iid' => $identityId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $out[(int) $c['employment_id']][] = $c;
        }

        return $out;
    } catch (Throwable $e) {
        return [];   // 1D-lite patch not run yet
    }
}

/**
 * The employee uploads a test report for one of their open lab / X-ray checks.
 * The file goes to the clinic doctor (occ_case_reports, never the employer) and
 * a copy into the employee's own Lab reports vault. The employer only learns
 * "report received".
 *
 * @param array<string, mixed> $data lab_name, report_date, note
 * @param array<string, mixed>|null $file one $_FILES entry
 * @return array{ok: bool, error?: string}
 */
function ecp_wh_report_upload(int $identityId, int $caseId, array $data, ?array $file): array
{
    require_once __DIR__ . '/patient_lab_reports.php';
    $db = ecp_db();
    if (!$db || $identityId <= 0) {
        return ['ok' => false, 'error' => 'server_error'];
    }
    $st = $db->prepare(
        "SELECT c.id, c.company_id, c.employment_id, et.name AS exam_name
           FROM corp_screening_cases c
           JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
           JOIN oh_exam_types et ON et.id = c.exam_type_id
          WHERE c.id = :id AND e.identity_id = :iid AND e.link_status = 'confirmed' AND e.status <> 'terminated'
            AND c.status IN ('not_scheduled', 'scheduled') AND et.kind IN ('lab_test', 'imaging')
          LIMIT 1"
    );
    $st->execute(['id' => $caseId, 'iid' => $identityId]);
    $case = $st->fetch(PDO::FETCH_ASSOC);
    if (!$case) {
        return ['ok' => false, 'error' => 'This check is not open for a report any more.'];
    }
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Choose the report file (PDF or photo).'];
    }
    if ((int) ($file['size'] ?? 0) > ECP_LAB_MAX_BYTES) {
        return ['ok' => false, 'error' => 'File too large (max 10 MB).'];
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    if (!isset(ECP_LAB_MIMES[$mime])) {
        return ['ok' => false, 'error' => 'Allowed: PDF, JPG, PNG, WEBP.'];
    }
    $saved = ecp_wh_store_report((int) $case['company_id'], (string) $file['tmp_name'], ECP_LAB_MIMES[$mime], true);
    if ($saved === null) {
        return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    $lab = ecp_lab_clean((string) ($data['lab_name'] ?? ''), 160);
    $date = ecp_lab_normalize_date((string) ($data['report_date'] ?? ''));
    if ($date !== null && $date > date('Y-m-d')) {
        $date = null;
    }

    // Copy into the employee's own vault (their record, even after they change jobs).
    $vaultId = 0;
    $vaultDir = ecp_lab_dir($identityId);
    $vaultName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . ECP_LAB_MIMES[$mime];
    if (@copy(__DIR__ . '/../' . $saved, $vaultDir . '/' . $vaultName)) {
        $db->prepare(
            "INSERT INTO patient_lab_reports (owner_identity_id, label, test_type, lab_name, notes, reported_on, file_path, file_mime, source)
             VALUES (:o, :label, :type, :lab, :notes, :rep, :fp, :mime, 'upload')"
        )->execute(['o' => $identityId, 'label' => mb_substr($case['exam_name'] . ' (work health check)', 0, 160), 'type' => $case['exam_name'],
            'lab' => $lab, 'notes' => 'Shared with the examining doctor for your work health check', 'rep' => $date,
            'fp' => 'storage/patient_labs/' . $identityId . '/' . $vaultName, 'mime' => $mime]);
        $vaultId = (int) $db->lastInsertId();
    }

    $now = gmdate('Y-m-d H:i:s');
    $db->prepare(
        "INSERT INTO occ_case_reports (company_id, employment_id, case_id, identity_id, uploaded_by, uploader_id, clinic_id, file_path, file_mime,
                                       original_name, lab_name, report_date, note, vault_report_id, created_at)
         VALUES (:cid, :eid, :case, :iid, 'employee', :up, 0, :fp, :mime, :orig, :lab, :rep, :note, :vault, :at)"
    )->execute(['cid' => (int) $case['company_id'], 'eid' => (int) $case['employment_id'], 'case' => $caseId, 'iid' => $identityId, 'up' => $identityId,
        'fp' => $saved, 'mime' => $mime, 'orig' => mb_substr((string) ($file['name'] ?? ''), 0, 190), 'lab' => $lab, 'rep' => $date,
        'note' => ecp_lab_clean((string) ($data['note'] ?? ''), 255), 'vault' => $vaultId, 'at' => $now]);
    ecp_wh_mark_report_received((int) $case['company_id'], $caseId, 'employee', $identityId, (string) $case['exam_name']);

    return ['ok' => true];
}

/** Store a report under storage/occ_reports/{company_id}/ (outside the web root). @return string|null path relative to repo root */
function ecp_wh_store_report(int $companyId, string $tmp, string $ext, bool $uploaded): ?string
{
    $root = __DIR__ . '/../storage/occ_reports';
    $dir = $root . '/' . $companyId;
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        return null;
    }
    if (!is_file($root . '/.htaccess')) {
        @file_put_contents($root . '/.htaccess', "Require all denied\n");
    }
    $name = 'rep-' . date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $ext;
    $ok = $uploaded ? move_uploaded_file($tmp, $dir . '/' . $name) : @copy($tmp, $dir . '/' . $name);

    return $ok ? 'storage/occ_reports/' . $companyId . '/' . $name : null;
}

/** The company learns only "report received" (no file, no values). */
function ecp_wh_mark_report_received(int $companyId, int $caseId, string $actorType, int $actorId, string $examName): void
{
    $db = ecp_db();
    $now = gmdate('Y-m-d H:i:s');
    $db->prepare('UPDATE corp_screening_cases SET report_received_at = :at, updated_at = :up WHERE id = :id AND company_id = :cid')
       ->execute(['at' => $now, 'up' => $now, 'id' => $caseId, 'cid' => $companyId]);
    $db->prepare(
        "INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
         VALUES (:cid, :case, 'report_received', NULL, :t, :aid, :at)"
    )->execute(['cid' => $companyId, 'case' => $caseId, 't' => $actorType === 'clinic' ? 'provider' : 'employee', 'aid' => $actorId, 'at' => $now]);
    $db->prepare(
        "INSERT INTO corp_audit_log (company_id, actor_type, actor_id, action, entity_type, entity_id, details, created_at)
         VALUES (:cid, :t, :aid, 'report_received', 'corp_screening_case', :case, :d, :at)"
    )->execute(['cid' => $companyId, 't' => $actorType === 'clinic' ? 'provider' : 'employee', 'aid' => $actorId, 'case' => $caseId,
        'd' => json_encode(['exam' => $examName]), 'at' => $now]);
}

/**
 * Phase 2D: the published pre-exam questionnaire with its items (null if none / patch not run).
 *
 * @return array{id:int, name:string, intro:?string, items: list<array<string, mixed>>}|null
 */
function ecp_wh_questionnaire(): ?array
{
    $db = ecp_db();
    if (!$db) {
        return null;
    }
    try {
        $q = $db->query("SELECT id, name, intro FROM oh_questionnaires WHERE status = 'published' ORDER BY code = 'PRE-EXAM' DESC, version DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$q) {
            return null;
        }
        $st = $db->prepare('SELECT code, section, type, text, options_json, detail_prompt, required FROM oh_questionnaire_items WHERE questionnaire_id = :q ORDER BY sort_order, id');
        $st->execute(['q' => (int) $q['id']]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$i) {
            $i['options'] = json_decode((string) ($i['options_json'] ?? '[]'), true) ?: [];
        }

        return ['id' => (int) $q['id'], 'name' => (string) $q['name'], 'intro' => $q['intro'], 'items' => $items];
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * One of the person's own upcoming bookings (confirmed link) + its saved answers, or null.
 *
 * @return array<string, mixed>|null
 */
function ecp_wh_q_booking(int $identityId, int $bookingId): ?array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0 || $bookingId <= 0) {
        return null;
    }
    try {
        $st = $db->prepare(
            "SELECT b.id, b.company_id, b.employment_id, b.scheduled_at, t.name AS clinic_name, COALESCE(NULLIF(c.trade_name, ''), c.legal_name) AS company_name
               FROM corp_bookings b
               JOIN corp_employments e ON e.id = b.employment_id AND e.company_id = b.company_id
               JOIN corp_companies c ON c.id = b.company_id
               JOIN tenants t ON t.id = b.clinic_id
              WHERE b.id = :id AND e.identity_id = :iid AND e.link_status = 'confirmed' AND b.status = 'booked' LIMIT 1"
        );
        $st->execute(['id' => $bookingId, 'iid' => $identityId]);
        $b = $st->fetch(PDO::FETCH_ASSOC);
        if (!$b) {
            return null;
        }
        $r = $db->prepare('SELECT answers_json FROM occ_questionnaire_responses WHERE company_id = :cid AND booking_id = :b LIMIT 1');
        $r->execute(['cid' => (int) $b['company_id'], 'b' => $bookingId]);
        $b['answers'] = json_decode((string) ($r->fetchColumn() ?: '[]'), true) ?: [];

        return $b;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Same rules as App\Services\OccHealth\QuestionnaireService::normalize (keep in step).
 *
 * @return array{answers: array<string, array<string, string>>, error: ?string, has_yes: bool}
 */
function ecp_wh_q_normalize(array $q, array $posted): array
{
    $answers = [];
    $hasYes = false;
    foreach ($q['items'] as $it) {
        $code = (string) $it['code'];
        $v = trim((string) ($posted[$code]['v'] ?? ''));
        $d = mb_substr(trim((string) ($posted[$code]['d'] ?? '')), 0, 500);
        if ($it['type'] === 'yes_no' && !in_array($v, ['yes', 'no'], true)) {
            $v = '';
        }
        if ($it['type'] === 'choice' && !in_array($v, $it['options'], true)) {
            $v = '';
        }
        if ($it['type'] === 'text') {
            $v = mb_substr($v, 0, 1000);
        }
        if ($v === '' && (int) $it['required'] === 1) {
            return ['answers' => [], 'error' => 'Please answer: "' . $it['text'] . '"', 'has_yes' => false];
        }
        if ($it['type'] !== 'yes_no' || $v !== 'yes') {
            $d = '';
        } else {
            $hasYes = true;
        }
        $answers[$code] = ['q' => (string) $it['text'], 's' => (string) $it['section'], 'v' => $v, 'd' => $d];
    }

    return ['answers' => $answers, 'error' => null, 'has_yes' => $hasYes];
}

/** The employee submits (or updates) their questionnaire for one booking. @return array{ok: bool, error?: string} */
function ecp_wh_q_save(int $identityId, int $bookingId, array $posted): array
{
    $b = ecp_wh_q_booking($identityId, $bookingId);
    $q = ecp_wh_questionnaire();
    if ($b === null || $q === null) {
        return ['ok' => false, 'error' => 'This booking is not open for a questionnaire any more.'];
    }
    $n = ecp_wh_q_normalize($q, $posted);
    if ($n['error'] !== null) {
        return ['ok' => false, 'error' => $n['error']];
    }
    $db = ecp_db();
    $now = gmdate('Y-m-d H:i:s');
    $db->prepare(
        "INSERT INTO occ_questionnaire_responses (company_id, employment_id, booking_id, questionnaire_id, answers_json, has_yes, submitted_by, submitter_id, submitted_at, updated_at)
         VALUES (:cid, :eid, :b, :q, :a, :y, 'employee', :iid, :at, :up)
         ON DUPLICATE KEY UPDATE questionnaire_id = VALUES(questionnaire_id), answers_json = VALUES(answers_json), has_yes = VALUES(has_yes),
                                 submitted_by = VALUES(submitted_by), submitter_id = VALUES(submitter_id), updated_at = VALUES(updated_at)"
    )->execute(['cid' => (int) $b['company_id'], 'eid' => (int) $b['employment_id'], 'b' => $bookingId, 'q' => (int) $q['id'],
        'a' => json_encode($n['answers'], JSON_UNESCAPED_UNICODE), 'y' => $n['has_yes'] ? 1 : 0, 'iid' => $identityId, 'at' => $now, 'up' => $now]);
    $db->prepare('UPDATE corp_bookings SET questionnaire_done_at = COALESCE(questionnaire_done_at, :at) WHERE id = :id AND company_id = :cid')
       ->execute(['at' => $now, 'id' => $bookingId, 'cid' => (int) $b['company_id']]);
    $db->prepare(
        "INSERT INTO corp_audit_log (company_id, actor_type, actor_id, action, entity_type, entity_id, details, created_at)
         VALUES (:cid, 'employee', :iid, 'questionnaire_done', 'corp_booking', :b, NULL, :at)"
    )->execute(['cid' => (int) $b['company_id'], 'iid' => $identityId, 'b' => $bookingId, 'at' => $now]);

    return ['ok' => true];
}

/** Outcome labels (same as App\Services\OccHealth\OccExamService). */
const ECP_WH_OUTCOMES = ['fit' => 'Fit', 'fit_restricted' => 'Fit with restrictions', 'temp_unfit' => 'Temporarily unfit',
    'unfit' => 'Unfit', 'further_assessment' => 'Further assessment required'];
const ECP_WH_RESTRICTIONS = ['no_height' => 'No work at height', 'no_driving' => 'No driving, crane or heavy-plant operation',
    'no_heavy_lifting' => 'No lifting over 20 kg', 'no_night' => 'No night shifts', 'no_confined' => 'No work in confined spaces',
    'noise_ppe' => 'Hearing protection required in noise areas', 'no_hot_work' => 'No hot work or welding', 'no_food' => 'No food handling'];

/**
 * The employee's own fitness certificates (Phase 1C), per employment id, for
 * CONFIRMED links. The employee always sees their own certificate, whether or
 * not they agreed to share it with the employer.
 *
 * @return array<int, list<array<string, mixed>>>
 */
function ecp_wh_certificates(int $identityId): array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0) {
        return [];
    }
    try {
        $st = $db->prepare(
            "SELECT f.id, f.employment_id, f.cert_no, f.exam_date, f.outcome, f.restrictions_json, f.restrictions_note, f.valid_to, f.review_by,
                    f.consent_shared, f.status, f.checks_json, f.verify_token, (f.pdf_path IS NOT NULL) AS has_pdf
               FROM corp_fitness_certificates f
               JOIN corp_employments e ON e.id = f.employment_id AND e.company_id = f.company_id
              WHERE e.identity_id = :iid AND e.link_status = 'confirmed' AND f.status IN ('valid', 'revoked')
              ORDER BY f.id DESC
              LIMIT 30"
        );
        $st->execute(['iid' => $identityId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $out[(int) $c['employment_id']][] = $c;
        }

        return $out;
    } catch (Throwable $e) {
        return [];   // 1C patch not run yet
    }
}

/**
 * Stream one of the signed-in person's own certificate PDFs, or 404. Ends the request.
 */
function ecp_wh_certificate_download(int $identityId, int $certId): void
{
    $db = ecp_db();
    $row = null;
    if ($db && $identityId > 0 && $certId > 0) {
        try {
            $st = $db->prepare(
                "SELECT f.cert_no, f.pdf_path FROM corp_fitness_certificates f
                   JOIN corp_employments e ON e.id = f.employment_id AND e.company_id = f.company_id
                  WHERE f.id = :id AND e.identity_id = :iid AND e.link_status = 'confirmed' LIMIT 1"
            );
            $st->execute(['id' => $certId, 'iid' => $identityId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $row = null;
        }
    }
    $root = realpath(__DIR__ . '/../storage/corporate_certs');
    $abs = ($row && $root !== false && !empty($row['pdf_path'])) ? realpath($root . '/' . $row['pdf_path']) : false;
    if ($abs === false || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || !is_file($abs)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Certificate not found.';
        exit;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) $row['cert_no']) . '.pdf"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . filesize($abs));
    readfile($abs);
    exit;
}

/**
 * Upcoming health-check appointments the employer booked (Phase 1C), per
 * employment id, for CONFIRMED links only. Clinic name/address/time and the
 * names of the checks; nothing clinical.
 *
 * @return array<int, list<array<string, mixed>>>
 */
function ecp_wh_bookings(int $identityId): array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0) {
        return [];
    }
    try {
        $st = $db->prepare(
            "SELECT b.id, b.employment_id, b.scheduled_at, b.questionnaire_done_at, t.name AS clinic_name, t.address AS clinic_address, t.phone AS clinic_phone,
                    (SELECT GROUP_CONCAT(et.name ORDER BY et.sort_order SEPARATOR ', ') FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
                      WHERE c.company_id = b.company_id AND c.booking_id = b.id) AS checks
               FROM corp_bookings b
               JOIN corp_employments e ON e.id = b.employment_id AND e.company_id = b.company_id
               JOIN tenants t ON t.id = b.clinic_id
              WHERE e.identity_id = :iid AND e.link_status = 'confirmed' AND b.status = 'booked'
                AND b.scheduled_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              ORDER BY b.scheduled_at
              LIMIT 20"
        );
        $st->execute(['iid' => $identityId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $out[(int) $b['employment_id']][] = $b;
        }

        return $out;
    } catch (Throwable $e) {
        return [];   // 1C patch not run yet
    }
}

/** Should the "Work health" tab show? One cheap query per page view. */
function ecp_wh_has_any(int $identityId): bool
{
    return ecp_wh_list($identityId) !== [];
}

/**
 * The employee's answer.
 *
 * @param 'confirm'|'decline' $answer
 * @return array{ok: bool, error?: string, status?: string}
 */
function ecp_wh_respond(int $identityId, int $employmentId, string $answer): array
{
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'unavailable'];
    }
    if (!in_array($answer, ['confirm', 'decline'], true)) {
        return ['ok' => false, 'error' => 'bad_action'];
    }
    $st = $db->prepare(
        "SELECT e.id, e.company_id, e.employee_no, e.link_status
           FROM corp_employments e
           JOIN corp_companies c ON c.id = e.company_id AND c.status = 'active'
          WHERE e.id = :id AND e.identity_id = :iid AND e.status <> 'terminated' LIMIT 1"
    );
    $st->execute(['id' => $employmentId, 'iid' => $identityId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if ($row['link_status'] !== 'pending') {
        // Already answered: report the current state (double taps are harmless).
        return ['ok' => true, 'status' => (string) $row['link_status']];
    }

    $now = gmdate('Y-m-d H:i:s');
    $new = $answer === 'confirm' ? 'confirmed' : 'declined';
    $cid = (int) $row['company_id'];
    try {
        $db->beginTransaction();
        $up = $db->prepare(
            "UPDATE corp_employments SET link_status = :ls, link_confirmed_at = :lca, updated_at = :upd
              WHERE id = :id AND company_id = :cid AND identity_id = :iid AND link_status = 'pending'"
        );
        $up->execute([
            'ls' => $new, 'lca' => $new === 'confirmed' ? $now : null, 'upd' => $now,
            'id' => $employmentId, 'cid' => $cid, 'iid' => $identityId,
        ]);
        if ($up->rowCount() === 1) {
            $db->prepare(
                'INSERT INTO corp_employment_events (company_id, employment_id, event, from_json, to_json, effective_on, actor_type, actor_id, created_at)
                 VALUES (:cid, :eid, :ev, :fj, :tj, NULL, \'employee\', :aid, :t)'
            )->execute([
                'cid' => $cid, 'eid' => $employmentId, 'ev' => $new === 'confirmed' ? 'linked' : 'link_declined',
                'fj' => json_encode(['link_status' => 'pending']), 'tj' => json_encode(['link_status' => $new]),
                'aid' => $identityId, 't' => $now,
            ]);
            $db->prepare(
                'INSERT INTO corp_audit_log (company_id, actor_type, actor_id, action, entity_type, entity_id, details, ip, user_agent, created_at)
                 VALUES (:cid, \'employee\', :aid, :act, \'corp_employment\', :eid, :det, :ip, :ua, :t)'
            )->execute([
                'cid' => $cid, 'aid' => $identityId, 'act' => $new === 'confirmed' ? 'employee_link_confirmed' : 'employee_link_declined',
                'eid' => $employmentId, 'det' => json_encode(['employee_no' => $row['employee_no']]),
                'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null, 't' => $now,
            ]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[work_health] respond: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'server_error'];
    }

    return ['ok' => true, 'status' => $new];
}

/**
 * The same rows shaped for JSON (web + mobile). No internal ids except the
 * employment id the answer endpoint needs.
 *
 * @param array<string, mixed> $r
 * @return array<string, mixed>
 */
function ecp_wh_shape(array $r): array
{
    return [
        'id' => (int) $r['id'],
        'company' => (string) $r['company_name'],
        'employee_no' => (string) $r['employee_no'],
        'name_on_record' => (string) $r['full_name'],
        'job_title' => $r['job_title'] !== null ? (string) $r['job_title'] : null,
        'department' => $r['department_name'] !== null ? (string) $r['department_name'] : null,
        'branch' => $r['branch_name'] !== null ? (string) $r['branch_name'] : null,
        'branch_city' => $r['branch_city'] !== null ? (string) $r['branch_city'] : null,
        'employment_type' => (string) $r['employment_type'],
        'contractor_company' => $r['contractor_company'] !== null ? (string) $r['contractor_company'] : null,
        'status' => (string) $r['link_status'],            // pending | confirmed
        'confirmed_at_utc' => $r['link_confirmed_at'] !== null ? (string) $r['link_confirmed_at'] : null,
        'on_leave' => $r['status'] === 'on_leave',
        'upcoming_bookings' => array_map(static fn (array $b): array => [
            'clinic' => (string) $b['clinic_name'],
            'address' => $b['clinic_address'] !== null ? (string) $b['clinic_address'] : null,
            'phone' => $b['clinic_phone'] !== null ? (string) $b['clinic_phone'] : null,
            'at_local' => (string) $b['scheduled_at'],          // clinic's local time, Y-m-d H:i:s
            'checks' => $b['checks'] !== null ? (string) $b['checks'] : '',
            'booking_id' => (int) $b['id'],
            'questionnaire_done' => !empty($b['questionnaire_done_at']),
        ], $r['bookings'] ?? []),
        'reports_needed' => array_map(static fn (array $c): array => [
            'case_id' => (int) $c['id'],
            'check' => (string) $c['exam_name'],
            'kind' => (string) $c['kind'],                     // lab_test | imaging
            'due_on' => (string) $c['due_on'],
            'reports_uploaded' => (int) $c['reports'],
        ], $r['reports_needed'] ?? []),
        'certificates' => array_map(static function (array $c): array {
            $restr = array_map(static fn ($k) => ECP_WH_RESTRICTIONS[$k] ?? (string) $k, json_decode((string) ($c['restrictions_json'] ?? '[]'), true) ?: []);
            if (!empty($c['restrictions_note'])) {
                $restr[] = (string) $c['restrictions_note'];
            }
            $snap = json_decode((string) ($c['checks_json'] ?? ''), true) ?: [];

            return [
                'id' => (int) $c['id'],
                'cert_no' => (string) $c['cert_no'],
                'exam_date' => (string) $c['exam_date'],
                'outcome' => (string) $c['outcome'],
                'outcome_label' => ECP_WH_OUTCOMES[$c['outcome']] ?? (string) $c['outcome'],
                'restrictions' => $restr,
                'valid_to' => $c['valid_to'] !== null ? (string) $c['valid_to'] : null,
                'review_by' => $c['review_by'] !== null ? (string) $c['review_by'] : null,
                'status' => (string) $c['status'],                   // valid | revoked
                'shared_with_employer' => (int) $c['consent_shared'] === 1,
                'clinic' => (string) ($snap['issuer']['clinic'] ?? ''),
                'has_pdf' => (int) $c['has_pdf'] === 1,
                'verify_token' => (string) $c['verify_token'],
            ];
        }, $r['certificates'] ?? []),
    ];
}
