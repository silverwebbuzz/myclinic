<?php
// =====================================================================
// api/lab_book.php — submit a lab booking from the "Book Now" form.
//
//   POST /api/lab_book        (JSON body)
//
// Auth: ecp_pid cookie (ecp_patient_current). 401 when logged out — the form
// already gates submit behind ecpAuth.require('lab_booking'); this enforces it
// server-side so the endpoint can't be posted to directly while logged out.
//
// Order of operations matters: PERSIST FIRST, THEN EMAIL. Mail is best-effort
// (ecp_send_mail returns false on failure and never throws), so a mail outage
// must not lose a booking the patient believes they made.
//
// Every amount is recomputed server-side in ecp_lab_price_order() — see the
// note there. The posted total is only used to log a drift warning.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/lab_orders.php';
require_once __DIR__ . '/../partials/lab_order_mail.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/lab_book] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error',
                      'message' => 'Something went wrong. Please try again.']);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$me = ecp_patient_current();
if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'login_required',
                      'message' => 'Please log in to complete your booking.']);
    exit;
}

// JSON body (the form serialises itself); fall back to form-encoded.
$raw = file_get_contents('php://input') ?: '';
$in = [];
if ($raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $in = json_decode($raw, true) ?: [];
} else {
    $in = $_POST;
}

// Default the contact name to the account holder when the form didn't send one.
if (trim((string) ($in['contact_name'] ?? '')) === '') {
    $in['contact_name'] = trim((string) ($me['name'] ?? ''));
}

$res = ecp_lab_order_create((int) $me['id'], $in);

if (!$res['ok']) {
    // Validation problems are the client's to fix; 'server_error' is ours.
    http_response_code($res['error'] === 'server_error' ? 500 : 422);
    echo json_encode([
        'ok'      => false,
        'error'   => $res['error'],
        'message' => $res['message'] ?? 'Please check the form and try again.',
    ]);
    exit;
}

$order = $res['order'];

// ── Email (best effort, after the order is safely stored) ───────────────
// Both calls BCC MAIL_ARCHIVE_BCC (eclinicpro.com@gmail.com) automatically.
$patientSent = false;
$opsSent = false;
try {
    $patientSent = ecp_lab_mail_patient($order);
} catch (Throwable $e) {
    error_log('[api/lab_book] patient mail: ' . $e->getMessage());
}
try {
    $opsSent = ecp_lab_mail_ops($order);
} catch (Throwable $e) {
    error_log('[api/lab_book] ops mail: ' . $e->getMessage());
}

// Record what actually went out, so a silent SMTP outage is visible in the data
// rather than only in the error log.
if ($patientSent || $opsSent) {
    try {
        $db = ecp_db();
        if ($db) {
            $upd = $db->prepare(
                'UPDATE lab_orders SET patient_email_sent = :p, admin_email_sent = :a WHERE id = :id'
            );
            $upd->execute([
                'p'  => $patientSent ? 1 : 0,
                'a'  => $opsSent ? 1 : 0,
                'id' => (int) $order['id'],
            ]);
        }
    } catch (Throwable $e) {
        error_log('[api/lab_book] email flag update: ' . $e->getMessage());
    }
}

echo json_encode([
    'ok'        => true,
    'order_ref' => $order['order_ref'],
    'total'     => ecp_lab_inr((int) $order['total_paise']),
    'status'    => 'pending',
    'email'     => $order['email'],
    // The UI says "a confirmation is on its way" only when one actually is.
    'email_sent' => $patientSent,
    'message'   => 'Booking request received. Reference ' . $order['order_ref'] . '.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
