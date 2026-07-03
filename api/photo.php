<?php
declare(strict_types=1);

require_once __DIR__ . '/../partials/db.php';

$ref = (string) ($_GET['ref'] ?? '');
$width = max(80, min(1600, (int) ($_GET['w'] ?? 400)));

if ($ref === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $ref)) {
    http_response_code(404);
    exit;
}

// ---- 30-day disk cache (ToS-aligned: temporary, auto-refreshed) ------------
// Google Places ToS allows only TEMPORARY caching of photo content, not
// permanent storage. We cache each (ref,width) to disk and re-fetch from Google
// once the file is older than 30 days. This kills per-view API cost (one Google
// call per photo per month, instead of per request) while staying within terms.
const ECP_PHOTO_CACHE_TTL = 2592000; // 30 days
$cacheDir = __DIR__ . '/../storage/photo_cache';
$cacheKey = sha1($ref . '|' . $width);
$cacheFile = $cacheDir . '/' . $cacheKey;
$cacheMeta = $cacheFile . '.type'; // stores the content-type

// Serve from cache if fresh.
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < ECP_PHOTO_CACHE_TTL) {
    $ct = is_file($cacheMeta) ? trim((string) file_get_contents($cacheMeta)) : 'image/jpeg';
    header('Content-Type: ' . ($ct !== '' ? $ct : 'image/jpeg'));
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    header('X-Photo-Cache: hit');
    readfile($cacheFile);
    exit;
}

$key = ecp_google_maps_api_key();
if ($key === '') {
    // No key, but a stale cached copy is better than nothing — serve it.
    if (is_file($cacheFile)) {
        $ct = is_file($cacheMeta) ? trim((string) file_get_contents($cacheMeta)) : 'image/jpeg';
        header('Content-Type: ' . ($ct !== '' ? $ct : 'image/jpeg'));
        header('Cache-Control: public, max-age=86400');
        header('X-Photo-Cache: stale-no-key');
        readfile($cacheFile);
        exit;
    }
    http_response_code(503);
    exit;
}

$url = 'https://maps.googleapis.com/maps/api/place/photo?' . http_build_query([
    'maxwidth' => $width, 'photoreference' => $ref, 'key' => $key,
]);

$body = false;
$contentType = 'image/jpeg';
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15, CURLOPT_HEADER => true,
        ]);
        $raw = curl_exec($ch);
        if ($raw !== false) {
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            if ($status >= 200 && $status < 300) {
                $headers = substr($raw, 0, $headerSize);
                $body = substr($raw, $headerSize);
                if (preg_match('/^Content-Type:\s*([^\r\n]+)/mi', $headers, $m)) {
                    $contentType = trim($m[1]);
                }
            }
        }
        curl_close($ch);
    }
}
if ($body === false) {
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => 1]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        // Google fetch failed — serve a stale cached copy if we have one.
        if (is_file($cacheFile)) {
            $ct = is_file($cacheMeta) ? trim((string) file_get_contents($cacheMeta)) : 'image/jpeg';
            header('Content-Type: ' . ($ct !== '' ? $ct : 'image/jpeg'));
            header('Cache-Control: public, max-age=86400');
            header('X-Photo-Cache: stale-fetch-failed');
            readfile($cacheFile);
            exit;
        }
        http_response_code(404);
        exit;
    }
}

// Save to the 30-day cache (best-effort; a write failure just means we re-fetch
// next time). Write atomically so a concurrent read never sees a partial file.
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
if (is_dir($cacheDir) && is_writable($cacheDir)) {
    $tmp = $cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $body) !== false) {
        @rename($tmp, $cacheFile);
        @file_put_contents($cacheMeta, $contentType);
    }
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('X-Photo-Cache: miss');
echo $body;
