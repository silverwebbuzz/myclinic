<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * The ONLY place cart/order totals are computed. The browser's numbers are
 * never trusted; checkout stores what the browser showed only as a diagnostic.
 *
 *  - Prices are GST-inclusive; GST is extracted per line from the seller's selling price
 *    (price − seller-funded discount), which is what the seller's tax invoice shows.
 *  - Shipping is charged per SELLER sub-order: flat fee, free above a threshold.
 *  - Coupons (optional) are spread across eligible lines in proportion to value:
 *        customer pays   = subtotal − vendor-funded − platform-funded discount
 *        seller revenue  = subtotal − vendor-funded discount   (platform coupons never cost the seller)
 *        commission      = on seller revenue
 *  - Commission (+ GST on commission) is resolved per line and snapshotted.
 */
final class PricingService
{
    /**
     * @param list<array<string, mixed>> $items CartService::items() rows; lines with a problem are excluded
     * @param array<string, mixed>|null $coupon store_coupons row, already checked with CouponService::unusableReason()
     * @return array<string, mixed>
     */
    public static function quote(array $items, ?array $coupon = null): array
    {
        $lines = array_values(array_filter($items, static fn ($it) => $it['problem'] === null));
        $excluded = count($items) - count($lines);

        // ---- Coupon allocation ----
        $couponNote = null;
        $lineDisc = array_fill(0, count($lines), 0);
        $freeShipVendors = [];
        if ($coupon !== null) {
            $eligible = [];
            $eligibleSubtotal = 0;
            foreach ($lines as $i => $it) {
                if (CouponService::lineEligible($coupon, $it)) {
                    $sub = (int) $it['price_paise'] * (int) $it['qty'];
                    $eligible[$i] = $sub;
                    $eligibleSubtotal += $sub;
                }
            }
            if (!$eligible) {
                $couponNote = 'This coupon doesn\'t apply to the items in your cart.';
            } elseif ($eligibleSubtotal < (int) $coupon['min_order_paise']) {
                $couponNote = 'Add ₹' . ProductService::rupees((int) $coupon['min_order_paise'] - $eligibleSubtotal) . ' more of eligible items to use this coupon.';
            } elseif ($coupon['type'] === 'free_shipping') {
                foreach (array_keys($eligible) as $i) {
                    $freeShipVendors[(int) $lines[$i]['vendor_id']] = true;
                }
            } else {
                $total = CouponService::itemDiscount($coupon, $eligibleSubtotal);
                $given = 0;
                $largest = null;
                foreach ($eligible as $i => $sub) {
                    $lineDisc[$i] = (int) floor($total * $sub / $eligibleSubtotal);
                    $given += $lineDisc[$i];
                    if ($largest === null || $sub > $eligible[$largest]) {
                        $largest = $i;
                    }
                }
                if ($largest !== null) {
                    $lineDisc[$largest] += $total - $given;   // rounding remainder
                }
            }
        }
        $vendorFunded = $coupon !== null && $coupon['funded_by'] === 'vendor';

        $groups = [];
        foreach ($lines as $i => $it) {
            $vid = (int) $it['vendor_id'];
            $groups[$vid] ??= [
                'vendor_id' => $vid, 'vendor_name' => $it['vendor_name'], 'vendor_slug' => $it['vendor_slug'],
                'lines' => [], 'subtotal' => 0, 'discount' => 0, 'vendor_discount' => 0, 'platform_discount' => 0,
                'mrp_total' => 0, 'tax' => 0, 'shipping' => 0, 'free_above' => null,
                'commission' => 0, 'commission_gst' => 0, 'vendor_payable' => 0, 'weight_g' => 0,
            ];
            $qty = (int) $it['qty'];
            $unit = (int) $it['price_paise'];
            $subtotal = $unit * $qty;
            $disc = $lineDisc[$i];
            $vDisc = $vendorFunded ? $disc : 0;
            $pDisc = $vendorFunded ? 0 : $disc;
            $paid = $subtotal - $disc;                    // what the customer pays for this line
            $sellerRevenue = $subtotal - $vDisc;          // what the seller is selling it for
            $gstBp = (int) $it['gst_bp'];
            // GST is on the seller's selling price: a platform-funded coupon doesn't lower the
            // seller's invoice (we pay the seller the difference). VERIFY WITH CA.
            $tax = (int) round($sellerRevenue * $gstBp / (10000 + $gstBp));
            $rule = CommissionService::resolve($vid, (int) $it['category_id'], (int) ($it['dept_id'] ?? 0), (int) $it['product_id']);
            $commission = CommissionService::amount($rule, $sellerRevenue, $qty);
            $commissionGst = (int) round($commission * CommissionService::COMMISSION_GST_BP / 10000);
            $payable = max(0, $sellerRevenue - $commission - $commissionGst);

            $groups[$vid]['lines'][] = $it + [
                'unit_price_paise' => $unit,
                'line_subtotal_paise' => $subtotal,
                'vendor_discount_paise' => $vDisc,
                'platform_discount_paise' => $pDisc,
                'line_total_paise' => $paid,
                'tax_included_paise' => $tax,
                'commission_type' => $rule['type'],
                'commission_rate_bp' => $rule['rate_bp'],
                'commission_rule_id' => $rule['rule_id'],
                'commission_paise' => $commission,
                'commission_gst_paise' => $commissionGst,
                'vendor_payable_paise' => $payable,
            ];
            $groups[$vid]['subtotal'] += $subtotal;
            $groups[$vid]['discount'] += $disc;
            $groups[$vid]['vendor_discount'] += $vDisc;
            $groups[$vid]['platform_discount'] += $pDisc;
            $groups[$vid]['mrp_total'] += (int) $it['mrp_paise'] * $qty;
            $groups[$vid]['tax'] += $tax;
            $groups[$vid]['commission'] += $commission;
            $groups[$vid]['commission_gst'] += $commissionGst;
            $groups[$vid]['vendor_payable'] += $payable;
            $groups[$vid]['weight_g'] += (int) $it['weight_g'] * $qty;
        }

        $subtotal = $shipping = $tax = $mrp = $discount = 0;
        $shippingWaived = 0;
        foreach ($groups as $vid => &$g) {
            [$flat, $freeAbove] = self::shippingRule($vid);
            $g['free_above'] = $freeAbove;
            // Free-shipping threshold is on what the customer pays this seller.
            $fee = ($freeAbove !== null && ($g['subtotal'] - $g['discount']) >= $freeAbove) ? 0 : $flat;
            if ($fee > 0 && isset($freeShipVendors[$vid])) {
                $shippingWaived += $fee;
                $fee = 0;
            }
            $g['shipping'] = $fee;
            $g['total'] = $g['subtotal'] - $g['discount'] + $g['shipping'];
            $subtotal += $g['subtotal'];
            $discount += $g['discount'];
            $shipping += $g['shipping'];
            $tax += $g['tax'];
            $mrp += $g['mrp_total'];
        }
        unset($g);

        return [
            'groups' => array_values($groups),
            'subtotal' => $subtotal,
            'mrp_total' => $mrp,
            'savings' => max(0, $mrp - $subtotal + $discount + $shippingWaived),
            'discount' => $discount,
            'shipping' => $shipping,
            'shipping_waived' => $shippingWaived,
            'tax_included' => $tax,
            'grand_total' => $subtotal - $discount + $shipping,
            'item_count' => array_sum(array_map(static fn ($g) => array_sum(array_column($g['lines'], 'qty')), $groups)),
            'excluded_lines' => $excluded,
            'coupon' => $coupon !== null ? ['id' => (int) $coupon['id'], 'code' => $coupon['code'], 'label' => CouponService::describe($coupon),
                'applied' => $couponNote === null && ($discount > 0 || $shippingWaived > 0), 'note' => $couponNote] : null,
        ];
    }

    /**
     * Share of a line amount for $q more units when $done units were already taken out.
     * Cumulative floor, so taking every unit out sums to exactly $lineAmount (no lost paise).
     */
    public static function unitShare(int $lineAmount, int $qty, int $done, int $q): int
    {
        if ($qty <= 0 || $q <= 0) {
            return 0;
        }

        return intdiv($lineAmount * min($qty, $done + $q), $qty) - intdiv($lineAmount * min($qty, $done), $qty);
    }

    /** Seller revenue on a stored order line (price minus seller-funded discount). */
    public static function sellerRevenue(array $orderItem): int
    {
        return (int) $orderItem['line_subtotal_paise'] - (int) ($orderItem['vendor_discount_paise'] ?? 0);
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

    /**
     * The customer's cart coupon (store_carts.coupon_code), if still usable.
     *
     * @return array{coupon: ?array, error: ?string}
     */
    public static function cartCoupon(array $cart, ?int $identityId): array
    {
        $code = (string) ($cart['coupon_code'] ?? '');
        if ($code === '') {
            return ['coupon' => null, 'error' => null];
        }
        $c = CouponService::findByCode($code);
        if ($c === null) {
            return ['coupon' => null, 'error' => 'Coupon ' . $code . ' no longer exists.'];
        }
        $why = CouponService::unusableReason($c, $identityId);

        return $why === null ? ['coupon' => $c, 'error' => null] : ['coupon' => null, 'error' => $why];
    }
}
