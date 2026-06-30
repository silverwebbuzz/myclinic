<?php
/**
 * Backfill prescriptions.tapering_steps JSON — add dose_amount to each step.
 *
 * tapering_steps is a JSON array on prescriptions (and prescription_template_items).
 * Each step object is now:
 *   { "days": int, "preset": string, "food": string, "dose_amount": float|null }
 *
 * Existing steps without dose_amount inherit the parent row's dose_amount.
 *
 * Usage:
 *   php app/database/patches/2026_06_30_tapering_step_dose_amount.php
 *   php app/database/patches/2026_06_30_tapering_step_dose_amount.php --dry
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\PrescriptionService;
use Dotenv\Dotenv;

$base = dirname(__DIR__, 2);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}

$dryRun = in_array('--dry', $argv, true);

if (!Database::ping()) {
    fwrite(STDERR, "Database unavailable.\n");
    exit(1);
}

$pdo = Database::connection();
$tables = ['prescriptions', 'prescription_template_items'];
$updated = 0;
$skipped = 0;

foreach ($tables as $table) {
    try {
        $pdo->query("SELECT tapering_steps, dose_amount FROM {$table} LIMIT 1");
    } catch (\Throwable $e) {
        echo "Skip {$table} — tapering_steps column not present.\n";
        continue;
    }

    $stmt = $pdo->query(
        "SELECT id, tapering_steps, dose_amount FROM {$table}
          WHERE tapering_steps IS NOT NULL AND tapering_steps != '' AND tapering_steps != '[]'"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $row) {
        $raw = json_decode((string) $row['tapering_steps'], true);
        if (!is_array($raw) || $raw === []) {
            $skipped++;
            continue;
        }

        $lineDose = isset($row['dose_amount']) && $row['dose_amount'] !== ''
            ? (float) $row['dose_amount'] : null;
        $normalized = PrescriptionService::normalizeTaperingStepsForSave($raw, $lineDose);

        if ($normalized === []) {
            $skipped++;
            continue;
        }

        $newJson = json_encode($normalized, JSON_THROW_ON_ERROR);
        if ($newJson === (string) $row['tapering_steps']) {
            $skipped++;
            continue;
        }

        echo ($dryRun ? '[dry] ' : '') . "{$table}#{$row['id']}: updated tapering_steps\n";
        if (!$dryRun) {
            $upd = $pdo->prepare("UPDATE {$table} SET tapering_steps = :ts WHERE id = :id");
            $upd->execute([':ts' => $newJson, ':id' => $row['id']]);
        }
        $updated++;
    }
}

echo "Done. Updated: {$updated}, skipped: {$skipped}" . ($dryRun ? ' (dry run)' : '') . "\n";
