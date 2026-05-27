<?php
// =====================================================================
// sitemap.php — auto-generated XML sitemap.
// .htaccess rewrites /sitemap.xml to here.
//
// Includes:
//   - Marketing pages (manual list)
//   - Every (city) and (specialty, city) combo with enough listings
//
// 50,000 URL cap per file is the spec. We're far below that.
// Cache the rendered XML for 6 hours so Googlebot hits don't pound the DB.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/db.php';
require_once __DIR__ . '/partials/seo_slugs.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=21600');   // 6 hours

$cachePath = __DIR__ . '/storage/cache/sitemap.xml';
$cacheTtl  = 6 * 3600;
if (is_file($cachePath) && (time() - filemtime($cachePath)) < $cacheTtl) {
    readfile($cachePath);
    exit;
}

$base = 'https://eclinicpro.com';
$now  = date('Y-m-d');

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// ---- Marketing pages (manual list — keep in sync with what's public) ----
$marketing = [
    ['',                   1.0, 'weekly'],
    ['/find-a-doctor',     0.9, 'daily'],
    ['/features',          0.8, 'monthly'],
    ['/pricing',           0.8, 'monthly'],
    ['/product-tour',      0.7, 'monthly'],
    ['/security',          0.6, 'monthly'],
    ['/customer-stories',  0.7, 'monthly'],
    ['/book-a-demo',       0.7, 'monthly'],
    // Specialty landing pages (marketing — distinct from /find-a-doctor SEO city pages)
    ['/gps',               0.7, 'monthly'],
    ['/dentists',          0.7, 'monthly'],
    ['/dermatologists',    0.7, 'monthly'],
    ['/pediatricians',     0.7, 'monthly'],
    ['/homeopaths',        0.7, 'monthly'],
    ['/physiotherapists',  0.7, 'monthly'],
    // NOTE: /patient is private (noindex) — excluded from sitemap.
];
foreach ($marketing as [$path, $priority, $changefreq]) {
    // Root must be a fully-qualified URL with trailing slash for Search Console.
    $loc = $path === '' ? "{$base}/" : "{$base}{$path}";
    $pr  = number_format((float) $priority, 1);
    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    echo "    <lastmod>{$now}</lastmod>\n";
    echo "    <changefreq>{$changefreq}</changefreq>\n";
    echo "    <priority>{$pr}</priority>\n";
    echo "  </url>\n";
}

// ---- City + (city, specialty) pages from live data ----
$db = ecp_db();
if ($db) {
    try {
        // Cities with enough doctors to be worth indexing.
        $rows = $db->prepare(
            "SELECT city, COUNT(*) AS n
             FROM directory_doctors
             WHERE is_active = 1 AND status = 'OPERATIONAL'
               AND city IS NOT NULL AND city <> ''
             GROUP BY city
             HAVING n >= :min
             ORDER BY n DESC"
        );
        $rows->bindValue(':min', ECP_SEO_MIN_LISTINGS, PDO::PARAM_INT);
        $rows->execute();
        $cities = $rows->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cities as $c) {
            $citySlug = ecp_slug((string) $c['city']);
            if ($citySlug === '') continue;
            $url = "{$base}/find-a-doctor/{$citySlug}";
            echo "  <url>\n";
            echo "    <loc>{$url}</loc>\n";
            echo "    <lastmod>{$now}</lastmod>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.8</priority>\n";  // city pages
            echo "  </url>\n";
        }

        // (city, specialty) combos with enough listings.
        $combos = $db->prepare(
            "SELECT city, specialty, COUNT(*) AS n
             FROM directory_doctors
             WHERE is_active = 1 AND status = 'OPERATIONAL'
               AND city IS NOT NULL AND city <> ''
               AND specialty IS NOT NULL AND specialty <> ''
             GROUP BY city, specialty
             HAVING n >= :min"
        );
        $combos->bindValue(':min', ECP_SEO_MIN_LISTINGS, PDO::PARAM_INT);
        $combos->execute();

        foreach ($combos->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $citySlug = ecp_slug((string) $r['city']);
            $specSlug = ecp_slug_for_db_specialty((string) $r['specialty']);
            if (!$citySlug || !$specSlug) continue;

            $url = "{$base}/find-a-doctor/{$specSlug}-in-{$citySlug}";
            echo "  <url>\n";
            echo "    <loc>{$url}</loc>\n";
            echo "    <lastmod>{$now}</lastmod>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.7</priority>\n";
            echo "  </url>\n";
        }
    } catch (Throwable $e) {
        error_log('[sitemap] ' . $e->getMessage());
    }
}

echo '</urlset>' . "\n";
$xml = ob_get_clean();

// Cache to disk for next time
$cacheDir = __DIR__ . '/storage/cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
@file_put_contents($cachePath, $xml, LOCK_EX);

echo $xml;
