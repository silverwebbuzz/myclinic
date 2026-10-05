<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Shiprocket shipping for seller packages. ONE Shiprocket account (ours); every
 * seller warehouse is registered as a Shiprocket "pickup location" and each
 * package ships from its seller's location.
 *
 * Booking is a chain of persisted steps, each skipped if already done, so
 * "Retry" after a failure continues where it stopped (never double-books):
 *   1 pickup location → 2 create order → 3 assign AWB → 4 schedule pickup → 5 label
 *
 * Tracking arrives by webhook (/webhooks/store-tracking) and by polling; both
 * go through applyStatus(), which is deduped and never moves a shipment
 * backwards (except exception states such as NDR).
 *
 * Endpoint shapes follow Shiprocket's public API docs — VERIFY WITH PROVIDER
 * once the account is live (field names are logged on any failure).
 */
final class ShippingService
{
    private const EXCEPTIONS = ['pickup_failed', 'ndr'];
    private const TERMINAL = ['delivered', 'rto_delivered', 'cancelled', 'lost', 'damaged'];

    // ------------------------------------------------------------------
    // Booking
    // ------------------------------------------------------------------

    /**
     * Book courier pickup for a packed package (seller or admin).
     *
     * @param array{weight_g: int, length_cm: float, breadth_cm: float, height_cm: float} $pkg
     * @return array{ok: bool, error?: string, shipment_id?: int}
     */
    public static function book(int $vendorOrderId, ?int $scopeVendorId, array $pkg, string $actorType, ?int $actorId): array
    {
        if (!ShiprocketClient::configured()) {
            return ['ok' => false, 'error' => 'Courier booking is not switched on yet. The eClinicPro team will arrange pickup.'];
        }
        $vo = self::vendorOrder($vendorOrderId, $scopeVendorId);
        if ($vo === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        $shipment = self::activeShipment($vendorOrderId);
        if ($shipment === null) {
            if ($vo['status'] !== 'packed') {
                return ['ok' => false, 'error' => 'Mark the order as packed first.'];
            }
            $weight = (int) $pkg['weight_g'];
            $dims = [(float) $pkg['length_cm'], (float) $pkg['breadth_cm'], (float) $pkg['height_cm']];
            if ($weight < 10 || $weight > 50000) {
                return ['ok' => false, 'error' => 'Enter the packed weight in grams (10 g – 50 kg).'];
            }
            foreach ($dims as $d) {
                if ($d < 1 || $d > 300) {
                    return ['ok' => false, 'error' => 'Enter the box length, breadth and height in cm (1–300).'];
                }
            }
            $pickupAddr = self::pickupAddress((int) $vo['vendor_id']);
            if ($pickupAddr === null) {
                return ['ok' => false, 'error' => 'Add a pickup address under Addresses first.'];
            }
            $seq = 1 + (int) QueryBuilder::table('store_shipments')->where('vendor_order_id', '=', $vendorOrderId)->count();
            $sid = QueryBuilder::table('store_shipments')->insert([
                'shipment_no' => $vo['sub_order_no'] . '-S' . $seq,
                'direction' => 'forward',
                'order_id' => (int) $vo['order_id'],
                'vendor_order_id' => $vendorOrderId,
                'vendor_id' => (int) $vo['vendor_id'],
                'status' => 'created',
                'pickup_snapshot_json' => json_encode($pickupAddr, JSON_UNESCAPED_UNICODE),
                'delivery_snapshot_json' => $vo['ship_address_json'],
                'weight_g' => $weight,
                'length_mm' => (int) round($dims[0] * 10),
                'breadth_mm' => (int) round($dims[1] * 10),
                'height_mm' => (int) round($dims[2] * 10),
                'declared_value_paise' => self::openValue($vendorOrderId),
            ]);
            // Every open unit ships in this parcel.
            $st = Database::connection()->prepare('SELECT id, qty, qty_cancelled FROM store_order_items WHERE vendor_order_id = :v');
            $st->execute(['v' => $vendorOrderId]);
            foreach ($st->fetchAll() as $it) {
                $open = (int) $it['qty'] - (int) $it['qty_cancelled'];
                if ($open > 0) {
                    QueryBuilder::table('store_shipment_items')->insert(['shipment_id' => $sid, 'order_item_id' => (int) $it['id'], 'qty' => $open]);
                }
            }
            OrderService::history((int) $vo['order_id'], $vendorOrderId, 'shipment', $sid, null, 'created', $actorType, $actorId, 'Courier booking started');
            $shipment = QueryBuilder::table('store_shipments')->where('id', '=', $sid)->first();
        }

        return self::continueBooking($shipment, $vo, $actorType, $actorId);
    }

    /** Runs the remaining booking steps for a shipment (also used by "Retry"). */
    public static function continueBooking(array $s, array $vo, string $actorType, ?int $actorId): array
    {
        $sid = (int) $s['id'];
        $fail = static function (string $step, array $res) use ($sid): array {
            $msg = $step . ': ' . (string) ($res['_error'] ?? 'unknown error');
            Database::connection()->prepare('UPDATE store_shipments SET attempts = attempts + 1, last_error = :e WHERE id = :id')
                ->execute(['e' => mb_substr($msg . ' | ' . json_encode($res), 0, 2000), 'id' => $sid]);
            error_log('[Shipping] shipment ' . $sid . ' ' . $msg);

            return ['ok' => false, 'error' => 'Courier booking failed at "' . $step . '". ' . self::friendly($res) . ' You can retry.', 'shipment_id' => $sid];
        };

        // 1. Seller's pickup location registered on our Shiprocket account.
        if (empty($s['pickup_location_name'])) {
            $pickup = json_decode((string) $s['pickup_snapshot_json'], true) ?: [];
            $name = self::ensurePickupLocation((int) $pickup['id'], (int) $s['vendor_id']);
            if (!is_string($name)) {
                return $fail('pickup address', $name);
            }
            QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update(['pickup_location_name' => $name]);
            $s['pickup_location_name'] = $name;
        }

        // 2. Shiprocket order.
        if (empty($s['sr_shipment_id'])) {
            $res = ShiprocketClient::post('/orders/create/adhoc', self::orderPayload($s, $vo));
            if (isset($res['_error']) || empty($res['shipment_id'])) {
                return $fail('create order', $res);
            }
            QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update([
                'sr_order_id' => (string) ($res['order_id'] ?? ''),
                'sr_shipment_id' => (string) $res['shipment_id'],
                'status' => 'sr_order_created', 'status_rank' => 10, 'last_error' => null,
            ]);
            $s['sr_shipment_id'] = (string) $res['shipment_id'];
            $s['sr_order_id'] = (string) ($res['order_id'] ?? '');
            if (!empty($res['awb_code'])) {   // some accounts auto-assign
                $s['awb_code'] = (string) $res['awb_code'];
                self::saveAwb($sid, $s['awb_code'], (string) ($res['courier_name'] ?? ''), (int) ($res['courier_company_id'] ?? 0));
            }
        }

        // 3. AWB (courier chosen by Shiprocket's recommendation rules on the account).
        if (empty($s['awb_code'])) {
            $res = ShiprocketClient::post('/courier/assign/awb', ['shipment_id' => (int) $s['sr_shipment_id']]);
            $awb = (string) ($res['response']['data']['awb_code'] ?? '');
            if (isset($res['_error']) || $awb === '') {
                self::mark($sid, 'awb_failed');

                return $fail('assign AWB', $res);
            }
            self::saveAwb($sid, $awb, (string) ($res['response']['data']['courier_name'] ?? ''), (int) ($res['response']['data']['courier_company_id'] ?? 0));
            self::saveCharge($sid, (array) ($res['response']['data'] ?? []));
            $s['awb_code'] = $awb;
        }
        // AWB in hand (from step 2 or 3): the package is ready to hand over. Guarded, so it runs once.
        $rts = Database::connection()->prepare("UPDATE store_vendor_orders SET status = 'ready_to_ship' WHERE id = :id AND status = 'packed'");
        $rts->execute(['id' => (int) $vo['id']]);
        if ($rts->rowCount() === 1) {
            OrderService::history((int) $vo['order_id'], (int) $vo['id'], 'shipment', $sid, 'created', 'awb_assigned', $actorType, $actorId, 'AWB ' . $s['awb_code']);
        }
        TaxDocumentService::issueForPackage((int) $vo['id']);   // invoice goes in the box with the label

        // 4. Pickup request.
        if (empty($s['pickup_scheduled_for'])) {
            $res = ShiprocketClient::post('/courier/generate/pickup', ['shipment_id' => [(int) $s['sr_shipment_id']]]);
            $already = str_contains(strtolower((string) ($res['_error'] ?? '')), 'already');
            if (isset($res['_error']) && !$already) {
                self::mark($sid, 'pickup_failed');

                return $fail('schedule pickup', $res);
            }
            $date = substr((string) ($res['response']['pickup_scheduled_date'] ?? ''), 0, 10);
            QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update([
                'pickup_scheduled_for' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d', time() + 86400),
                'status' => 'pickup_scheduled', 'status_rank' => 30, 'last_error' => null,
                'next_poll_at' => date('Y-m-d H:i:s', time() + 7200),
            ]);
        }

        // 5. Label (non-fatal: can be fetched again later).
        if (empty($s['label_url'])) {
            $res = ShiprocketClient::post('/courier/generate/label', ['shipment_id' => [(int) $s['sr_shipment_id']]]);
            if (!empty($res['label_url'])) {
                QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update(['label_url' => (string) $res['label_url']]);
            }
        }

        OrderService::recomputeStatus((int) $vo['order_id']);

        return ['ok' => true, 'shipment_id' => $sid];
    }

    /** Cancel a shipment that hasn't been picked up yet (admin). Package goes back to "packed". */
    public static function cancel(int $shipmentId, int $adminId): array
    {
        $s = QueryBuilder::table('store_shipments')->where('id', '=', $shipmentId)->first();
        if ($s === null || (int) $s['status_rank'] >= 40 || in_array($s['status'], self::TERMINAL, true)) {
            return ['ok' => false, 'error' => 'Only shipments not yet picked up can be cancelled here.'];
        }
        if (!empty($s['sr_order_id'])) {
            $res = ShiprocketClient::post('/orders/cancel', ['ids' => [(int) $s['sr_order_id']]]);
            if (isset($res['_error'])) {
                return ['ok' => false, 'error' => 'Shiprocket refused: ' . self::friendly($res)];
            }
        }
        QueryBuilder::table('store_shipments')->where('id', '=', $shipmentId)->update(['status' => 'cancelled', 'status_rank' => 130]);
        Database::connection()->prepare("UPDATE store_vendor_orders SET status = 'packed' WHERE id = :id AND status IN ('ready_to_ship','packed')")
            ->execute(['id' => (int) $s['vendor_order_id']]);
        OrderService::history((int) $s['order_id'], (int) $s['vendor_order_id'], 'shipment', $shipmentId, (string) $s['status'], 'cancelled', 'admin', $adminId, 'Shipment cancelled; package back to packed');

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Tracking
    // ------------------------------------------------------------------

    /** Pull tracking for one shipment from Shiprocket (poll / "refresh" button). */
    public static function refresh(array $s): bool
    {
        if (empty($s['awb_code']) || !ShiprocketClient::configured()) {
            return false;
        }
        $res = ShiprocketClient::get('/courier/track/awb/' . rawurlencode((string) $s['awb_code']));
        Database::connection()->prepare('UPDATE store_shipments SET next_poll_at = :n WHERE id = :id')
            ->execute(['n' => date('Y-m-d H:i:s', time() + 7200), 'id' => (int) $s['id']]);
        $td = $res['tracking_data'] ?? null;
        if (!is_array($td)) {
            return false;
        }
        // Oldest first so the timeline builds in order.
        $acts = array_reverse((array) ($td['shipment_track_activities'] ?? []));
        foreach ($acts as $a) {
            self::applyStatus($s, (string) ($a['sr-status-label'] ?? $a['activity'] ?? ''), (string) ($a['sr-status'] ?? ''),
                (string) ($a['date'] ?? ''), (string) ($a['location'] ?? ''), (string) ($a['activity'] ?? ''), 'poll');
            $s = QueryBuilder::table('store_shipments')->where('id', '=', (int) $s['id'])->first() ?? $s;
        }
        $current = (string) ($td['shipment_track'][0]['current_status'] ?? '');
        if ($current !== '') {
            self::applyStatus($s, $current, '', (string) ($td['shipment_track'][0]['updated_time_stamp'] ?? ''), '', $current, 'poll');
        }

        return true;
    }

    /** Poll shipments that haven't reported in a while (maintenance worker). */
    public static function pollDue(int $limit = 30): int
    {
        if (!ShiprocketClient::configured()) {
            return 0;
        }
        $in = "'" . implode("','", self::TERMINAL) . "'";
        $st = Database::connection()->prepare(
            "SELECT * FROM store_shipments WHERE awb_code IS NOT NULL AND status NOT IN ($in)
               AND (next_poll_at IS NULL OR next_poll_at < NOW()) ORDER BY next_poll_at LIMIT " . max(1, min(100, $limit))
        );
        $st->execute();
        $n = 0;
        foreach ($st->fetchAll() as $s) {
            $n += self::refresh($s) ? 1 : 0;
        }

        return $n;
    }

    /**
     * Shiprocket tracking webhook. Auth = the x-api-key header we set in Shiprocket
     * (must equal store_shiprocket_webhook_key) — VERIFY header name with provider.
     */
    public static function handleWebhook(string $payload, ?string $apiKey): bool
    {
        $expected = StoreSettings::get('store_shiprocket_webhook_key');
        if ($expected === '' || $apiKey === null || !hash_equals($expected, $apiKey)) {
            error_log('[Shipping] webhook auth failed');

            return false;
        }
        $e = json_decode($payload, true);
        if (!is_array($e)) {
            return true;
        }
        $awb = (string) ($e['awb'] ?? '');
        $status = (string) ($e['current_status'] ?? $e['shipment_status'] ?? '');
        $eventId = hash('sha256', $awb . '|' . $status . '|' . ($e['current_timestamp'] ?? '') . '|' . ($e['current_status_id'] ?? ''));
        $ins = Database::connection()->prepare(
            "INSERT IGNORE INTO store_webhook_events (provider, event_id, event_type, signature_ok, payload) VALUES ('shiprocket', :e, :t, 1, :p)"
        );
        $ins->execute(['e' => $eventId, 't' => mb_substr($status, 0, 80), 'p' => $payload]);
        if ($ins->rowCount() === 0) {
            return true;   // duplicate delivery
        }
        $s = $awb !== '' ? QueryBuilder::table('store_shipments')->where('awb_code', '=', $awb)->first() : null;
        if ($s === null && !empty($e['sr_order_id'])) {
            $s = QueryBuilder::table('store_shipments')->where('sr_order_id', '=', (string) $e['sr_order_id'])->first();
        }
        if ($s === null) {
            Database::connection()->prepare("UPDATE store_webhook_events SET status = 'ignored', processed_at = NOW() WHERE provider = 'shiprocket' AND event_id = :e")
                ->execute(['e' => $eventId]);

            return true;   // not ours (or not yet saved) — acknowledge
        }
        foreach ((array) ($e['scans'] ?? []) as $scan) {
            self::applyStatus($s, (string) ($scan['sr-status-label'] ?? $scan['status'] ?? ''), (string) ($scan['sr-status'] ?? ''),
                (string) ($scan['date'] ?? ''), (string) ($scan['location'] ?? ''), (string) ($scan['activity'] ?? ''), 'webhook');
            $s = QueryBuilder::table('store_shipments')->where('id', '=', (int) $s['id'])->first() ?? $s;
        }
        self::applyStatus($s, $status, (string) ($e['current_status_id'] ?? ''), (string) ($e['current_timestamp'] ?? ''), '', $status, 'webhook');
        Database::connection()->prepare("UPDATE store_webhook_events SET status = 'processed', processed_at = NOW() WHERE provider = 'shiprocket' AND event_id = :e")
            ->execute(['e' => $eventId]);

        return true;
    }

    /**
     * Record a tracking event and move the shipment / package / order forward.
     * Idempotent (dedupe key) and monotonic (rank), except exception states.
     */
    public static function applyStatus(array $s, string $rawStatus, string $rawCode, string $when, string $location, string $activity, string $source): void
    {
        $raw = strtoupper(trim($rawStatus));
        if ($raw === '') {
            return;
        }
        $map = self::statusMap();
        $internal = $map[$raw]['internal'] ?? null;
        $rank = (int) ($map[$raw]['rank'] ?? 0);
        $ts = strtotime($when) ?: time();

        $ins = Database::connection()->prepare(
            'INSERT IGNORE INTO store_shipment_events
                (shipment_id, raw_status, raw_code, internal_status, location, activity, event_at, source, dedupe_key)
             VALUES (:s, :rs, :rc, :i, :l, :a, :t, :src, :k)'
        );
        $ins->execute([
            's' => (int) $s['id'], 'rs' => mb_substr($raw, 0, 80), 'rc' => mb_substr($rawCode, 0, 20) ?: null, 'i' => $internal,
            'l' => mb_substr($location, 0, 190) ?: null, 'a' => mb_substr($activity, 0, 500) ?: null,
            't' => date('Y-m-d H:i:s', $ts), 'src' => $source,
            'k' => hash('sha256', $s['awb_code'] . '|' . $raw . '|' . $ts . '|' . $location),
        ]);
        if ($internal === null) {
            if ($ins->rowCount() === 1) {
                error_log('[Shipping] unmapped Shiprocket status "' . $raw . '": add it in store_shiprocket_status_map');
            }

            return;
        }
        $isException = in_array($internal, self::EXCEPTIONS, true);
        if (!$isException && $rank <= (int) $s['status_rank']) {
            return;   // old or duplicate news
        }
        if (in_array($s['status'], self::TERMINAL, true) && !in_array($internal, ['rto_initiated', 'rto_delivered'], true)) {
            return;
        }

        $upd = ['status' => $internal, 'last_raw_status' => mb_substr($raw, 0, 80), 'last_event_at' => date('Y-m-d H:i:s', $ts)];
        if (!$isException) {
            $upd['status_rank'] = $rank;
        }
        if ($internal === 'picked_up' && empty($s['picked_up_at'])) {
            $upd['picked_up_at'] = date('Y-m-d H:i:s', $ts);
        }
        if ($internal === 'delivered') {
            $upd['delivered_at'] = date('Y-m-d H:i:s', $ts);
        }
        if (in_array($internal, ['rto_initiated', 'rto_delivered'], true) && empty($s['rto_at'])) {
            $upd['rto_at'] = date('Y-m-d H:i:s', $ts);
        }
        QueryBuilder::table('store_shipments')->where('id', '=', (int) $s['id'])->update($upd);
        if (($s['direction'] ?? 'forward') === 'return') {
            ReturnService::onReturnShipment($s, $internal);   // customer → seller leg of a return

            return;
        }
        self::syncVendorOrder($s, $internal, $ts);
    }

    /** Shipment → package → order status, plus customer/admin notifications. */
    private static function syncVendorOrder(array $s, string $internal, int $ts): void
    {
        $pdo = Database::connection();
        $voId = (int) $s['vendor_order_id'];
        $orderId = (int) $s['order_id'];
        $when = date('Y-m-d H:i:s', $ts);
        $changed = null;

        if (in_array($internal, ['picked_up', 'in_transit', 'out_for_delivery'], true)) {
            $st = $pdo->prepare("UPDATE store_vendor_orders SET status = 'shipped', shipped_at = COALESCE(shipped_at, :t)
                                  WHERE id = :id AND status IN ('packed','ready_to_ship')");
            $st->execute(['t' => $when, 'id' => $voId]);
            $changed = $st->rowCount() ? 'shipped' : null;
        } elseif ($internal === 'delivered') {
            // Return window starts at delivery; money becomes payable after it (P9).
            $days = max(0, StoreSettings::int('store_default_return_window_days', 7));
            $vendor = QueryBuilder::table('store_vendors')->where('id', '=', (int) $s['vendor_id'])->first();
            if ($vendor !== null) {
                $days = (int) $vendor['default_return_window_days'];
            }
            $st = $pdo->prepare("UPDATE store_vendor_orders SET status = 'delivered', delivered_at = :t, settle_after = :sa
                                  WHERE id = :id AND status IN ('packed','ready_to_ship','shipped')");
            $st->execute(['t' => $when, 'sa' => date('Y-m-d H:i:s', $ts + $days * 86400), 'id' => $voId]);
            $changed = $st->rowCount() ? 'delivered' : null;
            if ($changed !== null) {
                SettlementService::recordDelivered($voId);   // seller's earnings: pending until the return window ends
            }
        } elseif ($internal === 'rto_delivered') {
            $st = $pdo->prepare("UPDATE store_vendor_orders SET status = 'rto' WHERE id = :id AND status <> 'rto'");
            $st->execute(['id' => $voId]);
            $changed = $st->rowCount() ? 'rto' : null;
        }
        if ($changed === 'shipped' || $changed === 'delivered') {
            TaxDocumentService::issueForPackage($voId);   // no-op if already issued at booking
        }
        if ($changed !== null) {
            OrderService::history($orderId, $voId, 'vendor_order', $voId, null, $changed, 'webhook', null, 'Courier: ' . $internal);
            OrderService::recomputeStatus($orderId);
            StoreNotifier::shipmentUpdate((int) $s['id'], $changed);
        }
        if ($internal === 'out_for_delivery') {
            // Reaches here once per shipment: applyStatus() drops statuses that don't raise status_rank.
            StoreNotifier::shipmentUpdate((int) $s['id'], 'out_for_delivery');
        } elseif ($changed === null && in_array($internal, ['ndr', 'pickup_failed', 'lost', 'damaged', 'rto_initiated'], true)) {
            OrderService::history($orderId, $voId, 'shipment', (int) $s['id'], null, $internal, 'webhook', null, 'Courier exception');
            StoreNotifier::shipmentUpdate((int) $s['id'], $internal);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Registers the seller's pickup address on Shiprocket once; returns the location name or an error array. */
    private static function ensurePickupLocation(int $addressId, int $vendorId): string|array
    {
        $a = QueryBuilder::table('store_vendor_addresses')->where('id', '=', $addressId)->where('vendor_id', '=', $vendorId)->first();
        if ($a === null) {
            return ['_error' => 'Pickup address not found'];
        }
        if (!empty($a['sr_pickup_name']) && $a['sr_pickup_status'] !== 'failed') {
            return (string) $a['sr_pickup_name'];
        }
        $vendor = QueryBuilder::table('store_vendors')->where('id', '=', $vendorId)->first() ?? [];
        $name = 'ECP-V' . $vendorId . '-A' . $addressId;   // ≤ 36 chars, stable, unique
        $res = ShiprocketClient::post('/settings/company/addpickup', [
            'pickup_location' => $name,
            'name' => mb_substr((string) $a['contact_name'], 0, 60),
            'email' => (string) ($a['email'] ?: ($vendor['email'] ?? '')),
            'phone' => substr(preg_replace('/\D/', '', (string) $a['phone']) ?? '', -10),
            'address' => mb_substr((string) $a['line1'], 0, 200) . (mb_strlen((string) $a['line1']) < 10 ? ', ' . $a['city'] : ''),
            'address_2' => (string) ($a['line2'] ?? ''),
            'city' => (string) $a['city'],
            'state' => (string) $a['state'],
            'country' => 'India',
            'pin_code' => (string) $a['pincode'],
        ]);
        $exists = str_contains(strtolower((string) ($res['_error'] ?? '')), 'already');
        if (isset($res['_error']) && !$exists) {
            QueryBuilder::table('store_vendor_addresses')->where('id', '=', $addressId)->update(['sr_pickup_status' => 'failed']);

            return $res;
        }
        QueryBuilder::table('store_vendor_addresses')->where('id', '=', $addressId)->update(['sr_pickup_name' => $name, 'sr_pickup_status' => 'verified']);

        return $name;
    }

    /** @return array<string, mixed> */
    private static function orderPayload(array $s, array $vo): array
    {
        $ship = json_decode((string) $s['delivery_snapshot_json'], true) ?: [];
        $parts = preg_split('/\s+/', trim((string) ($ship['name'] ?? 'Customer')), 2) ?: ['Customer'];
        $st = Database::connection()->prepare(
            'SELECT oi.name, oi.sku, oi.unit_price_paise, oi.gst_bp, oi.hsn_code, si.qty
               FROM store_shipment_items si JOIN store_order_items oi ON oi.id = si.order_item_id
              WHERE si.shipment_id = :s'
        );
        $st->execute(['s' => (int) $s['id']]);
        $items = [];
        $sub = 0;
        foreach ($st->fetchAll() as $it) {
            $items[] = [
                'name' => mb_substr((string) $it['name'], 0, 190),
                'sku' => (string) $it['sku'],
                'units' => (int) $it['qty'],
                'selling_price' => round((int) $it['unit_price_paise'] / 100, 2),
                'discount' => 0,
                'tax' => round((int) $it['gst_bp'] / 100, 2),
                'hsn' => (string) ($it['hsn_code'] ?? ''),
            ];
            $sub += (int) $it['unit_price_paise'] * (int) $it['qty'];
        }
        $phone = substr(preg_replace('/\D/', '', (string) ($ship['phone'] ?? $vo['contact_phone'] ?? '')) ?? '', -10);

        return [
            // Channel order id = our shipment number (unique per attempt, so a re-booking never collides).
            'order_id' => (string) $s['shipment_no'],
            'order_date' => date('Y-m-d H:i'),
            'pickup_location' => (string) $s['pickup_location_name'],
            'billing_customer_name' => $parts[0],
            'billing_last_name' => $parts[1] ?? '',
            'billing_address' => mb_substr(trim(($ship['line1'] ?? '') . (!empty($ship['landmark']) ? ', near ' . $ship['landmark'] : '')), 0, 200),
            'billing_address_2' => (string) ($ship['line2'] ?? ''),
            'billing_city' => (string) ($ship['city'] ?? ''),
            'billing_pincode' => (string) ($ship['pincode'] ?? ''),
            'billing_state' => (string) ($ship['state'] ?? ''),
            'billing_country' => 'India',
            'billing_email' => (string) (($vo['contact_email'] ?? '') ?: ($_ENV['NOREPLY_FROM'] ?? 'noreply@eclinicpro.com')),
            'billing_phone' => $phone,
            'shipping_is_billing' => true,
            'order_items' => $items,
            'payment_method' => 'Prepaid',
            'shipping_charges' => 0,
            'sub_total' => round($sub / 100, 2),
            'length' => round((int) $s['length_mm'] / 10, 1),
            'breadth' => round((int) $s['breadth_mm'] / 10, 1),
            'height' => round((int) $s['height_mm'] / 10, 1),
            'weight' => round((int) $s['weight_g'] / 1000, 3),
        ];
    }

    private static function saveAwb(int $sid, string $awb, string $courier, int $courierId): void
    {
        QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update([
            'awb_code' => $awb, 'courier_name' => $courier !== '' ? mb_substr($courier, 0, 120) : null,
            'courier_id' => $courierId > 0 ? $courierId : null,
            'status' => 'awb_assigned', 'status_rank' => 20, 'last_error' => null,
        ]);
    }

    /**
     * Freight Shiprocket billed for the shipment, when the AWB response carries it.
     * VERIFY WITH PROVIDER: field name. Otherwise admin enters it on the order page,
     * or the rate-card estimate is used at delivery (SellerFeeService).
     */
    private static function saveCharge(int $sid, array $data): void
    {
        foreach (['freight_charges', 'freight_charge', 'shipping_charges', 'rate'] as $k) {
            if (isset($data[$k]) && is_numeric($data[$k]) && (float) $data[$k] > 0) {
                QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update(['actual_charge_paise' => (int) round((float) $data[$k] * 100)]);

                return;
            }
        }
        error_log('[Shipping] no freight charge in AWB response for shipment ' . $sid . ': keys ' . implode(',', array_keys($data)));
    }

    // ------------------------------------------------------------------
    // Parcel photo (packed parcel on a scale — evidence for weight disputes)
    // ------------------------------------------------------------------

    private const PHOTO_EXT = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /** Private, outside web access: <repo>/storage/store_parcel_photos. */
    public static function parcelPhotoRoot(): string
    {
        return dirname(__DIR__, 4) . '/storage/store_parcel_photos';
    }

    /**
     * Save the seller's parcel photo for a package (replaces an earlier one).
     *
     * @param array<string, mixed> $file one $_FILES entry
     * @return array{ok: bool, error?: string}
     */
    public static function saveParcelPhoto(int $vendorOrderId, int $vendorId, array $file): array
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'The photo is too large for the server. Take it at a lower resolution (or send a screenshot of it) and try again.'];
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'error' => 'Attach a photo of the packed parcel on the scale.'];
        }
        if ((int) ($file['size'] ?? 0) > 6 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'The photo is too large (max 6 MB).'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
        $ext = array_search($mime, self::PHOTO_EXT, true);
        if ($ext === false) {
            return ['ok' => false, 'error' => 'The parcel photo must be a JPG, PNG or WebP image.'];
        }
        $root = self::parcelPhotoRoot();
        $dir = $root . '/' . $vendorId;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not save the photo. Please try again.'];
        }
        if (!is_file($root . '/.htaccess')) {
            @file_put_contents($root . '/.htaccess', "Require all denied\n");
        }
        $name = 'vo' . $vendorOrderId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Could not save the photo. Please try again.'];
        }
        try {
            QueryBuilder::table('store_vendor_orders')->where('id', '=', $vendorOrderId)->where('vendor_id', '=', $vendorId)
                ->update(['parcel_photo_path' => $vendorId . '/' . $name]);
        } catch (\Throwable $e) {
            error_log('[Shipping::saveParcelPhoto] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Parcel photos are not switched on yet (database update pending). Please contact eClinicPro.'];
        }

        return ['ok' => true];
    }

    /** Streams a package's parcel photo (caller authorises). */
    public static function parcelPhotoResponse(array $vendorOrder): \App\Http\Response
    {
        $rel = (string) ($vendorOrder['parcel_photo_path'] ?? '');
        $root = realpath(self::parcelPhotoRoot());
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

    private static function mark(int $sid, string $status): void
    {
        QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update(['status' => $status]);
    }

    /** @return array<string, array{internal: string, rank: int}> */
    private static function statusMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (Database::connection()->query('SELECT raw_status, internal_status, status_rank FROM store_shiprocket_status_map')->fetchAll() as $r) {
                $map[strtoupper((string) $r['raw_status'])] = ['internal' => (string) $r['internal_status'], 'rank' => (int) $r['status_rank']];
            }
        }

        return $map;
    }

    private static function vendorOrder(int $id, ?int $scopeVendorId): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT vo.*, o.order_no, o.ship_address_json, o.contact_email, o.contact_phone
               FROM store_vendor_orders vo JOIN store_orders o ON o.id = vo.order_id
              WHERE vo.id = :id AND o.payment_status IN ('paid','partially_refunded')"
            . ($scopeVendorId !== null ? ' AND vo.vendor_id = :v' : '')
        );
        $params = ['id' => $id];
        if ($scopeVendorId !== null) {
            $params['v'] = $scopeVendorId;
        }
        $st->execute($params);

        return $st->fetch() ?: null;
    }

    public static function activeShipment(int $vendorOrderId): ?array
    {
        try {
            $st = Database::connection()->prepare(
                "SELECT * FROM store_shipments WHERE vendor_order_id = :v AND direction = 'forward' AND status <> 'cancelled' ORDER BY id DESC LIMIT 1"
            );
            $st->execute(['v' => $vendorOrderId]);

            return $st->fetch() ?: null;
        } catch (\PDOException) {
            return null;   // fulfilment patch not imported yet
        }
    }

    /** @return list<array<string, mixed>> shipments of an order with their events */
    public static function forOrder(int $orderId): array
    {
        try {
            $st = Database::connection()->prepare('SELECT * FROM store_shipments WHERE order_id = :o ORDER BY id');
            $st->execute(['o' => $orderId]);
            $rows = $st->fetchAll();
        } catch (\PDOException) {
            return [];   // fulfilment patch not imported yet
        }
        $ev = Database::connection()->prepare('SELECT * FROM store_shipment_events WHERE shipment_id = :s ORDER BY event_at DESC, id DESC LIMIT 30');
        foreach ($rows as &$r) {
            $ev->execute(['s' => (int) $r['id']]);
            $r['events'] = $ev->fetchAll();
        }
        unset($r);

        return $rows;
    }

    /** Packed weight + box size suggested from the product data. */
    public static function suggestPackage(int $vendorOrderId): array
    {
        $st = Database::connection()->prepare(
            'SELECT (oi.qty - oi.qty_cancelled) AS q, sv.weight_g, sv.length_mm, sv.breadth_mm, sv.height_mm
               FROM store_order_items oi JOIN store_product_variants sv ON sv.id = oi.variant_id
              WHERE oi.vendor_order_id = :v'
        );
        $st->execute(['v' => $vendorOrderId]);
        $w = 0;
        $l = $b = $h = 0;
        foreach ($st->fetchAll() as $r) {
            $q = max(0, (int) $r['q']);
            $w += (int) $r['weight_g'] * $q;
            $l = max($l, (int) $r['length_mm']);
            $b = max($b, (int) $r['breadth_mm']);
            $h += (int) $r['height_mm'] * $q;   // items stacked
        }

        return ['weight_g' => max(50, $w), 'length_cm' => max(10, $l / 10), 'breadth_cm' => max(10, $b / 10), 'height_cm' => max(5, min(100, $h / 10))];
    }

    private static function pickupAddress(int $vendorId): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT id, contact_name, phone, line1, line2, city, state, pincode FROM store_vendor_addresses
              WHERE vendor_id = :v AND type = 'pickup' AND is_active = 1 ORDER BY is_default DESC, id DESC LIMIT 1"
        );
        $st->execute(['v' => $vendorId]);

        return $st->fetch() ?: null;
    }

    private static function openValue(int $vendorOrderId): int
    {
        $st = Database::connection()->prepare('SELECT COALESCE(SUM((qty - qty_cancelled) * unit_price_paise), 0) FROM store_order_items WHERE vendor_order_id = :v');
        $st->execute(['v' => $vendorOrderId]);

        return (int) $st->fetchColumn();
    }

    private static function friendly(array $res): string
    {
        $m = trim((string) ($res['_error'] ?? ''));

        return $m !== '' ? 'Courier said: ' . mb_substr($m, 0, 160) . '.' : '';
    }
}
