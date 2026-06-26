<?php
/**
 * One-time backfill: normalize directory_doctors.phone + intl_phone to the
 * canonical Indian format  +91XXXXXXXXXX  (+91 followed by exactly 10 digits,
 * no spaces / dashes / parentheses / leading 0).
 *
 * Rules (same as ecp_normalize_phone, plus an India-mobile validity check):
 *   - strip spaces, dashes, parentheses
 *   - leading 0 + 10 digits      → drop the 0, prepend +91
 *   - bare 10 digits             → prepend +91
 *   - 12 digits starting with 91 → prepend +
 *   - already +91XXXXXXXXXX       → keep
 *   - a valid mobile's 10 digits must start 6/7/8/9
 *
 * Source preference: intl_phone (already carries the country code) first,
 * falling back to phone. The clean value is written to BOTH columns so every
 * reader gets a correct number regardless of which column it uses.
 *
 * Numbers that CANNOT be cleaned to a valid +91 mobile are LEFT AS-IS and
 * written to storage/logs/phone-normalize-skipped.log for manual review.
 *
 * Safe to re-run (idempotent — already-clean rows are detected and skipped).
 *
 * Usage:
 *   php app/database/patches/normalize_directory_doctor_phones.php          # apply
 *   php app/database/patches/normalize_directory_doctor_phones.php --dry    # preview only
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../partials/db.php';   // ecp_db(), ecp_env()
require_once __DIR__ . '/../../../partials/sms.php';  // ecp_normalize_phone()

$dryRun = in_array('--dry', $argv, true);

/**
 * Return a canonical +91XXXXXXXXXX for a valid Indian mobile, or null if the
 * input can't be cleaned to one.
 */
function dd_canonical_in_mobile(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $norm = ecp_normalize_phone($raw); // e.g. +919876543210
    if ($norm === '' || !str_starts_with($norm, '+91')) {
        return null; // non-India or unparseable
    }
    $local = substr($norm, 3); // the 10 national digits
    // Must be exactly 10 digits and a real mobile prefix (6-9).
    if (!preg_match('/^[6-9]\d{9}$/', $local)) {
        return null;
    }
    return '+91' . $local;
}

$db = ecp_db();
if (!$db) {
    fwrite(STDERR, "DB unavailable — check app/.env\n");
    exit(1);
}

$logDir = __DIR__ . '/../../../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/phone-normalize-skipped.log';

$rows = $db->query('SELECT id, phone, intl_phone FROM directory_doctors')->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);
$updated = 0;
$skipped = 0;
$alreadyClean = 0;

$upd = $db->prepare('UPDATE directory_doctors SET phone = :p1, intl_phone = :p2 WHERE id = :id');

foreach ($rows as $r) {
    $id = (int) $r['id'];
    // Prefer intl_phone (carries the country code), fall back to phone.
    $canonical = dd_canonical_in_mobile($r['intl_phone']) ?? dd_canonical_in_mobile($r['phone']);

    if ($canonical === null) {
        // Couldn't clean either column — leave as-is, log for review.
        $skipped++;
        $line = sprintf(
            "[%s] id=%d phone=%s intl_phone=%s\n",
            date('Y-m-d H:i:s'), $id,
            json_encode($r['phone']), json_encode($r['intl_phone'])
        );
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        continue;
    }

    if ($r['phone'] === $canonical && $r['intl_phone'] === $canonical) {
        $alreadyClean++;
        continue;
    }

    if ($dryRun) {
        printf("WOULD UPDATE id=%d  '%s' / '%s'  ->  %s\n", $id, $r['phone'], $r['intl_phone'], $canonical);
    } else {
        $upd->execute([':p1' => $canonical, ':p2' => $canonical, ':id' => $id]);
    }
    $updated++;
}

printf(
    "\n%s\n  total rows:     %d\n  %s: %d\n  already clean:  %d\n  skipped (logged): %d\n",
    $dryRun ? 'DRY RUN (no writes)' : 'DONE',
    $total,
    $dryRun ? 'would update' : 'updated',
    $updated,
    $alreadyClean,
    $skipped
);
if ($skipped > 0) {
    printf("  review skipped numbers in: %s\n", $logFile);
}
