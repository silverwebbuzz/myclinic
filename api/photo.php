<?php
declare(strict_types=1);

require_once __DIR__ . '/../partials/db.php';

$ref = (string) ($_GET['ref'] ?? '');
$width = max(80, min(1600, (int) ($_GET['w'] ?? 400)));

if ($ref === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $ref)) {
    http_response_code(404);
    exit;
}

$key = ecp_google_maps_api_key();
if ($key === '') {
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
        http_response_code(404);
        exit;
    }
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
echo $body;
