<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * WordPress blog bridge — credentials from platform_settings (admin UI)
 * with .env fallback for local/dev.
 */
final class WordPressSettings
{
    public const MASK = '••••••';

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /** @var array<string, bool> */
    private const SECRET_KEYS = [
        'wordpress_api_app_password' => true,
        'wordpress_bridge_secret' => true,
    ];

    /** @var array<string, string> env fallback map */
    private const ENV_MAP = [
        'wordpress_api_url' => 'WORDPRESS_API_URL',
        'wordpress_site_url' => 'WORDPRESS_SITE_URL',
        'wordpress_api_user' => 'WORDPRESS_API_USER',
        'wordpress_api_app_password' => 'WORDPRESS_API_APP_PASSWORD',
        'wordpress_bridge_secret' => 'WORDPRESS_BRIDGE_SECRET',
    ];

    public static function apiBaseUrl(): string
    {
        return rtrim(self::get('wordpress_api_url'), '/');
    }

    public static function siteUrl(): string
    {
        $url = rtrim(self::get('wordpress_site_url'), '/');
        if ($url !== '') {
            return $url;
        }

        $api = self::apiBaseUrl();
        if ($api === '') {
            return '';
        }

        return (string) preg_replace('#/wp-json.*$#', '', $api);
    }

    public static function apiUser(): string
    {
        return self::get('wordpress_api_user');
    }

    public static function apiAppPassword(): string
    {
        // WP displays app passwords with spaces; strip them for Basic auth.
        $pass = self::get('wordpress_api_app_password');

        return str_replace(' ', '', $pass);
    }

    public static function bridgeSecret(): string
    {
        return self::get('wordpress_bridge_secret');
    }

    public static function isConfigured(): bool
    {
        return self::apiBaseUrl() !== ''
            && self::apiUser() !== ''
            && self::apiAppPassword() !== '';
    }

    /** @return array<string, array{setting_value: ?string, is_secret: int}> */
    public static function allForAdmin(): array
    {
        $rows = [];
        try {
            $keys = array_keys(self::ENV_MAP);
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = Database::connection()->prepare(
                "SELECT setting_key, setting_value, is_secret
                 FROM platform_settings
                 WHERE setting_key IN ({$placeholders})"
            );
            $stmt->execute($keys);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[$r['setting_key']] = $r;
            }
        } catch (\Throwable $e) {
            // platform_settings may not exist yet.
        }

        foreach (self::ENV_MAP as $key => $envKey) {
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'setting_value' => trim((string) ($_ENV[$envKey] ?? '')) ?: null,
                    'is_secret' => isset(self::SECRET_KEYS[$key]) ? 1 : 0,
                ];
            }
        }

        return $rows;
    }

    public static function displayValue(string $key, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (isset(self::SECRET_KEYS[$key])) {
            return self::MASK;
        }

        return $value;
    }

    /** @param array<string, string> $post */
    public static function saveFromAdmin(array $post): void
    {
        foreach (self::ENV_MAP as $key => $_env) {
            if (!array_key_exists($key, $post)) {
                continue;
            }
            $val = trim($post[$key]);
            if (isset(self::SECRET_KEYS[$key]) && $val === self::MASK) {
                continue;
            }
            self::set($key, $val === '' ? null : $val);
        }
        self::$cache = null;
    }

    public static function set(string $key, ?string $value): void
    {
        $isSecret = isset(self::SECRET_KEYS[$key]) ? 1 : 0;
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, is_secret)
             VALUES (:k, :v, :s)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret)'
        );
        $stmt->execute([
            ':k' => $key,
            ':v' => $value,
            ':s' => $isSecret,
        ]);
        self::$cache = null;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    private static function get(string $key): string
    {
        $all = self::loadAll();
        $val = trim($all[$key] ?? '');

        if ($val !== '') {
            return $val;
        }

        $envKey = self::ENV_MAP[$key] ?? '';

        return trim((string) ($_ENV[$envKey] ?? ''));
    }

    /** @return array<string, string> */
    private static function loadAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $map = [];
        try {
            $keys = array_keys(self::ENV_MAP);
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = Database::connection()->prepare(
                "SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ({$placeholders})"
            );
            $stmt->execute($keys);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ($r['setting_value'] !== null && $r['setting_value'] !== '') {
                    $map[$r['setting_key']] = (string) $r['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            // fall through to env
        }

        return self::$cache = $map;
    }
}
