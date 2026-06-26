<?php

declare(strict_types=1);

/**
 * Google Places helpers — legacy JSON API + Places API (New) fallback.
 *
 * REQUEST_DENIED on legacy usually means:
 * - Only "Places API (New)" is enabled, or
 * - API key is restricted to HTTP referrers (browser) but fetch_doctor runs server-side.
 */

function fd_places_denied_help(): string
{
    return implode("\n", [
        '',
        '⚠ Google API key rejected (REQUEST_DENIED / PERMISSION_DENIED). Check:',
        '  1. Google Cloud → APIs & Services → Library → enable BOTH:',
        '       • Places API (legacy)',
        '       • Places API (New)',
        '  2. Billing must be enabled on the project.',
        '  3. API key restrictions: use "IP addresses" (your server IP),',
        '     NOT "HTTP referrers" — fetch_doctor calls Google from PHP on the server.',
        '  4. Key lives in fetch_doctor/.env as GOOGLE_MAPS_API_KEY=...',
        '',
    ]);
}

function fd_places_status_denied(?string $status): bool
{
    if ($status === null || $status === '') {
        return false;
    }
    $s = strtoupper($status);

    return str_contains($s, 'DENIED') || str_contains($s, 'PERMISSION');
}

function fd_new_api_place_id(?string $resourceName): ?string
{
    if ($resourceName === null || $resourceName === '') {
        return null;
    }
    if (str_starts_with($resourceName, 'places/')) {
        return substr($resourceName, 7);
    }

    return $resourceName;
}

function fd_business_status_legacy(?string $raw): string
{
    $raw = strtoupper(str_replace('BUSINESS_STATUS_', '', (string) ($raw ?? 'OPERATIONAL')));

    return $raw !== '' ? $raw : 'OPERATIONAL';
}

/** POST JSON helper for Places API (New). */
function fd_http_post_json(string $url, array $body, array $headers, int &$reqCount): ?array
{
    $reqCount++;
    $ch = curl_init($url);
    $hdrs = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $hdrs,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'eClinicPro-Fetcher/1.0',
    ]);
    $bodyRaw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($bodyRaw === false || $code === 0) {
        $GLOBALS['fd_last_places_status'] = 'NETWORK_ERROR';

        return null;
    }

    $data = json_decode((string) $bodyRaw, true);
    if (!is_array($data)) {
        $GLOBALS['fd_last_places_status'] = 'INVALID_JSON';

        return null;
    }

    if ($code >= 400) {
        $errStatus = (string) ($data['error']['status'] ?? ('HTTP_' . $code));
        $GLOBALS['fd_last_places_status'] = $errStatus;

        return null;
    }

    $GLOBALS['fd_last_places_status'] = 'OK';

    return $data;
}

/**
 * Places API (New) — Text Search.
 *
 * @return list<array<string, mixed>>
 */
function fd_places_new_search_text(string $apiKey, string $query, float $lat, float $lng, int $radius, int &$reqCount): array
{
    $payload = [
        'textQuery' => $query,
        'regionCode' => 'IN',
        'languageCode' => 'en',
        'maxResultCount' => 5,
        'locationBias' => [
            'circle' => [
                'center' => ['latitude' => $lat, 'longitude' => $lng],
                'radius' => (float) min($radius, 50000),
            ],
        ],
    ];
    $fieldMask = implode(',', [
        'places.id',
        'places.displayName',
        'places.formattedAddress',
        'places.location',
        'places.types',
        'places.businessStatus',
        'places.rating',
        'places.userRatingCount',
        'places.nationalPhoneNumber',
        'places.internationalPhoneNumber',
        'places.websiteUri',
        'places.googleMapsUri',
        'places.regularOpeningHours',
        'places.photos',
        'places.reviews',
    ]);

    $data = fd_http_post_json(
        'https://places.googleapis.com/v1/places:searchText',
        $payload,
        [
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: ' . $fieldMask,
        ],
        $reqCount,
    );

    if (!is_array($data)) {
        return [];
    }

    return (array) ($data['places'] ?? []);
}

/**
 * Convert a Places API (New) place object to legacy text-search + details shapes.
 *
 * @return array{place: array<string, mixed>, details: array<string, mixed>}
 */
function fd_places_new_to_legacy_bundle(array $p): array
{
    $placeId = fd_new_api_place_id($p['id'] ?? null);
    $name = (string) ($p['displayName']['text'] ?? '');
    $lat = isset($p['location']['latitude']) ? (float) $p['location']['latitude'] : null;
    $lng = isset($p['location']['longitude']) ? (float) $p['location']['longitude'] : null;
    $biz = fd_business_status_legacy($p['businessStatus'] ?? null);

    $place = [
        'place_id' => $placeId,
        'name' => $name,
        'geometry' => ['location' => ['lat' => $lat, 'lng' => $lng]],
        'types' => array_values((array) ($p['types'] ?? [])),
        'business_status' => $biz,
        'rating' => isset($p['rating']) ? (float) $p['rating'] : null,
        'user_ratings_total' => isset($p['userRatingCount']) ? (int) $p['userRatingCount'] : 0,
    ];

    $hours = null;
    if (!empty($p['regularOpeningHours']['weekdayDescriptions'])) {
        $hours = ['weekday_text' => $p['regularOpeningHours']['weekdayDescriptions']];
    }

    $reviews = [];
    foreach ((array) ($p['reviews'] ?? []) as $r) {
        if (!empty($r['publishTime'])) {
            $ts = strtotime((string) $r['publishTime']);
            if ($ts) {
                $reviews[] = ['time' => $ts];
            }
        }
    }

    $photos = [];
    if (!empty($p['photos'][0]['name'])) {
        $photos[] = ['photo_reference' => (string) $p['photos'][0]['name']];
    }

    $details = [
        'name' => $name,
        'formatted_address' => $p['formattedAddress'] ?? null,
        'geometry' => ['location' => ['lat' => $lat, 'lng' => $lng]],
        'formatted_phone_number' => $p['nationalPhoneNumber'] ?? null,
        'international_phone_number' => $p['internationalPhoneNumber'] ?? null,
        'website' => $p['websiteUri'] ?? null,
        'url' => $p['googleMapsUri'] ?? null,
        'opening_hours' => $hours,
        'rating' => isset($p['rating']) ? (float) $p['rating'] : null,
        'user_ratings_total' => isset($p['userRatingCount']) ? (int) $p['userRatingCount'] : 0,
        'types' => array_values((array) ($p['types'] ?? [])),
        'business_status' => $biz,
        'photos' => $photos,
        'reviews' => $reviews,
    ];

    return ['place' => $place, 'details' => $details];
}

/**
 * @return array{place: array<string, mixed>, details: array<string, mixed>}|null
 */
function fd_resolve_place_by_name(string $apiKey, string $rawName, array $city, int &$reqCount, callable $legacyFind, callable $legacySearch): ?array
{
    $variants = fd_name_lookup_query_variants($rawName, $city);
    $legacyBlocked = false;
    $lastStatus = 'ZERO_RESULTS';

    foreach ($variants as $query) {
        if (!$legacyBlocked) {
            foreach ($legacyFind($apiKey, $query, $city['lat'], $city['lng'], 50000, $reqCount) as $candidate) {
                if (!empty($candidate['place_id'])) {
                    return ['place' => $candidate, 'details' => null];
                }
            }
            $lastStatus = (string) ($GLOBALS['fd_last_places_status'] ?? $lastStatus);
            if (fd_places_status_denied($lastStatus)) {
                $legacyBlocked = true;
            } else {
                foreach ($legacySearch($apiKey, $query, $city['lat'], $city['lng'], 50000, $reqCount) as $place) {
                    if (!empty($place['place_id'])) {
                        return ['place' => $place, 'details' => null];
                    }
                }
                $lastStatus = (string) ($GLOBALS['fd_last_places_status'] ?? $lastStatus);
                if (fd_places_status_denied($lastStatus)) {
                    $legacyBlocked = true;
                }
            }
        }

        foreach (fd_places_new_search_text($apiKey, $query, $city['lat'], $city['lng'], 50000, $reqCount) as $row) {
            $bundle = fd_places_new_to_legacy_bundle($row);
            if (!empty($bundle['place']['place_id'])) {
                return $bundle;
            }
        }
        $lastStatus = (string) ($GLOBALS['fd_last_places_status'] ?? $lastStatus);
        if (fd_places_status_denied($lastStatus)) {
            break;
        }
    }

    $GLOBALS['fd_last_places_status'] = $lastStatus;

    return null;
}

/** @return list<string> */
function fd_name_lookup_query_variants(string $rawName, array $city): array
{
    $cityName = (string) ($city['name'] ?? '');
    $base = trim($rawName);
    if ($base === '') {
        return [];
    }

    $variants = [];
    $add = static function (string $q) use (&$variants): void {
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
        if ($q !== '') {
            $variants[$q] = true;
        }
    };

    $withCity = (stripos($base, $cityName) !== false) ? $base : ($base . ' ' . $cityName);
    $add($withCity);
    if (str_contains($withCity, '&')) {
        $add(str_replace('&', 'and', $withCity));
    }

    if ($cityName !== '') {
        $noCity = trim(preg_replace('/\s+' . preg_quote($cityName, '/') . '\s*$/iu', '', $base));
        if ($noCity !== '' && strcasecmp($noCity, $base) !== 0) {
            $add($noCity . ' ' . $cityName);
            if (str_contains($noCity, '&')) {
                $add(str_replace('&', 'and', $noCity) . ' ' . $cityName);
            }
        }
        $add($withCity . ' Gujarat');
    }

    return array_keys($variants);
}
