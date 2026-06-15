<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Http\Request;

final class SessionService
{
    public static function create(int $userId, string $refreshToken, Request $request): int
    {
        $hash = hash('sha256', $refreshToken);
        $days = (int) ($_ENV['JWT_REFRESH_TTL_DAYS'] ?? 30);
        $expires = date('Y-m-d H:i:s', time() + ($days * 86400));

        return QueryBuilder::table('user_sessions')->insert([
            'user_id' => $userId,
            'refresh_token_hash' => $hash,
            'device_label' => self::deviceLabel($request),
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->header('User-Agent', '') ?? '', 0, 255),
            'is_current' => 1,
            'last_active_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expires,
        ]);
    }

    /**
     * How long a just-rotated refresh token stays valid so parallel requests
     * that raced past the access-token expiry can still authenticate.
     */
    private const ROTATION_GRACE_SECONDS = 30;

    public static function findByRefreshToken(string $refreshToken): ?array
    {
        $hash = hash('sha256', $refreshToken);
        $now = date('Y-m-d H:i:s');

        $row = QueryBuilder::table('user_sessions')
            ->where('refresh_token_hash', '=', $hash)
            ->where('expires_at', '>', $now)
            ->first();
        if ($row !== null) {
            return $row;
        }

        // Grace match: this token was rotated moments ago by a concurrent
        // request. Accept it for a short window so the user isn't logged out
        // mid-burst. Guarded — the prev_* columns require migration 025.
        try {
            $grace = QueryBuilder::table('user_sessions')
                ->where('prev_refresh_token_hash', '=', $hash)
                ->where('prev_token_expires_at', '>', $now)
                ->first();

            return $grace ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function touch(int $sessionId): void
    {
        QueryBuilder::table('user_sessions')->where('id', '=', $sessionId)->update([
            'last_active_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function rotateRefreshToken(int $sessionId, string $newToken): void
    {
        $days = (int) ($_ENV['JWT_REFRESH_TTL_DAYS'] ?? 30);

        $update = [
            'refresh_token_hash' => hash('sha256', $newToken),
            'last_active_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + ($days * 86400)),
        ];

        // Stash the outgoing token as the grace token (valid a few more seconds)
        // so concurrent requests still authenticate. Guarded for pre-025 DBs.
        try {
            $current = QueryBuilder::table('user_sessions')->where('id', '=', $sessionId)->first();
            if ($current !== null && !empty($current['refresh_token_hash'])) {
                $update['prev_refresh_token_hash'] = $current['refresh_token_hash'];
                $update['prev_token_expires_at'] = date('Y-m-d H:i:s', time() + self::ROTATION_GRACE_SECONDS);
            }
            QueryBuilder::table('user_sessions')->where('id', '=', $sessionId)->update($update);
        } catch (\Throwable $e) {
            // prev_* columns missing (migration 025 not applied) — rotate plainly.
            unset($update['prev_refresh_token_hash'], $update['prev_token_expires_at']);
            QueryBuilder::table('user_sessions')->where('id', '=', $sessionId)->update($update);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function listForUser(int $userId, ?string $currentRefresh = null): array
    {
        $currentHash = $currentRefresh !== null ? hash('sha256', $currentRefresh) : null;
        $rows = QueryBuilder::table('user_sessions')
            ->where('user_id', '=', $userId)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->orderBy('last_active_at', 'DESC')
            ->get();

        foreach ($rows as &$row) {
            $row['is_current'] = $currentHash !== null && $row['refresh_token_hash'] === $currentHash;
        }

        return $rows;
    }

    public static function revokeByRefreshToken(?string $refreshToken): void
    {
        if ($refreshToken === null || $refreshToken === '') {
            return;
        }

        QueryBuilder::table('user_sessions')
            ->where('refresh_token_hash', '=', hash('sha256', $refreshToken))
            ->delete();
    }

    public static function revoke(int $sessionId, int $userId): bool
    {
        $row = QueryBuilder::table('user_sessions')
            ->where('id', '=', $sessionId)
            ->where('user_id', '=', $userId)
            ->first();

        if ($row === null) {
            return false;
        }

        QueryBuilder::table('user_sessions')->where('id', '=', $sessionId)->delete();

        return true;
    }

    public static function revokeAllExcept(int $userId, ?string $currentRefresh): int
    {
        $query = QueryBuilder::table('user_sessions')->where('user_id', '=', $userId);
        if ($currentRefresh !== null && $currentRefresh !== '') {
            $query->where('refresh_token_hash', '!=', hash('sha256', $currentRefresh));
        }

        $count = $query->count();
        $query->delete();

        return $count;
    }

    public static function revokeAllForUser(int $userId): void
    {
        QueryBuilder::table('user_sessions')->where('user_id', '=', $userId)->delete();
        QueryBuilder::table('users')->where('id', '=', $userId)->update(['remember_token' => null]);
    }

    private static function deviceLabel(Request $request): string
    {
        $ua = strtolower($request->header('User-Agent', '') ?? '');
        $browser = 'Browser';
        if (str_contains($ua, 'chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'safari')) {
            $browser = 'Safari';
        } elseif (str_contains($ua, 'edge')) {
            $browser = 'Edge';
        }

        $os = 'Unknown OS';
        if (str_contains($ua, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'mac')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'linux')) {
            $os = 'Linux';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
            $os = 'iOS';
        }

        return "{$browser} on {$os}";
    }
}
