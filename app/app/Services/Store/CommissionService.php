<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Marketplace commission. Resolution order (most specific wins):
 *   product > vendor + category > vendor > category > default
 * A category rule matches the product's primary subcategory, or its department.
 *
 * The winning rule is COPIED onto store_order_items at checkout and never
 * recomputed, so changing a rule never rewrites past orders.
 */
final class CommissionService
{
    /** GST charged on our commission (VERIFY WITH CA). */
    public const COMMISSION_GST_BP = 1800;

    /** @var list<array<string, mixed>>|null */
    private static ?array $rules = null;

    /**
     * @return array{type: string, rate_bp: int, fixed_paise: int, rule_id: ?int}
     */
    public static function resolve(int $vendorId, int $subCategoryId, int $deptId, int $productId): array
    {
        $rules = self::rules();
        $today = date('Y-m-d');
        $candidates = [];
        foreach ($rules as $r) {
            if (($r['valid_from'] !== null && $r['valid_from'] > $today) || ($r['valid_to'] !== null && $r['valid_to'] < $today)) {
                continue;
            }
            $cat = (int) ($r['category_id'] ?? 0);
            $catMatch = $cat === $subCategoryId ? 2 : ($cat === $deptId && $deptId > 0 ? 1 : 0);
            $score = match ($r['scope']) {
                'product' => (int) $r['product_id'] === $productId ? 500 : -1,
                'vendor_category' => (int) $r['vendor_id'] === $vendorId && $catMatch > 0 ? 400 + $catMatch : -1,
                'vendor' => (int) $r['vendor_id'] === $vendorId ? 300 : -1,
                'category' => $catMatch > 0 ? 200 + $catMatch : -1,
                'default' => 100,
                default => -1,
            };
            if ($score >= 0) {
                $candidates[] = [$score, (int) $r['id'], $r];
            }
        }
        if (!$candidates) {
            return ['type' => 'percent', 'rate_bp' => StoreSettings::int('store_default_commission_bp', 1000), 'fixed_paise' => 0, 'rule_id' => null];
        }
        // Highest score; newest rule breaks ties.
        usort($candidates, static fn ($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
        $r = $candidates[0][2];

        return [
            'type' => (string) $r['type'],
            'rate_bp' => (int) $r['rate_bp'],
            'fixed_paise' => (int) $r['fixed_paise'],
            'rule_id' => (int) $r['id'],
        ];
    }

    /** Commission on one line (GST-inclusive line total the customer pays). */
    public static function amount(array $rule, int $lineTotalPaise, int $qty): int
    {
        $c = $rule['type'] === 'fixed'
            ? (int) $rule['fixed_paise'] * $qty
            : (int) round($lineTotalPaise * (int) $rule['rate_bp'] / 10000);

        return max(0, min($c, $lineTotalPaise));
    }

    /** @return list<array<string, mixed>> */
    private static function rules(): array
    {
        if (self::$rules === null) {
            try {
                self::$rules = Database::connection()
                    ->query('SELECT * FROM store_commission_rules WHERE is_active = 1')
                    ->fetchAll();
            } catch (\Throwable $e) {
                error_log('[CommissionService] ' . $e->getMessage());
                self::$rules = [];
            }
        }

        return self::$rules;
    }
}
