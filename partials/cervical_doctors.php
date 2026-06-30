<?php
// =====================================================================
// cervical_doctors.php — helper for the Cervical Cancer awareness page.
//
// Resolves the visitor's city (from ?city= or a sensible default) and
// returns up to N gynecologists in that city from directory_doctors, so
// the awareness page can close with "Find a gynecologist near you".
//
// Kept separate from db.php's big ecp_directory_doctors() (which loads up
// to 10k rows for the JS map). Here we only ever need ~10 cards, so we
// query narrowly: specialty = 'gyno', a single city, ordered by quality.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seo_slugs.php';
require_once __DIR__ . '/directory_profile.php';

/**
 * Pick the city to feature. Priority:
 *   1) ?city= query param (slug or name), if it actually has gynecologists
 *   2) the city with the most active gynecologists (sensible default)
 *
 * @return string|null Real city name (as stored), or null if none found.
 */
function ecp_cervical_pick_city(): ?string
{
    $db = ecp_db();
    if (!$db) return null;

    $requested = trim((string) ($_GET['city'] ?? ''));

    try {
        if ($requested !== '') {
            // Match either the raw city name or its slug (case-insensitive).
            $stmt = $db->prepare(
                "SELECT city, COUNT(*) AS n
                 FROM directory_doctors
                 WHERE is_active = 1 AND status = 'OPERATIONAL'
                   AND specialty = 'gyno'
                   AND city IS NOT NULL AND city <> ''
                   AND (LOWER(city) = LOWER(:raw)
                        OR REPLACE(LOWER(city), ' ', '-') = LOWER(:slug))
                 GROUP BY city
                 ORDER BY n DESC
                 LIMIT 1"
            );
            $stmt->execute(['raw' => $requested, 'slug' => $requested]);
            $hit = $stmt->fetchColumn(0);
            if ($hit !== false && $hit !== null) {
                return (string) $hit;
            }
        }

        // Default: city with the most gynecologists.
        $stmt = $db->query(
            "SELECT city
             FROM directory_doctors
             WHERE is_active = 1 AND status = 'OPERATIONAL'
               AND specialty = 'gyno'
               AND city IS NOT NULL AND city <> ''
             GROUP BY city
             ORDER BY COUNT(*) DESC
             LIMIT 1"
        );
        $city = $stmt->fetchColumn(0);
        return ($city !== false && $city !== null) ? (string) $city : null;
    } catch (Throwable $e) {
        error_log('[cervical] pick_city: ' . $e->getMessage());
        return null;
    }
}

/**
 * Up to $limit gynecologists in $city, shaped for the doctor cards.
 *
 * @return array<int, array<string, mixed>>
 */
function ecp_cervical_gynecologists(string $city, int $limit = 8): array
{
    $db = ecp_db();
    if (!$db || $city === '') return [];

    try {
        $stmt = $db->prepare(
            "SELECT id, place_id, name, doctor_name, specialty, entity_type, listing_slug,
                    city, state, area, address, gmaps_url, rating, reviews, types
             FROM directory_doctors
             WHERE is_active = 1 AND status = 'OPERATIONAL'
               AND specialty = 'gyno'
               AND LOWER(city) = LOWER(:city)
             ORDER BY is_claimed DESC, quality_score DESC, reviews DESC, rating DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':city', $city, PDO::PARAM_STR);
        $stmt->bindValue(':lim', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[cervical] gynecologists: ' . $e->getMessage());
        return [];
    }

    $out = [];
    foreach ($rows as $r) {
        $doctorName  = trim((string) ($r['doctor_name'] ?? ''));
        $displayName = $doctorName !== '' ? $doctorName : (string) ($r['name'] ?? '');

        // Initials for the avatar chip.
        $forInitials = preg_replace('/^Dr\.?\s+/i', '', $displayName) ?? $displayName;
        $parts = preg_split('/\s+/', trim($forInitials)) ?: [''];
        $first = $parts[0] ?? '';
        $last  = $parts[count($parts) - 1] ?? '';
        $fi = $first !== '' ? mb_substr($first, 0, 1) : 'D';
        $li = ($last !== '' && $last !== $first) ? mb_substr($last, 0, 1) : '';

        $out[] = [
            'name'        => $displayName,
            'initials'    => strtoupper($fi . $li),
            'area'        => trim((string) ($r['area'] ?? '')),
            'city'        => trim((string) ($r['city'] ?? '')),
            'address'     => trim((string) ($r['address'] ?? '')),
            'rating'      => $r['rating'] !== null ? (float) $r['rating'] : null,
            'reviews'     => (int) ($r['reviews'] ?? 0),
            'profile_url' => ecp_directory_profile_url($r),
            'gmaps_url'   => trim((string) ($r['gmaps_url'] ?? '')),
        ];
    }
    return $out;
}
