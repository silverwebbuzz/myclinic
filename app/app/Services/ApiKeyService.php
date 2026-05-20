<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class ApiKeyService
{
    public const SCOPES = [
        'patients:read',
        'patients:write',
        'appointments:read',
        'appointments:write',
        'visits:read',
        'invoices:read',
    ];

    /** @return list<array<string, mixed>> */
    public static function listForClinic(int $clinicId): array
    {
        return QueryBuilder::table('api_keys')
            ->forClinic($clinicId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /** @param list<string> $scopes @return array{key: string, id: int}|null */
    public static function create(int $clinicId, string $name, array $scopes): ?array
    {
        $plain = 'mc_live_' . bin2hex(random_bytes(24));
        $prefix = substr($plain, 0, 16);
        $hash = hash('sha256', $plain);

        QueryBuilder::table('api_keys')->insert([
            'clinic_id' => $clinicId,
            'name' => trim($name) ?: 'API key',
            'key_prefix' => $prefix,
            'key_hash' => $hash,
            'scopes' => json_encode(array_values(array_intersect($scopes, self::SCOPES))),
            'is_active' => 1,
        ]);

        $id = (int) \App\Core\Database::connection()->lastInsertId();

        return ['key' => $plain, 'id' => $id];
    }

    public static function revoke(int $clinicId, int $keyId): void
    {
        QueryBuilder::table('api_keys')
            ->forClinic($clinicId)
            ->where('id', '=', $keyId)
            ->update(['is_active' => 0]);
    }

    /** @return array{clinic_id: int, scopes: list<string>, key_id: int}|null */
    public static function validate(string $bearer): ?array
    {
        if (!str_starts_with($bearer, 'mc_live_')) {
            return null;
        }

        $hash = hash('sha256', $bearer);
        $row = QueryBuilder::table('api_keys')
            ->where('key_hash', '=', $hash)
            ->where('is_active', '=', 1)
            ->first();

        if ($row === null) {
            return null;
        }

        if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        $scopes = $row['scopes'] ?? '[]';
        if (is_string($scopes)) {
            $scopes = json_decode($scopes, true) ?: [];
        }

        QueryBuilder::table('api_keys')
            ->where('id', '=', (int) $row['id'])
            ->update(['last_used' => date('Y-m-d H:i:s')]);

        return [
            'clinic_id' => (int) $row['clinic_id'],
            'scopes' => is_array($scopes) ? $scopes : [],
            'key_id' => (int) $row['id'],
        ];
    }

    public static function hasScope(array $auth, string $scope): bool
    {
        return in_array($scope, $auth['scopes'] ?? [], true);
    }
}
