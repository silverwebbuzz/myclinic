<?php
// =====================================================================
// api/mobile/v1/work_health.php — Workforce Health, employee side.
//
// Thin adapter over partials/work_health.php — the same data layer the
// website's "Work health" tab uses (api/work_health.php).
//
//   GET  ?action=list                               (Bearer) → { show_tab, items }
//   POST ?action=respond { id, answer: confirm|decline } (Bearer) → { status }
//
// show_tab tells the app whether to show the Work health tab at all.
// Items: id, company, employee_no, name_on_record, job_title, department,
// branch, branch_city, employment_type, contractor_company,
// status (pending|confirmed), confirmed_at_utc, on_leave,
// upcoming_bookings[] (clinic, address, phone, at_local, checks),
// certificates[] (id, cert_no, exam_date, outcome, outcome_label, restrictions[],
// valid_to, review_by, status valid|revoked, shared_with_employer, clinic,
// has_pdf, verify_token).
//   GET  ?action=certificate&id=N                  (Bearer) → the PDF
//   POST ?action=upload_report (multipart: case_id, file, lab_name?, report_date?, note?) (Bearer)
//        → the report goes to the examining doctor + the user's Lab reports vault, never the employer.
//   GET  ?action=questionnaire&booking=N / POST ?action=submit_questionnaire {booking_id, answers} (Bearer)
//        → pre-exam health questionnaire; answers go to the examining doctor only.
// Items also carry reports_needed[] (case_id, check, kind, due_on, reports_uploaded).
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/work_health.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'upload_report': {
        // POST multipart ?action=upload_report: case_id, file, lab_name?, report_date?, note? (Bearer)
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $res = ecp_wh_report_upload((int) $me['id'], (int) ($_POST['case_id'] ?? 0), $_POST, $_FILES['file'] ?? null);
        if (!$res['ok']) {
            ecp_m_err('upload_failed', 422, ['message' => (string) ($res['error'] ?? 'Upload failed.')]);
        }
        ecp_m_ok(['uploaded' => true]);
        break;
    }
    case 'questionnaire': {
        // GET ?action=questionnaire&booking=N (Bearer) → { questionnaire: {name, intro, items[]}, answers }
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        $b = ecp_wh_q_booking((int) $me['id'], (int) ($_GET['booking'] ?? 0));
        $q = ecp_wh_questionnaire();
        if ($b === null || $q === null) {
            ecp_m_err('not_available', 404);
        }
        ecp_m_ok(['questionnaire' => ['name' => $q['name'], 'intro' => $q['intro'], 'items' => array_map(static fn ($i) => [
            'code' => $i['code'], 'section' => $i['section'], 'type' => $i['type'], 'text' => $i['text'], 'options' => $i['options'],
            'detail_prompt' => $i['detail_prompt'], 'required' => (int) $i['required'] === 1], $q['items'])], 'answers' => $b['answers']]);
        break;
    }
    case 'submit_questionnaire': {
        // POST JSON ?action=submit_questionnaire { booking_id, answers: { code: { v, d } } } (Bearer)
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $res = ecp_wh_q_save((int) $me['id'], (int) ($in['booking_id'] ?? 0), is_array($in['answers'] ?? null) ? $in['answers'] : []);
        if (!$res['ok']) {
            ecp_m_err('invalid', 422, ['message' => (string) ($res['error'] ?? 'Could not save.')]);
        }
        ecp_m_ok(['saved' => true]);
        break;
    }
    case 'certificate': {
        // GET ?action=certificate&id=N (Bearer) → application/pdf (own certificates only)
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        ecp_wh_certificate_download((int) $me['id'], (int) ($_GET['id'] ?? 0));
        break;
    }
    case 'list': {
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        $items = array_map('ecp_wh_shape', ecp_wh_list((int) $me['id']));
        ecp_m_ok(['show_tab' => $items !== [], 'items' => $items]);
        break;
    }

    case 'respond': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $res = ecp_wh_respond((int) $me['id'], (int) ($in['id'] ?? 0), (string) ($in['answer'] ?? ''));
        if (!$res['ok']) {
            ecp_m_err((string) $res['error'], $res['error'] === 'not_found' ? 404 : ($res['error'] === 'bad_action' ? 422 : 500));
        }
        ecp_m_ok(['status' => $res['status']]);
        break;
    }

    default:
        ecp_m_err('unknown_action', 404);
}
