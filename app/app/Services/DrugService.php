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
        if (!Database::ping()) {
            return [];
        }

        // Ranked queries depend on the Phase-3 `usage_count` column. On older
        // live DBs that column (or one of the catalog columns in the SELECT)
        // may be missing, which would 500 the autocomplete. Try the rich query
        // first; on ANY SQL error fall back to a minimal, schema-safe query so
        // the doctor still gets results instead of "Drug search failed".
        try {
            return self::runSearch($q, $limit, true);
        } catch (\Throwable $e) {
            error_log('[DrugService::search] ranked query failed, using safe fallback: ' . $e->getMessage());

            try {
                return self::runSearch($q, $limit, false);
            } catch (\Throwable $e2) {
                error_log('[DrugService::search] safe fallback also failed: ' . $e2->getMessage());

                return [];
            }
        }
    }

    /**
     * @param bool $ranked true = select catalog columns + ORDER BY usage_count
     *                      (needs Phase-3 schema); false = id/name only, name order.
     * @return list<array<string, mixed>>
     */
    private static function runSearch(string $q, int $limit, bool $ranked): array
    {
        $pdo = Database::connection();
        $cols = $ranked
            ? 'id, name, generic_name, strength, form, interactions, contraindications'
            : 'id, name';
        $order = $ranked ? 'usage_count DESC, name ASC' : 'name ASC';

        if ($q === '') {
            $stmt = $pdo->prepare("SELECT {$cols} FROM drugs WHERE is_active = 1 ORDER BY {$order} LIMIT :lim");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll() ?: [];
        }

        // Prefix match — fast and what doctors expect for autocomplete.
        $stmt = $pdo->prepare(
            "SELECT {$cols} FROM drugs
             WHERE is_active = 1 AND (name LIKE :p OR generic_name LIKE :p)
             ORDER BY {$order} LIMIT :lim",
        );
        $stmt->bindValue(':p', $q . '%');
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }

        // Contains-LIKE fallback for substring matches.
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare(
            "SELECT {$cols} FROM drugs
             WHERE is_active = 1 AND (name LIKE :p OR generic_name LIKE :p)
             ORDER BY {$order} LIMIT :lim",
        );
        $stmt->bindValue(':p', $like);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        $row = \App\Core\QueryBuilder::table('drugs')->where('id', '=', $id)->first();

        return $row ?: null;
    }

    /**
     * Smart defaults for the prescription builder: the clinic's most recent
     * frequency/duration/dose per drug, keyed by drug_id. The UI applies them
     * only to fields the doctor hasn't filled yet.
     *
     * Best-effort: returns [] if the Phase-2 columns or the index migration
     * (021) aren't in place yet.
     *
     * @param list<int> $drugIds
     * @return array<int, array<string, mixed>>
     */
    public static function lastUsedDefaults(int $clinicId, array $drugIds): array
    {
        $drugIds = array_values(array_filter(array_map('intval', $drugIds)));
        if ($drugIds === [] || !Database::ping()) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($drugIds), '?'));
            $stmt = Database::connection()->prepare(
                "SELECT rx.drug_id, rx.frequency_preset, rx.frequency, rx.duration_days,
                        rx.food_timing, rx.dose_unit, rx.dose_amount
                 FROM prescriptions rx
                 JOIN (SELECT drug_id, MAX(id) AS max_id FROM prescriptions
                       WHERE clinic_id = ? AND drug_id IN ({$placeholders})
                       GROUP BY drug_id) latest ON latest.max_id = rx.id",
            );
            $stmt->execute(array_merge([$clinicId], $drugIds));

            $defaults = [];
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $defaults[(int) $row['drug_id']] = [
                    'frequency_preset' => $row['frequency_preset'] ?? null,
                    'frequency' => $row['frequency'] ?? null,
                    'duration_days' => $row['duration_days'] ?? null,
                    'food_timing' => $row['food_timing'] ?? null,
                    'dose_unit' => $row['dose_unit'] ?? null,
                    'dose_amount' => $row['dose_amount'] ?? null,
                ];
            }

            return $defaults;
        } catch (\Throwable $e) {
            // Pre-Phase-2 schema — no defaults, autocomplete still works.
            return [];
        }
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
