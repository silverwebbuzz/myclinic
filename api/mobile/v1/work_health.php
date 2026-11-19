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
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/work_health.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
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
