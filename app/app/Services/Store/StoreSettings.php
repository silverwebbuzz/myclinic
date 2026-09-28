<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Store settings live in platform_settings under `store_*` keys (seeded by
 * 2026_09_28_store_foundation.sql). Read once per request.
 */
final class StoreSettings
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? (string) $all[$key] : $default;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key, '');

        return $v === '' ? $default : (int) $v;
    }

    public static function enabled(): bool
    {
        return self::get('store_enabled', '0') === '1';
    }

    public static function set(string $key, string $value, bool $secret = false): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, is_secret)
             VALUES (:k, :v, :s)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 's' => $secret ? 1 : 0]);
        self::$cache = null;
    }

    /** @return array<string, string> */
    private static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            $rows = Database::connection()
                ->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'store\\_%'")
                ->fetchAll();
            foreach ($rows as $r) {
                self::$cache[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
            }
        } catch (\Throwable) {
            // Table/rows missing (patch not run yet) — callers fall back to defaults.
        }

        return self::$cache;
    }
}
