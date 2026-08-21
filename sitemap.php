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
require_once __DIR__ . '/partials/lab_catalog.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=21600');   // 6 hours

$cachePath = __DIR__ . '/storage/cache/sitemap.xml';
$cacheTtl  = 6 * 3600;
if (is_file($cachePath) && (time() - filemtime($cachePath)) < $cacheTtl) {
    readfile($cachePath);
    exit;
}

$base = rtrim(ecp_site_url('/'), '/');
$now  = date('Y-m-d');

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// ---- Marketing pages (manual list — keep in sync with what's public) ----
$marketing = [
    ['',                   1.0, 'weekly'],
    ['/find-a-doctor',     0.9, 'daily'],
    ['/for-patients',      0.8, 'monthly'],
    ['/clinic-management-software', 0.8, 'monthly'],
    ['/product-tour',      0.7, 'monthly'],
    ['/security',          0.6, 'monthly'],
    ['/customer-stories',  0.7, 'monthly'],
    ['/book-a-demo',       0.7, 'monthly'],
    ['/contact',           0.5, 'yearly'],
    ['/cervical-cancer',   0.7, 'monthly'],
    ['/privacy-policy',    0.3, 'yearly'],
    ['/terms',             0.3, 'yearly'],
    ['/refund-policy',     0.3, 'yearly'],
    // Specialty landing pages (marketing — distinct from /find-a-doctor SEO city pages)
    ['/gps',               0.7, 'monthly'],
    ['/dentists',          0.7, 'monthly'],
    ['/dermatologists',    0.7, 'monthly'],
    ['/pediatricians',     0.7, 'monthly'],
    ['/homeopathy-clinic-management-software',        0.7, 'monthly'],
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

// ---- Lab catalog: hub, category listings, packages/offers & test pages ----
// Every crawlable lab URL so Google indexes "diabetes test", "aarogyam",
// "full body checkup", "vitamin d test price", etc.
if (function_exists('ecp_db') && ($labDb = ecp_db())) {
    $emit = static function (string $path, string $priority, string $freq) use ($base, $now): void {
        echo "  <url>\n";
        echo "    <loc>{$base}{$path}</loc>\n";
        echo "    <lastmod>{$now}</lastmod>\n";
        echo "    <changefreq>{$freq}</changefreq>\n";
        echo "    <priority>{$priority}</priority>\n";
        echo "  </url>\n";
    };
    try {
        // Browse hub.
        $emit('/lab/tests', '0.8', 'weekly');

        // Category listings — only categories with >= 3 products, so we don't
        // ship thin-content pages to Google (thin pages hurt SEO). Smaller
        // categories still work if linked, they're just not in the sitemap.
        foreach (ecp_lab_categories(3) as $c) {
            $emit('/lab/category/' . rawurlencode($c['slug']), '0.8', 'weekly');
        }

        // Book-by-symptom listings (curated concern pages; strong patient search).
        foreach (array_keys(ecp_lab_symptom_map()) as $symptomSlug) {
            $emit('/lab/symptom/' . rawurlencode($symptomSlug), '0.7', 'weekly');
        }

        // Every active product: packages/offers → /lab/package, tests → /lab/test.
        $stmt = $labDb->query(
            "SELECT product_type, slug FROM lab_products WHERE is_active = 1 ORDER BY product_type, slug"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $slug = rawurlencode((string) $p['slug']);
            if ($p['product_type'] === 'TEST') {
                $emit('/lab/test/' . $slug, '0.6', 'monthly');
            } else {
                $emit('/lab/package/' . $slug, '0.7', 'weekly');
            }
        }
    } catch (Throwable $e) {
        error_log('[sitemap lab] ' . $e->getMessage());
    }
}

echo '</urlset>' . "\n";
$xml = ob_get_clean();

// Cache to disk for next time
$cacheDir = __DIR__ . '/storage/cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
@file_put_contents($cachePath, $xml, LOCK_EX);

echo $xml;
