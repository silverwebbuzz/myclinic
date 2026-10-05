<?php
// =====================================================================
// api/mobile/v1/store_checkout.php — addresses, place order, Razorpay.
// All actions require Bearer (customers = patient identities), except the
// pincode lookup, which the address form calls before/without login.
//
// Thin adapter over the SAME services as store/checkout.php and
// api/store_pay.php: AddressService, CheckoutService::place (one DB
// transaction: re-price, reserve stock, split per seller), and
// StorePaymentService (start / confirm via Razorpay's API). The client's
// total is never trusted; it is stored only as a diagnostic.
//
// App flow:
//   1. GET  ?action=summary       → quote + saved addresses + checkout_key
//   2. POST ?action=place         → { order_no }          (stock now reserved)
//   3. POST ?action=pay_start     → { checkout }          → open Razorpay SDK
//   4. POST ?action=pay_verify    → { state: paid|pending|failed|unknown }
//   Closing the SDK is fine: the order stays payable until expires_at,
//   and the Razorpay webhook marks it paid even if step 4 never arrives.
//
//   GET  ?action=addresses
//   POST ?action=address_add          { name, phone, line1, line2?, landmark?, pincode, city, state, label? }
//   POST ?action=address_update       { address_id, …any address_add fields… }
//   POST ?action=address_set_default  { address_id }
//   POST ?action=address_remove       { address_id }
//   GET  ?action=pincode&pin=380001   (public) → city, GST state, serviceable, eta_days
// =====================================================================

declare(strict_types=1);

use App\Core\QueryBuilder;
use App\Services\Store\AddressService;
use App\Services\Store\CartService;
use App\Services\Store\CheckoutService;
use App\Services\Store\GstStates;
use App\Services\Store\PincodeService;
use App\Services\Store\StorePaymentService;
use App\Services\Store\VendorService;

require_once __DIR__ . '/_store.php';

ecp_ms_boot(true);
$action = (string) ($_GET['action'] ?? 'summary');

// ----- pincode → city/state + courier serviceability (public) -------------
// Advisory only: `place` does not check it. serviceable:true + eta_days:null
// means "couldn't check" (Shiprocket off or erroring): let the customer go on.
if ($action === 'pincode') {
    ecp_m_require_method('GET');
    $pin = trim((string) ($_GET['pin'] ?? ''));
    if (!preg_match('/^[1-9]\d{5}$/', $pin)) {
        ecp_m_err('invalid_pin', 400, ['message' => 'Enter a valid 6-digit pincode.']);
    }
    if (!store_throttle('pincode', 60, 600)) {   // each new pin may call Shiprocket
        ecp_m_err('too_many_attempts', 429, ['message' => 'Too many lookups. Please wait a few minutes and try again.']);
    }
    $found = PincodeService::lookup($pin);
    if ($found === null) {
        ecp_m_err('pin_not_found', 404, ['message' => 'We couldn\'t find this pincode. Please check it, or enter your city and state yourself.']);
    }
    ecp_m_ok($found);
}

$me = ecp_m_require_patient();
$identityId = (int) $me['id'];

switch ($action) {

    // ----- everything the checkout screen needs ---------------------------
    case 'summary': {
        ecp_m_require_method('GET');
        $res = ecp_ms_cart(false);
        $cart = ecp_ms_cart_payload($res['cart'], $res['new_token']);
        if ($cart['summary']['item_count'] === 0) {
            ecp_m_err('cart_empty', 409, ['cart' => $cart]);
        }
        if ($cart['has_problems']) {
            // Same rule as the web: fix the cart (out of stock / unavailable lines) first.
            ecp_m_err('cart_needs_attention', 409, ['cart' => $cart]);
        }
        ecp_m_ok([
            'cart' => $cart,
            'addresses' => array_map('ecp_ms_address', AddressService::list($identityId)),
            'contact' => [
                'name' => (string) ($me['name'] ?? ''),
                'phone' => (string) preg_replace('/^\+91/', '', (string) ($me['phone'] ?? '')),
                'email' => $me['email'] ?? null,
            ],
            // Idempotency key: send it back with `place`. A retry/double-tap with the
            // same key returns the order already placed instead of a second one.
            'checkout_key' => store_uuid4(),
            'payment_window_minutes' => (int) store_setting('store_payment_window_minutes', '30'),
            'states' => array_values(GstStates::STATES),
            'terms_url' => ecp_site_url('/terms'),
            'refund_policy_url' => ecp_site_url('/refund-policy'),
        ]);
        break;
    }

    case 'addresses': {
        ecp_m_require_method('GET');
        ecp_m_ok([
            'addresses' => array_map('ecp_ms_address', AddressService::list($identityId)),
            'max_addresses' => AddressService::MAX_ADDRESSES,
            'states' => array_values(GstStates::STATES),
        ]);
        break;
    }

    case 'address_add': {
        ecp_m_require_method('POST');
        $res = AddressService::create($identityId, ecp_m_input());
        if (!$res['ok']) {
            ecp_m_err('invalid_address', 422, ['message' => $res['error'] ?? 'Please check the address.']);
        }
        $a = AddressService::find($identityId, (int) $res['id']);
        ecp_m_ok(['address' => $a ? ecp_ms_address($a) : null]);
        break;
    }

    // Edit a saved address. Fields not sent keep their value. Orders already
    // placed keep their own copy of the address, so they don't change.
    case 'address_update': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $res = AddressService::update($identityId, (int) ($in['address_id'] ?? 0), $in);
        if (!$res['ok']) {
            if (($res['error'] ?? '') === 'not_found') {
                ecp_m_err('not_found', 404);
            }
            ecp_m_err('invalid_address', 422, ['message' => $res['error'] ?? 'Please check the address.']);
        }
        $a = AddressService::find($identityId, (int) $in['address_id']);
        ecp_m_ok(['address' => $a ? ecp_ms_address($a) : null]);
        break;
    }

    case 'address_set_default': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        if (!AddressService::setDefault($identityId, (int) ($in['address_id'] ?? 0))) {
            ecp_m_err('not_found', 404);
        }
        ecp_m_ok(['addresses' => array_map('ecp_ms_address', AddressService::list($identityId))]);
        break;
    }

    case 'address_remove': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        if (!AddressService::remove($identityId, (int) ($in['address_id'] ?? 0))) {
            ecp_m_err('not_found', 404);
        }
        ecp_m_ok();
        break;
    }

    // ----- place the order (mirrors store/checkout.php POST) ---------------
    // Body: { checkout_key, address_id | address:{…new address…},
    //         contact_name, contact_phone, contact_email?, client_total_paise? }
    case 'place': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $res = ecp_ms_cart(false);
        $cart = $res['cart'];
        $items = $cart !== null ? CartService::items((int) $cart['id']) : [];
        if (!$items) {
            ecp_m_err('cart_empty', 409);
        }

        if (!empty($in['address']) && is_array($in['address'])) {
            $made = AddressService::create($identityId, $in['address']);
            if (!$made['ok']) {
                ecp_m_err('invalid_address', 422, ['message' => $made['error'] ?? 'Please check the delivery address.']);
            }
            $addressId = (int) $made['id'];
        } else {
            $addressId = (int) ($in['address_id'] ?? 0);
        }
        $address = AddressService::find($identityId, $addressId);
        if ($address === null) {
            ecp_m_err('address_required', 422, ['message' => 'Choose a delivery address.']);
        }

        $contact = [
            'name' => trim((string) ($in['contact_name'] ?? ($me['name'] ?? ''))),
            'phone' => VendorService::normalizePhone((string) ($in['contact_phone'] ?? ($me['phone'] ?? ''))),
            'email' => trim((string) ($in['contact_email'] ?? '')) ?: null,
        ];
        if (mb_strlen($contact['name']) < 2) {
            ecp_m_err('invalid_contact', 422, ['message' => 'Enter your name.']);
        }
        if (!preg_match('/^\+91[6-9]\d{9}$/', $contact['phone'])) {
            ecp_m_err('invalid_contact', 422, ['message' => 'Enter a valid 10-digit mobile number.']);
        }
        if ($contact['email'] !== null && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            ecp_m_err('invalid_contact', 422, ['message' => 'That email address doesn\'t look right.']);
        }

        $clientTotal = isset($in['client_total_paise']) && is_numeric($in['client_total_paise']) ? (int) $in['client_total_paise'] : null;
        $placed = CheckoutService::place($identityId, (int) $cart['id'], $address, $contact,
            strtolower(trim((string) ($in['checkout_key'] ?? ''))), $clientTotal, 'mobile');
        if (!$placed['ok']) {
            // Stock/price may have changed: return the fresh cart so the app can re-render.
            ecp_m_err('cannot_place', 422, [
                'message' => $placed['error'] ?? 'We couldn\'t place your order.',
                'cart' => ecp_ms_cart_payload($cart),
            ]);
        }
        $order = QueryBuilder::table('store_orders')->where('order_no', '=', (string) $placed['order_no'])->first();
        ecp_m_ok([
            'order_no' => (string) $placed['order_no'],
            'grand_total_paise' => $order ? (int) $order['grand_total_paise'] : null,
            'expires_at' => $order['expires_at'] ?? null,   // pay before this or the stock is released
        ]);
        break;
    }

    // ----- Razorpay (mirrors api/store_pay.php) ----------------------------
    // pay_start → { checkout: { key, order_id, amount, currency, name, description, prefill, notes, theme, mode } }
    //   Pass these straight into razorpay_flutter's open() options.
    case 'pay_start':
    case 'pay_verify': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $orderNo = strtoupper(trim((string) ($in['order_no'] ?? '')));
        $order = QueryBuilder::table('store_orders')
            ->where('order_no', '=', $orderNo)
            ->where('identity_id', '=', $identityId)
            ->first();
        if ($order === null) {
            ecp_m_err('not_found', 404);
        }
        if (!store_throttle('pay', 30, 600)) {   // each start creates a Razorpay order: cap abuse
            ecp_m_err('too_many_attempts', 429, ['message' => 'Too many attempts. Please wait a few minutes and try again.']);
        }

        if ($action === 'pay_start') {
            $res = StorePaymentService::start($order);
            if (!$res['ok']) {
                ecp_m_err('cannot_pay', 422, ['message' => $res['error'] ?? 'Could not start the payment.']);
            }
            ecp_m_ok(['checkout' => $res['checkout']]);
        }

        // pay_verify body: { order_no, razorpay_order_id, razorpay_payment_id, razorpay_signature }
        // (razorpay_flutter's PaymentSuccessResponse: orderId, paymentId, signature — also accepted.)
        $rzpOrderId = (string) ($in['razorpay_order_id'] ?? ($in['orderId'] ?? ''));
        $paymentId = (string) ($in['razorpay_payment_id'] ?? ($in['paymentId'] ?? ''));
        $signature = (string) ($in['razorpay_signature'] ?? ($in['signature'] ?? ''));
        // The Razorpay order must be one we created for THIS order.
        $pay = QueryBuilder::table('store_payments')->where('rzp_order_id', '=', $rzpOrderId)->first();
        if ($pay === null || (int) $pay['order_id'] !== (int) $order['id']) {
            ecp_m_err('payment_mismatch', 422, ['message' => 'Payment does not match this order.']);
        }
        // Signature is checked, but the decision is ALWAYS Razorpay's API (same as web).
        if (!StorePaymentService::verifyCheckoutSignature($rzpOrderId, $paymentId, $signature)) {
            error_log('[api/mobile/store_checkout] checkout signature mismatch for ' . $orderNo . '; confirming via API');
        }
        $state = StorePaymentService::confirm($rzpOrderId);
        // paid → show success. pending | unknown → "confirming your payment", then re-fetch the
        // order in a few seconds (the webhook finishes the job). failed → allow retry via pay_start.
        ecp_m_ok(['state' => $state, 'paid' => $state === 'paid', 'order_no' => $orderNo]);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}
