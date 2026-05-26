<?php
// =====================================================================
// search_doctors_query.php — shared SQL search logic used by both:
//   - api/search_doctors.php (AJAX paginated search)
//   - find-a-doctor.php      (server-side first-page render)
//
// Keeping this in one place means the SSR output and the AJAX output
// stay byte-identical, which is what lets us hydrate Alpine without a
// flash of empty content.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Run the public-directory search.
 *
 * @param array{
 *   q?:string, country?:string, state?:string, city?:string, area?:string,
 *   spec?:string, min_rating?:float, sort?:string,
 *   lat?:?float, lng?:?float, max_km?:float,
 *   page?:int, per_page?:int
 * } $filters
 *
 * @return array{
 *   ok:bool, items:array<int,array<string,mixed>>,
 *   page:int, per_page:int, has_more:bool, total?:int
 * }
 */
function ecp_search_doctors(array $filters): array {
    $db = ecp_db();
    if (!$db) return ['ok' => false, 'items' => [], 'page' => 1, 'per_page' => 0, 'has_more' => false];

    $q          = trim((string) ($filters['q']      ?? ''));
    $country    = strtoupper(trim((string) ($filters['country'] ?? 'IN')));
    $state      = trim((string) ($filters['state']  ?? ''));
    $city       = trim((string) ($filters['city']   ?? ''));
    $area       = trim((string) ($filters['area']   ?? ''));
    $spec       = trim((string) ($filters['spec']   ?? ''));
    $minRating  = (float) ($filters['min_rating'] ?? 0);
    $sort       = (string) ($filters['sort']      ?? 'relevance');
    $lat        = isset($filters['lat']) ? (float) $filters['lat'] : null;
    $lng        = isset($filters['lng']) ? (float) $filters['lng'] : null;
    $maxKm      = (float) ($filters['max_km'] ?? 0);
    $page       = max(1, (int) ($filters['page']     ?? 1));
    $perPage    = min(50, max(1, (int) ($filters['per_page'] ?? 20)));
    $offset     = ($page - 1) * $perPage;

    // Soft caps so a malicious URL can't blow up the query.
    $q     = mb_substr($q,     0, 80);
    $state = mb_substr($state, 0, 80);
    $city  = mb_substr($city,  0, 80);
    $area  = mb_substr($area,  0, 120);
    $spec  = mb_substr($spec,  0, 80);

    // ---------- WHERE ----------
    $where  = ["dd.is_active = 1", "dd.status = 'OPERATIONAL'", "dd.country = :country"];
    $params = ['country' => $country];

    if ($state !== '') { $where[] = "dd.state = :state"; $params['state'] = $state; }
    if ($city  !== '') { $where[] = "dd.city  = :city";  $params['city']  = $city; }
    if ($area  !== '') { $where[] = "dd.area  = :area";  $params['area']  = $area; }
    if ($spec  !== '') { $where[] = "dd.specialty = :spec"; $params['spec'] = $spec; }
    if ($minRating > 0) { $where[] = "dd.rating >= :rating"; $params['rating'] = $minRating; }

    $relevanceExpr = null;
    if ($q !== '') {
        if (strlen($q) >= 3) {
            $where[] = "MATCH(dd.name, dd.doctor_name) AGAINST(:q IN BOOLEAN MODE)";
            $tokens = array_filter(preg_split('/\s+/', $q) ?: []);
            $tokens = array_map(static fn ($t) => preg_replace('/[^\w]/', '', $t) . '*', $tokens);
            $params['q']   = implode(' ', $tokens);
            $relevanceExpr = "MATCH(dd.name, dd.doctor_name) AGAINST(:qrel IN BOOLEAN MODE)";
            $params['qrel'] = $params['q'];
        } else {
            $where[] = "(dd.name LIKE :qlike OR dd.doctor_name LIKE :qlike OR dd.area LIKE :qlike OR dd.city LIKE :qlike)";
            $params['qlike'] = $q . '%';
        }
    }

    // ---------- SELECT (with optional distance) ----------
    $selectDistance = null;
    if ($lat !== null && $lng !== null) {
        $selectDistance =
            "(6371 * 2 * ASIN(SQRT(POWER(SIN((:ulat - dd.lat) * PI() / 360), 2)"
          . " + COS(:ulat * PI() / 180) * COS(dd.lat * PI() / 180)"
          . " * POWER(SIN((:ulng - dd.lng) * PI() / 360), 2)))) AS distance_km";
        $params['ulat'] = $lat;
        $params['ulng'] = $lng;
    }

    $order = match ($sort) {
        'distance'  => $selectDistance !== null ? "distance_km IS NULL, distance_km ASC"
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

    $selectCols = "dd.id, dd.name, dd.doctor_name, dd.specialty, dd.country, dd.city,
                   dd.state, dd.area, dd.address, dd.lat, dd.lng,
                   dd.phone, dd.website, dd.gmaps_url, dd.rating, dd.reviews,
                   dd.opening_hours, dd.photo_reference,
                   dd.consultation_fee, dd.consultation_fee_currency,
                   dd.is_claimed, dd.quality_score";
    if ($selectDistance !== null) $selectCols .= ",\n                   " . $selectDistance;

    $whereSql  = implode(' AND ', $where);
    $havingSql = '';
    if ($selectDistance !== null && $maxKm > 0) {
        $havingSql = " HAVING distance_km <= :max_km";
        $params['max_km'] = $maxKm;
    }

    $sql = "SELECT $selectCols FROM directory_doctors dd
            WHERE $whereSql $havingSql
            ORDER BY $order LIMIT :lim OFFSET :off";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $items = array_map('ecp_shape_directory_row', $rows);
    $resp  = [
        'ok'       => true,
        'items'    => $items,
        'page'     => $page,
        'per_page' => $perPage,
        'has_more' => count($items) === $perPage,
    ];
    if ($total !== null) $resp['total'] = $total;
    return $resp;
}

/**
 * Shape one directory_doctors row into the JSON object the front-end uses.
 * Kept identical in api/search_doctors.php so SSR == AJAX output.
 */
function ecp_shape_directory_row(array $r): array {
    $clinicName = (string) ($r['name'] ?? '');
    $doctorName = trim((string) ($r['doctor_name'] ?? ''));
    $display    = $doctorName !== '' ? $doctorName : $clinicName;
    $forInit    = preg_replace('/^Dr\.?\s+/i', '', $display) ?? $display;
    $parts      = preg_split('/\s+/', trim($forInit)) ?: [''];
    $first      = mb_substr($parts[0] ?? '', 0, 1) ?: 'D';
    $last       = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

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
        'countryName'  => $r['country'] ?? '',
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
        'qual'         => '',
        'years'        => 0,
        'langs'        => ['English'],
        'gender'       => '',
        'video'        => false,
        'next'         => ['when' => 'later', 'label' => 'Contact clinic', 'sub' => ''],
    ];
}
