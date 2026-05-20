<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class ImpersonationService
{
    public static function createToken(int $adminId, int $clinicId): ?string
    {
        if (!Database::ping()) {
            return null;
        }

        $user = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('is_active', '=', 1)
            ->where('role', '=', 'admin')
            ->orderBy('is_owner', 'DESC')
            ->first();

        if ($user === null) {
            $user = QueryBuilder::table('users')
                ->forClinic($clinicId)
                ->where('is_active', '=', 1)
                ->orderBy('id', 'ASC')
                ->first();
        }

        if ($user === null) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 1800);

        QueryBuilder::table('impersonation_tokens')->insert([
            'admin_id' => $adminId,
            'clinic_id' => $clinicId,
            'user_id' => (int) $user['id'],
            'token_hash' => $hash,
            'expires_at' => $expires,
        ]);

        self::audit($adminId, $clinicId, (int) $user['id']);

        return $token;
    }

    /** @return array{user: array<string, mixed>, clinic: array<string, mixed>, admin_name: string}|null */
    public static function consume(string $token): ?array
    {
        if (!Database::ping()) {
            return null;
        }

        $hash = hash('sha256', $token);
        $row = QueryBuilder::table('impersonation_tokens')
            ->where('token_hash', '=', $hash)
            ->where('used_at', 'IS', null)
            ->first();

        if ($row === null || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        QueryBuilder::table('impersonation_tokens')
            ->where('id', '=', (int) $row['id'])
            ->update(['used_at' => date('Y-m-d H:i:s')]);

        $user = QueryBuilder::table('users')->where('id', '=', (int) $row['user_id'])->first();
        $clinic = QueryBuilder::table('tenants')->where('id', '=', (int) $row['clinic_id'])->first();
        $admin = SuperAdminAuthService::find((int) $row['admin_id']);

        if ($user === null || $clinic === null) {
            return null;
        }

        return [
            'user' => $user,
            'clinic' => $clinic,
            'admin_name' => $admin['name'] ?? 'Support',
        ];
    }

    private static function audit(int $adminId, int $clinicId, int $userId): void
    {
        $sql = 'INSERT INTO audit_log (clinic_id, user_id, table_name, record_id, action, new_values)
                VALUES (:clinic_id, NULL, :table_name, :record_id, :action, :new_values)';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'clinic_id' => $clinicId,
            'table_name' => 'impersonation',
            'record_id' => $userId,
            'action' => 'LOGIN',
            'new_values' => json_encode(['admin_id' => $adminId, 'expires_minutes' => 30]),
        ]);
    }
}
