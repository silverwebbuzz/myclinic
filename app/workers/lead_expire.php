<?php

declare(strict_types=1);

// Cron: hourly — php workers/lead_expire.php
// Marks unconfirmed directory leads past their slot as no-response.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\LeadFlowService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$n = LeadFlowService::expireStale();
echo "Leads expired: {$n}\n";
