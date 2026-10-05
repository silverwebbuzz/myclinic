<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Checkout pincode lookup: city + canonical GST state from a full India pincode
 * list, plus "can a courier deliver here, and in how many days" from Shiprocket.
 *
 * Data: assets/data/india-pincodes.json — { "380001": ["Ahmedabad", "24"], … }
 * (city = district, state = GST code; built by fetch_doctor/build_india_pincodes.py).
 * Loaded once per request and kept in a static, like api/lab_pincode.php.
 *
 * Serviceability is advisory: it never blocks checkout. When Shiprocket is off,
 * has no pickup pincode to compare with, or errors, the answer is
 * serviceable = true, eta_days = null. Answers are cached for a day per
 * (pickup, delivery) pair in repo-root storage/store_pincode_cache/.
 */
final class PincodeService
{
    private const CACHE_TTL = 86400;

    /**
     * @return array{pin: string, city: string, state: string, serviceable: bool, eta_days: ?int}|null
     *   null = pincode not in the list
     */
    public static function lookup(string $pin): ?array
    {
        $rec = self::map()[$pin] ?? null;
        $state = is_array($rec) ? GstStates::name((string) ($rec[1] ?? '')) : '';
        if ($state === '' || trim((string) ($rec[0] ?? '')) === '') {
            return null;
        }

        return ['pin' => $pin, 'city' => (string) $rec[0], 'state' => $state] + self::serviceability($pin);
    }

    /** @return array<string, array{0: string, 1: string}> */
    private static function map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        $path = self::root() . '/assets/data/india-pincodes.json';
        if (is_readable($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                $map = $data;
            }
        }

        return $map;
    }

    /** @return array{serviceable: bool, eta_days: ?int} */
    private static function serviceability(string $pin): array
    {
        $unknown = ['serviceable' => true, 'eta_days' => null];
        if (!ShiprocketClient::configured()) {
            return $unknown;
        }
        $pickup = self::pickupPincode();
        if ($pickup === null) {
            return $unknown;
        }
        $cacheFile = self::root() . '/storage/store_pincode_cache/' . $pickup . '_' . $pin . '.json';
        if (is_readable($cacheFile) && filemtime($cacheFile) > time() - self::CACHE_TTL) {
            $hit = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($hit) && isset($hit['serviceable'])) {
                return ['serviceable' => (bool) $hit['serviceable'], 'eta_days' => isset($hit['eta_days']) ? (int) $hit['eta_days'] : null];
            }
        }

        // Prepaid only (cod=0); 0.5 kg is the smallest slab, enough to ask "is this pincode served?".
        $res = ShiprocketClient::get('/courier/serviceability/', [
            'pickup_postcode' => $pickup, 'delivery_postcode' => $pin, 'cod' => 0, 'weight' => 0.5,
        ]);
        $couriers = $res['data']['available_courier_companies'] ?? null;
        if (is_array($couriers) && $couriers !== []) {
            $days = array_filter(array_map(static fn ($c) => (int) ($c['estimated_delivery_days'] ?? 0), $couriers), static fn ($d) => $d > 0);
            $out = ['serviceable' => true, 'eta_days' => $days ? min($days) : null];
        } elseif ((int) ($res['_http'] ?? 0) === 404 || (int) ($res['status'] ?? 0) === 404 || (is_array($couriers) && !isset($res['_error']))) {
            // No courier for this pair (Shiprocket answers 404 / an empty list). VERIFY WITH PROVIDER.
            $out = ['serviceable' => false, 'eta_days' => null];
        } else {
            if (isset($res['_error'])) {
                error_log('[PincodeService] serviceability ' . $pickup . '→' . $pin . ': ' . $res['_error']);
            }

            return $unknown;   // login/network/other error: don't block checkout, don't cache
        }
        $dir = dirname($cacheFile);
        if (is_dir($dir) || @mkdir($dir, 0755, true)) {
            @file_put_contents($cacheFile, json_encode($out), LOCK_EX);
        }

        return $out;
    }

    /** Store setting store_default_pickup_pincode, else the newest default pickup address of an approved seller. */
    private static function pickupPincode(): ?string
    {
        $pin = preg_replace('/\D/', '', StoreSettings::get('store_default_pickup_pincode')) ?? '';
        if (preg_match('/^[1-9]\d{5}$/', $pin)) {
            return $pin;
        }
        try {
            $pin = (string) Database::connection()->query(
                "SELECT a.pincode FROM store_vendor_addresses a JOIN store_vendors v ON v.id = a.vendor_id
                  WHERE a.type = 'pickup' AND a.is_active = 1 AND v.status = 'approved'
                  ORDER BY a.is_default DESC, a.id DESC LIMIT 1"
            )->fetchColumn();
        } catch (\Throwable) {
            return null;
        }

        return preg_match('/^[1-9]\d{5}$/', $pin) ? $pin : null;
    }

    /** Repo root (app/app/Services/Store → 4 levels up), where assets/ and storage/ live. */
    private static function root(): string
    {
        return dirname(__DIR__, 4);
    }
}
