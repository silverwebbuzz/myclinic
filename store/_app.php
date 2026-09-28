<?php
// =====================================================================
// store/_app.php — loads the portal's (app/) classes for storefront pages
// that touch money or stock: cart, checkout, orders.
//
// Why: order/pricing/stock logic must exist ONCE (App\Services\Store\*),
// because the Razorpay webhook (portal side) will call the same code.
// Read-only catalog pages don't need this (they use store/_lib.php).
// =====================================================================

declare(strict_types=1);

/** Boots app/vendor autoload + app/.env once. False (and logged) if unavailable. */
function store_app(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $appDir = dirname(__DIR__) . '/app';
    $autoload = $appDir . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('[store_app] missing ' . $autoload);

        return $ok = false;
    }
    require_once $autoload;
    try {
        if (is_file($appDir . '/.env')) {
            \Dotenv\Dotenv::createImmutable($appDir)->safeLoad();
        }
        \App\Support\ClinicTime::bootstrap();
    } catch (\Throwable $e) {
        error_log('[store_app] ' . $e->getMessage());

        return $ok = false;
    }

    return $ok = true;
}

/** For pages that can't work without the portal classes: show a friendly 503 instead of a fatal error. */
function store_app_required(): void
{
    if (store_app()) {
        return;
    }
    http_response_code(503);
    header('Retry-After: 300');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Back soon | eClinicPro Store</title>'
        . '<div style="font:16px/1.6 system-ui,sans-serif;max-width:520px;margin:15vh auto;padding:0 20px;color:#13294b">'
        . '<h1 style="font-weight:500">We\'ll be right back</h1><p>Cart and checkout are briefly unavailable. '
        . 'Your cart is safe; please try again in a few minutes.</p><p><a href="/store/">Back to the store</a></p></div>';
    exit;
}

function store_uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

const STORE_CART_COOKIE = 'ecp_cart';

/** Current visitor's cart (creates one when $create). Sets the guest cookie when a new cart is made. */
function store_cart(bool $create): ?array
{
    if (!store_app()) {
        return null;
    }
    $me = ecp_patient_current();
    $token = isset($_COOKIE[STORE_CART_COOKIE]) ? (string) $_COOKIE[STORE_CART_COOKIE] : null;
    $res = \App\Services\Store\CartService::resolve($me ? (int) $me['id'] : null, $token, $create);
    if ($res['new_token'] !== null && !headers_sent()) {
        $secure = ($_SERVER['HTTPS'] ?? '') === 'on' || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        setcookie(STORE_CART_COOKIE, $res['new_token'], [
            'expires' => time() + 30 * 86400, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
        ]);
        $_COOKIE[STORE_CART_COOKIE] = $res['new_token'];
    }

    return $res['cart'];
}

/**
 * Same-origin check for state-changing POSTs from storefront forms.
 * Session cookies are SameSite=Lax already; this rejects any request whose
 * Origin/Referer is another site.
 */
function store_same_origin(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        $v = (string) ($_SERVER[$h] ?? '');
        if ($v !== '') {
            return strtolower((string) parse_url($v, PHP_URL_HOST)) === $host;
        }
    }

    return true; // neither header sent (rare); SameSite=Lax still protects the session
}
