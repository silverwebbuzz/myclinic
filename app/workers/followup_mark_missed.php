<?php

declare(strict_types=1);

// Cron: daily 03:00 — php workers/followup_mark_missed.php
// Marks follow-ups overdue >30 days as missed.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\FollowUpService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$n = FollowUpService::runMarkMissed();
echo "Follow-ups marked missed: {$n}\n";
