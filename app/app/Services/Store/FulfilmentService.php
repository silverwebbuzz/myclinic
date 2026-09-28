<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Seller-side package workflow (before shipping, which arrives with Shiprocket in P8):
 *
 *   new ──accept──▶ accepted ──mark packed──▶ packed ──(P8: create shipment)──▶ shipped …
 *    │                  │                        │
 *    └── cancel items (refund) ─────────────────┘   see StoreRefundService
 *
 * A package still `new` after its accept-by deadline is auto-cancelled and refunded.
 */
final class FulfilmentService
{
    /** @return array{ok: bool, error?: string} */
    public static function accept(int $vendorId, int $vendorOrderId, int $userId): array
    {
        return self::advance($vendorId, $vendorOrderId, 'new', 'accepted', ['accepted_at' => date('Y-m-d H:i:s')], $userId, 'Accepted by seller');
    }

    /** @return array{ok: bool, error?: string} */
    public static function markPacked(int $vendorId, int $vendorOrderId, int $userId): array
    {
        return self::advance($vendorId, $vendorOrderId, 'accepted', 'packed', ['packed_at' => date('Y-m-d H:i:s')], $userId, 'Packed by seller');
    }

    /**
     * Auto-cancel + refund packages not accepted in time. Returns how many were cancelled.
     * Called lazily from order screens and by the maintenance worker.
     */
    public static function autoCancelOverdue(int $limit = 20): int
    {
        try {
            $st = Database::connection()->prepare(
                "SELECT vo.id FROM store_vendor_orders vo
                   JOIN store_orders o ON o.id = vo.order_id AND o.payment_status IN ('paid','partially_refunded')
                  WHERE vo.status = 'new' AND vo.accept_by IS NOT NULL AND vo.accept_by < NOW()
                  ORDER BY vo.accept_by LIMIT " . max(1, min(100, $limit))
            );
            $st->execute();
            $n = 0;
            foreach ($st->fetchAll() as $r) {
                $res = StoreRefundService::cancelVendorOrder((int) $r['id'],
                    'Seller did not accept the order in time', 'system', null, true);
                $n += $res['ok'] ? 1 : 0;
                if (!$res['ok']) {
                    error_log('[Fulfilment::autoCancelOverdue] vo ' . $r['id'] . ': ' . ($res['error'] ?? ''));
                }
            }

            return $n;
        } catch (\Throwable $e) {
            error_log('[Fulfilment::autoCancelOverdue] ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Admin override: mark a package shipped by hand (courier arranged outside Shiprocket).
     * Creates a "manual" shipment record so the customer still sees courier + tracking number.
     */
    public static function adminMarkShipped(int $vendorOrderId, string $courier, string $awb, int $adminId): array
    {
        $pdo = Database::connection();
        $vo = \App\Core\QueryBuilder::table('store_vendor_orders')->where('id', '=', $vendorOrderId)->first();
        if ($vo === null || !in_array($vo['status'], ['accepted', 'packed', 'ready_to_ship'], true)) {
            return ['ok' => false, 'error' => 'Only accepted/packed packages can be marked shipped.'];
        }
        $awb = mb_substr(preg_replace('/\s+/', '', $awb) ?? '', 0, 40);
        $existing = ShippingService::activeShipment($vendorOrderId);
        $now = date('Y-m-d H:i:s');
        if ($existing !== null) {
            \App\Core\QueryBuilder::table('store_shipments')->where('id', '=', (int) $existing['id'])->update(array_filter([
                'status' => 'picked_up', 'status_rank' => 40, 'picked_up_at' => $now, 'last_raw_status' => 'MARKED SHIPPED BY ADMIN',
                'courier_name' => $courier !== '' ? mb_substr($courier, 0, 120) : null,
            ], static fn ($v) => $v !== null));
            $sid = (int) $existing['id'];
        } else {
            $seq = 1 + (int) \App\Core\QueryBuilder::table('store_shipments')->where('vendor_order_id', '=', $vendorOrderId)->count();
            $sid = \App\Core\QueryBuilder::table('store_shipments')->insert([
                'shipment_no' => $vo['sub_order_no'] . '-S' . $seq, 'direction' => 'forward',
                'order_id' => (int) $vo['order_id'], 'vendor_order_id' => $vendorOrderId, 'vendor_id' => (int) $vo['vendor_id'],
                'status' => 'picked_up', 'status_rank' => 40, 'awb_code' => $awb !== '' ? $awb : null,
                'courier_name' => $courier !== '' ? mb_substr($courier, 0, 120) : 'Manual',
                'picked_up_at' => $now, 'last_raw_status' => 'MARKED SHIPPED BY ADMIN', 'last_event_at' => $now,
            ]);
        }
        $pdo->prepare("UPDATE store_vendor_orders SET status = 'shipped', shipped_at = :t WHERE id = :id")->execute(['t' => $now, 'id' => $vendorOrderId]);
        TaxDocumentService::issueForPackage($vendorOrderId);
        OrderService::history((int) $vo['order_id'], $vendorOrderId, 'vendor_order', $vendorOrderId, (string) $vo['status'], 'shipped', 'admin', $adminId,
            'Marked shipped manually' . ($courier !== '' ? " ($courier" . ($awb !== '' ? " $awb" : '') . ')' : ''));
        OrderService::recomputeStatus((int) $vo['order_id']);
        StoreNotifier::shipmentUpdate($sid, 'shipped');

        return ['ok' => true];
    }

    /** Admin override: mark a shipped package delivered (starts the return window + seller settlement). */
    public static function adminMarkDelivered(int $vendorOrderId, int $adminId): array
    {
        $vo = \App\Core\QueryBuilder::table('store_vendor_orders')->where('id', '=', $vendorOrderId)->first();
        if ($vo === null || !in_array($vo['status'], ['shipped', 'ready_to_ship'], true)) {
            return ['ok' => false, 'error' => 'Only shipped packages can be marked delivered.'];
        }
        $vendor = VendorService::find((int) $vo['vendor_id']);
        $days = (int) ($vendor['default_return_window_days'] ?? StoreSettings::int('store_default_return_window_days', 7));
        $now = time();
        Database::connection()->prepare("UPDATE store_vendor_orders SET status = 'delivered', delivered_at = :t, settle_after = :sa WHERE id = :id")
            ->execute(['t' => date('Y-m-d H:i:s', $now), 'sa' => date('Y-m-d H:i:s', $now + $days * 86400), 'id' => $vendorOrderId]);
        $s = ShippingService::activeShipment($vendorOrderId);
        if ($s !== null) {
            \App\Core\QueryBuilder::table('store_shipments')->where('id', '=', (int) $s['id'])->update([
                'status' => 'delivered', 'status_rank' => 100, 'delivered_at' => date('Y-m-d H:i:s', $now), 'last_raw_status' => 'MARKED DELIVERED BY ADMIN',
            ]);
        }
        TaxDocumentService::issueForPackage($vendorOrderId);
        SettlementService::recordDelivered($vendorOrderId);
        OrderService::history((int) $vo['order_id'], $vendorOrderId, 'vendor_order', $vendorOrderId, (string) $vo['status'], 'delivered', 'admin', $adminId, 'Marked delivered manually');
        OrderService::recomputeStatus((int) $vo['order_id']);
        if ($s !== null) {
            StoreNotifier::shipmentUpdate((int) $s['id'], 'delivered');
        }

        return ['ok' => true];
    }

    /** @param array<string, string> $extra */
    private static function advance(int $vendorId, int $vendorOrderId, string $from, string $to, array $extra, int $userId, string $note): array
    {
        $vendor = VendorService::find($vendorId);
        if ($vendor === null || $vendor['status'] !== 'approved') {
            return ['ok' => false, 'error' => 'Your seller account must be active to process orders.'];
        }
        $set = 'status = :to';
        $params = ['to' => $to, 'id' => $vendorOrderId, 'v' => $vendorId, 'from' => $from];
        foreach ($extra as $col => $val) {
            $set .= ", $col = :x_$col";
            $params["x_$col"] = $val;
        }
        // Guarded UPDATE: only this seller's package, only from the expected status (double-clicks are harmless).
        $st = Database::connection()->prepare(
            "UPDATE store_vendor_orders SET $set WHERE id = :id AND vendor_id = :v AND status = :from"
        );
        $st->execute($params);
        if ($st->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'This order has already moved on. Refresh the page.'];
        }
        $vo = \App\Core\QueryBuilder::table('store_vendor_orders')->where('id', '=', $vendorOrderId)->first();
        OrderService::history((int) $vo['order_id'], $vendorOrderId, 'vendor_order', $vendorOrderId, $from, $to, 'vendor_user', $userId, $note);
        OrderService::recomputeStatus((int) $vo['order_id']);

        return ['ok' => true];
    }
}
