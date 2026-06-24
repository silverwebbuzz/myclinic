<?php
// =====================================================================
// helpers.php — small utility functions used across marketing pages.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * htmlspecialchars shortcut.
 */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns 'is-active' if the given nav slug matches the current page.
 * Pages set $activePage = 'features' (or similar) before requiring header.
 */
function nav_active(string $slug, string $current = ''): string
{
    if ($current === '' && isset($GLOBALS['activePage'])) {
        $current = (string) $GLOBALS['activePage'];
    }
    return $slug === $current ? 'is-active' : '';
}

/**
 * Format a number with thousand separators (2,847 etc.).
 */
function ecp_num(int $n): string
{
    return number_format($n);
}

/**
 * Public marketing site base URL (e.g. https://eclinicpro.com).
 * Reads SITE_URL from env (app/.env), falling back to the production domain.
 * Use this instead of hardcoding the domain in canonicals, JSON-LD, links, etc.
 */
function ecp_site_url(string $path = '/'): string
{
    $base = rtrim(ecp_env('SITE_URL', 'https://eclinicpro.com'), '/');
    return $base . $path;
}

/**
 * Portal URL helper — the doctor/clinic app (e.g. https://app.eclinicpro.com).
 * Reads APP_URL from env, falling back to the production portal domain.
 */
function ecp_portal_url(string $path = '/'): string
{
    $base = rtrim(ecp_env('APP_URL', 'https://app.eclinicpro.com'), '/');
    return $base . $path;
}
