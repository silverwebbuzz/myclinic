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
/**
 * Generic, specialty-appropriate treatment suggestions shown when a listing
 * has no doctor-entered services yet. These are clearly labelled as common
 * treatments for the specialty (not claims about this specific doctor).
 */
function ecp_directory_treatments_for_specialty(?string $specSlug): array
{
    $map = [
        'derma' => ['Acne treatment', 'Psoriasis care', 'Hair fall treatment', 'Laser hair removal', 'Chemical peel', 'Anti-ageing therapy'],
        'dental' => ['Root canal', 'Dental implants', 'Braces & aligners', 'Teeth whitening', 'Wisdom tooth extraction', 'Crowns & bridges'],
        'gp' => ['Fever & infections', 'Diabetes care', 'Hypertension', 'Thyroid disorders', 'General check-up', 'Vaccination'],
        'family_medicine' => ['Preventive check-ups', 'Chronic disease care', 'Vaccination', 'Lifestyle counselling', 'Minor illness care', 'Health screening'],
        'diabetology' => ['Diabetes management', 'Insulin therapy', 'Blood sugar monitoring', 'Diabetic foot care', 'Thyroid disorders', 'Diet & lifestyle counselling'],
        'endocrinology' => ['Diabetes management', 'Thyroid disorders', 'Hormonal imbalance', 'PCOS care', 'Obesity management', 'Osteoporosis care'],
        'cardio' => ['Heart health check-up', 'Hypertension management', 'ECG & echo', 'Cholesterol management', 'Heart failure care', 'Post-cardiac care'],
        'gastro' => ['Acidity & GERD', 'Liver care', 'IBS & gut health', 'Endoscopy', 'Constipation care', 'Pancreatitis management'],
        'gyno' => ['Pregnancy care', 'PCOS treatment', 'Menstrual disorders', 'Infertility care', 'Menopause care', 'Contraception advice'],
        'peds' => ['Child vaccination', 'Growth monitoring', 'Fever & infections', 'Nutrition guidance', 'Newborn care', 'Allergy management'],
        'ortho' => ['Joint pain treatment', 'Knee replacement', 'Fracture care', 'Sports injury rehab', 'Arthritis management', 'Spine care'],
        'neuro' => ['Headache & migraine', 'Epilepsy care', 'Stroke management', 'Vertigo treatment', 'Nerve pain care', 'Memory disorders'],
        'pulmonology' => ['Asthma care', 'COPD management', 'Allergy treatment', 'Sleep apnea care', 'TB treatment', 'Respiratory infections'],
        'eye' => ['Cataract surgery', 'Vision testing', 'LASIK consultation', 'Glaucoma care', 'Diabetic eye care', 'Dry eye treatment'],
        'ent' => ['Ear infection care', 'Sinusitis treatment', 'Tonsillitis care', 'Hearing assessment', 'Vertigo treatment', 'Snoring & sleep apnea'],
        'physio' => ['Back pain rehab', 'Sports injury rehab', 'Post-surgery rehab', 'Neck pain therapy', 'Stroke rehab', 'Posture correction'],
        'homeopathy' => ['Chronic ailment care', 'Allergy treatment', 'Skin disorders', 'Digestive issues', 'Migraine care', 'Immunity support'],
        'psychiatry' => ['Anxiety & depression', 'Stress management', 'Sleep disorders', 'Counselling', 'Addiction care', 'Mood disorders'],
    ];

    return ['items' => $map[$specSlug ?: 'gp'] ?? $map['gp']];
}

/**
 * Real, doctor-entered services for a directory listing, if any.
 * Stored as a JSON array in directory_doctors.services. Returns [] when
 * the column is missing/empty so the caller falls back to the generic list.
 *
 * @return list<string>
 */
function ecp_directory_services_from_row(array $row): array
{
    $raw = $row['services'] ?? null;
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $s) {
        $s = trim((string) $s);
        if ($s !== '') {
            $out[] = $s;
        }
    }

    return array_slice($out, 0, 24);
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

function ecp_profile_time_12h(string $hhmm): string
{
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $hhmm, $m)) {
        return '';
    }
    $h = (int) $m[1];
    $min = $m[2];
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }

    return $h12 . ':' . $min . ' ' . $ampm;
}

function ecp_profile_session_period(string $start): string
{
    $t = substr(trim($start), 0, 5);

    return ($t !== '' && $t < '13:00') ? 'Morning' : 'Evening';
}

/** @param list<array{start?: string, end?: string}> $sessions @return list<array{label: string, time: string}> */
function ecp_profile_labeled_sessions(array $sessions): array
{
    $out = [];
    foreach ($sessions as $session) {
        if (!is_array($session)) {
            continue;
        }
        $startRaw = (string) ($session['start'] ?? '');
        $start = ecp_profile_time_12h($startRaw);
        $end = ecp_profile_time_12h((string) ($session['end'] ?? ''));
        if ($start === '' || $end === '') {
            continue;
        }
        $out[] = [
            'label' => ecp_profile_session_period($startRaw),
            'time' => $start . ' - ' . $end,
        ];
    }

    return $out;
}

/** @param array<int, string>|array<string, mixed>|null $hours @return list<array{day: string, sessions: list<array{label: string, time: string}>, closed: bool}> */
function ecp_profile_hours_day_rows(?array $hours): array
{
    if (!$hours || (isset($hours[0]) && is_string($hours[0])) || isset($hours['weekday_text'])) {
        return [];
    }

    $dayOrder = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $dayLabel = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
    $byDay = [];

    foreach ($dayOrder as $day) {
        $cfg = $hours[$day] ?? null;
        if (!is_array($cfg)) {
            continue;
        }
        $enabled = !empty($cfg['enabled']);
        $sessions = is_array($cfg['sessions'] ?? null) ? $cfg['sessions'] : [];
        $labeled = ($enabled && $sessions !== []) ? ecp_profile_labeled_sessions($sessions) : [];
        $byDay[$day] = [
            'day' => $dayLabel[$day],
            'sessions' => $labeled,
            'closed' => $labeled === [],
        ];
    }

    if ($byDay === []) {
        return [];
    }

    $rows = [];
    $monSat = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $monSatKeys = array_values(array_filter($monSat, static fn (string $d): bool => array_key_exists($d, $byDay)));
    if (count($monSatKeys) === 6) {
        $fingerprints = array_map(
            static fn (string $d): string => json_encode($byDay[$d]['sessions']),
            $monSatKeys
        );
        if (count(array_unique($fingerprints)) === 1) {
            $first = $byDay['mon'];
            $rows[] = [
                'day' => 'Monday - Saturday',
                'sessions' => $first['sessions'],
                'closed' => $first['closed'],
            ];
        } else {
            foreach ($monSat as $day) {
                if (isset($byDay[$day])) {
                    $rows[] = $byDay[$day];
                }
            }
        }
    } else {
        foreach ($monSatKeys as $day) {
            $rows[] = $byDay[$day];
        }
    }

    if (isset($byDay['sun'])) {
        $rows[] = $byDay['sun'];
    }

    return $rows;
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

    // Portal specialty_configs.working_hours shape:
    // ['mon'=>['enabled'=>bool,'sessions'=>[['start'=>'09:00','end'=>'13:00'], ...]], ...]
    $dayOrder = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $dayLabel = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
    $sessionTextByDay = [];

    foreach ($dayOrder as $day) {
        $cfg = $hours[$day] ?? null;
        if (!is_array($cfg)) {
            continue;
        }

        $enabled = !empty($cfg['enabled']);
        $sessions = is_array($cfg['sessions'] ?? null) ? $cfg['sessions'] : [];
        if (!$enabled || $sessions === []) {
            $sessionTextByDay[$day] = 'Closed';
            continue;
        }

        $parts = [];
        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }
            $start = ecp_profile_time_12h((string) ($session['start'] ?? ''));
            $end = ecp_profile_time_12h((string) ($session['end'] ?? ''));
            if ($start === '' || $end === '') {
                continue;
            }
            $label = ecp_profile_session_period((string) ($session['start'] ?? ''));
            $parts[] = $label . ': ' . $start . ' - ' . $end;
        }

        $sessionTextByDay[$day] = $parts ? implode(', ', $parts) : 'Closed';
    }

    $lines = [];
    $monSat = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $monSatAvailable = array_values(array_filter(
        $monSat,
        static fn (string $d): bool => array_key_exists($d, $sessionTextByDay),
    ));
    if (count($monSatAvailable) === 6) {
        $uniqueMonSat = array_values(array_unique(array_map(
            static fn (string $d): string => $sessionTextByDay[$d],
            $monSatAvailable
        )));
        if (count($uniqueMonSat) === 1) {
            $lines[] = 'Monday - Saturday: ' . $uniqueMonSat[0];
        } else {
            foreach ($monSat as $day) {
                $lines[] = $dayLabel[$day] . ': ' . $sessionTextByDay[$day];
            }
        }
    } else {
        foreach ($monSatAvailable as $day) {
            $lines[] = $dayLabel[$day] . ': ' . $sessionTextByDay[$day];
        }
    }

    if (array_key_exists('sun', $sessionTextByDay)) {
        $lines[] = 'Sunday: ' . $sessionTextByDay['sun'];
    }

    return $lines;
}

/** @param array<int, string>|array<string, mixed>|null $hours @return array{compact: bool, day_range: string, sessions: list<array{label: string, time: string}>, rows: list<array{day: string, sessions: list<array{label: string, time: string}>, closed: bool}>} */
function ecp_profile_hours_hero_display(?array $hours): array
{
    $empty = ['compact' => true, 'day_range' => '', 'sessions' => [], 'rows' => []];
    $rows = ecp_profile_hours_day_rows($hours);
    if ($rows !== []) {
        $fingerprints = array_map(
            static fn (array $row): string => json_encode($row['sessions']),
            $rows
        );
        if (count(array_unique($fingerprints)) <= 1) {
            $first = $rows[0];

            return [
                'compact' => true,
                'day_range' => $first['day'],
                'sessions' => $first['sessions'],
                'rows' => [],
            ];
        }

        return ['compact' => false, 'day_range' => '', 'sessions' => [], 'rows' => $rows];
    }

    $hoursLines = ecp_profile_hours_lines($hours);
    if ($hoursLines === []) {
        return $empty;
    }

    $legacyRows = [];
    $timeParts = [];
    foreach ($hoursLines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $pos = strpos($line, ':');
        if ($pos === false) {
            $legacyRows[] = ['day' => '', 'sessions' => [], 'closed' => false];
            $timeParts[] = $line;
            continue;
        }
        $day = trim(substr($line, 0, $pos));
        $time = trim(substr($line, $pos + 1));
        $legacyRows[] = [
            'day' => $day,
            'sessions' => $time === 'Closed' ? [] : [['label' => '', 'time' => $time]],
            'closed' => $time === 'Closed',
        ];
        $timeParts[] = $time;
    }

    if ($legacyRows === []) {
        return $empty;
    }

    if (count(array_unique($timeParts)) <= 1) {
        $first = $legacyRows[0];

        return [
            'compact' => true,
            'day_range' => $first['day'],
            'sessions' => $first['sessions'],
            'rows' => [],
        ];
    }

    return ['compact' => false, 'day_range' => '', 'sessions' => [], 'rows' => $legacyRows];
}

/** @param array<int, string>|array<string, mixed>|null $hours @return list<array{day: string, morning: string, evening: string}> */
function ecp_profile_hours_table_rows(?array $hours): array
{
    $hoursDisplay = ecp_profile_hours_hero_display($hours);
    $out = [];

    if ($hoursDisplay['compact']) {
        $morning = '-';
        $evening = '-';
        foreach ((array) ($hoursDisplay['sessions'] ?? []) as $session) {
            $label = strtolower((string) ($session['label'] ?? ''));
            if ($label === 'morning') {
                $morning = (string) ($session['time'] ?? '-');
            } elseif ($label === 'evening') {
                $evening = (string) ($session['time'] ?? '-');
            }
        }
        $out[] = [
            'day' => (string) (($hoursDisplay['day_range'] ?? '') !== '' ? $hoursDisplay['day_range'] : 'Working days'),
            'morning' => $morning,
            'evening' => $evening,
        ];

        return $out;
    }

    foreach ((array) ($hoursDisplay['rows'] ?? []) as $hoursRow) {
        $morning = '-';
        $evening = '-';
        if (!empty($hoursRow['closed'])) {
            $morning = 'Closed';
            $evening = 'Closed';
        } else {
            foreach ((array) ($hoursRow['sessions'] ?? []) as $session) {
                $label = strtolower((string) ($session['label'] ?? ''));
                if ($label === 'morning') {
                    $morning = (string) ($session['time'] ?? '-');
                } elseif ($label === 'evening') {
                    $evening = (string) ($session['time'] ?? '-');
                }
            }
        }
        $out[] = [
            'day' => (string) ($hoursRow['day'] ?? ''),
            'morning' => $morning,
            'evening' => $evening,
        ];
    }

    return $out;
}

/** @return array<string, mixed>|null */
function ecp_profile_owner_working_hours(PDO $db, int $clinicId): ?array
{
    if ($clinicId <= 0) {
        return null;
    }

    // Prefer clinic owner. If absent, fall back to the first active doctor.
    $ownerStmt = $db->prepare(
        "SELECT id
           FROM users
          WHERE clinic_id = :cid
            AND is_active = 1
            AND is_owner = 1
          ORDER BY id ASC
          LIMIT 1"
    );
    $ownerStmt->execute(['cid' => $clinicId]);
    $doctorId = (int) ($ownerStmt->fetchColumn() ?: 0);

    if ($doctorId <= 0) {
        $docStmt = $db->prepare(
            "SELECT id
               FROM users
              WHERE clinic_id = :cid
                AND is_active = 1
                AND role = 'doctor'
              ORDER BY id ASC
              LIMIT 1"
        );
        $docStmt->execute(['cid' => $clinicId]);
        $doctorId = (int) ($docStmt->fetchColumn() ?: 0);
    }

    if ($doctorId <= 0) {
        return null;
    }

    $rowsStmt = $db->prepare(
        "SELECT day_of_week, start_time, end_time, extended_end_time
           FROM doctor_schedules
          WHERE clinic_id = :cid
            AND doctor_id = :did
            AND is_active = 1
          ORDER BY day_of_week ASC, start_time ASC"
    );
    $rowsStmt->execute(['cid' => $clinicId, 'did' => $doctorId]);
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return null;
    }

    $dayKeys = [0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat'];
    $hours = [];
    foreach ($dayKeys as $k) {
        $hours[$k] = ['enabled' => false, 'sessions' => []];
    }

    foreach ($rows as $r) {
        $dow = (int) ($r['day_of_week'] ?? -1);
        $day = $dayKeys[$dow] ?? null;
        if ($day === null) {
            continue;
        }
        $start = substr((string) ($r['start_time'] ?? ''), 0, 5);
        $end = substr((string) ($r['end_time'] ?? ''), 0, 5);
        if ($start === '' || $end === '') {
            continue;
        }

        $hours[$day]['enabled'] = true;
        $session = ['start' => $start, 'end' => $end];
        $extended = substr((string) ($r['extended_end_time'] ?? ''), 0, 5);
        if ($extended !== '') {
            $session['extended_end'] = $extended;
        }
        $hours[$day]['sessions'][] = $session;
    }

    return $hours;
}

/** @param array<string, mixed> $row @param array<string, mixed> $cityMeta */
function ecp_profile_build_payload(PDO $db, array $row, string $entityType, string $citySlug, array $cityMeta): array
{
    $entityType = ecp_directory_entity_type($row);
    $displayName = $entityType === 'doctor' ? ecp_directory_display_name($row) : ecp_directory_clinic_base_name($row);
    $specLabel = $row['specialty_label'] ?? ecp_specialty_label($row['specialty'] ?? null);
    $avatar = ecp_directory_avatar($row, 800);

    $hours = null;
    $claimedTenantId = (int) ($row['claimed_tenant_id'] ?? 0);
    if ($claimedTenantId > 0) {
        $hours = ecp_profile_owner_working_hours($db, $claimedTenantId);
    }
    if ($hours === null && !empty($row['tenant_hours'])) {
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
        // Claimed clinic: list the clinic's REAL doctor users from the portal
        // (users table) rather than guessing siblings from scraped Google rows.
        // A doctor who registered/claimed and added staff will see them here.
        $claimedTenantId = (int) ($row['claimed_tenant_id'] ?? 0);
        if ($claimedTenantId > 0) {
            $userStmt = $db->prepare(
                "SELECT id, name, specialization, qualification
                 FROM users
                 WHERE clinic_id = :cid AND is_active = 1
                   AND role IN ('doctor', 'admin')
                 ORDER BY is_owner DESC, name ASC
                 LIMIT 50"
            );
            $userStmt->execute(['cid' => $claimedTenantId]);
            foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $name = trim((string) ($u['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $avatar = ecp_directory_avatar(['doctor_name' => $name, 'id' => (int) ($u['id'] ?? 0)], 120);
                $doctors[] = [
                    'name' => $name,
                    'spec_label' => ecp_specialty_label($u['specialization'] ?? ($row['specialty'] ?? null)),
                    'rating' => 0,
                    'reviews' => 0,
                    'profile_url' => '#',
                    'photo_url' => $avatar['url'],
                    'avatar_initials' => $avatar['initials'],
                    'avatar_gradient' => $avatar['gradient'],
                ];
            }
        }
    }

    // Unclaimed clinic (or claimed but no portal doctors yet): fall back to the
    // scraped-directory heuristic — sibling directory_doctors rows in the same
    // city whose name matches this clinic's base name.
    if ($entityType === 'clinic' && $doctors === []) {
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

    // Doctor-entered services win; otherwise show generic specialty treatments.
    $customServices = ecp_directory_services_from_row($row);
    $treatments = $customServices !== []
        ? $customServices
        : ecp_directory_treatments_for_specialty($row['specialty'] ?? null)['items'];

    require_once __DIR__ . '/wordpress_blogs.php';
    $blogPosts = ecp_wordpress_posts_for_listing($db, $row, $entityType);

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
        'treatments' => $treatments,
        'treatments_custom' => $customServices !== [],
        'doctors' => $doctors,
        'related' => $related,
        'book_url' => ecp_directory_profile_url($row) . '#book',
        'meta_title' => $displayName . ' — ' . $specLabel . ' in ' . $cityName . ' | eClinicPro',
        'meta_description' => $displayName . ' — ' . $specLabel . ' in ' . $cityName . '. View timings, treatments and book online.',
        'blog_posts' => $blogPosts,
    ];
}
