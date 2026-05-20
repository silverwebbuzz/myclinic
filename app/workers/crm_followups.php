<?php

declare(strict_types=1);

// Cron: daily 9 AM — php workers/crm_followups.php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\CrmLeadService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$queued = CrmLeadService::queueFollowUpReminders();
echo "CRM follow-up reminders queued: {$queued}\n";
