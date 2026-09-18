<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Plan discount codes — managed at /admin/discounts, applied on the signup
 * checkout (/register/checkout) before payment.
 *
 * The discount comes off the plan price BEFORE GST (GST is charged on what
 * the clinic actually pays). A code counts as used only when the payment is
 * captured: redeem() is keyed by the gateway order id, so the webhook and the
 * return-URL verify can both call it safely.
 */
final class DiscountService
{
    /** Create the tables on first use (same DDL as the 2026_09_18 patch). */
    public static function ensureSchema(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }

        $sql = (string) @file_get_contents(dirname(__DIR__, 2) . '/database/patches/2026_09_18_plan_discount_codes.sql');
        try {
            $pdo = Database::connection();
            foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $stmt) {
                $stmt = trim(preg_replace('/^\s*--.*$/m', '', $stmt) ?? '');
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
            }

            return $ok = true;
        } catch (\Throwable $e) {
            error_log('[DiscountService] schema unavailable: ' . $e->getMessage());

            return $ok = false;
        }
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $code) ?? '');
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        if (!self::ensureSchema()) {
            return [];
        }

        return Database::connection()
            ->query('SELECT * FROM plan_discount_codes ORDER BY created_at DESC, id DESC')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        if (!self::ensureSchema()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM plan_discount_codes WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Is this code usable by this clinic right now?
     *
     * @return array{ok: bool, error?: string, code?: array<string, mixed>}
     */
    public static function validate(string $rawCode, int $clinicId): array
    {
        $code = self::normalize($rawCode);
        if ($code === '') {
            return ['ok' => false, 'error' => 'Please enter a discount code.'];
        }
        if (!self::ensureSchema()) {
            return ['ok' => false, 'error' => 'Discount codes are not available right now.'];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM plan_discount_codes WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $invalid = ['ok' => false, 'error' => 'This discount code is not valid.'];
        if (!$row || empty($row['is_active']) || (float) $row['discount_value'] <= 0) {
            return $invalid;
        }
        if (!empty($row['valid_from']) && $today < (string) $row['valid_from']) {
            return ['ok' => false, 'error' => 'This discount code is not active yet.'];
        }
        if (!empty($row['valid_until']) && $today > (string) $row['valid_until']) {
            return ['ok' => false, 'error' => 'This discount code has expired.'];
        }
        if ($row['max_uses'] !== null && (int) $row['used_count'] >= (int) $row['max_uses']) {
            return ['ok' => false, 'error' => 'This discount code has reached its usage limit.'];
        }

        $used = $pdo->prepare('SELECT COUNT(*) FROM plan_discount_redemptions WHERE discount_code_id = ? AND clinic_id = ?');
        $used->execute([(int) $row['id'], $clinicId]);
        if ((int) $used->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'You have already used this discount code.'];
        }

        return ['ok' => true, 'code' => $row];
    }

    /** Amount off a base price (never more than the price itself). */
    public static function amountOff(array $code, float $base): float
    {
        $value = (float) $code['discount_value'];
        $off = ($code['discount_type'] ?? 'percent') === 'flat'
            ? $value
            : $base * min(100.0, $value) / 100;

        return round(min($base, max(0.0, $off)), 2);
    }

    /** Human label, e.g. "20% off" / "₹200 off". */
    public static function label(array $code): string
    {
        $value = (float) $code['discount_value'];
        $num = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return ($code['discount_type'] ?? 'percent') === 'flat' ? '₹' . $num . ' off' : $num . '% off';
    }

    /**
     * Record a redemption once the order is paid. Idempotent per order id;
     * bumps used_count only when a new row was written.
     */
    public static function redeem(int $codeId, int $clinicId, string $orderId, float $base, float $discount): void
    {
        if ($codeId <= 0 || $orderId === '' || !self::ensureSchema()) {
            return;
        }

        try {
            $pdo = Database::connection();
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO plan_discount_redemptions
                    (discount_code_id, clinic_id, gateway_order_id, base_amount, discount_amount)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$codeId, $clinicId, $orderId, $base, $discount]);
            if ($ins->rowCount() > 0) {
                $pdo->prepare('UPDATE plan_discount_codes SET used_count = used_count + 1 WHERE id = ?')->execute([$codeId]);
            }
        } catch (\Throwable $e) {
            error_log('[DiscountService] redeem failed: ' . $e->getMessage());
        }
    }

    public static function redemptionCount(int $codeId): int
    {
        if (!self::ensureSchema()) {
            return 0;
        }
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM plan_discount_redemptions WHERE discount_code_id = ?');
        $stmt->execute([$codeId]);

        return (int) $stmt->fetchColumn();
    }
}
