<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * TemplateDiscovery — finds drug-combos that a doctor prescribes
 * together repeatedly and creates dormant prescription_templates
 * (auto_discovered=1, is_active=0) for the doctor to claim.
 *
 * Runs as a weekly cron. Idempotent — if a suggestion already exists
 * for the same doctor + drug-set, skip.
 *
 * Threshold: drug-set must appear 5+ times in the last 90 days.
 */
final class TemplateDiscovery
{
    public const MIN_OCCURRENCES = 5;
    public const LOOKBACK_DAYS = 90;
    public const SUGGESTIONS_LIMIT = 50;

    /**
     * Run the discovery sweep. Returns the count of new suggestions created.
     */
    public static function run(): int
    {
        $pdo = Database::connection();

        $candidates = self::findCandidates($pdo);
        $created = 0;

        foreach ($candidates as $cand) {
            if (self::suggestionExists($pdo, $cand)) continue;
            self::createSuggestion($pdo, $cand);
            $created++;
        }

        return $created;
    }

    /**
     * Find groups of drug_ids prescribed together by the same doctor at
     * least MIN_OCCURRENCES times in the lookback window.
     *
     * @return list<array{
     *   doctor_id: int,
     *   clinic_id: int,
     *   mode: string,
     *   drug_ids: string,
     *   times_prescribed: int
     * }>
     */
    private static function findCandidates(PDO $pdo): array
    {
        $sql = "
            WITH visit_bags AS (
              SELECT v.doctor_id,
                     v.clinic_id,
                     v.id AS visit_id,
                     p.mode,
                     GROUP_CONCAT(
                       COALESCE(p.drug_id, p.remedy_id)
                       ORDER BY COALESCE(p.drug_id, p.remedy_id)
                       SEPARATOR ','
                     ) AS drug_set
                FROM visits v
                JOIN prescriptions p ON p.visit_id = v.id
               WHERE v.visited_at > NOW() - INTERVAL :days DAY
                 AND v.status = 'completed'
                 AND (p.drug_id IS NOT NULL OR p.remedy_id IS NOT NULL)
            GROUP BY v.id, p.mode
            )
            SELECT doctor_id, clinic_id, mode, drug_set AS drug_ids,
                   COUNT(*) AS times_prescribed
              FROM visit_bags
             WHERE drug_set IS NOT NULL AND drug_set <> ''
          GROUP BY doctor_id, clinic_id, mode, drug_set
            HAVING times_prescribed >= :minocc
          ORDER BY times_prescribed DESC
             LIMIT :lim
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':days', self::LOOKBACK_DAYS, PDO::PARAM_INT);
        $stmt->bindValue(':minocc', self::MIN_OCCURRENCES, PDO::PARAM_INT);
        $stmt->bindValue(':lim', self::SUGGESTIONS_LIMIT, PDO::PARAM_INT);
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'doctor_id' => (int) $row['doctor_id'],
                'clinic_id' => (int) $row['clinic_id'],
                'mode' => (string) $row['mode'],
                'drug_ids' => (string) $row['drug_ids'],
                'times_prescribed' => (int) $row['times_prescribed'],
            ];
        }
        return $out;
    }

    /**
     * Has an auto-discovered template with this exact drug-set + doctor
     * already been created? We use the template's items to dedupe.
     */
    private static function suggestionExists(PDO $pdo, array $cand): bool
    {
        $idColumn = $cand['mode'] === 'homeopathic' ? 'remedy_id' : 'drug_id';

        // Build a canonical-sorted drug_set from each existing template
        // for this doctor and compare against $cand['drug_ids'].
        $stmt = $pdo->prepare(
            "SELECT t.id,
                    GROUP_CONCAT($idColumn ORDER BY $idColumn SEPARATOR ',') AS bag
               FROM prescription_templates t
               JOIN prescription_template_items i ON i.template_id = t.id
              WHERE t.doctor_id = :d AND t.mode = :m AND t.auto_discovered = 1
           GROUP BY t.id"
        );
        $stmt->execute([':d' => $cand['doctor_id'], ':m' => $cand['mode']]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['bag'] ?? '') === $cand['drug_ids']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Insert the suggested template (header + items) in is_active=0 state.
     * Doctor sees it as a chip "You often prescribe these N together —
     * save as template?" and one-click activates.
     */
    private static function createSuggestion(PDO $pdo, array $cand): void
    {
        $ids = array_filter(array_map('intval', explode(',', $cand['drug_ids'])));
        if ($ids === []) return;

        // Build a human label from the first 2 drug names.
        $names = self::namesFor($pdo, $cand['mode'], $ids);
        $first = array_slice($names, 0, 2);
        $name = 'Suggested: ' . implode(' + ', $first);
        if (count($names) > 2) {
            $name .= ' (+' . (count($names) - 2) . ')';
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO prescription_templates
                    (clinic_id, doctor_id, name, description, mode, use_count,
                     is_active, auto_discovered, created_at)
                 VALUES (:c, :d, :n, :desc, :m, 0, 0, 1, NOW())'
            );
            $ins->execute([
                ':c' => $cand['clinic_id'],
                ':d' => $cand['doctor_id'],
                ':n' => $name,
                ':desc' => 'You prescribed this combination ' . $cand['times_prescribed']
                         . ' times in the last 3 months.',
                ':m' => $cand['mode'],
            ]);
            $templateId = (int) $pdo->lastInsertId();

            $idColumn = $cand['mode'] === 'homeopathic' ? 'remedy_id' : 'drug_id';
            $insItem = $pdo->prepare(
                "INSERT INTO prescription_template_items
                    (template_id, mode, $idColumn, sort_order)
                 VALUES (:t, :m, :id, :o)"
            );
            foreach ($ids as $i => $drugId) {
                $insItem->execute([
                    ':t' => $templateId,
                    ':m' => $cand['mode'],
                    ':id' => $drugId,
                    ':o' => $i,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param list<int> $ids @return list<string> */
    private static function namesFor(PDO $pdo, string $mode, array $ids): array
    {
        $ids = array_values(array_filter($ids));
        if ($ids === []) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $table = $mode === 'homeopathic' ? 'remedies' : 'drugs';

        $stmt = $pdo->prepare("SELECT id, name FROM $table WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byId[(int) $row['id']] = (string) $row['name'];
        }

        $names = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) $names[] = $byId[$id];
        }
        return $names;
    }
}
