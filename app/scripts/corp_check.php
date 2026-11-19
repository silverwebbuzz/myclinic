<?php

declare(strict_types=1);

/**
 * Workforce Health health check (CLI only). Read-only: changes nothing.
 *
 *   php scripts/corp_check.php              tables / columns from every workforce SQL patch
 *   php scripts/corp_check.php <company_id> + runs every dashboard query for that company and prints any error
 *
 * Use it when a /company page shows "500 Internal Server Error".
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
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
$pdo = Database::connection();

// 1. Tables and columns each patch creates.
$tableExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
$colExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
$allOk = true;
echo "SQL patches:\n";
foreach (glob($base . '/database/patches/*workforce*.sql') ?: [] as $file) {
    $sql = (string) file_get_contents($file);
    preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z0-9_]+)`/i', $sql, $t);
    preg_match_all('/ALTER TABLE `([a-z0-9_]+)` ADD COLUMN `([a-z0-9_]+)`/i', $sql, $c);
    $missing = [];
    foreach (array_unique($t[1]) as $table) {
        $tableExists->execute(['t' => $table]);
        if ((int) $tableExists->fetchColumn() === 0) {
            $missing[] = $table;
        }
    }
    foreach ($c[1] as $i => $table) {
        $colExists->execute(['t' => $table, 'c' => $c[2][$i]]);
        if ((int) $colExists->fetchColumn() === 0) {
            $missing[] = $table . '.' . $c[2][$i];
        }
    }
    $allOk = $allOk && $missing === [];
    $n = count(array_unique($t[1])) + count($c[1]);
    echo '  ' . str_pad(basename($file), 48) . ($n === 0 ? 'data only' : ($missing === [] ? 'OK' : 'NOT RUN / incomplete, missing: ' . implode(', ', $missing))) . "\n";
}
echo $allOk ? "  All workforce tables are there.\n" : "  -> Run the missing patches in date order, then re-check.\n";

// 2. The dashboard's queries for one company.
$cid = (int) ($argv[1] ?? 0);
if ($cid <= 0) {
    echo "\nTip: php scripts/corp_check.php <company_id> also tests the dashboard queries. Companies:\n";
    foreach ($pdo->query('SELECT id, legal_name, status FROM corp_companies /* cross-company: admin */ ORDER BY id LIMIT 20')->fetchAll() as $r) {
        echo "  {$r['id']}  {$r['legal_name']} ({$r['status']})\n";
    }
    exit($allOk ? 0 : 1);
}
$st = $pdo->prepare('SELECT * FROM corp_companies /* cross-company: admin */ WHERE id = :id');
$st->execute(['id' => $cid]);
$company = $st->fetch();
if (!$company) {
    fwrite(STDERR, "No company {$cid}.\n");
    exit(1);
}
$tz = (string) ($company['timezone'] ?? 'UTC');
echo "\nDashboard queries for {$company['legal_name']}:\n";
$steps = [
    'plan status' => static fn () => \App\Services\Corporate\CorpPlanStatus::for($company),
    'country' => static fn () => \App\Support\Locale\CountryRepo::country((string) $company['country_code']),
    'trial days' => static fn () => empty($company['trial_ends_at']) ? null : \App\Support\Locale\LocalDate::daysUntil((string) $company['trial_ends_at'], $tz),
    'branches' => static fn () => count(\App\Services\Corporate\StructureService::branches($cid, true)),
    'job roles' => static fn () => count(\App\Services\Corporate\StructureService::jobRoles($cid, true)),
    'employee counts' => static fn () => \App\Services\Corporate\EmployeeService::counts($cid, null),
    'profile complete' => static fn () => \App\Services\Corporate\CompanyProfileService::isComplete($company),
    'documents' => static fn () => count(\App\Services\Corporate\CompanyDocumentService::list($cid)),
    'screening counts' => static fn () => \App\Services\Corporate\ScreeningCaseService::counts($cid, null, \App\Support\Locale\LocalDate::today($tz)),
];
$failed = 0;
foreach ($steps as $label => $fn) {
    try {
        $out = $fn();
        echo '  ' . str_pad($label, 18) . 'OK ' . mb_substr((string) json_encode($out), 0, 90) . "\n";
    } catch (\Throwable $e) {
        $failed++;
        echo '  ' . str_pad($label, 18) . 'FAILED: ' . get_class($e) . ': ' . $e->getMessage() . "\n"
            . '      at ' . str_replace($base . '/', '', $e->getFile()) . ':' . $e->getLine() . "\n";
    }
}
echo $failed === 0 ? "All dashboard queries work. If the page still fails, send the PHP error log line.\n" : "Send me the FAILED lines above.\n";
exit($failed === 0 && $allOk ? 0 : 1);
