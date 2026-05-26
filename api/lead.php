<?php
// =====================================================================
// api/lead.php — patient-side lead submission.
//
//   POST /api/lead?action=submit    (logged-in patient required)
//     body: {
//       doctor_id:        int,
//       preferred_date:   "2026-05-20",
//       preferred_time:   "17:30",
//       reason:           "Back pain"   (optional)
//     }
//     → records a 'book_submitted' lead, triggers SMS to doctor
//
//   POST /api/lead?action=track       (anonymous OK)
//     body: { doctor_id:int, type:"view"|"call" }
//     → records a lightweight tracking event (for analytics)
//
// Auth: the existing patient session cookie (ecp_pid). If absent and the
// caller asks for 'submit', we return 401 — the JS opens the existing
// login modal, user signs in, callback re-submits. We never collect
// patient name/phone here; we read it from patient_identities via the
// session.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/directory_leads.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/lead] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'hint' => $e->getMessage()]);
    exit;
});

function out(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_starts_with(trim($raw), '{')) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method !== 'POST') out(405, ['ok' => false, 'error' => 'method_not_allowed']);

// ----- track: lightweight, anonymous OK -----
if ($action === 'track') {
    $in       = input_json();
    $doctorId = (int) ($in['doctor_id'] ?? 0);
    $type     = (string) ($in['type'] ?? 'view');
    if (!in_array($type, ['view', 'call'], true)) {
        out(400, ['ok' => false, 'error' => 'invalid_type']);
    }
    $me  = ecp_patient_current();   // may be null — that's fine for 'view'
    $res = ecp_lead_create($doctorId, $me, $type, []);
    out(200, $res);
}

// ----- submit: full booking lead, login required -----
if ($action === 'submit') {
    $me = ecp_patient_current();
    if (!$me) out(401, ['ok' => false, 'error' => 'login_required']);

    $in = input_json();
    $doctorId      = (int)    ($in['doctor_id'] ?? 0);
    $preferredDate = (string) ($in['preferred_date'] ?? '');
    $preferredTime = (string) ($in['preferred_time'] ?? '');
    $reason        = (string) ($in['reason'] ?? '');

    if ($doctorId <= 0)        out(400, ['ok' => false, 'error' => 'doctor_id_required']);
    if ($preferredDate === '') out(400, ['ok' => false, 'error' => 'date_required']);
    if ($preferredTime === '') out(400, ['ok' => false, 'error' => 'time_required']);

    // Enforce the 7-day booking window patients agreed to.
    $now    = strtotime(date('Y-m-d'));
    $picked = strtotime($preferredDate);
    if ($picked === false || $picked < $now) {
        out(400, ['ok' => false, 'error' => 'date_in_past']);
    }
    if ($picked > $now + (7 * 86400)) {
        out(400, ['ok' => false, 'error' => 'date_out_of_window']);
    }

    // Spam guard — same identity can't submit > 5 book_submitted in last hour.
    $db = ecp_db();
    if ($db) {
        $g = $db->prepare(
            'SELECT COUNT(*) FROM directory_leads
             WHERE patient_identity_id = :iid AND type = "book_submitted"
               AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $g->execute(['iid' => (int) $me['id']]);
        if ((int) $g->fetchColumn() >= 5) {
            out(429, ['ok' => false, 'error' => 'too_many_requests']);
        }
    }

    $res = ecp_lead_create($doctorId, $me, 'book_submitted', [
        'preferred_date' => $preferredDate,
        'preferred_time' => $preferredTime,
        'reason'         => $reason,
    ]);

    if (!$res['ok']) {
        out(400, ['ok' => false, 'error' => $res['error'] ?? 'lead_failed']);
    }

    out(200, [
        'ok'         => true,
        'lead_id'    => $res['lead_id'],
        'sms_status' => $res['sms_status'],
        'message'    => match ($res['sms_status']) {
            'sent'              => "We've notified the clinic. They'll call you within 24 hours.",
            'suppressed_quota'  => "Request recorded. The clinic will see it next time they sign in.",
            'suppressed_quiet'  => "Request recorded. The clinic will be notified after 8 AM.",
            'suppressed_paused' => "Request recorded. Note: this clinic has paused notifications.",
            'not_applicable'    => "Booking request received.",
            default             => "Request recorded.",
        },
    ]);
}

out(400, ['ok' => false, 'error' => 'unknown_action']);
