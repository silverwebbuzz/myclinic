<?php

declare(strict_types=1);

// Cron: every 5 minutes — php workers/notification_worker.php
// Daily 7 AM — php workers/notification_worker.php --daily
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\NotificationProcessor;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$daily = in_array('--daily', $argv ?? [], true);
if ($daily) {
    $queued = NotificationProcessor::queueDailyReminders();
    echo "Daily reminders queued: {$queued}\n";
}

$processed = NotificationProcessor::processQueue(100);
echo "Processed notifications: {$processed}\n";
