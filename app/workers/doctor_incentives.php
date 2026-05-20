<?php

declare(strict_types=1);

// Cron: 1st of month — php workers/doctor_incentives.php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\DoctorIncentiveService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$period = $argv[1] ?? date('Y-m', strtotime('first day of last month'));
$count = DoctorIncentiveService::calculateAllClinics($period);
echo "Doctor incentives calculated for {$count} doctor records (period: {$period})\n";
