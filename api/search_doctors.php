<?php
// =====================================================================
// api/search_doctors.php — paginated search for the public directory.
//
// All filtering happens here in SQL instead of shipping the entire
// directory to the browser. The old find-a-doctor.php loads up to 10k
// doctors as a 7 MB JSON blob; this endpoint returns 20 at a time.
//
//   GET /api/search_doctors?
//       q=string          search box (matches name + doctor_name + clinic_name)
//       country=IN        ISO country code
//       state=Gujarat
//       city=Ahmedabad
//       area=Gota
//       spec=cardio       specialty slug
//       min_rating=4
//       lang=English      (currently always returns true; reserved)
//       sort=relevance|distance|rating|fee_asc|fee_desc|exp|claimed
//       lat=23.02&lng=72.57          user location (enables distance sort + filter)
//       max_km=25                    distance filter (requires lat+lng)
//       page=1                       1-based
//       per_page=20                  default 20, max 50
//
// Returns:
//   { ok: true, items: [...20 doctors...],
//     total: 4321, page: 1, per_page: 20, has_more: true }
//
// CloudFlare-friendly: GET only, public Cache-Control, no cookies needed.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');   // CF caches popular queries
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../partials/db.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/search_doctors] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

function out(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$db = ecp_db();
if (!$db) out(503, ['ok' => false, 'error' => 'db_unavailable']);

// -------------------- Parse filters --------------------

$q          = trim((string) ($_GET['q']      ?? ''));
$country    = strtoupper(trim((string) ($_GET['country'] ?? 'IN')));
$state      = trim((string) ($_GET['state']  ?? ''));
$city       = trim((string) ($_GET['city']   ?? ''));
$area       = trim((string) ($_GET['area']   ?? ''));
$spec       = trim((string) ($_GET['spec']   ?? ''));
$minRating  = (float) ($_GET['min_rating']  ?? 0);
$sort       = (string) ($_GET['sort']       ?? 'relevance');
$lat        = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
$lng        = isset($_GET['lng']) ? (float) $_GET['lng'] : null;
$maxKm      = isset($_GET['max_km']) ? (float) $_GET['max_km'] : 0.0;
$page       = max(1, (int) ($_GET['page']     ?? 1));
$perPage    = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
$offset     = ($page - 1) * $perPage;

// Caps to keep things sensible.
if (strlen($q) > 80)     $q = substr($q, 0, 80);
if (strlen($state) > 80) $state = substr($state, 0, 80);
if (strlen($city)  > 80) $city  = substr($city,  0, 80);
if (strlen($area)  > 120) $area = substr($area, 0, 120);
if (strlen($spec)  > 80) $spec  = substr($spec,  0, 80);

// -------------------- Build WHERE --------------------

$where  = ["dd.is_active = 1", "dd.status = 'OPERATIONAL'", "dd.country = :country"];
$params = ['country' => $country];

if ($state !== '') { $where[] = "dd.state = :state"; $params['state'] = $state; }
if ($city  !== '') { $where[] = "dd.city  = :city";  $params['city']  = $city; }
if ($area  !== '') { $where[] = "dd.area  = :area";  $params['area']  = $area; }
if ($spec  !== '') { $where[] = "dd.specialty = :spec"; $params['spec'] = $spec; }
if ($minRating > 0) { $where[] = "dd.rating >= :rating"; $params['rating'] = $minRating; }

// Text search — use FULLTEXT when the user typed > 2 chars, else LIKE prefix.
// FULLTEXT is far cheaper but requires natural-language tokens; for 1-2
// char queries (common while typing) LIKE on indexed area/city is fine.
$relevanceExpr = null;
if ($q !== '') {
    if (strlen($q) >= 3) {
        // BOOLEAN MODE so we can do prefix search with the * suffix.
        $where[] = "MATCH(dd.name, dd.doctor_name) AGAINST(:q IN BOOLEAN MODE)";
        // Each token gets a trailing * for prefix match.
        $tokens = array_filter(preg_split('/\s+/', $q) ?: []);
        $tokens = array_map(static fn ($t) => preg_replace('/[^\w]/', '', $t) . '*', $tokens);
        $params['q'] = implode(' ', $tokens);
        // For sort=relevance we'll order by MATCH score too.
        $relevanceExpr = "MATCH(dd.name, dd.doctor_name) AGAINST(:qrel IN BOOLEAN MODE)";
        $params['qrel'] = $params['q'];
    } else {
        $where[] = "(dd.name LIKE :qlike OR dd.doctor_name LIKE :qlike OR dd.area LIKE :qlike OR dd.city LIKE :qlike)";
        $params['qlike'] = $q . '%';
    }
}

// Distance — only when we have user coords. Use Haversine, push into HAVING
// to also enable max_km filter.
$selectDistance = null;
if ($lat !== null && $lng !== null) {
    $selectDistance =
        "(6371 * 2 * ASIN(SQRT(POWER(SIN((:ulat - dd.lat) * PI() / 360), 2)"
      . " + COS(:ulat * PI() / 180) * COS(dd.lat * PI() / 180)"
      . " * POWER(SIN((:ulng - dd.lng) * PI() / 360), 2)))) AS distance_km";
    $params['ulat'] = $lat;
    $params['ulng'] = $lng;
}

// -------------------- ORDER BY --------------------

$order = match ($sort) {
    'distance'  => $selectDistance !== null
                    ? "distance_km IS NULL, distance_km ASC"
                    : "dd.is_claimed DESC, dd.quality_score DESC",
    'rating'    => "dd.rating DESC, dd.reviews DESC",
    'fee_asc'   => "dd.consultation_fee IS NULL, dd.consultation_fee ASC",
    'fee_desc'  => "dd.consultation_fee IS NULL, dd.consultation_fee DESC",
    'claimed'   => "dd.is_claimed DESC, dd.quality_score DESC",
    'relevance', '' => ($relevanceExpr !== null)
                    ? "$relevanceExpr DESC, dd.is_claimed DESC, dd.quality_score DESC"
                    : "dd.is_claimed DESC, dd.quality_score DESC, dd.reviews DESC, dd.rating DESC",
    default     => "dd.is_claimed DESC, dd.quality_score DESC",
};

// -------------------- Query --------------------

$selectCols = "dd.id, dd.name, dd.doctor_name, dd.specialty, dd.country, dd.city,
               dd.state, dd.area, dd.address, dd.lat, dd.lng,
               dd.phone, dd.website, dd.gmaps_url, dd.rating, dd.reviews,
               dd.opening_hours, dd.photo_reference,
               dd.consultation_fee, dd.consultation_fee_currency,
               dd.is_claimed, dd.quality_score";
if ($selectDistance !== null) $selectCols .= ",\n               " . $selectDistance;

$whereSql = implode(' AND ', $where);

// HAVING clause for the distance filter (can't use WHERE — alias).
$havingSql = '';
if ($selectDistance !== null && $maxKm > 0) {
    $havingSql = " HAVING distance_km <= :max_km";
    $params['max_km'] = $maxKm;
}

$mainSql = "SELECT $selectCols
            FROM directory_doctors dd
            WHERE $whereSql
            $havingSql
            ORDER BY $order
            LIMIT :lim OFFSET :off";

$stmt = $db->prepare($mainSql);
foreach ($params as $k => $v) {
    if ($k === 'lim' || $k === 'off') continue;
    $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : (is_float($v) ? PDO::PARAM_STR : PDO::PARAM_STR));
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------- Total count for pagination --------------------

// We only need the count for page=1; subsequent pages can rely on has_more.
// This saves a duplicate query on every infinite-scroll request.
$total = null;
if ($page === 1) {
    $countSql = "SELECT COUNT(*) FROM directory_doctors dd WHERE $whereSql";
    $cnt = $db->prepare($countSql);
    foreach ($params as $k => $v) {
        if (in_array($k, ['lim', 'off', 'max_km', 'ulat', 'ulng', 'qrel'], true)) continue;
        $cnt->bindValue(':' . $k, $v);
    }
    $cnt->execute();
    $total = (int) $cnt->fetchColumn();
}

// -------------------- Shape items for the client --------------------

$items = array_map(static function (array $r): array {
    $clinicName = (string) ($r['name'] ?? '');
    $doctorName = trim((string) ($r['doctor_name'] ?? ''));
    $display    = $doctorName !== '' ? $doctorName : $clinicName;
    // initials minus the "Dr."
    $forInit = preg_replace('/^Dr\.?\s+/i', '', $display) ?? $display;
    $parts   = preg_split('/\s+/', trim($forInit)) ?: [''];
    $first   = mb_substr($parts[0] ?? '', 0, 1) ?: 'D';
    $last    = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

    $hours = null;
    if (!empty($r['opening_hours'])) {
        $d = json_decode((string) $r['opening_hours'], true);
        if (is_array($d)) $hours = $d;
    }
    $hospital = ($doctorName !== '' && $clinicName !== $doctorName) ? $clinicName : '';

    return [
        'id'           => (int) $r['id'],
        'name'         => $display,
        'doctorName'   => $doctorName ?: null,
        'clinicName'   => $clinicName,
        'hospital'     => $hospital,
        'firstInitial' => $first,
        'lastInitial'  => $last,
        'spec'         => $r['specialty'] ?? 'gp',
        'specLabel'    => ecp_specialty_label($r['specialty'] ?? null),
        'verified'     => (bool) $r['is_claimed'],
        'is_claimed'   => (bool) $r['is_claimed'],
        'rating'       => isset($r['rating']) ? (float) $r['rating'] : 0,
        'reviews'      => (int) ($r['reviews'] ?? 0),
        'area'         => $r['area']  ?? '',
        'city'         => $r['city']  ?? '',
        'state'        => $r['state'] ?? '',
        'country'      => $r['country'] ?? 'IN',
        'fee'          => isset($r['consultation_fee']) && $r['consultation_fee'] !== null ? (float) $r['consultation_fee'] : 0,
        'currency'     => $r['consultation_fee_currency'] ?? (($r['country'] ?? 'IN') === 'IN' ? '₹' : '$'),
        'phone'        => $r['phone'] ?? null,
        'website'      => $r['website'] ?? null,
        'gmaps_url'    => $r['gmaps_url'] ?? null,
        'lat'          => isset($r['lat']) ? (float) $r['lat'] : null,
        'lng'          => isset($r['lng']) ? (float) $r['lng'] : null,
        'address'      => $r['address'] ?? null,
        'opening_hours'=> $hours,
        'photo_url'    => ecp_doctor_photo_url($r['photo_reference'] ?? null, 400),
        'distance_km'  => isset($r['distance_km']) ? round((float) $r['distance_km'], 1) : null,
    ];
}, $rows);

$resp = [
    'ok'       => true,
    'items'    => $items,
    'page'     => $page,
    'per_page' => $perPage,
    'has_more' => count($items) === $perPage,
];
if ($total !== null) $resp['total'] = $total;

out(200, $resp);
