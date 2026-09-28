<?php

declare(strict_types=1);

// Cron: every 10 minutes — php workers/store_maintenance.php
// eClinicPro Store housekeeping (the same checks also run lazily on page views):
//   1. unpaid orders past their payment window → confirm with Razorpay, else expire + release stock
//   2. paid packages the seller didn't accept by the deadline → auto-cancel + refund
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\Store\FulfilmentService;
use App\Services\Store\OrderService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$expired = OrderService::expireStale(200);
$autoCancelled = FulfilmentService::autoCancelOverdue(50);
echo date('Y-m-d H:i:s') . " store: expired {$expired} unpaid order(s), auto-cancelled {$autoCancelled} late package(s)\n";
