<?php
// =====================================================================
// api/mobile/v1/store_orders.php — the customer's store orders. Bearer.
//
// Thin adapter over the SAME services as store/orders.php + store/order.php:
// OrderService (list/load/release), StoreRefundService::cancelItems,
// ReviewService, ReturnService, ShippingService, TaxDocumentService.
// Every lookup is scoped to the signed-in identity; a foreign order_no → 404.
//
//   GET  ?action=list
//   GET  ?action=detail&order_no=ECS…
//   POST ?action=cancel        { order_no }                       unpaid order only
//   POST ?action=cancel_items  { order_no, items:{order_item_id: qty}, reason? }  → refund
//   POST ?action=review        { order_item_id, rating 1-5, title?, body? }
//   POST ?action=return        multipart/form-data:
//                                vendor_order_id, reason, note?,
//                                items[<order_item_id>]=<qty> (or items = JSON string),
//                                photos[] (≤3 jpg/png/webp; required for damaged,
//                                wrong_item, expired, defective)
//   GET  ?action=invoice&order_no=…&doc=<id>   → text/html (open in a WebView
//                                                with the Authorization header)
// =====================================================================

declare(strict_types=1);

use App\Services\Store\FulfilmentService;
use App\Services\Store\OrderService;
use App\Services\Store\ReturnService;
use App\Services\Store\ReviewService;
use App\Services\Store\ShippingService;
use App\Services\Store\StoreRefundService;
use App\Services\Store\TaxDocumentService;

require_once __DIR__ . '/_store.php';

ecp_ms_boot(true);
$me = ecp_m_require_patient();
$identityId = (int) $me['id'];

const ECP_MS_CANCEL_REASONS = ['Changed my mind', 'Ordered by mistake', 'Found a better price', 'Delivery is too slow', 'Other'];

$action = (string) ($_GET['action'] ?? 'list');

switch ($action) {

    case 'list': {
        ecp_m_require_method('GET');
        OrderService::expireStale();
        $orders = OrderService::listForIdentity($identityId);
        ecp_m_ok(['orders' => array_map(static function ($o) {
            [$label, $tone] = ecp_ms_order_status((string) $o['status']);
            if ($o['payment_status'] === 'partially_refunded' && $tone === 'ok') {
                $label .= ' · part refunded';
            }
            $preview = array_slice($o['items'], 0, 4);

            return [
                'order_no' => (string) $o['order_no'],
                'status' => (string) $o['status'],
                'status_label' => $label,
                'status_tone' => $tone,
                'payment_status' => (string) $o['payment_status'],
                'grand_total_paise' => (int) $o['grand_total_paise'],
                'placed_at' => (string) $o['placed_at'],
                'expires_at' => $o['status'] === 'pending_payment' ? $o['expires_at'] : null,
                'packages' => (int) $o['packages'],
                'item_count' => array_sum(array_map(static fn ($it) => (int) $it['qty'], $o['items'])),
                'items_preview' => array_map(static fn ($it) => [
                    'name' => (string) $it['name'],
                    'image' => store_img($it['image_path'] !== null ? (string) $it['image_path'] : null),
                    'qty' => (int) $it['qty'],
                ], $preview),
                'more_items' => max(0, count($o['items']) - count($preview)),
                'can_pay' => $o['status'] === 'pending_payment',
            ];
        }, $orders)]);
        break;
    }

    case 'detail': {
        ecp_m_require_method('GET');
        OrderService::expireStale();
        FulfilmentService::autoCancelOverdue();
        $order = ecp_ms_order($identityId, (string) ($_GET['order_no'] ?? ''));
        ecp_m_ok(['order' => ecp_ms_order_detail($order, $identityId)]);
        break;
    }

    // ----- cancel an UNPAID order (nothing charged) ------------------------
    case 'cancel': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $order = ecp_ms_order($identityId, (string) ($in['order_no'] ?? ''));
        if (!OrderService::release((int) $order['id'], 'cancelled', 'Cancelled by customer before payment', 'customer', $identityId)) {
            ecp_m_err('cannot_cancel', 409, ['message' => 'This order can no longer be cancelled here.']);
        }
        ecp_m_ok(['order' => ecp_ms_order_detail(ecp_ms_order($identityId, (string) $order['order_no']), $identityId)]);
        break;
    }

    // ----- cancel items of a paid package the seller hasn't packed → refund
    case 'cancel_items': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $order = ecp_ms_order($identityId, (string) ($in['order_no'] ?? ''));
        $reason = trim((string) ($in['reason'] ?? 'Changed my mind')) ?: 'Changed my mind';
        $res = StoreRefundService::cancelItems((int) $order['id'], ecp_ms_qty_map($in['items'] ?? []),
            'Customer: ' . mb_substr($reason, 0, 120), 'customer', $identityId, true);
        if (!$res['ok']) {
            ecp_m_err('cannot_cancel', 422, ['message' => $res['error'] ?? 'Could not cancel. Please try again.']);
        }
        ecp_m_ok([
            'refunded_paise' => (int) ($res['refunded'] ?? 0),
            'refund_no' => $res['refund_no'] ?? null,
            'message' => 'Cancelled. ' . store_rupees((int) ($res['refunded'] ?? 0)) . ' is being refunded to your original payment method (5–7 working days).',
            'order' => ecp_ms_order_detail(ecp_ms_order($identityId, (string) $order['order_no']), $identityId),
        ]);
        break;
    }

    // ----- review a delivered item ------------------------------------------
    case 'review': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $res = ReviewService::create($identityId, (int) ($in['order_item_id'] ?? 0), (int) ($in['rating'] ?? 0),
            (string) ($in['title'] ?? ''), (string) ($in['body'] ?? ''));
        if (!$res['ok']) {
            ecp_m_err('cannot_review', 422, ['message' => $res['error'] ?? 'Could not save your review.']);
        }
        ecp_m_ok(['message' => 'Thanks for your review! It will appear on the product page after a quick check.']);
        break;
    }

    // ----- request a return (multipart, photos) -----------------------------
    case 'return': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $res = ReturnService::request($identityId, (int) ($in['vendor_order_id'] ?? 0), ecp_ms_qty_map($in['items'] ?? []),
            (string) ($in['reason'] ?? ''), (string) ($in['note'] ?? ''), ecp_ms_photos());
        if (!$res['ok']) {
            ecp_m_err('cannot_return', 422, ['message' => $res['error'] ?? 'Could not submit the return.']);
        }
        ecp_m_ok([
            'return_no' => (string) $res['return_no'],
            'message' => 'Return ' . $res['return_no'] . ' submitted. The seller will review it within 2 days; we\'ll email you.',
        ]);
        break;
    }

    // ----- GST invoice / credit note, printable HTML -------------------------
    case 'invoice': {
        ecp_m_require_method('GET');
        $order = ecp_ms_order($identityId, (string) ($_GET['order_no'] ?? ''));
        $doc = TaxDocumentService::load((int) ($_GET['doc'] ?? 0));
        if ($doc === null || (int) $doc['order_id'] !== (int) $order['id']) {
            ecp_m_err('not_found', 404);
        }
        $backUrl = null;
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        require __DIR__ . '/../../../app/views/components/store_tax_document.php';
        exit;
    }

    default:
        ecp_m_err('unknown_action', 400);
}

// ---------------------------------------------------------------------

/** The identity's order (OrderService::load shape) or 404. */
function ecp_ms_order(int $identityId, string $orderNo): array
{
    $orderNo = strtoupper(trim($orderNo));
    $order = preg_match('/^ECS[0-9]{6}-[A-Z0-9]{5,8}$/', $orderNo) ? OrderService::findForIdentity($orderNo, $identityId) : null;
    if ($order === null) {
        ecp_m_err('not_found', 404);
    }

    return $order;
}

/**
 * {order_item_id: qty} from JSON object, multipart items[id]=qty, or a JSON string.
 *
 * @return array<int, int>
 */
function ecp_ms_qty_map(mixed $raw): array
{
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    $out = [];
    foreach ((array) $raw as $id => $q) {
        if ((int) $id > 0 && (int) $q > 0) {
            $out[(int) $id] = (int) $q;
        }
    }

    return $out;
}

/** $_FILES['photos'] in the multi-file shape ReturnService expects, even if sent as a single `photos`. */
function ecp_ms_photos(): array
{
    $f = $_FILES['photos'] ?? null;
    if (!is_array($f) || !isset($f['error'])) {
        return [];
    }
    if (is_array($f['error'])) {
        return $f;
    }

    return array_map(static fn ($v) => [$v], $f);
}

/** Full order for the order screen (mirrors what store/order.php renders). */
function ecp_ms_order_detail(array $order, int $identityId): array
{
    [$label, $tone] = ecp_ms_order_status((string) $order['status']);
    $reviewable = ReviewService::reviewable($identityId, (int) $order['id']);
    $shipByVo = [];
    foreach (ShippingService::forOrder((int) $order['id']) as $s) {
        if ($s['status'] !== 'cancelled' && $s['direction'] === 'forward') {
            $shipByVo[(int) $s['vendor_order_id']] = $s;   // latest active shipment per package
        }
    }
    $docsByVo = [];
    foreach (TaxDocumentService::forOrder((int) $order['id']) as $d) {
        $docsByVo[(int) $d['vendor_order_id']][] = $d;
    }
    $paidish = in_array($order['payment_status'], ['paid', 'partially_refunded'], true);

    $packages = [];
    foreach ($order['vendor_orders'] as $vo) {
        $voId = (int) $vo['id'];
        $returnable = ReturnService::returnable($vo);
        $canCancel = $paidish && in_array($vo['status'], StoreRefundService::CANCELLABLE['customer'], true);
        $items = [];
        foreach ($vo['items'] as $it) {
            $left = (int) $it['qty'] - (int) $it['qty_cancelled'];
            $items[] = [
                'id' => (int) $it['id'],                        // order_item_id
                'product_id' => (int) $it['product_id'],        // store.php?action=product&id=
                'name' => (string) $it['name'],
                'variant_title' => $it['variant_title'] !== null && $it['variant_title'] !== '' ? (string) $it['variant_title'] : null,
                'image' => store_img($it['image_path'] !== null ? (string) $it['image_path'] : null),
                'qty' => (int) $it['qty'],
                'qty_cancelled' => (int) $it['qty_cancelled'],
                'qty_returned' => (int) $it['qty_returned'],
                'unit_price_paise' => (int) $it['unit_price_paise'],
                'line_total_paise' => (int) $it['line_total_paise'],
                'can_review' => isset($reviewable[(int) $it['id']]),
                'cancellable_qty' => $canCancel ? max(0, $left) : 0,
                'returnable_qty' => (int) ($returnable[(int) $it['id']] ?? 0),
            ];
        }
        $sh = $shipByVo[$voId] ?? null;
        $packages[] = [
            'id' => $voId,                                      // vendor_order_id (for returns)
            'sub_order_no' => (string) $vo['sub_order_no'],
            'seller' => ['name' => (string) $vo['vendor_name'], 'slug' => (string) $vo['vendor_slug']],
            'status' => (string) $vo['status'],
            'status_label' => ecp_ms_package_status((string) $vo['status']),
            'shipping_paise' => (int) $vo['shipping_paise'],
            'items' => $items,
            'tracking' => $sh !== null && !empty($sh['awb_code']) ? [
                'status' => (string) $sh['status'],
                'status_label' => ecp_ms_track_status((string) $sh['status']),
                'courier' => $sh['courier_name'] ?: null,
                'awb' => (string) $sh['awb_code'],
                'url' => 'https://shiprocket.co/tracking/' . rawurlencode((string) $sh['awb_code']),
                'events' => array_map(static fn ($ev) => [
                    'at' => (string) $ev['event_at'],
                    'status' => ucwords(strtolower((string) $ev['raw_status'])),
                    'location' => $ev['location'] ?: null,
                ], array_slice($sh['events'], 0, 10)),
            ] : null,
            'documents' => array_map(static fn ($d) => [
                'id' => (int) $d['id'],
                'type' => $d['doc_type'] === 'credit_note' ? 'Credit note' : ($d['issuer'] === 'platform' ? 'Delivery invoice' : 'Tax invoice'),
                'doc_no' => (string) $d['doc_no'],
                'issued_at' => $d['issued_at'] ?? null,
                // GET with the Authorization header (WebView) → printable HTML.
                'url' => ecp_site_url('/api/mobile/v1/store_orders.php?action=invoice&order_no=' . rawurlencode((string) $order['order_no']) . '&doc=' . (int) $d['id']),
            ], $docsByVo[$voId] ?? []),
            'returns' => array_map(static fn ($rt) => [
                'return_no' => (string) $rt['return_no'],
                'status' => (string) $rt['status'],
                'status_label' => ecp_ms_return_status($rt),
                'refund_paise' => (int) $rt['refund_amount_paise'],
            ], $order['returns'][$voId] ?? []),
            'can_cancel_items' => $canCancel && array_filter($items, static fn ($i) => $i['cancellable_qty'] > 0) !== [],
            'can_return' => $returnable !== [],
            'return_until' => $returnable !== [] ? $vo['settle_after'] : null,
        ];
    }

    $a = $order['ship_address'];

    return [
        'order_no' => (string) $order['order_no'],
        'status' => (string) $order['status'],
        'status_label' => $label,
        'status_tone' => $tone,
        'payment_status' => (string) $order['payment_status'],
        'notice' => ecp_ms_order_notice($order),
        'placed_at' => (string) $order['placed_at'],
        'paid_at' => $order['paid_at'] ?? null,
        'can_pay' => $order['status'] === 'pending_payment',
        'can_cancel' => $order['status'] === 'pending_payment',
        'expires_at' => $order['status'] === 'pending_payment' ? $order['expires_at'] : null,
        'packages' => $packages,
        'totals' => [
            'items_paise' => (int) $order['items_subtotal_paise'],
            'discount_paise' => (int) $order['discount_paise'],
            'coupon_code' => $order['coupon_code'] ?: null,
            'shipping_paise' => (int) $order['shipping_paise'],
            'tax_included_paise' => (int) $order['tax_included_paise'],
            'grand_total_paise' => (int) $order['grand_total_paise'],
        ],
        'refunds' => array_map(static fn ($rf) => [
            'refund_no' => (string) $rf['refund_no'],
            'amount_paise' => (int) $rf['amount_paise'],
            'status' => (string) $rf['status'],
            'processed' => $rf['status'] === 'processed',
        ], $order['refunds']),
        'ship_to' => [
            'name' => (string) ($a['name'] ?? ''), 'phone' => (string) ($a['phone'] ?? ''),
            'line1' => (string) ($a['line1'] ?? ''), 'line2' => $a['line2'] ?? null, 'landmark' => $a['landmark'] ?? null,
            'city' => (string) ($a['city'] ?? ''), 'state' => (string) ($a['state'] ?? ''), 'pincode' => (string) ($a['pincode'] ?? ''),
        ],
        'options' => [
            'cancel_reasons' => ECP_MS_CANCEL_REASONS,
            'return_reasons' => array_map(static fn ($k, $l) => ['key' => $k, 'label' => $l], array_keys(ReturnService::REASONS), ReturnService::REASONS),
            'return_photo_required_for' => ['damaged', 'wrong_item', 'expired', 'defective'],
        ],
    ];
}

/** Banner above the order (same wording as store/order.php). @return array{tone: string, text: string}|null */
function ecp_ms_order_notice(array $order): ?array
{
    $s = (string) $order['status'];
    if ($s === 'pending_payment') {
        return ['tone' => 'warn', 'text' => 'Complete your payment of ' . store_rupees((int) $order['grand_total_paise'])
            . '. Items are reserved until ' . date('g:i a', (int) strtotime((string) $order['expires_at'])) . '.'];
    }
    if (in_array($s, ['paid', 'partially_shipped', 'shipped', 'partially_delivered', 'delivered', 'completed'], true)) {
        return ['tone' => 'ok', 'text' => 'Thank you, payment received. Each seller now packs their items; you\'ll see tracking here once packages ship.'
            . (!empty($order['contact_email']) ? ' A confirmation has been emailed to ' . $order['contact_email'] . '.' : '')];
    }
    if ($s === 'refunded') {
        return ['tone' => 'warn', 'text' => 'Your payment arrived after this order had closed and the items were no longer available, so it has been refunded in full. Refunds reach your account in 5–7 working days.'];
    }
    if (in_array($s, ['expired', 'cancelled'], true) && $order['payment_status'] === 'pending') {
        return ['tone' => 'muted', 'text' => 'This order was not paid, so nothing was charged. Go to your cart to try again.'];
    }
    if ($s === 'cancelled') {
        return ['tone' => 'warn', 'text' => 'This order was cancelled and refunded to your original payment method. Refunds usually arrive in 5–7 working days.'];
    }

    return null;
}
