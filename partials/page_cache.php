<?php
// =====================================================================
// partials/page_cache.php — full-page disk cache for anonymous visitors.
//
// Same mechanism as /find-a-doctor's cache (see find-a-doctor.php): the
// first logged-out visitor's rendered HTML is saved to
// storage/page_cache/<key>.html and everyone after gets a file read with
// no database work, until the file expires.
//
// Only safe for pages that are byte-identical for every logged-out visitor:
// no CSRF token, no per-visitor text. The page decides that at the end of
// the render via ecp_page_cache_end($store).
//
// Usage:
//   ecp_page_cache_start('prof_' . sha1(...), 86400, ['city','entity_type','slug']);
//   ... render ...
//   ecp_page_cache_end($isSafeToStore);
// =====================================================================

declare(strict_types=1);

const ECP_PAGE_CACHE_DIR = __DIR__ . '/../storage/page_cache';

/** Key/file of the page being captured in this request (null = not capturing). */
$GLOBALS['ecp_page_cache_file'] = null;

/**
 * True when this request may be served from / written to the shared cache:
 * GET, logged-out, no store-preview cookie (it adds a nav link), and no query
 * params other than $allowedGetKeys (so ?ref=, ?utm_… etc. always render live).
 *
 * @param list<string> $allowedGetKeys
 */
function ecp_page_cache_eligible(array $allowedGetKeys): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && empty($_COOKIE['ecp_pid'])
        && empty($_COOKIE['ecp_store_preview'])
        && array_diff(array_keys($_GET), $allowedGetKeys) === [];
}

/**
 * Serve a fresh cached copy and exit, or start capturing this render.
 * Files also expire at midnight when $sameDayOnly (pages that show "today"/dates).
 *
 * @param list<string> $allowedGetKeys
 */
function ecp_page_cache_start(string $key, int $ttl, array $allowedGetKeys, bool $sameDayOnly = false): void
{
    if (!preg_match('/^[a-z0-9_]{1,80}$/', $key) || !ecp_page_cache_eligible($allowedGetKeys)) {
        return;
    }
    $file = ECP_PAGE_CACHE_DIR . '/' . $key . '.html';
    $mtime = is_file($file) ? (int) filemtime($file) : 0;
    if ($mtime > 0 && time() - $mtime < $ttl && (!$sameDayOnly || date('Y-m-d', $mtime) === date('Y-m-d'))) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: public, max-age=300');
        header('X-Page-Cache: hit');
        readfile($file);
        exit;
    }
    $GLOBALS['ecp_page_cache_file'] = $file;
    header('X-Page-Cache: miss');
    ob_start();
}

/** Send the captured page to the visitor and, when $store, save it for the next ones. */
function ecp_page_cache_end(bool $store): void
{
    $file = $GLOBALS['ecp_page_cache_file'] ?? null;
    if ($file === null || ob_get_level() === 0) {
        return;
    }
    $GLOBALS['ecp_page_cache_file'] = null;
    $html = (string) ob_get_clean();
    echo $html;
    // Never cache an error page or an empty render.
    if (!$store || http_response_code() !== 200 || strlen($html) < 1000) {
        return;
    }
    if (!is_dir(ECP_PAGE_CACHE_DIR)) {
        @mkdir(ECP_PAGE_CACHE_DIR, 0755, true);
    }
    if (is_dir(ECP_PAGE_CACHE_DIR) && is_writable(ECP_PAGE_CACHE_DIR)) {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $html) !== false) {
            @rename($tmp, $file);   // atomic swap: readers never see a half-written file
        }
    }
}

/** Delete cached pages whose key starts with $prefix (e.g. 'prof_'). Returns files removed. */
function ecp_page_cache_purge(string $prefix): int
{
    if (!preg_match('/^[a-z0-9_]{1,40}$/', $prefix)) {
        return 0;
    }
    $n = 0;
    foreach (glob(ECP_PAGE_CACHE_DIR . '/' . $prefix . '*.html') ?: [] as $f) {
        if (@unlink($f)) {
            $n++;
        }
    }

    return $n;
}
