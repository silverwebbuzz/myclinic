<?php
// =====================================================================
// fetch_doctor/build_clinic_siblings.php
// Fills directory_doctors.sibling_ids ("doctors at this clinic") and
// refreshes directory_doctors.search_text, city by city. See
// _clinic_siblings.php for how, and the 2026_09_29_*.sql files for why.
//
// Run the two 2026_09_29_*.sql files first. Then, either:
//   Browser:  https://eclinicpro.com/fetch_doctor/build_clinic_siblings.php
//             → "Start full rebuild". Works in ~20s chunks and reloads
//             itself until done; close the tab and come back to resume.
//   CLI:      php fetch_doctor/build_clinic_siblings.php            (resume, or start)
//             php fetch_doctor/build_clinic_siblings.php --restart  (start over)
//             php fetch_doctor/build_clinic_siblings.php --city="Ahmedabad"
//
// Resumable: progress is kept in jobs/clinic_siblings_state.json after each
// city. Safe to re-run any time: every city is recomputed from scratch and
// only changed rows are written. When a full run finishes, cached profile
// pages (storage/page_cache/prof_*.html) are deleted, since they embed the
// list.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_clinic_siblings.php';

@set_time_limit(0);

$isCli     = PHP_SAPI === 'cli';
$stateFile = __DIR__ . '/jobs/clinic_siblings_state.json';
$lockFile  = __DIR__ . '/jobs/clinic_siblings.lock';
$budget    = 20;   // seconds of work per browser request (CLI: unlimited)

function sib_out(string $line): void {
    echo PHP_SAPI === 'cli' ? $line . "\n" : htmlspecialchars($line) . "\n";
    @flush();
}

function sib_load_state(string $file): ?array {
    if (!is_file($file)) return null;
    $s = json_decode((string) file_get_contents($file), true);
    return is_array($s) && isset($s['cities'], $s['next']) ? $s : null;
}

function sib_save_state(string $file, array $s): void {
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $file);
}

function sib_new_state(PDO $pdo): array {
    return [
        'started_at'  => date('Y-m-d H:i:s'),
        'finished_at' => null,
        'cities'      => fd_sib_cities($pdo),
        'next'        => 0,
        'totals'      => ['rows' => 0, 'clinics' => 0, 'sibling_updates' => 0, 'sql_checks' => 0, 'search_updates' => 0],
    ];
}

// ---------- args ----------
$opts = $isCli ? getopt('', ['restart', 'city:']) : [];
$action = $isCli ? 'run' : (string) ($_GET['action'] ?? '');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
}

$pdo = ecp_db();
$fail = null;
if (!$pdo) {
    $fail = 'DB connection failed. Check ../app/.env (DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD).';
} elseif (!isset(fd_sib_columns($pdo)['sibling_ids'])) {
    $fail = 'Column directory_doctors.sibling_ids is missing. Run fetch_doctor/2026_09_29_clinic_sibling_ids.sql first.';
}
if (!is_dir(__DIR__ . '/jobs')) @mkdir(__DIR__ . '/jobs', 0755, true);
if ($fail === null && !is_writable(__DIR__ . '/jobs')) {
    $fail = 'fetch_doctor/jobs/ is not writable (progress is saved there). chmod 0755 it.';
}
$ready = $fail === null;   // setup OK (a later city error still leaves Resume available)

// Browser: buffer the log, then print the page once we know whether to reload.
if (!$isCli) ob_start();

$continue = false;   // browser: reload for another chunk?

if ($fail !== null) {
    sib_out('❌ ' . $fail);
} elseif ($isCli && isset($opts['city'])) {
    // ---------- one city, no state ----------
    $city = (string) $opts['city'];
    $t = microtime(true);
    $s = fd_sib_rebuild_city($pdo, $city);
    sib_out(sprintf('%s — %d rows, %d clinics, %d sibling updates, %d search_text updates (%.1fs)',
        $city, $s['rows'], $s['clinics'], $s['sibling_updates'], $s['search_updates'], microtime(true) - $t));
    sib_out('Purged ' . fd_sib_purge_profile_cache() . ' cached profile pages.');
} elseif ($action === '') {
    // ---------- browser status page ----------
    $state = sib_load_state($stateFile);
    if ($state === null) {
        sib_out('No rebuild has been run yet.');
    } else {
        sib_out(sprintf('Last run started %s — %d / %d cities done%s.',
            $state['started_at'], $state['next'], count($state['cities']),
            $state['finished_at'] ? ', finished ' . $state['finished_at'] : ' (unfinished)'));
        sib_out(json_encode($state['totals']));
    }
} else {
    // ---------- full run (CLI, or browser ?action=start / ?action=run) ----------
    $lock = fopen($lockFile, 'c');
    if (!$lock) {
        $fail = 'Could not open ' . $lockFile;
        sib_out('❌ ' . $fail);
    } elseif (!flock($lock, LOCK_EX | LOCK_NB)) {
        sib_out('Another rebuild is running right now. Try again in a minute.');
        $continue = !$isCli;
    } else {
        $state = sib_load_state($stateFile);
        $restart = $isCli ? isset($opts['restart']) : $action === 'start';
        if ($restart || $state === null || ($isCli && $state['finished_at'] !== null)) {
            $state = sib_new_state($pdo);
            sib_save_state($stateFile, $state);
            sib_out('Started a new rebuild: ' . count($state['cities']) . ' cities.');
        }

        $t0 = microtime(true);
        $total = count($state['cities']);
        while ($state['finished_at'] === null && $state['next'] < $total) {
            if (!$isCli && microtime(true) - $t0 > $budget) {
                $continue = true;
                break;
            }
            $city = $state['cities'][$state['next']];
            $t = microtime(true);
            try {
                $s = fd_sib_rebuild_city($pdo, $city);
            } catch (Throwable $e) {
                // Stop here; the next run resumes at this same city.
                $fail = $city . ': ' . $e->getMessage();
                sib_out('❌ ' . $fail);
                $continue = false;
                break;
            }
            foreach ($s as $k => $v) $state['totals'][$k] += $v;
            $state['next']++;
            sib_save_state($stateFile, $state);
            sib_out(sprintf('[%d/%d] %s — %d rows, %d clinics, %d sibling updates, %d search_text updates (%.1fs)',
                $state['next'], $total, $city, $s['rows'], $s['clinics'], $s['sibling_updates'], $s['search_updates'],
                microtime(true) - $t));
        }

        if ($state['finished_at'] === null && $state['next'] >= $total) {
            $state['finished_at'] = date('Y-m-d H:i:s');
            sib_save_state($stateFile, $state);
            sib_out('');
            sib_out('✅ Done. ' . json_encode($state['totals']));
            sib_out('Purged ' . fd_sib_purge_profile_cache() . ' cached profile pages.');
        } elseif ($state['finished_at'] !== null && !$continue) {
            sib_out('✅ Already finished at ' . $state['finished_at'] . '. Use "Start full rebuild" to run again.');
        } elseif ($fail === null) {
            sib_out('… continuing (' . $state['next'] . '/' . $total . ' cities done)');
        }
        flock($lock, LOCK_UN);
    }
}

if (!$isCli) {
    $log = (string) ob_get_clean();
    $state = $ready ? sib_load_state($stateFile) : null;
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<?php if ($continue): ?><meta http-equiv="refresh" content="1;url=?action=run"><?php endif; ?>
<title>Build clinic siblings — eClinicPro</title>
<style>
body { font: 14px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
.wrap { max-width: 880px; margin: 0 auto; padding: 32px 20px 80px; }
pre { background: #fff; border: 1px solid rgba(0,0,0,0.08); border-radius: 8px; padding: 14px; white-space: pre-wrap; font-size: 12px; }
.btn { display: inline-block; background: #0f172a; color: #fff; padding: 9px 16px; border-radius: 8px; text-decoration: none; margin-right: 8px; }
.btn:hover { background: #0F9B6E; }
</style>
</head>
<body>
<div class="wrap">
<h1>Build clinic siblings + search text</h1>
<pre><?= $log ?></pre>
<?php if (!$continue && $ready): ?>
    <?php if ($state !== null && $state['finished_at'] === null): ?><a class="btn" href="?action=run">Resume</a><?php endif; ?>
    <a class="btn" href="?action=start">Start full rebuild</a>
<?php endif; ?>
<p><a href="index.php" style="color:#0F9B6E;">← Back to fetcher</a></p>
</div>
</body>
</html>
<?php
}

if ($isCli && $fail !== null) exit(1);
