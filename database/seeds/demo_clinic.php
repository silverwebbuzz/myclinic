<?php

declare(strict_types=1);

use App\Core\Database;

/** @var PDO $pdo */
$pdo = Database::connection();

$exists = $pdo->query("SELECT id FROM tenants WHERE slug = 'demo'")->fetchColumn();
if ($exists) {
    echo "  Demo clinic already exists.\n";

    return;
}

$pdo->prepare(
    'INSERT INTO tenants (name, slug, specialty, plan, seat_limit, trial_ends_at, onboarding_step, email, phone)
     VALUES (?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 1, ?, ?)',
)->execute([
    'Demo Clinic',
    'demo',
    'gp',
    'free',
    2,
    'admin@demo.manageclinic.com',
    '+919999999999',
]);

$clinicId = (int) $pdo->lastInsertId();

$pdo->prepare(
    'INSERT INTO specialty_configs (clinic_id, uhid_prefix, invoice_prefix, working_hours)
     VALUES (?, ?, ?, ?)',
)->execute([
    $clinicId,
    'DEMO',
    'INV',
    json_encode([
        'mon' => ['open' => '09:00', 'close' => '18:00', 'enabled' => true],
        'tue' => ['open' => '09:00', 'close' => '18:00', 'enabled' => true],
        'wed' => ['open' => '09:00', 'close' => '18:00', 'enabled' => true],
        'thu' => ['open' => '09:00', 'close' => '18:00', 'enabled' => true],
        'fri' => ['open' => '09:00', 'close' => '18:00', 'enabled' => true],
        'sat' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
        'sun' => ['enabled' => false],
    ]),
]);

$freeModules = [
    'patients', 'appointments_basic', 'invoicing_basic', 'billing_pro', 'emr', 'vitals', 'prescription', 'whatsapp',
    'lab', 'pharmacy', 'consent', 'discharge', 'analytics', 'crm', 'staff', 'incentives', 'advanced_scheduling',
    'patient_portal', 'telemedicine', 'diet', 'before_after',
];
$ins = $pdo->prepare(
    'INSERT INTO clinic_modules (clinic_id, module_id, billing_cycle, is_active) VALUES (?, ?, ?, 1)',
);
foreach ($freeModules as $mod) {
    $ins->execute([$clinicId, $mod, 'free']);
}

echo "  Demo clinic created (slug: demo, id: {$clinicId}).\n";
