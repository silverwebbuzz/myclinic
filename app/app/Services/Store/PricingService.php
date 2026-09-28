<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * The ONLY place cart/order totals are computed. The browser's numbers are
 * never trusted; checkout stores what the browser showed only as a diagnostic.
 *
 *  - Prices are GST-inclusive; GST is extracted per line: price × rate / (100 + rate).
 *  - Shipping is charged per SELLER sub-order: flat fee, free above a threshold
 *    (seller-specific rule, else the platform default).
 *  - Commission (+ GST on commission) is resolved per line and snapshotted.
 *  - No coupons yet (tables exist; admin UI comes later).
 */
final class PricingService
{
    /**
     * @param list<array<string, mixed>> $items CartService::items() rows; lines with a problem are excluded
     * @return array<string, mixed>
     */
    public static function quote(array $items): array
    {
        $groups = [];
        $excluded = 0;
        foreach ($items as $it) {
            if ($it['problem'] !== null) {
                $excluded++;
                continue;
            }
            $vid = (int) $it['vendor_id'];
            $groups[$vid] ??= [
                'vendor_id' => $vid, 'vendor_name' => $it['vendor_name'], 'vendor_slug' => $it['vendor_slug'],
                'lines' => [], 'subtotal' => 0, 'mrp_total' => 0, 'tax' => 0, 'shipping' => 0, 'free_above' => null,
                'commission' => 0, 'commission_gst' => 0, 'vendor_payable' => 0, 'weight_g' => 0,
            ];
            $qty = (int) $it['qty'];
            $unit = (int) $it['price_paise'];
            $lineTotal = $unit * $qty;
            $gstBp = (int) $it['gst_bp'];
            $tax = (int) round($lineTotal * $gstBp / (10000 + $gstBp));
            $rule = CommissionService::resolve($vid, (int) $it['category_id'], (int) ($it['dept_id'] ?? 0), (int) $it['product_id']);
            $commission = CommissionService::amount($rule, $lineTotal, $qty);
            $commissionGst = (int) round($commission * CommissionService::COMMISSION_GST_BP / 10000);

            $groups[$vid]['lines'][] = $it + [
                'unit_price_paise' => $unit,
                'line_subtotal_paise' => $lineTotal,
                'line_total_paise' => $lineTotal,
                'tax_included_paise' => $tax,
                'commission_type' => $rule['type'],
                'commission_rate_bp' => $rule['rate_bp'],
                'commission_rule_id' => $rule['rule_id'],
                'commission_paise' => $commission,
                'commission_gst_paise' => $commissionGst,
                'vendor_payable_paise' => max(0, $lineTotal - $commission - $commissionGst),
            ];
            $groups[$vid]['subtotal'] += $lineTotal;
            $groups[$vid]['mrp_total'] += (int) $it['mrp_paise'] * $qty;
            $groups[$vid]['tax'] += $tax;
            $groups[$vid]['commission'] += $commission;
            $groups[$vid]['commission_gst'] += $commissionGst;
            $groups[$vid]['vendor_payable'] += max(0, $lineTotal - $commission - $commissionGst);
            $groups[$vid]['weight_g'] += (int) $it['weight_g'] * $qty;
        }

        $subtotal = $shipping = $tax = $mrp = 0;
        foreach ($groups as $vid => &$g) {
            [$flat, $freeAbove] = self::shippingRule($vid);
            $g['free_above'] = $freeAbove;
            $g['shipping'] = ($freeAbove !== null && $g['subtotal'] >= $freeAbove) ? 0 : $flat;
            $g['total'] = $g['subtotal'] + $g['shipping'];
            $subtotal += $g['subtotal'];
            $shipping += $g['shipping'];
            $tax += $g['tax'];
            $mrp += $g['mrp_total'];
        }
        unset($g);

        return [
            'groups' => array_values($groups),
            'subtotal' => $subtotal,
            'mrp_total' => $mrp,
            'savings' => max(0, $mrp - $subtotal),
            'shipping' => $shipping,
            'tax_included' => $tax,
            'discount' => 0,
            'grand_total' => $subtotal + $shipping,
            'item_count' => array_sum(array_map(static fn ($g) => array_sum(array_column($g['lines'], 'qty')), $groups)),
            'excluded_lines' => $excluded,
        ];
    }

    /** @return array{0: int, 1: ?int} [flat fee paise, free-above paise or null] */
    public static function shippingRule(int $vendorId): array
    {
        static $cache = [];
        if (isset($cache[$vendorId])) {
            return $cache[$vendorId];
        }
        $st = Database::connection()->prepare(
            'SELECT flat_fee_paise, free_above_paise FROM store_shipping_rules
              WHERE is_active = 1 AND (vendor_id = :v OR vendor_id IS NULL)
              ORDER BY vendor_id IS NULL, id DESC LIMIT 1'
        );
        $st->execute(['v' => $vendorId]);
        $r = $st->fetch();

        return $cache[$vendorId] = $r
            ? [(int) $r['flat_fee_paise'], $r['free_above_paise'] !== null ? (int) $r['free_above_paise'] : null]
            : [0, null];
    }
}
