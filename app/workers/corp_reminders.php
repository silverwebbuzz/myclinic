<?php

declare(strict_types=1);

// Cron: hourly — php workers/corp_reminders.php [--dry-run] [--include-demo] [company_id]
// Workforce Health reminders (design §6, R3–R5): each company is handled once its
// local clock passes 08:00; corp_reminder_log stops anything going twice, so
// running every hour (or re-running by hand) is safe.
//   --dry-run       print who would get what; send and log nothing
//   --include-demo  also demo companies (only with --dry-run: demo emails are .example)
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\Corporate\CorpPlanStatus;
use App\Services\Corporate\ReminderService;
use App\Support\ClinicTime;
use App\Support\Locale\LocalDate;
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

$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$demo = in_array('--include-demo', $args, true) && $dry;
$only = 0;
foreach ($args as $a) {
    if (ctype_digit($a)) {
        $only = (int) $a;
    }
}

$sql = "SELECT * FROM corp_companies /* cross-company: admin */ WHERE status = 'active'" . ($demo ? '' : ' AND is_demo = 0') . ($only > 0 ? ' AND id = ' . $only : '') . ' ORDER BY id';
$companies = Database::connection()->query($sql)->fetchAll();
$total = 0;
foreach ($companies as $c) {
    $tz = LocalDate::isValidZone((string) $c['timezone']) ? (string) $c['timezone'] : 'UTC';
    $local = new DateTimeImmutable('now', new DateTimeZone($tz));
    if ((int) $local->format('G') < ReminderService::SEND_FROM_HOUR && !$dry) {
        continue;   // not 08:00 there yet
    }
    if (CorpPlanStatus::for($c)['expired']) {
        continue;   // trial / plan ended: no reminders
    }
    try {
        $sent = ReminderService::run((int) $c['id'], $local->format('Y-m-d'), $dry);
        $n = array_sum($sent);
        $total += $n;
        if ($n > 0 || $dry) {
            echo sprintf("company %d (%s): %s\n", $c['id'], $c['legal_name'], implode(', ', array_map(static fn ($k, $v) => "{$k} {$v}", array_keys($sent), $sent)));
        }
    } catch (Throwable $e) {
        error_log('[corp_reminders] company ' . $c['id'] . ': ' . $e->getMessage());
        echo "company {$c['id']}: FAILED ({$e->getMessage()})\n";
    }
}
echo ($dry ? 'Dry run: ' : '') . "{$total} emails across " . count($companies) . " companies\n";
