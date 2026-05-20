<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class PortalAuthService
{
    private const OTP_TTL_MINUTES = 10;
    private const SESSION_DAYS = 7;

    public static function sendOtp(int $clinicId, string $phone): array
    {
        $normalized = PatientService::normalizePhone($phone);
        if ($normalized === '') {
            return ['ok' => false, 'message' => 'Invalid phone number'];
        }

        $patient = PatientService::findByPhone($clinicId, $normalized);
        if ($patient === null) {
            return ['ok' => false, 'message' => 'No patient record for this phone. Contact the clinic to register.'];
        }

        $otp = (string) random_int(100000, 999999);
        $hash = hash('sha256', $otp . ($_ENV['APP_KEY'] ?? 'dev'));

        QueryBuilder::table('otp_tokens')->insert([
            'phone' => $normalized,
            'otp_hash' => $hash,
            'purpose' => 'portal_login',
            'expires_at' => date('Y-m-d H:i:s', time() + self::OTP_TTL_MINUTES * 60),
        ]);

        $body = 'Your ManageClinic login code is ' . $otp . '. Valid for ' . self::OTP_TTL_MINUTES . ' minutes.';
        TwilioSmsService::send($normalized, $body);

        return ['ok' => true, 'message' => 'OTP sent', 'dev_otp' => ($_ENV['APP_ENV'] ?? 'local') === 'local' ? $otp : null];
    }

    public static function verifyOtp(int $clinicId, string $phone, string $code): ?array
    {
        $normalized = PatientService::normalizePhone($phone);
        $patient = PatientService::findByPhone($clinicId, $normalized);
        if ($patient === null) {
            return null;
        }

        $row = QueryBuilder::table('otp_tokens')
            ->where('phone', '=', $normalized)
            ->where('purpose', '=', 'portal_login')
            ->orderBy('id', 'DESC')
            ->first();

        if ($row === null || strtotime($row['expires_at']) < time()) {
            return null;
        }

        $expected = hash('sha256', trim($code) . ($_ENV['APP_KEY'] ?? 'dev'));
        if (!hash_equals($row['otp_hash'], $expected)) {
            return null;
        }

        QueryBuilder::table('otp_tokens')->where('id', '=', (int) $row['id'])->update(['used_at' => date('Y-m-d H:i:s')]);

        self::setPortalCookie((int) $patient['id'], $clinicId);

        return $patient;
    }

    public static function patientFromCookie(int $clinicId): ?array
    {
        $token = $_COOKIE['mc_portal'] ?? null;
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $payload = JWT::decode($token, new Key(self::secret(), 'HS256'));
            $data = (array) $payload;
            if ((int) ($data['clinic_id'] ?? 0) !== $clinicId) {
                return null;
            }
            if (($data['role'] ?? '') !== 'patient') {
                return null;
            }

            return PatientService::find($clinicId, (int) $data['sub']);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setPortalCookie(int $patientId, int $clinicId): void
    {
        $days = self::SESSION_DAYS;
        $token = JWT::encode([
            'sub' => $patientId,
            'clinic_id' => $clinicId,
            'role' => 'patient',
            'iat' => time(),
            'exp' => time() + ($days * 86400),
        ], self::secret(), 'HS256');

        $secure = ($_ENV['APP_ENV'] ?? 'local') !== 'local';
        setcookie('mc_portal', $token, [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearPortalCookie(): void
    {
        setcookie('mc_portal', '', ['expires' => time() - 3600, 'path' => '/']);
    }

    private static function secret(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'change-me-in-production';
    }
}
