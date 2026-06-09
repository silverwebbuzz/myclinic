<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Partner session token — a separate guard/cookie from clinic users and
 * platform admins. Mirrors SuperAdminJwtService.
 */
final class PartnerJwtService
{
    public static function issue(int $partnerId, string $email): string
    {
        $ttl = (int) ($_ENV['PARTNER_JWT_TTL_MINUTES'] ?? 480);
        $payload = [
            'sub' => $partnerId,
            'email' => $email,
            'scope' => 'partner',
            'iat' => time(),
            'exp' => time() + ($ttl * 60),
        ];

        return JWT::encode($payload, self::secret(), 'HS256');
    }

    public static function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), 'HS256'));
            $payload = (array) $decoded;
            if (($payload['scope'] ?? '') !== 'partner') {
                return null;
            }

            return $payload;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setCookie(string $token): void
    {
        $secure = ($_ENV['APP_ENV'] ?? 'local') !== 'local';
        $ttl = (int) ($_ENV['PARTNER_JWT_TTL_MINUTES'] ?? 480);
        setcookie('mc_partner_token', $token, [
            'expires' => time() + ($ttl * 60),
            'path' => '/partner',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie('mc_partner_token', '', ['expires' => time() - 3600, 'path' => '/partner']);
    }

    private static function secret(): string
    {
        return $_ENV['PARTNER_JWT_SECRET']
            ?? $_ENV['JWT_SECRET']
            ?? 'dev-partner-secret-change-in-production-min-32ch';
    }
}
