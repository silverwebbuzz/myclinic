<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * SymptomSearch — 3-layer symptom autocomplete.
 *
 * Search order:
 *   1. Personal hits (this doctor has used this label before)  — ranked first
 *   2. Master library prefix matches, specialty-boosted
 *   3. Synonym matches when prefix returns < 3 results
 *
 * If no match, the controller stores the term as a custom symptom on the
 * visit (source='custom'). Nightly cron promotes labels used by 10+
 * doctors across clinics into the master library.
 *
 * Performance:
 *   - idx_symptoms_master_label powers prefix scans
 *   - idx_personal_recent (doctor_id, last_used_at DESC) powers Layer 1
 *   - Synonyms are LIKE-scanned only when prefix < 3 hits to avoid full scans
 */
final class SymptomSearch
{
    public const DEFAULT_LIMIT = 8;

    /**
     * @return list<array{
     *   id: int|null,
     *   label: string,
     *   source: 'personal'|'master',
     *   master_id: int|null,
     *   specialty_match: bool
     * }>
     */
    public static function search(
        int $doctorId,
        int $clinicId,
        string $specialty,
        string $q,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        $q = trim($q);
        if ($q === '') {
            // Empty query → return doctor's most recent personal symptoms.
            return self::personalRecent($doctorId, $limit);
        }

        $needle = $q . '%';
        $contains = '%' . $q . '%';

        // Layer 1: doctor's personal (prefix match)
        $personal = self::personalHits($doctorId, $needle, max(3, (int) ($limit / 2)));

        // Layer 2: master library, specialty-boosted prefix
        $master = self::masterHits($specialty, $needle, $limit);

        // Merge — dedupe by label (case-insensitive)
        $merged = [];
        $seen = [];
        foreach (array_merge($personal, $master) as $row) {
            $key = mb_strtolower($row['label']);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $merged[] = $row;
            if (count($merged) >= $limit) break;
        }

        // Layer 3: synonym fallback when we have <3 hits
        if (count($merged) < 3) {
            foreach (self::synonymHits($specialty, $contains, $limit) as $row) {
                $key = mb_strtolower($row['label']);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $merged[] = $row;
                if (count($merged) >= $limit) break;
            }
        }

        return $merged;
    }

    /**
     * Resolve a free-text label to its canonical row.
     * Used when saving a visit_symptoms row — decides if this is master /
     * personal / custom and what FK to set.
     *
     * @return array{master_id: int|null, source: 'master'|'personal'|'custom'}
     */
    public static function resolveLabel(int $doctorId, int $clinicId, string $label): array
    {
        $clean = trim($label);
        if ($clean === '') {
            return ['master_id' => null, 'source' => 'custom'];
        }

        // Master? Case-insensitive exact-match on label first, then synonyms.
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id FROM symptoms_master
              WHERE is_active = 1 AND LOWER(label) = LOWER(:l)
              LIMIT 1'
        );
        $stmt->execute([':l' => $clean]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return ['master_id' => (int) $id, 'source' => 'master'];
        }

        // Synonym hit — plain LIKE on JSON text (MariaDB-safe; no JSON_SEARCH).
        $synNeedle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($clean)) . '%';
        $stmt = $pdo->prepare(
            "SELECT id FROM symptoms_master
              WHERE is_active = 1
                AND synonyms IS NOT NULL
                AND LOWER(synonyms) LIKE :needle
              LIMIT 1"
        );
        $stmt->execute([':needle' => $synNeedle]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return ['master_id' => (int) $id, 'source' => 'master'];
        }

        // Personal? Doctor's own.
        $stmt = $pdo->prepare(
            'SELECT id FROM symptoms_personal
              WHERE doctor_id = :d AND LOWER(label) = LOWER(:l) LIMIT 1'
        );
        $stmt->execute([':d' => $doctorId, ':l' => $clean]);
        if ($stmt->fetchColumn()) {
            return ['master_id' => null, 'source' => 'personal'];
        }

        return ['master_id' => null, 'source' => 'custom'];
    }

    /**
     * Bump the personal usage counter (or insert a new personal row).
     * Called when a doctor saves a custom or personal symptom on a visit.
     */
    public static function recordPersonalUse(int $doctorId, int $clinicId, string $label): void
    {
        $clean = trim($label);
        if ($clean === '') return;

        $stmt = Database::connection()->prepare(
            'INSERT INTO symptoms_personal (doctor_id, clinic_id, label, usage_count, last_used_at)
             VALUES (:d, :c, :l, 1, NOW())
             ON DUPLICATE KEY UPDATE
               usage_count = usage_count + 1,
               last_used_at = NOW()'
        );
        $stmt->execute([':d' => $doctorId, ':c' => $clinicId, ':l' => $clean]);
    }

    /**
     * Bump the master library's global usage counter. Fires when a
     * master-sourced symptom is saved on a visit.
     */
    public static function recordMasterUse(int $masterId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE symptoms_master
                SET global_usage_count = global_usage_count + 1
              WHERE id = :id'
        );
        $stmt->execute([':id' => $masterId]);
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    /** @return list<array{id: int|null, label: string, source: string, master_id: int|null, specialty_match: bool}> */
    private static function personalHits(int $doctorId, string $needle, int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, label, promoted_to_master_id AS master_id
               FROM symptoms_personal
              WHERE doctor_id = :d AND label LIKE :q
              ORDER BY usage_count DESC, last_used_at DESC
              LIMIT :n'
        );
        $stmt->bindValue(':d', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':q', $needle, PDO::PARAM_STR);
        $stmt->bindValue(':n', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'label' => (string) $row['label'],
                'source' => 'personal',
                'master_id' => $row['master_id'] ? (int) $row['master_id'] : null,
                'specialty_match' => false,
            ];
        }
        return $out;
    }

    /** @return list<array{id: int|null, label: string, source: string, master_id: int|null, specialty_match: bool}> */
    private static function masterHits(string $specialty, string $needle, int $limit): array
    {
        $specialty = strtolower(trim($specialty));
        $spLike = self::specialtyLikeNeedle($specialty);

        if ($specialty !== '') {
            $sql = "SELECT id, label, specialties, global_usage_count,
                           CASE WHEN LOWER(COALESCE(specialties, '')) LIKE ? THEN 1 ELSE 0 END AS specialty_match
                      FROM symptoms_master
                     WHERE is_active = 1 AND label LIKE ?
                  ORDER BY specialty_match DESC, global_usage_count DESC, label ASC
                     LIMIT ?";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([$spLike, $needle, $limit]);
        } else {
            $sql = "SELECT id, label, specialties, global_usage_count, 0 AS specialty_match
                      FROM symptoms_master
                     WHERE is_active = 1 AND label LIKE ?
                  ORDER BY global_usage_count DESC, label ASC
                     LIMIT ?";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([$needle, $limit]);
        }

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'label' => (string) $row['label'],
                'source' => 'master',
                'master_id' => (int) $row['id'],
                'specialty_match' => (bool) $row['specialty_match'],
            ];
        }
        return $out;
    }

    /** @return list<array{id: int|null, label: string, source: string, master_id: int|null, specialty_match: bool}> */
    private static function synonymHits(string $specialty, string $contains, int $limit): array
    {
        $specialty = strtolower(trim($specialty));
        $spLike = self::specialtyLikeNeedle($specialty);

        if ($specialty !== '') {
            $sql = "SELECT id, label, specialties, global_usage_count,
                           CASE WHEN LOWER(COALESCE(specialties, '')) LIKE ? THEN 1 ELSE 0 END AS specialty_match
                      FROM symptoms_master
                     WHERE is_active = 1
                       AND synonyms IS NOT NULL
                       AND LOWER(synonyms) LIKE LOWER(?)
                  ORDER BY specialty_match DESC, global_usage_count DESC
                     LIMIT ?";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([$spLike, $contains, $limit]);
        } else {
            $sql = "SELECT id, label, specialties, global_usage_count, 0 AS specialty_match
                      FROM symptoms_master
                     WHERE is_active = 1
                       AND synonyms IS NOT NULL
                       AND LOWER(synonyms) LIKE LOWER(?)
                  ORDER BY global_usage_count DESC
                     LIMIT ?";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([$contains, $limit]);
        }

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'label' => (string) $row['label'],
                'source' => 'master',
                'master_id' => (int) $row['id'],
                'specialty_match' => (bool) $row['specialty_match'],
            ];
        }
        return $out;
    }

    /** Doctor's most recent personal symptoms (empty-query suggestion list). */
    private static function personalRecent(int $doctorId, int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, label, promoted_to_master_id AS master_id
               FROM symptoms_personal
              WHERE doctor_id = :d
              ORDER BY last_used_at DESC
              LIMIT :n'
        );
        $stmt->bindValue(':d', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':n', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'label' => (string) $row['label'],
                'source' => 'personal',
                'master_id' => $row['master_id'] ? (int) $row['master_id'] : null,
                'specialty_match' => false,
            ];
        }
        return $out;
    }

    /**
     * Build the LIKE needle for matching a specialty inside the `specialties`
     * JSON-array text, e.g. specialty "gp" -> '%"gp"%'.
     */
    public static function specialtyLikeNeedle(string $specialty): string
    {
        $s = strtolower(trim($specialty));
        $s = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);

        return '%"' . $s . '"%';
    }
}
