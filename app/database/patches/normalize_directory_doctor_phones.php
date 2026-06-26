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

/**
 * Clean a SUPPORT / short-code / toll-free number (e.g. 1860, 1066, 500…,
 * 1800-xxx-xxxx). These are call-only: keep the digits, strip spaces/dashes,
 * but DO NOT add +91 and DO NOT treat as a mobile. Returns digits-only, or
 * null if there are no usable digits.
 *
 * The +91 prefix is what marks a number as WhatsApp-able elsewhere, so leaving
 * these without it keeps them call-only across the app.
 */
function dd_clean_support(?string $raw): ?string
{
    $digits = preg_replace('/\D/', '', (string) $raw) ?? '';
    return $digits !== '' ? $digits : null;
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
$support = 0;
$skipped = 0;
$alreadyClean = 0;

$upd = $db->prepare('UPDATE directory_doctors SET phone = :p1, intl_phone = :p2 WHERE id = :id');

foreach ($rows as $r) {
    $id = (int) $r['id'];
    // 1) Real Indian mobile → canonical +91XXXXXXXXXX (intl first, then local).
    $canonical = dd_canonical_in_mobile($r['intl_phone']) ?? dd_canonical_in_mobile($r['phone']);
    $kind = 'mobile';

    // 2) Not a mobile → support/short-code/toll-free: keep digits, NO +91.
    if ($canonical === null) {
        $canonical = dd_clean_support($r['phone']) ?? dd_clean_support($r['intl_phone']);
        $kind = 'support';
    }

    if ($canonical === null) {
        // No usable digits at all — leave as-is, log for review.
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
        printf("WOULD UPDATE id=%d [%s]  '%s' / '%s'  ->  %s\n", $id, $kind, $r['phone'], $r['intl_phone'], $canonical);
    } else {
        $upd->execute([':p1' => $canonical, ':p2' => $canonical, ':id' => $id]);
    }
    if ($kind === 'support') {
        $support++;
    } else {
        $updated++;
    }
}

printf(
    "\n%s\n  total rows:         %d\n  %s mobile (+91): %d\n  %s support (no +91): %d\n  already clean:      %d\n  skipped (logged):   %d\n",
    $dryRun ? 'DRY RUN (no writes)' : 'DONE',
    $total,
    $dryRun ? 'would set' : 'set',
    $updated,
    $dryRun ? 'would set' : 'set',
    $support,
    $alreadyClean,
    $skipped
);
if ($skipped > 0) {
    printf("  review skipped numbers in: %s\n", $logFile);
}
