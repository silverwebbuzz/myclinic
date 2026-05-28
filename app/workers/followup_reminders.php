<?php

declare(strict_types=1);

// Cron: daily 09:00 — php workers/followup_reminders.php
// Queues WhatsApp follow-up reminders (only for clinics with patient_connect).
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\FollowUpService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$n = FollowUpService::runReminders();
echo "Follow-up reminders queued: {$n}\n";
