<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seo_slugs.php';

/** @return 'clinic'|'doctor' */
function ecp_directory_entity_type(array $row): string
{
    $stored = strtolower(trim((string) ($row['entity_type'] ?? '')));
    if ($stored === 'clinic' || $stored === 'doctor') {
        return $stored;
    }
    $name = strtolower((string) ($row['name'] ?? ''));
    foreach (['clinic', 'hospital', 'care', 'centre', 'center', 'polyclinic', 'nursing home', 'dispensary'] as $kw) {
        if (str_contains($name, $kw)) {
            return 'clinic';
        }
    }
    $types = json_decode((string) ($row['types'] ?? '[]'), true);
    if (is_array($types) && in_array('doctor', $types, true)) {
        return 'doctor';
    }

    return 'clinic';
}

function ecp_directory_clinic_base_name(array $row): string
{
    $base = preg_replace('/\s*[-–|]\s*Dr\.?\s.*$/i', '', (string) ($row['name'] ?? ''));
    if ($base === null || $base === '') {
        $base = (string) ($row['name'] ?? '');
    }

    return trim($base);
}

function ecp_directory_listing_slug(array $row, ?string $entityType = null): string
{
    $stored = strtolower(trim((string) ($row['listing_slug'] ?? '')));
    if ($stored !== '') {
        return $stored;
    }
    $entityType ??= ecp_directory_entity_type($row);
    if ($entityType === 'clinic') {
        $base = ecp_directory_clinic_base_name($row);
        $area = trim((string) ($row['area'] ?? ''));

        return ecp_slug($area !== '' ? $base . ' ' . $area : $base);
    }
    $doctorName = trim((string) ($row['doctor_name'] ?? ''));

    return ecp_slug($doctorName !== '' ? $doctorName : (string) ($row['name'] ?? ''));
}

function ecp_directory_profile_url(array $row): string
{
    $city = trim((string) ($row['city'] ?? ''));
    if ($city === '') {
        return '/find-a-doctor';
    }
    $entityType = ecp_directory_entity_type($row);

    return '/' . ecp_slug_for_city($city) . '/' . $entityType . '/' . ecp_directory_listing_slug($row, $entityType);
}

function ecp_profile_slug_columns_ready(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $ready = (bool) $db->query("SHOW COLUMNS FROM directory_doctors LIKE 'listing_slug'")->fetch();
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function ecp_profile_city_meta(PDO $db, string $citySlug): ?array
{
    static $cache = [];
    $key = strtolower(trim($citySlug));
    if ($key === '') {
        return null;
    }
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $guess = implode(' ', array_map(
        static fn (string $w): string => $w === '' ? '' : mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1),
        explode('-', $key)
    ));
    $stmt = $db->prepare(
        "SELECT city, state FROM directory_doctors
         WHERE is_active = 1 AND status = 'OPERATIONAL' AND city = :city
         GROUP BY city, state LIMIT 1"
    );
    $stmt->execute(['city' => $guess]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $cache[$key] = ['city' => $row['city'], 'state' => $row['state']];
    }

    return $cache[$key] = ecp_city_by_slug($key);
}

/** @return array<string, list<string>> */
function ecp_directory_treatments_for_specialty(?string $specSlug): array
{
    $map = [
        'derma' => ['Acne treatment', 'Psoriasis care', 'Hair fall treatment', 'Laser hair removal', 'Chemical peel', 'Anti-ageing therapy'],
        'dental' => ['Root canal', 'Dental implants', 'Braces & aligners', 'Teeth whitening', 'Wisdom tooth extraction', 'Crowns & bridges'],
        'gp' => ['Fever management', 'Diabetes care', 'Hypertension', 'Thyroid disorders', 'General check-up', 'Vaccination'],
        'ortho' => ['Joint pain treatment', 'Knee replacement', 'Fracture care', 'Sports injury rehab', 'Arthritis management', 'Spine care'],
        'physio' => ['Back pain rehab', 'Sports injury rehab', 'Post-surgery rehab', 'Neck pain therapy', 'Stroke rehab', 'Posture correction'],
        'homeopathy' => ['Chronic ailment care', 'Allergy treatment', 'Skin disorders', 'Digestive issues', 'Migraine care', 'Immunity support'],
    ];

    return ['items' => $map[$specSlug ?: 'gp'] ?? $map['gp']];
}

function ecp_directory_display_name(array $row): string
{
    $doctorName = trim((string) ($row['doctor_name'] ?? ''));

    return $doctorName !== '' ? $doctorName : (string) ($row['name'] ?? 'Clinic');
}

/** @return array<string, mixed>|null */
function ecp_profile_find_row(PDO $db, string $city, string $entityType, string $slug): ?array
{
    if (ecp_profile_slug_columns_ready($db)) {
        $stmt = $db->prepare(
            "SELECT dd.*, t.slug AS tenant_slug, t.logo_path, t.phone AS tenant_phone, t.address AS tenant_address,
                    sc.working_hours AS tenant_hours,
                    sm.label AS specialty_label, sm.url_slug AS specialty_url_slug
             FROM directory_doctors dd
             LEFT JOIN tenants t ON t.id = dd.claimed_tenant_id AND t.is_active = 1
             LEFT JOIN specialty_configs sc ON sc.clinic_id = t.id
             LEFT JOIN specialty_master sm ON sm.slug = dd.specialty AND sm.is_active = 1
             WHERE dd.is_active = 1 AND dd.status = 'OPERATIONAL'
               AND dd.city = :city AND dd.entity_type = :et AND dd.listing_slug = :slug
             LIMIT 1"
        );
        $stmt->execute(['city' => $city, 'et' => $entityType, 'slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    $stmt = $db->prepare(
        "SELECT dd.*, t.slug AS tenant_slug, t.logo_path, t.phone AS tenant_phone, t.address AS tenant_address,
                sc.working_hours AS tenant_hours,
                sm.label AS specialty_label, sm.url_slug AS specialty_url_slug
         FROM directory_doctors dd
         LEFT JOIN tenants t ON t.id = dd.claimed_tenant_id AND t.is_active = 1
         LEFT JOIN specialty_configs sc ON sc.clinic_id = t.id
         LEFT JOIN specialty_master sm ON sm.slug = dd.specialty AND sm.is_active = 1
         WHERE dd.is_active = 1 AND dd.status = 'OPERATIONAL' AND dd.city = :city"
    );
    $stmt->execute(['city' => $city]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $et = ecp_directory_entity_type($row);
        if ($et === $entityType && ecp_directory_listing_slug($row, $et) === $slug) {
            return $row;
        }
    }

    return null;
}

/** @return array<string, mixed>|null */
function ecp_profile_find(string $citySlug, string $entityType, string $slug): ?array
{
    $citySlug = strtolower(trim($citySlug));
    $entityType = strtolower(trim($entityType));
    $slug = strtolower(trim($slug));
    if ($citySlug === '' || $slug === '' || !in_array($entityType, ['clinic', 'doctor'], true)) {
        return null;
    }
    $db = ecp_db();
    if (!$db) {
        return null;
    }
    $cityMeta = ecp_profile_city_meta($db, $citySlug);
    if ($cityMeta === null) {
        return null;
    }
    $row = ecp_profile_find_row($db, $cityMeta['city'], $entityType, $slug);
    if ($row === null) {
        return null;
    }

    return ecp_profile_build_payload($db, $row, $entityType, $citySlug, $cityMeta);
}

/** @param array<int, string>|array<string, mixed>|null $hours */
function ecp_profile_hours_lines(?array $hours): array
{
    if (!$hours) {
        return [];
    }
    if (isset($hours[0]) && is_string($hours[0])) {
        return $hours;
    }
    if (isset($hours['weekday_text']) && is_array($hours['weekday_text'])) {
        return $hours['weekday_text'];
    }

    return [];
}

/** @param array<string, mixed> $row @param array<string, mixed> $cityMeta */
function ecp_profile_build_payload(PDO $db, array $row, string $entityType, string $citySlug, array $cityMeta): array
{
    $entityType = ecp_directory_entity_type($row);
    $displayName = $entityType === 'doctor' ? ecp_directory_display_name($row) : ecp_directory_clinic_base_name($row);
    $specLabel = $row['specialty_label'] ?? ecp_specialty_label($row['specialty'] ?? null);
    $avatar = ecp_directory_avatar($row, 800);

    $hours = null;
    if (!empty($row['tenant_hours'])) {
        $decoded = json_decode((string) $row['tenant_hours'], true);
        if (is_array($decoded)) {
            $hours = $decoded;
        }
    }
    if ($hours === null && !empty($row['opening_hours'])) {
        $decoded = json_decode((string) $row['opening_hours'], true);
        if (is_array($decoded)) {
            $hours = $decoded;
        }
    }

    $langs = ['English'];
    if (!empty($row['languages'])) {
        $decoded = json_decode((string) $row['languages'], true);
        if (is_array($decoded) && $decoded) {
            $langs = $decoded;
        }
    }

    $images = [];
    if ($avatar['url']) {
        $images[] = ['url' => $avatar['url'], 'alt' => $displayName, 'type' => 'photo'];
    }

    $phone = trim((string) ($row['phone'] ?? ''));
    if ($phone === '' && !empty($row['tenant_phone'])) {
        $phone = trim((string) $row['tenant_phone']);
    }
    $address = trim((string) ($row['address'] ?? ''));
    if ($address === '' && !empty($row['tenant_address'])) {
        $address = trim((string) $row['tenant_address']);
    }

    $fee = isset($row['consultation_fee']) && $row['consultation_fee'] !== null ? (float) $row['consultation_fee'] : 0;
    $currency = $row['consultation_fee_currency'] ?? ((($row['country'] ?? 'IN') === 'IN') ? 'INR' : 'USD');
    $currencySymbol = $currency === 'INR' ? '₹' : ($currency === 'USD' ? '$' : $currency . ' ');

    $directionsUrl = trim((string) ($row['gmaps_url'] ?? ''));
    if ($directionsUrl === '' && !empty($row['lat']) && !empty($row['lng'])) {
        $directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode((string) $row['lat'] . ',' . (string) $row['lng']);
    }

    $relatedStmt = $db->prepare(
        "SELECT dd.id, dd.name, dd.doctor_name, dd.city, dd.area, dd.rating, dd.reviews, dd.photo_reference, dd.types, dd.is_claimed
         FROM directory_doctors dd
         WHERE dd.is_active = 1 AND dd.status = 'OPERATIONAL'
           AND dd.city = :city AND dd.specialty = :spec AND dd.id <> :id
         ORDER BY dd.is_claimed DESC, dd.rating DESC LIMIT 6"
    );
    $relatedStmt->execute([
        'city' => (string) ($row['city'] ?? ''),
        'spec' => (string) ($row['specialty'] ?? 'gp'),
        'id' => (int) ($row['id'] ?? 0),
    ]);
    $related = [];
    foreach ($relatedStmt->fetchAll(PDO::FETCH_ASSOC) as $rel) {
        $relAvatar = ecp_directory_avatar($rel, 200);
        $related[] = [
            'name' => ecp_directory_display_name($rel),
            'area' => $rel['area'] ?? '',
            'rating' => isset($rel['rating']) ? (float) $rel['rating'] : 0,
            'profile_url' => ecp_directory_profile_url($rel),
            'photo_url' => $relAvatar['url'],
        ];
    }

    $doctors = [];
    if ($entityType === 'clinic') {
        $base = ecp_directory_clinic_base_name($row);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $base) . '%';
        $docStmt = $db->prepare(
            "SELECT dd.id, dd.name, dd.doctor_name, dd.specialty, dd.rating, dd.reviews, dd.photo_reference, dd.types, dd.city, dd.area
             FROM directory_doctors dd
             WHERE dd.is_active = 1 AND dd.status = 'OPERATIONAL'
               AND dd.city = :city AND dd.id <> :id AND dd.name LIKE :like ESCAPE '\\\\'
             ORDER BY dd.doctor_name ASC LIMIT 20"
        );
        $docStmt->execute(['city' => (string) ($row['city'] ?? ''), 'id' => (int) ($row['id'] ?? 0), 'like' => $like]);
        foreach ($docStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
            if (ecp_directory_entity_type($doc) !== 'doctor' && trim((string) ($doc['doctor_name'] ?? '')) === '') {
                continue;
            }
            $docAvatar = ecp_directory_avatar($doc, 120);
            $doctors[] = [
                'name' => ecp_directory_display_name($doc),
                'spec_label' => ecp_specialty_label($doc['specialty'] ?? null),
                'rating' => isset($doc['rating']) ? (float) $doc['rating'] : 0,
                'reviews' => (int) ($doc['reviews'] ?? 0),
                'profile_url' => ecp_directory_profile_url($doc),
                'photo_url' => $docAvatar['url'],
                'avatar_initials' => $docAvatar['initials'],
                'avatar_gradient' => $docAvatar['gradient'],
            ];
        }
    }

    $cityName = (string) ($row['city'] ?? $cityMeta['city']);
    $isClaimed = (bool) ($row['is_claimed'] ?? false);
    $rating = isset($row['rating']) ? (float) $row['rating'] : 0;
    $reviews = (int) ($row['reviews'] ?? 0);

    return [
        'id' => (int) $row['id'],
        'entity_type' => $entityType,
        'slug' => ecp_directory_listing_slug($row, $entityType),
        'city_slug' => $citySlug,
        'canonical' => ecp_directory_profile_url($row),
        'display_name' => $displayName,
        'clinic_name' => ecp_directory_clinic_base_name($row),
        'doctor_name' => trim((string) ($row['doctor_name'] ?? '')),
        'specialty' => $row['specialty'] ?? 'gp',
        'specialty_label' => $specLabel,
        'specialty_url_slug' => $row['specialty_url_slug'] ?? ecp_slug_for_db_specialty($row['specialty'] ?? null),
        'city' => $cityName,
        'state' => (string) ($row['state'] ?? ($cityMeta['state'] ?? '')),
        'area' => (string) ($row['area'] ?? ''),
        'address' => $address,
        'phone' => $phone !== '' ? $phone : null,
        'website' => trim((string) ($row['website'] ?? '')) ?: null,
        'gmaps_url' => trim((string) ($row['gmaps_url'] ?? '')) ?: null,
        'directions_url' => $directionsUrl !== '' ? $directionsUrl : null,
        'rating' => $rating,
        'reviews' => $reviews,
        'is_claimed' => $isClaimed,
        'tenant_slug' => !empty($row['tenant_slug']) ? (string) $row['tenant_slug'] : null,
        'bio' => trim((string) ($row['bio'] ?? '')),
        'languages' => $langs,
        'opening_hours' => $hours,
        'fee' => $fee,
        'currency' => $currencySymbol,
        'images' => $images,
        'photo_url' => $avatar['url'],
        'avatar_initials' => $avatar['initials'],
        'avatar_gradient' => $avatar['gradient'],
        'treatments' => ecp_directory_treatments_for_specialty($row['specialty'] ?? null)['items'],
        'doctors' => $doctors,
        'related' => $related,
        'book_url' => ecp_directory_profile_url($row) . '#book',
        'meta_title' => $displayName . ' — ' . $specLabel . ' in ' . $cityName . ' | eClinicPro',
        'meta_description' => $displayName . ' — ' . $specLabel . ' in ' . $cityName . '. View timings, treatments and book online.',
    ];
}
