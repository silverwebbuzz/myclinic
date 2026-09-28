<?php
// =====================================================================
// api/store_cart.php — eClinicPro Store cart (JSON).
//
//   POST {action:"add", variant_id, qty}   → { ok, count, qty }
//   POST {action:"set", item_id, qty}      → { ok, count }   (qty 0 removes)
//
// Guests are allowed (cart cookie `ecp_cart`); logging in merges the cart.
// JSON body required (CSRF: can't be sent cross-site without a preflight).
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../store/_lib.php';
require_once __DIR__ . '/../store/_app.php';

function store_cart_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    store_cart_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
}
if (!str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json') || !store_same_origin()) {
    store_cart_out(415, ['ok' => false, 'error' => 'json_required']);
}
if (!ecp_db() || !store_can_view()) {
    store_cart_out(404, ['ok' => false, 'error' => 'store_unavailable']);
}
if (!store_app()) {
    store_cart_out(503, ['ok' => false, 'error' => 'Cart is temporarily unavailable. Please try again shortly.']);
}

$in = json_decode((string) file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];
$action = (string) ($in['action'] ?? '');

try {
    $cart = store_cart(true);
    if ($cart === null) {
        store_cart_out(503, ['ok' => false, 'error' => 'Cart is temporarily unavailable.']);
    }
    $cartId = (int) $cart['id'];

    if ($action === 'add') {
        $res = \App\Services\Store\CartService::add($cartId, (int) ($in['variant_id'] ?? 0), (int) ($in['qty'] ?? 1));
        if (!$res['ok']) {
            store_cart_out(422, ['ok' => false, 'error' => $res['error'] ?? 'Could not add to cart.']);
        }
        store_cart_out(200, ['ok' => true, 'qty' => $res['qty'], 'count' => \App\Services\Store\CartService::count($cartId)]);
    }
    if ($action === 'set') {
        $ok = \App\Services\Store\CartService::setQty($cartId, (int) ($in['item_id'] ?? 0), (int) ($in['qty'] ?? 0));
        store_cart_out($ok ? 200 : 404, ['ok' => $ok, 'count' => \App\Services\Store\CartService::count($cartId)]);
    }
    store_cart_out(400, ['ok' => false, 'error' => 'unknown_action']);
} catch (Throwable $e) {
    error_log('[api/store_cart] ' . $e->getMessage());
    store_cart_out(500, ['ok' => false, 'error' => 'Something went wrong. Please try again.']);
}
