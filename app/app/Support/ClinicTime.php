<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SlotService;
use PDO;

/**
 * Single source of truth for clinic wall-clock time (India / IST by default).
 * Bootstraps PHP's default timezone and aligns MySQL session time on connect.
 */
final class ClinicTime
{
    public const DEFAULT_ZONE = 'Asia/Kolkata';

    private static bool $booted = false;

    public static function bootstrap(): void
    {
        if (self::$booted) {
            return;
        }

        $zone = self::zone();
        if (@date_default_timezone_set($zone) === false) {
            date_default_timezone_set(self::DEFAULT_ZONE);
        }

        self::$booted = true;
    }

    public static function zone(): string
    {
        $fromEnv = $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        return self::DEFAULT_ZONE;
    }

    public static function clinicZone(int $clinicId): string
    {
        try {
            return SlotService::clinicTimezone($clinicId);
        } catch (\Throwable) {
            return self::zone();
        }
    }

    public static function now(?string $zone = null): \DateTimeImmutable
    {
        self::bootstrap();

        return new \DateTimeImmutable('now', new \DateTimeZone($zone ?? self::zone()));
    }

    public static function today(?string $zone = null): string
    {
        return self::now($zone)->format('Y-m-d');
    }

    /** Current local time as H:i:s (staff clock-in/out, logs). */
    public static function time(?string $zone = null): string
    {
        return self::now($zone)->format('H:i:s');
    }

    public static function mysqlOffset(?string $zone = null): string
    {
        return self::now($zone ?? self::zone())->format('P');
    }

    public static function applyMysqlSession(PDO $pdo, ?string $zone = null): void
    {
        $offset = self::mysqlOffset($zone);
        $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
    }

    /** Format a MySQL TIME/DATETIME value for UI (e.g. 01:19 PM). */
    public static function formatTime12(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        $normalized = self::normalizeTimeString($value);
        if ($normalized === null) {
            return '—';
        }

        self::bootstrap();
        $ts = strtotime('2000-01-01 ' . $normalized);

        return $ts !== false ? date('g:i A', $ts) : '—';
    }

    /** Seconds since midnight for a TIME value — for duration math. */
    public static function timeToSeconds(?string $value): int
    {
        $normalized = self::normalizeTimeString($value);
        if ($normalized === null) {
            return 0;
        }

        $parts = explode(':', $normalized);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);

        return $h * 3600 + $m * 60 + $s;
    }

    private static function normalizeTimeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // MySQL TIME column: "HH:MM:SS"
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value) === 1) {
            return strlen($value) === 5 ? $value . ':00' : $value;
        }

        // DATETIME: extract clock portion
        if (preg_match('/\d{1,2}:\d{2}(:\d{2})?$/', $value, $m)) {
            $clock = $m[0];

            return strlen($clock) === 5 ? $clock . ':00' : $clock;
        }

        return null;
    }
}
