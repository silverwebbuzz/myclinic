<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Verified-buyer reviews: one per order line, only after that package was delivered.
 * New reviews wait for admin moderation (health products attract claims we must
 * not publish, e.g. "cured my diabetes"). Ratings on products and sellers are
 * recomputed from PUBLISHED reviews only.
 */
final class ReviewService
{
    /** Order item ids this customer can still review in this order. @return array<int, true> */
    public static function reviewable(int $identityId, int $orderId): array
    {
        try {
            $st = Database::connection()->prepare(
                "SELECT oi.id FROM store_order_items oi
                   JOIN store_vendor_orders vo ON vo.id = oi.vendor_order_id AND vo.status IN ('delivered','completed')
                   JOIN store_orders o ON o.id = oi.order_id AND o.identity_id = :i
                  WHERE oi.order_id = :o AND oi.qty > oi.qty_cancelled
                    AND NOT EXISTS (SELECT 1 FROM store_reviews r WHERE r.order_item_id = oi.id)"
            );
            $st->execute(['i' => $identityId, 'o' => $orderId]);
            $out = [];
            foreach ($st->fetchAll() as $r) {
                $out[(int) $r['id']] = true;
            }

            return $out;
        } catch (\PDOException) {
            return [];
        }
    }

    /** @return array{ok: bool, error?: string} */
    public static function create(int $identityId, int $orderItemId, int $rating, string $title, string $body): array
    {
        if ($rating < 1 || $rating > 5) {
            return ['ok' => false, 'error' => 'Choose 1 to 5 stars.'];
        }
        $st = Database::connection()->prepare(
            "SELECT oi.product_id, oi.vendor_id FROM store_order_items oi
               JOIN store_vendor_orders vo ON vo.id = oi.vendor_order_id AND vo.status IN ('delivered','completed')
               JOIN store_orders o ON o.id = oi.order_id AND o.identity_id = :i
              WHERE oi.id = :id AND oi.qty > oi.qty_cancelled"
        );
        $st->execute(['i' => $identityId, 'id' => $orderItemId]);
        $it = $st->fetch();
        if (!$it) {
            return ['ok' => false, 'error' => 'You can review items once they are delivered.'];
        }
        if (QueryBuilder::table('store_reviews')->where('order_item_id', '=', $orderItemId)->first() !== null) {
            return ['ok' => false, 'error' => 'You have already reviewed this item.'];
        }
        $auto = StoreSettings::get('store_reviews_auto_publish', '0') === '1';
        $reviewId = QueryBuilder::table('store_reviews')->insert([
            'product_id' => (int) $it['product_id'], 'vendor_id' => (int) $it['vendor_id'], 'identity_id' => $identityId,
            'order_item_id' => $orderItemId, 'rating' => $rating,
            'title' => mb_substr(trim($title), 0, 190) ?: null,
            'body' => mb_substr(trim($body), 0, 3000) ?: null,
            'status' => $auto ? 'published' : 'pending',
        ]);
        if ($auto) {
            self::recompute((int) $it['product_id'], (int) $it['vendor_id']);
        }
        StoreNotifier::reviewCreated((int) $reviewId);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function moderate(int $reviewId, string $decision): array
    {
        if (!in_array($decision, ['published', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Unknown decision.'];
        }
        $r = QueryBuilder::table('store_reviews')->where('id', '=', $reviewId)->first();
        if ($r === null) {
            return ['ok' => false, 'error' => 'Review not found.'];
        }
        QueryBuilder::table('store_reviews')->where('id', '=', $reviewId)->update(['status' => $decision]);
        self::recompute((int) $r['product_id'], (int) $r['vendor_id']);
        StoreAudit::log('review.' . $decision, 'review', $reviewId);
        if ($decision === 'published' && $r['status'] !== 'published') {
            StoreNotifier::reviewPublished($reviewId);
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function reply(int $vendorId, int $reviewId, string $reply): array
    {
        $before = QueryBuilder::table('store_reviews')->where('id', '=', $reviewId)->where('vendor_id', '=', $vendorId)->first();
        $new = mb_substr(trim($reply), 0, 1000);
        $n = QueryBuilder::table('store_reviews')->where('id', '=', $reviewId)->where('vendor_id', '=', $vendorId)->update([
            'vendor_reply' => $new ?: null,
            'replied_at' => $new !== '' ? date('Y-m-d H:i:s') : null,
        ]);
        // Tell the customer only about a new or changed reply (not when it's cleared).
        if ($n > 0 && $new !== '' && $new !== trim((string) ($before['vendor_reply'] ?? ''))) {
            StoreNotifier::reviewReplied($reviewId);
        }

        return $n > 0 ? ['ok' => true] : ['ok' => false, 'error' => 'Review not found.'];
    }

    public static function recompute(int $productId, int $vendorId): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            "UPDATE store_products p SET
                rating_count = (SELECT COUNT(*) FROM store_reviews r WHERE r.product_id = :p1 AND r.status = 'published'),
                rating_avg = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM store_reviews r WHERE r.product_id = :p2 AND r.status = 'published'), 0)
              WHERE p.id = :p3"
        )->execute(['p1' => $productId, 'p2' => $productId, 'p3' => $productId]);
        $pdo->prepare(
            "UPDATE store_vendors v SET
                rating_count = (SELECT COUNT(*) FROM store_reviews r WHERE r.vendor_id = :v1 AND r.status = 'published'),
                rating_avg = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM store_reviews r WHERE r.vendor_id = :v2 AND r.status = 'published'), 0)
              WHERE v.id = :v3"
        )->execute(['v1' => $vendorId, 'v2' => $vendorId, 'v3' => $vendorId]);
    }

    /** @return list<array<string, mixed>> */
    public static function list(?int $vendorId, string $status, int $limit = 200): array
    {
        $where = [];
        $params = [];
        if ($vendorId !== null) {
            $where[] = 'r.vendor_id = :v';
            $params['v'] = $vendorId;
        }
        if ($status !== '') {
            $where[] = 'r.status = :s';
            $params['s'] = $status;
        }
        $st = Database::connection()->prepare(
            'SELECT r.*, p.name AS product_name, p.slug AS product_slug, v.display_name AS vendor_name
               FROM store_reviews r JOIN store_products p ON p.id = r.product_id JOIN store_vendors v ON v.id = r.vendor_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY r.id DESC LIMIT ' . max(1, min(500, $limit))
        );
        $st->execute($params);

        return $st->fetchAll();
    }
}
