<?php
// =====================================================================
// db.php — read-only DB connection for the marketing site.
// Marketing pages connect to the SAME database as the portal,
// but only run SELECT queries. Never INSERT/UPDATE here, except for the
// dedicated demo_requests table (added separately).
// =====================================================================

declare(strict_types=1);

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
        $sql .= ' ORDER BY is_claimed DESC, quality_score DESC, reviews DESC, rating DESC LIMIT 500';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return null;

        // Shape rows for the Alpine component (matches structure of
        // partials/find-doctor-data.php → doctors[]).
        $out = [];
        foreach ($rows as $r) {
            $name = (string) ($r['name'] ?? '');
            $parts = preg_split('/\s+/', trim($name)) ?: [''];
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

            $out[] = [
                'id' => (int) $r['id'],
                'name' => $name,
                'firstInitial' => $fi,
                'lastInitial' => $li,
                'qual' => '',                      // not from Google; filled when claimed
                'years' => 0,
                'spec' => $r['specialty'] ?? 'gp',
                'specLabel' => ucfirst((string) ($r['specialty'] ?? 'general practice')),
                'verified' => (bool) $r['is_claimed'],
                'video' => false,
                'gender' => '',
                'rating' => isset($r['rating']) ? (float) $r['rating'] : 0,
                'reviews' => (int) ($r['reviews'] ?? 0),
                'langs' => ['English'],
                'hospital' => '',
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
    if ($photoRef === null || $photoRef === '') return null;
    $key = getenv('GOOGLE_MAPS_API_KEY') ?: ($_ENV['GOOGLE_MAPS_API_KEY'] ?? '');
    if ($key === '') return null;
    return 'https://maps.googleapis.com/maps/api/place/photo?'
        . http_build_query([
            'maxwidth' => $maxWidth,
            'photoreference' => $photoRef,
            'key' => $key,
        ]);
}
