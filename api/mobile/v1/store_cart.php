<?php
// =====================================================================
// api/mobile/v1/store_cart.php — store cart (guest or signed in).
//
// Thin adapter over App\Services\Store\CartService + PricingService — the
// SAME code the web cart (api/store_cart.php, store/cart.php) runs, so
// stock caps, per-seller shipping, coupons and totals are identical.
//
// Identity: Bearer → the patient's cart. No Bearer → the guest cart named by
// the X-Cart-Token header (see _store.php). Send BOTH after login once and
// the guest cart is merged into the patient's cart.
//
// Every call returns the full cart (same shape) so the app can re-render
// from one response.
//
//   GET  ?action=get                                      → { cart }
//   POST ?action=add     { variant_id, qty? }             → { cart, added_qty }
//   POST ?action=set     { item_id, qty }   (qty 0 removes) → { cart }
//   POST ?action=coupon  { code }           ("" removes)  → { cart }
// =====================================================================

declare(strict_types=1);

use App\Core\QueryBuilder;
use App\Services\Store\CartService;
use App\Services\Store\CouponService;

require_once __DIR__ . '/_store.php';

ecp_ms_boot(true);

$action = (string) ($_GET['action'] ?? 'get');

switch ($action) {

    case 'get': {
        ecp_m_require_method('GET');
        $res = ecp_ms_cart(false);   // don't create a cart just to look at it
        ecp_m_ok(['cart' => ecp_ms_cart_payload($res['cart'], $res['new_token'])]);
        break;
    }

    case 'add': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $res = ecp_ms_cart(true);
        if ($res['cart'] === null) {
            ecp_m_err('store_temporarily_unavailable', 503);
        }
        $add = CartService::add((int) $res['cart']['id'], (int) ($in['variant_id'] ?? 0), (int) ($in['qty'] ?? 1));
        if (!$add['ok']) {
            // `message` is customer-facing text from CartService (out of stock, cart full…).
            ecp_m_err('cannot_add', 422, ['message' => $add['error'] ?? 'Could not add to cart.', 'cart_token' => $res['new_token']]);
        }
        ecp_m_ok(['added_qty' => (int) $add['qty'], 'cart' => ecp_ms_cart_payload($res['cart'], $res['new_token'])]);
        break;
    }

    case 'set': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $res = ecp_ms_cart(false);
        if ($res['cart'] === null || !CartService::setQty((int) $res['cart']['id'], (int) ($in['item_id'] ?? 0), (int) ($in['qty'] ?? 0))) {
            ecp_m_err('item_not_found', 404);
        }
        ecp_m_ok(['cart' => ecp_ms_cart_payload($res['cart'], $res['new_token'])]);
        break;
    }

    case 'coupon': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $res = ecp_ms_cart(false);
        if ($res['cart'] === null) {
            ecp_m_err('cart_empty', 409);
        }
        $cartId = (int) $res['cart']['id'];
        $code = strtoupper(trim((string) ($in['code'] ?? '')));
        if ($code !== '') {
            // Stop coupon-code guessing: 10 tries per 10 minutes per IP (same bucket as web).
            if (!store_throttle('coupon', 10, 600)) {
                ecp_m_err('too_many_attempts', 429, ['message' => 'Too many coupon attempts. Please wait a few minutes and try again.']);
            }
            $c = CouponService::findByCode($code);
            $me = ecp_m_current();
            $why = $c === null ? 'That coupon code isn\'t valid.' : CouponService::unusableReason($c, $me ? (int) $me['id'] : null);
            if ($why !== null) {
                ecp_m_err('coupon_invalid', 422, ['message' => $why]);
            }
        }
        QueryBuilder::table('store_carts')->where('id', '=', $cartId)->update(['coupon_code' => $code !== '' ? $code : null]);
        $res['cart']['coupon_code'] = $code !== '' ? $code : null;   // payload reads the row we already hold
        ecp_m_ok(['cart' => ecp_ms_cart_payload($res['cart'], $res['new_token'])]);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}
