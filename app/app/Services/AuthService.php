<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Http\Request;

final class AuthService
{
    public static function slugAvailable(string $slug): bool
    {
        if (!Database::ping()) {
            return false;
        }

        return QueryBuilder::table('tenants')->where('slug', '=', $slug)->count() === 0;
    }

    /** @return array{tenant_id: int, user_id: int} */
    public static function registerClinic(
        string $clinicName,
        string $ownerName,
        string $slug,
        string $email,
        string $password,
        ?string $googleId = null,
    ): array {
        // The user (clinic owner/doctor) gets their own name; the tenant keeps
        // the clinic name. Fall back to the clinic name only if no owner name
        // was provided, so older callers / blank submits never store an empty
        // user name.
        $ownerName = trim($ownerName) !== '' ? trim($ownerName) : $clinicName;
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Trial clock starts at registration; PlanService::applyPlanToTenant()
            // only sets trial_ends_at when empty (never extends mid-onboarding).
            $tenantId = QueryBuilder::table('tenants')->insert([
                'name' => $clinicName,
                'slug' => $slug,
                'email' => $email,
                'trial_ends_at' => date('Y-m-d', strtotime('+1 month')),
            ]);

            QueryBuilder::table('specialty_configs')->insert([
                'clinic_id' => $tenantId,
                'uhid_prefix' => strtoupper(substr(preg_replace('/[^a-z]/', '', strtolower($slug)), 0, 6) ?: 'MC'),
            ]);

            $userData = [
                'clinic_id' => $tenantId,
                'name' => $ownerName,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role' => 'admin',
                'is_owner' => 1,
                'is_active' => 1,
            ];
            if ($googleId !== null) {
                $userData['google_id'] = $googleId;
            }

            $userId = QueryBuilder::table('users')->insert($userData);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Outside the transaction try/catch: the clinic + user are committed.
        // A broken SMTP/Mailgun config must not 500 the registration (and the
        // old rollBack() after commit threw its own exception on top).
        PlanService::applyPlanToTenant($tenantId, 'standard', true);

        try {
            MailService::send($email, 'welcome', [
                'clinic_name' => $clinicName,
                'login_url' => rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/') . '/login',
            ], $tenantId);
        } catch (\Throwable $e) {
            error_log('[registerClinic] welcome mail failed: ' . $e->getMessage());
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId];
    }

    /**
     * Register via verified mobile number (phone + password).
     *
     * @return array{tenant_id: int, user_id: int, username: string}
     */
    public static function registerClinicViaPhone(
        string $clinicName,
        string $ownerName,
        string $slug,
        string $phone,
        string $password,
        ?string $email = null,
        ?string $username = null,
    ): array {
        $ownerName = trim($ownerName) !== '' ? trim($ownerName) : $clinicName;
        $phone = DoctorOtpService::normalizePhone($phone);
        $email = $email !== null ? strtolower(trim($email)) : '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        $username = $username ?? UsernameService::resolveForRegistration('', $phone, $ownerName);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $tenantId = QueryBuilder::table('tenants')->insert([
                'name' => $clinicName,
                'slug' => $slug,
                'phone' => $phone,
                'email' => $email !== '' ? $email : null,
                'trial_ends_at' => date('Y-m-d', strtotime('+1 month')),
            ]);

            QueryBuilder::table('specialty_configs')->insert([
                'clinic_id' => $tenantId,
                'uhid_prefix' => strtoupper(substr(preg_replace('/[^a-z]/', '', strtolower($slug)), 0, 6) ?: 'MC'),
            ]);

            $userId = QueryBuilder::table('users')->insert([
                'clinic_id' => $tenantId,
                'name' => $ownerName,
                'email' => $email !== '' ? $email : null,
                'username' => $username,
                'phone' => $phone,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role' => 'admin',
                'is_owner' => 1,
                'is_active' => 1,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        PlanService::applyPlanToTenant($tenantId, 'standard', true);

        if ($email !== '') {
            try {
                MailService::send($email, 'welcome', [
                    'clinic_name' => $clinicName,
                    'login_url' => rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/') . '/login',
                ], $tenantId);
            } catch (\Throwable $e) {
                error_log('[registerClinicViaPhone] welcome mail failed: ' . $e->getMessage());
            }
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId, 'username' => $username];
    }

    public static function findUserByUsername(string $username): ?array
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return null;
        }

        return QueryBuilder::table('users')
            ->where('username', '=', $username)
            ->where('is_active', '=', 1)
            ->first();
    }

    public static function findUserByPhone(string $phone): ?array
    {
        $normalized = DoctorOtpService::normalizePhone($phone);
        if ($normalized === '') {
            return null;
        }
        return QueryBuilder::table('users')
            ->where('phone', '=', $normalized)
            ->where('is_active', '=', 1)
            ->first();
    }

    public static function findUserByEmail(string $email): ?array
    {
        return QueryBuilder::table('users')->where('email', '=', $email)->where('is_active', '=', 1)->first();
    }

    /**
     * True if the email is already attached to any user (active or not).
     * Used to reject duplicate registrations before the INSERT, so a unique
     * constraint violation never bubbles up as a 500.
     */
    public static function emailRegistered(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !Database::ping()) {
            return false;
        }

        return QueryBuilder::table('users')->where('email', '=', $email)->count() > 0;
    }

    public static function findUserByLogin(string $login): ?array
    {
        $login = strtolower(trim($login));
        if ($login === '') {
            return null;
        }

        if (str_contains($login, '@')) {
            return self::findUserByEmail($login);
        }

        $user = QueryBuilder::table('users')
            ->where('username', '=', $login)
            ->where('is_active', '=', 1)
            ->first();

        if ($user !== null) {
            return $user;
        }

        $digits = preg_replace('/\D/', '', $login) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return QueryBuilder::table('users')
                ->where('username', '=', $digits)
                ->where('is_active', '=', 1)
                ->first();
        }

        return null;
    }

    public static function failedLoginCount(string $email): int
    {
        $key = 'auth:failed:' . strtolower($email);
        $client = RedisClient::connection();
        if ($client === null) {
            return 0;
        }

        return (int) $client->get($key);
    }

    public static function recordFailedLogin(string $email): int
    {
        $key = 'auth:failed:' . strtolower($email);
        $client = RedisClient::connection();
        if ($client !== null) {
            $count = (int) $client->incr($key);
            if ($count === 1) {
                $client->expire($key, 900);
            }

            return $count;
        }

        return 0;
    }

    public static function clearFailedLogins(string $email): void
    {
        RedisClient::del('auth:failed:' . strtolower($email));
    }

    public static function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** @deprecated Use SessionService — kept for backward compatibility */
    public static function storeRefreshToken(int $userId, string $token): void
    {
        QueryBuilder::table('users')->where('id', '=', $userId)->update([
            'remember_token' => hash('sha256', $token),
        ]);
    }

    public static function establishSession(array $user, Request $request, bool $remember): ?string
    {
        if (!$remember) {
            return null;
        }

        $refresh = self::generateRefreshToken();
        SessionService::create((int) $user['id'], $refresh, $request);
        self::storeRefreshToken((int) $user['id'], $refresh);

        return $refresh;
    }

    public static function updatePassword(int $userId, string $password): void
    {
        QueryBuilder::table('users')->where('id', '=', $userId)->update([
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);
    }
}
