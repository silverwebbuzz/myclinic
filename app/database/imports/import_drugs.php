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

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Database;

$base = dirname(__DIR__, 2);       // the app/ dir (has vendor + .env)
$repoRoot = dirname($base);        // repo root (one level up from app/)
Application::boot($base);

$fresh = in_array('--fresh', $argv, true);

// First non-flag argument is the CSV path (optional).
$argCsv = null;
foreach (array_slice($argv, 1) as $a) {
    if ($a !== '' && $a[0] !== '-') { $argCsv = $a; break; }
}

// Resolve the CSV from the most likely locations so it works no matter
// which directory you run the script from.
$csvName = 'document/A_Z_medicines_dataset_of_India.csv';
$candidates = [];
if ($argCsv !== null) {
    $candidates[] = str_starts_with($argCsv, '/') ? $argCsv : getcwd() . '/' . $argCsv; // as given (abs or relative to cwd)
    $candidates[] = $argCsv;                          // raw (relative to cwd already)
}
$candidates[] = $repoRoot . '/' . $csvName;           // repo-root/document/...  (your layout)
$candidates[] = $base . '/' . $csvName;               // app/document/...
$candidates[] = getcwd() . '/' . $csvName;            // cwd/document/...

$csvPath = null;
foreach ($candidates as $c) {
    if ($c !== null && is_file($c)) { $csvPath = $c; break; }
}

if ($csvPath === null) {
    fwrite(STDERR, "CSV not found. Tried:\n  - " . implode("\n  - ", array_filter($candidates)) . "\n");
    fwrite(STDERR, "Pass an explicit path, e.g.:\n  php scripts/import_drugs.php /full/path/to.csv --fresh\n");
    exit(1);
}
echo "Using CSV: {$csvPath}\n";

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
    'INSERT INTO drugs
        (name, generic_name, strength, composition, form, pack_size,
         medicine_type, manufacturer, mrp, source_ref, is_active)
     VALUES
        (:name, :generic, :strength, :composition, :form, :pack_size,
         :medicine_type, :manufacturer, :mrp, :source_ref, :active)'
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
    $comp1 = trim((string) ($row[7] ?? ''));
    $comp2 = trim((string) ($row[8] ?? ''));
    [$generic, $strength] = $parseComposition($comp1, $comp2);
    $form = $inferForm((string) ($row[6] ?? ''));
    $composition = trim($comp1 . ($comp2 !== '' ? ', ' . $comp2 : ''));
    $composition = preg_replace('/\s+/', ' ', $composition) ?? $composition;
    $price = (string) ($row[2] ?? '');
    $mrp = is_numeric($price) ? (float) $price : null;

    $insert->execute([
        'name'          => mb_substr($name, 0, 150),
        'generic'       => $generic !== null ? mb_substr($generic, 0, 150) : null,
        'strength'      => $strength !== null ? mb_substr($strength, 0, 30) : null,
        'composition'   => $composition !== '' ? mb_substr($composition, 0, 255) : null,
        'form'          => $form,
        'pack_size'     => mb_substr(trim((string) ($row[6] ?? '')), 0, 80) ?: null,
        'medicine_type' => mb_substr(trim((string) ($row[5] ?? '')), 0, 20) ?: null,
        'manufacturer'  => mb_substr(trim((string) ($row[4] ?? '')), 0, 120) ?: null,
        'mrp'           => $mrp,
        'source_ref'    => mb_substr(trim((string) ($row[0] ?? '')), 0, 40) ?: null,
        'active'        => $discontinued ? 0 : 1,
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
