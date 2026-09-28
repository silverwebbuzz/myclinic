<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Item-level cancellation + partial refund on a PAID order.
 *
 * One call = one Razorpay refund against the order's single captured payment:
 *   amount = cancelled units × unit price
 *          + that seller's shipping fee, when ALL of their items end up cancelled.
 *
 * The Razorpay refund call happens INSIDE the database transaction: if Razorpay
 * refuses, nothing is cancelled and the caller can retry. So an item is never
 * marked cancelled without its money going back (and vice versa, except the
 * rare "refund OK but DB commit failed" case, which is logged loudly).
 */
final class StoreRefundService
{
    public const CANCELLABLE = [
        'customer' => ['new', 'accepted'],
        'vendor_user' => ['new', 'accepted', 'packed'],
        'admin' => ['new', 'accepted', 'packed', 'ready_to_ship'],
        'system' => ['new'],
    ];

    /** Package states from which admin can refund an undelivered package (RTO / lost / damaged). */
    public const UNDELIVERED_FROM = ['ready_to_ship', 'shipped', 'rto'];

    /**
     * @param array<int, int> $itemQty order_item_id => units to cancel
     * @param string $actorType customer | vendor_user | admin | system
     * @param int|null $scopeVendorId when set (seller actions), items must belong to this seller
     * @param array{undelivered?: string, refund_shipping?: bool, compensate_seller?: bool} $opts
     *        undelivered = rto | lost | damaged: admin refunds a package that left but never reached the customer
     * @return array{ok: bool, error?: string, refunded?: int, refund_no?: string}
     */
    public static function cancelItems(int $orderId, array $itemQty, string $reason, string $actorType, ?int $actorId,
        bool $restock, ?int $scopeVendorId = null, array $opts = []): array
    {
        $itemQty = array_filter(array_map('intval', $itemQty), static fn ($q) => $q > 0);
        if (!$itemQty) {
            return ['ok' => false, 'error' => 'Choose at least one item to cancel.'];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'error' => 'Please give a reason.'];
        }
        $undelivered = in_array($opts['undelivered'] ?? '', ['rto', 'lost', 'damaged'], true) && $actorType === 'admin'
            ? (string) $opts['undelivered'] : null;
        $allowed = $undelivered !== null ? self::UNDELIVERED_FROM : (self::CANCELLABLE[$actorType] ?? []);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM store_orders WHERE id = :id FOR UPDATE');
            $st->execute(['id' => $orderId]);
            $order = $st->fetch();
            if (!$order || !in_array($order['payment_status'], ['paid', 'partially_refunded'], true)) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Only paid orders can be cancelled with a refund.'];
            }
            $st = $pdo->prepare("SELECT * FROM store_payments WHERE order_id = :o AND status IN ('captured','partially_refunded') ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $st->execute(['o' => $orderId]);
            $pay = $st->fetch();
            if (!$pay || empty($pay['rzp_payment_id'])) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'No captured payment found for this order.'];
            }

            $st = $pdo->prepare(
                'SELECT oi.*, vo.status AS vo_status, vo.id AS vo_id
                   FROM store_order_items oi JOIN store_vendor_orders vo ON vo.id = oi.vendor_order_id
                  WHERE oi.order_id = :o FOR UPDATE'
            );
            $st->execute(['o' => $orderId]);
            $all = [];
            foreach ($st->fetchAll() as $row) {
                $all[(int) $row['id']] = $row;
            }

            $amount = 0;
            $lines = [];
            $touchedVo = [];
            foreach ($itemQty as $itemId => $q) {
                $it = $all[(int) $itemId] ?? null;
                if ($it === null || ($scopeVendorId !== null && (int) $it['vendor_id'] !== $scopeVendorId)) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => 'Item not found in this order.'];
                }
                if (!in_array($it['vo_status'], $allowed, true)) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => 'This package can no longer be cancelled (' . str_replace('_', ' ', (string) $it['vo_status']) . ').'];
                }
                $open = (int) $it['qty'] - (int) $it['qty_cancelled'] - (int) $it['qty_returned'];
                if ($q > $open) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => $it['name'] . ': only ' . $open . ' unit(s) can be cancelled.'];
                }
                // What the customer actually paid for these units (after any coupon).
                $lineAmt = PricingService::unitShare((int) $it['line_total_paise'], (int) $it['qty'], (int) $it['qty_refunded'], $q);
                $amount += $lineAmt;
                $lines[] = ['item' => $it, 'qty' => $q, 'amount' => $lineAmt];
                $touchedVo[(int) $it['vo_id']] = true;
            }

            // A booked courier would still collect the box: cancel the booking first.
            // (Not for undelivered packages: the courier already has/had it.)
            foreach ($undelivered !== null ? [] : array_keys($touchedVo) as $voId) {
                $active = ShippingService::activeShipment($voId);
                if ($active !== null && !empty($active['sr_shipment_id'])) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => 'A courier is already booked for this package. Cancel the courier booking first (admin → order → shipment).'];
                }
            }

            // Shipping comes back when a seller's whole package is cancelled.
            $shippingBack = [];
            foreach (array_keys($touchedVo) as $voId) {
                $fully = true;
                foreach ($all as $it) {
                    if ((int) $it['vo_id'] !== $voId) {
                        continue;
                    }
                    $cancelNow = (int) ($itemQty[(int) $it['id']] ?? 0);
                    if ((int) $it['qty_cancelled'] + $cancelNow < (int) $it['qty']) {
                        $fully = false;
                        break;
                    }
                }
                if ($fully) {
                    $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', $voId)->first();
                    // Undelivered: the delivery charge comes back only if admin says so (e.g. lost by courier).
                    $shippingBack[$voId] = $undelivered !== null && empty($opts['refund_shipping']) ? 0 : (int) ($vo['shipping_paise'] ?? 0);
                    $amount += $shippingBack[$voId];
                }
            }

            $captured = (int) $pay['amount_paise'];
            if ((int) $pay['refunded_paise'] + $amount > $captured) {
                $pdo->rollBack();
                error_log("[StoreRefund] refund would exceed capture on order $orderId");

                return ['ok' => false, 'error' => 'Refund total would exceed the amount paid. Contact support.'];
            }

            // ---- Razorpay refund (inside the transaction on purpose; see class doc) ----
            $refundNo = 'RF' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $res = StorePaymentService::refund((string) $pay['rzp_payment_id'], $amount, [
                'purpose' => 'store', 'refund_no' => $refundNo, 'order_no' => (string) $order['order_no'],
            ]);
            if (empty($res['id'])) {
                $pdo->rollBack();
                error_log('[StoreRefund] Razorpay refused refund for ' . $order['order_no'] . ': ' . json_encode($res['error'] ?? $res));

                return ['ok' => false, 'error' => 'The refund could not be processed right now, so nothing was cancelled. Please try again.'];
            }

            // ---- Record everything ----
            $refundId = QueryBuilder::table('store_refunds')->insert([
                'refund_no' => $refundNo,
                'order_id' => $orderId,
                'vendor_order_id' => count($touchedVo) === 1 ? (int) array_key_first($touchedVo) : null,
                'payment_id' => (int) $pay['id'],
                'rzp_refund_id' => (string) $res['id'],
                'amount_paise' => $amount,
                'reason' => $undelivered ?? self::reasonCode($actorType),
                'note' => mb_substr($reason, 0, 500),
                'status' => ($res['status'] ?? '') === 'processed' ? 'processed' : 'pending',
                'initiated_by_type' => in_array($actorType, ['system', 'customer', 'vendor_user', 'admin'], true) ? $actorType : 'system',
                'initiated_by_id' => $actorId,
                'processed_at' => ($res['status'] ?? '') === 'processed' ? date('Y-m-d H:i:s') : null,
            ]);

            $updItem = $pdo->prepare(
                'UPDATE store_order_items
                    SET qty_cancelled = qty_cancelled + :q1, qty_refunded = qty_refunded + :q2,
                        status = IF(qty_cancelled >= qty, \'cancelled\', \'partially_cancelled\')
                  WHERE id = :id'
            );
            $updVo = $pdo->prepare(
                'UPDATE store_vendor_orders
                    SET vendor_payable_paise = vendor_payable_paise - :vp,
                        commission_paise = IF(commission_paise > :c1, commission_paise - :c2, 0)
                  WHERE id = :id'
            );
            $restockQ = $pdo->prepare('UPDATE store_product_variants SET stock_qty = stock_qty + :q WHERE id = :v');
            $move = $pdo->prepare(
                "INSERT INTO store_inventory_movements (variant_id, delta, reason, ref_type, ref_id, actor_type)
                 VALUES (:v, :d, 'restock_return', 'refund', :r, :a)"
            );
            $products = [];
            foreach ($lines as $l) {
                $it = $l['item'];
                $q = $l['qty'];
                $pdo->prepare(
                    'INSERT INTO store_refund_items (refund_id, order_item_id, qty, amount_paise, shipping_paise) VALUES (:r, :i, :q, :a, 0)'
                )->execute(['r' => $refundId, 'i' => (int) $it['id'], 'q' => $q, 'a' => $l['amount']]);
                $updItem->execute(['q1' => $q, 'q2' => $q, 'id' => (int) $it['id']]);
                // Seller payable + commission shrink pro-rata for the cancelled units.
                $vp = (int) round((int) $it['vendor_payable_paise'] * $q / max(1, (int) $it['qty']));
                $cm = (int) round((int) $it['commission_paise'] * $q / max(1, (int) $it['qty']));
                $updVo->execute(['vp' => $vp, 'c1' => $cm, 'c2' => $cm, 'id' => (int) $it['vo_id']]);
                // Lost/damaged by the courier is not the seller's fault: pay them what they would have earned.
                if ($undelivered !== null && $undelivered !== 'rto' && !empty($opts['compensate_seller']) && $vp > 0) {
                    $pdo->prepare(
                        "INSERT IGNORE INTO store_vendor_ledger
                            (vendor_id, vendor_order_id, order_item_id, entry_type, amount_paise, status, available_at, dedupe_key, memo, created_by_type, created_by_id)
                         VALUES (:v, :vo, :oi, 'adjustment', :a, 'available', NOW(), :k, :m, 'admin', :u)"
                    )->execute([
                        'v' => (int) $it['vendor_id'], 'vo' => (int) $it['vo_id'], 'oi' => (int) $it['id'], 'a' => $vp,
                        'k' => 'lost:' . $refundId . ':item:' . (int) $it['id'],
                        'm' => 'Compensation: ' . $q . ' × ' . $it['sku'] . ' ' . $undelivered . ' in transit', 'u' => $actorId,
                    ]);
                }
                if ($restock) {
                    $restockQ->execute(['q' => $q, 'v' => (int) $it['variant_id']]);
                    $move->execute(['v' => (int) $it['variant_id'], 'd' => $q, 'r' => $refundId,
                        'a' => in_array($actorType, ['vendor_user', 'admin'], true) ? $actorType : 'system']);
                    $products[(int) $it['product_id']] = true;
                }
            }

            $voStatus = $undelivered !== null ? ($undelivered === 'rto' ? 'rto' : 'lost_in_transit') : match ($actorType) {
                'customer' => 'cancelled_by_customer',
                'vendor_user' => 'cancelled_by_vendor',
                'admin' => 'cancelled_by_admin',
                default => 'auto_cancelled',
            };
            foreach ($shippingBack as $voId => $ship) {
                $pdo->prepare('UPDATE store_vendor_orders SET status = :s, cancel_reason = :r, cancelled_by_type = :t, cancelled_by_id = :i WHERE id = :id')
                    ->execute(['s' => $voStatus, 'r' => mb_substr($reason, 0, 500),
                        't' => $actorType === 'vendor_user' ? 'vendor_user' : (in_array($actorType, ['customer', 'admin'], true) ? $actorType : 'system'),
                        'i' => $actorId, 'id' => $voId]);
                if ($ship > 0) {
                    // Record it on a line of THIS package (credit notes are per package).
                    $pdo->prepare('UPDATE store_refund_items SET shipping_paise = :s WHERE refund_id = :r
                                     AND order_item_id IN (SELECT id FROM store_order_items WHERE vendor_order_id = :vo)
                                   ORDER BY order_item_id LIMIT 1')
                        ->execute(['s' => $ship, 'r' => $refundId, 'vo' => $voId]);
                }
                OrderService::history($orderId, $voId, 'vendor_order', $voId, null, $voStatus, $actorType, $actorId, $reason);
            }
            // Units that were already invoiced get a credit note (same transaction as the refund).
            TaxDocumentService::creditForRefund((int) $refundId, $undelivered ?? 'cancel');

            $newRefunded = (int) $pay['refunded_paise'] + $amount;
            $fullyRefunded = $newRefunded >= $captured;
            $pdo->prepare('UPDATE store_payments SET refunded_paise = :r, status = :s WHERE id = :id')->execute([
                'r' => $newRefunded, 's' => $fullyRefunded ? 'refunded' : 'partially_refunded', 'id' => (int) $pay['id'],
            ]);
            $pdo->prepare('UPDATE store_orders SET payment_status = :s WHERE id = :id')->execute([
                's' => $fullyRefunded ? 'refunded' : 'partially_refunded', 'id' => $orderId,
            ]);
            OrderService::history($orderId, null, 'payment', (int) $pay['id'], null, $fullyRefunded ? 'refunded' : 'partially_refunded',
                $actorType, $actorId, "Refund $refundNo of Rs. " . ProductService::rupees($amount) . ': ' . $reason);
            OrderService::recomputeStatus($orderId);

            foreach (array_keys($products) as $pid) {
                ProductService::refreshDenormalized($pid);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // If this fires AFTER Razorpay accepted the refund, money went back without our record — alert loudly.
            error_log('[StoreRefund::cancelItems] CRITICAL ' . $e->getMessage() . (isset($res['id']) ? ' (Razorpay refund ' . $res['id'] . ' WAS issued)' : ''));

            return ['ok' => false, 'error' => 'Something went wrong while cancelling. Please contact support.'];
        }

        StoreNotifier::itemsCancelled($orderId, $refundNo, $amount, $actorType, $reason);

        return ['ok' => true, 'refunded' => $amount, 'refund_no' => $refundNo];
    }

    /**
     * Admin: refund a package that was dispatched but never reached the customer.
     *   rto     = came back to the seller (refused / unreachable / bad address) → usually keep the delivery charge
     *   lost    = lost by the courier → refund everything, optionally pay the seller (we claim from Shiprocket)
     *   damaged = destroyed in transit → same as lost
     */
    public static function refundUndelivered(int $vendorOrderId, string $cause, bool $refundShipping, bool $restock,
        bool $compensateSeller, string $note, int $adminId): array
    {
        $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', $vendorOrderId)->first();
        if ($vo === null) {
            return ['ok' => false, 'error' => 'Package not found.'];
        }
        $st = Database::connection()->prepare('SELECT id, qty, qty_cancelled, qty_returned FROM store_order_items WHERE vendor_order_id = :v');
        $st->execute(['v' => $vendorOrderId]);
        $qty = [];
        foreach ($st->fetchAll() as $it) {
            $open = (int) $it['qty'] - (int) $it['qty_cancelled'] - (int) $it['qty_returned'];
            if ($open > 0) {
                $qty[(int) $it['id']] = $open;
            }
        }
        $label = ['rto' => 'Returned to seller', 'lost' => 'Lost in transit', 'damaged' => 'Damaged in transit'][$cause] ?? 'Undelivered';

        return self::cancelItems((int) $vo['order_id'], $qty, $label . (trim($note) !== '' ? ': ' . trim($note) : ''), 'admin', $adminId,
            $restock, (int) $vo['vendor_id'], ['undelivered' => $cause, 'refund_shipping' => $refundShipping, 'compensate_seller' => $compensateSeller]);
    }

    /** Cancel everything still open in one seller's package. */
    public static function cancelVendorOrder(int $vendorOrderId, string $reason, string $actorType, ?int $actorId, bool $restock): array
    {
        $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', $vendorOrderId)->first();
        if ($vo === null) {
            return ['ok' => false, 'error' => 'Package not found.'];
        }
        $st = Database::connection()->prepare('SELECT id, qty, qty_cancelled FROM store_order_items WHERE vendor_order_id = :v');
        $st->execute(['v' => $vendorOrderId]);
        $qty = [];
        foreach ($st->fetchAll() as $it) {
            $open = (int) $it['qty'] - (int) $it['qty_cancelled'];
            if ($open > 0) {
                $qty[(int) $it['id']] = $open;
            }
        }

        return self::cancelItems((int) $vo['order_id'], $qty, $reason, $actorType, $actorId, $restock, (int) $vo['vendor_id']);
    }

    private static function reasonCode(string $actorType): string
    {
        return match ($actorType) {
            'customer' => 'customer_cancel',
            'vendor_user' => 'vendor_cancel',
            'admin' => 'admin',
            default => 'vendor_sla',
        };
    }
}
