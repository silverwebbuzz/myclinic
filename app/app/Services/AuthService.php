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
        string $slug,
        string $email,
        string $password,
        ?string $googleId = null,
    ): array {
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
                'name' => $clinicName,
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
            MailService::send($email, 'welcome', ['clinic_name' => $clinicName], $tenantId);
        } catch (\Throwable $e) {
            error_log('[registerClinic] welcome mail failed: ' . $e->getMessage());
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId];
    }

    public static function findUserByEmail(string $email): ?array
    {
        return QueryBuilder::table('users')->where('email', '=', $email)->where('is_active', '=', 1)->first();
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
