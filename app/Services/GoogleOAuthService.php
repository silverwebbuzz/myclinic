<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use League\OAuth2\Client\Provider\Google;

final class GoogleOAuthService
{
    public static function isConfigured(): bool
    {
        return !empty($_ENV['GOOGLE_CLIENT_ID']) && !empty($_ENV['GOOGLE_CLIENT_SECRET']);
    }

    public static function provider(): Google
    {
        $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');

        return new Google([
            'clientId' => $_ENV['GOOGLE_CLIENT_ID'],
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
            'redirectUri' => $base . '/auth/google/callback',
        ]);
    }

    public static function authorizationUrl(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $provider = self::provider();
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        return $provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
            'state' => $state,
        ]);
    }

    /** @return array{google_id: string, email: string, name: string}|null */
    public static function fetchUserFromCallback(string $code, string $state): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
            return null;
        }
        unset($_SESSION['oauth_state']);

        try {
            $token = self::provider()->getAccessToken('authorization_code', ['code' => $code]);
            $owner = self::provider()->getResourceOwner($token);

            return [
                'google_id' => (string) $owner->getId(),
                'email' => strtolower((string) $owner->getEmail()),
                'name' => (string) ($owner->getName() ?: $owner->getEmail()),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findUserByGoogleId(string $googleId): ?array
    {
        return QueryBuilder::table('users')
            ->where('google_id', '=', $googleId)
            ->where('is_active', '=', 1)
            ->first();
    }

    public static function linkGoogleAccount(int $userId, string $googleId): void
    {
        QueryBuilder::table('users')->where('id', '=', $userId)->update([
            'google_id' => $googleId,
        ]);
    }

    public static function storePendingRegistration(array $profile): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['google_register'] = $profile;
    }

    /** @return array{google_id: string, email: string, name: string}|null */
    public static function pendingRegistration(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['google_register'] ?? null;
    }

    public static function clearPendingRegistration(): void
    {
        unset($_SESSION['google_register']);
    }
}
