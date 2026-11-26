<?php

declare(strict_types=1);

/**
 * One-time: move patient documents out of the public webroot (CLI only).
 *
 *   php scripts/move_public_files_to_storage.php           dry run: prints what it would do
 *   php scripts/move_public_files_to_storage.php --apply   moves files + updates the DB paths
 *
 * Old files under public/uploads/ were downloadable by anyone with the URL:
 *   rx/        → storage/prescriptions/{clinic}/legacy/   (visits.rx_pdf_path)
 *   invoices/  → storage/invoices/{clinic}/              (invoices.pdf_path)
 *   diet/      → storage/diet/{clinic}/                  (diet_plans.pdf_path)
 *   patients/  → storage/patients/{clinic}/              (patients.photo_path)
 *   slips/     → deleted (regenerated privately on each download)
 * Each DB row is pointed at the new private path, so portal downloads, the
 * clinic's own links and patient photos keep working. Files no row refers to
 * are deleted. Safe to re-run (already-moved rows are skipped).
 *
 * public/.htaccess already blocks these folders, so they are not reachable
 * even before this runs; this script is the cleanup.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}
$apply = in_array('--apply', $argv, true);
$pdo = Database::connection();

echo $apply ? "APPLYING\n" : "DRY RUN (add --apply to make changes)\n";

/**
 * @param callable(array<string,mixed>): string $dest  relative "storage/..." target for a row
 */
$move = static function (string $label, string $table, string $col, string $prefix, callable $dest) use ($pdo, $base, $apply): void {
    echo "\n== {$label} ({$table}.{$col})\n";
    try {
        $rows = $pdo->query("SELECT id, clinic_id, {$col} AS p FROM {$table} WHERE {$col} LIKE '{$prefix}%'")
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        echo "  ! skipped: " . $e->getMessage() . "\n";
        return;
    }
    $upd = $pdo->prepare("UPDATE {$table} SET {$col} = ? WHERE id = ?");
    $moved = $missing = 0;
    foreach ($rows as $r) {
        $src = $base . '/public/' . ltrim((string) $r['p'], '/');
        $rel = $dest($r);
        $dst = $base . '/' . $rel;
        if (!is_file($src)) {
            $missing++;
            echo "  #{$r['id']}: file missing — clearing path\n";
            if ($apply) {
                $pdo->prepare("UPDATE {$table} SET {$col} = NULL WHERE id = ?")->execute([(int) $r['id']]);
            }
            continue;
        }
        echo "  #{$r['id']}: {$r['p']} -> {$rel}\n";
        if (!$apply) {
            continue;
        }
        if (!is_dir(dirname($dst)) && !mkdir(dirname($dst), 0755, true) && !is_dir(dirname($dst))) {
            echo "    ! cannot create " . dirname($dst) . "\n";
            continue;
        }
        if (!rename($src, $dst)) {
            echo "    ! move failed\n";
            continue;
        }
        $upd->execute([$rel, (int) $r['id']]);
        $moved++;
    }
    echo "  rows: " . count($rows) . " | moved: {$moved} | missing: {$missing}\n";
};

$move('Prescriptions', 'visits', 'rx_pdf_path', '/uploads/rx/',
    static fn (array $r): string => 'storage/prescriptions/' . (int) $r['clinic_id'] . '/legacy/visit-' . (int) $r['id'] . '.pdf');
$move('Invoices', 'invoices', 'pdf_path', '/uploads/invoices/',
    static fn (array $r): string => 'storage/invoices/' . (int) $r['clinic_id'] . '/inv-' . (int) $r['id'] . '.pdf');
$move('Diet plans', 'diet_plans', 'pdf_path', '/uploads/diet/',
    static fn (array $r): string => 'storage/diet/' . (int) $r['clinic_id'] . '/diet-' . (int) $r['id'] . '.pdf');
$move('Patient photos', 'patients', 'photo_path', '/uploads/patients/',
    static fn (array $r): string => 'storage/patients/' . (int) $r['clinic_id'] . '/' . basename((string) $r['p']));

// Orphans: anything still left in those public folders belongs to no row.
echo "\n== Leftover public files\n";
$deleted = 0;
// Slips aren't stored in the DB (regenerated on each download), so old
// public copies are simply deleted.
foreach (['rx', 'prescriptions', 'invoices', 'diet', 'patients', 'slips'] as $dir) {
    $abs = $base . '/public/uploads/' . $dir;
    if (!is_dir($abs)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        if ($f->isFile()) {
            echo "  delete " . substr($f->getPathname(), strlen($base) + 1) . "\n";
            if ($apply && @unlink($f->getPathname())) {
                $deleted++;
            }
        } elseif ($apply) {
            @rmdir($f->getPathname());
        }
    }
}
echo "  deleted: {$deleted}\n";
