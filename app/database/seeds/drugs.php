<?php

declare(strict_types=1);

use App\Core\Database;

/** @var PDO $pdo */
$pdo = Database::connection();

$count = (int) $pdo->query('SELECT COUNT(*) FROM drugs')->fetchColumn();
if ($count >= 1000) {
    echo "  Drugs already seeded ({$count}).\n";

    return;
}

$baseDrugs = [
    ['Paracetamol', 'Acetaminophen', 'Analgesic', '500mg', 'tablet'],
    ['Ibuprofen', 'Ibuprofen', 'NSAID', '400mg', 'tablet'],
    ['Amoxicillin', 'Amoxicillin', 'Antibiotic', '500mg', 'capsule'],
    ['Azithromycin', 'Azithromycin', 'Antibiotic', '500mg', 'tablet'],
    ['Metformin', 'Metformin', 'Antidiabetic', '500mg', 'tablet'],
    ['Amlodipine', 'Amlodipine', 'Antihypertensive', '5mg', 'tablet'],
    ['Atorvastatin', 'Atorvastatin', 'Statin', '10mg', 'tablet'],
    ['Omeprazole', 'Omeprazole', 'PPI', '20mg', 'capsule'],
    ['Cetirizine', 'Cetirizine', 'Antihistamine', '10mg', 'tablet'],
    ['Salbutamol', 'Albuterol', 'Bronchodilator', '100mcg', 'inhaler'],
];

$stmt = $pdo->prepare(
    'INSERT INTO drugs (name, generic_name, drug_class, strength, form) VALUES (?, ?, ?, ?, ?)',
);

$inserted = 0;
for ($i = 0; $inserted < 1000; $i++) {
    foreach ($baseDrugs as $d) {
        $name = $i === 0 ? $d[0] : $d[0] . ' ' . ($i + 1);
        $stmt->execute([$name, $d[1], $d[2], $d[3], $d[4]]);
        $inserted++;
        if ($inserted >= 1000) {
            break 2;
        }
    }
}

echo "  {$inserted} drugs seeded.\n";
