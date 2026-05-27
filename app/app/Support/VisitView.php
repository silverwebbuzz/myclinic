<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * VisitView — single source of truth for "which sections show on this
 * clinic's visit screen?".
 *
 * Three signals, in priority order:
 *   1. clinic_settings.visible_modules (per-clinic override, JSON array)
 *   2. config/specialty_defaults.php   (specialty-keyed defaults)
 *   3. hardcoded ['vitals','case_specialty']  (safety net)
 *
 * Doctors expand/collapse sections via ghost-link reveals. The
 * recordSectionExpand() call increments a counter; on the 3rd expansion
 * the section auto-promotes into visible_modules so it stays open by
 * default from then on.
 *
 * Symptoms / Diagnosis / Prescription / Notes are ALWAYS visible — they
 * are the 4 fundamentals and not part of this gating.
 */
final class VisitView
{
    /** Sections that are always rendered, regardless of config. */
    public const ALWAYS_ON = ['symptoms', 'diagnosis', 'prescription', 'notes'];

    /** Modules controllable by specialty defaults / per-clinic toggles. */
    public const OPTIONAL = ['vitals', 'labs', 'photos', 'diet', 'consent', 'case_specialty'];

    /** Per-request memoization. */
    private static array $modulesCache = [];
    private static array $stateCache = [];

    /**
     * Get the list of optional modules currently visible for this clinic.
     * Returns module keys only (not the always-on ones).
     */
    public static function visibleModules(int $clinicId, string $specialty = ''): array
    {
        if (array_key_exists($clinicId, self::$modulesCache)) {
            return self::$modulesCache[$clinicId];
        }

        $row = self::loadSettings($clinicId);

        if ($row && !empty($row['visible_modules'])) {
            $decoded = json_decode((string) $row['visible_modules'], true);
            if (is_array($decoded)) {
                return self::$modulesCache[$clinicId] = self::sanitize($decoded);
            }
        }

        // Fallback to specialty defaults
        return self::$modulesCache[$clinicId] = self::sanitize(self::defaultsForSpecialty($specialty));
    }

    /**
     * Resolve specialty → default module list from the config file.
     */
    public static function defaultsForSpecialty(string $specialty): array
    {
        static $config = null;
        if ($config === null) {
            $config = require dirname(__DIR__, 2) . '/config/specialty_defaults.php';
        }
        return $config[$specialty] ?? $config['__default'] ?? ['vitals', 'case_specialty'];
    }

    /**
     * Returns 'expanded' | 'collapsed' | 'ghost'
     *   expanded   — section is in visible_modules AND user prefers it open
     *   collapsed  — section is in visible_modules but user folded it
     *   ghost      — section is NOT in visible_modules (revealed only via
     *                the +Add link for this visit)
     */
    public static function sectionState(int $clinicId, string $section): string
    {
        $visible = self::visibleModules($clinicId);
        if (!in_array($section, $visible, true)) {
            return 'ghost';
        }

        $state = self::loadSectionStateRow($clinicId);
        $entry = $state[$section] ?? null;

        return ($entry['state'] ?? 'expanded') === 'collapsed' ? 'collapsed' : 'expanded';
    }

    /**
     * Record that a user expanded a section. Used by the auto-promote
     * heuristic: after 3 reveals of a ghost section, promote into
     * visible_modules.
     *
     * For sections already in visible_modules, this just updates the
     * collapsed/expanded preference.
     */
    public static function recordSectionExpand(int $clinicId, string $section): void
    {
        $row = self::loadSettings($clinicId);
        if (!$row) {
            // Lazy-create the clinic_settings row.
            self::ensureRow($clinicId);
            $row = self::loadSettings($clinicId);
        }

        $state = $row && !empty($row['section_state'])
            ? (json_decode((string) $row['section_state'], true) ?: [])
            : [];

        $entry = $state[$section] ?? ['state' => 'expanded', 'expand_count' => 0];
        $entry['state'] = 'expanded';
        $entry['expand_count'] = ((int) ($entry['expand_count'] ?? 0)) + 1;
        $state[$section] = $entry;

        // Auto-promote into visible_modules after 3 ghost-link reveals.
        $visible = $row && !empty($row['visible_modules'])
            ? (json_decode((string) $row['visible_modules'], true) ?: [])
            : null;

        if (is_array($visible)
            && !in_array($section, $visible, true)
            && in_array($section, self::OPTIONAL, true)
            && (int) $entry['expand_count'] >= 3) {
            $visible[] = $section;
            self::persistVisibleModules($clinicId, array_values(array_unique($visible)));
        }

        self::persistSectionState($clinicId, $state);
        self::clearCache($clinicId);
    }

    /**
     * Record that a user collapsed a section. Doesn't affect visible_modules.
     */
    public static function recordSectionCollapse(int $clinicId, string $section): void
    {
        $row = self::loadSettings($clinicId);
        $state = $row && !empty($row['section_state'])
            ? (json_decode((string) $row['section_state'], true) ?: [])
            : [];

        $entry = $state[$section] ?? ['state' => 'collapsed', 'expand_count' => 0];
        $entry['state'] = 'collapsed';
        $state[$section] = $entry;

        self::ensureRow($clinicId);
        self::persistSectionState($clinicId, $state);
        self::clearCache($clinicId);
    }

    /**
     * Toggle a single module's visibility. Doctor-driven from settings UI.
     */
    public static function toggleModule(int $clinicId, string $moduleKey, bool $visible): void
    {
        if (!in_array($moduleKey, self::OPTIONAL, true)) {
            return;
        }

        $row = self::loadSettings($clinicId);
        $current = $row && !empty($row['visible_modules'])
            ? (json_decode((string) $row['visible_modules'], true) ?: [])
            : self::defaultsForSpecialty('');

        if ($visible && !in_array($moduleKey, $current, true)) {
            $current[] = $moduleKey;
        } elseif (!$visible) {
            $current = array_values(array_filter($current, static fn ($m) => $m !== $moduleKey));
        }

        self::ensureRow($clinicId);
        self::persistVisibleModules($clinicId, array_values(array_unique($current)));
        self::clearCache($clinicId);
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    private static function sanitize(array $modules): array
    {
        return array_values(array_filter(
            $modules,
            static fn ($m) => is_string($m) && in_array($m, self::OPTIONAL, true)
        ));
    }

    private static function loadSettings(int $clinicId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT clinic_id, visible_modules, section_state
               FROM clinic_settings WHERE clinic_id = :cid LIMIT 1'
        );
        $stmt->execute([':cid' => $clinicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function loadSectionStateRow(int $clinicId): array
    {
        if (array_key_exists($clinicId, self::$stateCache)) {
            return self::$stateCache[$clinicId];
        }
        $row = self::loadSettings($clinicId);
        $state = $row && !empty($row['section_state'])
            ? (json_decode((string) $row['section_state'], true) ?: [])
            : [];
        return self::$stateCache[$clinicId] = $state;
    }

    private static function ensureRow(int $clinicId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO clinic_settings (clinic_id) VALUES (:cid)'
        );
        $stmt->execute([':cid' => $clinicId]);
    }

    private static function persistVisibleModules(int $clinicId, array $modules): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clinic_settings SET visible_modules = :j WHERE clinic_id = :cid'
        );
        $stmt->execute([
            ':j' => json_encode($modules, JSON_UNESCAPED_SLASHES),
            ':cid' => $clinicId,
        ]);
    }

    private static function persistSectionState(int $clinicId, array $state): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clinic_settings SET section_state = :j WHERE clinic_id = :cid'
        );
        $stmt->execute([
            ':j' => json_encode($state, JSON_UNESCAPED_SLASHES),
            ':cid' => $clinicId,
        ]);
    }

    private static function clearCache(int $clinicId): void
    {
        unset(self::$modulesCache[$clinicId], self::$stateCache[$clinicId]);
    }

    /** Test seam — clears all caches. Don't call from prod code. */
    public static function flushCache(): void
    {
        self::$modulesCache = [];
        self::$stateCache = [];
    }
}
