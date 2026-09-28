<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Store orders: reads for customer/admin, status history, and releasing the
 * stock held by an unpaid order (expiry / cancel / replaced by a new checkout).
 */
final class OrderService
{
    /** Release every expired unpaid order (called lazily; a cron can call it too). */
    public static function expireStale(int $limit = 50): int
    {
        try {
            $st = Database::connection()->prepare(
                "SELECT id FROM store_orders WHERE payment_status = 'pending' AND status = 'pending_payment'
                   AND expires_at IS NOT NULL AND expires_at < NOW() ORDER BY expires_at LIMIT " . max(1, min(500, $limit))
            );
            $st->execute();
            $n = 0;
            foreach ($st->fetchAll() as $r) {
                $n += self::release((int) $r['id'], 'expired', 'Payment window expired') ? 1 : 0;
            }

            return $n;
        } catch (\Throwable $e) {
            error_log('[OrderService::expireStale] ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Cancel/expire an UNPAID order and give its reserved stock back.
     * Idempotent: only the call that flips pending_payment → $toStatus does work.
     */
    public static function release(int $orderId, string $toStatus, string $note, string $actorType = 'system', ?int $actorId = null): bool
    {
        if (!in_array($toStatus, ['expired', 'cancelled', 'payment_failed'], true)) {
            return false;
        }
        $pdo = Database::connection();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            $upd = $pdo->prepare(
                "UPDATE store_orders SET status = :s, cancelled_at = NOW()
                  WHERE id = :id AND status = 'pending_payment' AND payment_status = 'pending'"
            );
            $upd->execute(['s' => $toStatus, 'id' => $orderId]);
            if ($upd->rowCount() !== 1) {
                if ($ownTx) {
                    $pdo->rollBack();
                }

                return false;
            }
            $st = $pdo->prepare('SELECT variant_id, qty FROM store_order_items WHERE order_id = :o ORDER BY variant_id');
            $st->execute(['o' => $orderId]);
            // IF() rather than GREATEST(x - q, 0): the subtraction would underflow an UNSIGNED column.
            $rel = $pdo->prepare('UPDATE store_product_variants SET reserved_qty = IF(reserved_qty > :q, reserved_qty - :q2, 0) WHERE id = :v');
            $move = $pdo->prepare(
                "INSERT INTO store_inventory_movements (variant_id, delta, reason, ref_type, ref_id, actor_type)
                 VALUES (:v, :d, 'release', 'order', :o, 'system')"
            );
            $products = [];
            foreach ($st->fetchAll() as $it) {
                $rel->execute(['q' => (int) $it['qty'], 'q2' => (int) $it['qty'], 'v' => (int) $it['variant_id']]);
                $move->execute(['v' => (int) $it['variant_id'], 'd' => (int) $it['qty'], 'o' => $orderId]);
                $products[] = (int) $it['variant_id'];
            }
            $pdo->prepare(
                "UPDATE store_vendor_orders SET status = :s, cancel_reason = :r, cancelled_by_type = :t
                  WHERE order_id = :o AND status = 'pending_payment'"
            )->execute([
                's' => $toStatus === 'expired' ? 'auto_cancelled' : 'cancelled_by_customer',
                'r' => mb_substr($note, 0, 500),
                't' => $actorType === 'customer' ? 'customer' : ($actorType === 'admin' ? 'admin' : 'system'),
                'o' => $orderId,
            ]);
            self::history($orderId, null, 'order', $orderId, 'pending_payment', $toStatus, $actorType, $actorId, $note);
            // Keep listing "in stock" flags right.
            if ($products) {
                $in = implode(',', array_map('intval', array_unique($products)));
                foreach ($pdo->query("SELECT DISTINCT product_id FROM store_product_variants WHERE id IN ($in)")->fetchAll() as $r) {
                    ProductService::refreshDenormalized((int) $r['product_id']);
                }
            }
            if ($ownTx) {
                $pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[OrderService::release] ' . $e->getMessage());

            return false;
        }
    }

    public static function history(int $orderId, ?int $vendorOrderId, string $entity, ?int $entityId, ?string $from, string $to,
        string $actorType, ?int $actorId, string $note = ''): void
    {
        $actorType = in_array($actorType, ['system', 'customer', 'vendor_user', 'admin', 'webhook'], true) ? $actorType : 'system';
        QueryBuilder::table('store_order_status_history')->insert([
            'order_id' => $orderId,
            'vendor_order_id' => $vendorOrderId,
            'entity' => $entity,
            'entity_id' => $entityId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note !== '' ? mb_substr($note, 0, 500) : null,
        ]);
    }

    /** Customer-scoped lookup by order number. */
    public static function findForIdentity(string $orderNo, int $identityId): ?array
    {
        $o = QueryBuilder::table('store_orders')->where('order_no', '=', $orderNo)->where('identity_id', '=', $identityId)->first();

        return $o === null ? null : self::load((int) $o['id']);
    }

    /** Order + sub-orders (with items) + history. */
    public static function load(int $orderId): ?array
    {
        $o = QueryBuilder::table('store_orders')->where('id', '=', $orderId)->first();
        if ($o === null) {
            return null;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare(
            'SELECT vo.*, v.display_name AS vendor_name, v.slug AS vendor_slug
               FROM store_vendor_orders vo JOIN store_vendors v ON v.id = vo.vendor_id
              WHERE vo.order_id = :o ORDER BY vo.id'
        );
        $st->execute(['o' => $orderId]);
        $vos = $st->fetchAll();
        $st = $pdo->prepare('SELECT * FROM store_order_items WHERE order_id = :o ORDER BY id');
        $st->execute(['o' => $orderId]);
        $byVo = [];
        foreach ($st->fetchAll() as $it) {
            $byVo[(int) $it['vendor_order_id']][] = $it;
        }
        foreach ($vos as &$vo) {
            $vo['items'] = $byVo[(int) $vo['id']] ?? [];
        }
        unset($vo);
        $o['vendor_orders'] = $vos;
        $o['ship_address'] = json_decode((string) $o['ship_address_json'], true) ?: [];
        $st = $pdo->prepare('SELECT * FROM store_order_status_history WHERE order_id = :o ORDER BY id');
        $st->execute(['o' => $orderId]);
        $o['history'] = $st->fetchAll();

        return $o;
    }

    /** @return array{rows: list<array<string, mixed>>, counts: array<string, int>} */
    public static function adminList(string $status, string $q): array
    {
        self::expireStale();
        $pdo = Database::connection();
        $counts = [];
        foreach ($pdo->query('SELECT status, COUNT(*) n FROM store_orders GROUP BY status')->fetchAll() as $r) {
            $counts[(string) $r['status']] = (int) $r['n'];
        }
        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'o.status = :st';
            $params['st'] = $status;
        }
        if ($q !== '') {
            $where[] = '(o.order_no LIKE :q1 OR o.contact_phone LIKE :q2 OR o.contact_name LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
        }
        $st = $pdo->prepare(
            'SELECT o.*, (SELECT COUNT(*) FROM store_vendor_orders vo WHERE vo.order_id = o.id) AS seller_count,
                    (SELECT COALESCE(SUM(oi.qty), 0) FROM store_order_items oi WHERE oi.order_id = o.id) AS item_count
               FROM store_orders o' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
              ORDER BY o.id DESC LIMIT 200'
        );
        $st->execute($params);

        return ['rows' => $st->fetchAll(), 'counts' => $counts];
    }
}
