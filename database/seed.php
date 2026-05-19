<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$pdo = Database::connection();

echo "Seeding module_catalog...\n";
require __DIR__ . '/seeds/modules.php';

echo "Seeding drugs...\n";
require __DIR__ . '/seeds/drugs.php';

echo "Seeding remedies...\n";
require __DIR__ . '/seeds/remedies.php';

echo "Seeding lab tests...\n";
require __DIR__ . '/seeds/lab_tests.php';

echo "Seeding demo clinic...\n";
require __DIR__ . '/seeds/demo_clinic.php';

echo "Seeding platform admin...\n";
require __DIR__ . '/seeds/platform_admin.php';

echo "Seed complete.\n";
