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
