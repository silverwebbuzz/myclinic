<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Partner login. A partner can authenticate once their account exists; the
 * dashboard itself gates features (uploads, payouts) on `status = active`.
 */
final class PartnerAuthService
{
    public static function attempt(string $email, string $password): ?array
    {
        if (!Database::ping()) {
            return null;
        }

        $partner = QueryBuilder::table('partners')
            ->where('email', '=', strtolower(trim($email)))
            ->first();

        if ($partner === null || !(int) ($partner['is_active'] ?? 0)) {
            return null;
        }

        if (($partner['status'] ?? '') === 'rejected') {
            return null;
        }

        if (!password_verify($password, (string) $partner['password_hash'])) {
            return null;
        }

        PartnerService::touchLogin((int) $partner['id']);

        return $partner;
    }

    public static function find(int $id): ?array
    {
        return PartnerService::find($id);
    }
}
