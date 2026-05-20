<?php

declare(strict_types=1);

// Cron: nightly 2 AM — php workers/analytics_snapshot.php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\AnalyticsSnapshotService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$date = $argv[1] ?? date('Y-m-d', strtotime('-1 day'));
$count = AnalyticsSnapshotService::buildAll($date);
echo "Analytics snapshots built for {$count} clinics (date: {$date})\n";
