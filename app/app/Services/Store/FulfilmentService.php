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
