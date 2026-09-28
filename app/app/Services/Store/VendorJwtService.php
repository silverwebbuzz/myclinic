<?php

declare(strict_types=1);

namespace App\Services\Store;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Vendor session token — its own guard/cookie (mc_vendor_token, path /vendor),
 * separate from clinic users, platform admins and partners. Mirrors PartnerJwtService.
 */
final class VendorJwtService
{
    public const COOKIE = 'mc_vendor_token';

    public static function issue(int $vendorUserId, int $vendorId): string
    {
        $payload = [
            'sub' => $vendorUserId,
            'vid' => $vendorId,
            'scope' => 'vendor',
            'iat' => time(),
            'exp' => time() + (self::ttlMinutes() * 60),
        ];

        return JWT::encode($payload, self::secret(), 'HS256');
    }

    /** @return array<string, mixed>|null */
    public static function decode(string $token): ?array
    {
        try {
            $payload = (array) JWT::decode($token, new Key(self::secret(), 'HS256'));

            return ($payload['scope'] ?? '') === 'vendor' ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setCookie(string $token): void
    {
        setcookie(self::COOKIE, $token, [
            'expires' => time() + (self::ttlMinutes() * 60),
            'path' => '/vendor',
            'secure' => ($_ENV['APP_ENV'] ?? 'local') !== 'local',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/vendor']);
    }

    private static function ttlMinutes(): int
    {
        return (int) ($_ENV['VENDOR_JWT_TTL_MINUTES'] ?? 480);
    }

    private static function secret(): string
    {
        // No hard-coded fallback: a missing secret would make tokens forgeable.
        $secret = (string) ($_ENV['VENDOR_JWT_SECRET'] ?? $_ENV['JWT_SECRET'] ?? '');
        if (strlen($secret) < 16) {
            throw new \RuntimeException('VENDOR_JWT_SECRET / JWT_SECRET must be set (32+ chars recommended).');
        }

        return $secret;
    }
}
