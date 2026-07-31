<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\MessagingSettings;
use PDO;

/**
 * Passwordless OTP for doctors: login (existing accounts) and register
 * (pre-account phone verification on /register).
 */
final class DoctorOtpService
{
    public const TTL_SECONDS    = 600;
    public const MAX_ATTEMPTS   = 5;
    public const RESEND_SECONDS = 30;
    public const SESSION_REGISTER_PHONE = 'register_verified_phone';

    /**
     * Issue a 6-digit OTP for the given phone, IF a doctor/owner account exists.
     *
     * @return array{ok: bool, error?: string, mode?: string, dev_code?: ?string, retry_after?: int}
     */
    public static function issue(string $rawPhone): array
    {
        $phone = self::normalizePhone($rawPhone);
        if ($phone === '' || strlen($phone) < 8) {
            return ['ok' => false, 'error' => 'invalid_phone'];
        }

        if (self::findLoginUser($phone) === null) {
            return ['ok' => false, 'error' => 'no_account'];
        }

        $tpl = WaTemplateService::OTP_TEMPLATE;
        if (!MessagingSettings::whatsappConfigured() || WaTemplateService::isApproved($tpl)) {
            return self::issueForPurposeWhatsApp($phone, 'login', $tpl);
        }

        return ['ok' => false, 'error' => 'whatsapp_unavailable'];
    }

    /**
     * Issue OTP for public clinic registration (phone must NOT already be registered).
     *
     * @return array{ok: bool, error?: string, mode?: string, dev_code?: ?string, retry_after?: int}
     */
    public static function issueRegister(string $rawPhone): array
    {
        $phone = self::normalizePhone($rawPhone);
        if ($phone === '' || strlen($phone) < 8) {
            return ['ok' => false, 'error' => 'invalid_phone'];
        }
        if (self::phoneRegistered($phone)) {
            return ['ok' => false, 'error' => 'already_registered'];
        }

        // Enforce WhatsApp-only OTP for registration.
        $tpl = WaTemplateService::OTP_TEMPLATE;
        if (!MessagingSettings::whatsappConfigured() || WaTemplateService::isApproved($tpl)) {
            return self::issueForPurposeWhatsApp($phone, 'register', $tpl);
        }

        // Live WhatsApp configured but OTP template not approved in Meta yet.
        return ['ok' => false, 'error' => 'whatsapp_unavailable'];
    }

    /**
     * Verify a login code. Returns ['ok'=>true,'user'=>array] on success.
     */
    public static function verify(string $rawPhone, string $code): array
    {
        $phone = self::normalizePhone($rawPhone);
        $res = self::verifyForPurpose($phone, $code, 'login');
        if (!$res['ok']) {
            return $res;
        }

        $user = self::findLoginUser($phone);
        if ($user === null) {
            return ['ok' => false, 'error' => 'no_account'];
        }
        return ['ok' => true, 'user' => $user];
    }

    /**
     * Verify a registration OTP and store the verified phone in session.
     *
     * @return array{ok: bool, phone?: string, error?: string}
     */
    public static function verifyRegister(string $rawPhone, string $code): array
    {
        $phone = self::normalizePhone($rawPhone);
        $res = self::verifyForPurpose($phone, $code, 'register');
        if (!$res['ok']) {
            return $res;
        }
        if (self::phoneRegistered($phone)) {
            return ['ok' => false, 'error' => 'already_registered'];
        }

        self::markPhoneVerified($phone);
        return ['ok' => true, 'phone' => $phone];
    }

    public static function markPhoneVerified(string $phone): void
    {
        self::ensureSession();
        $_SESSION[self::SESSION_REGISTER_PHONE] = [
            'phone' => $phone,
            'at' => time(),
        ];
    }

    public static function verifiedRegisterPhone(): ?string
    {
        self::ensureSession();
        $data = $_SESSION[self::SESSION_REGISTER_PHONE] ?? null;
        if (!is_array($data) || empty($data['phone'])) {
            return null;
        }
        if (time() - (int) ($data['at'] ?? 0) > 7200) {
            self::clearPhoneVerified();
            return null;
        }
        return (string) $data['phone'];
    }

    public static function clearPhoneVerified(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_REGISTER_PHONE]);
    }

    public static function phoneRegistered(string $rawPhone): bool
    {
        $phone = self::normalizePhone($rawPhone);
        if ($phone === '' || !Database::ping()) {
            return false;
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT 1 FROM users WHERE phone = :p LIMIT 1');
        $stmt->execute(['p' => $phone]);
        return (bool) $stmt->fetchColumn();
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

    /**
     * @return array{ok: bool, error?: string, mode?: string, dev_code?: ?string, retry_after?: int}
     */
    private static function issueForPurpose(string $phone, string $purpose, string $bodyTemplate): array
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
            if ($age < self::RESEND_SECONDS) {
                return ['ok' => false, 'error' => 'resend_too_soon',
                        'retry_after' => self::RESEND_SECONDS - $age];
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
            'ttl' => self::TTL_SECONDS,
        ]);

        $body = str_replace('{code}', $code, $bodyTemplate);
        $sent = TwilioSmsService::send($phone, $body);
        $devMode = self::isLocalEnv();

        return [
            'ok'       => (bool) $sent['ok'],
            'mode'     => $devMode ? 'dev' : 'live',
            'dev_code' => $devMode ? $code : null,
        ];
    }

    /**
     * Issue OTP and send via WhatsApp template. If Meta rejects delivery
     * because the number isn't on WhatsApp, surface a specific error.
     *
     * @return array{ok: bool, error?: string, mode?: string, dev_code?: ?string, retry_after?: int}
     */
    private static function issueForPurposeWhatsApp(string $phone, string $purpose, string $templateKey): array
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
            if ($age < self::RESEND_SECONDS) {
                return ['ok' => false, 'error' => 'resend_too_soon',
                        'retry_after' => self::RESEND_SECONDS - $age];
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
            'ttl' => self::TTL_SECONDS,
        ]);

        $wa = WhatsAppService::send($phone, $templateKey, ['code' => $code, 'body' => "OTP: {$code}"]);
        $devMode = self::isLocalEnv();
        if (!$wa['ok']) {
            $msg = strtolower((string) ($wa['message'] ?? ''));
            // Common Meta wording when recipient isn't a WhatsApp user.
            if (str_contains($msg, 'not a valid whatsapp') || str_contains($msg, 'whatsapp user') || str_contains($msg, 'recipient')) {
                return ['ok' => false, 'error' => 'not_whatsapp'];
            }
            return ['ok' => false, 'error' => 'wa_send_failed'];
        }

        return [
            'ok'       => true,
            'mode'     => $devMode ? 'dev' : 'live',
            'dev_code' => $devMode ? $code : null,
        ];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private static function verifyForPurpose(string $phone, string $code, string $purpose): array
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

        return ['ok' => true];
    }

    /** @return array<string, mixed>|null */
    private static function findLoginUser(string $phone): ?array
    {
        $db = Database::connection();
        // Doctors from claim queue, plus clinic owners who registered via phone.
        $u = $db->prepare(
            'SELECT * FROM users
             WHERE phone = :p AND is_active = 1
               AND (role = "doctor" OR is_owner = 1)
             LIMIT 1'
        );
        $u->execute(['p' => $phone]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
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
