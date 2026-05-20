<?php

declare(strict_types=1);

// Cron: daily 8 AM — php workers/pharmacy_alerts.php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\QueryBuilder;
use App\Services\OnboardingService;
use App\Services\NotificationService;
use App\Services\PharmacyInventoryService;
use Dotenv\Dotenv;

$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$rows = QueryBuilder::table('clinic_modules')
    ->where('module_id', '=', 'pharmacy')
    ->where('is_active', '=', 1)
    ->get();

$queued = 0;
foreach ($rows as $row) {
    $clinicId = (int) $row['clinic_id'];
    $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
    if ($clinic === null) {
        continue;
    }

    $config = OnboardingService::specialtyConfig($clinicId) ?? [];
    $phone = trim((string) ($config['whatsapp_number'] ?? ''));
    if ($phone === '') {
        continue;
    }

    $low = PharmacyInventoryService::lowStock($clinicId);
    $exp = PharmacyInventoryService::expiringSoon($clinicId, 30);
    if ($low === [] && $exp === []) {
        continue;
    }

    $parts = [];
    foreach (array_slice($low, 0, 5) as $item) {
        $parts[] = ($item['drug_name'] ?? 'Drug') . ' (' . (int) ($item['quantity'] ?? 0) . ')';
    }
    foreach (array_slice($exp, 0, 3) as $item) {
        $parts[] = 'EXP ' . ($item['drug_name'] ?? '') . ' ' . ($item['expiry_date'] ?? '');
    }

    NotificationService::queueWhatsApp(
        $clinicId,
        null,
        $phone,
        'follow_up_reminder',
        [
            'patient_name' => 'Pharmacy alert: ' . implode('; ', $parts),
            'clinic_name' => $clinic['name'],
        ],
        date('Y-m-d') . ' 08:00:00',
    );
    $queued++;
}

echo "Pharmacy alerts queued for {$queued} clinics\n";
