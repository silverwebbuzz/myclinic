<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/** Customer delivery addresses (store_addresses), keyed by patient identity. */
final class AddressService
{
    public const MAX_ADDRESSES = 10;

    /** @return list<array<string, mixed>> */
    public static function list(int $identityId): array
    {
        $st = Database::connection()->prepare(
            'SELECT * FROM store_addresses WHERE identity_id = :i AND deleted_at IS NULL ORDER BY is_default DESC, id DESC'
        );
        $st->execute(['i' => $identityId]);

        return $st->fetchAll();
    }

    public static function find(int $identityId, int $id): ?array
    {
        return QueryBuilder::table('store_addresses')
            ->where('id', '=', $id)->where('identity_id', '=', $identityId)->where('deleted_at', 'IS')
            ->first();
    }

    /**
     * Validate + save a new address; becomes the default.
     *
     * @param array<string, mixed> $in
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function create(int $identityId, array $in): array
    {
        $row = [
            'identity_id' => $identityId,
            'label' => mb_substr(trim((string) ($in['label'] ?? '')), 0, 40) ?: null,
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 160),
            'phone' => VendorService::normalizePhone((string) ($in['phone'] ?? '')),
            'line1' => mb_substr(trim((string) ($in['line1'] ?? '')), 0, 255),
            'line2' => mb_substr(trim((string) ($in['line2'] ?? '')), 0, 255) ?: null,
            'landmark' => mb_substr(trim((string) ($in['landmark'] ?? '')), 0, 160) ?: null,
            'city' => mb_substr(trim((string) ($in['city'] ?? '')), 0, 120),
            'state' => mb_substr(trim((string) ($in['state'] ?? '')), 0, 120),
            'pincode' => preg_replace('/\D/', '', (string) ($in['pincode'] ?? '')) ?? '',
            'country' => 'IN',
            'is_default' => 1,
        ];
        foreach (['name' => 'Full name', 'line1' => 'House / building and street', 'city' => 'City', 'state' => 'State'] as $k => $label) {
            if ($row[$k] === '') {
                return ['ok' => false, 'error' => "$label is required."];
            }
        }
        // Canonical state name: decides CGST+SGST vs IGST on the invoice.
        $code = GstStates::codeFor($row['state']);
        if ($code === null) {
            return ['ok' => false, 'error' => 'Choose your state from the list.'];
        }
        $row['state'] = GstStates::name($code);
        if (!preg_match('/^\+91[6-9]\d{9}$/', $row['phone'])) {
            return ['ok' => false, 'error' => 'Enter a valid 10-digit mobile number for delivery.'];
        }
        if (!preg_match('/^[1-9]\d{5}$/', $row['pincode'])) {
            return ['ok' => false, 'error' => 'Enter a valid 6-digit pincode.'];
        }
        if (count(self::list($identityId)) >= self::MAX_ADDRESSES) {
            return ['ok' => false, 'error' => 'You have saved the maximum number of addresses. Remove one first.'];
        }
        Database::connection()->prepare('UPDATE store_addresses SET is_default = 0 WHERE identity_id = :i')
            ->execute(['i' => $identityId]);

        return ['ok' => true, 'id' => QueryBuilder::table('store_addresses')->insert($row)];
    }

    public static function remove(int $identityId, int $id): bool
    {
        return QueryBuilder::table('store_addresses')->where('id', '=', $id)->where('identity_id', '=', $identityId)
            ->update(['deleted_at' => date('Y-m-d H:i:s'), 'is_default' => 0]) > 0;
    }

    /** Snapshot stored on the order (addresses can change later). */
    public static function snapshot(array $a): array
    {
        return [
            'name' => $a['name'], 'phone' => $a['phone'], 'line1' => $a['line1'], 'line2' => $a['line2'],
            'landmark' => $a['landmark'], 'city' => $a['city'], 'state' => $a['state'],
            'pincode' => $a['pincode'], 'country' => $a['country'] ?? 'IN',
        ];
    }
}
