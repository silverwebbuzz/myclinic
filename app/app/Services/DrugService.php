<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class DrugService
{
    /** Common Indian prescription shorthand → full form word. */
    private const FORM_ABBREVS = [
        'syr' => 'syrup',
        'syp' => 'syrup',
        'tab' => 'tablet',
        'tabs' => 'tablet',
        'cap' => 'capsule',
        'caps' => 'capsule',
        'inj' => 'injection',
        'crm' => 'cream',
        'drp' => 'drops',
        'susp' => 'suspension',
        'oint' => 'ointment',
    ];

    /** @return list<array<string, mixed>> */
    public static function search(string $q, int $limit = 15, ?int $clinicId = null): array
    {
        $q = trim($q);
        if (!Database::ping()) {
            return [];
        }

        $merged = [];
        $seen = [];

        // Layer 1: medicines this clinic has actually prescribed before (includes
        // free-typed names stored in prescriptions.dosage when no catalog drug_id).
        if ($clinicId !== null && $clinicId > 0 && $q !== '') {
            foreach (self::clinicRecentHits($clinicId, $q, $limit) as $row) {
                $key = mb_strtolower((string) $row['name']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $merged[] = $row;
            }
        }

        $remaining = max(0, $limit - count($merged));
        if ($remaining === 0) {
            return $merged;
        }

        // Layer 2: global drugs catalog.
        try {
            $catalog = self::runSearch($q, $remaining, true);
        } catch (\Throwable $e) {
            error_log('[DrugService::search] ranked query failed, using safe fallback: ' . $e->getMessage());

            try {
                $catalog = self::runSearch($q, $remaining, false);
            } catch (\Throwable $e2) {
                error_log('[DrugService::search] safe fallback also failed: ' . $e2->getMessage());
                $catalog = [];
            }
        }

        foreach ($catalog as $row) {
            $key = mb_strtolower((string) ($row['name'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $row;
            if (count($merged) >= $limit) {
                break;
            }
        }

        return $merged;
    }

    /**
     * Distinct medicine names this clinic has prescribed, ranked by recency.
     *
     * @return list<array{id: int|null, name: string, strength: ?string, source: string}>
     */
    private static function clinicRecentHits(int $clinicId, string $q, int $limit): array
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT rx.drug_id, rx.remedy_id,
                        COALESCE(d.name, r.name, NULLIF(TRIM(rx.dosage), '')) AS name,
                        d.strength, d.form,
                        MAX(rx.id) AS last_id
                   FROM prescriptions rx
              LEFT JOIN drugs d ON d.id = rx.drug_id
              LEFT JOIN remedies r ON r.id = rx.remedy_id
                  WHERE rx.clinic_id = :c
                    AND COALESCE(d.name, r.name, NULLIF(TRIM(rx.dosage), '')) IS NOT NULL
               GROUP BY COALESCE(d.name, r.name, NULLIF(TRIM(rx.dosage), '')),
                        rx.drug_id, rx.remedy_id, d.strength, d.form
               ORDER BY last_id DESC
                  LIMIT 80",
            );
            $stmt->execute([':c' => $clinicId]);
            $rows = $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            error_log('[DrugService::clinicRecentHits] ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || !self::nameMatchesQuery($name, $q)) {
                continue;
            }
            $out[] = [
                'id' => !empty($row['drug_id']) ? (int) $row['drug_id'] : null,
                'name' => $name,
                'strength' => $row['strength'] ?? null,
                'form' => $row['form'] ?? null,
                'source' => 'recent',
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** Multi-token match: "CXM syr" matches "CXM Syrup". */
    public static function nameMatchesQuery(string $name, string $query): bool
    {
        $hay = mb_strtolower($name);
        $tokens = self::queryTokens($query);
        if ($tokens === []) {
            return true;
        }

        $words = preg_split('/\s+/', $hay) ?: [];

        foreach ($tokens as $token) {
            if (str_contains($hay, $token)) {
                continue;
            }
            $matched = false;
            foreach ($words as $word) {
                if (str_starts_with($word, $token)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private static function queryTokens(string $q): array
    {
        $parts = preg_split('/\s+/', mb_strtolower(trim($q))) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $out[] = $part;
            $expanded = self::FORM_ABBREVS[$part] ?? null;
            if ($expanded !== null) {
                $out[] = $expanded;
            }
        }

        return array_values(array_unique($out));
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

        $needle = mb_strtolower($q);
        $escape = static fn (string $s): string => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
        $nameCol = 'LOWER(name)';
        $genericCol = 'LOWER(COALESCE(generic_name, \'\'))';

        // Multi-token: each word must appear in name or generic (e.g. "amox 500").
        $tokens = self::queryTokens($q);
        if (count($tokens) > 1) {
            $conds = [];
            $binds = [];
            foreach (array_values(array_unique($tokens)) as $i => $token) {
                $key = ':t' . $i;
                $like = '%' . $escape($token) . '%';
                $conds[] = "({$nameCol} LIKE {$key} OR {$genericCol} LIKE {$key})";
                $binds[$key] = $like;
            }
            $sql = "SELECT {$cols} FROM drugs
                    WHERE is_active = 1 AND " . implode(' AND ', $conds) . "
                    ORDER BY {$order} LIMIT :lim";
            $stmt = $pdo->prepare($sql);
            foreach ($binds as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll() ?: [];
            if ($rows !== []) {
                return $rows;
            }
        }

        // Prefix match — fast and what doctors expect for autocomplete.
        $stmt = $pdo->prepare(
            "SELECT {$cols} FROM drugs
             WHERE is_active = 1
               AND ({$nameCol} LIKE :p OR {$genericCol} LIKE :p)
             ORDER BY {$order} LIMIT :lim",
        );
        $stmt->bindValue(':p', $escape($needle) . '%');
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }

        // Contains-LIKE fallback for substring matches (e.g. "ubicar" → "Ubicar Cream").
        $like = '%' . $escape($needle) . '%';
        $stmt = $pdo->prepare(
            "SELECT {$cols} FROM drugs
             WHERE is_active = 1
               AND ({$nameCol} LIKE :p OR {$genericCol} LIKE :p)
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
