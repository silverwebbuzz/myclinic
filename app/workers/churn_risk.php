<?php

declare(strict_types=1);

// Cron: daily — php workers/churn_risk.php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\ChurnOutreachService;
use App\Services\ChurnRiskService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$flagged = ChurnRiskService::run();
$sent = ChurnOutreachService::sendOutreach();
echo "Churn risk: {$flagged} clinics flagged, {$sent} outreach emails queued.\n";
