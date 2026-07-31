<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Passwordless patient identity auth (patient_identities + patient_sessions).
 * Shared with the marketing site — same tables, same ecp_pid cookie.
 */
final class PatientIdentityAuthService
{
    public const COOKIE = 'ecp_pid';
    public const TTL_SECONDS = 600;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 30;
    public const SESSION_DAYS = 30;

    /** @return array<string, mixed>|null */
    public static function current(): ?array
    {
        $token = (string) ($_COOKIE[self::COOKIE] ?? '');
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT pi.*
                 FROM patient_sessions ps
                 JOIN patient_identities pi ON pi.id = ps.identity_id
                 WHERE ps.id = :id AND ps.expires_at > NOW() AND pi.is_active = 1
                 LIMIT 1'
            );
            $stmt->execute(['id' => $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{ok: bool, error?: string, retry_after?: int, mode?: string, dev_code?: ?string, exists?: bool, name_hint?: ?string} */
    public static function sendOtp(string $rawPhone, string $intent = ''): array
    {
        $phone = self::normalizePhone($rawPhone);
        if ($phone === '' || strlen(preg_replace('/\D/', '', $phone) ?? '') < 10) {
            return ['ok' => false, 'error' => 'invalid_phone'];
        }

        $pdo = Database::connection();
        $exists = false;
        $nameHint = null;
        $stmt = $pdo->prepare('SELECT id, name, first_name FROM patient_identities WHERE phone = :p LIMIT 1');
        $stmt->execute(['p' => $phone]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $exists = true;
            $nameHint = $row['first_name'] ?: explode(' ', (string) $row['name'])[0];
        }

        if ($intent === 'signin' && !$exists) {
            return ['ok' => false, 'error' => 'account_not_found'];
        }
        if ($intent === 'signup' && $exists) {
            return ['ok' => false, 'error' => 'account_exists'];
        }

        $last = $pdo->prepare(
            'SELECT created_at FROM patient_otp_codes
             WHERE handle = :h AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $last->execute(['h' => $phone]);
        if ($lastAt = $last->fetchColumn()) {
            $age = time() - strtotime((string) $lastAt);
            if ($age < self::RESEND_SECONDS) {
                return ['ok' => false, 'error' => 'resend_too_soon', 'retry_after' => self::RESEND_SECONDS - $age];
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo->prepare(
            'INSERT INTO patient_otp_codes (handle, channel, code_hash, expires_at)
             VALUES (:h, "whatsapp", :hash, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        )->execute(['h' => $phone, 'hash' => hash('sha256', $code), 'ttl' => self::TTL_SECONDS]);

        $wa = WhatsAppService::send($phone, WaTemplateService::OTP_TEMPLATE, [
            'code' => $code,
            'body' => "OTP: {$code}",
        ]);
        $devMode = strtolower((string) ($_ENV['APP_ENV'] ?? 'local')) === 'local';

        if (!$wa['ok']) {
            $msg = strtolower((string) ($wa['message'] ?? ''));
            if (str_contains($msg, 'not a valid whatsapp') || str_contains($msg, 'whatsapp user') || str_contains($msg, 'recipient')) {
                return ['ok' => false, 'error' => 'not_whatsapp'];
            }
            if (str_contains($msg, 'no approved whatsapp template')) {
                return ['ok' => false, 'error' => 'wa_template_missing'];
            }
            return ['ok' => false, 'error' => 'wa_send_failed'];
        }

        return [
            'ok' => true,
            'mode' => $devMode ? 'dev' : 'live',
            'dev_code' => $devMode ? $code : null,
            'exists' => $exists,
            'name_hint' => $nameHint,
        ];
    }

    /** @return array{ok: bool, error?: string, identity?: array<string,mixed>, is_new?: bool} */
    public static function verifyOtp(string $rawPhone, string $code, ?string $name = null): array
    {
        $phone = self::normalizePhone($rawPhone);
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($phone === '' || strlen($code) !== 6) {
            return ['ok' => false, 'error' => 'invalid_input'];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, code_hash, expires_at, attempts
             FROM patient_otp_codes
             WHERE handle = :h AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['h' => $phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'error' => 'no_code_issued'];
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'expired'];
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'too_many_attempts'];
        }

        $pdo->prepare('UPDATE patient_otp_codes SET attempts = attempts + 1 WHERE id = :id')
            ->execute(['id' => $row['id']]);

        if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
            return ['ok' => false, 'error' => 'invalid_code'];
        }

        $pdo->prepare('UPDATE patient_otp_codes SET consumed_at = NOW() WHERE id = :id')
            ->execute(['id' => $row['id']]);

        $find = $pdo->prepare('SELECT * FROM patient_identities WHERE phone = :p LIMIT 1');
        $find->execute(['p' => $phone]);
        $identity = $find->fetch(PDO::FETCH_ASSOC);
        $isNew = false;

        if (!$identity) {
            $displayName = trim((string) $name);
            if ($displayName === '') {
                $displayName = 'Patient';
            }
            $pdo->prepare(
                'INSERT INTO patient_identities (phone, name, source, phone_verified_at)
                 VALUES (:p, :n, "self_signup", NOW())'
            )->execute(['p' => $phone, 'n' => $displayName]);
            $find->execute(['p' => $phone]);
            $identity = $find->fetch(PDO::FETCH_ASSOC);
            $isNew = true;
        } elseif (empty($identity['phone_verified_at'])) {
            $pdo->prepare('UPDATE patient_identities SET phone_verified_at = NOW() WHERE id = :id')
                ->execute(['id' => $identity['id']]);
        }

        // Record messaging opt-in: a verified OTP proves control of the number
        // and intent to use the service — the strongest consent basis for
        // business-initiated WhatsApp (Meta Acceptable Use). Best-effort.
        MessagingConsent::recordOptIn(
            $phone,
            'otp_verify',
            (int) $identity['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
        );

        self::backfillClinicCharts((int) $identity['id'], $phone);
        self::startSession((int) $identity['id']);

        return ['ok' => true, 'identity' => $identity, 'is_new' => $isNew];
    }

    public static function logout(): void
    {
        $token = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($token !== '' && strlen($token) === 64 && ctype_xdigit($token)) {
            try {
                Database::connection()
                    ->prepare('DELETE FROM patient_sessions WHERE id = :id')
                    ->execute(['id' => $token]);
            } catch (\Throwable) {
                // ignore
            }
        }
        self::clearCookie();
    }

    /** @return array{id: int, name: string, first_name: ?string, phone: string, email: ?string}|null */
    public static function publicPatient(?array $identity): ?array
    {
        if ($identity === null) {
            return null;
        }

        return [
            'id' => (int) $identity['id'],
            'name' => (string) ($identity['name'] ?? ''),
            'first_name' => $identity['first_name'] ?? null,
            'phone' => (string) ($identity['phone'] ?? ''),
            'email' => $identity['email'] ?? null,
        ];
    }

    public static function normalizePhone(string $raw): string
    {
        $s = preg_replace('/[\s\-()]/', '', trim($raw)) ?? '';
        if ($s === '') {
            return '';
        }
        if ($s[0] === '+') {
            $rest = preg_replace('/\D/', '', substr($s, 1)) ?? '';

            return $rest === '' ? '' : '+' . $rest;
        }
        $digits = preg_replace('/\D/', '', $s) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }
        if (strlen($digits) === 11 && $digits[0] === '0') {
            return '+91' . substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+' . $digits;
        }

        return '+' . $digits;
    }

    private static function startSession(int $identityId): void
    {
        $token = bin2hex(random_bytes(32));
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $ip = $ip ? substr(explode(',', (string) $ip)[0], 0, 45) : null;

        Database::connection()->prepare(
            'INSERT INTO patient_sessions (id, identity_id, user_agent, ip, expires_at)
             VALUES (:id, :iid, :ua, :ip, DATE_ADD(NOW(), INTERVAL :days DAY))'
        )->execute([
            'id' => $token,
            'iid' => $identityId,
            'ua' => $ua ?: null,
            'ip' => $ip,
            'days' => self::SESSION_DAYS,
        ]);

        self::setCookie($token);
    }

    private static function setCookie(string $token): void
    {
        if (headers_sent()) {
            return;
        }

        $secure = ($_SERVER['HTTPS'] ?? '') === 'on'
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        $domain = self::cookieDomain();
        if ($domain !== '') {
            self::clearHostCookie($secure);
        }

        $opts = [
            'expires' => time() + self::SESSION_DAYS * 86400,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($domain !== '') {
            $opts['domain'] = $domain;
        }

        setcookie(self::COOKIE, $token, $opts);
    }

    private static function clearHostCookie(bool $secure): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $secure = ($_SERVER['HTTPS'] ?? '') === 'on'
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        $domain = self::cookieDomain();
        self::clearHostCookie($secure);

        $opts = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($domain !== '') {
            $opts['domain'] = $domain;
        }

        setcookie(self::COOKIE, '', $opts);
    }

    private static function cookieDomain(): string
    {
        $explicit = trim((string) ($_ENV['PATIENT_COOKIE_DOMAIN'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $host = strtolower((string) (parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST)
            ?: ($_SERVER['HTTP_HOST'] ?? '')));

        if (preg_match('/(^|\.)eclinicpro\.com$/', $host)) {
            return '.eclinicpro.com';
        }
        if (preg_match('/(^|\.)silverwebbuzz\.com$/', $host)) {
            return '.silverwebbuzz.com';
        }

        return '';
    }

    private static function backfillClinicCharts(int $identityId, string $normalizedPhone): void
    {
        if ($identityId <= 0 || $normalizedPhone === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $normalizedPhone) ?? '';
        if (strlen($digits) < 10) {
            return;
        }
        $last10 = substr($digits, -10);

        try {
            Database::connection()->prepare(
                'UPDATE patients
                 SET identity_id = :iid
                 WHERE identity_id IS NULL
                   AND RIGHT(
                     REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", ""), "(", ""), ")", ""),
                     10
                   ) COLLATE utf8mb4_unicode_ci
                 = CAST(:l10 AS CHAR(10) CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci'
            )->execute(['iid' => $identityId, 'l10' => $last10]);
        } catch (\Throwable) {
            // best-effort
        }
    }
}
