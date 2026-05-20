<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class SuperAdminAuthService
{
    public static function attempt(string $email, string $password): ?array
    {
        if (!Database::ping()) {
            return null;
        }

        $admin = QueryBuilder::table('platform_admins')
            ->where('email', '=', strtolower(trim($email)))
            ->first();

        if ($admin === null || !(int) ($admin['is_active'] ?? 0)) {
            return null;
        }

        if (!password_verify($password, (string) $admin['password_hash'])) {
            return null;
        }

        QueryBuilder::table('platform_admins')
            ->where('id', '=', (int) $admin['id'])
            ->update(['last_login_at' => date('Y-m-d H:i:s')]);

        return $admin;
    }

    public static function find(int $id): ?array
    {
        return QueryBuilder::table('platform_admins')->where('id', '=', $id)->first();
    }
}
