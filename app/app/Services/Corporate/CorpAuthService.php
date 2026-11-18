<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Sign-in, lockout, password reset and email verification for company users.
 * All times UTC. Error messages never reveal whether an email has an account.
 */
final class CorpAuthService
{
    public const MAX_FAILED_LOGINS = 5;
    public const LOCK_MINUTES = 15;
    private const RESET_MINUTES = 60;
    private const VERIFY_HOURS = 48;
    private const MAX_RESETS_PER_HOUR = 3;

    /**
     * @return array{ok: bool, error?: string, user?: array<string, mixed>, company?: array<string, mixed>}
     */
    public static function attemptLogin(string $email, string $password): array
    {
        $generic = ['ok' => false, 'error' => 'Invalid email or password.'];
        $user = CompanyService::findUserByEmail($email);
        if ($user === null || $user['password_hash'] === null || $user['password_hash'] === '') {
            // Same hashing time as a real check, so timing doesn't reveal accounts.
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');

            return $generic;
        }
        $now = time();
        if (!empty($user['locked_until']) && strtotime($user['locked_until'] . ' UTC') > $now) {
            return ['ok' => false, 'error' => 'Too many failed attempts. Please try again in ' . self::LOCK_MINUTES . ' minutes.'];
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            $fails = (int) $user['failed_logins'] + 1;
            $locked = $fails >= self::MAX_FAILED_LOGINS;
            Database::connection()->prepare('UPDATE corp_users SET failed_logins = :f, locked_until = :l WHERE id = :id')->execute([
                'f' => $locked ? 0 : $fails,
                'l' => $locked ? LocalDate::utcPlusSeconds(self::LOCK_MINUTES * 60) : $user['locked_until'],
                'id' => (int) $user['id'],
            ]);
            CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], $locked ? 'login_locked' : 'login_failed');

            return $generic;
        }
        if (($user['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'This login has been disabled. Please contact your company administrator.'];
        }
        $company = CompanyService::find((int) $user['company_id']);
        $blocked = self::companyBlockMessage($company);
        if ($blocked !== null) {
            return ['ok' => false, 'error' => $blocked];
        }

        Database::connection()->prepare('UPDATE corp_users SET failed_logins = 0, locked_until = NULL, last_login_at = :last WHERE id = :id')
            ->execute(['last' => LocalDate::nowUtc(), 'id' => (int) $user['id']]);
        if (password_needs_rehash((string) $user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            Database::connection()->prepare('UPDATE corp_users SET password_hash = :hash WHERE id = :id')
                ->execute(['hash' => CorpPasswordPolicy::hash($password), 'id' => (int) $user['id']]);
        }
        CorpAudit::log((int) $company['id'], 'corp_user', (int) $user['id'], 'login');

        return ['ok' => true, 'user' => $user, 'company' => $company];
    }

    /**
     * Why this company can't use the portal, or null when it can.
     * Pending companies CAN sign in (to finish their profile, verify email).
     *
     * @param array<string, mixed>|null $company
     */
    public static function companyBlockMessage(?array $company): ?string
    {
        return match ($company['status'] ?? null) {
            'pending', 'active' => null,
            'suspended' => 'Your company account is suspended. Please contact eClinicPro support.',
            'rejected' => 'Your company registration was not approved. Please contact eClinicPro support.',
            'closed' => 'This company account has been closed.',
            default => 'Your company account was not found. Please contact eClinicPro support.',
        };
    }

    // ------------------------------------------------------------------
    // Password reset
    // ------------------------------------------------------------------

    /** Emails a reset link if the account exists. Silent otherwise. */
    public static function startPasswordReset(string $email): void
    {
        $user = CompanyService::findUserByEmail($email);
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return;
        }
        $company = CompanyService::find((int) $user['company_id']);
        if (self::companyBlockMessage($company) !== null) {
            return;
        }
        try {
            if (self::recentTokens((int) $user['id'], 'reset', 3600) >= self::MAX_RESETS_PER_HOUR) {
                return;
            }
            $token = self::createToken((int) $user['id'], 'reset', self::RESET_MINUTES * 60);
        } catch (\Throwable $e) {
            error_log('[CorpAuthService::startPasswordReset] ' . $e->getMessage());

            return;
        }
        CorpMailer::send(
            (string) $user['email'],
            'Reset your eClinicPro company password',
            'Reset your password',
            [
                'Hi ' . self::firstName((string) $user['name']) . ',',
                'We received a request to reset the password for your ' . $company['legal_name'] . ' account. The link below works once and expires in ' . self::RESET_MINUTES . ' minutes.',
                'If you didn\'t ask for this, you can ignore this email. Your password stays the same.',
            ],
            CorpMailer::appUrl() . '/company/reset-password?token=' . $token,
            'Choose a new password',
        );
        CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], 'password_reset_requested');
    }

    /** @return array<string, mixed>|null the reset row for a still-valid token */
    public static function findToken(string $token, string $purpose): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        try {
            $st = Database::connection()->prepare(
                'SELECT * FROM corp_password_resets
                  WHERE token_hash = :h AND purpose = :p AND used_at IS NULL AND expires_at > :now LIMIT 1'
            );
            $st->execute(['h' => hash('sha256', $token), 'p' => $purpose, 'now' => LocalDate::nowUtc()]);

            return $st->fetch() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{ok: bool, error?: string} */
    public static function completePasswordReset(string $token, string $password, string $confirm): array
    {
        $row = self::findToken($token, 'reset');
        if ($row === null) {
            return ['ok' => false, 'error' => 'This reset link has expired or was already used. Please request a new one.'];
        }
        $user = CompanyService::findUser((int) $row['user_id']);
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'This login is not active.'];
        }
        $policy = CorpPasswordPolicy::check($password, $confirm, (string) $user['email'], (string) $user['name']);
        if ($policy !== null) {
            return ['ok' => false, 'error' => $policy];
        }

        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Claim the token first (guarded) so two tabs can't both use it.
            $claim = $pdo->prepare('UPDATE corp_password_resets SET used_at = :t WHERE id = :id AND used_at IS NULL');
            $claim->execute(['t' => $now, 'id' => (int) $row['id']]);
            if ($claim->rowCount() !== 1) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'This reset link was already used. Please request a new one.'];
            }
            $pdo->prepare(
                'UPDATE corp_users SET password_hash = :h, failed_logins = 0, locked_until = NULL, updated_at = :t WHERE id = :id'
            )->execute(['h' => CorpPasswordPolicy::hash($password), 't' => $now, 'id' => (int) $user['id']]);
            // Any other outstanding reset links stop working too.
            $pdo->prepare("UPDATE corp_password_resets SET used_at = :t WHERE user_id = :u AND purpose = 'reset' AND used_at IS NULL")
                ->execute(['t' => $now, 'u' => (int) $user['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[CorpAuthService::completePasswordReset] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not update your password. Please try again.'];
        }
        // A reset means the old password may be known: sign out every device.
        CorpSessionService::revokeAllForUser((int) $user['id']);
        CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], 'password_reset');

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Email verification
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $user */
    public static function sendVerifyEmail(array $user): bool
    {
        if (!empty($user['email_verified_at'])) {
            return false;
        }
        try {
            if (self::recentTokens((int) $user['id'], 'verify_email', 3600) >= self::MAX_RESETS_PER_HOUR) {
                return false;
            }
            $token = self::createToken((int) $user['id'], 'verify_email', self::VERIFY_HOURS * 3600);
        } catch (\Throwable $e) {
            error_log('[CorpAuthService::sendVerifyEmail] ' . $e->getMessage());

            return false;
        }

        return CorpMailer::send(
            (string) $user['email'],
            'Confirm your email for eClinicPro Workforce Health',
            'Confirm your email address',
            [
                'Hi ' . self::firstName((string) $user['name']) . ',',
                'Thanks for registering your company on eClinicPro Workforce Health. Please confirm this is your email address. The link expires in ' . self::VERIFY_HOURS . ' hours.',
                'We\'re reviewing your company details and will email you once your account is approved.',
            ],
            CorpMailer::appUrl() . '/company/verify-email?token=' . $token,
            'Confirm email',
        );
    }

    /** @return array{ok: bool, error?: string} */
    public static function verifyEmail(string $token): array
    {
        $row = self::findToken($token, 'verify_email');
        if ($row === null) {
            return ['ok' => false, 'error' => 'This link has expired or was already used. Sign in and request a new one from your dashboard.'];
        }
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $claim = $pdo->prepare('UPDATE corp_password_resets SET used_at = :t WHERE id = :id AND used_at IS NULL');
        $claim->execute(['t' => $now, 'id' => (int) $row['id']]);
        if ($claim->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'This link was already used.'];
        }
        $pdo->prepare('UPDATE corp_users SET email_verified_at = COALESCE(email_verified_at, :t), updated_at = :t2 WHERE id = :id')
            ->execute(['t' => $now, 't2' => $now, 'id' => (int) $row['user_id']]);
        $user = CompanyService::findUser((int) $row['user_id']);
        if ($user !== null) {
            CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], 'email_verified');
        }

        return ['ok' => true];
    }

    // ------------------------------------------------------------------

    private static function createToken(int $userId, string $purpose, int $ttlSeconds): string
    {
        $token = bin2hex(random_bytes(32));
        Database::connection()->prepare(
            'INSERT INTO corp_password_resets (user_id, purpose, token_hash, expires_at, created_at)
             VALUES (:u, :p, :h, :e, :c)'
        )->execute([
            'u' => $userId,
            'p' => $purpose,
            'h' => hash('sha256', $token),
            'e' => LocalDate::utcPlusSeconds($ttlSeconds),
            'c' => LocalDate::nowUtc(),
        ]);

        return $token;
    }

    private static function recentTokens(int $userId, string $purpose, int $withinSeconds): int
    {
        $st = Database::connection()->prepare(
            'SELECT COUNT(*) FROM corp_password_resets WHERE user_id = :u AND purpose = :p AND created_at >= :since'
        );
        $st->execute(['u' => $userId, 'p' => $purpose, 'since' => LocalDate::utcPlusSeconds(-$withinSeconds)]);

        return (int) $st->fetchColumn();
    }

    private static function firstName(string $name): string
    {
        $first = trim((string) strtok(trim($name), ' '));

        return $first !== '' ? $first : 'there';
    }
}
