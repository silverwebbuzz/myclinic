<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class LabCatalogService
{
    /** @return list<array<string, mixed>> */
    public static function listForClinic(int $clinicId): array
    {
        if (!Database::ping()) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM lab_tests_catalog
             WHERE (clinic_id IS NULL OR clinic_id = ?) AND is_active = 1
             ORDER BY category, test_name',
        );
        $stmt->execute([$clinicId]);

        return array_map([self::class, 'hydrate'], $stmt->fetchAll() ?: []);
    }

    public static function find(int $testId): ?array
    {
        $row = QueryBuilder::table('lab_tests_catalog')->where('id', '=', $testId)->first();

        return $row ? self::hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): array
    {
        if (is_string($row['parameters'] ?? null)) {
            $row['parameters'] = json_decode($row['parameters'], true) ?: [];
        }

        return $row;
    }
}
