<?php
// =====================================================================
// api/locations.php — autocomplete for the location search box.
//
// Old behavior: ship all distinct areas/cities/states (~2,200 rows) in
// the page payload, filter client-side. Doesn't scale.
//
// New behavior: query as the user types. Returns 8 best matches across
// areas, cities, states. Cached by CloudFlare for 5 minutes per query.
//
//   GET /api/locations?q=ban&country=IN
//
// Returns:
//   { ok: true, items: [
//       { type: "AREA",  label: "Bandra",    sub: "Mumbai, Maharashtra",
//         value: { area: "Bandra", city: "Mumbai", state: "Maharashtra", country: "IN" },
//         flag: "🇮🇳" },
//       ...
//     ] }
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');   // popular prefixes cache nicely
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../partials/db.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/locations] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

$db = ecp_db();
if (!$db) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}

$q       = trim((string) ($_GET['q']       ?? ''));
$country = strtoupper(trim((string) ($_GET['country'] ?? 'IN')));

if (strlen($q) > 80) $q = substr($q, 0, 80);

$flags = ['IN' => '🇮🇳', 'US' => '🇺🇸', 'GB' => '🇬🇧', 'AE' => '🇦🇪',
          'CA' => '🇨🇦', 'AU' => '🇦🇺', 'SG' => '🇸🇬'];
$flag  = $flags[$country] ?? '🌐';

$items = [];

// Empty query → return a few popular places (top cities by doctor count).
// This populates the dropdown on focus, before the user types.
if ($q === '') {
    $stmt = $db->prepare(
        "SELECT city, state, COUNT(*) AS n
         FROM directory_doctors
         WHERE country = :c AND is_active = 1 AND city IS NOT NULL AND city <> ''
         GROUP BY city, state
         ORDER BY n DESC
         LIMIT 8"
    );
    $stmt->execute(['c' => $country]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $items[] = [
            'type'  => 'CITY',
            'label' => $r['city'],
            'sub'   => $r['state'] ?? '',
            'value' => ['city' => $r['city'], 'state' => $r['state'], 'country' => $country],
            'flag'  => $flag,
        ];
    }
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = $q . '%';

// 1. AREAS matching prefix (most specific → show first).
$areas = $db->prepare(
    "SELECT DISTINCT area, city, state
     FROM directory_doctors
     WHERE country = :c AND is_active = 1
       AND area IS NOT NULL AND area <> ''
       AND area LIKE :q
     ORDER BY area
     LIMIT 6"
);
$areas->execute(['c' => $country, 'q' => $like]);
foreach ($areas->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $items[] = [
        'type'  => 'AREA',
        'label' => $r['area'],
        'sub'   => trim(($r['city'] ?? '') . ($r['state'] ? ', ' . $r['state'] : '')),
        'value' => ['area' => $r['area'], 'city' => $r['city'],
                    'state' => $r['state'], 'country' => $country],
        'flag'  => $flag,
    ];
}

// 2. CITIES matching prefix.
$cities = $db->prepare(
    "SELECT DISTINCT city, state
     FROM directory_doctors
     WHERE country = :c AND is_active = 1
       AND city IS NOT NULL AND city <> ''
       AND city LIKE :q
     ORDER BY city
     LIMIT 6"
);
$cities->execute(['c' => $country, 'q' => $like]);
$seenCity = [];
foreach ($cities->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = strtolower((string) $r['city']);
    if (isset($seenCity[$key])) continue;
    $seenCity[$key] = true;
    $items[] = [
        'type'  => 'CITY',
        'label' => $r['city'],
        'sub'   => $r['state'] ?? '',
        'value' => ['city' => $r['city'], 'state' => $r['state'], 'country' => $country],
        'flag'  => $flag,
    ];
}

// 3. STATES matching prefix.
$states = $db->prepare(
    "SELECT DISTINCT state
     FROM directory_doctors
     WHERE country = :c AND is_active = 1
       AND state IS NOT NULL AND state <> ''
       AND state LIKE :q
     ORDER BY state
     LIMIT 4"
);
$states->execute(['c' => $country, 'q' => $like]);
$seenState = [];
foreach ($states->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = strtolower((string) $r['state']);
    if (isset($seenState[$key])) continue;
    $seenState[$key] = true;
    $items[] = [
        'type'  => 'STATE',
        'label' => $r['state'],
        'sub'   => '',
        'value' => ['state' => $r['state'], 'country' => $country],
        'flag'  => $flag,
    ];
}

// Cap at 8 total, areas-first.
$items = array_slice($items, 0, 8);

echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
