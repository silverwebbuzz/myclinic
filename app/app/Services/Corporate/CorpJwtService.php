<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Company-portal session token: its own cookie (mc_corp_token, path /company),
 * separate from clinic users, platform admins, partners and sellers.
 * The token only identifies a corp_sessions row (jti); that row decides whether
 * the session is still valid (revoked / idle / expired), so logout and
 * "sign out other devices" take effect immediately.
 */
final class CorpJwtService
{
    public const COOKIE = 'mc_corp_token';
    private const SCOPE = 'corp';

    public static function issue(int $userId, int $companyId, string $jti): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $userId,
            'cid' => $companyId,
            'jti' => $jti,
            'scope' => self::SCOPE,
            'iat' => $now,
            'exp' => $now + self::ttlSeconds(),
        ], self::secret(), 'HS256');
    }

    /** @return array<string, mixed>|null */
    public static function decode(string $token): ?array
    {
        try {
            $payload = (array) JWT::decode($token, new Key(self::secret(), 'HS256'));

            return ($payload['scope'] ?? '') === self::SCOPE ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setCookie(string $token): void
    {
        setcookie(self::COOKIE, $token, [
            'expires' => time() + self::ttlSeconds(),
            'path' => '/company',
            'secure' => ($_ENV['APP_ENV'] ?? 'local') !== 'local',
            'httponly' => true,
            'samesite' => 'Lax',   // Lax so links from emails land signed in
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/company',
            'secure' => ($_ENV['APP_ENV'] ?? 'local') !== 'local',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Absolute session lifetime (default 12 h). */
    public static function ttlSeconds(): int
    {
        return max(1, (int) ($_ENV['CORP_SESSION_HOURS'] ?? 12)) * 3600;
    }

    private static function secret(): string
    {
        // No hard-coded fallback: a missing secret would make tokens forgeable.
        $secret = (string) ($_ENV['CORP_JWT_SECRET'] ?? $_ENV['JWT_SECRET'] ?? '');
        if (strlen($secret) < 16) {
            throw new \RuntimeException('CORP_JWT_SECRET / JWT_SECRET must be set (32+ chars recommended).');
        }

        return $secret;
    }
}
