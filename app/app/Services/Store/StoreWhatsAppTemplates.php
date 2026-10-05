<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Services\WaTemplateService;
use App\Support\MessagingSettings;

/**
 * Customer WhatsApp order updates: which templates exist, their sample data and
 * the store's on/off switches.
 *
 * The WORDING is not here: Meta must approve every business-initiated message,
 * so the text lives in the platform's wa_templates registry (seeded by
 * 2026_10_05_store_whatsapp.sql, edited and marked approved on /admin/messaging).
 * StoreNotifier decides WHEN to send and fills the numbered parameters; the
 * notifications queue (NotificationProcessor → WhatsAppService) sends them.
 *
 * A message goes out only when ALL of these hold:
 *   - store switch on (store_wa_enabled) and this template not switched off (store_wa_disabled)
 *   - platform messaging on (platform_settings messaging_enabled)
 *   - the template exists in wa_templates and its status is 'approved'
 * Otherwise StoreNotifier logs it as 'skipped' (store_email_log, channel whatsapp).
 */
final class StoreWhatsAppTemplates
{
    /** key => [label, when it is sent, sample payload (keys = the template's variables)] */
    private const T = [
        'store_order_confirmed' => ['Order confirmed', 'Payment received',
            ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'amount' => '₹1,249', 'packages' => '2', 'order_url' => 'https://eclinicpro.com/store/order/ECS260929-7K3MQ']],
        'store_order_shipped' => ['Package shipped', 'Courier picked up a package (max one per order per day)',
            ['name' => 'Priya', 'seller' => 'Wellness Mart', 'order_no' => 'ECS260929-7K3MQ', 'courier' => 'Delhivery', 'awb' => '1234567890', 'tracking_url' => 'https://shiprocket.co/tracking/1234567890']],
        'store_order_out_for_delivery' => ['Out for delivery', 'Courier reports out for delivery (max one per order per day)',
            ['name' => 'Priya', 'seller' => 'Wellness Mart', 'order_no' => 'ECS260929-7K3MQ', 'courier' => 'Delhivery', 'awb' => '1234567890', 'tracking_url' => 'https://shiprocket.co/tracking/1234567890']],
        'store_order_delivered' => ['Order delivered', 'Last package of the order delivered (once per order)',
            ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'order_url' => 'https://eclinicpro.com/store/order/ECS260929-7K3MQ']],
        'store_order_cancelled' => ['Items cancelled + refund', 'Items cancelled (by customer, seller, admin or auto-cancel) and refunded',
            ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'who' => 'as you requested', 'amount' => '₹499', 'refund_no' => 'RF260930-A1B2C3']],
        'store_refund_processed' => ['Return refunded', 'Refund issued for a return',
            ['name' => 'Priya', 'amount' => '₹499', 'return_no' => 'RT260930-9F8E7D', 'order_no' => 'ECS260929-7K3MQ']],
        'store_return_update' => ['Return approved / not accepted', 'Seller approved (or pickup booked) or rejected a return',
            ['name' => 'Priya', 'return_no' => 'RT260930-9F8E7D', 'order_no' => 'ECS260929-7K3MQ',
                'update' => 'The seller approved your return. A courier will collect the item from your delivery address. Please keep it packed with all tags and accessories.',
                'order_url' => 'https://eclinicpro.com/store/order/ECS260929-7K3MQ']],
        'store_order_expired' => ['Order not paid (expired)', 'Unpaid order expired and stock was released',
            ['name' => 'Priya', 'order_no' => 'ECS260929-7K3MQ', 'amount' => '₹1,249', 'cart_url' => 'https://eclinicpro.com/store/cart']],
    ];

    /** @return array<string, array{label: string, when: string}> */
    public static function registry(): array
    {
        $out = [];
        foreach (self::T as $key => $t) {
            $out[$key] = ['label' => $t[0], 'when' => $t[1]];
        }

        return $out;
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::T[$key]);
    }

    /** @return array<string, string> */
    public static function sampleVars(string $key): array
    {
        return self::T[$key][2] ?? [];
    }

    /** Store master switch (Store email → Customer WhatsApp updates). */
    public static function enabled(): bool
    {
        return StoreSettings::get('store_wa_enabled', '1') === '1';
    }

    /** @return list<string> template keys switched off by admin */
    public static function disabledKeys(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', StoreSettings::get('store_wa_disabled')))));
    }

    public static function isOn(string $key): bool
    {
        return self::enabled() && self::isKnown($key) && !in_array($key, self::disabledKeys(), true);
    }

    /** @param list<string> $onKeys */
    public static function saveSwitches(bool $enabled, array $onKeys): void
    {
        StoreSettings::set('store_wa_enabled', $enabled ? '1' : '0');
        StoreSettings::set('store_wa_disabled', implode(',', array_values(array_diff(array_keys(self::T), $onKeys))));
    }

    /**
     * Why this template can't be sent right now, or null when it can.
     * (Store switches are checked separately: those are logged as 'disabled'.)
     */
    public static function blocker(string $key): ?string
    {
        if (!MessagingSettings::enabled()) {
            return 'platform messaging is switched off (/admin/messaging)';
        }
        $tpl = WaTemplateService::find($key);
        if ($tpl === null) {
            return 'template ' . $key . ' is missing from wa_templates (run 2026_10_05_store_whatsapp.sql)';
        }
        if (!WaTemplateService::isApproved($key)) {
            return 'template ' . $key . ' is not approved yet (status: ' . ($tpl['status'] ?? '?') . ')';
        }

        return null;
    }

    /** wa_templates status for the admin list: approved / draft / submitted / rejected / paused / missing. */
    public static function metaStatus(string $key): string
    {
        $tpl = WaTemplateService::find($key);

        return $tpl === null ? 'missing' : (string) ($tpl['status'] ?? 'draft');
    }
}
