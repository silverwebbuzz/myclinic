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

    if (preg_match('#^/L/([a-f0-9]{16})/?$#', $uri, $m)) {
        $_GET['t'] = $m[1];
        require __DIR__ . '/../L.php';

        return true;
    }

    if ($uri === '/sitemap.xml') {
        require __DIR__ . '/../sitemap.php';

        return true;
    }

    if (preg_match('#^/pricing/?$#', $uri)) {
        header('Location: /features#pricing', true, 301);
        exit;
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
