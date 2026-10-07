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

/**
 * A NEW account was just created: attach the Refer & Earn code the person typed, or the one
 * remembered from a /r/{code} link (cookie ecp_ref). Best-effort: never breaks sign-up.
 */
function store_referral_capture(int $identityId, ?string $typedCode = null): void
{
    $code = trim((string) $typedCode) !== '' ? (string) $typedCode : (string) ($_COOKIE['ecp_ref'] ?? '');
    if ($identityId <= 0 || $code === '' || !store_app()) {
        return;
    }
    try {
        \App\Services\Store\ReferralService::capture($identityId, $code);
    } catch (\Throwable $e) {
        error_log('[store_referral_capture] ' . $e->getMessage());
    }
    if (isset($_COOKIE['ecp_ref']) && !headers_sent()) {
        setcookie('ecp_ref', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    }
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

/**
 * Tiny per-IP rate limit for storefront endpoints (sliding window, file-backed in the
 * system temp dir so it needs no table). Returns false when the caller is over the limit.
 * Fails open if the temp dir isn't writable, so it can never lock real customers out.
 */
function store_throttle(string $bucket, int $max, int $windowSec): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $file = rtrim(sys_get_temp_dir(), '/') . '/ecp_store_rl_' . hash('sha256', $bucket . '|' . $ip);
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return true;
    }
    $now = time();
    flock($fh, LOCK_EX);
    $hits = json_decode((string) stream_get_contents($fh), true);
    $hits = array_values(array_filter(is_array($hits) ? $hits : [], static fn ($t) => (int) $t > $now - $windowSec));
    $ok = count($hits) < $max;
    if ($ok) {
        $hits[] = $now;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($hits));
    flock($fh, LOCK_UN);
    fclose($fh);

    return $ok;
}
