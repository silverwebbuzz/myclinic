<?php

declare(strict_types=1);

// Cron: nightly — php workers/directory_sync.php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\DirectorySyncService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$count = DirectorySyncService::syncAll();
echo "Directory sync: {$count} doctor profiles updated.\n";
