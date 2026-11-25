<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Two-step sign-in (authenticator app + backup codes) for company users.
 *
 * Rules:
 *  - A user with MFA turned on must pass the code once per session
 *    (corp_sessions.mfa_passed).
 *  - When platform_settings.corp_mfa_required = 1, a user without MFA is sent
 *    to set it up before anything else.
 *  - If no encryption key is configured, MFA can't be stored, so it is NOT
 *    enforced (logged loudly) rather than locking every company out.
 */
final class CorpMfaService
{
    public const RECOVERY_COUNT = 10;
    public const MAX_FAILED = 5;
    private const SETUP_KEY = 'corp_mfa_setup';
    private const FAIL_KEY = 'corp_mfa_fail';
    private const SETUP_TTL = 900;                       // a half-finished setup expires after 15 min
    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function isConfigured(): bool
    {
        return CorpCrypto::isConfigured() && function_exists('sodium_crypto_secretbox');
    }

    public static function required(): bool
    {
        if (CompanyService::setting('corp_mfa_required', '1') !== '1') {
            return false;
        }
        if (!self::isConfigured()) {
            error_log('[CorpMfaService] corp_mfa_required=1 but CORP_DATA_KEY/STORE_DATA_KEY is missing — MFA NOT enforced');

            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $user */
    public static function isEnabled(array $user): bool
    {
        return !empty($user['mfa_enabled_at']) && !empty($user['mfa_secret_enc']);
    }

    /**
     * Where this session must go before it can use the portal: 'verify',
     * 'setup', or null when it's fine.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $session corp_sessions row
     */
    public static function pendingStep(array $user, array $session): ?string
    {
        if (self::isEnabled($user)) {
            return (int) ($session['mfa_passed'] ?? 0) === 1 ? null : 'verify';
        }

        return self::required() ? 'setup' : null;
    }

    // ------------------------------------------------------------------
    // Setup
    // ------------------------------------------------------------------

    /** The secret being set up for this user (kept in the PHP session until confirmed). */
    public static function pendingSecret(int $userId, bool $createIfMissing = true): ?string
    {
        self::ensurePhpSession();
        $row = $_SESSION[self::SETUP_KEY][$userId] ?? null;
        if (is_array($row) && (time() - (int) $row['at']) < self::SETUP_TTL) {
            return (string) $row['secret'];
        }
        if (!$createIfMissing) {
            return null;
        }
        $secret = TotpService::generateSecret();
        $_SESSION[self::SETUP_KEY][$userId] = ['secret' => $secret, 'at' => time()];

        return $secret;
    }

    /**
     * Confirm the authenticator works, then save it.
     *
     * @param array<string, mixed> $user
     * @return array{ok: bool, error?: string, codes?: list<string>}
     */
    public static function confirmSetup(array $user, string $code, string $jti): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'Two-step sign-in is not available on this server yet. Please contact support.'];
        }
        $secret = self::pendingSecret((int) $user['id'], false);
        if ($secret === null) {
            return ['ok' => false, 'error' => 'The setup timed out. We\'ve made a new QR code; please scan it again.'];
        }
        $step = TotpService::verify($secret, $code);
        if ($step === null) {
            return ['ok' => false, 'error' => 'That code didn\'t match. Check the time on your phone is set automatically, and enter the newest code.'];
        }
        $codes = self::newRecoveryCodes();
        $now = LocalDate::nowUtc();
        Database::connection()->prepare(
            'UPDATE corp_users SET mfa_secret_enc = :s, mfa_enabled_at = :at, mfa_last_step = :step, mfa_recovery = :rec, updated_at = :up WHERE id = :id'
        )->execute([
            's' => CorpCrypto::encrypt($secret),
            'at' => $now,
            'step' => $step,
            'rec' => self::hashCodes($codes),
            'up' => $now,
            'id' => (int) $user['id'],
        ]);
        unset($_SESSION[self::SETUP_KEY][(int) $user['id']]);
        CorpSessionService::markMfaPassed($jti);
        // Other devices signed in before MFA existed must now pass it too.
        Database::connection()->prepare('UPDATE corp_sessions SET mfa_passed = 0 WHERE user_id = :uid AND jti <> :jti AND revoked_at IS NULL')
            ->execute(['uid' => (int) $user['id'], 'jti' => $jti]);
        CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], 'mfa_enabled');

        return ['ok' => true, 'codes' => $codes];
    }

    // ------------------------------------------------------------------
    // Sign-in challenge
    // ------------------------------------------------------------------

    /**
     * Check a 6-digit code or a backup code for this session.
     *
     * @param array<string, mixed> $user
     * @return array{ok: bool, error?: string, locked?: bool, used_recovery?: bool, recovery_left?: int}
     */
    public static function verifyChallenge(array $user, string $input, string $jti): array
    {
        if (self::failures($jti) >= self::MAX_FAILED) {
            return ['ok' => false, 'locked' => true, 'error' => 'Too many wrong codes. Please sign in again.'];
        }
        $input = trim($input);
        $digits = preg_replace('/\s+/', '', $input) ?? '';

        if (preg_match('/^\d{6}$/', $digits) === 1) {
            $secret = CorpCrypto::decrypt((string) ($user['mfa_secret_enc'] ?? ''));
            if ($secret === null) {
                error_log('[CorpMfaService] cannot decrypt MFA secret for corp_user ' . (int) $user['id']);

                return ['ok' => false, 'error' => 'We couldn\'t check your code. Please use a backup code or contact support.'];
            }
            $last = $user['mfa_last_step'] !== null ? (int) $user['mfa_last_step'] : null;
            $step = TotpService::verify($secret, $digits, $last);
            if ($step !== null) {
                // Guarded update: two tabs can't both spend the same code.
                $st = Database::connection()->prepare(
                    'UPDATE corp_users SET mfa_last_step = :step WHERE id = :id AND (mfa_last_step IS NULL OR mfa_last_step < :step2)'
                );
                $st->execute(['step' => $step, 'id' => (int) $user['id'], 'step2' => $step]);
                if ($st->rowCount() === 1) {
                    return self::passed($user, $jti, false, null);
                }
            }

            return self::failed($user, $jti, 'That code didn\'t match. Enter the newest code from your authenticator app.');
        }

        $normalised = self::normaliseRecovery($input);
        if ($normalised !== null) {
            $hashes = json_decode((string) ($user['mfa_recovery'] ?? '[]'), true) ?: [];
            foreach ($hashes as $i => $hash) {
                if (is_string($hash) && password_verify($normalised, $hash)) {
                    unset($hashes[$i]);
                    $left = array_values($hashes);
                    Database::connection()->prepare('UPDATE corp_users SET mfa_recovery = :rec WHERE id = :id')
                        ->execute(['rec' => json_encode($left), 'id' => (int) $user['id']]);

                    return self::passed($user, $jti, true, count($left));
                }
            }
        }

        return self::failed($user, $jti, 'That code didn\'t match. Enter the 6-digit code from your app, or one of your backup codes.');
    }

    // ------------------------------------------------------------------
    // Security page actions
    // ------------------------------------------------------------------

    /**
     * New set of backup codes (old ones stop working). Needs a current app code.
     *
     * @param array<string, mixed> $user
     * @return array{ok: bool, error?: string, codes?: list<string>}
     */
    public static function regenerateRecoveryCodes(array $user, string $code): array
    {
        if (!self::isEnabled($user)) {
            return ['ok' => false, 'error' => 'Two-step sign-in is not turned on.'];
        }
        if (!self::checkCurrentCode($user, $code)) {
            return ['ok' => false, 'error' => 'That code didn\'t match. Enter the newest code from your authenticator app.'];
        }
        $codes = self::newRecoveryCodes();
        Database::connection()->prepare('UPDATE corp_users SET mfa_recovery = :rec WHERE id = :id')
            ->execute(['rec' => self::hashCodes($codes), 'id' => (int) $user['id']]);
        CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], 'mfa_recovery_regenerated');

        return ['ok' => true, 'codes' => $codes];
    }

    /**
     * Turn MFA off (only allowed when the platform doesn't require it).
     *
     * @param array<string, mixed> $user
     * @return array{ok: bool, error?: string}
     */
    public static function disable(array $user, string $password, string $code): array
    {
        if (self::required()) {
            return ['ok' => false, 'error' => 'Two-step sign-in is required for all company accounts and can\'t be turned off.'];
        }
        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return ['ok' => false, 'error' => 'Your current password is not correct.'];
        }
        if (!self::checkCurrentCode($user, $code)) {
            return ['ok' => false, 'error' => 'That code didn\'t match. Enter the newest code from your authenticator app.'];
        }
        Database::connection()->prepare(
            'UPDATE corp_users SET mfa_secret_enc = NULL, mfa_enabled_at = NULL, mfa_last_step = NULL, mfa_recovery = NULL, updated_at = :up WHERE id = :id'
        )->execute(['up' => LocalDate::nowUtc(), 'id' => (int) $user['id']]);
        CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], 'mfa_disabled');

        return ['ok' => true];
    }

    /** @param array<string, mixed> $user */
    public static function recoveryCodesLeft(array $user): int
    {
        return count(json_decode((string) ($user['mfa_recovery'] ?? '[]'), true) ?: []);
    }

    /** "ABCD-EFGH" input in any case / spacing → "ABCDEFGH", or null. */
    public static function normaliseRecovery(string $input): ?string
    {
        $s = strtoupper((string) preg_replace('/[\s\-]+/', '', $input));

        return preg_match('/^[' . self::CODE_ALPHABET . ']{8}$/', $s) === 1 ? $s : null;
    }

    /** @return list<string> ten "XXXX-XXXX" codes, shown to the user once */
    public static function newRecoveryCodes(): array
    {
        $codes = [];
        $max = strlen(self::CODE_ALPHABET) - 1;
        while (count($codes) < self::RECOVERY_COUNT) {
            $c = '';
            for ($i = 0; $i < 8; $i++) {
                $c .= self::CODE_ALPHABET[random_int(0, $max)];
            }
            $codes[substr($c, 0, 4) . '-' . substr($c, 4)] = true;
        }

        return array_keys($codes);
    }

    // ------------------------------------------------------------------

    /** @param array<string, mixed> $user */
    private static function checkCurrentCode(array $user, string $code): bool
    {
        $secret = CorpCrypto::decrypt((string) ($user['mfa_secret_enc'] ?? ''));
        if ($secret === null) {
            return false;
        }
        $last = $user['mfa_last_step'] !== null ? (int) $user['mfa_last_step'] : null;
        $step = TotpService::verify($secret, $code, $last);
        if ($step === null) {
            return false;
        }
        Database::connection()->prepare('UPDATE corp_users SET mfa_last_step = :step WHERE id = :id')
            ->execute(['step' => $step, 'id' => (int) $user['id']]);

        return true;
    }

    /** @param list<string> $codes */
    private static function hashCodes(array $codes): string
    {
        return json_encode(array_map(
            static fn (string $c): string => password_hash(str_replace('-', '', $c), PASSWORD_BCRYPT),
            $codes,
        ));
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ok: true, used_recovery: bool, recovery_left: int|null}
     */
    private static function passed(array $user, string $jti, bool $usedRecovery, ?int $left): array
    {
        CorpSessionService::markMfaPassed($jti);
        self::ensurePhpSession();
        unset($_SESSION[self::FAIL_KEY][$jti]);
        CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], $usedRecovery ? 'mfa_recovery_used' : 'mfa_passed',
            null, null, $usedRecovery ? ['codes_left' => $left] : null);

        return ['ok' => true, 'used_recovery' => $usedRecovery, 'recovery_left' => $left];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ok: false, error: string, locked?: bool}
     */
    private static function failed(array $user, string $jti, string $message): array
    {
        self::ensurePhpSession();
        $n = self::failures($jti) + 1;
        $_SESSION[self::FAIL_KEY][$jti] = $n;
        CorpAudit::log((int) $user['company_id'], 'corp_user', (int) $user['id'], 'mfa_failed', null, null, ['attempt' => $n]);
        if ($n >= self::MAX_FAILED) {
            CorpSessionService::revoke($jti);

            return ['ok' => false, 'locked' => true, 'error' => 'Too many wrong codes. Please sign in again.'];
        }

        return ['ok' => false, 'error' => $message];
    }

    private static function failures(string $jti): int
    {
        self::ensurePhpSession();

        return (int) ($_SESSION[self::FAIL_KEY][$jti] ?? 0);
    }

    private static function ensurePhpSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
