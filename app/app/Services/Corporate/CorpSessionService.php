<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * One corp_sessions row per sign-in. Gives us: a device list, logout that
 * really ends the session, "sign out everywhere" after a password reset, an
 * idle timeout and an absolute timeout.
 */
final class CorpSessionService
{
    /** Only write last_seen_at when it is older than this (saves a write per request). */
    private const TOUCH_EVERY_SECONDS = 300;

    public static function idleSeconds(): int
    {
        return max(5, (int) ($_ENV['CORP_IDLE_MINUTES'] ?? 30)) * 60;
    }

    /** Start a session and set the cookie. Returns the jti. */
    public static function start(int $companyId, int $userId, bool $mfaPassed = false): string
    {
        $jti = bin2hex(random_bytes(16));
        $now = LocalDate::nowUtc();
        $stmt = Database::connection()->prepare(
            'INSERT INTO corp_sessions (company_id, user_id, jti, mfa_passed, ip, user_agent, created_at, last_seen_at, expires_at)
             VALUES (:company_id, :user_id, :jti, :mfa, :ip, :ua, :created_at, :last_seen_at, :expires_at)'
        );
        $stmt->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
            'jti' => $jti,
            'mfa' => $mfaPassed ? 1 : 0,
            'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            'created_at' => $now,
            'last_seen_at' => $now,
            'expires_at' => LocalDate::utcPlusSeconds(CorpJwtService::ttlSeconds()),
        ]);
        CorpJwtService::setCookie(CorpJwtService::issue($userId, $companyId, $jti));

        return $jti;
    }

    /**
     * The live session for this jti + user, or null if revoked, expired or idle
     * too long. Refreshes last_seen_at (throttled).
     *
     * @return array<string, mixed>|null
     */
    public static function validate(string $jti, int $userId): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $jti) !== 1) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT * FROM corp_sessions WHERE jti = :jti AND user_id = :uid AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute(['jti' => $jti, 'uid' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $now = time();
        $expires = strtotime($row['expires_at'] . ' UTC');
        $lastSeen = strtotime($row['last_seen_at'] . ' UTC');
        if ($expires === false || $lastSeen === false || $expires <= $now || ($now - $lastSeen) > self::idleSeconds()) {
            self::revoke($jti);

            return null;
        }

        if (($now - $lastSeen) >= self::TOUCH_EVERY_SECONDS) {
            Database::connection()->prepare('UPDATE corp_sessions SET last_seen_at = :t WHERE id = :id')
                ->execute(['t' => LocalDate::nowUtc(), 'id' => (int) $row['id']]);
        }

        return $row;
    }

    public static function revoke(string $jti): void
    {
        Database::connection()->prepare('UPDATE corp_sessions SET revoked_at = :t WHERE jti = :jti AND revoked_at IS NULL')
            ->execute(['t' => LocalDate::nowUtc(), 'jti' => $jti]);
    }

    /** End every session of a user, optionally keeping one (the current device). */
    public static function revokeAllForUser(int $userId, ?string $exceptJti = null): int
    {
        $sql = 'UPDATE corp_sessions SET revoked_at = :t WHERE user_id = :uid AND revoked_at IS NULL';
        $params = ['t' => LocalDate::nowUtc(), 'uid' => $userId];
        if ($exceptJti !== null) {
            $sql .= ' AND jti <> :keep';
            $params['keep'] = $exceptJti;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /** End every session of every user of a company (suspension). */
    public static function revokeAllForCompany(int $companyId): int
    {
        $stmt = Database::connection()->prepare(
            'UPDATE corp_sessions SET revoked_at = :t WHERE company_id = :cid AND revoked_at IS NULL'
        );
        $stmt->execute(['t' => LocalDate::nowUtc(), 'cid' => $companyId]);

        return $stmt->rowCount();
    }

    public static function markMfaPassed(string $jti): void
    {
        Database::connection()->prepare('UPDATE corp_sessions SET mfa_passed = 1 WHERE jti = :jti')
            ->execute(['jti' => $jti]);
    }
}
