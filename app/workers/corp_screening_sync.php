<?php

declare(strict_types=1);

// Cron: daily 02:45 — php workers/corp_screening_sync.php [company_id]
// Workforce Health safety net: re-works every active company's screening due
// dates (ScreeningEngine is idempotent, so a run with no changes writes nothing),
// after applying clinic-side booking changes (cancelled / no-show / attended / moved).
// The engine also runs straight after each employee / programme change.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\Corporate\ScreeningEngine;
use App\Support\ClinicTime;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$base = dirname(__DIR__);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}
ClinicTime::bootstrap();

$only = isset($argv[1]) ? (int) $argv[1] : 0;
$ids = array_map('intval', Database::connection()
    ->query("SELECT id FROM corp_companies /* cross-company: admin */ WHERE status = 'active'" . ($only > 0 ? ' AND id = ' . $only : '') . ' ORDER BY id')
    ->fetchAll(PDO::FETCH_COLUMN));

$started = microtime(true);
foreach ($ids as $id) {
    try {
        // Bookings first: a clinic-side cancel / no-show puts checks back to "not booked" before the due dates are re-worked.
        try {
            $b = \App\Services\Corporate\BookingService::syncCompany($id);
            if (array_sum($b) > 0) {
                echo sprintf("company %d bookings: %d cancelled by clinic, %d no-show, %d attended\n", $id, $b['cancelled'], $b['no_show'], $b['attended']);
            }
        } catch (Throwable $e) {
            error_log('[corp_screening_sync] bookings ' . $id . ': ' . $e->getMessage());   // 1C patch not run yet
        }
        $r = ScreeningEngine::syncCompany($id);
        echo sprintf("company %d: %d people, %d created, %d re-dated, %d cancelled\n", $id, $r['employees'], $r['created'], $r['redated'], $r['cancelled']);
    } catch (Throwable $e) {
        error_log('[corp_screening_sync] company ' . $id . ': ' . $e->getMessage());
        echo "company {$id}: FAILED ({$e->getMessage()})\n";
    }
}
echo sprintf("Done: %d companies in %.1fs\n", count($ids), microtime(true) - $started);
