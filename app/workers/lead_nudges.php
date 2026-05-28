<?php

declare(strict_types=1);

// Cron: every 15 min — php workers/lead_nudges.php
// Soft-nudge + appointment reminders for directory booking leads.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\LeadFlowService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$queued = LeadFlowService::runNudges();
echo "Lead nudges queued: {$queued}\n";
