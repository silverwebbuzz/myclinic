<?php

declare(strict_types=1);

use App\Core\Database;

/** @var PDO $pdo */
$pdo = Database::connection();

$count = (int) $pdo->query('SELECT COUNT(*) FROM remedies')->fetchColumn();
if ($count >= 500) {
    echo "  Remedies already seeded ({$count}).\n";

    return;
}

$base = [
    ['Arsenicum Album', 'Ars.alb', 'mineral', 'Anxiety, restlessness, burning pains'],
    ['Belladonna', 'Bell.', 'plant', 'Sudden onset, fever, inflammation'],
    ['Bryonia Alba', 'Bry.', 'plant', 'Worse motion, dry mucous membranes'],
    ['Calcarea Carbonica', 'Calc.', 'mineral', 'Chilly, sweaty, slow development'],
    ['Chamomilla', 'Cham.', 'plant', 'Irritability, teething, colic'],
    ['Ignatia Amara', 'Ign.', 'plant', 'Grief, hysteria, contradictory symptoms'],
    ['Lycopodium Clavatum', 'Lyc.', 'plant', 'Bloating, right-sided complaints'],
    ['Natrum Muriaticum', 'Nat-m.', 'mineral', 'Grief, salt craving, headaches'],
    ['Nux Vomica', 'Nux-v.', 'plant', 'Digestive upset, irritability, overindulgence'],
    ['Pulsatilla', 'Puls.', 'plant', 'Mild, changeable, thirstless'],
    ['Rhus Toxicodendron', 'Rhus-t.', 'plant', 'Stiffness, worse first motion'],
    ['Sepia', 'Sep.', 'animal', 'Hormonal, indifference, sagging'],
    ['Sulphur', 'Sulph.', 'mineral', 'Burning, itching, untidy'],
    ['Thuja Occidentalis', 'Thuj.', 'plant', 'Warts, vaccinosis, fixed ideas'],
    ['Lachesis', 'Lach.', 'animal', 'Left-sided, jealousy, loquacity'],
];

$stmt = $pdo->prepare(
    'INSERT INTO remedies (name, abbreviation, source, key_indications, dietary_restrictions)
     VALUES (?, ?, ?, ?, ?)',
);

$inserted = 0;
for ($i = 0; $inserted < 500; $i++) {
    foreach ($base as $r) {
        $name = $i === 0 ? $r[0] : $r[0] . ' var.' . ($i + 1);
        $stmt->execute([
            $name,
            $r[1],
            $r[2],
            $r[3],
            'Avoid coffee, mint, camphor during treatment.',
        ]);
        $inserted++;
        if ($inserted >= 500) {
            break 2;
        }
    }
}

echo "  {$inserted} remedies seeded.\n";
