<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\ClinicTime;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

ClinicTime::bootstrap();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

$dbname = $_ENV['DB_DATABASE'] ?? 'manageclinic';
$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

ClinicTime::applyMysqlSession($pdo);

// install.sql is the single source of truth for the schema (full structure
// + reference data). There are no incremental migration files anymore.
//
// The reference data contains literal semicolons inside string values, so we
// cannot naively split on ';' in PHP — we pipe the file through the mysql
// client, which parses SQL correctly. (PDO is only used above to validate the
// connection settings before we shell out.)
$file = __DIR__ . '/install.sql';
if (!is_file($file)) {
    fwrite(STDERR, "install.sql not found at {$file}\n");
    exit(1);
}

echo "Loading install.sql via mysql client...\n";

$cmd = sprintf(
    'mysql --host=%s --port=%s --user=%s %s %s < %s',
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($user),
    $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
    escapeshellarg($dbname),
    escapeshellarg($file),
);

passthru($cmd, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "mysql import failed (exit {$exitCode}).\n");
    fwrite(STDERR, "Alternatively run: mysql -u USER -p DB < database/install.sql\n");
    exit($exitCode);
}

echo "Schema loaded.\n";
