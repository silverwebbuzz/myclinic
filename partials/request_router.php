<?php

declare(strict_types=1);

/**
 * Mirror root .htaccess rewrites for environments where they are not applied
 * (PHP built-in server falls back to index.php for unknown paths).
 */
function ecp_dispatch_clean_url(string $requestUri): bool
{
    $uri = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $uri = rawurldecode($uri);

    if ($uri === '/' || $uri === '/index.php') {
        return false;
    }

    if (preg_match('#^/find-a-doctor/([A-Za-z0-9][A-Za-z0-9\-]*)/?$#', $uri, $m)) {
        $_GET['seo'] = $m[1];
        require __DIR__ . '/../find-a-doctor.php';

        return true;
    }

    if (preg_match('#^/find-a-doctor/([A-Za-z0-9][A-Za-z0-9\-]*)/([A-Za-z0-9][A-Za-z0-9\-]*)/?$#', $uri, $m)) {
        $_GET['seo'] = $m[1] . '/' . $m[2];
        require __DIR__ . '/../find-a-doctor.php';

        return true;
    }

    if (preg_match('#^/([a-z0-9][a-z0-9\-]*)/(clinic|doctor)/([a-z0-9][a-z0-9\-]*)/?$#i', $uri, $m)) {
        $_GET['city'] = strtolower($m[1]);
        $_GET['entity_type'] = strtolower($m[2]);
        $_GET['slug'] = strtolower($m[3]);
        require __DIR__ . '/../profile.php';

        return true;
    }

    if (preg_match('#^/L/([a-f0-9]{16})/?$#', $uri, $m)) {
        $_GET['t'] = $m[1];
        require __DIR__ . '/../L.php';

        return true;
    }

    // /features was renamed to /clinic-management-software — keep old links alive.
    if (preg_match('#^/features/?$#i', $uri)) {
        header('Location: /clinic-management-software', true, 301);

        return true;
    }

    if ($uri === '/sitemap.xml') {
        require __DIR__ . '/../sitemap.php';

        return true;
    }

    // Lab browse hub — /lab/tests (all packages, offers & tests).
    if (preg_match('#^/lab/tests/?$#i', $uri)) {
        $_GET['type'] = 'hub';
        require __DIR__ . '/../lab-listing.php';

        return true;
    }

    // Lab category listing — /lab/category/{slug} (SEO landing per concern).
    if (preg_match('#^/lab/category/([a-z0-9][a-z0-9\-]*)/?$#i', $uri, $m)) {
        $_GET['type'] = 'category';
        $_GET['slug'] = strtolower($m[1]);
        require __DIR__ . '/../lab-listing.php';

        return true;
    }

    // Individual test page — /lab/test/{slug} (SEO long-tail).
    if (preg_match('#^/lab/test/([a-z0-9][a-z0-9\-]*)/?$#i', $uri, $m)) {
        $_GET['type'] = 'test';
        $_GET['slug'] = strtolower($m[1]);
        require __DIR__ . '/../lab-detail.php';

        return true;
    }

    // Book-by-symptom listing — /lab/symptom/{slug} (was a thin detail page).
    if (preg_match('#^/lab/symptom/([a-z0-9][a-z0-9\-]*)/?$#i', $uri, $m)) {
        $_GET['type'] = 'symptom';
        $_GET['slug'] = strtolower($m[1]);
        require __DIR__ . '/../lab-listing.php';

        return true;
    }

    if (preg_match('#^/lab/(package|organ|life|step|why|partner)/([a-z0-9][a-z0-9\-]*)/?$#i', $uri, $m)) {
        $_GET['type'] = strtolower($m[1]);
        $_GET['slug'] = strtolower($m[2]);
        require __DIR__ . '/../lab-detail.php';

        return true;
    }

    // eClinicPro Store — mirrors the /store rules in the root .htaccess.
    if (preg_match('#^/store(/.*)?$#i', $uri)) {
        $storeDir = __DIR__ . '/../store/';
        $routes = [
            '#^/store/?$#' => ['index.php', []],
            '#^/store/c/([a-z0-9][a-z0-9\-]*)/([a-z0-9][a-z0-9\-]*)/?$#i' => ['listing.php', ['_r_type' => 'category', '_r_a' => 1, '_r_b' => 2]],
            '#^/store/c/([a-z0-9][a-z0-9\-]*)/?$#i' => ['listing.php', ['_r_type' => 'category', '_r_a' => 1]],
            '#^/store/(need|goal|brand)/([a-z0-9][a-z0-9\-]*)/?$#i' => ['listing.php', ['_r_type' => 1, '_r_a' => 2]],
            '#^/store/search/?$#i' => ['listing.php', ['_r_type' => 'search']],
            '#^/store/p/([a-z0-9][a-z0-9\-]*)/?$#i' => ['product.php', ['_r_a' => 1]],
            '#^/store/seller/([a-z0-9][a-z0-9\-]*)/?$#i' => ['seller.php', ['_r_a' => 1]],
            '#^/store/(categories|wishlist|cart|checkout|orders)/?$#i' => [null, []],
            '#^/store/order/([A-Za-z0-9\-]+)/?$#' => ['order.php', ['_r_a' => 1]],
        ];
        foreach ($routes as $re => [$file, $params]) {
            if (!preg_match($re, $uri, $m)) {
                continue;
            }
            foreach ($params as $k => $v) {
                $_GET[$k] = is_int($v) ? strtolower($m[$v]) : $v;
            }
            require $storeDir . ($file ?? strtolower($m[1]) . '.php');

            return true;
        }

        return false;   // e.g. /store/_lib.php → 404, never executed
    }

    if (preg_match('#^/([^.]+)/?$#', $uri, $m)) {
        $php = __DIR__ . '/../' . $m[1] . '.php';
        if (is_file($php)) {
            require $php;

            return true;
        }

        $html = __DIR__ . '/../' . $m[1] . '.html';
        if (is_file($html)) {
            readfile($html);

            return true;
        }
    }

    return false;
}
