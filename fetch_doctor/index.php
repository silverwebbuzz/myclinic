<?php
// =====================================================================
// fetch_doctor/index.php
// Pick Indian cities, then fetch doctors from Google Places in small
// AJAX-driven chunks (one sub-area per request) so no single PHP call
// times out. Live progress bar.
//
// Three modes (driven by query string):
//   (no params)            — picker UI
//   ?job={id}              — progress view for a running job
//   ?action=step&job={id}  — does ONE chunk, returns JSON status (AJAX only)
//   ?action=status&job={id}— returns current state JSON without running
//
// Setup once:
//   1. Copy .env.example to .env, set GOOGLE_MAPS_API_KEY
//   2. chmod 0755 fetch_doctor/json fetch_doctor/jobs
// =====================================================================

declare(strict_types=1);

// =====================================================================
// CONFIG
// =====================================================================

$envFile = __DIR__ . '/.env';
$apiKey = '';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === 'GOOGLE_MAPS_API_KEY') $apiKey = trim($v, " \t\"'");
    }
}

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

$JSON_DIR = __DIR__ . '/json';
$JOBS_DIR = __DIR__ . '/jobs';
foreach ([$JSON_DIR, $JOBS_DIR] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}

// =====================================================================
// HELPERS — shared
// =====================================================================

function find_city(array $states, string $name): ?array {
    foreach ($states as $stateName => $cities) {
        foreach ($cities as $c) {
            if (strcasecmp($c['name'], $name) === 0) return $c + ['state' => $stateName];
        }
    }
    return null;
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function generate_sub_areas(float $lat, float $lng, int $cityRadius): array {
    $offsetKm = $cityRadius / 2000.0;
    $latStep = $offsetKm / 111.0;
    $lngStep = $offsetKm / (111.0 * cos(deg2rad($lat)));
    $subRadius = (int) round($cityRadius / 2);
    $areas = [];
    foreach ([-1, 0, 1] as $dLat) {
        foreach ([-1, 0, 1] as $dLng) {
            $areas[] = [
                'lat'    => $lat + ($latStep * $dLat),
                'lng'    => $lng + ($lngStep * $dLng),
                'radius' => $subRadius,
            ];
        }
    }
    return $areas;
}

function places_type_acceptable(array $types): bool {
    $accept = ['doctor', 'dentist', 'hospital', 'physiotherapist', 'health'];
    $reject = ['pharmacy', 'drugstore', 'health_food', 'spa'];
    if (array_intersect($types, $accept)) return true;
    if (array_intersect($types, $reject)) return false;
    return true;
}

function format_doctor(array $place, array $details, array $city, string $spec): array {
    $loc = $details['geometry']['location'] ?? [];
    $lastReviewAt = null;
    if (isset($details['reviews']) && is_array($details['reviews'])) {
        $latest = 0;
        foreach ($details['reviews'] as $r) {
            $t = (int) ($r['time'] ?? 0);
            if ($t > $latest) $latest = $t;
        }
        if ($latest > 0) $lastReviewAt = date('Y-m-d H:i:s', $latest);
    }
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

function quality_check(array $row, array $city): string {
    $status = $row['status'] ?? 'OPERATIONAL';
    if ($status === 'CLOSED_PERMANENTLY' || $status === 'CLOSED_TEMPORARILY') return 'SKIP:closed';
    if (empty($row['address'])) return 'SKIP:no_address';
    $addr = strtolower((string) $row['address']);
    if (!str_contains($addr, strtolower($city['name']))) return 'SKIP:city_mismatch';
    if ((int) ($row['reviews'] ?? 0) < 3) return 'low_reviews';
    if (empty($row['opening_hours'])) return 'no_hours';
    if (!empty($row['last_review_at'])) {
        $age = time() - strtotime((string) $row['last_review_at']);
        if ($age > 18 * 30 * 86400) return 'stale_reviews';
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
// HTTP — Google Places calls (per-chunk; brief enough to fit in one PHP call)
// =====================================================================

function http_get_json(string $url, int &$reqCount): ?array {
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $reqCount++;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'eClinicPro-Fetcher/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code === 0 || ($code >= 500 && $code < 600)) {
            if ($attempt === 1) { sleep(2); continue; }
            return null;
        }
        if ($code !== 200) return null;
        $data = json_decode((string) $body, true);
        if (!is_array($data)) return null;

        $status = $data['status'] ?? '';
        if ($status === 'OK' || $status === 'ZERO_RESULTS') return $data;
        if ($status === 'INVALID_REQUEST' && $attempt === 1) { sleep(3); continue; }
        if ($status === 'OVER_QUERY_LIMIT' && $attempt === 1) { sleep(5); continue; }
        if ($status === 'UNKNOWN_ERROR' && $attempt === 1) { sleep(2); continue; }
        return null;
    }
    return null;
}

function fetch_text_search(string $apiKey, string $query, float $lat, float $lng, int $radius, int &$reqCount): array {
    $baseParams = [
        'query'    => $query,
        'location' => sprintf('%f,%f', $lat, $lng),
        'radius'   => (string) $radius,
        'key'      => $apiKey,
    ];
    $all = [];
    $pageToken = null;
    for ($page = 0; $page < 3; $page++) {
        if ($pageToken !== null) {
            sleep(3);
            $params = ['pagetoken' => $pageToken, 'key' => $apiKey];
        } else {
            $params = $baseParams;
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
        'fields'   => 'name,formatted_address,geometry/location,formatted_phone_number,'
                    . 'international_phone_number,website,url,opening_hours/weekday_text,'
                    . 'rating,user_ratings_total,price_level,reviews/time,'
                    . 'types,business_status,plus_code,photo,permanently_closed',
        'key'      => $apiKey,
    ]);
    $data = http_get_json($url, $reqCount);
    return is_array($data['result'] ?? null) ? $data['result'] : null;
}

// =====================================================================
// JOB STATE — one JSON file per job, atomic writes
// =====================================================================

function job_path(string $id): string {
    global $JOBS_DIR;
    $id = preg_replace('/[^a-z0-9]/', '', $id) ?: 'invalid';
    return $JOBS_DIR . '/' . $id . '.json';
}

function job_load(string $id): ?array {
    $p = job_path($id);
    if (!is_file($p)) return null;
    $raw = json_decode((string) file_get_contents($p), true);
    return is_array($raw) ? $raw : null;
}

function job_save(array $job): void {
    $p = job_path($job['id']);
    $tmp = $p . '.tmp';
    file_put_contents($tmp, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $p);
}

function job_create(array $cities, array $queries): string {
    $id = bin2hex(random_bytes(6));
    $tasks = [];
    foreach ($cities as $city) {
        $subAreas = generate_sub_areas($city['lat'], $city['lng'], $city['radius']);
        foreach ($queries as $qIdx => $qrow) {
            foreach ($subAreas as $aIdx => $area) {
                $tasks[] = [
                    'city'    => $city['name'],
                    'state'   => $city['state'],
                    'q'       => $qrow['q'],
                    'spec'    => $qrow['spec'],
                    'area'    => $area,
                    'qIdx'    => $qIdx,
                    'aIdx'    => $aIdx,
                    'qCount'  => count($queries),
                    'aCount'  => count($subAreas),
                    'cityRef' => $city,
                ];
            }
        }
    }
    $job = [
        'id'         => $id,
        'created_at' => date('Y-m-d H:i:s'),
        'cities'     => array_column($cities, 'name'),
        'cursor'     => 0,
        'total'      => count($tasks),
        'tasks'      => $tasks,
        'totals'     => [
            'doctors_new'    => 0,
            'requests'       => 0,
            'skipped_closed' => 0,
            'skipped_type'   => 0,
            'skipped_addr'   => 0,
            'skipped_city'   => 0,
            'flagged'        => 0,
            'detail_fail'    => 0,
        ],
        'log'        => [],   // recent log lines (capped)
        'status'     => 'running',  // running | paused | done | error
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    job_save($job);
    return $id;
}

function job_append_log(array &$job, string $msg): void {
    $job['log'][] = '[' . date('H:i:s') . '] ' . $msg;
    // Cap log so the JSON doesn't grow unbounded
    if (count($job['log']) > 200) $job['log'] = array_slice($job['log'], -200);
}

// =====================================================================
// CHUNK WORKER — does exactly ONE task (one city/query/sub-area)
// =====================================================================

function run_one_chunk(array &$job, string $apiKey, string $jsonDir): void {
    if ($job['cursor'] >= $job['total']) {
        $job['status'] = 'done';
        return;
    }

    $task = $job['tasks'][$job['cursor']];
    $city = $task['cityRef'];
    $area = $task['area'];
    $q    = $task['q'];
    $spec = $task['spec'];

    $reqs = 0;
    $newRows = [];

    // Step 1 — Text Search this sub-area
    $places = fetch_text_search($apiKey, $q, $area['lat'], $area['lng'], $area['radius'], $reqs);

    // Load existing city JSON for dedup against rows already saved
    $cityPath = $jsonDir . '/' . slugify($city['name']) . '.json';
    $existing = [];
    $existingIds = [];
    if (is_file($cityPath)) {
        $raw = json_decode((string) file_get_contents($cityPath), true);
        if (isset($raw['doctors']) && is_array($raw['doctors'])) {
            $existing = $raw['doctors'];
            foreach ($existing as $d) {
                if (!empty($d['place_id'])) $existingIds[$d['place_id']] = true;
            }
        }
    }

    foreach ($places as $place) {
        $pid = $place['place_id'] ?? null;
        if (!$pid || isset($existingIds[$pid])) continue;

        $types = $place['types'] ?? [];
        $biz = $place['business_status'] ?? 'OPERATIONAL';
        if (str_starts_with((string) $biz, 'CLOSED_')) { $job['totals']['skipped_closed']++; continue; }
        if (!places_type_acceptable($types))           { $job['totals']['skipped_type']++; continue; }

        $details = fetch_place_details($apiKey, $pid, $reqs);
        if (!$details) { $job['totals']['detail_fail']++; continue; }

        if (!empty($details['permanently_closed'])) { $job['totals']['skipped_closed']++; continue; }
        $detailStatus = $details['business_status'] ?? '';
        if (str_starts_with((string) $detailStatus, 'CLOSED_')) { $job['totals']['skipped_closed']++; continue; }

        $row = format_doctor($place, $details, $city, $spec);
        $verdict = quality_check($row, $city);
        if (str_starts_with($verdict, 'SKIP:')) {
            $reason = substr($verdict, 5);
            if ($reason === 'no_address')    $job['totals']['skipped_addr']++;
            elseif ($reason === 'city_mismatch') $job['totals']['skipped_city']++;
            elseif ($reason === 'closed')    $job['totals']['skipped_closed']++;
            continue;
        }
        if ($verdict !== '') {
            $row['dropped_reason'] = $verdict;
            $job['totals']['flagged']++;
        }
        $newRows[] = $row;
        $existingIds[$pid] = true; // also dedup within this chunk
    }

    // Save merged JSON for the city
    if (!empty($newRows)) {
        $merged = dedupe_by_place_id(array_merge($existing, $newRows));
        file_put_contents($cityPath, json_encode([
            'city'       => $city['name'],
            'state'      => $city['state'],
            'country'    => 'IN',
            'count'      => count($merged),
            'updated_at' => date('Y-m-d H:i:s'),
            'doctors'    => $merged,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    $found = count($newRows);
    $job['totals']['doctors_new'] += $found;
    $job['totals']['requests']    += $reqs;
    $job['cursor']++;
    $job['updated_at'] = date('Y-m-d H:i:s');

    job_append_log($job, sprintf(
        '%s · %s · area %d/%d → +%d doctors (%d req)',
        $city['name'], $q, $task['aIdx'] + 1, $task['aCount'], $found, $reqs
    ));

    if ($job['cursor'] >= $job['total']) $job['status'] = 'done';
}

// =====================================================================
// ROUTER
// =====================================================================

$action = $_GET['action'] ?? null;
$jobId  = isset($_GET['job']) ? (string) $_GET['job'] : null;

// ----- AJAX: do ONE chunk -----
if ($action === 'step' && $jobId !== null) {
    header('Content-Type: application/json; charset=utf-8');
    @set_time_limit(60);  // each chunk should comfortably finish in < 30s
    if ($apiKey === '') { echo json_encode(['error' => 'no_api_key']); exit; }

    $job = job_load($jobId);
    if (!$job) { http_response_code(404); echo json_encode(['error' => 'job_not_found']); exit; }
    if ($job['status'] === 'paused') { echo json_encode(_job_snapshot($job)); exit; }
    if ($job['status'] === 'done')   { echo json_encode(_job_snapshot($job)); exit; }

    try {
        run_one_chunk($job, $apiKey, $JSON_DIR);
        job_save($job);
    } catch (Throwable $e) {
        $job['status'] = 'error';
        job_append_log($job, 'ERROR: ' . $e->getMessage());
        job_save($job);
    }
    echo json_encode(_job_snapshot($job));
    exit;
}

// ----- AJAX: status only -----
if ($action === 'status' && $jobId !== null) {
    header('Content-Type: application/json; charset=utf-8');
    $job = job_load($jobId);
    if (!$job) { http_response_code(404); echo json_encode(['error' => 'job_not_found']); exit; }
    echo json_encode(_job_snapshot($job));
    exit;
}

// ----- AJAX: pause / resume / cancel -----
if ($action === 'pause' && $jobId !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $job = job_load($jobId);
    if (!$job) { http_response_code(404); echo json_encode(['error' => 'job_not_found']); exit; }
    if ($job['status'] === 'running') $job['status'] = 'paused';
    elseif ($job['status'] === 'paused') $job['status'] = 'running';
    job_save($job);
    echo json_encode(_job_snapshot($job));
    exit;
}

function _job_snapshot(array $job): array {
    // Strip the giant tasks[] array from the snapshot so the AJAX payload stays small.
    $cur = $job['cursor'];
    $total = $job['total'];
    $currentTask = ($cur < $total) ? $job['tasks'][$cur] : null;
    return [
        'id'         => $job['id'],
        'status'     => $job['status'],
        'cursor'     => $cur,
        'total'      => $total,
        'percent'    => $total > 0 ? round($cur / $total * 100, 1) : 100,
        'totals'     => $job['totals'],
        'log'        => array_slice($job['log'], -30),  // last 30 lines only
        'current'    => $currentTask ? [
            'city'  => $currentTask['city'],
            'state' => $currentTask['state'],
            'q'     => $currentTask['q'],
            'qIdx'  => $currentTask['qIdx'] + 1,
            'qCount'=> $currentTask['qCount'],
            'aIdx'  => $currentTask['aIdx'] + 1,
            'aCount'=> $currentTask['aCount'],
        ] : null,
        'updated_at' => $job['updated_at'],
        'cities'     => $job['cities'],
    ];
}

// ----- POST: create job from picker -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cities'])) {
    if ($apiKey === '') {
        $err = 'GOOGLE_MAPS_API_KEY missing in fetch_doctor/.env';
    } else {
        $picked = [];
        foreach ((array) $_POST['cities'] as $name) {
            $c = find_city($STATES, (string) $name);
            if ($c) $picked[] = $c;
        }
        if (empty($picked)) {
            $err = 'No valid cities selected.';
        } else {
            $newJobId = job_create($picked, $QUERIES);
            header('Location: ?job=' . $newJobId);
            exit;
        }
    }
}

// ----- View: progress UI -----
if ($jobId !== null) {
    $job = job_load($jobId);
    if (!$job) {
        echo '<p style="font-family:sans-serif;padding:40px;">Job not found.</p>';
        exit;
    }
    render_progress_ui($job);
    exit;
}

// ----- Default: picker UI -----
render_picker_ui($STATES, $QUERIES, $apiKey, $JSON_DIR, $err ?? null);

// =====================================================================
// VIEWS
// =====================================================================

function render_picker_ui(array $STATES, array $QUERIES, string $apiKey, string $jsonDir, ?string $err): void {
    $keyMissing = ($apiKey === '');
    $existingJson = [];
    foreach (glob($jsonDir . '/*.json') ?: [] as $f) {
        $raw = json_decode((string) file_get_contents($f), true);
        $existingJson[basename($f)] = (int) ($raw['count'] ?? 0);
    }
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fetch Doctors — eClinicPro</title>
<style>
:root { --teal:#0F9B6E; --line:rgba(0,0,0,0.08); --bg:#f8fafc; --mute:#64748b; --ink:#0f172a; }
*{box-sizing:border-box}body{font:14px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;margin:0;background:var(--bg);color:var(--ink)}
.wrap{max-width:1000px;margin:0 auto;padding:32px 20px 80px}
h1{font-size:24px;font-weight:600;margin:0 0 8px}
.lede{color:var(--mute);margin:0 0 24px}
.warn,.err{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:13px}
.warn{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412}
.err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
.toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.toolbar button,.toolbar a{font:inherit;background:#fff;border:1px solid var(--line);padding:7px 14px;border-radius:8px;cursor:pointer;color:var(--ink);text-decoration:none}
.toolbar button:hover,.toolbar a:hover{border-color:var(--teal);color:var(--teal)}
.state{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-bottom:12px}
.state-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.state-name{font-weight:600;font-size:15px}
.state-action{font-size:12px;color:var(--teal);cursor:pointer;background:none;border:0;padding:0}
.cities{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px}
.city{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--line);border-radius:8px;cursor:pointer;background:#fff;font-size:13px}
.city:hover{border-color:var(--teal)}
.city input{margin:0;cursor:pointer}
.city.has-data{background:#ecfdf5;border-color:#a7f3d0}
.city-tag{margin-left:auto;font-size:11px;color:var(--mute)}
.submit{position:sticky;bottom:16px;margin-top:24px;background:var(--ink);color:#fff;padding:14px 18px;border:0;border-radius:12px;font-size:14px;font-weight:500;cursor:pointer;width:100%;box-shadow:0 8px 30px rgba(0,0,0,0.15)}
.submit:hover{background:var(--teal)}
.summary{display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:var(--mute);margin:8px 0 16px}
.summary strong{color:var(--ink)}
.note{font-size:12px;color:var(--mute);margin-top:16px;padding:12px;background:#fff;border-radius:8px;border-left:3px solid var(--teal)}
@media(max-width:600px){.cities{grid-template-columns:1fr 1fr}}
</style></head><body><div class="wrap">

<h1>🩺 Fetch Doctors</h1>
<p class="lede">Pick cities below. Clicking <strong>Start</strong> opens a live progress page that fetches each city in tiny chunks — no timeouts.</p>

<?php if ($keyMissing): ?>
    <div class="warn">⚠ <strong>GOOGLE_MAPS_API_KEY missing.</strong> Create <code>fetch_doctor/.env</code>: <code>GOOGLE_MAPS_API_KEY=your_key</code></div>
<?php endif; ?>
<?php if ($err): ?><div class="err">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

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
        ?>
        <label class="city <?= $hasData ? 'has-data' : '' ?>">
            <input type="checkbox" name="cities[]" value="<?= htmlspecialchars($c['name']) ?>">
            <span><?= htmlspecialchars($c['name']) ?></span>
            <?php if ($hasData): ?><span class="city-tag"><?= $existingJson[$jsonFile] ?> saved</span><?php endif; ?>
        </label>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<button type="submit" class="submit">▶ Start fetch &nbsp;·&nbsp; <span id="count">0</span> selected</button>

<div class="note">
    Each city has <strong><?= count($QUERIES) ?> specialty queries × 9 sub-areas = ~<?= count($QUERIES) * 9 ?> chunks</strong>. Each chunk takes 5-15 seconds and runs as its own AJAX call — no PHP timeouts. Budget ~$5-$10 per city in API costs.
</div>
</form>

</div><script>
function toggleAll(on){document.querySelectorAll('input[name="cities[]"]').forEach(i=>i.checked=on);updateCount()}
function toggleState(b){const x=b.closest('.state').querySelectorAll('input[name="cities[]"]'),off=[...x].some(b=>!b.checked);x.forEach(b=>b.checked=off);updateCount()}
function updateCount(){document.getElementById('count').textContent=document.querySelectorAll('input[name="cities[]"]:checked').length}
document.addEventListener('change',e=>{if(e.target.matches('input[name="cities[]"]'))updateCount()})
</script></body></html><?php
}

function render_progress_ui(array $job): void {
    $jobId = $job['id'];
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fetching… — eClinicPro</title>
<style>
:root { --teal:#0F9B6E; --line:rgba(0,0,0,0.08); --bg:#f8fafc; --mute:#64748b; --ink:#0f172a; }
*{box-sizing:border-box}body{font:14px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;margin:0;background:var(--bg);color:var(--ink)}
.wrap{max-width:880px;margin:0 auto;padding:32px 20px 80px}
h1{font-size:22px;font-weight:600;margin:0 0 4px}
.sub{color:var(--mute);font-size:13px;margin-bottom:24px}
.bar-wrap{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px 20px;margin-bottom:16px}
.bar-row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px}
.bar-num{font-size:32px;font-weight:300;letter-spacing:-1px}
.bar-num small{font-size:14px;color:var(--mute);font-weight:400;letter-spacing:0}
.bar-pct{font-size:13px;color:var(--mute)}
.bar{background:var(--bg);border-radius:8px;height:8px;overflow:hidden}
.bar-fill{background:linear-gradient(90deg,var(--teal),#34d399);height:100%;transition:width .25s}
.current{margin-top:14px;padding-top:12px;border-top:1px solid var(--line);font-size:13px;color:var(--mute)}
.current strong{color:var(--ink);font-weight:500}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:16px}
.stat{background:#fff;border:1px solid var(--line);border-radius:10px;padding:12px 14px}
.stat .v{font-size:22px;font-weight:300;letter-spacing:-0.5px}
.stat .l{font-size:11px;color:var(--mute);text-transform:uppercase;letter-spacing:0.06em;margin-top:2px}
.toolbar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.toolbar button,.toolbar a{font:inherit;background:#fff;border:1px solid var(--line);padding:8px 14px;border-radius:8px;cursor:pointer;color:var(--ink);text-decoration:none}
.toolbar .primary{background:var(--ink);color:#fff;border-color:var(--ink)}
.toolbar button:hover{border-color:var(--teal);color:var(--teal)}
.toolbar .primary:hover{background:var(--teal);color:#fff}
.log{background:#0f172a;color:#e2e8f0;border-radius:12px;padding:14px 18px;font:12px/1.55 'JetBrains Mono',ui-monospace,monospace;max-height:380px;overflow-y:auto}
.log .row{padding:1px 0;white-space:pre-wrap}
.log .row:nth-child(odd){opacity:.85}
.done-banner{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:18px 20px;border-radius:12px;margin-bottom:16px;font-weight:500}
.err-banner{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:18px 20px;border-radius:12px;margin-bottom:16px}
.paused{color:#9a3412}
@media(max-width:600px){.bar-num{font-size:26px}.stat .v{font-size:18px}}
</style></head><body>

<div class="wrap" x-data="progress(<?= htmlspecialchars(json_encode(['jobId' => $jobId]), ENT_QUOTES) ?>)" x-init="init()">
    <h1>🩺 Fetching doctors</h1>
    <p class="sub">
        Cities: <strong><?= htmlspecialchars(implode(', ', $job['cities'])) ?></strong>
        · Job <code><?= htmlspecialchars($jobId) ?></code>
    </p>

    <template x-if="s.status === 'done'">
        <div class="done-banner">
            ✅ All done. <span x-text="s.totals.doctors_new"></span> new doctors saved across <span x-text="s.cities.length"></span> cit<span x-text="s.cities.length === 1 ? 'y' : 'ies'"></span>.
            Next: <a href="insert_db.php" style="color:#065f46;font-weight:600;">→ Import to database</a>
        </div>
    </template>
    <template x-if="s.status === 'error'">
        <div class="err-banner">❌ Job ended with an error. See log below.</div>
    </template>

    <div class="bar-wrap">
        <div class="bar-row">
            <div class="bar-num"><span x-text="s.cursor"></span><small> / <span x-text="s.total"></span> chunks</small></div>
            <div class="bar-pct">
                <span x-text="s.percent"></span>%
                <template x-if="s.status === 'paused'"><span class="paused"> · paused</span></template>
            </div>
        </div>
        <div class="bar"><div class="bar-fill" :style="'width:' + s.percent + '%'"></div></div>
        <template x-if="s.current">
            <div class="current">
                Now: <strong x-text="s.current.city"></strong> ·
                <strong x-text="s.current.q"></strong>
                <span>(query <span x-text="s.current.qIdx"></span>/<span x-text="s.current.qCount"></span>,
                area <span x-text="s.current.aIdx"></span>/<span x-text="s.current.aCount"></span>)</span>
            </div>
        </template>
    </div>

    <div class="stats">
        <div class="stat"><div class="v" x-text="s.totals.doctors_new.toLocaleString()"></div><div class="l">Doctors saved</div></div>
        <div class="stat"><div class="v" x-text="s.totals.requests.toLocaleString()"></div><div class="l">API requests</div></div>
        <div class="stat"><div class="v" x-text="'$' + (s.totals.requests * 0.024).toFixed(2)"></div><div class="l">Est. cost</div></div>
        <div class="stat"><div class="v" x-text="(s.totals.skipped_closed + s.totals.skipped_type + s.totals.skipped_addr + s.totals.skipped_city).toLocaleString()"></div><div class="l">Filtered out</div></div>
        <div class="stat"><div class="v" x-text="s.totals.flagged.toLocaleString()"></div><div class="l">Flagged low-quality</div></div>
    </div>

    <div class="toolbar">
        <template x-if="s.status === 'running' || s.status === 'paused'">
            <button type="button" @click="togglePause()" x-text="s.status === 'paused' ? '▶ Resume' : '⏸ Pause'"></button>
        </template>
        <template x-if="s.status === 'done'">
            <a href="insert_db.php" class="primary">→ Import to database</a>
        </template>
        <a href="index.php">← New job</a>
    </div>

    <div class="log">
        <template x-for="line in s.log" :key="line">
            <div class="row" x-text="line"></div>
        </template>
        <template x-if="s.log.length === 0">
            <div class="row" style="opacity:.6;">Waiting for first chunk to complete…</div>
        </template>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function progress(cfg){
    return {
        jobId: cfg.jobId,
        s: { status:'running', cursor:0, total:1, percent:0, totals:{doctors_new:0,requests:0,skipped_closed:0,skipped_type:0,skipped_addr:0,skipped_city:0,flagged:0,detail_fail:0}, log:[], current:null, cities:[], updated_at:'' },
        running: false,
        init(){
            // Initial status load, then loop.
            this.fetchStatus().then(()=> this.loop());
        },
        async fetchStatus(){
            try {
                const r = await fetch('?action=status&job=' + this.jobId);
                if (!r.ok) return;
                this.s = await r.json();
            } catch(e) {}
        },
        async loop(){
            if (this.running) return;
            this.running = true;
            while (this.s.status === 'running') {
                try {
                    const r = await fetch('?action=step&job=' + this.jobId);
                    if (!r.ok) { this.s.status = 'error'; break; }
                    this.s = await r.json();
                } catch(e) {
                    // Network blip: pause briefly and continue.
                    await this.sleep(2000);
                }
                await this.sleep(300); // tiny gap so DB write + UI repaint happen
            }
            this.running = false;
        },
        async togglePause(){
            const r = await fetch('?action=pause&job=' + this.jobId, { method:'POST' });
            this.s = await r.json();
            if (this.s.status === 'running' && !this.running) this.loop();
        },
        sleep(ms){ return new Promise(r => setTimeout(r, ms)); },
    };
}
</script>
</body></html><?php
}
