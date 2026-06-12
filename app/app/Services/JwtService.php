<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    /**
     * Access-token lifetime. Product requirement: a doctor's login must last
     * a full working day (>= 10h), so the env value is floored at 600 minutes
     * — a stale JWT_TTL_MINUTES=15 in a server .env can't log doctors out
     * mid-consultation.
     */
    public static function ttlMinutes(): int
    {
        return max((int) ($_ENV['JWT_TTL_MINUTES'] ?? 720), 600);
    }

    public static function issue(array $user, int $clinicId): string
    {
        $ttl = self::ttlMinutes();
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
        // SameSite=Lax (not Strict): doctors arrive via links in WhatsApp /
        // email / Google — Strict drops the cookies on that first navigation
        // and the app looks logged-out even though the session is fine.
        setcookie('mc_token', $accessToken, [
            'expires' => time() + (self::ttlMinutes() * 60),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if ($refreshToken !== null) {
            $days = (int) ($_ENV['JWT_REFRESH_TTL_DAYS'] ?? 30);
            setcookie('mc_refresh', $refreshToken, [
                'expires' => time() + ($days * 86400),
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
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
