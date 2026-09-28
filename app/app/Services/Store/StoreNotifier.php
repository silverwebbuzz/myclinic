<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\QueryBuilder;
use App\Services\SmtpMailService;

/**
 * Store notifications: plain-text email (SMTP) + in-app rows (store_notifications).
 * Best-effort ALWAYS — called after the database commit, never inside it, so a
 * mail failure can't undo a paid order. Email only goes to addresses we have.
 */
final class StoreNotifier
{
    public static function orderPaid(int $orderId): void
    {
        try {
            $o = OrderService::load($orderId);
            if ($o === null) {
                return;
            }
            $r = static fn (int $p): string => 'Rs. ' . ProductService::rupees($p);
            $a = $o['ship_address'];
            $storeBase = rtrim((string) ($_ENV['STORE_BASE_URL'] ?? 'https://eclinicpro.com'), '/');
            $portalBase = rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');

            // Customer
            if (!empty($o['contact_email'])) {
                $lines = [];
                foreach ($o['vendor_orders'] as $vo) {
                    $lines[] = 'Package from ' . $vo['vendor_name'] . ':';
                    foreach ($vo['items'] as $it) {
                        $lines[] = '  - ' . $it['name'] . ($it['variant_title'] ? ' (' . $it['variant_title'] . ')' : '') . ' x ' . $it['qty'] . ': ' . $r((int) $it['line_total_paise']);
                    }
                }
                $body = "Hi {$o['contact_name']},\n\nThank you! We've received your payment for order {$o['order_no']}.\n\n"
                    . implode("\n", $lines) . "\n\n"
                    . 'Shipping: ' . ((int) $o['shipping_paise'] > 0 ? $r((int) $o['shipping_paise']) : 'Free') . "\n"
                    . 'Total paid: ' . $r((int) $o['grand_total_paise']) . " (incl. GST)\n\n"
                    . "Delivering to: {$a['name']}, {$a['line1']}, {$a['city']}, {$a['state']} {$a['pincode']}\n\n"
                    . "Each seller packs and ships their items separately. We'll email you when each package ships.\n"
                    . "View your order: {$storeBase}/store/order/{$o['order_no']}\n\n— eClinicPro Store";
                self::mail((string) $o['contact_email'], 'Order confirmed: ' . $o['order_no'], $body);
            }

            // Each seller: ONLY their own package (no other sellers' items).
            foreach ($o['vendor_orders'] as $vo) {
                $vendor = VendorService::find((int) $vo['vendor_id']);
                if ($vendor === null) {
                    continue;
                }
                $lines = [];
                foreach ($vo['items'] as $it) {
                    $lines[] = '  - ' . $it['sku'] . ' | ' . $it['name'] . ($it['variant_title'] ? ' (' . $it['variant_title'] . ')' : '') . ' x ' . $it['qty'];
                }
                $body = "New paid order {$vo['sub_order_no']}\n\n" . implode("\n", $lines) . "\n\n"
                    . "Deliver to: {$a['city']}, {$a['state']} {$a['pincode']}\n"
                    . 'Please accept and pack it by ' . date('j M, g:i a', (int) strtotime((string) $vo['accept_by'])) . ".\n"
                    . "Open your seller portal: {$portalBase}/vendor/orders\n\n— eClinicPro Store";
                self::mail((string) $vendor['email'], 'New order ' . $vo['sub_order_no'], $body);
                $owner = QueryBuilder::table('store_vendor_users')->where('vendor_id', '=', (int) $vendor['id'])->where('role', '=', 'owner')->first();
                if ($owner !== null) {
                    self::inApp('vendor_user', (int) $owner['id'], 'order.new', 'New order ' . $vo['sub_order_no'],
                        count($vo['items']) . ' item(s) to pack', '/vendor/orders');
                }
            }

            // Store team
            $admin = (string) ($_ENV['STORE_ADMIN_EMAIL'] ?? $_ENV['HELP_FROM'] ?? 'help@eclinicpro.com');
            self::mail($admin, 'Store order paid: ' . $o['order_no'] . ' (' . $r((int) $o['grand_total_paise']) . ')',
                "Order {$o['order_no']} paid: " . $r((int) $o['grand_total_paise']) . ', ' . count($o['vendor_orders']) . " seller(s).\n"
                . "{$portalBase}/admin/store/orders/{$o['id']}");
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::orderPaid] ' . $e->getMessage());
        }
    }

    /** After a cancellation + refund: tell the customer; tell the seller(s) unless they did it themselves. */
    public static function itemsCancelled(int $orderId, string $refundNo, int $amount, string $actorType, string $reason): void
    {
        try {
            $o = OrderService::load($orderId);
            if ($o === null) {
                return;
            }
            $amt = 'Rs. ' . ProductService::rupees($amount);
            $who = match ($actorType) {
                'customer' => 'as you requested',
                'vendor_user' => 'by the seller',
                'admin' => 'by the eClinicPro team',
                default => 'because the seller did not confirm it in time',
            };
            $storeBase = rtrim((string) ($_ENV['STORE_BASE_URL'] ?? 'https://eclinicpro.com'), '/');
            if (!empty($o['contact_email'])) {
                self::mail((string) $o['contact_email'], "Refund of $amt for order {$o['order_no']}",
                    "Hi {$o['contact_name']},\n\nSome items in order {$o['order_no']} were cancelled $who.\nReason: $reason\n\n"
                    . "We've refunded $amt to your original payment method (refund ref $refundNo). It usually reaches your account in 5–7 working days.\n\n"
                    . "View your order: {$storeBase}/store/order/{$o['order_no']}\n\n— eClinicPro Store");
            }
            if ($actorType !== 'vendor_user') {
                // Every seller with items in THIS refund, with just their cancelled lines.
                $st = \App\Core\Database::connection()->prepare(
                    'SELECT oi.vendor_id, vo.sub_order_no, vo.status AS vo_status, oi.sku, oi.name, ri.qty
                       FROM store_refunds rf
                       JOIN store_refund_items ri ON ri.refund_id = rf.id
                       JOIN store_order_items oi ON oi.id = ri.order_item_id
                       JOIN store_vendor_orders vo ON vo.id = oi.vendor_order_id
                      WHERE rf.refund_no = :r'
                );
                $st->execute(['r' => $refundNo]);
                $byVendor = [];
                foreach ($st->fetchAll() as $row) {
                    $byVendor[(int) $row['vendor_id']]['sub'] = $row['sub_order_no'];
                    $byVendor[(int) $row['vendor_id']]['whole'] = !in_array($row['vo_status'], ['new', 'accepted', 'packed', 'ready_to_ship'], true);
                    $byVendor[(int) $row['vendor_id']]['lines'][] = "  - {$row['sku']} | {$row['name']} x {$row['qty']}";
                }
                foreach ($byVendor as $vid => $v) {
                    $vendor = VendorService::find($vid);
                    if ($vendor === null) {
                        continue;
                    }
                    self::mail((string) $vendor['email'], ($v['whole'] ? 'Order cancelled: ' : 'Items cancelled: ') . $v['sub'],
                        "In order {$v['sub']}, these items were cancelled $who:\n" . implode("\n", $v['lines'])
                        . "\nReason: $reason\n" . ($v['whole'] ? "Please don't ship this order.\n" : "Ship only the remaining items.\n") . "\n— eClinicPro Store");
                }
            }
            if ($actorType === 'system') {
                $admin = (string) ($_ENV['STORE_ADMIN_EMAIL'] ?? $_ENV['HELP_FROM'] ?? 'help@eclinicpro.com');
                self::mail($admin, 'Auto-cancelled (seller SLA): ' . $o['order_no'], "Refunded $amt ($refundNo). Reason: $reason");
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::itemsCancelled] ' . $e->getMessage());
        }
    }

    public static function inApp(string $recipientType, int $recipientId, string $event, string $title, string $body, string $link): void
    {
        try {
            QueryBuilder::table('store_notifications')->insert([
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'event' => $event,
                'title' => mb_substr($title, 0, 190),
                'body' => mb_substr($body, 0, 500),
                'link' => $link,
            ]);
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::inApp] ' . $e->getMessage());
        }
    }

    private static function mail(string $to, string $subject, string $body): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $res = SmtpMailService::send($to, $subject, $body, $_ENV['NOREPLY_FROM'] ?? 'noreply@eclinicpro.com', 'eClinicPro Store');
        if (!$res['ok']) {
            error_log('[StoreNotifier] mail to ' . $to . ' failed: ' . ($res['error'] ?? ''));
        }
    }
}
