<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use Predis\Client;

final class RedisClient
{
    private static ?Client $client = null;

    private static bool $available = true;

    public static function connection(): ?Client
    {
        if (!self::$available) {
            return null;
        }

        if (self::$client === null) {
            try {
                self::$client = new Client([
                    'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                    'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
                    'password' => $_ENV['REDIS_PASSWORD'] ?: null,
                ]);
                self::$client->ping();
            } catch (\Throwable) {
                self::$available = false;
                self::$client = null;
            }
        }

        return self::$client;
    }

    public static function ping(): bool
    {
        $client = self::connection();

        return $client !== null;
    }

    public static function get(string $key): ?string
    {
        $client = self::connection();
        if ($client === null) {
            return self::fileFallbackGet($key);
        }

        $value = $client->get($key);

        return $value === null ? null : (string) $value;
    }

    public static function setex(string $key, int $ttl, string $value): void
    {
        $client = self::connection();
        if ($client === null) {
            self::fileFallbackSet($key, $ttl, $value);

            return;
        }

        $client->setex($key, $ttl, $value);
    }

    public static function del(string $key): void
    {
        $client = self::connection();
        if ($client === null) {
            self::fileFallbackDel($key);

            return;
        }

        $client->del([$key]);
    }

    private static function fileFallbackPath(string $key): string
    {
        $dir = Application::basePath() . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . '/' . hash('sha256', $key) . '.cache';
    }

    private static function fileFallbackGet(string $key): ?string
    {
        $path = self::fileFallbackPath($key);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || ($data['expires'] ?? 0) < time()) {
            @unlink($path);

            return null;
        }

        return $data['value'] ?? null;
    }

    private static function fileFallbackSet(string $key, int $ttl, string $value): void
    {
        file_put_contents(self::fileFallbackPath($key), json_encode([
            'expires' => time() + $ttl,
            'value' => $value,
        ]));
    }

    private static function fileFallbackDel(string $key): void
    {
        @unlink(self::fileFallbackPath($key));
    }
}
