<?php
// =====================================================================
// fetch_doctor/index.php
// One-page tool: pick Indian cities (grouped by state) and fetch all
// doctors from Google Places into JSON files in fetch_doctor/json/.
//
// Usage in browser:
//   https://eclinicpro.com/fetch_doctor/index.php
//
// Setup once:
//   1. Copy .env.example to .env and fill in GOOGLE_MAPS_API_KEY
//   2. Make sure fetch_doctor/json/ is writable (chmod 0755)
// =====================================================================

declare(strict_types=1);

// ---------- env loader ----------
$envFile = __DIR__ . '/.env';
$apiKey = '';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === 'GOOGLE_MAPS_API_KEY') {
            $apiKey = trim($v, " \t\"'");
        }
    }
}

// ---------- cities, grouped by state ----------
// lat/lng = city center, radius_m = search radius (meters)
$STATES = [
    'Gujarat' => [
        ['name' => 'Ahmedabad', 'lat' => 23.0225, 'lng' => 72.5714, 'radius' => 15000],
        ['name' => 'Surat',     'lat' => 21.1702, 'lng' => 72.8311, 'radius' => 15000],
        ['name' => 'Vadodara',  'lat' => 22.3072, 'lng' => 73.1812, 'radius' => 12000],
        ['name' => 'Rajkot',    'lat' => 22.3039, 'lng' => 70.8022, 'radius' => 12000],
        ['name' => 'Bhavnagar', 'lat' => 21.7645, 'lng' => 72.1519, 'radius' => 10000],
        ['name' => 'Gandhinagar','lat' => 23.2156, 'lng' => 72.6369, 'radius' => 10000],
    ],
    'Maharashtra' => [
        ['name' => 'Mumbai',    'lat' => 19.0760, 'lng' => 72.8777, 'radius' => 15000],
        ['name' => 'Pune',      'lat' => 18.5204, 'lng' => 73.8567, 'radius' => 15000],
        ['name' => 'Nagpur',    'lat' => 21.1458, 'lng' => 79.0882, 'radius' => 12000],
        ['name' => 'Nashik',    'lat' => 19.9975, 'lng' => 73.7898, 'radius' => 12000],
        ['name' => 'Aurangabad','lat' => 19.8762, 'lng' => 75.3433, 'radius' => 10000],
        ['name' => 'Thane',     'lat' => 19.2183, 'lng' => 72.9781, 'radius' => 10000],
    ],
    'Delhi NCR' => [
        ['name' => 'Delhi',     'lat' => 28.7041, 'lng' => 77.1025, 'radius' => 15000],
        ['name' => 'Noida',     'lat' => 28.5355, 'lng' => 77.3910, 'radius' => 12000],
        ['name' => 'Gurgaon',   'lat' => 28.4595, 'lng' => 77.0266, 'radius' => 12000],
        ['name' => 'Faridabad', 'lat' => 28.4089, 'lng' => 77.3178, 'radius' => 12000],
        ['name' => 'Ghaziabad', 'lat' => 28.6692, 'lng' => 77.4538, 'radius' => 12000],
    ],
    'Karnataka' => [
        ['name' => 'Bangalore', 'lat' => 12.9716, 'lng' => 77.5946, 'radius' => 15000],
        ['name' => 'Mysore',    'lat' => 12.2958, 'lng' => 76.6394, 'radius' => 10000],
        ['name' => 'Mangalore', 'lat' => 12.9141, 'lng' => 74.8560, 'radius' => 10000],
        ['name' => 'Hubli',     'lat' => 15.3647, 'lng' => 75.1240, 'radius' => 10000],
    ],
    'Tamil Nadu' => [
        ['name' => 'Chennai',     'lat' => 13.0827, 'lng' => 80.2707, 'radius' => 15000],
        ['name' => 'Coimbatore',  'lat' => 11.0168, 'lng' => 76.9558, 'radius' => 12000],
        ['name' => 'Madurai',     'lat' => 9.9252,  'lng' => 78.1198, 'radius' => 10000],
        ['name' => 'Tiruchirappalli','lat' => 10.7905,'lng' => 78.7047,'radius' => 10000],
    ],
    'Telangana' => [
        ['name' => 'Hyderabad', 'lat' => 17.3850, 'lng' => 78.4867, 'radius' => 15000],
        ['name' => 'Warangal',  'lat' => 17.9689, 'lng' => 79.5941, 'radius' => 10000],
    ],
    'West Bengal' => [
        ['name' => 'Kolkata',  'lat' => 22.5726, 'lng' => 88.3639, 'radius' => 15000],
        ['name' => 'Howrah',   'lat' => 22.5958, 'lng' => 88.2636, 'radius' => 10000],
        ['name' => 'Siliguri', 'lat' => 26.7271, 'lng' => 88.3953, 'radius' => 10000],
    ],
    'Rajasthan' => [
        ['name' => 'Jaipur',  'lat' => 26.9124, 'lng' => 75.7873, 'radius' => 12000],
        ['name' => 'Jodhpur', 'lat' => 26.2389, 'lng' => 73.0243, 'radius' => 10000],
        ['name' => 'Udaipur', 'lat' => 24.5854, 'lng' => 73.7125, 'radius' => 10000],
        ['name' => 'Kota',    'lat' => 25.2138, 'lng' => 75.8648, 'radius' => 10000],
    ],
    'Uttar Pradesh' => [
        ['name' => 'Lucknow',  'lat' => 26.8467, 'lng' => 80.9462, 'radius' => 12000],
        ['name' => 'Kanpur',   'lat' => 26.4499, 'lng' => 80.3319, 'radius' => 12000],
        ['name' => 'Varanasi', 'lat' => 25.3176, 'lng' => 82.9739, 'radius' => 10000],
        ['name' => 'Agra',     'lat' => 27.1767, 'lng' => 78.0081, 'radius' => 10000],
    ],
    'Kerala' => [
        ['name' => 'Kochi',              'lat' => 9.9312,  'lng' => 76.2673, 'radius' => 12000],
        ['name' => 'Thiruvananthapuram', 'lat' => 8.5241,  'lng' => 76.9366, 'radius' => 12000],
        ['name' => 'Kozhikode',          'lat' => 11.2588, 'lng' => 75.7804, 'radius' => 10000],
    ],
    'Punjab' => [
        ['name' => 'Ludhiana',  'lat' => 30.9000, 'lng' => 75.8573, 'radius' => 10000],
        ['name' => 'Amritsar',  'lat' => 31.6340, 'lng' => 74.8723, 'radius' => 10000],
        ['name' => 'Chandigarh','lat' => 30.7333, 'lng' => 76.7794, 'radius' => 10000],
    ],
];

// ---------- queries (one per Indian-relevant specialty) ----------
$QUERIES = [
    ['q' => 'doctor',              'spec' => 'gp'],
    ['q' => 'dentist',             'spec' => 'dental'],
    ['q' => 'pediatrician',        'spec' => 'peds'],
    ['q' => 'dermatologist',       'spec' => 'derma'],
    ['q' => 'gynecologist',        'spec' => 'gyno'],
    ['q' => 'cardiologist',        'spec' => 'cardio'],
    ['q' => 'orthopedic',          'spec' => 'ortho'],
    ['q' => 'physiotherapist',     'spec' => 'physio'],
    ['q' => 'homeopathy clinic',   'spec' => 'homeo'],
    ['q' => 'ENT specialist',      'spec' => 'ent'],
    ['q' => 'eye doctor',          'spec' => 'eye'],
    ['q' => 'neurologist',         'spec' => 'neuro'],
];

// Helper: flatten and find a city by name
function find_city(array $states, string $name): ?array {
    foreach ($states as $stateName => $cities) {
        foreach ($cities as $c) {
            if (strcasecmp($c['name'], $name) === 0) {
                return $c + ['state' => $stateName];
            }
        }
    }
    return null;
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

// =====================================================================
// FETCH MODE — POST a list of cities, call Google Places, save JSON,
// then redirect back to the picker with a success flash.
// =====================================================================
$flash = $_GET['msg'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cities'])) {
    if ($apiKey === '') {
        $flash = 'ERROR: GOOGLE_MAPS_API_KEY missing in fetch_doctor/.env';
    } else {
        @set_time_limit(0);                  // long-running script
        ignore_user_abort(true);
        header('Content-Type: text/plain; charset=utf-8');
        echo "fetch_doctor — fetching " . count($_POST['cities']) . " cities\n";
        echo str_repeat('=', 60) . "\n\n";

        $jsonDir = __DIR__ . '/json';
        if (!is_dir($jsonDir)) @mkdir($jsonDir, 0755, true);

        $totalNew = 0;
        $totalReq = 0;

        foreach ($_POST['cities'] as $cityName) {
            $city = find_city($STATES, (string) $cityName);
            if (!$city) { echo "  skip unknown city: {$cityName}\n"; continue; }

            echo "📍 {$city['name']} ({$city['state']})\n";

            $cityDoctors = [];
            $seenPlaceIds = [];
            $skipStats = ['closed_text' => 0, 'bad_types' => 0, 'closed_detail' => 0, 'no_address' => 0, 'city_mismatch' => 0, 'details_failed' => 0];
            $flaggedCount = 0;

            foreach ($QUERIES as $qrow) {
                echo "  🔍 {$qrow['q']}\n";
                $places = fetch_text_search($apiKey, $qrow['q'], $city['lat'], $city['lng'], $city['radius'], $totalReq);

                foreach ($places as $place) {
                    $pid = $place['place_id'] ?? null;
                    if (!$pid || isset($seenPlaceIds[$pid])) continue;
                    $seenPlaceIds[$pid] = true;

                    // STAGE 1 — cheap pre-filter on Text Search (no Details cost yet).
                    $types = $place['types'] ?? [];
                    $biz = $place['business_status'] ?? 'OPERATIONAL';
                    if (str_starts_with((string) $biz, 'CLOSED_')) { $skipStats['closed_text']++; continue; }
                    if (!places_type_acceptable($types))           { $skipStats['bad_types']++; continue; }

                    // STAGE 2 — pay for Details and check the deeper signals.
                    $details = fetch_place_details($apiKey, $pid, $totalReq);
                    if (!$details) { $skipStats['details_failed']++; continue; }

                    // permanently_closed is a legacy field Google still returns sometimes
                    if (!empty($details['permanently_closed'])) { $skipStats['closed_detail']++; continue; }
                    $detailStatus = $details['business_status'] ?? '';
                    if (str_starts_with((string) $detailStatus, 'CLOSED_')) { $skipStats['closed_detail']++; continue; }

                    $row = format_doctor($place, $details, $city, $qrow['spec']);

                    // STAGE 3 — quality check. Hard SKIP or soft FLAG.
                    $verdict = quality_check($row, $city);
                    if (str_starts_with($verdict, 'SKIP:')) {
                        $reason = substr($verdict, 5);
                        if (isset($skipStats[$reason])) $skipStats[$reason]++;
                        continue;
                    }
                    if ($verdict !== '') {
                        // Soft flag — keep the row but tag it so insert_db.php can lower quality_score
                        $row['dropped_reason'] = $verdict;
                        $flaggedCount++;
                    }

                    $cityDoctors[] = $row;
                }
                usleep(200_000); // 200ms between queries to avoid throttling
            }

            // Summary line of filter activity for this city
            $skipParts = [];
            foreach ($skipStats as $k => $v) if ($v > 0) $skipParts[] = "{$k}:{$v}";
            if (!empty($skipParts)) echo "  ⚠ Filtered out: " . implode(', ', $skipParts) . "\n";
            if ($flaggedCount > 0) echo "  ⓘ Flagged (kept but low-quality): {$flaggedCount}\n";

            // Merge with existing JSON (if any) so re-runs preserve data
            $path = $jsonDir . '/' . slugify($city['name']) . '.json';
            $existing = [];
            if (is_file($path)) {
                $raw = json_decode((string) file_get_contents($path), true);
                if (isset($raw['doctors']) && is_array($raw['doctors'])) {
                    $existing = $raw['doctors'];
                }
            }
            $merged = dedupe_by_place_id(array_merge($existing, $cityDoctors));

            file_put_contents($path, json_encode([
                'city'       => $city['name'],
                'state'      => $city['state'],
                'country'    => 'IN',
                'count'      => count($merged),
                'updated_at' => date('Y-m-d H:i:s'),
                'doctors'    => $merged,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $newThisRun = count($cityDoctors);
            $totalNew += $newThisRun;
            echo "  ✅ saved {$path} (+{$newThisRun} new, " . count($merged) . " total)\n\n";
        }

        $costEst = round(($totalReq / 1000) * 24, 2);  // ~$24 per 1000 (Text + Details Basic)
        echo str_repeat('=', 60) . "\n";
        echo "Done. New doctors: {$totalNew}\n";
        echo "API requests:       {$totalReq}\n";
        echo "Estimated cost:    ~\${$costEst} USD\n";
        echo "\nNext: open insert_db.php to import into your database.\n";
        exit;
    }
}

// =====================================================================
// FETCHER FUNCTIONS
// =====================================================================
function fetch_text_search(string $apiKey, string $query, float $lat, float $lng, int $radius, int &$reqCount): array {
    $params = [
        'query'    => $query,
        'location' => sprintf('%f,%f', $lat, $lng),
        'radius'   => (string) $radius,
        'key'      => $apiKey,
    ];
    $all = [];
    $pageToken = null;
    for ($i = 0; $i < 3; $i++) {
        if ($pageToken !== null) {
            sleep(2);                       // token isn't valid for 2s
            $params = ['pagetoken' => $pageToken, 'key' => $apiKey];
        }
        $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query($params);
        $data = http_get_json($url, $reqCount);
        if (!$data) break;

        foreach ((array) ($data['results'] ?? []) as $row) $all[] = $row;
        $pageToken = $data['next_page_token'] ?? null;
        if (!$pageToken) break;
    }
    return $all;
}

function fetch_place_details(string $apiKey, string $placeId, int &$reqCount): ?array {
    $url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query([
        'place_id' => $placeId,
        // Fields: Basic SKU (name/address/geometry/types/url/photos/plus_code/business_status)
        //       + Contact SKU (phone/website/opening_hours)
        //       + Atmosphere SKU (rating/user_ratings_total/price_level/reviews[0].time)
        // We keep reviews limited to time field only, so cost stays at ~$24/1000.
        'fields'   => 'name,formatted_address,geometry/location,formatted_phone_number,'
                    . 'international_phone_number,website,url,opening_hours/weekday_text,'
                    . 'rating,user_ratings_total,price_level,reviews/time,'
                    . 'types,business_status,plus_code,photo,permanently_closed',
        'key'      => $apiKey,
    ]);
    $data = http_get_json($url, $reqCount);
    return is_array($data['result'] ?? null) ? $data['result'] : null;
}

function http_get_json(string $url, int &$reqCount): ?array {
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $reqCount++;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'eClinicPro-Fetcher/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code === 0 || ($code >= 500 && $code < 600)) {
            if ($attempt === 1) { sleep(3); continue; }
            return null;
        }
        if ($code !== 200) return null;

        $data = json_decode((string) $body, true);
        if (!is_array($data)) return null;

        $status = $data['status'] ?? '';
        if ($status === 'OK' || $status === 'ZERO_RESULTS') return $data;

        if ($status === 'OVER_QUERY_LIMIT') {
            $msg = (string) ($data['error_message'] ?? '');
            if (stripos($msg, 'per second') !== false || stripos($msg, 'short term') !== false) {
                if ($attempt === 1) { sleep(5); continue; }
            }
            echo "  ❌ OVER_QUERY_LIMIT: {$msg}\n";
            return null;
        }
        if ($status === 'UNKNOWN_ERROR' && $attempt === 1) { sleep(3); continue; }

        echo "  ⚠ status={$status} " . ($data['error_message'] ?? '') . "\n";
        return null;
    }
    return null;
}

function places_type_acceptable(array $types): bool {
    $accept = ['doctor', 'dentist', 'hospital', 'physiotherapist', 'health'];
    $reject = ['pharmacy', 'drugstore', 'health_food', 'spa'];
    if (array_intersect($types, $accept)) return true;
    if (array_intersect($types, $reject)) return false;
    return true; // neutral: keep small clinics tagged only 'establishment'
}

function format_doctor(array $place, array $details, array $city, string $spec): array {
    $loc = $details['geometry']['location'] ?? [];

    // Most recent review timestamp — used downstream for staleness detection.
    $lastReviewAt = null;
    if (isset($details['reviews']) && is_array($details['reviews'])) {
        $latest = 0;
        foreach ($details['reviews'] as $r) {
            $t = (int) ($r['time'] ?? 0);
            if ($t > $latest) $latest = $t;
        }
        if ($latest > 0) $lastReviewAt = date('Y-m-d H:i:s', $latest);
    }

    // First photo reference only (NEVER cache the actual photo bytes per Google TOS).
    $photoRef = null;
    if (isset($details['photos'][0]['photo_reference'])) {
        $photoRef = (string) $details['photos'][0]['photo_reference'];
    }

    return [
        'place_id'        => $place['place_id'] ?? null,
        'name'            => (string) ($details['name'] ?? $place['name'] ?? ''),
        'specialty'       => $spec,
        'city'            => $city['name'],
        'state'           => $city['state'],
        'country'         => 'IN',
        'address'         => $details['formatted_address'] ?? null,
        'lat'             => isset($loc['lat']) ? (float) $loc['lat'] : null,
        'lng'             => isset($loc['lng']) ? (float) $loc['lng'] : null,
        'phone'           => $details['formatted_phone_number'] ?? null,
        'intl_phone'      => $details['international_phone_number'] ?? null,
        'website'         => $details['website'] ?? null,
        'gmaps_url'       => $details['url'] ?? null,
        'plus_code'       => $details['plus_code']['compound_code'] ?? null,
        'status'          => $details['business_status'] ?? 'OPERATIONAL',
        'rating'          => isset($details['rating']) ? (float) $details['rating'] : null,
        'reviews'         => isset($details['user_ratings_total']) ? (int) $details['user_ratings_total'] : 0,
        'price_level'     => isset($details['price_level']) ? (int) $details['price_level'] : null,
        'last_review_at'  => $lastReviewAt,
        'types'           => array_values((array) ($details['types'] ?? [])),
        'opening_hours'   => $details['opening_hours']['weekday_text'] ?? null,
        'photo_reference' => $photoRef,
        'fetched_at'      => date('Y-m-d H:i:s'),
    ];
}

/**
 * Multi-stage quality filter. Returns null when the doctor should be SKIPPED
 * entirely, or a string reason like 'low_reviews' to flag (still save but
 * mark dropped_reason in DB). Returns '' to mean "looks good, keep".
 *
 * Called AFTER format_doctor() because some checks need details fields.
 */
function quality_check(array $row, array $city): string {
    // 1. Hard skip — closed
    $status = $row['status'] ?? 'OPERATIONAL';
    if ($status === 'CLOSED_PERMANENTLY' || $status === 'CLOSED_TEMPORARILY') {
        return 'SKIP:closed';
    }

    // 2. Hard skip — no address means it's a phantom listing
    if (empty($row['address'])) return 'SKIP:no_address';

    // 3. Hard skip — city mismatch (Google sometimes returns far-away results)
    //    Accept if the searched city name appears anywhere in the address.
    $addr = strtolower((string) $row['address']);
    if (!str_contains($addr, strtolower($city['name']))) {
        return 'SKIP:city_mismatch';
    }

    // 4. Flag — very low review count = probably abandoned / spam listing
    if ((int) ($row['reviews'] ?? 0) < 3) {
        return 'low_reviews';
    }

    // 5. Flag — no opening hours at all = listing rarely maintained
    if (empty($row['opening_hours'])) {
        return 'no_hours';
    }

    // 6. Flag — last review > 18 months ago = likely stale / moved
    if (!empty($row['last_review_at'])) {
        $age = time() - strtotime((string) $row['last_review_at']);
        if ($age > 18 * 30 * 86400) {
            return 'stale_reviews';
        }
    }

    return '';
}

function dedupe_by_place_id(array $rows): array {
    $seen = [];
    $out = [];
    foreach ($rows as $r) {
        $k = $r['place_id'] ?? null;
        if (!$k || isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $r;
    }
    return $out;
}

// =====================================================================
// PICKER UI — default GET view
// =====================================================================
$keyMissing = ($apiKey === '');
$existingJson = [];
foreach (glob(__DIR__ . '/json/*.json') ?: [] as $f) {
    $raw = json_decode((string) file_get_contents($f), true);
    $existingJson[basename($f)] = (int) ($raw['count'] ?? 0);
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fetch Doctors — eClinicPro</title>
<style>
:root { --teal: #0F9B6E; --line: rgba(0,0,0,0.08); --bg: #f8fafc; --mute: #64748b; --ink: #0f172a; }
* { box-sizing: border-box; }
body { font: 14px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; margin: 0; background: var(--bg); color: var(--ink); }
.wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px 80px; }
h1 { font-size: 24px; font-weight: 600; margin: 0 0 8px; }
.lede { color: var(--mute); margin: 0 0 24px; }
.warn, .ok { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
.warn { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.toolbar { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.toolbar button, .toolbar a {
  font: inherit; background: #fff; border: 1px solid var(--line); padding: 7px 14px;
  border-radius: 8px; cursor: pointer; color: var(--ink); text-decoration: none;
}
.toolbar button:hover, .toolbar a:hover { border-color: var(--teal); color: var(--teal); }
.state { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 16px 18px; margin-bottom: 12px; }
.state-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.state-name { font-weight: 600; font-size: 15px; }
.state-action { font-size: 12px; color: var(--teal); cursor: pointer; background: none; border: 0; padding: 0; }
.cities { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; }
.city {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px;
  cursor: pointer; transition: all .15s; background: #fff; font-size: 13px;
}
.city:hover { border-color: var(--teal); }
.city input { margin: 0; cursor: pointer; }
.city.has-data { background: #ecfdf5; border-color: #a7f3d0; }
.city-tag { margin-left: auto; font-size: 11px; color: var(--mute); }
.submit {
  position: sticky; bottom: 16px; margin-top: 24px; background: var(--ink); color: #fff;
  padding: 14px 18px; border: 0; border-radius: 12px; font-size: 14px; font-weight: 500;
  cursor: pointer; width: 100%; box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}
.submit:hover { background: var(--teal); }
.summary { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; color: var(--mute); margin: 8px 0 16px; }
.summary strong { color: var(--ink); }
.note { font-size: 12px; color: var(--mute); margin-top: 16px; padding: 12px; background: #fff; border-radius: 8px; border-left: 3px solid var(--teal); }
</style>
</head>
<body>
<div class="wrap">

<h1>🩺 Fetch Doctors</h1>
<p class="lede">Pick cities below — clicking <strong>Fetch</strong> will call Google Places and save the results into <code>json/</code>. Then open <a href="insert_db.php">insert_db.php</a> to import into the database.</p>

<?php if ($keyMissing): ?>
    <div class="warn">⚠ <strong>GOOGLE_MAPS_API_KEY missing.</strong> Create <code>fetch_doctor/.env</code> with one line: <code>GOOGLE_MAPS_API_KEY=your_key</code></div>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="ok"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="summary">
    <span>Cities available: <strong><?= array_sum(array_map('count', $STATES)) ?></strong></span>
    <span>States: <strong><?= count($STATES) ?></strong></span>
    <span>Specialty queries per city: <strong><?= count($QUERIES) ?></strong></span>
    <span>JSON files saved: <strong><?= count($existingJson) ?></strong></span>
</div>

<form method="post">
<div class="toolbar">
    <button type="button" onclick="toggleAll(true)">Select all</button>
    <button type="button" onclick="toggleAll(false)">Clear all</button>
    <a href="insert_db.php">→ Go to importer</a>
</div>

<?php foreach ($STATES as $stateName => $cities): ?>
<div class="state">
    <div class="state-head">
        <span class="state-name"><?= htmlspecialchars($stateName) ?> <small style="color:var(--mute);font-weight:400;">(<?= count($cities) ?>)</small></span>
        <button type="button" class="state-action" onclick="toggleState(this)">toggle</button>
    </div>
    <div class="cities">
        <?php foreach ($cities as $c):
            $slug = slugify($c['name']);
            $jsonFile = "{$slug}.json";
            $hasData = isset($existingJson[$jsonFile]);
            $countStr = $hasData ? "{$existingJson[$jsonFile]} saved" : '';
        ?>
        <label class="city <?= $hasData ? 'has-data' : '' ?>">
            <input type="checkbox" name="cities[]" value="<?= htmlspecialchars($c['name']) ?>">
            <span><?= htmlspecialchars($c['name']) ?></span>
            <?php if ($hasData): ?><span class="city-tag"><?= $countStr ?></span><?php endif; ?>
        </label>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<button type="submit" class="submit">▶ Fetch selected cities &nbsp;·&nbsp; <span id="count">0</span> selected</button>

<div class="note">
    <strong>Per-city cost</strong>: roughly <?= count($QUERIES) ?> queries × ~$0.024 = ~$<?= number_format(count($QUERIES) * 0.024 * 30, 2) ?> for ~30 doctors. Each city averages 100-300 doctors, so budget $5-$10/city. Watch the live log while fetching.
</div>
</form>

</div>

<script>
function toggleAll(on) {
    document.querySelectorAll('input[name="cities[]"]').forEach(i => i.checked = on);
    updateCount();
}
function toggleState(btn) {
    const boxes = btn.closest('.state').querySelectorAll('input[name="cities[]"]');
    const anyOff = [...boxes].some(b => !b.checked);
    boxes.forEach(b => b.checked = anyOff);
    updateCount();
}
function updateCount() {
    document.getElementById('count').textContent =
        document.querySelectorAll('input[name="cities[]"]:checked').length;
}
document.addEventListener('change', e => { if (e.target.matches('input[name="cities[]"]')) updateCount(); });
</script>
</body>
</html>
