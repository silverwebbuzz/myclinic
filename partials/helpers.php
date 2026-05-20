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
 * Portal URL helper — change once if the subdomain ever moves.
 */
function ecp_portal_url(string $path = '/'): string
{
    return 'https://app.eclinicpro.com' . $path;
}
