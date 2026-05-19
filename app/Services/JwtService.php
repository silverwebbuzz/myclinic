<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public static function issue(array $user, int $clinicId): string
    {
        $ttl = (int) ($_ENV['JWT_TTL_MINUTES'] ?? 15);
        $payload = [
            'sub' => (int) $user['id'],
            'clinic_id' => $clinicId,
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + ($ttl * 60),
        ];

        return JWT::encode($payload, self::secret(), 'HS256');
    }

    public static function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), 'HS256'));

            return (array) $decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setAuthCookies(string $accessToken, ?string $refreshToken = null): void
    {
        $secure = ($_ENV['APP_ENV'] ?? 'local') !== 'local';
        setcookie('mc_token', $accessToken, [
            'expires' => time() + ((int) ($_ENV['JWT_TTL_MINUTES'] ?? 15) * 60),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        if ($refreshToken !== null) {
            $days = (int) ($_ENV['JWT_REFRESH_TTL_DAYS'] ?? 30);
            setcookie('mc_refresh', $refreshToken, [
                'expires' => time() + ($days * 86400),
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
    }

    public static function clearAuthCookies(): void
    {
        setcookie('mc_token', '', ['expires' => time() - 3600, 'path' => '/']);
        setcookie('mc_refresh', '', ['expires' => time() - 3600, 'path' => '/']);
    }

    private static function secret(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'dev-secret-change-in-production-min-32-chars';
    }
}
