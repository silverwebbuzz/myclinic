<?php
/**
 * Enrich serviceable-pincodes.csv → serviceable-pincodes.json
 *
 * Reads the bare pincode list (one 6-digit pincode per line) and looks each
 * one up against the public India Post postal API to attach the official
 * District (used as "city") and State. The result is an object keyed by
 * pincode so the runtime lookup is O(1):
 *
 *   { "380001": { "city": "Ahmedabad", "state": "Gujarat" }, ... }
 *
 * Re-runnable: results are cached in serviceable-pincodes.json, so a second
 * run only fetches pincodes that are still missing or previously failed.
 *
 * Usage:  php fetch_doctor/enrich_pincodes.php
 */

declare(strict_types=1);

const CSV_PATH  = __DIR__ . '/../document/serviceable-pincodes.csv';
const JSON_PATH = __DIR__ . '/../assets/data/serviceable-pincodes.json';
const API_TMPL  = 'https://api.postalpincode.in/pincode/%s';
const SLEEP_US  = 120000; // 0.12s between calls — be polite to the free API
const SAVE_EVERY = 50;    // flush progress to disk periodically

/** Load the pincodes we need to serve. */
function load_pincodes(): array
{
    if (!is_readable(CSV_PATH)) {
        fwrite(STDERR, "CSV not found: " . CSV_PATH . "\n");
        exit(1);
    }
    $pins = [];
    foreach (file(CSV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $pin = preg_replace('/\D/', '', trim($line));
        if (preg_match('/^[1-9][0-9]{5}$/', $pin)) {
            $pins[$pin] = true; // dedupe
        }
    }
    return array_keys($pins);
}

/** Load existing JSON so we can resume without re-fetching. */
function load_existing(): array
{
    if (is_readable(JSON_PATH)) {
        $data = json_decode((string) file_get_contents(JSON_PATH), true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

function save_json(array $data): void
{
    ksort($data);
    file_put_contents(
        JSON_PATH,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

/** Query the postal API for one pincode. Returns [city, state] or null. */
function lookup(string $pin): ?array
{
    $ch = curl_init(sprintf(API_TMPL, $pin));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'eClinicPro-pincode-enrich/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) {
        return null;
    }
    $json = json_decode((string) $body, true);
    $rec  = $json[0] ?? null;
    if (!is_array($rec) || ($rec['Status'] ?? '') !== 'Success') {
        return null;
    }
    // Prefer the Head/first delivery office; District = city, State = state.
    $offices = $rec['PostOffice'] ?? [];
    $office  = $offices[0] ?? null;
    if (!is_array($office)) {
        return null;
    }
    $city  = trim((string) ($office['District'] ?? ''));
    $state = trim((string) ($office['State'] ?? ''));
    if ($city === '' || $state === '') {
        return null;
    }
    return ['city' => $city, 'state' => $state];
}

// ---- main ------------------------------------------------------------------

$pins     = load_pincodes();
$out      = load_existing();
$total    = count($pins);
$done     = 0;
$fetched  = 0;
$failed   = [];

fwrite(STDERR, "Enriching {$total} pincodes...\n");

foreach ($pins as $i => $pin) {
    $done++;
    // Skip ones we already have a good record for.
    if (isset($out[$pin]['city'], $out[$pin]['state'])) {
        continue;
    }
    $res = lookup($pin);
    if ($res === null) {
        $failed[] = $pin;
        usleep(SLEEP_US);
        continue;
    }
    $out[$pin] = $res;
    $fetched++;
    usleep(SLEEP_US);

    if ($fetched % SAVE_EVERY === 0) {
        save_json($out);
        fwrite(STDERR, sprintf("  [%d/%d] fetched=%d failed=%d\n", $done, $total, $fetched, count($failed)));
    }
}

save_json($out);

fwrite(STDERR, sprintf(
    "\nDone. total=%d, in-json=%d, newly-fetched=%d, failed=%d\n",
    $total, count($out), $fetched, count($failed)
));
if ($failed) {
    fwrite(STDERR, "Failed pincodes (re-run to retry): " . implode(',', array_slice($failed, 0, 40))
        . (count($failed) > 40 ? '…' : '') . "\n");
}
