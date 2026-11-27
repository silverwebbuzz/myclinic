<?php

declare(strict_types=1);

// Cron: once a day, e.g. 3 AM — php workers/sitemap_build.php
//   (run it once by hand right after uploading, too)
//
// Writes a STATIC public_html/sitemap.xml from sitemap.php (the live list of
// every page: marketing, find-a-doctor cities, Wellness Hub hub / sections /
// posts / creators / camps / events). Google Search Console reads this plain
// file; it never goes stale because this job rebuilds it every day.
//
// Safety: the new file only replaces the old one if it is valid XML with a
// sensible number of URLs and no old /health-tips links — otherwise the old
// file stays and the job exits with an error (cron emails you the output).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);                       // app/workers → public_html
$target = $root . '/sitemap.xml';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'eclinicpro.com';
$_SERVER['HTTPS'] = 'on';

@unlink($root . '/storage/cache/sitemap.xml');     // force a fresh build, not the 6-hour cache

ob_start();
require $root . '/sitemap.php';
$xml = (string) ob_get_clean();

libxml_use_internal_errors(true);
$doc = simplexml_load_string($xml);
if ($doc === false) {
    fwrite(STDERR, "sitemap_build: generated XML is invalid — kept the old sitemap.xml\n");
    foreach (libxml_get_errors() as $err) {
        fwrite(STDERR, '  line ' . $err->line . ': ' . trim($err->message) . "\n");
    }
    exit(1);
}
$count = count($doc->url);
if ($count < 20) {
    fwrite(STDERR, "sitemap_build: only {$count} URLs (database down?) — kept the old sitemap.xml\n");
    exit(1);
}
$old = substr_count($xml, '/health-tips');
if ($old > 0) {
    fwrite(STDERR, "sitemap_build: {$old} old /health-tips URLs found — kept the old sitemap.xml\n");
    exit(1);
}

$tmp = $target . '.tmp';
if (file_put_contents($tmp, $xml, LOCK_EX) === false || !rename($tmp, $target)) {
    @unlink($tmp);
    fwrite(STDERR, "sitemap_build: could not write {$target}\n");
    exit(1);
}
@chmod($target, 0644);

$wellness = substr_count($xml, '/wellness');
echo 'sitemap.xml written: ' . $count . ' URLs (' . $wellness . ' Wellness Hub), ' . number_format(strlen($xml) / 1024, 1) . " KB\n";
