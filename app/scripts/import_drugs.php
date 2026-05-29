<?php

declare(strict_types=1);

/**
 * One-off importer: load the public "A-Z Medicines Dataset of India" CSV
 * into the global `drugs` master catalog (the table the prescription
 * autocomplete searches).
 *
 * The `drugs` table has no unique key on name, so this is a plain insert.
 * To re-run cleanly, pass --fresh to TRUNCATE first (otherwise you'll get
 * duplicates). drugs has FKs from prescriptions/pharmacy_inventory, so
 * --fresh only works before any clinic has prescribed/stocked anything.
 *
 * Usage (from the app root, e.g. /path/to/app):
 *   php scripts/import_drugs.php document/A_Z_medicines_dataset_of_India.csv
 *   php scripts/import_drugs.php /abs/path/to.csv --fresh   (truncate first)
 *
 * CSV columns: id,name,price(₹),Is_discontinued,manufacturer_name,type,
 *              pack_size_label,short_composition1,short_composition2
 *
 * Mapping into `drugs`:
 *   name         <- name
 *   generic_name <- composition with the "(...mg)" strength stripped
 *   strength     <- first strength token extracted from composition
 *   form         <- inferred from pack_size_label (tablet/syrup/...)
 *   is_active    <- NOT Is_discontinued
 * Clinical columns (drug_class, schedule, interactions, contraindications)
 * are left at their defaults — the CSV has no such data.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Database;

$base = dirname(__DIR__);
Application::boot($base);

$csvPath = $argv[1] ?? ($base . '/document/A_Z_medicines_dataset_of_India.csv');
if (!str_starts_with($csvPath, '/')) {
    $csvPath = $base . '/' . $csvPath;
}
$fresh = in_array('--fresh', $argv, true);

if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV not found: {$csvPath}\n");
    exit(1);
}

$pdo = Database::connection();

/** Map a pack-size label to the drugs.form ENUM. */
$inferForm = static function (string $packLabel): string {
    $l = strtolower($packLabel);
    return match (true) {
        str_contains($l, 'tablet')                                   => 'tablet',
        str_contains($l, 'capsule')                                  => 'capsule',
        str_contains($l, 'syrup') || str_contains($l, 'suspension')
            || str_contains($l, 'solution') || str_contains($l, 'liquid')
            || str_contains($l, 'bottle')                            => 'syrup',
        str_contains($l, 'injection') || str_contains($l, 'vial')
            || str_contains($l, 'ampoule')                           => 'injection',
        str_contains($l, 'cream') || str_contains($l, 'ointment')
            || str_contains($l, 'gel') || str_contains($l, 'lotion') => 'cream',
        str_contains($l, 'drop')                                     => 'drops',
        str_contains($l, 'inhaler') || str_contains($l, 'respule')
            || str_contains($l, 'rotacap')                           => 'inhaler',
        str_contains($l, 'patch')                                    => 'patch',
        default                                                      => 'other',
    };
};

/**
 * From a composition like "Amoxycillin  (500mg) , Clavulanic Acid (125mg)"
 * return [generic_name without strengths, first strength token].
 */
$parseComposition = static function (string $c1, string $c2): array {
    $full = trim($c1 . ($c2 !== '' ? ', ' . $c2 : ''));
    $full = preg_replace('/\s+/', ' ', $full) ?? $full;

    // First strength token, e.g. (500mg), (5mg/5ml), (2.5%).
    $strength = null;
    if (preg_match('/\(([^)]*\d[^)]*)\)/', $full, $m)) {
        $strength = trim($m[1]);
    }
    // Generic = composition with all "(...)" removed.
    $generic = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $full) ?? $full);
    $generic = trim(preg_replace('/\s+,/', ',', $generic) ?? $generic, " ,");

    return [$generic !== '' ? $generic : null, $strength];
};

if ($fresh) {
    echo "Truncating drugs table…\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('TRUNCATE TABLE drugs');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

$fh = fopen($csvPath, 'r');
if ($fh === false) {
    fwrite(STDERR, "Could not open CSV.\n");
    exit(1);
}

$header = fgetcsv($fh); // discard header row
$insert = $pdo->prepare(
    'INSERT INTO drugs (name, generic_name, strength, form, is_active)
     VALUES (:name, :generic, :strength, :form, :active)'
);

$pdo->beginTransaction();
$count = 0;
$skipped = 0;
$batch = 0;

while (($row = fgetcsv($fh)) !== false) {
    // Columns by position: 0 id,1 name,2 price,3 Is_discontinued,
    // 4 manufacturer,5 type,6 pack_size_label,7 comp1,8 comp2
    $name = trim((string) ($row[1] ?? ''));
    if ($name === '') {
        $skipped++;
        continue;
    }
    $discontinued = strtoupper(trim((string) ($row[3] ?? 'FALSE'))) === 'TRUE';
    [$generic, $strength] = $parseComposition((string) ($row[7] ?? ''), (string) ($row[8] ?? ''));
    $form = $inferForm((string) ($row[6] ?? ''));

    $insert->execute([
        'name'     => mb_substr($name, 0, 150),
        'generic'  => $generic !== null ? mb_substr($generic, 0, 150) : null,
        'strength' => $strength !== null ? mb_substr($strength, 0, 30) : null,
        'form'     => $form,
        'active'   => $discontinued ? 0 : 1,
    ]);

    $count++;
    $batch++;
    if ($batch >= 2000) {
        $pdo->commit();
        $pdo->beginTransaction();
        $batch = 0;
        echo "  …{$count} imported\n";
    }
}

$pdo->commit();
fclose($fh);

echo "Done. Imported {$count} medicines (skipped {$skipped} blank rows).\n";
