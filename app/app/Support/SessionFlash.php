<?php

declare(strict_types=1);

namespace App\Support;

final class SessionFlash
{
    public static function put(string $key, mixed $value): void
    {
        self::ensureSession();
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pull(string $key): mixed
    {
        self::ensureSession();
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
