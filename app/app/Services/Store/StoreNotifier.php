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

    /** Shipment milestones: customer hears "shipped"/"delivered"; the team hears about problems. */
    public static function shipmentUpdate(int $shipmentId, string $event): void
    {
        try {
            $s = QueryBuilder::table('store_shipments')->where('id', '=', $shipmentId)->first();
            if ($s === null) {
                return;
            }
            $o = QueryBuilder::table('store_orders')->where('id', '=', (int) $s['order_id'])->first();
            $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', (int) $s['vendor_order_id'])->first();
            $vendor = VendorService::find((int) $s['vendor_id']);
            if ($o === null || $vo === null) {
                return;
            }
            $storeBase = rtrim((string) ($_ENV['STORE_BASE_URL'] ?? 'https://eclinicpro.com'), '/');
            $link = "{$storeBase}/store/order/{$o['order_no']}";
            $from = $vendor['display_name'] ?? 'the seller';
            if (in_array($event, ['shipped', 'delivered'], true) && !empty($o['contact_email'])) {
                $subject = $event === 'shipped' ? "Shipped: your package from $from" : "Delivered: your package from $from";
                $body = $event === 'shipped'
                    ? "Hi {$o['contact_name']},\n\nYour package from $from (order {$o['order_no']}) is on its way"
                      . (!empty($s['courier_name']) ? ' with ' . $s['courier_name'] : '') . ".\nTracking number (AWB): {$s['awb_code']}\n\nTrack it here: $link\n\n— eClinicPro Store"
                    : "Hi {$o['contact_name']},\n\nYour package from $from (order {$o['order_no']}) has been delivered. We hope it helps!\n\n"
                      . "If something is wrong with it, reply to this email or write to help@eclinicpro.com with your order number.\n$link\n\n— eClinicPro Store";
                self::mail((string) $o['contact_email'], $subject, $body);
            }
            if (in_array($event, ['ndr', 'pickup_failed', 'lost', 'damaged', 'rto_initiated', 'rto'], true)) {
                $admin = (string) ($_ENV['STORE_ADMIN_EMAIL'] ?? $_ENV['HELP_FROM'] ?? 'help@eclinicpro.com');
                $portalBase = rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');
                self::mail($admin, "Shipment problem ($event): {$vo['sub_order_no']}",
                    "AWB {$s['awb_code']} · {$from}\nLast courier status: {$s['last_raw_status']}\n{$portalBase}/admin/store/orders/{$o['id']}");
                if ($vendor !== null && in_array($event, ['pickup_failed', 'rto_initiated'], true)) {
                    self::mail((string) $vendor['email'], "Courier update for {$vo['sub_order_no']}: " . str_replace('_', ' ', $event),
                        "The courier reported: {$s['last_raw_status']} for AWB {$s['awb_code']}.\n"
                        . ($event === 'pickup_failed' ? "Please keep the package ready; pickup will be re-attempted.\n" : "The package is being returned to you.\n")
                        . "\n— eClinicPro Store");
                }
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::shipmentUpdate] ' . $e->getMessage());
        }
    }

    public static function returnEvent(int $returnId, string $event): void
    {
        try {
            $r = ReturnService::find($returnId, null);
            if ($r === null) {
                return;
            }
            $o = QueryBuilder::table('store_orders')->where('id', '=', (int) $r['order_id'])->first();
            $vendor = VendorService::find((int) $r['vendor_id']);
            $storeBase = rtrim((string) ($_ENV['STORE_BASE_URL'] ?? 'https://eclinicpro.com'), '/');
            $portalBase = rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');
            $admin = (string) ($_ENV['STORE_ADMIN_EMAIL'] ?? $_ENV['HELP_FROM'] ?? 'help@eclinicpro.com');
            $items = implode("\n", array_map(static fn ($i) => "  - {$i['sku']} | {$i['name']} x {$i['qty']}", $r['items']));
            $reason = ReturnService::REASONS[$r['reason_code']] ?? $r['reason_code'];
            $custEmail = (string) ($o['contact_email'] ?? '');
            $orderLink = "{$storeBase}/store/order/{$r['order_no']}";

            switch ($event) {
                case 'requested':
                    if ($vendor !== null) {
                        self::mail((string) $vendor['email'], "Return requested: {$r['return_no']} ({$r['sub_order_no']})",
                            "A customer asked to return:\n$items\nReason: $reason\n" . ($r['customer_note'] ? "Note: {$r['customer_note']}\n" : '')
                            . "\nPlease approve or reject within 2 days: {$portalBase}/vendor/returns/{$returnId}\n\n— eClinicPro Store");
                    }
                    self::mail($admin, "Return requested: {$r['return_no']} · {$r['vendor_name']}", "$reason\n$items\n{$portalBase}/admin/store/returns/{$returnId}");
                    break;
                case 'approved':
                case 'pickup_scheduled':
                    self::mail($custEmail, "Return approved: {$r['return_no']}",
                        "Hi {$r['contact_name']},\n\nYour return {$r['return_no']} was approved.\n"
                        . ($event === 'pickup_scheduled' ? "A courier will collect the item from your delivery address. Please keep it packed with all tags and accessories.\n"
                            : "We'll contact you to arrange the pickup.\n")
                        . "Your refund is issued once the seller receives and checks the item.\n$orderLink\n\n— eClinicPro Store");
                    break;
                case 'rejected':
                    self::mail($custEmail, "About your return {$r['return_no']}",
                        "Hi {$r['contact_name']},\n\nWe're sorry, your return {$r['return_no']} couldn't be accepted.\nReason: {$r['vendor_note']}\n\n"
                        . "If you think this is wrong, reply to this email or write to help@eclinicpro.com.\n\n— eClinicPro Store");
                    break;
                case 'qc_failed':
                    self::mail($admin, "Return QC failed: {$r['return_no']} · {$r['vendor_name']}",
                        "The seller says the returned item failed the check: {$r['vendor_note']}\nDecide (refund anyway or reject): {$portalBase}/admin/store/returns/{$returnId}");
                    break;
                case 'refunded':
                    self::mail($custEmail, "Refund of Rs. " . ProductService::rupees((int) $r['refund_amount_paise']) . " for return {$r['return_no']}",
                        "Hi {$r['contact_name']},\n\nWe've refunded Rs. " . ProductService::rupees((int) $r['refund_amount_paise'])
                        . " to your original payment method. It usually arrives within 5–7 working days.\n$orderLink\n\n— eClinicPro Store");
                    if ($vendor !== null) {
                        self::mail((string) $vendor['email'], "Return refunded: {$r['return_no']}",
                            "The customer was refunded for:\n$items\nThe amount has been adjusted in your earnings (Payouts page).\n\n— eClinicPro Store");
                    }
                    break;
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::returnEvent] ' . $e->getMessage());
        }
    }

    public static function payoutPaid(int $payoutId): void
    {
        try {
            $p = QueryBuilder::table('store_payouts')->where('id', '=', $payoutId)->first();
            $vendor = $p !== null ? VendorService::find((int) $p['vendor_id']) : null;
            if ($p === null || $vendor === null) {
                return;
            }
            $portalBase = rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');
            self::mail((string) $vendor['email'], 'Payout sent: Rs. ' . ProductService::rupees((int) $p['net_paise']) . ' (' . $p['payout_no'] . ')',
                "Hi {$vendor['contact_name']},\n\nWe've transferred Rs. " . ProductService::rupees((int) $p['net_paise'])
                . ' to your bank account ending ' . ($p['bank_last4'] ?? '') . ".\nBank reference (UTR): {$p['reference']}\n\n"
                . "Download the statement: {$portalBase}/vendor/payouts\n\n— eClinicPro Store");
            $owner = QueryBuilder::table('store_vendor_users')->where('vendor_id', '=', (int) $vendor['id'])->where('role', '=', 'owner')->first();
            if ($owner !== null) {
                self::inApp('vendor_user', (int) $owner['id'], 'payout.paid', 'Payout ' . $p['payout_no'] . ' sent',
                    'Rs. ' . ProductService::rupees((int) $p['net_paise']) . ' · UTR ' . $p['reference'], '/vendor/payouts');
            }
        } catch (\Throwable $e) {
            error_log('[StoreNotifier::payoutPaid] ' . $e->getMessage());
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
