<?php
// =====================================================================
// api/store_pay.php — Razorpay for store orders (JSON, logged-in owner only).
//
//   POST {action:"start",  order_no}                         → { ok, checkout }
//   POST {action:"verify", order_no, razorpay_order_id,
//         razorpay_payment_id, razorpay_signature}            → { ok, state }
//
// "verify" checks the Checkout.js signature AND asks Razorpay's API whether
// the money was captured. The webhook does the same independently, so a
// closed tab or lost callback still ends up paid.
// =====================================================================

declare(strict_types=1);

use App\Core\QueryBuilder;
use App\Services\Store\StorePaymentService;

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../store/_lib.php';
require_once __DIR__ . '/../store/_app.php';

function store_pay_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')
    || !store_same_origin()) {
    store_pay_out(400, ['ok' => false, 'error' => 'bad_request']);
}
if (!ecp_db() || !store_can_view() || !store_app()) {
    store_pay_out(503, ['ok' => false, 'error' => 'Payments are temporarily unavailable.']);
}
$me = ecp_patient_current();
if (!$me) {
    store_pay_out(401, ['ok' => false, 'error' => 'login_required']);
}

$in = json_decode((string) file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];
$orderNo = strtoupper(trim((string) ($in['order_no'] ?? '')));

try {
    $order = QueryBuilder::table('store_orders')
        ->where('order_no', '=', $orderNo)
        ->where('identity_id', '=', (int) $me['id'])
        ->first();
    if ($order === null) {
        store_pay_out(404, ['ok' => false, 'error' => 'Order not found.']);
    }

    $action = (string) ($in['action'] ?? '');
    if (!store_throttle('pay', 30, 600)) {   // each start creates a Razorpay order: cap abuse
        store_pay_out(429, ['ok' => false, 'error' => 'Too many attempts. Please wait a few minutes and try again.']);
    }
    if ($action === 'start') {
        $res = StorePaymentService::start($order);
        store_pay_out($res['ok'] ? 200 : 422, $res);
    }

    if ($action === 'verify') {
        $rzpOrderId = (string) ($in['razorpay_order_id'] ?? '');
        // The Razorpay order must be one we created for THIS order.
        $pay = QueryBuilder::table('store_payments')->where('rzp_order_id', '=', $rzpOrderId)->first();
        if ($pay === null || (int) $pay['order_id'] !== (int) $order['id']) {
            store_pay_out(422, ['ok' => false, 'error' => 'Payment does not match this order.']);
        }
        // The signature is checked, but the decision is ALWAYS Razorpay's API:
        // a forged callback can't mark an order paid, and a real payment whose
        // callback got mangled still goes through.
        $sigOk = StorePaymentService::verifyCheckoutSignature($rzpOrderId, (string) ($in['razorpay_payment_id'] ?? ''), (string) ($in['razorpay_signature'] ?? ''));
        if (!$sigOk) {
            error_log('[api/store_pay] checkout signature mismatch for ' . $orderNo . '; confirming via API');
        }
        $state = StorePaymentService::confirm($rzpOrderId);
        store_pay_out(200, ['ok' => $state === 'paid', 'state' => $state]);
    }

    store_pay_out(400, ['ok' => false, 'error' => 'unknown_action']);
} catch (Throwable $e) {
    error_log('[api/store_pay] ' . $e->getMessage());
    store_pay_out(500, ['ok' => false, 'error' => 'Something went wrong. If money was deducted, it will reflect on your order within a few minutes.']);
}
