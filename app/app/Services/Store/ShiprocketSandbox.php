<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Simulated Shiprocket for store testing (Store settings → Shiprocket → Sandbox).
 *
 * Shiprocket has no sandbox API: every AWB on the real account debits the wallet
 * and dispatches a real courier. In sandbox mode ShiprocketClient answers from
 * here instead of the network, with response shapes matching what our callers read.
 *
 * Sandbox references start with "SBX" (AWB, order and shipment ids) so they never
 * collide with real Shiprocket ids, and so live mode can skip them.
 *
 * Tracking moves ONE step per refresh (admin "Refresh tracking" or the cron poll):
 * picked up → in transit → out for delivery → delivered. Only SBX AWBs move.
 */
final class ShiprocketSandbox
{
    public const PREFIX = 'SBX';

    private const STEPS = [
        // [rank the shipment must be below, raw Shiprocket status]
        [40, 'PICKED UP'],
        [50, 'IN TRANSIT'],
        [60, 'OUT FOR DELIVERY'],
        [100, 'DELIVERED'],
    ];

    public static function isRef(?string $ref): bool
    {
        return $ref !== null && str_starts_with($ref, self::PREFIX);
    }

    /** @return array<string, mixed> */
    public static function respond(string $method, string $path, ?array $body): array
    {
        $route = strtok($path, '?') ?: $path;

        if ($route === '/settings/company/addpickup') {
            return ['success' => true, 'address' => ['pickup_code' => (string) ($body['pickup_location'] ?? '')]];
        }
        if ($route === '/orders/create/adhoc' || $route === '/orders/create/return') {
            return ['order_id' => self::ref('O'), 'shipment_id' => self::ref('S'), 'status' => 'NEW', 'status_code' => 1];
        }
        if ($route === '/courier/assign/awb') {
            return ['awb_assign_status' => 1, 'response' => ['data' => [
                'awb_code' => self::ref(''),
                'courier_company_id' => 0,
                'courier_name' => 'Sandbox Courier',
                'freight_charges' => StoreSettings::int('store_courier_est_base_paise', 6500) / 100,
            ]]];
        }
        if ($route === '/courier/generate/pickup') {
            return ['pickup_status' => 1, 'response' => ['pickup_scheduled_date' => date('Y-m-d', time() + 86400) . ' 10:00:00']];
        }
        if ($route === '/courier/generate/label') {
            $base = rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');

            return ['label_created' => 1, 'label_url' => $base . '/sandbox-shipping-label.html'];
        }
        if ($route === '/orders/cancel') {
            return ['message' => 'Sandbox order cancelled.'];
        }
        if ($route === '/courier/serviceability/') {
            return ['status' => 200, 'data' => ['available_courier_companies' => [
                ['courier_name' => 'Sandbox Courier', 'estimated_delivery_days' => 4, 'rate' => 65],
            ]]];
        }
        if (str_starts_with($route, '/courier/track/awb/')) {
            return self::track(rawurldecode(substr($route, strlen('/courier/track/awb/'))));
        }

        return ['_error' => 'Sandbox: no simulated response for ' . $method . ' ' . $route, '_http' => 501];
    }

    /** Next tracking step for a sandbox AWB (just that one activity, so the timeline doesn't duplicate). */
    private static function track(string $awb): array
    {
        if (!self::isRef($awb)) {
            return ['tracking_data' => ['track_status' => 0, 'error' => 'Not a sandbox AWB.']];
        }
        $st = Database::connection()->prepare('SELECT status_rank FROM store_shipments WHERE awb_code = :a LIMIT 1');
        $st->execute(['a' => $awb]);
        $rank = $st->fetchColumn();
        if ($rank === false) {
            return ['tracking_data' => ['track_status' => 0]];
        }
        foreach (self::STEPS as [$below, $raw]) {
            if ((int) $rank < $below) {
                return ['tracking_data' => ['track_status' => 1, 'shipment_track_activities' => [[
                    'date' => date('Y-m-d H:i:s'),
                    'sr-status-label' => $raw,
                    'activity' => 'Sandbox: ' . ucwords(strtolower($raw)),
                    'location' => 'Sandbox hub',
                ]]]];
            }
        }

        return ['tracking_data' => ['track_status' => 1, 'shipment_track_activities' => []]];
    }

    private static function ref(string $kind): string
    {
        return self::PREFIX . $kind . date('ymdHis') . random_int(100, 999);
    }
}
