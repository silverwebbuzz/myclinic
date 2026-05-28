<?php
// =====================================================================
// seo_slugs.php — canonical slug/lookup helpers for SEO URLs.
//
// Canonical URL patterns:
//   /find-a-doctor                       — global
//   /find-a-doctor/{city}                — e.g. /find-a-doctor/mumbai
//   /find-a-doctor/{spec}-in-{city}      — e.g. /find-a-doctor/dermatologist-in-mumbai
//
// We canonicalize aggressively: lowercase, ASCII-only, hyphen-joined.
// Anything else (Mumbai, mumbai-city, Mumbai%20India) is treated as a
// non-canonical form and 301-redirected to its canonical equivalent.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------
// Slugification
// ---------------------------------------------------------------------

/**
 * Slug a string for URL use. Lossy: drops everything that isn't a-z 0-9.
 *   "Mumbai"           → "mumbai"
 *   "New Delhi"        → "new-delhi"
 *   "Thiruvananthapuram" → "thiruvananthapuram"
 */
function ecp_slug(string $raw): string {
    $s = strtolower(trim($raw));
    // Transliterate common accented chars if possible (best-effort).
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) $s = $t;
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
    return trim($s, '-');
}

// ---------------------------------------------------------------------
// Specialty <-> slug
// ---------------------------------------------------------------------

/**
 * Specialty slug map. Key = slug as it appears in URL.
 * Value = ['db' => directory_doctors.specialty value, 'label' => display label].
 *
 * URL slugs lean SEO-friendly ("dermatologist") while DB values
 * match what the fetcher used ("derma"). Keep this in sync with
 * partials/find-doctor-data.php.
 */
/**
 * Canonical specialty map: url-slug => ['db', 'label', 'plural', 'safe'].
 *
 * Single source of truth is the specialty_master table (admin-managed), so the
 * homepage, find-a-doctor, and sitemap all show identical names. Falls back to
 * the hardcoded list below if the table is missing (pre-migration). Cached per
 * request.
 */
function ecp_specialty_map(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $db = function_exists('ecp_db') ? ecp_db() : null;
    if ($db) {
        try {
            $rows = $db->query(
                "SELECT url_slug, slug, label, plural_label, seo_safe
                   FROM specialty_master
                  WHERE is_active = 1 AND url_slug IS NOT NULL AND url_slug <> ''
                  ORDER BY sort_order ASC, label ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $map = [];
                foreach ($rows as $r) {
                    $map[$r['url_slug']] = [
                        'db'     => $r['slug'],
                        'label'  => $r['label'],
                        'plural' => $r['plural_label'] ?: ($r['label'] . 's'),
                        'safe'   => (bool) $r['seo_safe'],
                    ];
                }
                return $cache = $map;
            }
        } catch (Throwable $e) { /* table missing → fall back below */ }
    }

    return $cache = ecp_specialty_map_fallback();
}

/** Hardcoded fallback if specialty_master is unavailable. */
function ecp_specialty_map_fallback(): array {
    return [
        // canonical url slug => [ db value, display label, plural label ]
        // Each row also has 'safe' => true|false. When false the spec is
        // indexed by search engines (its city pages still 200 OK) but is
        // omitted from the homepage hero, footer mega-links, and chip rows.
        // Used for brand-sensitive specialties like sexology.
        'general-physician' => ['db' => 'gp',             'label' => 'General physician',  'plural' => 'General physicians',  'safe' => true],
        'family-medicine'   => ['db' => 'family_medicine','label' => 'Family medicine doctor', 'plural' => 'Family medicine doctors', 'safe' => true],
        'ophthalmologist'   => ['db' => 'eye',            'label' => 'Ophthalmologist',    'plural' => 'Ophthalmologists',    'safe' => true],
        'dermatologist'     => ['db' => 'derma',          'label' => 'Dermatologist',      'plural' => 'Dermatologists',      'safe' => true],
        'cosmetologist'     => ['db' => 'cosmetology',    'label' => 'Cosmetologist',      'plural' => 'Cosmetologists',      'safe' => true],
        'trichologist'      => ['db' => 'trichology',     'label' => 'Trichologist',       'plural' => 'Trichologists',       'safe' => true],
        'cardiologist'      => ['db' => 'cardio',         'label' => 'Cardiologist',       'plural' => 'Cardiologists',       'safe' => true],
        'psychiatrist'      => ['db' => 'psychiatrist',   'label' => 'Psychiatrist',       'plural' => 'Psychiatrists',       'safe' => true],
        'gastroenterologist'=> ['db' => 'gastro',         'label' => 'Gastroenterologist', 'plural' => 'Gastroenterologists', 'safe' => true],
        'hepatologist'      => ['db' => 'hepatology',     'label' => 'Hepatologist',       'plural' => 'Hepatologists',       'safe' => true],
        'ent-specialist'    => ['db' => 'ent',            'label' => 'ENT specialist',     'plural' => 'ENT specialists',     'safe' => true],
        'gynecologist'      => ['db' => 'gyno',           'label' => 'Gynecologist',       'plural' => 'Gynecologists',       'safe' => true],
        'fertility-specialist'=>['db' => 'fertility',     'label' => 'Fertility specialist','plural' => 'Fertility specialists','safe' => true],
        'neurologist'       => ['db' => 'neuro',          'label' => 'Neurologist',        'plural' => 'Neurologists',        'safe' => true],
        'urologist'         => ['db' => 'urologist',      'label' => 'Urologist',          'plural' => 'Urologists',          'safe' => true],
        'andrologist'       => ['db' => 'andrology',      'label' => 'Andrologist',        'plural' => 'Andrologists',        'safe' => true],
        // Sexology: indexable + city-page-accessible, but hidden from
        // marketing surfaces (homepage tiles, footer, hero chip row).
        'sexologist'        => ['db' => 'sexology',       'label' => 'Sexologist',         'plural' => 'Sexologists',         'safe' => false],
        'pediatrician'      => ['db' => 'peds',           'label' => 'Pediatrician',       'plural' => 'Pediatricians',       'safe' => true],
        'orthopedic'        => ['db' => 'ortho',          'label' => 'Orthopedic doctor',  'plural' => 'Orthopedic doctors',  'safe' => true],
        'sports-medicine'   => ['db' => 'sports_medicine','label' => 'Sports medicine doctor','plural' => 'Sports medicine doctors','safe' => true],
        'rheumatologist'    => ['db' => 'rheumatology',   'label' => 'Rheumatologist',     'plural' => 'Rheumatologists',     'safe' => true],
        'pain-management'   => ['db' => 'pain_management','label' => 'Pain management specialist','plural' => 'Pain management specialists','safe' => true],
        'oncologist'        => ['db' => 'oncology',       'label' => 'Oncologist',         'plural' => 'Oncologists',         'safe' => true],
        'hematologist'      => ['db' => 'hematology',     'label' => 'Hematologist',       'plural' => 'Hematologists',       'safe' => true],
        'pulmonologist'     => ['db' => 'pulmonology',    'label' => 'Pulmonologist',      'plural' => 'Pulmonologists',      'safe' => true],
        'allergist'         => ['db' => 'allergy',        'label' => 'Allergist',          'plural' => 'Allergists',          'safe' => true],
        'nephrologist'      => ['db' => 'nephrology',     'label' => 'Nephrologist',       'plural' => 'Nephrologists',       'safe' => true],
        'diabetologist'     => ['db' => 'diabetology',    'label' => 'Diabetologist',      'plural' => 'Diabetologists',      'safe' => true],
        'endocrinologist'   => ['db' => 'endocrinology',  'label' => 'Endocrinologist',    'plural' => 'Endocrinologists',    'safe' => true],
        'neurosurgeon'      => ['db' => 'neurosurgery',   'label' => 'Neurosurgeon',       'plural' => 'Neurosurgeons',       'safe' => true],
        'spine-surgeon'     => ['db' => 'spine',          'label' => 'Spine surgeon',      'plural' => 'Spine surgeons',      'safe' => true],
        'gi-surgeon'        => ['db' => 'gi_surgery',     'label' => 'GI surgeon',         'plural' => 'GI surgeons',         'safe' => true],
        'general-surgeon'   => ['db' => 'general_surgery','label' => 'General surgeon',    'plural' => 'General surgeons',    'safe' => true],
        'plastic-surgeon'   => ['db' => 'plastic_surgery','label' => 'Plastic surgeon',    'plural' => 'Plastic surgeons',    'safe' => true],
        'bariatric-surgeon' => ['db' => 'bariatric',      'label' => 'Bariatric surgeon',  'plural' => 'Bariatric surgeons',  'safe' => true],
        'vascular-surgeon'  => ['db' => 'vascular',       'label' => 'Vascular surgeon',   'plural' => 'Vascular surgeons',   'safe' => true],
        'radiologist'       => ['db' => 'radiology',      'label' => 'Radiologist',        'plural' => 'Radiologists',        'safe' => true],
        'critical-care'     => ['db' => 'critical_care',  'label' => 'Critical care specialist', 'plural' => 'Critical care specialists', 'safe' => true],
        'dentist'           => ['db' => 'dental',         'label' => 'Dentist',            'plural' => 'Dentists'],
        'prosthodontist'    => ['db' => 'prosthodontist', 'label' => 'Prosthodontist',     'plural' => 'Prosthodontists'],
        'orthodontist'      => ['db' => 'orthodontist',   'label' => 'Orthodontist',       'plural' => 'Orthodontists'],
        'pediatric-dentist' => ['db' => 'pediatric_dentist','label' => 'Pediatric dentist','plural' => 'Pediatric dentists'],
        'endodontist'       => ['db' => 'endodontist',    'label' => 'Endodontist',        'plural' => 'Endodontists'],
        'implantologist'    => ['db' => 'implantologist', 'label' => 'Dental implant specialist', 'plural' => 'Dental implant specialists'],
        'ayurveda'          => ['db' => 'ayurveda',       'label' => 'Ayurveda doctor',    'plural' => 'Ayurveda doctors'],
        'homeopathy'        => ['db' => 'homeopathy',     'label' => 'Homeopathy doctor',  'plural' => 'Homeopathy doctors'],
        'siddha'            => ['db' => 'siddha',         'label' => 'Siddha doctor',      'plural' => 'Siddha doctors'],
        'unani'             => ['db' => 'unani',          'label' => 'Unani doctor',       'plural' => 'Unani doctors'],
        'naturopathy'       => ['db' => 'naturopathy',    'label' => 'Naturopathy doctor', 'plural' => 'Naturopathy doctors'],
        'acupuncturist'     => ['db' => 'acupuncturist',  'label' => 'Acupuncturist',      'plural' => 'Acupuncturists'],
        'physiotherapist'   => ['db' => 'physio',         'label' => 'Physiotherapist',    'plural' => 'Physiotherapists'],
        'psychologist'      => ['db' => 'psychologist',   'label' => 'Psychologist',       'plural' => 'Psychologists'],
        'audiologist'       => ['db' => 'audiologist',    'label' => 'Audiologist',        'plural' => 'Audiologists'],
        'speech-therapist'  => ['db' => 'speech',         'label' => 'Speech therapist',   'plural' => 'Speech therapists'],
        'dietitian'         => ['db' => 'dietitian',      'label' => 'Dietitian',          'plural' => 'Dietitians'],
    ];
}

/** url-slug → ['db' => ..., 'label' => ..., 'plural' => ..., 'safe' => bool] or null */
function ecp_specialty_by_slug(string $slug): ?array {
    $slug = strtolower(trim($slug));
    $map = ecp_specialty_map();
    if (!isset($map[$slug])) return null;
    // Default 'safe' to true if the row predates that field.
    return $map[$slug] + ['safe' => true];
}

/**
 * @return array<string,array{db:string,label:string,plural:string,safe:bool}>
 *         The catalog filtered to brand-safe specialties only.
 *         Use this on homepage hero, footer mega-cities, and SEO hero chips.
 *         The full map is still used for indexing + direct URL access.
 */
function ecp_specialty_map_safe(): array {
    $out = [];
    foreach (ecp_specialty_map() as $slug => $info) {
        if (($info['safe'] ?? true) === false) continue;
        $out[$slug] = $info + ['safe' => true];
    }
    return $out;
}

/** db-value → url-slug. Returns null if not in our SEO map (won't be linked). */
function ecp_slug_for_db_specialty(?string $dbValue): ?string {
    if (!$dbValue) return null;
    foreach (ecp_specialty_map() as $slug => $info) {
        if ($info['db'] === $dbValue) return $slug;
    }
    return null;
}

// ---------------------------------------------------------------------
// City lookup (DB-driven, cached per request)
// ---------------------------------------------------------------------

/**
 * Resolve a city URL slug to its real city name as stored in directory_doctors.
 *   "mumbai"  → ['city' => 'Mumbai', 'state' => 'Maharashtra']
 *   "new-delhi" → ['city' => 'New Delhi', 'state' => 'Delhi NCR']
 *
 * Strategy: query distinct city values, slug each, match.
 * The result is cached for the request so multiple lookups don't requery.
 */
function ecp_city_by_slug(string $slug): ?array {
    static $bySlug = null;
    if ($bySlug === null) $bySlug = _ecp_city_slug_index();
    return $bySlug[strtolower(trim($slug))] ?? null;
}

/** Reverse direction: real city name → URL slug (using the canonical map). */
function ecp_slug_for_city(string $cityName): string {
    return ecp_slug($cityName);
}

/**
 * Build the slug→city index in one query.
 */
function _ecp_city_slug_index(): array {
    $db = ecp_db();
    if (!$db) return [];

    try {
        $stmt = $db->query(
            "SELECT DISTINCT city, state
             FROM directory_doctors
             WHERE is_active = 1 AND status = 'OPERATIONAL'
               AND city IS NOT NULL AND city <> ''"
        );
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $slug = ecp_slug((string) $r['city']);
            if ($slug === '') continue;
            // First-wins on collisions (rare: e.g. multiple states with same city name).
            if (!isset($out[$slug])) {
                $out[$slug] = ['city' => $r['city'], 'state' => $r['state']];
            }
        }
        return $out;
    } catch (Throwable $e) {
        error_log('[seo city index] ' . $e->getMessage());
        return [];
    }
}

// ---------------------------------------------------------------------
// Count helpers — used by the "is this page worth indexing?" check
// ---------------------------------------------------------------------

/**
 * Doctor count for a (city, specialty) pair. Pass specialty=null for "all".
 * Cached per request.
 */
function ecp_count_doctors(?string $cityName, ?string $dbSpecialty): int {
    static $cache = [];
    $key = ($cityName ?? '*') . '|' . ($dbSpecialty ?? '*');
    if (isset($cache[$key])) return $cache[$key];

    $db = ecp_db();
    if (!$db) return $cache[$key] = 0;

    $sql = "SELECT COUNT(*) FROM directory_doctors
            WHERE is_active = 1 AND status = 'OPERATIONAL'";
    $params = [];
    if ($cityName !== null) {
        $sql .= " AND city = :c";
        $params['c'] = $cityName;
    }
    if ($dbSpecialty !== null) {
        $sql .= " AND specialty = :s";
        $params['s'] = $dbSpecialty;
    }
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $cache[$key] = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return $cache[$key] = 0;
    }
}

/**
 * Threshold below which we 404 + noindex a (city, specialty) page.
 * Tweak in one place: thin pages hurt SEO.
 */
const ECP_SEO_MIN_LISTINGS = 5;

// ---------------------------------------------------------------------
// Parse one of the canonical SEO URLs into filter params + meta info.
// Returns null for invalid combos. Returns ['redirect' => '/canonical/url']
// for non-canonical-but-recognizable forms.
// ---------------------------------------------------------------------

/**
 * @param string $path  the path AFTER /find-a-doctor (no leading slash, no query)
 *
 * @return array{
 *   match?:array{city?:array, specialty?:array, canonical:string, title:string, h1:string, intro:string, doctor_count:int},
 *   redirect?:string,
 *   notfound?:bool
 * }
 */
function ecp_resolve_seo_path(string $path): array {
    $path = trim($path, "/ \t\n\r\0\x0B");
    if ($path === '') return ['match' => null];   // bare /find-a-doctor

    // Already lowercase + hyphen? If not, redirect to canonical form.
    $lower = strtolower($path);
    if ($lower !== $path) {
        return ['redirect' => '/find-a-doctor/' . $lower];
    }

    // --- Pattern 1: {specialty}-in-{city}, e.g. "dermatologist-in-mumbai"
    if (str_contains($path, '-in-')) {
        // Greedy split on last "-in-" so multi-word specialties work
        // (e.g. "pediatric-dentist-in-mumbai" → spec=pediatric-dentist, city=mumbai).
        $pos = strrpos($path, '-in-');
        if ($pos !== false) {
            $specSlug = substr($path, 0, $pos);
            $citySlug = substr($path, $pos + 4);
            return _ecp_resolve_city_spec($citySlug, $specSlug, $path);
        }
    }

    // --- Pattern 2: {city}/{specialty} — alternative form, 301 to canonical
    if (str_contains($path, '/')) {
        [$citySlug, $specSlug] = explode('/', $path, 2);
        $canonical = '/find-a-doctor/' . $specSlug . '-in-' . $citySlug;
        return ['redirect' => $canonical];
    }

    // --- Pattern 3: just a city
    return _ecp_resolve_city_only($path);
}

function _ecp_resolve_city_only(string $citySlug): array {
    $city = ecp_city_by_slug($citySlug);
    if ($city === null) return ['notfound' => true];

    $count = ecp_count_doctors($city['city'], null);
    if ($count < ECP_SEO_MIN_LISTINGS) return ['notfound' => true];

    $cityName = $city['city'];
    return [
        'match' => [
            'city'         => $city,
            'specialty'    => null,
            'canonical'    => '/find-a-doctor/' . $citySlug,
            'title'        => "Best Doctors in {$cityName} — Book Appointment Online | eClinicPro",
            'h1'           => "Best Doctors in {$cityName}",
            'intro'        => "Find and book appointments with top-rated doctors in {$cityName}. "
                            . "Verified profiles, real reviews, transparent fees. "
                            . "No call needed — book online in 30 seconds.",
            'doctor_count' => $count,
            'filter_city'  => $cityName,
            'filter_spec'  => null,
        ],
    ];
}

/**
 * Top cities in absolute terms — used in the hero "Change city" pill.
 * Always returns same list regardless of current page so user can switch
 * to any major city. Returns at most $limit.
 */
function ecp_seo_top_cities(int $limit = 12): array {
    static $cache = null;
    if ($cache !== null) return array_slice($cache, 0, $limit);

    $db = ecp_db();
    if (!$db) return [];
    try {
        $stmt = $db->query(
            "SELECT city, state, COUNT(*) AS n
             FROM directory_doctors
             WHERE is_active = 1 AND status = 'OPERATIONAL'
               AND city IS NOT NULL AND city <> ''
             GROUP BY city, state
             HAVING n >= 5
             ORDER BY n DESC
             LIMIT 50"
        );
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $slug = ecp_slug((string) $r['city']);
            if ($slug === '') continue;
            $out[] = [
                'city'  => $r['city'],
                'state' => $r['state'] ?? '',
                'slug'  => $slug,
                'count' => (int) $r['n'],
            ];
        }
        $cache = $out;
        return array_slice($cache, 0, $limit);
    } catch (Throwable $e) {
        return $cache = [];
    }
}

/**
 * Top cities for the "Also browse" block on an SEO page.
 * Skips the current city. Returns at most 8.
 */
function ecp_seo_also_cities(array $seoMeta): array {
    $db = ecp_db();
    if (!$db) return [];
    $currentCity = $seoMeta['city']['city'] ?? '';
    $specDb      = $seoMeta['specialty']['db'] ?? null;
    $specSlug    = $specDb ? ecp_slug_for_db_specialty($specDb) : null;

    // If we're on a city+specialty page, pick top cities for THAT specialty.
    // Else top cities overall.
    $sql = "SELECT city, COUNT(*) AS n FROM directory_doctors
            WHERE is_active = 1 AND status = 'OPERATIONAL'
              AND city IS NOT NULL AND city <> '' AND city <> :exclude";
    $params = ['exclude' => $currentCity];
    if ($specDb) {
        $sql .= " AND specialty = :s";
        $params['s'] = $specDb;
    }
    $sql .= " GROUP BY city HAVING n >= 5 ORDER BY n DESC LIMIT 8";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cSlug = ecp_slug((string) $r['city']);
            if ($cSlug === '') continue;
            $url = $specSlug
                ? "/find-a-doctor/{$specSlug}-in-{$cSlug}"
                : "/find-a-doctor/{$cSlug}";
            $out[] = ['city' => $r['city'], 'url' => $url];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Other specialties available in this city — for "Also browse" block.
 * Returns at most 10.
 */
function ecp_seo_also_specialties(array $seoMeta): array {
    $db = ecp_db();
    if (!$db) return [];
    $cityName    = $seoMeta['city']['city'] ?? '';
    if ($cityName === '') return [];
    $currentSpec = $seoMeta['specialty']['db'] ?? '';
    $citySlug    = ecp_slug($cityName);

    try {
        $sql = "SELECT specialty, COUNT(*) AS n FROM directory_doctors
                WHERE is_active = 1 AND status = 'OPERATIONAL'
                  AND city = :c
                  AND specialty IS NOT NULL AND specialty <> ''";
        $params = ['c' => $cityName];
        if ($currentSpec !== '') {
            $sql .= " AND specialty <> :exclude";
            $params['exclude'] = $currentSpec;
        }
        $sql .= " GROUP BY specialty HAVING n >= 5 ORDER BY n DESC LIMIT 10";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sSlug = ecp_slug_for_db_specialty((string) $r['specialty']);
            if ($sSlug === null) continue;
            $info = ecp_specialty_by_slug($sSlug);
            if ($info === null) continue;
            // Brand-safety filter: skip specialties marked safe=false
            // (currently just sexology). They're still indexable and
            // reachable by direct URL — just not surfaced in chip rows.
            if (($info['safe'] ?? true) === false) continue;
            $out[] = [
                'label' => $info['plural'],
                'url'   => "/find-a-doctor/{$sSlug}-in-{$citySlug}",
            ];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function _ecp_resolve_city_spec(string $citySlug, string $specSlug, string $path): array {
    $city = ecp_city_by_slug($citySlug);
    $spec = ecp_specialty_by_slug($specSlug);
    if ($city === null || $spec === null) return ['notfound' => true];

    $count = ecp_count_doctors($city['city'], $spec['db']);
    if ($count < ECP_SEO_MIN_LISTINGS) return ['notfound' => true];

    $cityName = $city['city'];
    $plural   = $spec['plural'];
    $label    = $spec['label'];

    return [
        'match' => [
            'city'         => $city,
            'specialty'    => $spec,
            'canonical'    => '/find-a-doctor/' . $specSlug . '-in-' . $citySlug,
            'title'        => "Best {$plural} in {$cityName} — Book Appointment Online | eClinicPro",
            'h1'           => "Best {$plural} in {$cityName}",
            'intro'        => "Find top-rated {$plural} in {$cityName} with verified reviews and consultation fees. "
                            . "Book your appointment in 30 seconds — no phone call, no app to install.",
            'doctor_count' => $count,
            'filter_city'  => $cityName,
            'filter_spec'  => $spec['db'],
        ],
    ];
}
