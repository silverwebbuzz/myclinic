<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use PDO;

/**
 * Forgot username / password recovery via verified mobile number.
 */
final class AccountRecoveryService
{
    public const SESSION_PASSWORD_RESET_PHONE = 'password_reset_verified_phone';

    /**
     * Send the account username to the phone on file (SMS).
     *
     * @return array{ok: bool, error?: string, mode?: string, dev_username?: ?string, retry_after?: int}
     */
    public static function sendUsernameReminder(string $rawPhone): array
    {
        $phone = DoctorOtpService::normalizePhone($rawPhone);
        if ($phone === '' || strlen(preg_replace('/\D/', '', $phone) ?? '') < 10) {
            return ['ok' => false, 'error' => 'invalid_phone'];
        }

        $user = AuthService::findUserByPhone($phone);
        if ($user === null) {
            // Don't reveal whether the number exists.
            return ['ok' => true, 'mode' => 'live', 'dev_username' => null];
        }

        $username = trim((string) ($user['username'] ?? ''));
        if ($username === '') {
            $username = UsernameService::resolveForRegistration('', $phone, (string) ($user['name'] ?? 'Doctor'));
            QueryBuilder::table('users')->where('id', '=', (int) $user['id'])->update([
                'username' => $username,
            ]);
        }

        $body = "Your eClinicPro login username is: {$username}\nUse it with your password at the login page.";
        $sent = TwilioSmsService::send($phone, $body);
        $devMode = self::isLocalEnv();

        return [
            'ok' => (bool) ($sent['ok'] ?? false) || $devMode,
            'mode' => $devMode ? 'dev' : 'live',
            'dev_username' => $devMode ? $username : null,
            'error' => ($sent['ok'] ?? false) || $devMode ? null : 'send_failed',
        ];
    }

    /**
     * @return array{ok: bool, error?: string, mode?: string, dev_code?: ?string, retry_after?: int}
     */
    public static function sendPasswordResetOtp(string $rawPhone): array
    {
        $phone = DoctorOtpService::normalizePhone($rawPhone);
        if ($phone === '' || strlen(preg_replace('/\D/', '', $phone) ?? '') < 10) {
            return ['ok' => false, 'error' => 'invalid_phone'];
        }

        if (AuthService::findUserByPhone($phone) === null) {
            return ['ok' => true, 'mode' => 'live', 'dev_code' => null];
        }

        return self::issueOtp($phone, 'password_reset', "Your eClinicPro password reset code is: {code}\nValid for 10 minutes.");
    }

    /**
     * Verify OTP and allow password reset for this phone (session-scoped).
     *
     * @return array{ok: bool, error?: string, phone?: string}
     */
    public static function verifyPasswordResetOtp(string $rawPhone, string $code): array
    {
        
        $phone = DoctorOtpService::normalizePhone($rawPhone);
        $res = self::verifyOtp($phone, $code, 'password_reset');
        if (!$res['ok']) {
            return $res;
        }
        if (AuthService::findUserByPhone($phone) === null) {
            return ['ok' => false, 'error' => 'no_account'];
        }

        self::ensureSession();
        $_SESSION[self::SESSION_PASSWORD_RESET_PHONE] = [
            'phone' => $phone,
            'at' => time(),
        ];

        return ['ok' => true, 'phone' => $phone];
    }

    public static function verifiedPasswordResetPhone(): ?string
    {
        self::ensureSession();
        $data = $_SESSION[self::SESSION_PASSWORD_RESET_PHONE] ?? null;
        if (!is_array($data) || empty($data['phone'])) {
            return null;
        }
        if (time() - (int) ($data['at'] ?? 0) > 900) {
            unset($_SESSION[self::SESSION_PASSWORD_RESET_PHONE]);
            return null;
        }

        return (string) $data['phone'];
    }

    public static function clearPasswordResetSession(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_PASSWORD_RESET_PHONE]);
    }

    /** @return array{ok: bool, error?: string} */
    public static function resetPassword(string $newPassword): array
    {
        $phone = self::verifiedPasswordResetPhone();
        if ($phone === null) {
            return ['ok' => false, 'error' => 'session_expired'];
        }

        $user = AuthService::findUserByPhone($phone);
        if ($user === null) {
            return ['ok' => false, 'error' => 'no_account'];
        }

        AuthService::updatePassword((int) $user['id'], $newPassword);
        QueryBuilder::table('users')->where('id', '=', (int) $user['id'])->update([
            'remember_token' => null,
        ]);
        SessionService::revokeAllForUser((int) $user['id']);
        self::clearPasswordResetSession();

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string, mode?: string, dev_code?: ?string, retry_after?: int}
     */
    private static function issueOtp(string $phone, string $purpose, string $bodyTemplate): array
    {
        $db = Database::connection();

        $last = $db->prepare(
            'SELECT created_at FROM doctor_otp_codes
             WHERE phone = :p AND purpose = :purpose AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $last->execute(['p' => $phone, 'purpose' => $purpose]);
        if ($lastAt = $last->fetchColumn()) {
            $age = time() - strtotime((string) $lastAt);
            if ($age < DoctorOtpService::RESEND_SECONDS) {
                return [
                    'ok' => false,
                    'error' => 'resend_too_soon',
                    'retry_after' => DoctorOtpService::RESEND_SECONDS - $age,
                ];
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = hash('sha256', $code);

        $db->prepare(
            'INSERT INTO doctor_otp_codes (phone, purpose, code_hash, expires_at)
             VALUES (:p, :purpose, :h, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        )->execute([
            'p' => $phone,
            'purpose' => $purpose,
            'h' => $hash,
            'ttl' => DoctorOtpService::TTL_SECONDS,
        ]);

        $body = str_replace('{code}', $code, $bodyTemplate);
        $sent = TwilioSmsService::send($phone, $body);
        $devMode = self::isLocalEnv();

        return [
            'ok' => (bool) ($sent['ok'] ?? false) || $devMode,
            'mode' => $devMode ? 'dev' : 'live',
            'dev_code' => $devMode ? $code : null,
        ];
    }

    /** @return array{ok: bool, error?: string} */
    private static function verifyOtp(string $phone, string $code, string $purpose): array
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($phone === '' || strlen($code) !== 6) {
            return ['ok' => false, 'error' => 'invalid_input'];
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, code_hash, expires_at, attempts
             FROM doctor_otp_codes
             WHERE phone = :p AND purpose = :purpose AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['p' => $phone, 'purpose' => $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'no_code_issued'];
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'expired'];
        }
        if ((int) $row['attempts'] >= DoctorOtpService::MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'too_many_attempts'];
        }

        $db->prepare('UPDATE doctor_otp_codes SET attempts = attempts + 1 WHERE id = :id')
            ->execute(['id' => $row['id']]);

        if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
            return ['ok' => false, 'error' => 'invalid_code'];
        }
        $db->prepare('UPDATE doctor_otp_codes SET consumed_at = NOW() WHERE id = :id')
            ->execute(['id' => $row['id']]);

        return ['ok' => true];
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private static function isLocalEnv(): bool
    {
        return strtolower((string) ($_ENV['APP_ENV'] ?? 'local')) === 'local';
    }
}
