<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Pre-registration email verification for /register.
 * Doctor enters only an email → we send a link → after click, session
 * holds the verified email so they can finish clinic details + password.
 */
final class RegistrationEmailVerifyService
{
    public const TTL_SECONDS = 86400; // 24 hours
    public const SESSION_KEY = 'register_verified_email';

    /**
     * @return array{ok: bool, error?: string, already?: bool}
     */
    public static function send(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid_email'];
        }
        if (AuthService::emailRegistered($email)) {
            return ['ok' => false, 'error' => 'already_registered'];
        }
        if (!Database::ping()) {
            return ['ok' => false, 'error' => 'unavailable'];
        }

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        // Invalidate prior unused tokens for this email.
        try {
            QueryBuilder::table('email_verification_tokens')
                ->where('email', '=', $email)
                ->where('consumed_at', 'IS', null)
                ->delete();

            QueryBuilder::table('email_verification_tokens')->insert([
                'email' => $email,
                'token_hash' => $hash,
                'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
            ]);
        } catch (\Throwable $e) {
            error_log('[RegistrationEmailVerifyService::send] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'unavailable'];
        }

        $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        try {
            MailService::send($email, 'register_verify', [
                'verify_url' => "{$base}/register/verify-email/{$raw}",
            ], null);
        } catch (\Throwable $e) {
            error_log('[RegistrationEmailVerifyService::send mail] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'mail_failed'];
        }

        return ['ok' => true];
    }

    /**
     * Consume a raw token from the verify link. Marks email verified in session.
     *
     * @return array{ok: bool, email?: string, error?: string}
     */
    public static function consume(string $rawToken): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }
        if (!Database::ping()) {
            return ['ok' => false, 'error' => 'unavailable'];
        }

        $hash = hash('sha256', $rawToken);
        $row = QueryBuilder::table('email_verification_tokens')
            ->where('token_hash', '=', $hash)
            ->where('consumed_at', 'IS', null)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        if ($row === null) {
            return ['ok' => false, 'error' => 'invalid_or_expired'];
        }

        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '' || AuthService::emailRegistered($email)) {
            return ['ok' => false, 'error' => 'already_registered'];
        }

        QueryBuilder::table('email_verification_tokens')
            ->where('id', '=', (int) $row['id'])
            ->update(['consumed_at' => date('Y-m-d H:i:s')]);

        self::markVerified($email);

        return ['ok' => true, 'email' => $email];
    }

    public static function markVerified(string $email): void
    {
        self::ensureSession();
        $_SESSION[self::SESSION_KEY] = [
            'email' => strtolower(trim($email)),
            'at' => time(),
        ];
    }

    public static function verifiedEmail(): ?string
    {
        self::ensureSession();
        $data = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($data) || empty($data['email'])) {
            return null;
        }
        // Session proof expires after 2 hours of idle completion window.
        if (time() - (int) ($data['at'] ?? 0) > 7200) {
            self::clearVerified();
            return null;
        }

        return (string) $data['email'];
    }

    public static function clearVerified(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
