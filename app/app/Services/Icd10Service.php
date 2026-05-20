<?php

declare(strict_types=1);

namespace App\Services;

final class Icd10Service
{
    /** @return list<array{code: string, label: string}> */
    public static function search(string $q, int $limit = 20): array
    {
        $q = strtolower(trim($q));
        $catalog = require dirname(__DIR__, 2) . '/config/icd10_common.php';
        if ($q === '') {
            return array_slice($catalog, 0, $limit);
        }

        $results = [];
        foreach ($catalog as $row) {
            if (str_contains(strtolower($row['code']), $q) || str_contains(strtolower($row['label']), $q)) {
                $results[] = $row;
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }
}
