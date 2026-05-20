<?php

declare(strict_types=1);

use App\Core\Database;

/** @var PDO $pdo */
$pdo = Database::connection();

$count = (int) $pdo->query('SELECT COUNT(*) FROM lab_tests_catalog WHERE clinic_id IS NULL')->fetchColumn();
if ($count >= 10) {
    echo "  Lab catalog already seeded ({$count}).\n";

    return;
}

$tests = [
    ['CBC', 'Complete Blood Count', 'haematology', 'blood', 350, [
        ['name' => 'Haemoglobin', 'unit' => 'g/dL', 'min' => 12, 'max' => 17, 'critical_low' => 7, 'critical_high' => 20],
        ['name' => 'WBC', 'unit' => '/cumm', 'min' => 4000, 'max' => 11000],
        ['name' => 'Platelets', 'unit' => '/cumm', 'min' => 150000, 'max' => 450000, 'critical_low' => 50000],
    ]],
    ['FBS', 'Fasting Blood Sugar', 'biochemistry', 'blood', 120, [
        ['name' => 'Glucose (Fasting)', 'unit' => 'mg/dL', 'min' => 70, 'max' => 100, 'critical_low' => 50, 'critical_high' => 400],
    ]],
    ['LFT', 'Liver Function Test', 'biochemistry', 'blood', 600, [
        ['name' => 'SGOT/AST', 'unit' => 'U/L', 'min' => 0, 'max' => 40],
        ['name' => 'SGPT/ALT', 'unit' => 'U/L', 'min' => 0, 'max' => 41],
        ['name' => 'Bilirubin Total', 'unit' => 'mg/dL', 'min' => 0.1, 'max' => 1.2],
    ]],
    ['KFT', 'Kidney Function Test', 'biochemistry', 'blood', 550, [
        ['name' => 'Creatinine', 'unit' => 'mg/dL', 'min' => 0.6, 'max' => 1.3, 'critical_high' => 5],
        ['name' => 'Urea', 'unit' => 'mg/dL', 'min' => 15, 'max' => 45],
    ]],
    ['TSH', 'Thyroid Stimulating Hormone', 'serology', 'blood', 300, [
        ['name' => 'TSH', 'unit' => 'mIU/L', 'min' => 0.4, 'max' => 4.5],
    ]],
    ['URINE-R', 'Urine Routine', 'microbiology', 'urine', 150, [
        ['name' => 'Appearance', 'unit' => '', 'min' => null, 'max' => null],
        ['name' => 'Protein', 'unit' => '', 'min' => null, 'max' => null],
    ]],
    ['HBA1C', 'HbA1c', 'biochemistry', 'blood', 450, [
        ['name' => 'HbA1c', 'unit' => '%', 'min' => 4, 'max' => 5.6, 'critical_high' => 10],
    ]],
    ['CRP', 'C-Reactive Protein', 'serology', 'blood', 400, [
        ['name' => 'CRP', 'unit' => 'mg/L', 'min' => 0, 'max' => 5],
    ]],
];

$stmt = $pdo->prepare(
    'INSERT INTO lab_tests_catalog (clinic_id, test_code, test_name, category, parameters, sample_type, tat_hours, rate, is_active)
     VALUES (NULL, ?, ?, ?, ?, ?, 24, ?, 1)',
);

foreach ($tests as $t) {
    $stmt->execute([
        $t[0], $t[1], $t[2], json_encode($t[4]), $t[3], $t[5],
    ]);
}

echo '  Seeded ' . count($tests) . " global lab tests.\n";
