<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Turns a cart into an order — in ONE transaction:
 *   1. lock the variants, re-read the cart, re-price server-side,
 *   2. reserve stock (conditional UPDATE, so two buyers can't take the last unit),
 *   3. write store_orders + one store_vendor_orders per seller + store_order_items
 *      (commission snapshot per line), all status `pending_payment`.
 *
 * The split per seller happens HERE, before payment, so "payment succeeded but
 * order creation failed" cannot happen. Payment (P6) only flips the status.
 * Unpaid orders expire after `store_payment_window_minutes` and release stock.
 *
 * A customer has at most one open (unpaid) order: placing a new one cancels
 * the previous unpaid one first.
 */
final class CheckoutService
{
    /**
     * @param array<string, mixed> $address store_addresses row (already identity-scoped)
     * @param array{name: string, phone: string, email: ?string} $contact
     * @param string $source web | mobile (store_orders.source)
     * @return array{ok: bool, error?: string, order_no?: string}
     */
    public static function place(int $identityId, int $cartId, array $address, array $contact, string $checkoutKey, ?int $clientTotal, string $source = 'web'): array
    {
        if (!preg_match('/^[a-f0-9-]{36}$/', $checkoutKey)) {
            return ['ok' => false, 'error' => 'Your checkout session expired. Please reload the page.'];
        }
        OrderService::expireStale();

        // Idempotency: a double-click / retry returns the order already placed.
        $prior = QueryBuilder::table('store_orders')->where('checkout_key', '=', $checkoutKey)->first();
        if ($prior !== null) {
            return (int) $prior['identity_id'] === $identityId
                ? ['ok' => true, 'order_no' => (string) $prior['order_no']]
                : ['ok' => false, 'error' => 'Please reload the page and try again.'];
        }

        $flag = QueryBuilder::table('store_customer_flags')->where('identity_id', '=', $identityId)->first();
        if ($flag !== null && (int) $flag['is_blocked'] === 1) {
            return ['ok' => false, 'error' => 'Your account can\'t place orders right now. Please contact support.'];
        }

        // One open checkout at a time: release the previous unpaid order's stock.
        $st = Database::connection()->prepare("SELECT id FROM store_orders WHERE identity_id = :i AND status = 'pending_payment'");
        $st->execute(['i' => $identityId]);
        foreach ($st->fetchAll() as $open) {
            OrderService::release((int) $open['id'], 'cancelled', 'Replaced by a newer checkout');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Lock every variant in the cart (consistent order avoids deadlocks).
            $lock = $pdo->prepare(
                'SELECT sv.id FROM store_product_variants sv
                   JOIN store_cart_items ci ON ci.variant_id = sv.id
                  WHERE ci.cart_id = :c ORDER BY sv.id FOR UPDATE'
            );
            $lock->execute(['c' => $cartId]);

            $items = CartService::items($cartId);
            if (!$items) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Your cart is empty.'];
            }
            foreach ($items as $it) {
                if ($it['problem'] !== null) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => $it['name'] . ': ' . $it['problem'] . '. Please update your cart.'];
                }
            }
            $cartRow = QueryBuilder::table('store_carts')->where('id', '=', $cartId)->first() ?? [];
            $cc = PricingService::cartCoupon($cartRow, $identityId);
            if ($cc['error'] !== null) {
                // The price the customer saw included a coupon that no longer works: stop and explain.
                $pdo->rollBack();
                // After the rollback, or the rollback would undo it.
                QueryBuilder::table('store_carts')->where('id', '=', $cartId)->update(['coupon_code' => null]);

                return ['ok' => false, 'error' => $cc['error'] . ' It has been removed; please review your total.'];
            }
            $quote = PricingService::quote($items, $cc['coupon']);
            $couponApplied = $quote['coupon'] !== null && $quote['coupon']['applied'];

            // Reserve stock; the WHERE guard makes this safe against concurrent checkouts.
            $reserve = $pdo->prepare(
                'UPDATE store_product_variants SET reserved_qty = reserved_qty + :q
                  WHERE id = :id AND is_active = 1 AND stock_qty >= reserved_qty + :q2'
            );
            $move = $pdo->prepare(
                "INSERT INTO store_inventory_movements (variant_id, delta, reason, ref_type, actor_type)
                 VALUES (:v, :d, 'reserve', 'order', 'system')"
            );
            foreach ($items as $it) {
                $reserve->execute(['q' => (int) $it['qty'], 'id' => (int) $it['variant_id'], 'q2' => (int) $it['qty']]);
                if ($reserve->rowCount() !== 1) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => $it['name'] . ' just sold out. Please update your cart.'];
                }
                $move->execute(['v' => (int) $it['variant_id'], 'd' => -(int) $it['qty']]);
            }

            $orderNo = self::newOrderNo();
            $window = max(5, StoreSettings::int('store_payment_window_minutes', 30));
            $ship = AddressService::snapshot($address);
            $orderId = QueryBuilder::table('store_orders')->insert([
                'order_no' => $orderNo,
                'identity_id' => $identityId,
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'contact_name' => mb_substr($contact['name'], 0, 160),
                'contact_phone' => $contact['phone'],
                'contact_email' => $contact['email'],
                'ship_address_json' => json_encode($ship, JSON_UNESCAPED_UNICODE),
                'bill_address_json' => json_encode($ship, JSON_UNESCAPED_UNICODE),
                'items_subtotal_paise' => $quote['subtotal'],
                'discount_paise' => $quote['discount'],
                'coupon_id' => $couponApplied ? (int) $quote['coupon']['id'] : null,
                'coupon_code' => $couponApplied ? (string) $quote['coupon']['code'] : null,
                'shipping_paise' => $quote['shipping'],
                'tax_included_paise' => $quote['tax_included'],
                'platform_fee_paise' => 0,
                'grand_total_paise' => $quote['grand_total'],
                'client_total_paise' => $clientTotal,
                'checkout_key' => $checkoutKey,
                'expires_at' => date('Y-m-d H:i:s', time() + $window * 60),
                'source' => $source === 'mobile' ? 'mobile' : 'web',
                'created_ip' => ($ip = @inet_pton((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) !== false ? $ip : null,
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);

            foreach ($quote['groups'] as $i => $g) {
                $pickup = self::pickupAddress((int) $g['vendor_id']);
                $voId = QueryBuilder::table('store_vendor_orders')->insert([
                    'sub_order_no' => $orderNo . '-' . self::suffix($i),
                    'order_id' => $orderId,
                    'vendor_id' => (int) $g['vendor_id'],
                    'status' => 'pending_payment',
                    'items_subtotal_paise' => $g['subtotal'],
                    'vendor_discount_paise' => $g['vendor_discount'],
                    'platform_discount_paise' => $g['platform_discount'],
                    'shipping_paise' => $g['shipping'],
                    'tax_included_paise' => $g['tax'],
                    'commission_paise' => $g['commission'],
                    'commission_gst_paise' => $g['commission_gst'],
                    'vendor_payable_paise' => $g['vendor_payable'],
                    'pickup_address_id' => $pickup['id'] ?? null,
                    'pickup_snapshot_json' => $pickup !== null ? json_encode($pickup, JSON_UNESCAPED_UNICODE) : null,
                ]);
                foreach ($g['lines'] as $l) {
                    QueryBuilder::table('store_order_items')->insert([
                        'order_id' => $orderId,
                        'vendor_order_id' => $voId,
                        'vendor_id' => (int) $g['vendor_id'],
                        'product_id' => (int) $l['product_id'],
                        'variant_id' => (int) $l['variant_id'],
                        'sku' => $l['sku'],
                        'name' => mb_substr((string) $l['name'], 0, 255),
                        'variant_title' => $l['variant_title'],
                        'image_path' => $l['cover'],
                        'category_id' => (int) $l['category_id'],
                        'hsn_code' => $l['hsn_code'],
                        'gst_bp' => (int) $l['gst_bp'],
                        'qty' => (int) $l['qty'],
                        'mrp_paise' => (int) $l['mrp_paise'],
                        'unit_price_paise' => $l['unit_price_paise'],
                        'line_subtotal_paise' => $l['line_subtotal_paise'],
                        'vendor_discount_paise' => $l['vendor_discount_paise'],
                        'platform_discount_paise' => $l['platform_discount_paise'],
                        'tax_included_paise' => $l['tax_included_paise'],
                        'line_total_paise' => $l['line_total_paise'],
                        'commission_type' => $l['commission_type'],
                        'commission_rate_bp' => $l['commission_rate_bp'],
                        'commission_rule_id' => $l['commission_rule_id'],
                        'commission_paise' => $l['commission_paise'],
                        'vendor_payable_paise' => $l['vendor_payable_paise'],
                    ]);
                }
            }
            OrderService::history($orderId, null, 'order', $orderId, null, 'pending_payment', 'customer', $identityId, 'Order placed');

            foreach (array_unique(array_map(static fn ($it) => (int) $it['product_id'], $items)) as $pid) {
                ProductService::refreshDenormalized($pid);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[CheckoutService::place] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'We couldn\'t place your order. Nothing was charged; please try again.'];
        }

        return ['ok' => true, 'order_no' => $orderNo];
    }

    /** Human-quotable order number, e.g. ECS260929-7K3MQ. Never exposes the row id. */
    private static function newOrderNo(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';   // no 0/O/1/I confusion
        for ($try = 0; $try < 5; $try++) {
            $code = '';
            for ($i = 0; $i < 5; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $no = 'ECS' . date('ymd') . '-' . $code;
            if (QueryBuilder::table('store_orders')->where('order_no', '=', $no)->first() === null) {
                return $no;
            }
        }

        return 'ECS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    private static function suffix(int $i): string
    {
        return $i < 26 ? chr(65 + $i) : 'Z' . ($i - 25);
    }

    private static function pickupAddress(int $vendorId): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT id, contact_name, phone, line1, line2, city, state, pincode, sr_pickup_name
               FROM store_vendor_addresses
              WHERE vendor_id = :v AND type = 'pickup' AND is_active = 1
              ORDER BY is_default DESC, id DESC LIMIT 1"
        );
        $st->execute(['v' => $vendorId]);

        return $st->fetch() ?: null;
    }
}
