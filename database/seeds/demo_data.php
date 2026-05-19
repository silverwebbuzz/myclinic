<?php

declare(strict_types=1);

/**
 * Demo data seeder — 4 clinics (one per plan tier), backdated 2026-01-01 → today.
 *
 * Usage:
 *   composer demo              # seed (skips clinics that already exist)
 *   composer demo -- --wipe    # truncate the 4 demo tenants first, then reseed
 *
 * Login passwords for all seeded users: Password@123
 * Patient portal OTPs are not seeded — generate on demand via /portal/login.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Database;
use Dotenv\Dotenv;

$base = dirname(__DIR__, 2);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

mt_srand(20260101);

$WIPE = in_array('--wipe', $argv ?? [], true);
$START_DATE = new DateTimeImmutable('2026-01-01');
$END_DATE = new DateTimeImmutable('today');
$PASSWORD_HASH = password_hash('Password@123', PASSWORD_BCRYPT);

$CLINICS = [
    [
        'slug' => 'sunrise', 'name' => 'Sunrise Family Clinic',
        'plan' => 'free', 'specialty' => 'gp', 'seat_limit' => 2,
        'doctors' => [['name' => 'Dr. Rohan Mehta', 'spec' => 'General Practitioner', 'fee' => 300]],
        'sub_doctors' => [],
        'receptionists' => [['name' => 'Priya Shah']],
        'patient_count' => 60, 'appts_per_doc_per_day' => [4, 8],
        'modules' => ['patients', 'appointments_basic', 'invoicing_basic'],
        'uhid_prefix' => 'SUN', 'invoice_prefix' => 'SUN',
    ],
    [
        'slug' => 'carepoint', 'name' => 'CarePoint Homeopathy',
        'plan' => 'clinic', 'specialty' => 'homeopathy', 'seat_limit' => 3,
        'doctors' => [['name' => 'Dr. Anita Sharma', 'spec' => 'Homeopathy', 'fee' => 500, 'incentive' => 10.0]],
        'sub_doctors' => [],
        'receptionists' => [['name' => 'Anjali Verma']],
        'patient_count' => 250, 'appts_per_doc_per_day' => [6, 14],
        'modules' => ['patients', 'appointments_basic', 'invoicing_basic', 'vitals', 'prescription', 'emr', 'billing_pro', 'whatsapp', 'qr', 'consent', 'discharge', 'incentives'],
        'uhid_prefix' => 'CP', 'invoice_prefix' => 'CP',
    ],
    [
        'slug' => 'wellness', 'name' => 'Wellness Multispecialty',
        'plan' => 'practice', 'specialty' => 'other', 'seat_limit' => 8,
        'doctors' => [
            ['name' => 'Dr. Suresh Iyer', 'spec' => 'Cardiology', 'fee' => 800, 'incentive' => 15.0],
            ['name' => 'Dr. Asif Khan', 'spec' => 'Dermatology', 'fee' => 700, 'incentive' => 12.0],
            ['name' => 'Dr. Latha Rao', 'spec' => 'Paediatrics', 'fee' => 600, 'incentive' => 12.0],
        ],
        'sub_doctors' => [
            ['name' => 'Dr. Kavya Nair (Jr)', 'spec' => 'Resident', 'fee' => 400, 'incentive' => 5.0],
            ['name' => 'Dr. Vikram Bose (Jr)', 'spec' => 'Resident', 'fee' => 400, 'incentive' => 5.0],
        ],
        'receptionists' => [['name' => 'Neha Kapoor']],
        'patient_count' => 600, 'appts_per_doc_per_day' => [5, 12],
        'modules' => ['patients', 'appointments_basic', 'invoicing_basic', 'vitals', 'prescription', 'emr', 'billing_pro', 'whatsapp', 'qr', 'consent', 'discharge', 'incentives', 'advanced_scheduling', 'lab', 'pharmacy', 'analytics', 'staff', 'crm'],
        'uhid_prefix' => 'WMS', 'invoice_prefix' => 'WMS',
    ],
    [
        'slug' => 'metrohealth', 'name' => 'MetroHealth Group',
        'plan' => 'enterprise', 'specialty' => 'other', 'seat_limit' => 999,
        'doctors' => [
            ['name' => 'Dr. Rajesh Gupta', 'spec' => 'Orthopaedics', 'fee' => 1200, 'incentive' => 18.0],
            ['name' => 'Dr. Meera Joshi', 'spec' => 'Gynaecology', 'fee' => 1000, 'incentive' => 15.0],
            ['name' => 'Dr. Tarun Das', 'spec' => 'ENT', 'fee' => 900, 'incentive' => 15.0],
            ['name' => 'Dr. Sneha Pillai', 'spec' => 'Dermatology', 'fee' => 1100, 'incentive' => 15.0],
        ],
        'sub_doctors' => [
            ['name' => 'Dr. Aakash Jain (Jr)', 'spec' => 'Resident', 'fee' => 500, 'incentive' => 6.0],
            ['name' => 'Dr. Ritu Sen (Jr)', 'spec' => 'Resident', 'fee' => 500, 'incentive' => 6.0],
            ['name' => 'Dr. Manish Yadav (Jr)', 'spec' => 'Resident', 'fee' => 500, 'incentive' => 6.0],
        ],
        'receptionists' => [['name' => 'Pooja Singh'], ['name' => 'Reema Das']],
        'patient_count' => 1200, 'appts_per_doc_per_day' => [6, 16],
        'modules' => null,
        'uhid_prefix' => 'MH', 'invoice_prefix' => 'MH',
    ],
];

if ($WIPE) {
    echo "[wipe] Truncating demo tenants...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($CLINICS as $c) {
        $tid = (int) $pdo->query("SELECT id FROM tenants WHERE slug='{$c['slug']}'")->fetchColumn();
        if (!$tid) {
            continue;
        }
        $tables = [
            'doctor_incentives', 'expenses', 'crm_leads', 'staff_leaves', 'staff_attendance',
            'diet_plans', 'patient_photos', 'discharge_summaries', 'consent_forms',
            'lab_results', 'lab_orders', 'lab_tests_catalog', 'pharmacy_inventory',
            'payments', 'invoice_items', 'invoices', 'prescriptions', 'vitals', 'visits',
            'appointments', 'doctor_leaves', 'doctor_schedules', 'waiting_list',
            'patient_allergies', 'notifications', 'analytics_snapshots', 'events',
            'audit_log', 'api_keys', 'doctor_locations', 'doctor_profiles',
            'patients', 'staff_invitations', 'users', 'clinic_modules', 'specialty_configs',
            'saas_invoices',
        ];
        foreach ($tables as $t) {
            $pdo->exec("DELETE FROM {$t} WHERE clinic_id={$tid}");
        }
        $pdo->exec("DELETE FROM tenants WHERE id={$tid}");
        echo "  wiped: {$c['slug']}\n";
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

$allModuleIds = $pdo->query('SELECT id FROM module_catalog')->fetchAll(PDO::FETCH_COLUMN);
$drugIds = $pdo->query('SELECT id FROM drugs LIMIT 60')->fetchAll(PDO::FETCH_COLUMN);
$remedyIds = $pdo->query('SELECT id FROM remedies LIMIT 30')->fetchAll(PDO::FETCH_COLUMN);

if (!$allModuleIds) {
    fwrite(STDERR, "ERROR: module_catalog is empty. Run `php database/seed.php` first.\n");
    exit(1);
}

function pickN(array $a, int $n): array
{
    if ($n >= count($a)) {
        return $a;
    }
    $keys = (array) array_rand($a, $n);

    return array_values(array_intersect_key($a, array_flip($keys)));
}

function randInRange(int $min, int $max): int
{
    return mt_rand($min, $max);
}

function phone(): string
{
    return '+9170000' . str_pad((string) mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
}

function fakeName(string $gender): string
{
    $first = $gender === 'M'
        ? ['Arjun', 'Karan', 'Aman', 'Ravi', 'Vikas', 'Rohit', 'Sahil', 'Devansh', 'Nikhil', 'Yash', 'Akash', 'Ritesh', 'Sandeep', 'Manoj', 'Praveen']
        : ['Priya', 'Anita', 'Neha', 'Pooja', 'Riya', 'Sneha', 'Kavya', 'Megha', 'Divya', 'Shreya', 'Ankita', 'Swati', 'Nisha', 'Rekha', 'Lata'];
    $last = ['Sharma', 'Patel', 'Reddy', 'Nair', 'Iyer', 'Khan', 'Singh', 'Verma', 'Mehta', 'Joshi', 'Kapoor', 'Das', 'Bose', 'Pillai', 'Yadav', 'Gupta'];

    return $first[array_rand($first)] . ' ' . $last[array_rand($last)];
}

function dailyRange(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $out = [];
    $cur = $start;
    while ($cur <= $end) {
        $out[] = $cur;
        $cur = $cur->modify('+1 day');
    }

    return $out;
}

$ICD = [
    ['I10', 'Essential hypertension'], ['E11.9', 'Type 2 diabetes mellitus'],
    ['J06.9', 'Acute URI'], ['K30', 'Functional dyspepsia'],
    ['L20.9', 'Atopic dermatitis'], ['M54.5', 'Low back pain'],
    ['R51', 'Headache'], ['N39.0', 'UTI'], ['J45.9', 'Asthma'],
    ['R10.4', 'Abdominal pain'], ['F41.1', 'Anxiety'], ['R05', 'Cough'],
];

$COMPLAINTS = [
    'Headache for 3 days', 'Cough and cold since 1 week', 'Lower back pain',
    'Skin rash on arms', 'Fever with chills', 'Stomach pain after meals',
    'Fatigue and weakness', 'Sore throat', 'Joint pain in knees',
    'Acidity and bloating', 'Allergic reaction', 'Sleep disturbance',
];

echo "[seed] Creating " . count($CLINICS) . " clinics...\n";
$days = dailyRange($START_DATE, $END_DATE);

foreach ($CLINICS as $C) {
    if ($pdo->query("SELECT id FROM tenants WHERE slug='{$C['slug']}'")->fetchColumn()) {
        echo "  [{$C['slug']}] already exists, skipping. Use --wipe to reset.\n";
        continue;
    }

    echo "  [{$C['slug']}] {$C['name']} ({$C['plan']})...\n";
    $createdAt = $START_DATE->format('Y-m-d 09:00:00');

    $pdo->prepare(
        'INSERT INTO tenants (name, slug, specialty, plan, seat_limit, onboarding_step, onboarding_completed_at, email, phone, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, 5, ?, ?, ?, 1, ?)'
    )->execute([
        $C['name'], $C['slug'], $C['specialty'], $C['plan'], $C['seat_limit'],
        $createdAt, "admin@{$C['slug']}.test", phone(), $createdAt,
    ]);
    $clinicId = (int) $pdo->lastInsertId();

    $rxMode = $C['specialty'] === 'homeopathy' ? 'homeopathic' : 'allopathic';
    $pdo->prepare(
        'INSERT INTO specialty_configs (clinic_id, prescription_mode, uhid_prefix, invoice_prefix, slot_duration_min, invoice_tax_percent, working_hours)
         VALUES (?, ?, ?, ?, 15, 18.00, ?)'
    )->execute([
        $clinicId, $rxMode, $C['uhid_prefix'], $C['invoice_prefix'],
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

    $modules = $C['modules'] ?? $allModuleIds;
    $insMod = $pdo->prepare(
        'INSERT INTO clinic_modules (clinic_id, module_id, billing_cycle, is_active) VALUES (?, ?, ?, 1)'
    );
    foreach ($modules as $m) {
        if (in_array($m, $allModuleIds, true)) {
            $insMod->execute([$clinicId, $m, $C['plan'] === 'free' ? 'free' : 'monthly']);
        }
    }

    $userIns = $pdo->prepare(
        'INSERT INTO users (clinic_id, name, email, phone, password_hash, role, is_owner, specialization, incentive_percent, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
    );

    $userIns->execute([$clinicId, "Owner ({$C['name']})", "owner@{$C['slug']}.test", phone(), $PASSWORD_HASH, 'admin', 1, null, 0, $createdAt]);
    $ownerId = (int) $pdo->lastInsertId();

    $doctorIds = [];
    foreach (array_merge($C['doctors'], $C['sub_doctors']) as $i => $D) {
        $isSub = $i >= count($C['doctors']);
        $userIns->execute([
            $clinicId, $D['name'],
            strtolower(str_replace([' ', '.', '(', ')'], ['', '', '', ''], $D['name'])) . "@{$C['slug']}.test",
            phone(), $PASSWORD_HASH, 'doctor', 0, $D['spec'], $D['incentive'] ?? 0, $createdAt,
        ]);
        $did = (int) $pdo->lastInsertId();
        $doctorIds[] = ['id' => $did, 'name' => $D['name'], 'fee' => $D['fee'], 'incentive' => $D['incentive'] ?? 0, 'sub' => $isSub];
    }

    $receptionistIds = [];
    foreach ($C['receptionists'] as $R) {
        $userIns->execute([
            $clinicId, $R['name'],
            strtolower(str_replace(' ', '', $R['name'])) . "@{$C['slug']}.test",
            phone(), $PASSWORD_HASH, 'receptionist', 0, null, 0, $createdAt,
        ]);
        $receptionistIds[] = (int) $pdo->lastInsertId();
    }

    $schedIns = $pdo->prepare(
        'INSERT INTO doctor_schedules (clinic_id, doctor_id, day_of_week, start_time, end_time, slot_duration, max_patients, is_active)
         VALUES (?, ?, ?, ?, ?, 15, 30, 1)'
    );
    foreach ($doctorIds as $D) {
        for ($dow = 1; $dow <= 6; $dow++) {
            $schedIns->execute([$clinicId, $D['id'], $dow, '09:00:00', $dow === 6 ? '13:00:00' : '18:00:00']);
        }
    }

    $patientIns = $pdo->prepare(
        'INSERT INTO patients (clinic_id, uhid_seq, uhid, name, dob, gender, phone, email, blood_group, qr_token, source, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $patientIds = [];
    for ($p = 1; $p <= $C['patient_count']; $p++) {
        $gender = mt_rand(0, 1) ? 'M' : 'F';
        $name = fakeName($gender);
        $dob = (new DateTimeImmutable('2026-01-01'))
            ->modify('-' . randInRange(5, 80) . ' years')
            ->modify('-' . randInRange(0, 364) . ' days')->format('Y-m-d');
        $blood = ['A+', 'B+', 'O+', 'AB+', 'A-', 'O-'][mt_rand(0, 5)];
        $source = ['walk_in', 'walk_in', 'referral', 'online', 'camp'][mt_rand(0, 4)];

        $offset = (int) (($p / $C['patient_count']) * (count($days) - 1));
        $regAt = $days[$offset]->setTime(randInRange(9, 17), randInRange(0, 59))->format('Y-m-d H:i:s');

        $uhid = $C['uhid_prefix'] . str_pad((string) $p, 5, '0', STR_PAD_LEFT);
        $qr = bin2hex(random_bytes(16));
        $patientIns->execute([
            $clinicId, $p, $uhid, $name, $dob, $gender, phone(),
            mt_rand(0, 1) ? strtolower(str_replace(' ', '.', $name)) . '@test.local' : null,
            $blood, $qr, $source, $regAt,
        ]);
        $patientIds[] = (int) $pdo->lastInsertId();
    }

    $apptIns = $pdo->prepare(
        'INSERT INTO appointments (clinic_id, patient_id, doctor_id, scheduled_at, slot_duration, type, source, status, chief_complaint, token_number, reminder_sent, created_by, created_at)
         VALUES (?, ?, ?, ?, 15, ?, ?, ?, ?, ?, 1, ?, ?)'
    );
    $visitIns = $pdo->prepare(
        'INSERT INTO visits (clinic_id, appointment_id, patient_id, doctor_id, visit_number, chief_complaint, diagnosis, icd10_code, clinical_notes, visited_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $vitalIns = $pdo->prepare(
        'INSERT INTO vitals (clinic_id, visit_id, patient_id, bp_systolic, bp_diastolic, weight_kg, height_cm, temperature, spo2, pulse_rate, recorded_by, recorded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $rxIns = $pdo->prepare(
        'INSERT INTO prescriptions (clinic_id, visit_id, patient_id, mode, drug_id, remedy_id, dosage, frequency, duration_days, instructions, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $invIns = $pdo->prepare(
        'INSERT INTO invoices (clinic_id, patient_id, visit_id, attributed_doctor_id, invoice_number, currency, subtotal, tax_label, tax_percent, tax_amount, total, advance_paid, payment_mode, status, paid_at, created_at)
         VALUES (?, ?, ?, ?, ?, "INR", ?, "GST", 18.00, ?, ?, ?, ?, ?, ?, ?)'
    );
    $invItemIns = $pdo->prepare(
        'INSERT INTO invoice_items (invoice_id, description, item_type, qty, unit_price, discount) VALUES (?, ?, ?, ?, ?, 0)'
    );
    $payIns = $pdo->prepare(
        'INSERT INTO payments (clinic_id, invoice_id, amount, method, paid_at, recorded_by) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $hasEMR = in_array('emr', $modules, true);
    $hasBillingPro = in_array('billing_pro', $modules, true) || in_array('invoicing_basic', $modules, true);
    $hasLab = in_array('lab', $modules, true);
    $hasPharmacy = in_array('pharmacy', $modules, true);
    $hasCRM = in_array('crm', $modules, true);
    $hasStaff = in_array('staff', $modules, true);
    $hasIncentives = in_array('incentives', $modules, true);
    $hasConsent = in_array('consent', $modules, true);
    $hasDischarge = in_array('discharge', $modules, true);
    $hasPortal = in_array('patient_portal', $modules, true);
    $hasPhotos = in_array('before_after', $modules, true);
    $hasDiet = in_array('diet', $modules, true);

    $invoiceCounter = 0;
    $apptCount = 0;
    $visitCount = 0;
    [$minA, $maxA] = $C['appts_per_doc_per_day'];

    foreach ($days as $day) {
        $dow = (int) $day->format('w');
        if ($dow === 0) {
            continue;
        }
        $weight = $dow === 1 ? 1.3 : ($dow === 6 ? 0.6 : 1.0);
        $dayOfMonth = (int) $day->format('j');
        if ($dayOfMonth >= 12 && $dayOfMonth <= 18) {
            $weight *= 1.15;
        }

        foreach ($doctorIds as $D) {
            if ($D['sub'] && mt_rand(0, 100) < 30) {
                continue;
            }
            $count = (int) round(randInRange($minA, $maxA) * $weight);
            for ($a = 0; $a < $count; $a++) {
                $hour = randInRange(9, $dow === 6 ? 12 : 17);
                $min = [0, 15, 30, 45][mt_rand(0, 3)];
                $apptAt = $day->setTime($hour, $min);
                $patientId = $patientIds[array_rand($patientIds)];
                $complaint = $COMPLAINTS[array_rand($COMPLAINTS)];

                $roll = mt_rand(1, 100);
                if ($apptAt > $END_DATE) {
                    $status = 'scheduled';
                } elseif ($roll <= 70) {
                    $status = 'completed';
                } elseif ($roll <= 82) {
                    $status = 'cancelled';
                } elseif ($roll <= 90) {
                    $status = 'no_show';
                } else {
                    $status = 'confirmed';
                }

                $type = mt_rand(0, 100) < 20 ? 'walkin' : 'prebooked';
                $source = ['reception', 'reception', 'phone', 'whatsapp', 'website'][mt_rand(0, 4)];
                $createdBy = $receptionistIds[array_rand($receptionistIds)];

                $apptIns->execute([
                    $clinicId, $patientId, $D['id'], $apptAt->format('Y-m-d H:i:s'),
                    $type, $source, $status, $complaint, $a + 1, $createdBy,
                    $apptAt->modify('-1 day')->format('Y-m-d H:i:s'),
                ]);
                $apptId = (int) $pdo->lastInsertId();
                $apptCount++;

                if ($status !== 'completed' || !$hasEMR) {
                    if ($status === 'completed' && $hasBillingPro) {
                        $invoiceCounter++;
                        $sub = (float) $D['fee'];
                        $tax = round($sub * 0.18, 2);
                        $total = $sub + $tax;
                        $invNum = $C['invoice_prefix'] . '-' . str_pad((string) $invoiceCounter, 6, '0', STR_PAD_LEFT);
                        $payRoll = mt_rand(1, 100);
                        $invStatus = $payRoll <= 85 ? 'paid' : ($payRoll <= 95 ? 'partial' : 'sent');
                        $advance = $invStatus === 'paid' ? $total : ($invStatus === 'partial' ? round($total / 2, 2) : 0);
                        $payMode = ['cash', 'upi', 'card'][mt_rand(0, 2)];
                        $paidAt = $invStatus === 'paid' ? $apptAt->format('Y-m-d H:i:s') : null;
                        $invIns->execute([
                            $clinicId, $patientId, null, $D['id'], $invNum,
                            $sub, $tax, $total, $advance, $payMode, $invStatus, $paidAt, $apptAt->format('Y-m-d H:i:s'),
                        ]);
                        $invId = (int) $pdo->lastInsertId();
                        $invItemIns->execute([$invId, 'Consultation', 'consultation', 1, $sub]);
                        if ($advance > 0) {
                            $payIns->execute([$clinicId, $invId, $advance, $payMode, $apptAt->format('Y-m-d H:i:s'), $createdBy]);
                        }
                    }
                    continue;
                }

                $icd = $ICD[array_rand($ICD)];
                $visitIns->execute([
                    $clinicId, $apptId, $patientId, $D['id'], 1,
                    $complaint, $icd[1], $icd[0],
                    'Patient examined. ' . $icd[1] . '. Advised rest and medication.',
                    $apptAt->format('Y-m-d H:i:s'),
                ]);
                $visitId = (int) $pdo->lastInsertId();
                $visitCount++;

                $vitalIns->execute([
                    $clinicId, $visitId, $patientId,
                    randInRange(105, 145), randInRange(65, 95),
                    randInRange(50, 95), randInRange(150, 185),
                    36.5 + (mt_rand(0, 30) / 10), randInRange(94, 99), randInRange(60, 95),
                    $D['id'], $apptAt->format('Y-m-d H:i:s'),
                ]);

                $rxLines = randInRange(1, 3);
                for ($r = 0; $r < $rxLines; $r++) {
                    if ($rxMode === 'homeopathic' && $remedyIds) {
                        $rxIns->execute([
                            $clinicId, $visitId, $patientId, 'homeopathic', null,
                            $remedyIds[array_rand($remedyIds)], '5 drops', 'TDS', randInRange(7, 30),
                            'In warm water before meals', $r,
                        ]);
                    } elseif ($drugIds) {
                        $rxIns->execute([
                            $clinicId, $visitId, $patientId, 'allopathic',
                            $drugIds[array_rand($drugIds)], null,
                            '1 tab', ['OD', 'BD', 'TDS'][mt_rand(0, 2)], randInRange(3, 14),
                            'After food', $r,
                        ]);
                    }
                }

                if ($hasBillingPro) {
                    $invoiceCounter++;
                    $sub = (float) $D['fee'];
                    $tax = round($sub * 0.18, 2);
                    $total = $sub + $tax;
                    $invNum = $C['invoice_prefix'] . '-' . str_pad((string) $invoiceCounter, 6, '0', STR_PAD_LEFT);
                    $payRoll = mt_rand(1, 100);
                    $invStatus = $payRoll <= 85 ? 'paid' : ($payRoll <= 95 ? 'partial' : 'sent');
                    $advance = $invStatus === 'paid' ? $total : ($invStatus === 'partial' ? round($total / 2, 2) : 0);
                    $payMode = ['cash', 'upi', 'card'][mt_rand(0, 2)];
                    $paidAt = $invStatus === 'paid' ? $apptAt->format('Y-m-d H:i:s') : null;
                    $invIns->execute([
                        $clinicId, $patientId, $visitId, $D['id'], $invNum,
                        $sub, $tax, $total, $advance, $payMode, $invStatus, $paidAt, $apptAt->format('Y-m-d H:i:s'),
                    ]);
                    $invId = (int) $pdo->lastInsertId();
                    $invItemIns->execute([$invId, 'Consultation', 'consultation', 1, $sub]);
                    if ($advance > 0) {
                        $payIns->execute([$clinicId, $invId, $advance, $payMode, $apptAt->format('Y-m-d H:i:s'), $createdBy]);
                    }
                }
            }
        }
    }

    if ($hasLab && $patientIds) {
        $labCat = $pdo->prepare(
            'INSERT INTO lab_tests_catalog (clinic_id, test_code, test_name, category, parameters, sample_type, tat_hours, rate, is_panel, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1)'
        );
        $tests = [
            ['CBC', 'Complete Blood Count', 'haematology', 'blood', 24, 350],
            ['LFT', 'Liver Function Test', 'biochemistry', 'blood', 24, 800],
            ['KFT', 'Kidney Function Test', 'biochemistry', 'blood', 24, 750],
            ['HBA1C', 'HbA1c', 'biochemistry', 'blood', 24, 500],
            ['TSH', 'Thyroid TSH', 'biochemistry', 'blood', 24, 400],
        ];
        $testIds = [];
        foreach ($tests as $t) {
            $labCat->execute([$clinicId, $t[0], $t[1], $t[2], json_encode([['name' => $t[1], 'unit' => '', 'range' => '']]), $t[3], $t[4], $t[5]]);
            $testIds[] = (int) $pdo->lastInsertId();
        }
        $labOrdIns = $pdo->prepare(
            'INSERT INTO lab_orders (clinic_id, patient_id, ordered_by, test_id, barcode, status, ordered_at, resulted_at, share_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($days as $day) {
            if (mt_rand(0, 1) === 0) {
                continue;
            }
            $orders = randInRange(2, 4);
            for ($i = 0; $i < $orders; $i++) {
                $orderedAt = $day->setTime(randInRange(9, 16), randInRange(0, 59));
                $resulted = $orderedAt < $END_DATE->modify('-1 day');
                $status = $resulted ? 'resulted' : 'ordered';
                $labOrdIns->execute([
                    $clinicId, $patientIds[array_rand($patientIds)], $doctorIds[0]['id'],
                    $testIds[array_rand($testIds)], 'BC' . str_pad((string) randInRange(10000, 99999), 6, '0', STR_PAD_LEFT),
                    $status, $orderedAt->format('Y-m-d H:i:s'),
                    $resulted ? $orderedAt->modify('+1 day')->format('Y-m-d H:i:s') : null,
                    bin2hex(random_bytes(16)),
                ]);
            }
        }
    }

    if ($hasPharmacy && $drugIds) {
        $phIns = $pdo->prepare(
            'INSERT INTO pharmacy_inventory (clinic_id, drug_id, batch_number, quantity, low_stock_threshold, expiry_date, purchase_price, selling_price, supplier, added_at)
             VALUES (?, ?, ?, ?, 10, ?, ?, ?, ?, ?)'
        );
        foreach (pickN($drugIds, 25) as $drugId) {
            $batch = 'B' . randInRange(100, 999);
            $qty = randInRange(20, 200);
            $expiry = (new DateTimeImmutable('today'))->modify('+' . randInRange(30, 540) . ' days')->format('Y-m-d');
            $pp = randInRange(20, 200);
            $phIns->execute([
                $clinicId, $drugId, $batch, $qty, $expiry, $pp, round($pp * 1.4),
                'MediSupply Pvt Ltd', $START_DATE->format('Y-m-d H:i:s'),
            ]);
        }
    }

    if ($hasCRM) {
        $crmIns = $pdo->prepare(
            'INSERT INTO crm_leads (clinic_id, name, phone, email, inquiry_about, source, assigned_to, status, converted_patient_id, follow_up_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($days as $day) {
            $leads = randInRange(0, 3);
            for ($i = 0; $i < $leads; $i++) {
                $name = fakeName(mt_rand(0, 1) ? 'M' : 'F');
                $src = ['website', 'google_ads', 'instagram', 'facebook', 'walk_in', 'referral'][mt_rand(0, 5)];
                $roll = mt_rand(1, 100);
                $status = $roll <= 30 ? 'converted' : ($roll <= 55 ? 'follow_up' : ($roll <= 75 ? 'contacted' : ($roll <= 90 ? 'new' : 'lost')));
                $converted = $status === 'converted' ? $patientIds[array_rand($patientIds)] : null;
                $crmIns->execute([
                    $clinicId, $name, phone(),
                    mt_rand(0, 1) ? strtolower(str_replace(' ', '.', $name)) . '@test.local' : null,
                    'Inquiry about treatment options', $src, $receptionistIds[0], $status, $converted,
                    $day->modify('+' . randInRange(1, 7) . ' days')->format('Y-m-d'),
                    $day->setTime(randInRange(10, 17), 0)->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    if ($hasStaff) {
        $attIns = $pdo->prepare(
            'INSERT IGNORE INTO staff_attendance (clinic_id, user_id, date, clock_in, clock_out, status) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $staffUsers = array_merge(array_column($doctorIds, 'id'), $receptionistIds);
        foreach ($days as $day) {
            $dow = (int) $day->format('w');
            if ($dow === 0) {
                continue;
            }
            foreach ($staffUsers as $uid) {
                $roll = mt_rand(1, 100);
                if ($roll <= 90) {
                    $attIns->execute([$clinicId, $uid, $day->format('Y-m-d'),
                        sprintf('%02d:%02d:00', randInRange(8, 9), randInRange(45, 59)),
                        sprintf('%02d:%02d:00', randInRange(17, 19), randInRange(0, 30)), 'present', ]);
                } elseif ($roll <= 95) {
                    $attIns->execute([$clinicId, $uid, $day->format('Y-m-d'), null, null, 'leave']);
                } elseif ($roll <= 98) {
                    $attIns->execute([$clinicId, $uid, $day->format('Y-m-d'),
                        sprintf('%02d:%02d:00', 9, randInRange(0, 30)),
                        sprintf('%02d:%02d:00', 13, randInRange(0, 30)), 'half_day', ]);
                } else {
                    $attIns->execute([$clinicId, $uid, $day->format('Y-m-d'), null, null, 'absent']);
                }
            }
        }
    }

    if ($hasIncentives) {
        $incIns = $pdo->prepare(
            'INSERT INTO doctor_incentives (clinic_id, doctor_id, period_month, revenue_generated, incentive_percent, flat_fee, tds_amount, net_payable, payment_status, paid_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)'
        );
        $months = ['2026-01', '2026-02', '2026-03', '2026-04'];
        foreach ($doctorIds as $D) {
            if ($D['incentive'] <= 0) {
                continue;
            }
            foreach ($months as $m) {
                $revStmt = $pdo->prepare(
                    "SELECT COALESCE(SUM(total),0) FROM invoices WHERE clinic_id=? AND attributed_doctor_id=? AND DATE_FORMAT(created_at,'%Y-%m')=? AND status IN ('paid','partial')"
                );
                $revStmt->execute([$clinicId, $D['id'], $m]);
                $rev = (float) $revStmt->fetchColumn();
                $gross = round($rev * $D['incentive'] / 100, 2);
                $tds = round($gross * 0.1, 2);
                $net = $gross - $tds;
                $incIns->execute([
                    $clinicId, $D['id'], $m, $rev, $D['incentive'], $tds, $net,
                    'paid', (new DateTimeImmutable($m . '-05'))->format('Y-m-d 10:00:00'),
                ]);
            }
        }
    }

    $expIns = $pdo->prepare(
        'INSERT INTO expenses (clinic_id, category, description, amount, currency, expense_date, paid_via, entered_by, created_at)
         VALUES (?, ?, ?, ?, "INR", ?, ?, ?, ?)'
    );
    $monthsCur = $START_DATE;
    while ($monthsCur <= $END_DATE) {
        $expIns->execute([$clinicId, 'rent', 'Monthly rent', randInRange(25000, 60000), $monthsCur->format('Y-m-01'), 'bank', $ownerId, $monthsCur->format('Y-m-01 09:00:00')]);
        $expIns->execute([$clinicId, 'utilities', 'Electricity + Internet', randInRange(4000, 9000), $monthsCur->format('Y-m-05'), 'upi', $ownerId, $monthsCur->format('Y-m-05 10:00:00')]);
        $expIns->execute([$clinicId, 'consumables', 'Medical supplies', randInRange(8000, 22000), $monthsCur->format('Y-m-10'), 'bank', $ownerId, $monthsCur->format('Y-m-10 10:00:00')]);
        $monthsCur = $monthsCur->modify('+1 month');
    }

    if ($hasConsent && $visitCount > 0) {
        $consIns = $pdo->prepare(
            'INSERT INTO consent_forms (clinic_id, patient_id, visit_id, form_type, form_version, form_content, signed_by_name, relationship, content_hash, signed_at)
             VALUES (?, ?, ?, ?, "v1", ?, ?, "self", ?, ?)'
        );
        $someVisits = $pdo->query("SELECT v.id, v.patient_id, p.name, v.visited_at FROM visits v JOIN patients p ON p.id=v.patient_id WHERE v.clinic_id={$clinicId} ORDER BY RAND() LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($someVisits as $v) {
            $content = 'I consent to the procedure as explained.';
            $consIns->execute([
                $clinicId, $v['patient_id'], $v['id'], 'general', $content, $v['name'],
                hash('sha256', $content), $v['visited_at'],
            ]);
        }
    }

    if ($hasDischarge) {
        $dsIns = $pdo->prepare(
            'INSERT INTO discharge_summaries (clinic_id, patient_id, visit_id, final_diagnosis, condition_at_discharge, follow_up_instructions, status, finalized_at, created_at)
             VALUES (?, ?, ?, ?, "improved", "Follow up in 7 days", "finalized", ?, ?)'
        );
        $someVisits = $pdo->query("SELECT id, patient_id, diagnosis, visited_at FROM visits WHERE clinic_id={$clinicId} ORDER BY RAND() LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($someVisits as $v) {
            $dsIns->execute([$clinicId, $v['patient_id'], $v['id'], $v['diagnosis'] ?: 'Resolved', $v['visited_at'], $v['visited_at']]);
        }
    }

    if ($hasPhotos) {
        $phIns2 = $pdo->prepare(
            'INSERT INTO patient_photos (clinic_id, patient_id, type, photo_path, condition_label, uploaded_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $samplePatients = array_slice($patientIds, 0, 10);
        foreach ($samplePatients as $pid) {
            $phIns2->execute([$clinicId, $pid, 'before', 'demo/placeholder_before.jpg', 'Treatment start', $START_DATE->format('Y-m-d 10:00:00')]);
            $phIns2->execute([$clinicId, $pid, 'after', 'demo/placeholder_after.jpg', 'After 3 months', $START_DATE->modify('+90 days')->format('Y-m-d 10:00:00')]);
        }
    }

    if ($hasDiet) {
        $dietIns = $pdo->prepare(
            'INSERT INTO diet_plans (clinic_id, patient_id, prescribed_by, `condition`, plan_json, veg_type, status, shared_at, created_at)
             VALUES (?, ?, ?, ?, ?, "veg", "shared", ?, ?)'
        );
        foreach (array_slice($patientIds, 0, 10) as $pid) {
            $plan = ['mon' => ['breakfast' => 'Oats + fruit', 'lunch' => 'Dal rice', 'dinner' => 'Chapati + sabzi']];
            $dietIns->execute([$clinicId, $pid, $doctorIds[0]['id'], 'Weight management', json_encode($plan), $START_DATE->modify('+30 days')->format('Y-m-d 10:00:00'), $START_DATE->modify('+30 days')->format('Y-m-d 10:00:00')]);
        }
    }

    echo "    appointments: {$apptCount}, visits: {$visitCount}\n";
}

echo "\n[seed] Done.\n";
echo "Login: any user email above (owner@<slug>.test, etc.) / password: Password@123\n";
echo "Slugs: " . implode(', ', array_column($CLINICS, 'slug')) . "\n";
