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
        if ($q === '') {
            $pdo = Database::connection();
            // Phase 3: rank by usage_count when available. Old rows have 0 →
            // alphabetical order kicks in as the tiebreaker.
            $stmt = $pdo->prepare(
                'SELECT id, name, generic_name, strength, form, interactions, contraindications
                 FROM drugs WHERE is_active = 1
                 ORDER BY usage_count DESC, name ASC
                 LIMIT :lim',
            );
            $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        }

        $pdo = Database::connection();
        // Phase 3: prefix LIKE first (fast on idx_drugs_usage), fall back to
        // fulltext if needed. Prefix is what doctors expect for autocomplete.
        $prefix = $q . '%';
        $stmt = $pdo->prepare(
            'SELECT id, name, generic_name, strength, form, interactions, contraindications
             FROM drugs
             WHERE is_active = 1 AND (name LIKE :p OR generic_name LIKE :p)
             ORDER BY usage_count DESC, name ASC
             LIMIT :lim',
        );
        $stmt->bindValue(':p', $prefix);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }

        // Fulltext fallback for multi-word / substring matches. Wrapped so
        // a missing FULLTEXT index falls through to LIKE instead of 500'ing.
        try {
            $stmt = $pdo->prepare(
                'SELECT id, name, generic_name, strength, form, interactions, contraindications
                 FROM drugs
                 WHERE is_active = 1
                 AND MATCH(name, generic_name) AGAINST(:q IN BOOLEAN MODE)
                 ORDER BY usage_count DESC
                 LIMIT :lim',
            );
            $term = '+' . implode('* +', array_filter(explode(' ', $q))) . '*';
            $stmt->bindValue(':q', $term);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll() ?: [];
            if ($rows !== []) {
                return $rows;
            }
        } catch (\Throwable $e) {
            // No FULLTEXT index — fall through to LIKE below.
        }

        // Final fallback: contains LIKE.
        $like = '%' . $q . '%';
        $fallback = $pdo->prepare(
            'SELECT id, name, generic_name, strength, form, interactions, contraindications
             FROM drugs WHERE is_active = 1 AND (name LIKE ? OR generic_name LIKE ?)
             ORDER BY usage_count DESC, name ASC LIMIT ?',
        );
        $fallback->execute([$like, $like, $limit]);

        return $fallback->fetchAll() ?: [];
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
