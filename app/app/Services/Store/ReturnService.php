<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Post-delivery returns (item level).
 *
 *   requested ──approve──▶ approved ──(reverse pickup)──▶ pickup_scheduled ─▶ picked_up ─▶ received
 *       │                     │                                                                 │
 *       └─reject─▶ rejected   └─"refund without pickup"─────────────────────▶ refunded ◀── qc_passed
 *                                                                               qc_failed ─▶ admin decides
 *
 * Rules:
 *  - Only DELIVERED packages, only before settle_after (delivery + return window),
 *    only products marked returnable; one open return per package.
 *  - While a return is open the package's earnings stay frozen (see SettlementService::releaseMatured).
 *  - Refund = returned units × price paid, via Razorpay on the original payment; the
 *    seller's ledger gets matching reversal entries (sale back, commission share back).
 */
final class ReturnService
{
    public const REASONS = [
        'damaged' => 'Arrived damaged',
        'defective' => 'Defective / not working',
        'wrong_item' => 'Wrong item sent',
        'expired' => 'Expired or near expiry',
        'not_as_described' => 'Not as described',
        'other' => 'Other',
    ];
    /** Reasons where a photo is required. */
    private const PHOTO_REQUIRED = ['damaged', 'wrong_item', 'expired', 'defective'];
    public const OPEN = ['requested', 'approved', 'pickup_scheduled', 'picked_up', 'received', 'qc_failed'];
    private const PHOTO_EXT = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    // ------------------------------------------------------------------
    // Eligibility + request (customer)
    // ------------------------------------------------------------------

    /**
     * Returnable units per order item for one package ([] = nothing returnable).
     *
     * @return array<int, int> order_item_id => max units
     */
    public static function returnable(array $vo): array
    {
        if (!in_array($vo['status'], ['delivered'], true) || empty($vo['settle_after']) || strtotime((string) $vo['settle_after']) < time()) {
            return [];
        }
        if (self::openFor((int) $vo['id']) !== null) {
            return [];
        }
        $st = Database::connection()->prepare(
            'SELECT oi.id, oi.qty, oi.qty_cancelled, oi.qty_returned, COALESCE(p.is_returnable, 1) AS is_returnable
               FROM store_order_items oi LEFT JOIN store_products p ON p.id = oi.product_id
              WHERE oi.vendor_order_id = :v'
        );
        $st->execute(['v' => (int) $vo['id']]);
        $out = [];
        foreach ($st->fetchAll() as $it) {
            $left = (int) $it['qty'] - (int) $it['qty_cancelled'] - (int) $it['qty_returned'];
            if ($left > 0 && (int) $it['is_returnable'] === 1) {
                $out[(int) $it['id']] = $left;
            }
        }

        return $out;
    }

    public static function openFor(int $vendorOrderId): ?array
    {
        try {
            $in = "'" . implode("','", self::OPEN) . "'";
            $st = Database::connection()->prepare("SELECT * FROM store_returns WHERE vendor_order_id = :v AND status IN ($in) ORDER BY id DESC LIMIT 1");
            $st->execute(['v' => $vendorOrderId]);

            return $st->fetch() ?: null;
        } catch (\PDOException) {
            return null;
        }
    }

    /**
     * @param array<int, int> $itemQty
     * @param array<string, mixed> $photos $_FILES['photos'] (multiple)
     * @return array{ok: bool, error?: string, return_no?: string}
     */
    public static function request(int $identityId, int $vendorOrderId, array $itemQty, string $reason, string $note, array $photos): array
    {
        $st = Database::connection()->prepare(
            'SELECT vo.* FROM store_vendor_orders vo JOIN store_orders o ON o.id = vo.order_id WHERE vo.id = :v AND o.identity_id = :i'
        );
        $st->execute(['v' => $vendorOrderId, 'i' => $identityId]);
        $vo = $st->fetch();
        if (!$vo) {
            return ['ok' => false, 'error' => 'Package not found.'];
        }
        $allowed = self::returnable($vo);
        if (!$allowed) {
            return ['ok' => false, 'error' => 'This package can no longer be returned (return window closed, already returned, or not returnable).'];
        }
        if (!array_key_exists($reason, self::REASONS)) {
            return ['ok' => false, 'error' => 'Choose a reason.'];
        }
        $lines = [];
        foreach ($itemQty as $itemId => $q) {
            $q = (int) $q;
            if ($q <= 0) {
                continue;
            }
            if (!isset($allowed[(int) $itemId]) || $q > $allowed[(int) $itemId]) {
                return ['ok' => false, 'error' => 'One of the items can\'t be returned in that quantity.'];
            }
            $lines[(int) $itemId] = $q;
        }
        if (!$lines) {
            return ['ok' => false, 'error' => 'Choose the items to return.'];
        }
        $hasPhoto = !empty(array_filter((array) ($photos['error'] ?? []), static fn ($e) => (int) $e === UPLOAD_ERR_OK));
        if (in_array($reason, self::PHOTO_REQUIRED, true) && !$hasPhoto) {
            return ['ok' => false, 'error' => 'Please add at least one photo showing the problem.'];
        }

        $returnNo = 'RT' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $rid = QueryBuilder::table('store_returns')->insert([
                'return_no' => $returnNo,
                'order_id' => (int) $vo['order_id'],
                'vendor_order_id' => $vendorOrderId,
                'identity_id' => $identityId,
                'status' => 'requested',
                'reason_code' => $reason,
                'customer_note' => mb_substr(trim($note), 0, 1000) ?: null,
            ]);
            foreach ($lines as $itemId => $q) {
                QueryBuilder::table('store_return_items')->insert([
                    'return_id' => $rid, 'order_item_id' => $itemId, 'qty' => $q, 'reason' => self::REASONS[$reason],
                ]);
            }
            $saved = self::storePhotos($rid, $photos);
            QueryBuilder::table('store_returns')->where('id', '=', $rid)->update(['photos_json' => json_encode($saved)]);
            OrderService::history((int) $vo['order_id'], $vendorOrderId, 'return', $rid, null, 'requested', 'customer', $identityId, self::REASONS[$reason]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[ReturnService::request] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not submit the return. Please try again.'];
        }
        StoreNotifier::returnEvent($rid, 'requested');

        return ['ok' => true, 'return_no' => $returnNo];
    }

    // ------------------------------------------------------------------
    // Decisions (seller / admin)
    // ------------------------------------------------------------------

    /**
     * @param string $mode 'pickup' (reverse pickup) | 'no_pickup' (refund now, customer keeps/discards item)
     * @return array{ok: bool, error?: string}
     */
    public static function approve(int $returnId, ?int $scopeVendorId, string $mode, string $note, string $actorType, ?int $actorId): array
    {
        $r = self::find($returnId, $scopeVendorId);
        if ($r === null || $r['status'] !== 'requested') {
            return ['ok' => false, 'error' => 'This return is not awaiting a decision.'];
        }
        QueryBuilder::table('store_returns')->where('id', '=', $returnId)->update([
            'status' => 'approved', 'vendor_decision' => 'approved', 'vendor_decided_at' => date('Y-m-d H:i:s'),
            'vendor_note' => mb_substr(trim($note), 0, 500) ?: null,
            'admin_override_by' => $actorType === 'admin' ? $actorId : null,
        ]);
        OrderService::history((int) $r['order_id'], (int) $r['vendor_order_id'], 'return', $returnId, 'requested', 'approved', $actorType, $actorId, $note);

        if ($mode === 'no_pickup') {
            $res = self::refund($returnId, $actorType, $actorId, false);
            if (!$res['ok']) {
                return $res;
            }
        } else {
            $book = self::bookReversePickup($returnId);
            StoreNotifier::returnEvent($returnId, $book['ok'] ? 'pickup_scheduled' : 'approved');
        }

        return ['ok' => true];
    }

    public static function reject(int $returnId, ?int $scopeVendorId, string $note, string $actorType, ?int $actorId): array
    {
        $r = self::find($returnId, $scopeVendorId);
        if ($r === null || !in_array($r['status'], ['requested', 'qc_failed'], true)) {
            return ['ok' => false, 'error' => 'This return can\'t be rejected now.'];
        }
        if (trim($note) === '') {
            return ['ok' => false, 'error' => 'Tell the customer why.'];
        }
        QueryBuilder::table('store_returns')->where('id', '=', $returnId)->update([
            'status' => 'rejected', 'vendor_decision' => 'rejected', 'vendor_decided_at' => date('Y-m-d H:i:s'),
            'vendor_note' => mb_substr(trim($note), 0, 500), 'admin_override_by' => $actorType === 'admin' ? $actorId : null,
        ]);
        OrderService::history((int) $r['order_id'], (int) $r['vendor_order_id'], 'return', $returnId, (string) $r['status'], 'rejected', $actorType, $actorId, $note);
        StoreNotifier::returnEvent($returnId, 'rejected');

        return ['ok' => true];
    }

    /**
     * Seller (or admin) confirms the item came back. QC passed → refund now (optionally restock).
     * QC failed → the eClinicPro team decides (refund anyway, or reject).
     */
    public static function receive(int $returnId, ?int $scopeVendorId, bool $qcPassed, bool $restock, string $note, string $actorType, ?int $actorId): array
    {
        $r = self::find($returnId, $scopeVendorId);
        if ($r === null || !in_array($r['status'], ['approved', 'pickup_scheduled', 'picked_up', 'received'], true)) {
            return ['ok' => false, 'error' => 'This return is not in transit to you.'];
        }
        $to = $qcPassed ? 'qc_passed' : 'qc_failed';
        QueryBuilder::table('store_returns')->where('id', '=', $returnId)->update(['status' => $to, 'vendor_note' => mb_substr(trim($note), 0, 500) ?: $r['vendor_note']]);
        $pdo = Database::connection();
        $pdo->prepare("UPDATE store_return_items SET condition_on_receipt = :c WHERE return_id = :r")
            ->execute(['c' => $qcPassed ? 'ok' : 'damaged', 'r' => $returnId]);
        OrderService::history((int) $r['order_id'], (int) $r['vendor_order_id'], 'return', $returnId, (string) $r['status'], $to, $actorType, $actorId, $note);
        if (!$qcPassed) {
            StoreNotifier::returnEvent($returnId, 'qc_failed');

            return ['ok' => true];
        }

        return self::refund($returnId, $actorType, $actorId, $restock);
    }

    // ------------------------------------------------------------------
    // Refund + ledger reversal
    // ------------------------------------------------------------------

    /** @return array{ok: bool, error?: string, refunded?: int} */
    public static function refund(int $returnId, string $actorType, ?int $actorId, bool $restock): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM store_returns WHERE id = :id FOR UPDATE');
            $st->execute(['id' => $returnId]);
            $r = $st->fetch();
            if (!$r || in_array($r['status'], ['refunded', 'closed', 'rejected'], true)) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'This return is already closed.'];
            }
            $st = $pdo->prepare("SELECT * FROM store_payments WHERE order_id = :o AND status IN ('captured','partially_refunded') ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $st->execute(['o' => (int) $r['order_id']]);
            $pay = $st->fetch();
            if (!$pay || empty($pay['rzp_payment_id'])) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'No refundable payment found on this order.'];
            }
            $st = $pdo->prepare(
                'SELECT ri.qty AS rqty, oi.* FROM store_return_items ri JOIN store_order_items oi ON oi.id = ri.order_item_id WHERE ri.return_id = :r'
            );
            $st->execute(['r' => $returnId]);
            $lines = $st->fetchAll();
            $amount = 0;
            foreach ($lines as &$l) {
                // What the customer paid for these units (after any coupon); seller revenue for the ledger.
                $l['_refund'] = PricingService::unitShare((int) $l['line_total_paise'], (int) $l['qty'], (int) $l['qty_refunded'], (int) $l['rqty']);
                $l['_seller'] = PricingService::unitShare(PricingService::sellerRevenue($l), (int) $l['qty'], (int) $l['qty_refunded'], (int) $l['rqty']);
                $amount += $l['_refund'];
            }
            unset($l);
            if ($amount <= 0 || (int) $pay['refunded_paise'] + $amount > (int) $pay['amount_paise']) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Refund amount is invalid for this payment. Contact support.'];
            }
            $refundNo = 'RF' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $res = StorePaymentService::refund((string) $pay['rzp_payment_id'], $amount, [
                'purpose' => 'store', 'refund_no' => $refundNo, 'return_no' => (string) $r['return_no'],
            ]);
            if (empty($res['id'])) {
                $pdo->rollBack();
                error_log('[ReturnService::refund] Razorpay refused: ' . json_encode($res['error'] ?? $res));

                return ['ok' => false, 'error' => 'The refund could not be processed right now. Please try again.'];
            }
            $refundId = QueryBuilder::table('store_refunds')->insert([
                'refund_no' => $refundNo, 'order_id' => (int) $r['order_id'], 'vendor_order_id' => (int) $r['vendor_order_id'],
                'return_id' => $returnId, 'payment_id' => (int) $pay['id'], 'rzp_refund_id' => (string) $res['id'],
                'amount_paise' => $amount, 'reason' => 'return', 'note' => 'Return ' . $r['return_no'],
                'status' => ($res['status'] ?? '') === 'processed' ? 'processed' : 'pending',
                'initiated_by_type' => in_array($actorType, ['system', 'customer', 'vendor_user', 'admin'], true) ? $actorType : 'system',
                'initiated_by_id' => $actorId,
            ]);
            $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', (int) $r['vendor_order_id'])->first();
            foreach ($lines as $l) {
                $q = (int) $l['rqty'];
                $amt = (int) $l['_refund'];
                $pdo->prepare('INSERT INTO store_refund_items (refund_id, order_item_id, qty, amount_paise, shipping_paise) VALUES (:r, :i, :q, :a, 0)')
                    ->execute(['r' => $refundId, 'i' => (int) $l['id'], 'q' => $q, 'a' => $amt]);
                $pdo->prepare("UPDATE store_order_items SET qty_returned = qty_returned + :q1, qty_refunded = qty_refunded + :q2,
                                      status = IF(qty_returned + qty_cancelled >= qty, 'returned', 'partially_returned') WHERE id = :id")
                    ->execute(['q1' => $q, 'q2' => $q, 'id' => (int) $l['id']]);
                if ($restock) {
                    $pdo->prepare('UPDATE store_product_variants SET stock_qty = stock_qty + :q WHERE id = :v')->execute(['q' => $q, 'v' => (int) $l['variant_id']]);
                    $pdo->prepare("INSERT INTO store_inventory_movements (variant_id, delta, reason, ref_type, ref_id, actor_type)
                                   VALUES (:v, :d, 'restock_return', 'return', :r, :a)")
                        ->execute(['v' => (int) $l['variant_id'], 'd' => $q, 'r' => $returnId, 'a' => in_array($actorType, ['vendor_user', 'admin'], true) ? $actorType : 'system']);
                    ProductService::refreshDenormalized((int) $l['product_id']);
                }
                // Seller ledger: take back the sale, give back the matching commission + its GST.
                $commissionShare = (int) round((int) $l['commission_paise'] * $q / max(1, (int) $l['qty']));
                $gstShare = (int) round($commissionShare * CommissionService::COMMISSION_GST_BP / 10000);
                // Take back what the seller earned on these units (not what the customer paid:
                // a platform-funded coupon never came out of the seller's pocket). Three separate
                // lines so the accounts and eClinicPro's monthly invoice see the commission reversal.
                if ($vo !== null) {
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO store_vendor_ledger
                            (vendor_id, vendor_order_id, order_item_id, entry_type, amount_paise, status, available_at, dedupe_key, memo, created_by_type)
                         VALUES (:v, :vo, :oi, :t, :a, 'available', NOW(), :k, :m, 'system')"
                    );
                    $memo = 'Return ' . $r['return_no'] . ' (' . $q . ' × ' . $l['sku'] . ')';
                    foreach ([
                        ['refund_reversal', -(int) $l['_seller'], "return:$returnId:item:{$l['id']}", $memo],
                        ['commission_debit', $commissionShare, "return:$returnId:item:{$l['id']}:comm", 'Commission refunded: ' . $memo],
                        ['commission_gst_debit', $gstShare, "return:$returnId:item:{$l['id']}:commgst", 'GST on commission refunded: ' . $memo],
                    ] as [$type, $amt, $key, $m]) {
                        if ($amt !== 0) {
                            $ins->execute(['v' => (int) $vo['vendor_id'], 'vo' => (int) $vo['id'], 'oi' => (int) $l['id'], 't' => $type, 'a' => $amt, 'k' => $key, 'm' => $m]);
                        }
                    }
                }
            }
            // GST: credit note against the seller's invoice for the returned units.
            TaxDocumentService::creditForRefund((int) $refundId, 'return');
            $newRefunded = (int) $pay['refunded_paise'] + $amount;
            $full = $newRefunded >= (int) $pay['amount_paise'];
            $pdo->prepare('UPDATE store_payments SET refunded_paise = :r, status = :s WHERE id = :id')
                ->execute(['r' => $newRefunded, 's' => $full ? 'refunded' : 'partially_refunded', 'id' => (int) $pay['id']]);
            $pdo->prepare('UPDATE store_orders SET payment_status = :s WHERE id = :id')
                ->execute(['s' => $full ? 'refunded' : 'partially_refunded', 'id' => (int) $r['order_id']]);
            QueryBuilder::table('store_returns')->where('id', '=', $returnId)->update(['status' => 'refunded', 'refund_amount_paise' => $amount]);
            OrderService::history((int) $r['order_id'], (int) $r['vendor_order_id'], 'return', $returnId, (string) $r['status'], 'refunded', $actorType, $actorId,
                "Refund $refundNo of Rs. " . ProductService::rupees($amount));
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ReturnService::refund] CRITICAL ' . $e->getMessage() . (isset($res['id']) ? ' (Razorpay refund ' . $res['id'] . ' WAS issued)' : ''));

            return ['ok' => false, 'error' => 'Something went wrong while refunding. Please contact support.'];
        }
        StoreNotifier::returnEvent($returnId, 'refunded');

        return ['ok' => true, 'refunded' => $amount];
    }

    // ------------------------------------------------------------------
    // Reverse pickup (Shiprocket)
    // ------------------------------------------------------------------

    /** @return array{ok: bool, error?: string} */
    public static function bookReversePickup(int $returnId): array
    {
        if (!ShiprocketClient::configured()) {
            return ['ok' => false, 'error' => 'Shiprocket not connected: arrange the return pickup manually.'];
        }
        $r = QueryBuilder::table('store_returns')->where('id', '=', $returnId)->first();
        $vo = $r !== null ? QueryBuilder::table('store_vendor_orders')->where('id', '=', (int) $r['vendor_order_id'])->first() : null;
        $o = $r !== null ? QueryBuilder::table('store_orders')->where('id', '=', (int) $r['order_id'])->first() : null;
        if ($r === null || $vo === null || $o === null) {
            return ['ok' => false, 'error' => 'Return not found.'];
        }
        $cust = json_decode((string) $o['ship_address_json'], true) ?: [];
        $st = Database::connection()->prepare(
            "SELECT * FROM store_vendor_addresses WHERE vendor_id = :v AND type IN ('return','pickup') AND is_active = 1
              ORDER BY type = 'return' DESC, is_default DESC, id DESC LIMIT 1"
        );
        $st->execute(['v' => (int) $vo['vendor_id']]);
        $to = $st->fetch();
        if (!$to) {
            return ['ok' => false, 'error' => 'Seller has no return address.'];
        }
        $st = Database::connection()->prepare(
            'SELECT oi.name, oi.sku, oi.unit_price_paise, ri.qty, sv.weight_g, sv.length_mm, sv.breadth_mm, sv.height_mm
               FROM store_return_items ri JOIN store_order_items oi ON oi.id = ri.order_item_id
               LEFT JOIN store_product_variants sv ON sv.id = oi.variant_id WHERE ri.return_id = :r'
        );
        $st->execute(['r' => $returnId]);
        $items = [];
        $sub = $w = 0;
        $l = $b = $h = 10;
        foreach ($st->fetchAll() as $it) {
            $items[] = ['name' => mb_substr((string) $it['name'], 0, 190), 'sku' => (string) $it['sku'], 'units' => (int) $it['qty'],
                'selling_price' => round((int) $it['unit_price_paise'] / 100, 2)];
            $sub += (int) $it['unit_price_paise'] * (int) $it['qty'];
            $w += max(100, (int) $it['weight_g']) * (int) $it['qty'];
            $l = max($l, (int) $it['length_mm'] / 10);
            $b = max($b, (int) $it['breadth_mm'] / 10);
            $h = max($h, (int) $it['height_mm'] / 10);
        }
        $parts = preg_split('/\s+/', trim((string) ($cust['name'] ?? 'Customer')), 2) ?: ['Customer'];
        $shipmentNo = $vo['sub_order_no'] . '-R' . $returnId;
        // VERIFY WITH PROVIDER: return-order field names.
        $res = ShiprocketClient::post('/orders/create/return', [
            'order_id' => $shipmentNo,
            'order_date' => date('Y-m-d'),
            'pickup_customer_name' => $parts[0], 'pickup_last_name' => $parts[1] ?? '',
            'pickup_address' => (string) ($cust['line1'] ?? ''), 'pickup_address_2' => (string) ($cust['line2'] ?? ''),
            'pickup_city' => (string) ($cust['city'] ?? ''), 'pickup_state' => (string) ($cust['state'] ?? ''),
            'pickup_country' => 'India', 'pickup_pincode' => (string) ($cust['pincode'] ?? ''),
            'pickup_email' => (string) (($o['contact_email'] ?? '') ?: ($_ENV['NOREPLY_FROM'] ?? 'noreply@eclinicpro.com')),
            'pickup_phone' => substr(preg_replace('/\D/', '', (string) ($cust['phone'] ?? $o['contact_phone'])) ?? '', -10),
            'shipping_customer_name' => (string) $to['contact_name'], 'shipping_address' => (string) $to['line1'],
            'shipping_address_2' => (string) ($to['line2'] ?? ''), 'shipping_city' => (string) $to['city'],
            'shipping_state' => (string) $to['state'], 'shipping_country' => 'India', 'shipping_pincode' => (string) $to['pincode'],
            'shipping_phone' => substr(preg_replace('/\D/', '', (string) $to['phone']) ?? '', -10),
            'order_items' => $items, 'payment_method' => 'Prepaid', 'sub_total' => round($sub / 100, 2),
            'length' => $l, 'breadth' => $b, 'height' => $h, 'weight' => round($w / 1000, 3),
        ]);
        if (isset($res['_error']) || empty($res['shipment_id'])) {
            error_log('[ReturnService] reverse pickup failed: ' . json_encode($res));

            return ['ok' => false, 'error' => 'Reverse pickup booking failed: ' . ($res['_error'] ?? 'unknown') . '. Arrange it manually or retry.'];
        }
        $sid = QueryBuilder::table('store_shipments')->insert([
            'shipment_no' => $shipmentNo, 'direction' => 'return', 'order_id' => (int) $o['id'], 'vendor_order_id' => (int) $vo['id'],
            'vendor_id' => (int) $vo['vendor_id'], 'return_id' => $returnId, 'status' => 'sr_order_created', 'status_rank' => 10,
            'sr_order_id' => (string) ($res['order_id'] ?? ''), 'sr_shipment_id' => (string) $res['shipment_id'],
            'pickup_snapshot_json' => json_encode($cust, JSON_UNESCAPED_UNICODE), 'delivery_snapshot_json' => json_encode($to, JSON_UNESCAPED_UNICODE),
            'weight_g' => $w, 'declared_value_paise' => $sub,
        ]);
        $awb = ShiprocketClient::post('/courier/assign/awb', ['shipment_id' => (int) $res['shipment_id'], 'is_return' => 1]);
        $code = (string) ($awb['response']['data']['awb_code'] ?? '');
        if ($code !== '') {
            QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update([
                'awb_code' => $code, 'courier_name' => mb_substr((string) ($awb['response']['data']['courier_name'] ?? ''), 0, 120) ?: null,
                'status' => 'pickup_scheduled', 'status_rank' => 30, 'next_poll_at' => date('Y-m-d H:i:s', time() + 7200),
            ]);
        }
        QueryBuilder::table('store_returns')->where('id', '=', $returnId)->update(['status' => 'pickup_scheduled', 'return_shipment_id' => $sid]);

        return ['ok' => true];
    }

    /** Called by ShippingService when a RETURN shipment's courier status changes. */
    public static function onReturnShipment(array $shipment, string $internal): void
    {
        $rid = (int) ($shipment['return_id'] ?? 0);
        $map = ['picked_up' => 'picked_up', 'in_transit' => 'picked_up', 'out_for_delivery' => 'picked_up', 'delivered' => 'received'];
        if ($rid <= 0 || !isset($map[$internal])) {
            return;
        }
        $to = $map[$internal];
        $st = Database::connection()->prepare(
            "UPDATE store_returns SET status = :s WHERE id = :id AND status IN ('approved','pickup_scheduled'" . ($to === 'received' ? ",'picked_up'" : '') . ')'
        );
        $st->execute(['s' => $to, 'id' => $rid]);
        if ($st->rowCount() && $to === 'received') {
            StoreNotifier::returnEvent($rid, 'received');
        }
    }

    // ------------------------------------------------------------------
    // Reads + photos
    // ------------------------------------------------------------------

    /** Return (+ items, order no, seller) optionally scoped to a seller. */
    public static function find(int $returnId, ?int $scopeVendorId): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT r.*, vo.sub_order_no, vo.vendor_id, o.order_no, o.contact_name, v.display_name AS vendor_name
               FROM store_returns r JOIN store_vendor_orders vo ON vo.id = r.vendor_order_id
               JOIN store_orders o ON o.id = r.order_id JOIN store_vendors v ON v.id = vo.vendor_id
              WHERE r.id = :id' . ($scopeVendorId !== null ? ' AND vo.vendor_id = :v' : '')
        );
        $params = ['id' => $returnId];
        if ($scopeVendorId !== null) {
            $params['v'] = $scopeVendorId;
        }
        $st->execute($params);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $st = Database::connection()->prepare(
            'SELECT ri.*, oi.name, oi.sku, oi.variant_title, oi.unit_price_paise FROM store_return_items ri
               JOIN store_order_items oi ON oi.id = ri.order_item_id WHERE ri.return_id = :r'
        );
        $st->execute(['r' => $returnId]);
        $r['items'] = $st->fetchAll();
        $r['photos'] = json_decode((string) ($r['photos_json'] ?? ''), true) ?: [];

        return $r;
    }

    /** @return list<array<string, mixed>> */
    public static function list(?int $vendorId, string $status): array
    {
        $where = [];
        $params = [];
        if ($vendorId !== null) {
            $where[] = 'vo.vendor_id = :v';
            $params['v'] = $vendorId;
        }
        if ($status === 'open') {
            $where[] = "r.status IN ('" . implode("','", self::OPEN) . "')";
        } elseif ($status !== '') {
            $where[] = 'r.status = :s';
            $params['s'] = $status;
        }
        $st = Database::connection()->prepare(
            'SELECT r.*, vo.sub_order_no, v.display_name AS vendor_name, o.order_no
               FROM store_returns r JOIN store_vendor_orders vo ON vo.id = r.vendor_order_id
               JOIN store_vendors v ON v.id = vo.vendor_id JOIN store_orders o ON o.id = r.order_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY r.id DESC LIMIT 200'
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    /**
     * Private, outside web access: <repo>/storage/store_return_photos. Derived from this
     * file's location (not Application::basePath()) because customers upload from the
     * storefront, where the portal application isn't booted.
     */
    public static function photoRoot(): string
    {
        return dirname(__DIR__, 4) . '/storage/store_return_photos';
    }

    /** Streams one photo of a return (caller authorises). */
    public static function photoResponse(array $return, int $index): \App\Http\Response
    {
        $rel = (string) ($return['photos'][$index] ?? '');
        $root = realpath(self::photoRoot());
        $abs = ($root !== false && $rel !== '') ? realpath($root . '/' . $rel) : false;
        if ($abs === false || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) {
            return \App\Http\Response::html('Not found', 404);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        return new \App\Http\Response((string) file_get_contents($abs), 200, [
            'Content-Type' => self::PHOTO_EXT[$ext] ?? 'application/octet-stream',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return list<string> relative paths */
    private static function storePhotos(int $returnId, array $files): array
    {
        $out = [];
        $names = (array) ($files['name'] ?? []);
        if (!$names) {
            return $out;
        }
        $dir = self::photoRoot() . '/' . $returnId;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return $out;
        }
        if (!is_file(self::photoRoot() . '/.htaccess')) {
            @file_put_contents(self::photoRoot() . '/.htaccess', "Require all denied\n");
        }
        foreach (array_keys($names) as $i) {
            if (count($out) >= 3 || (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = (string) ($files['tmp_name'][$i] ?? '');
            if (!is_uploaded_file($tmp) || (int) ($files['size'][$i] ?? 0) > 5 * 1024 * 1024) {
                continue;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
            $ext = array_search($mime, self::PHOTO_EXT, true);
            if ($ext === false) {
                continue;
            }
            $name = 'p' . ($i + 1) . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($tmp, $dir . '/' . $name)) {
                $out[] = $returnId . '/' . $name;
            }
        }

        return $out;
    }
}
