<?php
// =====================================================================
// api/mobile/v1/_store.php — shared helpers for the store (marketplace)
// endpoints: store.php, store_cart.php, store_checkout.php,
// store_orders.php, store_wishlist.php.
//
// Same rule as the rest of the facade: THIN transport. Catalog reads use
// the storefront's store/_lib.php queries; anything touching money, stock
// or orders goes through App\Services\Store\* (booted by store/_app.php),
// exactly like the web store. Nothing here re-computes prices or totals.
//
// Store-specific request headers (all optional):
//   X-Cart-Token:    guest cart token. A logged-out app can build a cart;
//                    the token comes back as `cart_token` whenever a new
//                    guest cart is created. Keep sending it after login and
//                    the guest cart is merged into the patient's cart
//                    (same CartService::resolve() the website uses).
//   X-Store-Preview: the store preview key (platform_settings
//                    store_preview_key). Only needed while the store is
//                    hidden (store_enabled = 0), for testing on live.
//
// Money: every amount is an integer in PAISE (`*_paise`). Prices include GST.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../store/_lib.php';
require_once __DIR__ . '/../../../store/_app.php';

// Resolve the Bearer token BEFORE anything else calls ecp_patient_current():
// that function caches its first answer for the request.
ecp_m_current();

/**
 * Store visibility gate + (optionally) the portal classes.
 * Hidden store → 404 store_unavailable (same as the website's 404).
 */
function ecp_ms_boot(bool $needApp = false): void
{
    $preview = trim((string) ($_SERVER['HTTP_X_STORE_PREVIEW'] ?? ''));
    if ($preview !== '') {
        $_COOKIE[STORE_PREVIEW_COOKIE] = $preview;   // store_can_view() checks it like the web cookie
    }
    if (!ecp_db() || !store_can_view()) {
        ecp_m_err('store_unavailable', 404);
    }
    if ($needApp && !store_app()) {
        ecp_m_err('store_temporarily_unavailable', 503);
    }
}

/** The guest cart token from X-Cart-Token (or body/query cart_token), if well-formed. */
function ecp_ms_cart_token(): ?string
{
    $t = (string) ($_SERVER['HTTP_X_CART_TOKEN'] ?? ($_GET['cart_token'] ?? ''));
    $t = strtolower(trim($t));

    return preg_match('/^[a-f0-9]{64}$/', $t) ? $t : null;
}

/**
 * The caller's cart (patient's cart when signed in, else the guest cart).
 *
 * @return array{cart: ?array, new_token: ?string}
 */
function ecp_ms_cart(bool $create): array
{
    $me = ecp_m_current();

    return \App\Services\Store\CartService::resolve($me ? (int) $me['id'] : null, ecp_ms_cart_token(), $create);
}

// ---------------------------------------------------------------------
// Shapes
// ---------------------------------------------------------------------

/** Absolute URL for a site-relative asset path (/assets/...). */
function ecp_ms_site_asset(string $path): string
{
    return ecp_site_url('/' . ltrim($path, '/'));
}

/** Product card (store_card_cols() row). */
function ecp_ms_card(array $p): array
{
    $price = (int) $p['min_price_paise'];
    $mrp = (int) ($p['mrp_paise'] ?? 0);
    $noPromo = !empty($p['no_promotion']);

    return [
        'id' => (int) $p['id'],
        'slug' => (string) $p['slug'],
        'name' => (string) $p['name'],
        'brand' => $p['brand_name'] !== null && $p['brand_name'] !== '' ? (string) $p['brand_name'] : null,
        'seller' => ['name' => (string) $p['vendor_name'], 'slug' => (string) $p['vendor_slug']],
        'image' => store_img(isset($p['cover']) ? (string) $p['cover'] : null),
        'price_paise' => $price,
        'mrp_paise' => $mrp > $price ? $mrp : null,
        // IMS Act (infant food etc.): MRP may be shown, "% off" may NOT.
        'off_pct' => $noPromo ? 0 : store_off_pct($mrp, $price),
        'no_promotion' => $noPromo,
        'price_from' => (int) ($p['variant_count'] ?? 1) > 1,   // show "From ₹…"
        'in_stock' => (int) $p['in_stock'] === 1,
        'rating_avg' => (int) ($p['rating_count'] ?? 0) > 0 ? round((float) $p['rating_avg'], 1) : null,
        'rating_count' => (int) ($p['rating_count'] ?? 0),
        'wishlisted' => isset(store_wishlist_ids()[(int) $p['id']]),
    ];
}

/** @param list<array<string,mixed>> $rows */
function ecp_ms_cards(array $rows): array
{
    return array_map('ecp_ms_card', $rows);
}

function ecp_ms_address(array $a): array
{
    return [
        'id' => isset($a['id']) ? (int) $a['id'] : null,
        'label' => $a['label'] ?? null,
        'name' => (string) ($a['name'] ?? ''),
        'phone' => (string) ($a['phone'] ?? ''),
        'line1' => (string) ($a['line1'] ?? ''),
        'line2' => $a['line2'] ?? null,
        'landmark' => $a['landmark'] ?? null,
        'city' => (string) ($a['city'] ?? ''),
        'state' => (string) ($a['state'] ?? ''),
        'pincode' => (string) ($a['pincode'] ?? ''),
        'is_default' => (int) ($a['is_default'] ?? 0) === 1,
    ];
}

/**
 * Full cart payload: every line grouped by seller (including lines with a
 * problem, as the web cart shows them) + the PricingService quote.
 * Also acknowledges changed prices once they have been shown, like the web.
 */
function ecp_ms_cart_payload(?array $cart, ?string $newToken = null): array
{
    $me = ecp_m_current();
    $items = $cart !== null ? \App\Services\Store\CartService::items((int) $cart['id']) : [];
    $cc = $cart !== null
        ? \App\Services\Store\PricingService::cartCoupon($cart, $me ? (int) $me['id'] : null)
        : ['coupon' => null, 'error' => null];
    $quote = \App\Services\Store\PricingService::quote($items, $cc['coupon'], $me ? (int) $me['id'] : null);

    $groupQuote = [];
    foreach ($quote['groups'] as $g) {
        $groupQuote[(int) $g['vendor_id']] = $g;
    }
    $groups = [];
    $hasProblems = false;
    $priceChanged = false;
    foreach ($items as $it) {
        $vid = (int) $it['vendor_id'];
        if (!isset($groups[$vid])) {
            $g = $groupQuote[$vid] ?? null;
            $groups[$vid] = [
                'seller' => ['id' => $vid, 'name' => (string) $it['vendor_name'], 'slug' => (string) $it['vendor_slug']],
                'subtotal_paise' => $g !== null ? (int) $g['subtotal'] : 0,
                // Delivery is now one fee per ORDER (see summary). Kept for older app builds.
                'shipping_paise' => 0,
                'free_shipping_above_paise' => null,
                'add_for_free_shipping_paise' => null,
                'lines' => [],
            ];
        }
        $problem = $it['problem'];
        $hasProblems = $hasProblems || $problem !== null;
        $changed = $problem === null && $it['price_changed'];
        $priceChanged = $priceChanged || $changed;
        $price = (int) $it['price_paise'];
        $mrp = (int) $it['mrp_paise'];
        $groups[$vid]['lines'][] = [
            'item_id' => (int) $it['item_id'],
            'product_id' => (int) $it['product_id'],
            'variant_id' => (int) $it['variant_id'],
            'slug' => (string) $it['slug'],
            'name' => (string) $it['name'],
            'variant_title' => $it['variant_title'] !== null && $it['variant_title'] !== '' ? (string) $it['variant_title'] : null,
            'image' => store_img($it['cover'] !== null ? (string) $it['cover'] : null),
            'qty' => (int) $it['qty'],
            'available_qty' => (int) $it['available_qty'],
            // Largest quantity the picker should offer.
            'max_qty' => max(1, min(\App\Services\Store\CartService::MAX_QTY_PER_LINE, max((int) $it['qty'], (int) $it['available_qty']))),
            'price_paise' => $price,
            'mrp_paise' => $mrp > $price ? $mrp : null,
            'off_pct' => !empty($it['no_promotion']) ? 0 : store_off_pct($mrp, $price),
            'line_total_paise' => $problem === null ? $price * (int) $it['qty'] : null,
            'problem' => $problem,            // null = buyable; else show in red and exclude from totals
            'price_changed' => $changed,
        ];
    }
    if ($cart !== null && $priceChanged) {
        \App\Services\Store\CartService::acknowledgePrices((int) $cart['id']);   // shown once, like the web
    }

    return [
        'cart_token' => $newToken,   // non-null only when a NEW guest cart was created: store it
        'count' => $cart !== null ? \App\Services\Store\CartService::count((int) $cart['id']) : 0,
        'groups' => array_values($groups),
        'summary' => [
            'item_count' => (int) $quote['item_count'],
            'seller_count' => count($quote['groups']),
            'subtotal_paise' => (int) $quote['subtotal'],
            'savings_paise' => (int) $quote['savings'],        // vs MRP
            'discount_paise' => (int) $quote['discount'],      // coupon
            'points_discount_paise' => (int) $quote['points_discount'],   // eClinicPro Points (1 pt = ₹1)
            'shipping_paise' => (int) $quote['shipping'],        // one delivery fee per order
            'free_shipping_above_paise' => $quote['free_above'] !== null ? (int) $quote['free_above'] : null,
            'add_for_free_shipping_paise' => $quote['add_for_free_shipping'],   // "Add ₹X more for free delivery" (null = n/a)
            'tax_included_paise' => (int) $quote['tax_included'],
            'grand_total_paise' => (int) $quote['grand_total'],
        ],
        'coupon' => $quote['coupon'] !== null ? [
            'code' => (string) $quote['coupon']['code'],
            'label' => (string) $quote['coupon']['label'],
            'applied' => (bool) $quote['coupon']['applied'],
            'note' => $quote['coupon']['note'],
        ] : null,
        // null = guest / points off. kind: welcome | standard | null. welcome_add_paise: "Add ₹X more to use your welcome points".
        'points' => $quote['points'],
        'coupon_error' => $cc['error'],   // set when a saved coupon stopped being usable
        'has_problems' => $hasProblems,
        'prices_changed' => $priceChanged,
        'can_checkout' => (int) $quote['item_count'] > 0 && !$hasProblems,
        'signed_in' => $me !== null,
        'max_qty_per_line' => \App\Services\Store\CartService::MAX_QTY_PER_LINE,
    ];
}

// ---------------------------------------------------------------------
// Status labels — same wording as store/order.php and store/orders.php
// ---------------------------------------------------------------------

/** @return array{0: string, 1: string} [label, tone] tone = ok|warn|err|muted */
function ecp_ms_order_status(string $status): array
{
    return [
        'pending_payment' => ['Awaiting payment', 'warn'],
        'paid' => ['Order confirmed', 'ok'],
        'refunded' => ['Refunded', 'muted'],
        'partially_shipped' => ['Partly shipped', 'ok'],
        'shipped' => ['Shipped', 'ok'],
        'partially_delivered' => ['Partly delivered', 'ok'],
        'delivered' => ['Delivered', 'ok'],
        'completed' => ['Completed', 'ok'],
        'expired' => ['Payment window expired', 'muted'],
        'cancelled' => ['Cancelled', 'muted'],
        'payment_failed' => ['Payment failed', 'err'],
    ][$status] ?? [ucwords(str_replace('_', ' ', $status)), 'muted'];
}

function ecp_ms_package_status(string $status): string
{
    return [
        'pending_payment' => 'Awaiting payment', 'new' => 'Confirmed: seller preparing', 'accepted' => 'Seller preparing',
        'packed' => 'Packed', 'ready_to_ship' => 'Ready to ship', 'shipped' => 'Shipped', 'delivered' => 'Delivered',
        'completed' => 'Delivered', 'auto_cancelled' => 'Cancelled', 'cancelled_by_customer' => 'Cancelled',
        'cancelled_by_vendor' => 'Cancelled by seller', 'cancelled_by_admin' => 'Cancelled', 'rto' => 'Not delivered: returned to seller',
        'lost_in_transit' => 'Lost or damaged in transit: refunded',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function ecp_ms_track_status(string $status): string
{
    return [
        'sr_order_created' => 'Preparing shipment', 'awb_assigned' => 'Courier assigned', 'pickup_scheduled' => 'Pickup scheduled',
        'pickup_failed' => 'Pickup being rescheduled', 'picked_up' => 'Picked up', 'in_transit' => 'In transit',
        'out_for_delivery' => 'Out for delivery', 'ndr' => 'Delivery attempt failed: courier will retry',
        'delivered' => 'Delivered', 'rto_initiated' => 'Returning to seller', 'rto_delivered' => 'Returned to seller',
        'lost' => 'Lost in transit (we\'re on it)', 'damaged' => 'Damaged in transit (we\'re on it)',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function ecp_ms_return_status(array $rt): string
{
    return [
        'requested' => 'Waiting for the seller', 'approved' => 'Approved: pickup being arranged', 'pickup_scheduled' => 'Pickup scheduled',
        'picked_up' => 'On its way back', 'received' => 'Received by seller', 'qc_passed' => 'Checked', 'qc_failed' => 'Under review by eClinicPro',
        'refunded' => 'Refunded ' . store_rupees((int) $rt['refund_amount_paise']),
        'rejected' => 'Not accepted' . (!empty($rt['vendor_note']) ? ': ' . $rt['vendor_note'] : ''),
        'closed' => 'Closed',
    ][$rt['status']] ?? (string) $rt['status'];
}
