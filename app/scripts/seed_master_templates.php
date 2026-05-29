<?php

declare(strict_types=1);

/**
 * Seed MASTER prescription templates (global, system-provided) into
 * prescription_templates_master + prescription_template_master_items.
 *
 * These are starter "condition packs" per specialty — a doctor picks one
 * and 4-7 medicines pre-fill with dose/frequency/duration. Doctors then
 * tweak and optionally save their own (per-clinic) templates on top.
 *
 * HOW MATCHING WORKS (important):
 *   - Clinical content (below, $CONTENT) lists medicines by GENERIC NAME
 *     using Indian/British pharma spelling (Paracetamol, Amoxycillin,
 *     Salbutamol — matching the imported A-Z India catalog).
 *   - For each medicine the seeder finds the best matching row in `drugs`
 *     (prefers a common, low-strength oral form) and links its drug_id.
 *   - Specialty slugs are resolved against the live specialty_master so we
 *     don't hard-code slugs that might differ (derma vs dermatology).
 *   - ANYTHING that can't be matched is reported at the end and SKIPPED
 *     (never inserts an empty/dangling item).
 *
 * Usage (from app/):
 *   php scripts/seed_master_templates.php            # seed (idempotent-ish)
 *   php scripts/seed_master_templates.php --fresh    # wipe master templates first
 *   php scripts/seed_master_templates.php --dry      # match-report only, no writes
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Database;

$base = dirname(__DIR__);
Application::boot($base);
$pdo = Database::connection();

$fresh = in_array('--fresh', $argv, true);
$dry   = in_array('--dry', $argv, true);

require __DIR__ . '/master_templates_content.php';   // defines $CONTENT
/** @var array<string, array{label:string, conditions: array<int, array{name:string, desc?:string, mode?:string, items: array<int, array{generic:string, dose_unit?:string, dose_amount?:float, freq?:string, days?:int, food?:string, instructions?:string}>}>}> $CONTENT */

// ---- Resolve specialty slugs from the live specialty_master ----
$specRows = $pdo->query('SELECT slug, label FROM specialty_master')->fetchAll(PDO::FETCH_ASSOC);

// --list-specialties: print every real slug+label and exit (so content
// keys can be matched to the authoritative catalog — never guess slugs).
if (in_array('--list-specialties', $argv, true)) {
    echo "Specialties in specialty_master (slug => label):\n";
    foreach ($specRows as $r) {
        echo "  {$r['slug']}  =>  {$r['label']}\n";
    }
    exit(0);
}

$slugByKey = [];          // normalized label/slug => actual slug
foreach ($specRows as $r) {
    $slugByKey[strtolower($r['slug'])] = $r['slug'];
    $slugByKey[strtolower($r['label'])] = $r['slug'];
}
$resolveSpecialty = static function (string $key, string $label) use ($slugByKey): ?string {
    foreach ([$key, $label] as $cand) {
        $c = strtolower(trim($cand));
        if (isset($slugByKey[$c])) return $slugByKey[$c];
    }
    // loose: contains match (e.g. "dermatology" vs "dermatologist")
    foreach ($slugByKey as $norm => $slug) {
        if (str_contains($norm, strtolower($key)) || str_contains(strtolower($key), $norm)) return $slug;
    }
    return null;
};

// ---- Drug matcher: generic name -> a real drugs.id ----
// Prefer an active, oral, common-strength row. Cache to keep it fast.
$drugCache = [];
$findDrug = static function (string $generic) use ($pdo, &$drugCache): ?array {
    $key = strtolower(trim($generic));
    if (array_key_exists($key, $drugCache)) return $drugCache[$key];

    // 1) generic_name starts with the term (mono-drug preferred), active first,
    //    cheapest/most common via shortest name (avoids combo brands).
    $stmt = $pdo->prepare(
        "SELECT id, name, generic_name, strength, form
           FROM drugs
          WHERE is_active = 1 AND generic_name LIKE :p
          ORDER BY CHAR_LENGTH(generic_name) ASC, CHAR_LENGTH(name) ASC
          LIMIT 1"
    );
    $stmt->execute([':p' => $generic . '%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // 2) fallback: generic_name contains the term anywhere.
    if (!$row) {
        $stmt = $pdo->prepare(
            "SELECT id, name, generic_name, strength, form
               FROM drugs
              WHERE is_active = 1 AND generic_name LIKE :p
              ORDER BY CHAR_LENGTH(generic_name) ASC LIMIT 1"
        );
        $stmt->execute([':p' => '%' . $generic . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // 3) last resort: brand name starts with the term.
    if (!$row) {
        $stmt = $pdo->prepare(
            "SELECT id, name, generic_name, strength, form
               FROM drugs WHERE is_active = 1 AND name LIKE :p
               ORDER BY CHAR_LENGTH(name) ASC LIMIT 1"
        );
        $stmt->execute([':p' => $generic . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $drugCache[$key] = $row;
    return $row;
};

if ($fresh && !$dry) {
    echo "Wiping existing master templates…\n";
    $pdo->exec('DELETE FROM prescription_template_master_items');
    $pdo->exec('DELETE FROM prescription_templates_master');
}

$insTpl = $pdo->prepare(
    'INSERT INTO prescription_templates_master (specialty, name, description, mode, is_active)
     VALUES (:specialty, :name, :description, :mode, 1)'
);
$insItem = $pdo->prepare(
    'INSERT INTO prescription_template_master_items
        (template_id, mode, drug_id, match_name, dose_unit, dose_amount,
         frequency_preset, duration_days, food_timing, instructions, sort_order)
     VALUES
        (:template_id, :mode, :drug_id, :match_name, :dose_unit, :dose_amount,
         :frequency_preset, :duration_days, :food_timing, :instructions, :sort_order)'
);

$tplCount = 0; $itemCount = 0; $unmatched = []; $missingSpecs = [];

foreach ($CONTENT as $specKey => $spec) {
    $slug = $resolveSpecialty($specKey, $spec['label'] ?? $specKey);
    if ($slug === null) {
        $missingSpecs[] = $specKey . ' (' . ($spec['label'] ?? '') . ')';
        continue;
    }

    foreach ($spec['conditions'] as $cond) {
        $mode = $cond['mode'] ?? 'allopathic';

        // Resolve all items first; skip the whole template only if NOTHING matches.
        $resolved = [];
        foreach ($cond['items'] as $sort => $it) {
            $drug = $findDrug($it['generic']);
            if (!$drug) {
                $unmatched[$it['generic']] = ($unmatched[$it['generic']] ?? 0) + 1;
                continue;
            }
            $resolved[] = [$drug, $it, $sort];
        }
        if ($resolved === []) {
            continue;
        }

        if ($dry) { $tplCount++; $itemCount += count($resolved); continue; }

        $insTpl->execute([
            ':specialty' => $slug,
            ':name' => $cond['name'],
            ':description' => $cond['desc'] ?? null,
            ':mode' => $mode,
        ]);
        $tid = (int) $pdo->lastInsertId();
        $tplCount++;

        foreach ($resolved as $i => [$drug, $it, $sort]) {
            $insItem->execute([
                ':template_id' => $tid,
                ':mode' => $mode,
                ':drug_id' => $drug['id'],
                ':match_name' => $it['generic'],
                ':dose_unit' => $it['dose_unit'] ?? null,
                ':dose_amount' => $it['dose_amount'] ?? null,
                ':frequency_preset' => $it['freq'] ?? null,
                ':duration_days' => $it['days'] ?? null,
                ':food_timing' => $it['food'] ?? 'any',
                ':instructions' => $it['instructions'] ?? null,
                ':sort_order' => $i,
            ]);
            $itemCount++;
        }
    }
}

echo ($dry ? "[DRY RUN] " : "") . "Templates: {$tplCount}, items: {$itemCount}\n";
if ($missingSpecs) {
    echo "\nSpecialties in content but NOT found in specialty_master (skipped):\n  - "
        . implode("\n  - ", $missingSpecs) . "\n";
}
if ($unmatched) {
    arsort($unmatched);
    echo "\nMedicines that could NOT be matched in `drugs` (skipped):\n";
    foreach ($unmatched as $g => $n) {
        echo "  - {$g} (used in {$n} template item" . ($n === 1 ? '' : 's') . ")\n";
    }
    echo "\nFix: adjust the generic spelling in master_templates_content.php to match the catalog, then re-run --fresh.\n";
}
