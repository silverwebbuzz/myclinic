<?php
// =====================================================================
// _clinic_siblings.php — per-city rebuild of the precomputed columns
// from 2026_09_29_clinic_sibling_ids.sql and 2026_09_29_search_text_fulltext.sql
//
// Used by:
//   - build_clinic_siblings.php (full rebuild, browser or CLI)
//   - insert_db.php (rebuilds just the cities it imported)
//
// sibling_ids: for every clinic row, the ids the clinic profile page would
// get from its "doctors at this clinic" fallback query
// (partials/directory_profile.php):
//     WHERE is_active = 1 AND status = 'OPERATIONAL'
//       AND city = :city AND id <> :id AND name LIKE '%<base name>%'
//     ORDER BY doctor_name ASC LIMIT 20
// Running that query once per clinic is exactly what is slow on the live
// site, so instead we load each city's rows ONCE (already sorted by
// doctor_name, by MySQL, so the collation order is MySQL's own) and do the
// substring match in PHP. Where PHP can't be sure it agrees with MySQL's
// collation (non-ASCII text, a backslash in the name, a case-sensitive
// column) the candidates are re-checked with the real LIKE, by primary key.
// =====================================================================

declare(strict_types=1);

// ecp_directory_clinic_base_name() / ecp_directory_entity_type() — reused,
// not copied, so the match rule can't drift from the profile page.
require_once __DIR__ . '/../partials/directory_profile.php';

/** Max siblings stored per clinic — same as the page query's LIMIT. */
const FD_SIB_LIMIT = 20;

/**
 * search_text for alias dd (directory_doctors) + sm (specialty_master).
 * Keep identical to the triggers/backfill in 2026_09_29_search_text_fulltext.sql.
 */
const FD_SEARCH_TEXT_EXPR = "CONCAT_WS(' ',
        dd.name, dd.doctor_name, dd.area, dd.city,
        REPLACE(dd.specialty, '_', ' '),
        sm.label, sm.plural_label, sm.url_slug)";

/** @return array<string, true> directory_doctors column names */
function fd_sib_columns(PDO $pdo): array {
    static $cols = null;
    if ($cols !== null) return $cols;
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM directory_doctors')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cols[(string) $c['Field']] = true;
    }
    return $cols;
}

/**
 * True when directory_doctors.name uses a case-insensitive collation (the
 * default utf8mb4 ones all do). Only then is a lowercase ASCII substring
 * test in PHP exactly equal to MySQL's LIKE.
 */
function fd_sib_name_is_ci(PDO $pdo): bool {
    static $ci = null;
    if ($ci !== null) return $ci;
    $stmt = $pdo->query(
        "SELECT collation_name FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'directory_doctors' AND column_name = 'name'"
    );
    $ci = str_ends_with(strtolower((string) $stmt->fetchColumn()), '_ci');
    return $ci;
}

/**
 * Loose, lowercase, accent-stripped form used to shortlist candidates for
 * the non-ASCII case. Null when ext-intl is missing (then every non-ASCII
 * name is simply re-checked in SQL).
 */
function fd_sib_fold(string $s): ?string {
    if (!class_exists('Normalizer')) return null;
    $n = Normalizer::normalize($s, Normalizer::FORM_KD);
    if ($n === false) return null;
    return mb_strtolower(preg_replace('/\p{Mn}+/u', '', $n) ?? $n);
}

/** @return list<string> every city with live listings (one entry per collation-equal spelling) */
function fd_sib_cities(PDO $pdo): array {
    $stmt = $pdo->query(
        "SELECT DISTINCT city FROM directory_doctors
         WHERE is_active = 1 AND status = 'OPERATIONAL' AND city <> ''
         ORDER BY city"
    );
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Recompute sibling_ids for every clinic in $city and refresh search_text
 * for every row in $city. Idempotent; only rows whose value changed are
 * written. Skips whichever column doesn't exist yet.
 *
 * @return array{rows:int, clinics:int, sibling_updates:int, sql_checks:int, search_updates:int}
 */
function fd_sib_rebuild_city(PDO $pdo, string $city): array {
    $stats = ['rows' => 0, 'clinics' => 0, 'sibling_updates' => 0, 'sql_checks' => 0, 'search_updates' => 0];
    $cols = fd_sib_columns($pdo);

    if (isset($cols['search_text'])) {
        $upd = $pdo->prepare(
            'UPDATE directory_doctors dd
             LEFT JOIN specialty_master sm ON sm.slug = dd.specialty AND sm.is_active = 1
             SET dd.search_text = ' . FD_SEARCH_TEXT_EXPR . ', dd.updated_at = dd.updated_at
             WHERE dd.city = :a1 AND NOT (dd.search_text <=> ' . FD_SEARCH_TEXT_EXPR . ')'
        );
        $upd->execute([':a1' => $city]);
        $stats['search_updates'] = $upd->rowCount();
    }

    if (!isset($cols['sibling_ids'])) return $stats;

    // Everything the page query could return for a clinic in this city, in
    // the page query's ORDER BY. Position in this list = sort rank, so a
    // clinic's siblings are just its matches in list order.
    $entityCol = isset($cols['entity_type']) ? ', entity_type' : '';
    $stmt = $pdo->prepare(
        "SELECT id, name, doctor_name, types, sibling_ids{$entityCol}
         FROM directory_doctors
         WHERE is_active = 1 AND status = 'OPERATIONAL' AND city = :a1
         ORDER BY doctor_name ASC, id ASC"
    );
    $stmt->execute([':a1' => $city]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['rows'] = count($rows);
    if (!$rows) return $stats;

    $ci = fd_sib_name_is_ci($pdo);
    $ids = []; $lower = []; $fold = [];
    foreach ($rows as $j => $r) {
        $ids[$j] = (int) $r['id'];
        if ($r['name'] === null) continue;              // NULL LIKE ... never matches
        $name = (string) $r['name'];
        if (!preg_match('/[\x80-\xFF]/', $name)) {
            $lower[$j] = strtolower($name);             // ASCII: exact under a _ci collation
        } else {
            $fold[$j] = fd_sib_fold($name);             // non-ASCII: shortlist, then SQL
        }
    }

    $byBase = [];           // base name => sorted match positions (clinic itself not yet removed)
    $writes = [];           // id => new sibling_ids JSON

    foreach ($rows as $i => $r) {
        if (ecp_directory_entity_type($r) !== 'clinic') continue;
        $stats['clinics']++;

        $base = ecp_directory_clinic_base_name($r);
        if (!isset($byBase[$base])) {
            $asciiBase = !preg_match('/[\x80-\xFF]/', $base);
            // The page only escapes % and _, so a '\' in the name acts as
            // LIKE's escape character: no PHP shortlist, MySQL checks all.
            $exact = $ci && $asciiBase && !str_contains($base, '\\');
            $needle = str_contains($base, '\\') ? null : ($asciiBase ? strtolower($base) : fd_sib_fold($base));
            $sure = []; $maybe = [];
            foreach ($lower as $j => $l) {
                if ($exact) {
                    if (str_contains($l, $needle)) $sure[] = $j;
                } elseif ($needle === null || str_contains($l, $needle)) {
                    $maybe[] = $j;
                }
            }
            foreach ($fold as $j => $f) {
                if ($f === null || $needle === null || str_contains($f, $needle)) $maybe[] = $j;
            }
            if ($maybe) {
                // Let MySQL decide the doubtful ones with the page's own LIKE.
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $base) . '%';
                $inList = implode(',', array_map(static fn (int $j): int => $ids[$j], $maybe));
                $check = $pdo->prepare(
                    "SELECT id FROM directory_doctors WHERE id IN ({$inList}) AND name LIKE :a1 ESCAPE '\\\\'"
                );
                $check->execute([':a1' => $like]);
                $hit = array_flip(array_map('intval', $check->fetchAll(PDO::FETCH_COLUMN)));
                $stats['sql_checks']++;
                foreach ($maybe as $j) {
                    if (isset($hit[$ids[$j]])) $sure[] = $j;
                }
            }
            sort($sure);
            $byBase[$base] = $sure;
        }

        $sib = [];
        foreach ($byBase[$base] as $j) {
            if ($j === $i) continue;                    // id <> :id
            $sib[] = $ids[$j];
            if (count($sib) >= FD_SIB_LIMIT) break;
        }
        $json = json_encode($sib);
        if ($r['sibling_ids'] !== $json) $writes[$ids[$i]] = $json;
    }

    if ($writes) {
        // updated_at = updated_at: this is derived data, not a listing edit.
        $upd = $pdo->prepare('UPDATE directory_doctors SET sibling_ids = :a1, updated_at = updated_at WHERE id = :a2');
        foreach (array_chunk($writes, 500, true) as $chunk) {
            $pdo->beginTransaction();
            try {
                foreach ($chunk as $id => $json) {
                    $upd->execute([':a1' => $json, ':a2' => $id]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        $stats['sibling_updates'] = count($writes);
    }

    return $stats;
}

/**
 * Delete the cached profile pages (partials/page_cache.php, key prof_*) —
 * they embed the "doctors at this clinic" list. Returns files removed.
 */
function fd_sib_purge_profile_cache(): int {
    $n = 0;
    foreach (glob(__DIR__ . '/../storage/page_cache/prof_*.html') ?: [] as $f) {
        if (@unlink($f)) $n++;
    }
    return $n;
}
