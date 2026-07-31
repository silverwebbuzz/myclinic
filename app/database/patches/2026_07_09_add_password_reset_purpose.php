<?php
/**
 * Add `password_reset` to doctor_otp_codes.purpose ENUM values.
 *
 * Usage:
 *   php app/database/patches/2026_07_09_add_password_reset_purpose.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2)))->safeLoad();

use App\Core\Database;

$pdo = Database::connection();

$row = $pdo->query("SHOW COLUMNS FROM doctor_otp_codes LIKE 'purpose'")->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    throw new RuntimeException('Column doctor_otp_codes.purpose not found.');
}

$type = (string) ($row['Type'] ?? '');
if (!preg_match("/^enum\((.*)\)$/i", $type, $m)) {
    throw new RuntimeException("doctor_otp_codes.purpose is not ENUM. Current type: {$type}");
}

$rawValues = trim((string) $m[1]);
$values = [];
if ($rawValues !== '') {
    $parts = explode(',', $rawValues);
    foreach ($parts as $p) {
        $v = trim($p);
        $v = trim($v, "'");
        if ($v !== '') {
            $values[] = $v;
        }
    }
}

if (!in_array('password_reset', $values, true)) {
    $values[] = 'password_reset';
}

$values = array_values(array_unique($values));
$enumSql = implode(',', array_map(static fn(string $v): string => "'" . str_replace("'", "''", $v) . "'", $values));

$sql = "ALTER TABLE doctor_otp_codes MODIFY COLUMN purpose ENUM({$enumSql}) NOT NULL";
$pdo->exec($sql);

echo "Updated doctor_otp_codes.purpose ENUM => " . implode(', ', $values) . PHP_EOL;
