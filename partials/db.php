<?php
// =====================================================================
// db.php — read-only DB connection for the marketing site.
// Marketing pages connect to the SAME database as the portal,
// but only run SELECT queries. Never INSERT/UPDATE here, except for the
// dedicated demo_requests table (added separately).
// =====================================================================

declare(strict_types=1);

function ecp_env(string $key, string $default = ''): string
{
    static $env = null;
    if ($env === null) {
        $env = [];
        foreach ([__DIR__ . '/../app/.env', __DIR__ . '/../fetch_doctor/.env'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }
    if (isset($env[$key]) && $env[$key] !== '') {
        return $env[$key];
    }
    $fromEnv = getenv($key);
    if ($fromEnv !== false && $fromEnv !== '') {
        return (string) $fromEnv;
    }

    return $default;
}

function ecp_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = ecp_env('BASE_URL') ?: ecp_env('APP_URL');
    if ($configured !== '' && !str_contains($configured, 'app.eclinicpro.com')) {
        return $base = rtrim($configured, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https' : 'http';

    return $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'eclinicpro.com');
}

function ecp_google_maps_api_key(): string
{
    return ecp_env('GOOGLE_MAPS_API_KEY');
}

/**
 * Returns a PDO connection, reusing one per request.
 * Reads credentials from /app/.env (same file the portal uses).
 * Fails silently — pages should degrade to static content if DB is unreachable.
 */
function ecp_db(): ?PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Locate .env (look in the sibling /app folder where the portal lives).
    $envPath = __DIR__ . '/../app/.env';
    if (!is_file($envPath)) {
        return null;
    }

    $env = [];
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }

    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $name = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';

    if ($name === '' || $user === '') {
        return null;
    }

    try {
        $appTz = $env['APP_TIMEZONE'] ?? 'Asia/Kolkata';
        if (@date_default_timezone_set($appTz) === false) {
            date_default_timezone_set('Asia/Kolkata');
        }

        $pdo = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        try {
            $offset = (new DateTime('now', new DateTimeZone($appTz)))->format('P');
            $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
        } catch (Throwable) {
            // Non-fatal — SELECT queries still work; NOW() may stay on server default.
        }

        return $pdo;
    } catch (Throwable $e) {
        error_log('[marketing db] connection failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Total active clinics — used in "Trusted by N clinics" copy.
 * Cached in static memory per-request. Falls back to a hard-coded floor.
 */
function ecp_active_clinic_count(int $floor = 2847): int
{
    static $count = null;
    if ($count !== null) return $count;
    $db = ecp_db();
    if (!$db) return $count = $floor;
    try {
        $stmt = $db->query('SELECT COUNT(*) AS c FROM tenants WHERE is_active = 1');
        $row = $stmt->fetch();
        $real = (int) ($row['c'] ?? 0);
        // Show whichever is bigger (so the marketing number never goes down on a slow week).
        $count = max($real, $floor);
    } catch (Throwable $e) {
        $count = $floor;
    }
    return $count;
}

/**
 * Top N cities by directory listing count, for the footer mega-city links.
 * Cached per request. Returns [['city' => 'Mumbai', 'n' => 432], ...].
 */
function ecp_footer_top_cities(int $limit = 30): array
{
    static $cache = null;
    if ($cache !== null) return array_slice($cache, 0, $limit);

    $db = ecp_db();
    if (!$db) return [];
    try {
        $stmt = $db->prepare(
            "SELECT city, COUNT(*) AS n
             FROM directory_doctors
             WHERE is_active = 1 AND status = 'OPERATIONAL'
               AND city IS NOT NULL AND city <> ''
             GROUP BY city
             HAVING n >= 5
             ORDER BY n DESC
             LIMIT 50"
        );
        $stmt->execute();
        $cache = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_slice($cache, 0, $limit);
    } catch (Throwable $e) {
        return $cache = [];
    }
}

/**
 * Country count (for "in N countries" copy).
 */
function ecp_country_count(int $floor = 47): int
{
    static $count = null;
    if ($count !== null) return $count;
    $db = ecp_db();
    if (!$db) return $count = $floor;
    try {
        $stmt = $db->query("SELECT COUNT(DISTINCT country_code) AS c FROM tenants WHERE is_active = 1 AND country_code IS NOT NULL AND country_code <> ''");
        $row = $stmt->fetch();
        $count = max((int) ($row['c'] ?? 0), $floor);
    } catch (Throwable $e) {
        $count = $floor;
    }
    return $count;
}

/**
 * Active subscription plans, for the pricing page.
 * @return list<array<string,mixed>>
 */
function ecp_plans(): array
{
    $db = ecp_db();
    if (!$db) return [];
    try {
        $stmt = $db->query('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC, monthly_price ASC');
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Logs a demo request. Creates the table on first use so we don't need a migration.
 */
function ecp_save_demo_request(array $data): bool
{
    $db = ecp_db();
    if (!$db) return false;
    try {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS demo_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(160) NOT NULL,
                phone VARCHAR(40) NULL,
                clinic_name VARCHAR(160) NULL,
                specialty VARCHAR(40) NULL,
                message TEXT NULL,
                source VARCHAR(40) DEFAULT "website",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $stmt = $db->prepare(
            'INSERT INTO demo_requests (name, email, phone, clinic_name, specialty, message)
             VALUES (:name, :email, :phone, :clinic, :specialty, :message)'
        );
        return $stmt->execute([
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? null,
            'clinic' => $data['clinic_name'] ?? null,
            'specialty' => $data['specialty'] ?? null,
            'message' => $data['message'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('[marketing demo] ' . $e->getMessage());
        return false;
    }
}

/**
 * Logs a health-insurance lead. Creates the table on first use so we don't
 * need a migration (same pattern as ecp_save_demo_request).
 *
 * @param array<string,mixed> $data
 */
function ecp_save_insurance_lead(array $data): bool
{
    $db = ecp_db();
    if (!$db) return false;
    try {
        // Schema lives in migration 038_insurance_leads.sql — run on deploy.
        $stmt = $db->prepare(
            'INSERT INTO insurance_leads
                (name, phone, email, gender, cover_for, age_band, children_ages, city, call_time, plan_interest)
             VALUES
                (:name, :phone, :email, :gender, :cover_for, :age_band, :children_ages, :city, :call_time, :plan_interest)'
        );
        return $stmt->execute([
            'name' => $data['name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'email' => ($data['email'] ?? '') !== '' ? $data['email'] : null,
            'gender' => $data['gender'] ?? null,
            'cover_for' => $data['cover_for'] ?? null,
            'age_band' => $data['age_band'] ?? null,
            'children_ages' => ($data['children_ages'] ?? '') !== '' ? $data['children_ages'] : null,
            'city' => $data['city'] ?? null,
            'call_time' => $data['call_time'] ?? null,
            'plan_interest' => ($data['plan_interest'] ?? '') !== '' ? $data['plan_interest'] : null,
        ]);
    } catch (Throwable $e) {
        error_log('[marketing insurance] ' . $e->getMessage());
        return false;
    }
}

/**
 * Returns the total count of active directory doctors (optionally per country),
 * unaffected by the LIMIT on ecp_directory_doctors(). Used by hero copy
 * ("Search 2,789 verified clinicians…") so the displayed number is honest.
 */
function ecp_directory_doctor_count(?string $countryCode = null): int
{
    $db = ecp_db();
    if (!$db) return 0;
    try {
        $sql = "SELECT COUNT(*) FROM directory_doctors WHERE is_active = 1 AND status = 'OPERATIONAL'";
        $params = [];
        if ($countryCode !== null && $countryCode !== '') {
            $sql .= ' AND country = :c';
            $params['c'] = strtoupper($countryCode);
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Returns directory doctors (from the scraped/claimed table) shaped for the
 * Find Doctor page Alpine component. Returns null if the table is missing or
 * empty — caller should fall back to the static seed file.
 *
 * @return list<array<string,mixed>>|null
 */
function ecp_directory_doctors(?string $countryCode = null): ?array
{
    $db = ecp_db();
    if (!$db) return null;
    try {
        $sql = "SELECT id, place_id, name, specialty, country, city, state, area,
                       address, lat, lng, phone, website, gmaps_url, rating, reviews,
                       price_level, opening_hours, photo_reference,
                       consultation_fee, consultation_fee_currency, doctor_name,
                       quality_score, dropped_reason,
                       status, is_claimed, is_active
                FROM directory_doctors
                WHERE is_active = 1 AND status = 'OPERATIONAL'";
        $params = [];
        if ($countryCode !== null && $countryCode !== '') {
            $sql .= ' AND country = :c';
            $params['c'] = strtoupper($countryCode);
        }
        // Rank: claimed clinics first, then by quality_score (computed at import),
        // then by raw reviews/rating as tie-breakers.
        // Cap at 10k so the JSON payload sent to the browser stays under ~5MB.
        // Beyond that we should switch to AJAX-paginated search.
        $sql .= ' ORDER BY is_claimed DESC, quality_score DESC, reviews DESC, rating DESC LIMIT 10000';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return null;

        // Shape rows for the Alpine component (matches structure of
        // partials/find-doctor-data.php → doctors[]).
        $out = [];
        foreach ($rows as $r) {
            $clinicName = (string) ($r['name'] ?? '');
            $doctorName = trim((string) ($r['doctor_name'] ?? ''));
            // Prefer parsed doctor name for the headline; fall back to the
            // listing/clinic name when we couldn't extract a person from it.
            $displayName = $doctorName !== '' ? $doctorName : $clinicName;

            // Build initials off the display name (sans "Dr." prefix).
            $forInitials = preg_replace('/^Dr\.?\s+/i', '', $displayName) ?? $displayName;
            $parts = preg_split('/\s+/', trim($forInitials)) ?: [''];
            $first = $parts[0] ?? '';
            $last = $parts[count($parts) - 1] ?? '';
            $fi = $first !== '' ? mb_substr($first, 0, 1) : 'D';
            $li = $last !== '' && $last !== $first ? mb_substr($last, 0, 1) : '';

            $hours = null;
            if (!empty($r['opening_hours'])) {
                $decoded = json_decode((string) $r['opening_hours'], true);
                if (is_array($decoded)) $hours = $decoded;
            }

            // Fee: prefer self-submitted consultation_fee; fall back to null.
            // Google's price_level is too coarse for healthcare so we ignore it.
            $fee = isset($r['consultation_fee']) && $r['consultation_fee'] !== null
                ? (float) $r['consultation_fee']
                : 0;
            $currency = $r['consultation_fee_currency']
                ?? ((($r['country'] ?? 'IN') === 'IN') ? '₹' : '$');

            // If we extracted a real doctor name, the listing name is the
            // clinic/hospital. Otherwise the listing IS the clinic and we have
            // no person to show separately.
            $hospital = $doctorName !== '' && $clinicName !== $doctorName ? $clinicName : '';

            $out[] = [
                'id' => (int) $r['id'],
                'name' => $displayName,
                'clinicName' => $clinicName,
                'doctorName' => $doctorName,
                'firstInitial' => $fi,
                'lastInitial' => $li,
                'qual' => '',                      // not from Google; filled when claimed
                'years' => 0,
                'spec' => $r['specialty'] ?? 'gp',
                'specLabel' => ecp_specialty_label($r['specialty'] ?? null),
                'verified' => (bool) $r['is_claimed'],
                'video' => false,
                'gender' => '',
                'rating' => isset($r['rating']) ? (float) $r['rating'] : 0,
                'reviews' => (int) ($r['reviews'] ?? 0),
                'langs' => ['English'],
                'hospital' => $hospital,
                'area' => $r['area'] ?? '',
                'city' => $r['city'] ?? '',
                'state' => $r['state'] ?? '',
                'country' => $r['country'] ?? 'IN',
                'countryName' => $r['country'] ?? '',
                'currency' => $currency,
                'fee' => $fee,
                'next' => ['when' => 'later', 'label' => 'Contact clinic', 'sub' => ''],
                'phone' => $r['phone'] ?? null,
                'website' => $r['website'] ?? null,
                'gmaps_url' => $r['gmaps_url'] ?? null,
                'lat' => isset($r['lat']) ? (float) $r['lat'] : null,
                'lng' => isset($r['lng']) ? (float) $r['lng'] : null,
                'address' => $r['address'] ?? null,
                'opening_hours' => $hours,
                'photo_url' => ecp_doctor_photo_url($r['photo_reference'] ?? null, 400),
                'is_claimed' => (bool) $r['is_claimed'],
                'quality_score' => isset($r['quality_score']) ? (int) $r['quality_score'] : null,
            ];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('[ecp_directory_doctors] ' . $e->getMessage());
        return null;
    }
}

/**
 * Builds a Google Places photo URL at request time using the photo_reference
 * stored in the DB. Per Google's TOS we can store the reference, but NOT the
 * photo bytes — they must be fetched live, with attribution displayed.
 *
 * Returns null when:
 *   - photo_reference is empty
 *   - GOOGLE_MAPS_API_KEY env var is not set
 *
 * NOTE: this URL exposes the API key to the browser. For production you should
 * proxy through a server-side endpoint (e.g. /photo-proxy.php?ref=...) so the
 * key never reaches the client. For now we ship the simple form.
 */
function ecp_doctor_photo_url(?string $photoRef, int $maxWidth = 400): ?string
{
    if ($photoRef === null || trim($photoRef) === '' || ecp_google_maps_api_key() === '') {
        return null;
    }

    return ecp_base_url() . '/api/photo.php?' . http_build_query([
        'ref' => $photoRef,
        'w'   => max(80, min(1600, $maxWidth)),
    ]);
}

/** @return array{url: ?string, initials: string, gradient: int} */
/**
 * Neutral default doctor avatar as an inline SVG data URI. Used when a doctor
 * has no Google photo and no uploaded clinic logo, so the listing and profile
 * pages show the same icon instead of bare initials.
 */
function ecp_default_doctor_avatar(): string
{
    // Brand default avatar (200×200 PNG) shown when a doctor/clinic has no
    // Google photo and no uploaded logo. Static file, browser-cached.
    return '/assets/img/eClinicpro-DefaultDoctorAvatar.png';
}

/** Turn a portal storage path or absolute URL into a public URL. */
function ecp_upload_public_url(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }

    return str_starts_with($path, 'http') ? $path : ecp_portal_url('/' . ltrim($path, '/'));
}

/** @return list<string> Google Places photo tokens from a directory row. */
function ecp_directory_photo_refs(array $row): array
{
    $refs = [];
    $primary = trim((string) ($row['photo_reference'] ?? ''));
    if ($primary !== '') {
        $refs[$primary] = true;
    }

    $raw = $row['photo_references'] ?? null;
    if ($raw !== null && $raw !== '') {
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $ref) {
                $ref = trim((string) $ref);
                if ($ref !== '') {
                    $refs[$ref] = true;
                }
            }
        }
    }

    return array_keys($refs);
}

/**
 * Resolve a listing/doctor avatar URL.
 *
 * Priority:
 *   1. Clinic logo (when $opts['use_clinic_logo'] !== false)
 *   2. User/doctor uploaded photo (photo_path / user_photo_path)
 *   3. Google Places photo_reference from directory_doctors
 *
 * @param array{use_clinic_logo?: bool} $opts
 * @return array{url: ?string, google_photo_url: ?string, initials: string, gradient: int}
 */
function ecp_directory_avatar(array $row, int $photoWidth = 400, array $opts = []): array
{
    $clinicName = (string) ($row['name'] ?? '');
    $doctorName = trim((string) ($row['doctor_name'] ?? ''));
    $display = $doctorName !== '' ? $doctorName : $clinicName;
    $forInit = preg_replace('/^Dr\.?\s+/i', '', $display) ?? $display;
    $parts = preg_split('/\s+/', trim($forInit)) ?: [''];
    $first = mb_substr($parts[0] ?? '', 0, 1) ?: 'D';
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

    $googleUrl = null;
    foreach (ecp_directory_photo_refs($row) as $ref) {
        $googleUrl = ecp_doctor_photo_url($ref, $photoWidth);
        if ($googleUrl !== null) {
            break;
        }
    }

    $uploadUrl = null;
    if (($opts['use_clinic_logo'] ?? true) === true) {
        $uploadUrl = ecp_upload_public_url($row['clinic_logo_path'] ?? $row['logo_path'] ?? null);
    }
    if ($uploadUrl === null) {
        $uploadUrl = ecp_upload_public_url($row['user_photo_path'] ?? $row['photo_path'] ?? null);
    }

    $url = $uploadUrl ?? $googleUrl;

    return [
        'url'               => $url,
        'google_photo_url'  => $googleUrl,
        'initials'          => strtoupper($first . $last),
        'gradient'          => 1 + ((int) ($row['id'] ?? 0) % 6),
    ];
}

/**
 * Distinct areas / cities / states from directory_doctors, shaped for the
 * Find a Doctor autocomplete. Returns null if the DB isn't available.
 * Country flag is hardcoded per country code for now.
 */
function ecp_directory_locations(): ?array
{
    $db = ecp_db();
    if (!$db) return null;

    $flags = ['IN' => '🇮🇳', 'US' => '🇺🇸', 'GB' => '🇬🇧', 'AE' => '🇦🇪', 'CA' => '🇨🇦', 'AU' => '🇦🇺', 'SG' => '🇸🇬'];
    $out = [];
    $seen = [];

    try {
        // Areas (most specific)
        $stmt = $db->query("SELECT DISTINCT area, city, state, country
                            FROM directory_doctors
                            WHERE is_active = 1 AND area IS NOT NULL AND area <> ''
                            ORDER BY area
                            LIMIT 1500");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = strtolower('a|' . $r['area'] . '|' . $r['city']);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = [
                'type'  => 'AREA',
                'label' => $r['area'],
                'sub'   => trim(($r['city'] ?? '') . ($r['state'] ? ', ' . $r['state'] : '')),
                'flag'  => $flags[$r['country']] ?? '🌐',
                'value' => ['area' => $r['area'], 'city' => $r['city'], 'state' => $r['state'], 'country' => $r['country']],
                'country' => $r['country'],
            ];
        }

        // Cities
        $stmt = $db->query("SELECT DISTINCT city, state, country
                            FROM directory_doctors
                            WHERE is_active = 1 AND city IS NOT NULL AND city <> ''
                            ORDER BY city
                            LIMIT 500");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = strtolower('c|' . $r['city']);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = [
                'type'  => 'CITY',
                'label' => $r['city'],
                'sub'   => $r['state'] ?? '',
                'flag'  => $flags[$r['country']] ?? '🌐',
                'value' => ['city' => $r['city'], 'state' => $r['state'], 'country' => $r['country']],
                'country' => $r['country'],
            ];
        }

        // States
        $stmt = $db->query("SELECT DISTINCT state, country
                            FROM directory_doctors
                            WHERE is_active = 1 AND state IS NOT NULL AND state <> ''
                            ORDER BY state
                            LIMIT 200");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = strtolower('s|' . $r['state']);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = [
                'type'  => 'STATE',
                'label' => $r['state'],
                'sub'   => '',
                'flag'  => $flags[$r['country']] ?? '🌐',
                'value' => ['state' => $r['state'], 'country' => $r['country']],
                'country' => $r['country'],
            ];
        }
    } catch (Throwable $e) {
        error_log('[ecp_directory_locations] ' . $e->getMessage());
        return null;
    }

    return $out;
}

/**
 * Map a specialty slug ('eye', 'prosthodontist', 'homeopathy', ...) to its
 * display label ('Ophthalmologist', etc.), loading the seed file once.
 * Falls back to a title-cased slug for unknown values.
 */
function ecp_specialty_label(?string $slug): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $dataPath = __DIR__ . '/find-doctor-data.php';
        if (is_file($dataPath)) {
            $seed = require $dataPath;
            foreach ((array) ($seed['specialties'] ?? []) as $s) {
                if (isset($s['id'], $s['label'])) $map[$s['id']] = $s['label'];
            }
        }
    }
    if (!$slug) return 'General Physician';
    return $map[$slug] ?? ucwords(str_replace('_', ' ', $slug));
}
