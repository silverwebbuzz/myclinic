<?php
// =====================================================================
// api/patient_lab_orders.php — the "Lab bookings" tab in the patient panel.
//
//   GET  /api/patient_lab_orders                → list (newest first)
//   GET  /api/patient_lab_orders?action=detail&id=…  → one order + bill + people
//   POST /api/patient_lab_orders?action=cancel  → cancel a pending booking
//
// Auth: ecp_pid cookie (ecp_patient_current). 401 when logged out.
// Ownership is enforced inside every query in partials/lab_orders.php, so a
// guessed id returns 404 rather than someone else's order.
//
// Note this is the ORDERS vault (what the patient booked). The separate
// patient_lab_reports API is the RESULTS vault (files they uploaded).
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/lab_orders.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/patient_lab_orders] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

$me = ecp_patient_current();
if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}
$identityId = (int) $me['id'];

$action = (string) ($_GET['action'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

switch ($action) {
    case 'detail':
        $order = ecp_lab_order_find($identityId, (int) ($_GET['id'] ?? 0));
        if ($order === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            break;
        }
        echo json_encode(['ok' => true, 'order' => $order], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'cancel':
        if (!$isPost) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
            break;
        }
        $raw = file_get_contents('php://input') ?: '';
        $body = ($raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'))
            ? (json_decode($raw, true) ?: [])
            : $_POST;

        $res = ecp_lab_order_cancel($identityId, (int) ($body['id'] ?? 0));
        if (!$res['ok']) {
            http_response_code($res['error'] === 'server_error' ? 500 : 409);
        }
        echo json_encode($res);
        break;

    default:
        $items = ecp_lab_orders_for($identityId);
        echo json_encode([
            'ok'    => true,
            'count' => count($items),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
