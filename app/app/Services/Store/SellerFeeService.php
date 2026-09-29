<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Who pays for delivery, in one place.
 *
 *   Customer: one delivery fee PER ORDER (flat, free above a threshold), split across
 *             the order's packages in proportion to their value (PricingService).
 *   Seller:   the forward courier cost of their package, MINUS the part of the
 *             customer's delivery fee that went to that package. Never below zero;
 *             any excess fee stays with eClinicPro. Charged at delivery (SettlementService).
 *   eClinicPro: customer-caused failed deliveries (RTO), change-of-mind return pickups.
 *
 * The courier cost is Shiprocket's real charge when we have it (store_shipments.actual_charge_paise),
 * otherwise the rate-card estimate below. All amounts include GST. VERIFY WITH CA:
 * the seller charge is a supply by eClinicPro to the seller (18% GST inside).
 */
final class SellerFeeService
{
    public const SLAB_G = 500;
    /** Couriers bill the larger of actual weight and L×B×H(cm) / 5000 kg. */
    public const VOLUMETRIC_DIVISOR = 5000;

    /** @return array{delivery_fee: int, free_above: ?int, courier_base: int, courier_addl: int, slab_g: int, vol_divisor: int, commission_gst_bp: int} */
    public static function config(): array
    {
        try {
            [$flat, $freeAbove] = PricingService::shippingRule();
        } catch (\Throwable) {
            [$flat, $freeAbove] = [4900, 49900];   // orders patch not imported yet: the seeded defaults
        }

        return [
            'delivery_fee' => $flat,
            'free_above' => $freeAbove,
            'courier_base' => StoreSettings::int('store_courier_est_base_paise', 6500),
            'courier_addl' => StoreSettings::int('store_courier_est_addl_paise', 4000),
            'slab_g' => self::SLAB_G,
            'vol_divisor' => self::VOLUMETRIC_DIVISOR,
            'commission_gst_bp' => CommissionService::COMMISSION_GST_BP,
        ];
    }

    /** Weight the courier bills: the larger of actual and volumetric (grams). */
    public static function chargeableWeight(int $weightG, float $lengthCm, float $breadthCm, float $heightCm): int
    {
        $volumetric = (int) ceil($lengthCm * $breadthCm * $heightCm / self::VOLUMETRIC_DIVISOR * 1000);

        return max($weightG, $volumetric, 1);
    }

    /** Rate-card estimate of the forward courier charge (paise, GST included). */
    public static function courierEstimate(int $weightG, float $lengthCm = 0, float $breadthCm = 0, float $heightCm = 0): int
    {
        $c = self::config();
        $slabs = max(1, (int) ceil(self::chargeableWeight($weightG, $lengthCm, $breadthCm, $heightCm) / self::SLAB_G));

        return $c['courier_base'] + ($slabs - 1) * $c['courier_addl'];
    }

    /**
     * What the seller is charged for a package's forward delivery.
     *
     * @return array{courier: int, credit: int, charge: int, estimated: bool}
     */
    public static function packageCharge(array $vendorOrder, ?array $shipment): array
    {
        // Admin-entered charge wins, then Shiprocket's charge, then the rate-card estimate.
        $estimated = false;
        $courier = isset($vendorOrder['courier_charge_paise']) && $vendorOrder['courier_charge_paise'] !== null ? (int) $vendorOrder['courier_charge_paise'] : null;
        if ($courier === null && $shipment !== null && $shipment['actual_charge_paise'] !== null) {
            $courier = (int) $shipment['actual_charge_paise'];
        }
        if ($courier === null) {
            $estimated = true;
            $courier = $shipment !== null
                ? self::courierEstimate((int) $shipment['weight_g'], (int) $shipment['length_mm'] / 10, (int) $shipment['breadth_mm'] / 10, (int) $shipment['height_mm'] / 10)
                : self::courierEstimate(self::packageWeight((int) $vendorOrder['id']));
        }
        $credit = min(max(0, (int) ($vendorOrder['shipping_paise'] ?? 0)), $courier);

        return ['courier' => $courier, 'credit' => $credit, 'charge' => $courier - $credit, 'estimated' => $estimated];
    }

    /**
     * Estimated earnings for ONE unit sold on its own, for the seller's calculator.
     * Two cases: a small order (customer pays the delivery fee, which offsets the
     * courier) and an order above the free-delivery threshold (seller pays it all).
     *
     * @param array{type: string, rate_bp: int, fixed_paise: int} $rule commission rule
     * @return array<string, int|bool|null>
     */
    public static function estimate(int $pricePaise, int $weightG, float $lengthCm, float $breadthCm, float $heightCm, array $rule): array
    {
        $c = self::config();
        $commission = CommissionService::amount($rule + ['rule_id' => null], $pricePaise, 1);
        $commissionGst = (int) round($commission * $c['commission_gst_bp'] / 10000);
        $courier = self::courierEstimate($weightG, $lengthCm, $breadthCm, $heightCm);
        $smallOrder = $c['free_above'] === null || $pricePaise < $c['free_above'];
        $credit = $smallOrder ? min($c['delivery_fee'], $courier) : 0;
        $shipping = $courier - $credit;

        return [
            'price' => $pricePaise,
            'commission' => $commission,
            'commission_gst' => $commissionGst,
            'courier' => $courier,
            'customer_fee_credit' => $credit,
            'shipping' => $shipping,
            'net' => $pricePaise - $commission - $commissionGst - $shipping,
            'small_order' => $smallOrder,
        ];
    }

    private static function packageWeight(int $vendorOrderId): int
    {
        $st = Database::connection()->prepare(
            'SELECT COALESCE(SUM((oi.qty - oi.qty_cancelled) * sv.weight_g), 0)
               FROM store_order_items oi JOIN store_product_variants sv ON sv.id = oi.variant_id
              WHERE oi.vendor_order_id = :v'
        );
        $st->execute(['v' => $vendorOrderId]);

        return max(50, (int) $st->fetchColumn());
    }

    /** Seller charges on a package (courier, weight disputes, RTO, return pickups) for the order screens. @return list<array<string, mixed>> */
    public static function chargesFor(int $vendorOrderId): array
    {
        try {
            $st = Database::connection()->prepare(
                "SELECT id, entry_type, amount_paise, memo, status, created_by_type, created_at FROM store_vendor_ledger
                  WHERE vendor_order_id = :v AND entry_type IN ('shipping_debit','rto_charge','penalty','adjustment')
                  ORDER BY id"
            );
            $st->execute(['v' => $vendorOrderId]);

            return $st->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }
}
