<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * Single source of truth for the specialty catalog, backed by the
 * specialty_master table and editable from /admin/specialties.
 *
 * If the table is missing (e.g. before the seed is run), it falls back to a
 * minimal inline guard (GP only) so the app never white-screens.
 *
 * Custom clinical behaviour still lives in code (SpecialtyAdapter); this
 * catalog only carries the data + the has_custom_form flag.
 */
final class SpecialtyCatalog
{
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    /**
     * All active specialties, keyed by slug, in sort order.
     * Shape matches the legacy config: ['label','icon','prescription_mode','group',...].
     * @return array<string,array<string,mixed>>
     */
    public static function all(bool $includeInactive = false): array
    {
        if (self::$cache !== null && !$includeInactive) {
            return self::$cache;
        }

        $rows = self::fetch($includeInactive);
        if ($rows === null) {
            return self::fallback(); // table missing → old config file
        }

        $out = [];
        foreach ($rows as $r) {
            $out[$r['slug']] = [
                'label' => $r['label'],
                'plural_label' => $r['plural_label'] ?? ($r['label'] . 's'),
                'icon' => $r['icon'] ?? '🩺',
                'prescription_mode' => $r['prescription_mode'] ?? 'allopathic',
                'group' => $r['category'] ?? 'Other',
                'has_custom_form' => (bool) ($r['has_custom_form'] ?? false),
                'seo_safe' => (bool) ($r['seo_safe'] ?? true),
                'is_active' => (bool) ($r['is_active'] ?? true),
                'sort_order' => (int) ($r['sort_order'] ?? 100),
            ];
        }

        if (!$includeInactive) {
            self::$cache = $out;
        }

        return $out;
    }

    /** One specialty by slug (active or not), or null. */
    public static function get(string $slug): ?array
    {
        return self::all(true)[$slug] ?? null;
    }

    /** Display label for a slug, with a humanised fallback. */
    public static function label(string $slug): string
    {
        $row = self::get($slug);
        return $row['label'] ?? ucfirst(str_replace('_', ' ', $slug));
    }

    /** Resolved prescription mode for a slug. */
    public static function prescriptionMode(string $slug): string
    {
        return self::get($slug)['prescription_mode'] ?? 'allopathic';
    }

    /** Does this slug have a bespoke clinical form in code? */
    public static function hasCustomForm(string $slug): bool
    {
        return (bool) (self::get($slug)['has_custom_form'] ?? false);
    }

    /** Active specialties grouped by category (for grouped UIs). */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::all() as $slug => $spec) {
            $grouped[$spec['group']][$slug] = $spec;
        }
        return $grouped;
    }

    /** Clear the per-request cache (after admin edits). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return list<array<string,mixed>>|null  null = table unavailable */
    private static function fetch(bool $includeInactive): ?array
    {
        if (!Database::ping()) {
            return null;
        }
        try {
            $sql = 'SELECT * FROM specialty_master';
            if (!$includeInactive) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY sort_order ASC, label ASC';
            return Database::connection()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Minimal safety fallback if specialty_master is unavailable (e.g. before
     * the seed is run). Just enough for the app not to white-screen; the real
     * catalog lives in the DB and is managed from /admin/specialties.
     */
    private static function fallback(): array
    {
        return [
            'gp' => [
                'label' => 'General Practice', 'plural_label' => 'General physicians',
                'icon' => '🩺', 'prescription_mode' => 'allopathic',
                'group' => 'General & specialists', 'has_custom_form' => true,
                'seo_safe' => true, 'is_active' => true, 'sort_order' => 1,
            ],
        ];
    }
}
