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
 *  - Delivery is charged once per ORDER (flat fee, free above a threshold) and split
 *    across seller packages by value; sellers pay the courier minus their share (SellerFeeService).
 *  - Coupons (optional) are spread across eligible lines in proportion to value:
 *        customer pays   = subtotal − vendor-funded − platform-funded discount
 *        seller revenue  = subtotal − vendor-funded discount   (platform coupons never cost the seller)
 *        commission      = on seller revenue excluding GST (taxable value)
 *  - Commission (+ GST on commission) is resolved per line and snapshotted.
 *  - eClinicPro Points (PointsService) come off after coupons, spread across lines like a
 *    platform coupon (platform_discount_paise). Free delivery is judged on what the customer
 *    pays for items AFTER coupons and points.
 */
final class PricingService
{
    /**
     * @param list<array<string, mixed>> $items CartService::items() rows; lines with a problem are excluded
     * @param array<string, mixed>|null $coupon store_coupons row, already checked with CouponService::unusableReason()
     * @param int|null $identityId signed-in customer: their eClinicPro Points are applied (null = guest / no points)
     * @return array<string, mixed>
     */
    public static function quote(array $items, ?array $coupon = null, ?int $identityId = null): array
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

        // ---- eClinicPro Points: on what's left after the coupon, never on delivery ----
        $afterCoupon = [];
        foreach ($lines as $i => $it) {
            $afterCoupon[$i] = (int) $it['price_paise'] * (int) $it['qty'] - $lineDisc[$i];
        }
        $points = null;
        $linePts = array_fill(0, count($lines), 0);
        if ($identityId !== null && $identityId > 0 && $lines && PointsService::enabled()) {
            $points = PointsService::plan($identityId, array_sum($afterCoupon));
            if ($points['points'] > 0) {
                $linePts = self::split($points['points'] * 100, $afterCoupon) + $linePts;
            }
        }

        $groups = [];
        foreach ($lines as $i => $it) {
            $vid = (int) $it['vendor_id'];
            $groups[$vid] ??= [
                'vendor_id' => $vid, 'vendor_name' => $it['vendor_name'], 'vendor_slug' => $it['vendor_slug'],
                'lines' => [], 'subtotal' => 0, 'discount' => 0, 'points_discount' => 0, 'vendor_discount' => 0, 'platform_discount' => 0,
                'mrp_total' => 0, 'tax' => 0, 'shipping' => 0, 'free_above' => null,
                'commission' => 0, 'commission_gst' => 0, 'vendor_payable' => 0, 'weight_g' => 0,
            ];
            $qty = (int) $it['qty'];
            $unit = (int) $it['price_paise'];
            $subtotal = $unit * $qty;
            $disc = $lineDisc[$i];
            $ptsDisc = $linePts[$i];
            $vDisc = $vendorFunded ? $disc : 0;
            $pDisc = ($vendorFunded ? 0 : $disc) + $ptsDisc;   // points are always platform-funded
            $paid = $subtotal - $disc - $ptsDisc;         // what the customer pays for this line
            $sellerRevenue = $subtotal - $vDisc;          // what the seller is selling it for
            $gstBp = (int) $it['gst_bp'];
            // GST is on the seller's selling price: a platform-funded coupon doesn't lower the
            // seller's invoice (we pay the seller the difference). VERIFY WITH CA.
            $tax = (int) round($sellerRevenue * $gstBp / (10000 + $gstBp));
            $rule = CommissionService::resolve($vid, (int) $it['category_id'], (int) ($it['dept_id'] ?? 0), (int) $it['product_id']);
            // Commission is on the seller's price EXCLUDING GST (the GST belongs to the government).
            $commission = CommissionService::amount($rule, $sellerRevenue - $tax, $qty);
            $commissionGst = (int) round($commission * CommissionService::COMMISSION_GST_BP / 10000);
            $payable = max(0, $sellerRevenue - $commission - $commissionGst);

            $groups[$vid]['lines'][] = $it + [
                'unit_price_paise' => $unit,
                'line_subtotal_paise' => $subtotal,
                'vendor_discount_paise' => $vDisc,
                'platform_discount_paise' => $pDisc,
                'points_discount_paise' => $ptsDisc,
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
            $groups[$vid]['points_discount'] += $ptsDisc;
            $groups[$vid]['vendor_discount'] += $vDisc;
            $groups[$vid]['platform_discount'] += $pDisc;
            $groups[$vid]['mrp_total'] += (int) $it['mrp_paise'] * $qty;
            $groups[$vid]['tax'] += $tax;
            $groups[$vid]['commission'] += $commission;
            $groups[$vid]['commission_gst'] += $commissionGst;
            $groups[$vid]['vendor_payable'] += $payable;
            $groups[$vid]['weight_g'] += (int) $it['weight_g'] * $qty;
        }

        $subtotal = $tax = $mrp = $discount = $pointsDiscount = 0;
        foreach ($groups as $g) {
            $subtotal += $g['subtotal'];
            $discount += $g['discount'];
            $pointsDiscount += $g['points_discount'];
            $tax += $g['tax'];
            $mrp += $g['mrp_total'];
        }

        // ONE delivery fee per order, free from a threshold on what the customer pays for items
        // after every discount (coupon + points): ₹500 of items paid ₹400 with points pays delivery.
        [$flat, $freeAbove] = self::shippingRule();
        $itemsPaid = $subtotal - $discount - $pointsDiscount;
        $shipping = ($groups && !($freeAbove !== null && $itemsPaid >= $freeAbove)) ? $flat : 0;
        $shippingWaived = 0;
        if ($shipping > 0 && $freeShipVendors) {   // free-shipping coupon applied to this cart
            $shippingWaived = $shipping;
            $shipping = 0;
        }
        // Split the fee across packages by value. Each share offsets that seller's
        // courier charge (SellerFeeService) and is invoiced per package as eClinicPro's delivery supply.
        $shares = self::split($shipping, array_map(static fn ($g) => $g['subtotal'] - $g['discount'] - $g['points_discount'], $groups));
        foreach ($groups as $vid => &$g) {
            $g['shipping'] = $shares[$vid] ?? 0;
            $g['free_above'] = $freeAbove;
            $g['total'] = $g['subtotal'] - $g['discount'] - $g['points_discount'] + $g['shipping'];
        }
        unset($g);

        return [
            'groups' => array_values($groups),
            'subtotal' => $subtotal,
            'mrp_total' => $mrp,
            'savings' => max(0, $mrp - $subtotal + $discount + $pointsDiscount + $shippingWaived),
            'discount' => $discount,                    // coupon only
            'points_discount' => $pointsDiscount,
            'shipping' => $shipping,
            'shipping_waived' => $shippingWaived,
            'free_above' => $freeAbove,
            // "Add ₹X more for free delivery" (null when delivery is already free or never free).
            'add_for_free_shipping' => $shipping > 0 && $freeAbove !== null ? max(0, $freeAbove - $itemsPaid) : null,
            'tax_included' => $tax,
            'grand_total' => $subtotal - $discount - $pointsDiscount + $shipping,
            'item_count' => array_sum(array_map(static fn ($g) => array_sum(array_column($g['lines'], 'qty')), $groups)),
            'excluded_lines' => $excluded,
            'coupon' => $coupon !== null ? ['id' => (int) $coupon['id'], 'code' => $coupon['code'], 'label' => CouponService::describe($coupon),
                'applied' => $couponNote === null && ($discount > 0 || $shippingWaived > 0), 'note' => $couponNote] : null,
            // null = guest / points switched off. kind: welcome | standard | null (none used this order).
            'points' => $points !== null ? [
                'kind' => $points['kind'],
                'used' => $points['points'],
                'available' => $points['balance']['available'],
                'pending' => $points['balance']['pending'],
                'welcome' => $points['balance']['welcome'],
                'welcome_add_paise' => $points['welcome_add_paise'],   // "Add ₹X more to use your welcome points"
                // Loyalty this order earns after delivery (only when it uses no points).
                'earns' => $points['points'] > 0 ? 0 : PointsService::loyaltyPoints($subtotal - $discount),
            ] : null,
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

    /**
     * The customer delivery fee: once per ORDER (the platform default row, vendor_id NULL).
     *
     * @return array{0: int, 1: ?int} [flat fee paise, free-above paise or null]
     */
    public static function shippingRule(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $r = Database::connection()->query(
            'SELECT flat_fee_paise, free_above_paise FROM store_shipping_rules
              WHERE is_active = 1 AND vendor_id IS NULL ORDER BY id DESC LIMIT 1'
        )->fetch();

        return $cache = $r
            ? [(int) $r['flat_fee_paise'], $r['free_above_paise'] !== null ? (int) $r['free_above_paise'] : null]
            : [0, null];
    }

    /**
     * Split $amount across keys in proportion to $weights (largest remainder, sums exactly).
     * All weights zero → everything on the first key.
     *
     * @param array<int, int> $weights
     * @return array<int, int>
     */
    public static function split(int $amount, array $weights): array
    {
        if (!$weights) {
            return [];
        }
        $out = array_fill_keys(array_keys($weights), 0);
        $total = array_sum(array_map(static fn ($w) => max(0, $w), $weights));
        if ($amount <= 0) {
            return $out;
        }
        if ($total <= 0) {
            $out[array_key_first($weights)] = $amount;

            return $out;
        }
        $given = 0;
        $rem = [];
        foreach ($weights as $k => $w) {
            $exact = $amount * max(0, $w) / $total;
            $out[$k] = (int) floor($exact);
            $given += $out[$k];
            $rem[$k] = $exact - $out[$k];
        }
        arsort($rem);
        foreach (array_keys($rem) as $k) {
            if ($given >= $amount) {
                break;
            }
            $out[$k]++;
            $given++;
        }

        return $out;
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
