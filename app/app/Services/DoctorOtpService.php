<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Passwordless OTP login for doctors approved through the claim queue.
 * Mirrors the marketing-site patient flow but writes to doctor_otp_codes
 * with purpose='login' and finds users via users.phone + role='doctor'.
 */
final class DoctorOtpService
{
    public const TTL_SECONDS    = 600;
    public const MAX_ATTEMPTS   = 5;
    public const RESEND_SECONDS = 30;

    /**
     * Issue a 6-digit OTP for the given phone, IF a doctor account exists.
     *
     * @return array{ok: bool, error?: string, mode?: string, dev_code?: ?string, retry_after?: int}
     */
    public static function issue(string $rawPhone): array
    {
        $phone = self::normalizePhone($rawPhone);
        if ($phone === '' || strlen($phone) < 8) {
            return ['ok' => false, 'error' => 'invalid_phone'];
        }

        // Confirm a doctor account exists for this phone.
        $db = Database::connection();
        $u = $db->prepare(
            'SELECT id FROM users
             WHERE phone = :p AND role = "doctor" AND is_active = 1
             LIMIT 1'
        );
        $u->execute(['p' => $phone]);
        if (!$u->fetchColumn()) {
            // Don't reveal whether a phone is registered (anti-enumeration);
            // return a generic error matching the failure message.
            return ['ok' => false, 'error' => 'no_account'];
        }

        // Throttle.
        $last = $db->prepare(
            'SELECT created_at FROM doctor_otp_codes
             WHERE phone = :p AND purpose = "login" AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $last->execute(['p' => $phone]);
        if ($lastAt = $last->fetchColumn()) {
            $age = time() - strtotime((string) $lastAt);
            if ($age < self::RESEND_SECONDS) {
                return ['ok' => false, 'error' => 'resend_too_soon',
                        'retry_after' => self::RESEND_SECONDS - $age];
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = hash('sha256', $code);

        $db->prepare(
            'INSERT INTO doctor_otp_codes (phone, purpose, code_hash, expires_at)
             VALUES (:p, "login", :h, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        )->execute(['p' => $phone, 'h' => $hash, 'ttl' => self::TTL_SECONDS]);

        $body = "Your eClinicPro login code is: {$code}\nValid for 10 minutes.";
        $sent = TwilioSmsService::send($phone, $body);
        $devMode = (($_ENV['TWILIO_ACCOUNT_SID'] ?? '') === ''
                 || ($_ENV['TWILIO_AUTH_TOKEN']   ?? '') === ''
                 || ($_ENV['TWILIO_FROM_NUMBER'] ?? '') === '');

        return [
            'ok'       => (bool) $sent['ok'],
            'mode'     => $devMode ? 'dev' : 'live',
            'dev_code' => $devMode ? $code : null,
        ];
    }

    /**
     * Verify a code. Returns ['ok'=>true,'user'=>array] on success.
     */
    public static function verify(string $rawPhone, string $code): array
    {
        $phone = self::normalizePhone($rawPhone);
        $code  = preg_replace('/\D/', '', $code) ?? '';
        if ($phone === '' || strlen($code) !== 6) {
            return ['ok' => false, 'error' => 'invalid_input'];
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT id, code_hash, expires_at, attempts
             FROM doctor_otp_codes
             WHERE phone = :p AND purpose = "login" AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['p' => $phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row)                                          return ['ok' => false, 'error' => 'no_code_issued'];
        if (strtotime((string) $row['expires_at']) < time()) return ['ok' => false, 'error' => 'expired'];
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS)   return ['ok' => false, 'error' => 'too_many_attempts'];

        $db->prepare('UPDATE doctor_otp_codes SET attempts = attempts + 1 WHERE id = :id')
           ->execute(['id' => $row['id']]);

        if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
            return ['ok' => false, 'error' => 'invalid_code'];
        }
        $db->prepare('UPDATE doctor_otp_codes SET consumed_at = NOW() WHERE id = :id')
           ->execute(['id' => $row['id']]);

        // Load the user row for cookie issuance by the caller.
        $u = $db->prepare(
            'SELECT * FROM users
             WHERE phone = :p AND role = "doctor" AND is_active = 1
             LIMIT 1'
        );
        $u->execute(['p' => $phone]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['ok' => false, 'error' => 'no_account'];
        }
        return ['ok' => true, 'user' => $user];
    }

    public static function normalizePhone(string $raw): string
    {
        $s = preg_replace('/[\s\-\(\)]/', '', $raw) ?? $raw;
        if ($s === '') return '';
        if ($s[0] === '+') {
            $rest = preg_replace('/\D/', '', substr($s, 1)) ?? '';
            return $rest === '' ? '' : '+' . $rest;
        }
        $digits = preg_replace('/\D/', '', $s) ?? '';
        if ($digits === '') return '';
        if (strlen($digits) === 10) return '+91' . $digits;
        if (strlen($digits) === 11 && $digits[0] === '0') return '+91' . substr($digits, 1);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) return '+' . $digits;
        return '+' . $digits;
    }
}
