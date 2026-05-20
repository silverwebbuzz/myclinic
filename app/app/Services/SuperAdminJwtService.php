<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class SuperAdminJwtService
{
    public static function issue(int $adminId, string $email): string
    {
        $ttl = (int) ($_ENV['SUPERADMIN_JWT_TTL_MINUTES'] ?? 480);
        $payload = [
            'sub' => $adminId,
            'email' => $email,
            'scope' => 'superadmin',
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
            if (($payload['scope'] ?? '') !== 'superadmin') {
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
        $ttl = (int) ($_ENV['SUPERADMIN_JWT_TTL_MINUTES'] ?? 480);
        setcookie('mc_sa_token', $token, [
            'expires' => time() + ($ttl * 60),
            'path' => '/admin',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie('mc_sa_token', '', ['expires' => time() - 3600, 'path' => '/admin']);
    }

    private static function secret(): string
    {
        return $_ENV['SUPERADMIN_JWT_SECRET']
            ?? $_ENV['JWT_SECRET']
            ?? 'dev-superadmin-secret-change-in-production-min-32';
    }
}
