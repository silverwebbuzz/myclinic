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

    /** Full dosage-form words — too broad alone for catalog search (not abbrevs like syr/tab). */
    private const FULL_FORM_TERMS = [
        'tablet', 'tablets',
        'syrup',
        'capsule', 'capsules',
        'cream',
        'injection',
        'drops',
        'suspension',
        'ointment',
        'gel', 'lotion', 'powder', 'solution',
        'mg', 'ml', 'mcg', 'iu',
    ];

    /** Minimum length before we run a substring (%) catalog scan on 250k+ rows. */
    private const MIN_SUBSTRING_LEN = 4;

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

        // Skip the 250k-row catalog when the query is only a form word ("tablet",
        // "syrup") — clinic recents + same-visit lines are enough in that case.
        if (self::isGenericOnlyQuery($q)) {
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
            $id = !empty($row['id']) ? (int) $row['id'] : 0;
            $key = $id > 0 ? 'id:' . $id : 'name:' . mb_strtolower((string) ($row['name'] ?? ''));
            if ($key === 'name:' || isset($seen[$key])) {
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

    /** True when every token is a full dosage-form word (e.g. "tablet", "syrup") — not abbrevs. */
    public static function isGenericOnlyQuery(string $q): bool
    {
        $parts = self::rawQueryParts($q);
        if ($parts === []) {
            return false;
        }
        foreach ($parts as $part) {
            if (isset(self::FORM_ABBREVS[$part])) {
                return false;
            }
            if (!in_array($part, self::FULL_FORM_TERMS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tokens worth sending to the catalog index — drops form/strength noise.
     *
     * @return list<string>
     */
    private static function meaningfulTokens(string $q, bool $dropGeneric = true): array
    {
        $out = [];
        foreach (self::rawQueryParts($q) as $part) {
            // Abbrevs like "syr" / "tab" are how doctors filter by form — keep them.
            if (isset(self::FORM_ABBREVS[$part])) {
                $out[] = $part;
                continue;
            }
            if ($dropGeneric && in_array($part, self::FULL_FORM_TERMS, true)) {
                continue;
            }
            $out[] = $part;
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private static function rawQueryParts(string $q): array
    {
        $parts = preg_split('/\s+/', mb_strtolower(trim($q))) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || mb_strlen($part) < 2) {
                continue;
            }
            $out[] = $part;
        }

        return $out;
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
        $tokens = self::meaningfulTokens($query);
        if ($tokens === []) {
            return false;
        }

        $hay = mb_strtolower($name);
        $words = preg_split('/\s+/', $hay) ?: [];

        foreach ($tokens as $token) {
            if (str_contains($hay, $token)) {
                continue;
            }
            $expanded = self::FORM_ABBREVS[$token] ?? null;
            if ($expanded !== null && str_contains($hay, $expanded)) {
                continue;
            }
            $matched = false;
            foreach ($words as $word) {
                if (str_starts_with($word, $token)) {
                    $matched = true;
                    break;
                }
                if ($expanded !== null && str_starts_with($word, $expanded)) {
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
        $out = [];
        foreach (self::rawQueryParts($q) as $part) {
            $out[] = $part;
            $expanded = self::FORM_ABBREVS[$part] ?? null;
            if ($expanded !== null) {
                $out[] = $expanded;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<string> $patterns already-escaped LIKE patterns (with %)
     * @return list<array<string, mixed>>
     */
    private static function likeSearch(\PDO $pdo, string $cols, string $order, array $patterns, int $limit): array
    {
        if ($patterns === []) {
            return [];
        }
        $nameCol = 'LOWER(name)';
        $genericCol = 'LOWER(COALESCE(generic_name, \'\'))';
        $conds = [];
        $binds = [];
        foreach ($patterns as $i => $pattern) {
            $key = ':p' . $i;
            $conds[] = "({$nameCol} LIKE {$key} OR {$genericCol} LIKE {$key})";
            $binds[$key] = $pattern;
        }
        $sql = "SELECT {$cols} FROM drugs
                WHERE is_active = 1 AND (" . implode(' OR ', $conds) . ")
                ORDER BY {$order} LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        foreach ($binds as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
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

        $escape = static fn (string $s): string => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
        $nameCol = 'LOWER(name)';
        $genericCol = 'LOWER(COALESCE(generic_name, \'\'))';

        // Search on brand/generic tokens only — "althrocin tablet" → "althrocin".
        $tokens = self::meaningfulTokens($q);
        if ($tokens === []) {
            return [];
        }

        // FULLTEXT prefix search — fast on 250k+ rows (uses idx_drug_search).
        $ft = self::fulltextSearch($pdo, $cols, $order, $tokens, $limit);
        if ($ft !== []) {
            return $ft;
        }

        $needle = $escape($tokens[0]);

        // Single-token prefix — uses name index when present.
        if (count($tokens) === 1) {
            $token = $tokens[0];
            $rows = self::likeSearch($pdo, $cols, $order, [$needle . '%'], $limit);
            if ($rows !== []) {
                return $rows;
            }

            $abbrevExpanded = self::FORM_ABBREVS[$token] ?? null;
            if ($abbrevExpanded !== null) {
                $patterns = ['%' . $needle . '%', '%' . $escape($abbrevExpanded) . '%'];
                $rows = self::likeSearch($pdo, $cols, $order, $patterns, $limit);
                if ($rows !== []) {
                    return $rows;
                }
            }

            // Substring only for longer, non-generic tokens (avoid %tablet% table scan).
            if (mb_strlen($token) >= self::MIN_SUBSTRING_LEN) {
                return self::likeSearch($pdo, $cols, $order, ['%' . $needle . '%'], $limit);
            }

            return [];
        }

        // Multi-token: each meaningful word must appear in name or generic.
        $conds = [];
        $binds = [];
        foreach ($tokens as $i => $token) {
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

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<string> $tokens
     * @return list<array<string, mixed>>
     */
    private static function fulltextSearch(\PDO $pdo, string $cols, string $order, array $tokens, int $limit): array
    {
        if ($tokens === []) {
            return [];
        }

        $parts = [];
        foreach ($tokens as $token) {
            $clean = preg_replace('/[^a-z0-9]+/i', '', $token) ?? '';
            if ($clean === '' || mb_strlen($clean) < 2) {
                continue;
            }
            $parts[] = '+' . $clean . '*';
        }
        if ($parts === []) {
            return [];
        }

        try {
            $boolQ = implode(' ', $parts);
            $sql = "SELECT {$cols},
                           MATCH(name, generic_name) AGAINST(:q1 IN BOOLEAN MODE) AS ft_score
                      FROM drugs
                     WHERE is_active = 1
                       AND MATCH(name, generic_name) AGAINST(:q2 IN BOOLEAN MODE)
                  ORDER BY ft_score DESC, {$order}
                     LIMIT :lim";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':q1', $boolQ);
            $stmt->bindValue(':q2', $boolQ);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as &$row) {
                unset($row['ft_score']);
            }
            unset($row);

            return $rows;
        } catch (\Throwable) {
            return [];
        }
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
