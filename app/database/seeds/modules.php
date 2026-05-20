<?php

declare(strict_types=1);

use App\Core\Database;

/** @var PDO $pdo */
$pdo = Database::connection();

$modules = [
    ['id' => 'patients', 'name' => 'Patient Management', 'category' => 'free', 'price' => 0, 'plans' => '["free","clinic","practice","enterprise"]', 'sort' => 1],
    ['id' => 'appointments_basic', 'name' => 'Appointments Basic', 'category' => 'free', 'price' => 0, 'plans' => '["free","clinic","practice","enterprise"]', 'sort' => 2],
    ['id' => 'invoicing_basic', 'name' => 'Invoicing Basic', 'category' => 'free', 'price' => 0, 'plans' => '["free","clinic","practice","enterprise"]', 'sort' => 3],
    ['id' => 'vitals', 'name' => 'Vitals Tracker', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 10],
    ['id' => 'prescription', 'name' => 'Prescription Engine', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 11],
    ['id' => 'emr', 'name' => 'Clinical Visit / EMR', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 12],
    ['id' => 'billing_pro', 'name' => 'Billing Pro', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 13],
    ['id' => 'whatsapp', 'name' => 'WhatsApp Notifications', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 14],
    ['id' => 'qr', 'name' => 'QR Patient Card', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 15],
    ['id' => 'consent', 'name' => 'Digital Consent Forms', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 16],
    ['id' => 'discharge', 'name' => 'Discharge Summary', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 17],
    ['id' => 'incentives', 'name' => 'Doctor Incentive Calculator', 'category' => 'core', 'price' => 0, 'plans' => '["clinic","practice","enterprise"]', 'sort' => 18],
    ['id' => 'advanced_scheduling', 'name' => 'Advanced Scheduling', 'category' => 'addon', 'price' => 12, 'plans' => '["practice","enterprise"]', 'sort' => 30],
    ['id' => 'lab', 'name' => 'Lab Module (LIS)', 'category' => 'addon', 'price' => 12, 'plans' => '["practice","enterprise"]', 'sort' => 31],
    ['id' => 'pharmacy', 'name' => 'Pharmacy POS', 'category' => 'addon', 'price' => 12, 'plans' => '["practice","enterprise"]', 'sort' => 32],
    ['id' => 'analytics', 'name' => 'Analytics Dashboard', 'category' => 'addon', 'price' => 15, 'plans' => '["practice","enterprise"]', 'sort' => 33],
    ['id' => 'staff', 'name' => 'Staff Management', 'category' => 'addon', 'price' => 8, 'plans' => '["practice","enterprise"]', 'sort' => 34],
    ['id' => 'crm', 'name' => 'CRM & Leads', 'category' => 'addon', 'price' => 10, 'plans' => '["practice","enterprise"]', 'sort' => 35],
    ['id' => 'radiology', 'name' => 'Radiology', 'category' => 'addon', 'price' => 10, 'plans' => '["practice","enterprise"]', 'sort' => 36],
    ['id' => 'telemedicine', 'name' => 'Telemedicine', 'category' => 'addon', 'price' => 15, 'plans' => '["enterprise"]', 'sort' => 40],
    ['id' => 'diet', 'name' => 'Diet & Nutrition', 'category' => 'addon', 'price' => 8, 'plans' => '["enterprise"]', 'sort' => 41],
    ['id' => 'patient_portal', 'name' => 'Patient Portal', 'category' => 'addon', 'price' => 10, 'plans' => '["enterprise"]', 'sort' => 42],
    ['id' => 'before_after', 'name' => 'Before/After Photos', 'category' => 'addon', 'price' => 8, 'plans' => '["enterprise"]', 'sort' => 43],
    ['id' => 'sms_email', 'name' => 'SMS + Email', 'category' => 'addon', 'price' => 5, 'plans' => '["enterprise"]', 'sort' => 44],
    ['id' => 'extra_seat', 'name' => 'Extra Seat', 'category' => 'addon', 'price' => 5, 'plans' => '["clinic","practice"]', 'sort' => 50],
    ['id' => 'directory', 'name' => 'Doctors Directory', 'category' => 'platform', 'price' => 0, 'plans' => '[]', 'sort' => 90],
    ['id' => 'super_admin', 'name' => 'Super Admin Panel', 'category' => 'platform', 'price' => 0, 'plans' => '[]', 'sort' => 91],
    ['id' => 'api', 'name' => 'REST API', 'category' => 'platform', 'price' => 0, 'plans' => '["enterprise"]', 'sort' => 92],
    ['id' => 'white_label', 'name' => 'White Label', 'category' => 'platform', 'price' => 0, 'plans' => '["enterprise"]', 'sort' => 93],
];

$stmt = $pdo->prepare(
    'INSERT IGNORE INTO module_catalog (id, name, description, category, price_monthly_usd, specialties, included_in_plans, sort_order)
     VALUES (:id, :name, :name, :category, :price, \'["all"]\', :plans, :sort)',
);

foreach ($modules as $m) {
    $stmt->execute([
        'id' => $m['id'],
        'name' => $m['name'],
        'category' => $m['category'],
        'price' => $m['price'],
        'plans' => $m['plans'],
        'sort' => $m['sort'],
    ]);
}

echo '  ' . count($modules) . " modules seeded.\n";
