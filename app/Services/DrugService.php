<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class DrugService
{
    /** @return list<array<string, mixed>> */
    public static function search(string $q, int $limit = 15): array
    {
        $q = trim($q);
        if ($q === '' || !Database::ping()) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, name, generic_name, strength, form, interactions, contraindications
             FROM drugs
             WHERE is_active = 1
             AND MATCH(name, generic_name) AGAINST(:q IN BOOLEAN MODE)
             LIMIT :lim',
        );
        $term = '+' . implode('* +', array_filter(explode(' ', $q))) . '*';
        $stmt->bindValue('q', $term);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }

        $like = '%' . $q . '%';
        $fallback = $pdo->prepare(
            'SELECT id, name, generic_name, strength, form, interactions, contraindications
             FROM drugs WHERE is_active = 1 AND (name LIKE ? OR generic_name LIKE ?) LIMIT ?',
        );
        $fallback->execute([$like, $like, $limit]);

        return $fallback->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        $row = \App\Core\QueryBuilder::table('drugs')->where('id', '=', $id)->first();

        return $row ?: null;
    }

    /** @param list<string> $allergies @return list<string> warnings */
    public static function allergyWarnings(array $drug, array $allergies): array
    {
        $warnings = [];
        $name = strtolower((string) ($drug['name'] ?? ''));
        $generic = strtolower((string) ($drug['generic_name'] ?? ''));
        foreach ($allergies as $allergen) {
            $a = strtolower(trim($allergen));
            if ($a === '') {
                continue;
            }
            if (str_contains($name, $a) || str_contains($generic, $a)) {
                $warnings[] = "Possible allergy: patient allergic to {$allergen}";
            }
        }

        return $warnings;
    }

    /** @param list<array<string, mixed>> $selectedDrugs @return list<string> */
    public static function interactionWarnings(array $drug, array $selectedDrugs): array
    {
        $warnings = [];
        $raw = $drug['interactions'] ?? null;
        if ($raw === null) {
            return [];
        }
        $interactions = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($interactions)) {
            return [];
        }

        foreach ($selectedDrugs as $other) {
            if ((int) ($other['id'] ?? 0) === (int) ($drug['id'] ?? 0)) {
                continue;
            }
            $otherName = strtolower((string) ($other['name'] ?? ''));
            foreach ($interactions as $entry) {
                $match = is_string($entry) ? $entry : ($entry['drug'] ?? '');
                if ($match !== '' && str_contains($otherName, strtolower($match))) {
                    $warnings[] = "Interaction: {$drug['name']} with {$other['name']}";
                }
            }
        }

        return $warnings;
    }
}
