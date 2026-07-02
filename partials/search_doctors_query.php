<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/directory_profile.php';

/**
 * @param array<string, mixed> $filters
 * @return array{ok:bool, items:list<array<string,mixed>>, page:int, per_page:int, has_more:bool, total?:int}
 */
function ecp_search_doctors(array $filters): array {
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'items' => [], 'page' => 1, 'per_page' => 0, 'has_more' => false];
    }

    $q         = trim((string) ($filters['q'] ?? ''));
    $country   = strtoupper(trim((string) ($filters['country'] ?? 'IN')));
    $state     = trim((string) ($filters['state'] ?? ''));
    $city      = trim((string) ($filters['city'] ?? ''));
    $area      = trim((string) ($filters['area'] ?? ''));
    $locText   = trim((string) ($filters['loc'] ?? ''));
    $spec      = trim((string) ($filters['spec'] ?? ''));
    if ($spec === 'all') {
        $spec = '';
    }
    $minRating = (float) ($filters['min_rating'] ?? 0);
    $sort      = (string) ($filters['sort'] ?? 'relevance');
    $lat       = isset($filters['lat']) ? (float) $filters['lat'] : null;
    $lng       = isset($filters['lng']) ? (float) $filters['lng'] : null;
    $maxKm     = (float) ($filters['max_km'] ?? 0);
    $page      = max(1, (int) ($filters['page'] ?? 1));
    $perPage   = min(50, max(1, (int) ($filters['per_page'] ?? 20)));
    $offset    = ($page - 1) * $perPage;
    $noFulltext = !empty($filters['_no_fulltext']);

    $q     = mb_substr($q, 0, 80);
    $state = mb_substr($state, 0, 80);
    $city  = mb_substr($city, 0, 80);
    $area    = mb_substr($area, 0, 120);
    $spec    = mb_substr($spec, 0, 80);
    $locText = mb_substr($locText, 0, 120);

    if ($locText !== '' && $city === '' && $state === '' && $area === '') {
        $resolved = ecp_resolve_location_filter($locText, $country);
        foreach (['state', 'city', 'area'] as $key) {
            if (!empty($resolved[$key])) {
                ${$key} = (string) $resolved[$key];
            }
        }
    }

    $where  = ["dd.is_active = 1", "dd.status = 'OPERATIONAL'", "dd.country = :country"];
    $params = ['country' => $country];

    if ($state !== '') { $where[] = 'LOWER(dd.state) = LOWER(:state)'; $params['state'] = $state; }
    if ($city !== '')  { $where[] = 'LOWER(dd.city) = LOWER(:city)';   $params['city']  = $city; }
    if ($area !== '')  { $where[] = 'LOWER(dd.area) = LOWER(:area)';   $params['area']  = $area; }
    if ($locText !== '' && $city === '' && $state === '' && $area === '') {
        $where[] = '(dd.city LIKE :loclike1 OR dd.area LIKE :loclike2 OR dd.state LIKE :loclike3)';
        $locLike = '%' . $locText . '%';
        $params['loclike1'] = $locLike;
        $params['loclike2'] = $locLike;
        $params['loclike3'] = $locLike;
    }
    if ($spec !== '')  { $where[] = 'dd.specialty = :spec'; $params['spec'] = $spec; }
    if ($minRating > 0) { $where[] = 'dd.rating >= :rating'; $params['rating'] = $minRating; }

    $relevanceExpr = null;
    $needsSpecJoin = false;
    if ($q !== '') {
        $like = '%' . $q . '%';
        $likeParts = [
            'dd.name LIKE :qlike1',
            'dd.doctor_name LIKE :qlike2',
            'dd.area LIKE :qlike3',
            'dd.city LIKE :qlike4',
            'dd.specialty LIKE :qlike5',
            'sm.label LIKE :qlike6',
            'sm.plural_label LIKE :qlike7',
            'sm.url_slug LIKE :qlike8',
        ];
        foreach (range(1, 8) as $i) {
            $params['qlike' . $i] = $like;
        }
        $needsSpecJoin = true;

        $rawTokens = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ftTokens  = array_values(array_filter($rawTokens, static fn ($t) => mb_strlen($t) >= 3));

        if ($ftTokens && !$noFulltext) {
            $ftQuery = implode(' ', array_map(static fn ($t) => $t . '*', $ftTokens));
            $where[] = '('
                . 'MATCH(dd.name, dd.doctor_name) AGAINST(:qft IN BOOLEAN MODE)'
                . ' OR ' . implode(' OR ', $likeParts)
                . ')';
            $params['qft'] = $ftQuery;
            $relevanceExpr = 'MATCH(dd.name, dd.doctor_name) AGAINST(:qrel IN BOOLEAN MODE)';
            $params['qrel'] = $ftQuery;
        } else {
            $where[] = '(' . implode(' OR ', $likeParts) . ')';
        }
    }

    $selectDistance = null;
    if ($lat !== null && $lng !== null) {
        $selectDistance =
            '(6371 * 2 * ASIN(SQRT(POWER(SIN((:ulat - dd.lat) * PI() / 360), 2)'
          . ' + COS(:ulat * PI() / 180) * COS(dd.lat * PI() / 180)'
          . ' * POWER(SIN((:ulng - dd.lng) * PI() / 360), 2)))) AS distance_km';
        $params['ulat'] = $lat;
        $params['ulng'] = $lng;
    }

    // Default ranking tiers: joined clinics first, then listings WITH a photo,
    // then the rest by quality. Photos make cards look complete/trustworthy, so
    // photo'd listings rank above photo-less ones within the same claim tier.
    $hasPhoto = "(dd.photo_reference IS NOT NULL AND dd.photo_reference <> '') DESC";
    $order = match ($sort) {
        'distance' => $selectDistance !== null ? 'distance_km IS NULL, distance_km ASC' : "dd.is_claimed DESC, $hasPhoto, dd.quality_score DESC",
        'rating'   => 'dd.rating DESC, dd.reviews DESC',
        'fee_asc'  => 'dd.consultation_fee IS NULL, dd.consultation_fee ASC',
        'fee_desc' => 'dd.consultation_fee IS NULL, dd.consultation_fee DESC',
        'claimed'  => "dd.is_claimed DESC, $hasPhoto, dd.quality_score DESC",
        'relevance', '' => ($relevanceExpr !== null)
            ? "$relevanceExpr DESC, dd.is_claimed DESC, $hasPhoto, dd.quality_score DESC"
            : "dd.is_claimed DESC, $hasPhoto, dd.quality_score DESC, dd.reviews DESC, dd.rating DESC",
        default => "dd.is_claimed DESC, $hasPhoto, dd.quality_score DESC",
    };

    $slugCols = ecp_profile_slug_columns_ready($db) ? 'dd.entity_type, dd.listing_slug,' : '';
    $selectCols = "dd.id, dd.name, dd.doctor_name,
                   (SELECT u.name
                      FROM users u
                     WHERE u.clinic_id = dd.claimed_tenant_id
                       AND u.is_owner = 1
                       AND u.is_active = 1
                     ORDER BY u.id ASC
                     LIMIT 1) AS owner_doctor_name,
                   {$slugCols}
                   dd.specialty, dd.country, dd.city, dd.state, dd.area, dd.address,
                   dd.lat, dd.lng, dd.phone, dd.website, dd.gmaps_url, dd.rating, dd.reviews,
                   dd.opening_hours, dd.photo_reference, dd.types, dd.languages,
                   dd.consultation_fee, dd.consultation_fee_currency,
                   dd.is_claimed, dd.quality_score, t.slug AS clinic_slug,
                   t.logo_path AS clinic_logo_path";
    if ($selectDistance !== null) {
        $selectCols .= ",\n                   " . $selectDistance;
    }

    $fromSql = 'directory_doctors dd LEFT JOIN tenants t ON t.id = dd.claimed_tenant_id'
        . ($needsSpecJoin ? ' LEFT JOIN specialty_master sm ON sm.slug = dd.specialty AND sm.is_active = 1' : '');

    $whereSql  = implode(' AND ', $where);
    $havingSql = '';
    if ($selectDistance !== null && $maxKm > 0) {
        $havingSql = ' HAVING distance_km <= :max_km';
        $params['max_km'] = $maxKm;
    }

    $sql = "SELECT $selectCols FROM $fromSql WHERE $whereSql $havingSql ORDER BY $order LIMIT :lim OFFSET :off";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);

    try {
        $stmt->execute();
    } catch (PDOException $e) {
        if ($q === '' || !isset($params['qft'])) {
            throw $e;
        }
        return ecp_search_doctors($filters + ['_no_fulltext' => true]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = null;
    if ($page === 1) {
        $cnt = $db->prepare("SELECT COUNT(*) FROM $fromSql WHERE $whereSql");
        foreach ($params as $k => $v) {
            if (in_array($k, ['lim', 'off', 'max_km', 'ulat', 'ulng', 'qrel'], true)) {
                continue;
            }
            $cnt->bindValue(':' . $k, $v);
        }
        $cnt->execute();
        $total = (int) $cnt->fetchColumn();
    }

    $resp = [
        'ok'       => true,
        'items'    => array_map('ecp_shape_directory_row', $rows),
        'page'     => $page,
        'per_page' => $perPage,
        'has_more' => count($rows) === $perPage,
    ];
    if ($total !== null) {
        $resp['total'] = $total;
    }

    return $resp;
}

/** @param array<string, mixed> $r */
function ecp_shape_directory_row(array $r): array {
    $clinicName = (string) ($r['name'] ?? '');
    $ownerDoctorName = trim((string) ($r['owner_doctor_name'] ?? ''));
    $doctorName = $ownerDoctorName !== ''
        ? $ownerDoctorName
        : trim((string) ($r['doctor_name'] ?? ''));
    $display    = $doctorName !== '' ? $doctorName : $clinicName;
    $avatar     = ecp_directory_avatar($r, 400);
    $first      = mb_substr($avatar['initials'], 0, 1) ?: 'D';
    $last       = mb_strlen($avatar['initials']) > 1 ? mb_substr($avatar['initials'], -1) : '';

    $hours = null;
    if (!empty($r['opening_hours'])) {
        $d = json_decode((string) $r['opening_hours'], true);
        if (is_array($d)) {
            $hours = $d;
        }
    }

    $langs = ['English'];
    if (!empty($r['languages'])) {
        $decoded = json_decode((string) $r['languages'], true);
        if (is_array($decoded)) {
            $clean = array_values(array_filter(array_map(
                static fn ($l): string => trim((string) $l),
                $decoded
            ), static fn (string $l): bool => $l !== ''));
            if ($clean !== []) {
                $langs = $clean;
            }
        }
    }

    return [
        'id'              => (int) $r['id'],
        'name'            => $display,
        'doctorName'      => $doctorName ?: null,
        'clinicName'      => $clinicName,
        'hospital'        => ($doctorName !== '' && $clinicName !== $doctorName) ? $clinicName : '',
        'firstInitial'    => $first,
        'lastInitial'     => $last,
        'avatar_gradient' => $avatar['gradient'],
        'spec'            => $r['specialty'] ?? 'gp',
        'specLabel'       => ecp_specialty_label($r['specialty'] ?? null),
        'verified'        => (bool) $r['is_claimed'],
        'is_claimed'      => (bool) $r['is_claimed'],
        'slug'            => $r['clinic_slug'] ?? null,
        'entity_type'     => ecp_directory_entity_type($r),
        'listing_slug'    => ecp_directory_listing_slug($r),
        'profile_url'     => ecp_directory_profile_url($r),
        'rating'          => isset($r['rating']) ? (float) $r['rating'] : 0,
        'reviews'         => (int) ($r['reviews'] ?? 0),
        'area'            => $r['area'] ?? '',
        'city'            => $r['city'] ?? '',
        'state'           => $r['state'] ?? '',
        'country'         => $r['country'] ?? 'IN',
        'countryName'     => $r['country'] ?? '',
        'fee'             => isset($r['consultation_fee']) && $r['consultation_fee'] !== null ? (float) $r['consultation_fee'] : 0,
        'currency'        => $r['consultation_fee_currency'] ?? ((($r['country'] ?? 'IN') === 'IN') ? '₹' : '$'),
        'phone'           => $r['phone'] ?? null,
        'website'         => $r['website'] ?? null,
        'gmaps_url'       => $r['gmaps_url'] ?? null,
        'lat'             => isset($r['lat']) ? (float) $r['lat'] : null,
        'lng'             => isset($r['lng']) ? (float) $r['lng'] : null,
        'address'         => $r['address'] ?? null,
        'opening_hours'   => $hours,
        'photo_url'       => $avatar['url'],
        'distance_km'     => isset($r['distance_km']) ? round((float) $r['distance_km'], 1) : null,
        'qual'            => '',
        'years'           => 0,
        'langs'           => $langs,
        'gender'          => '',
        'video'           => false,
        'next'            => ['when' => 'later', 'label' => 'Contact clinic', 'sub' => ''],
    ];
}

/**
 * @return array{state?:string, city?:string, area?:string}
 */
function ecp_resolve_location_filter(string $loc, string $country = 'IN'): array
{
    $loc = trim($loc);
    if ($loc === '') {
        return [];
    }

    $db = ecp_db();
    if (!$db) {
        return [];
    }

    $country = strtoupper($country) ?: 'IN';
    $parts = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $loc) ?: [])));
    $primary = $parts[0] ?? $loc;
    $secondary = $parts[1] ?? '';

    if ($secondary !== '') {
        $area = ecp_lookup_directory_place($db, $country, 'area', $primary, $secondary);
        if ($area !== null) {
            return $area;
        }
    }

    foreach (['city' => $primary, 'area' => $primary, 'state' => $primary] as $kind => $needle) {
        $hit = ecp_lookup_directory_place($db, $country, $kind, $needle, '');
        if ($hit !== null) {
            return $hit;
        }
    }

    if ($secondary !== '') {
        foreach (['city' => $secondary, 'state' => $secondary] as $kind => $needle) {
            $hit = ecp_lookup_directory_place($db, $country, $kind, $needle, '');
            if ($hit !== null) {
                return $hit;
            }
        }
    }

    return [];
}

/** @return array{state?:string, city?:string, area?:string}|null */
function ecp_lookup_directory_place(PDO $db, string $country, string $kind, string $needle, string $context): ?array
{
    $needle = trim($needle);
    if ($needle === '') {
        return null;
    }

    $likeExact = $needle;
    $likePrefix = $needle . '%';

    if ($kind === 'area') {
        $sql = "SELECT area, city, state FROM directory_doctors
                WHERE country = :c AND is_active = 1 AND status = 'OPERATIONAL'
                  AND area IS NOT NULL AND area <> ''
                  AND (LOWER(area) = LOWER(:exact) OR area LIKE :prefix)";
        $params = ['c' => $country, 'exact' => $likeExact, 'prefix' => $likePrefix];
        if ($context !== '') {
            $sql .= " AND (LOWER(city) = LOWER(:ctx) OR city LIKE :ctxp)";
            $params['ctx'] = $context;
            $params['ctxp'] = $context . '%';
        }
        $sql .= ' ORDER BY (LOWER(area) = LOWER(:exact_ord)) DESC, area LIMIT 1';
        $params['exact_ord'] = $likeExact;
    } elseif ($kind === 'city') {
        $sql = "SELECT city, state FROM directory_doctors
                WHERE country = :c AND is_active = 1 AND status = 'OPERATIONAL'
                  AND city IS NOT NULL AND city <> ''
                  AND (LOWER(city) = LOWER(:exact) OR city LIKE :prefix)
                ORDER BY (LOWER(city) = LOWER(:exact_ord)) DESC, city LIMIT 1";
        $params = ['c' => $country, 'exact' => $likeExact, 'prefix' => $likePrefix, 'exact_ord' => $likeExact];
    } else {
        $sql = "SELECT state FROM directory_doctors
                WHERE country = :c AND is_active = 1 AND status = 'OPERATIONAL'
                  AND state IS NOT NULL AND state <> ''
                  AND (LOWER(state) = LOWER(:exact) OR state LIKE :prefix)
                ORDER BY (LOWER(state) = LOWER(:exact_ord)) DESC, state LIMIT 1";
        $params = ['c' => $country, 'exact' => $likeExact, 'prefix' => $likePrefix, 'exact_ord' => $likeExact];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    if ($kind === 'area') {
        return array_filter([
            'area'  => (string) ($row['area'] ?? ''),
            'city'  => (string) ($row['city'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
        ], static fn ($v) => $v !== '');
    }
    if ($kind === 'city') {
        return array_filter([
            'city'  => (string) ($row['city'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
        ], static fn ($v) => $v !== '');
    }

    return ['state' => (string) ($row['state'] ?? '')];
}
