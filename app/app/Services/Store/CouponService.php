<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Store coupons (store_coupons / store_coupon_redemptions).
 *
 *   type percent        value = basis points (1000 = 10%), optional max_discount_paise cap
 *   type fixed          value = paise off the eligible items
 *   type free_shipping  shipping on eligible sellers' packages becomes free (platform-funded)
 *
 * Eligible lines: not "no promotion" (IMS Act), and matching the coupon's seller /
 * category (department or subcategory) when set. funded_by decides who pays:
 * 'platform' → seller earnings untouched; 'vendor' → the seller's earnings drop.
 * A redemption is recorded only when the order is PAID (StorePaymentService::markPaid).
 */
final class CouponService
{
    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));

        return $code === '' ? null : QueryBuilder::table('store_coupons')->where('code', '=', $code)->first();
    }

    /**
     * Is this coupon usable right now by this customer? null = OK, else the reason.
     */
    public static function unusableReason(array $c, ?int $identityId): ?string
    {
        $now = time();
        if ((int) $c['is_active'] !== 1) {
            return 'This coupon is not active.';
        }
        if (!empty($c['starts_at']) && strtotime((string) $c['starts_at']) > $now) {
            return 'This coupon is not active yet.';
        }
        if (!empty($c['ends_at']) && strtotime((string) $c['ends_at']) < $now) {
            return 'This coupon has expired.';
        }
        if ($c['limit_total'] !== null && (int) $c['used_count'] >= (int) $c['limit_total']) {
            return 'This coupon has been fully used.';
        }
        if ($identityId !== null && $c['limit_per_customer'] !== null) {
            $st = Database::connection()->prepare('SELECT COUNT(*) FROM store_coupon_redemptions WHERE coupon_id = :c AND identity_id = :i');
            $st->execute(['c' => (int) $c['id'], 'i' => $identityId]);
            if ((int) $st->fetchColumn() >= (int) $c['limit_per_customer']) {
                return 'You have already used this coupon.';
            }
        }

        return null;
    }

    /** Does a cart line qualify? */
    public static function lineEligible(array $c, array $line): bool
    {
        if (!empty($line['no_promotion'])) {
            return false;
        }
        if (!empty($c['vendor_id']) && (int) $c['vendor_id'] !== (int) $line['vendor_id']) {
            return false;
        }
        if (!empty($c['category_id'])) {
            $cat = (int) $c['category_id'];
            if ($cat !== (int) $line['category_id'] && $cat !== (int) ($line['dept_id'] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Total item discount (whole rupees) for the eligible subtotal, or 0.
     */
    public static function itemDiscount(array $c, int $eligibleSubtotal): int
    {
        if ($eligibleSubtotal <= 0 || $eligibleSubtotal < (int) $c['min_order_paise']) {
            return 0;
        }
        $d = match ($c['type']) {
            'percent' => (int) floor($eligibleSubtotal * (int) $c['value'] / 10000),
            'fixed' => (int) $c['value'],
            default => 0,
        };
        if ($c['max_discount_paise'] !== null && (int) $c['max_discount_paise'] > 0) {
            $d = min($d, (int) $c['max_discount_paise']);
        }
        $d = min($d, $eligibleSubtotal);

        return (int) (floor($d / 100) * 100);   // whole rupees, never more than advertised
    }

    public static function describe(array $c): string
    {
        return match ($c['type']) {
            'percent' => ((int) $c['value'] / 100) . '% off' . ($c['max_discount_paise'] ? ' (up to ₹' . ProductService::rupees((int) $c['max_discount_paise']) . ')' : ''),
            'fixed' => '₹' . ProductService::rupees((int) $c['value']) . ' off',
            default => 'Free shipping',
        } . ((int) $c['min_order_paise'] > 0 ? ' on ₹' . ProductService::rupees((int) $c['min_order_paise']) . '+' : '');
    }

    /** Record a redemption once the order is paid (idempotent per order). */
    public static function redeem(int $couponId, int $orderId, int $identityId, int $discount): void
    {
        $ins = Database::connection()->prepare(
            'INSERT IGNORE INTO store_coupon_redemptions (coupon_id, order_id, identity_id, discount_paise) VALUES (:c, :o, :i, :d)'
        );
        $ins->execute(['c' => $couponId, 'o' => $orderId, 'i' => $identityId, 'd' => $discount]);
        if ($ins->rowCount() === 1) {
            Database::connection()->prepare('UPDATE store_coupons SET used_count = used_count + 1 WHERE id = :c')->execute(['c' => $couponId]);
        }
    }

    // ---- admin ------------------------------------------------------------------

    /** @return array{ok: bool, error?: string} */
    public static function save(array $in, int $adminId): array
    {
        $id = (int) ($in['id'] ?? 0);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($in['code'] ?? '')) ?? '');
        $type = (string) ($in['type'] ?? 'percent');
        if (strlen($code) < 3 || strlen($code) > 30) {
            return ['ok' => false, 'error' => 'Code must be 3–30 letters/numbers.'];
        }
        if (!in_array($type, ['percent', 'fixed', 'free_shipping'], true)) {
            return ['ok' => false, 'error' => 'Choose a type.'];
        }
        $value = 0;
        if ($type === 'percent') {
            $pct = (float) ($in['value'] ?? 0);
            if ($pct <= 0 || $pct > 90) {
                return ['ok' => false, 'error' => 'Percent must be between 0 and 90.'];
            }
            $value = (int) round($pct * 100);
        } elseif ($type === 'fixed') {
            $value = (int) (ProductService::toPaise((string) ($in['value'] ?? '')) ?? 0);
            if ($value <= 0) {
                return ['ok' => false, 'error' => 'Enter the rupee amount off.'];
            }
        }
        $dup = self::findByCode($code);
        if ($dup !== null && (int) $dup['id'] !== $id) {
            return ['ok' => false, 'error' => 'That code already exists.'];
        }
        $row = [
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'max_discount_paise' => ProductService::toPaise((string) ($in['max_discount'] ?? '')),
            'min_order_paise' => (int) (ProductService::toPaise((string) ($in['min_order'] ?? '')) ?? 0),
            'funded_by' => ($in['funded_by'] ?? 'platform') === 'vendor' ? 'vendor' : 'platform',
            'vendor_id' => (int) ($in['vendor_id'] ?? 0) ?: null,
            'category_id' => (int) ($in['category_id'] ?? 0) ?: null,
            'starts_at' => !empty($in['starts_at']) ? date('Y-m-d H:i:s', (int) strtotime((string) $in['starts_at'])) : null,
            'ends_at' => !empty($in['ends_at']) ? date('Y-m-d H:i:s', (int) strtotime((string) $in['ends_at'] . ' 23:59:59')) : null,
            'limit_total' => ($in['limit_total'] ?? '') !== '' ? max(1, (int) $in['limit_total']) : null,
            'limit_per_customer' => ($in['limit_per_customer'] ?? '') !== '' ? max(1, (int) $in['limit_per_customer']) : null,
            'is_active' => !empty($in['is_active']) ? 1 : 0,
        ];
        if ($row['funded_by'] === 'vendor' && $row['vendor_id'] === null) {
            return ['ok' => false, 'error' => 'A seller-funded coupon must be limited to that seller.'];
        }
        if ($id > 0) {
            QueryBuilder::table('store_coupons')->where('id', '=', $id)->update($row);
        } else {
            $id = QueryBuilder::table('store_coupons')->insert($row);
        }
        StoreAudit::log('coupon.save', 'coupon', $id, null, $row);

        return ['ok' => true];
    }
}
