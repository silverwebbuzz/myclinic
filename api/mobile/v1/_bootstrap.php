<?php
// =====================================================================
// api/mobile/v1/_bootstrap.php
// Shared bootstrap for the mobile (Flutter patient app) API facade.
//
// DESIGN PRINCIPLE — thin transport, shared brain:
//   Every route under api/mobile/v1/ is a THIN adapter. It parses input,
//   calls the SAME ecp_* business functions the website already uses
//   (in partials/), and re-emits the result. It must NOT re-implement
//   booking/OTP/wishlist/notification logic. That way the web and the app
//   behave identically and future changes (SMS/WhatsApp cron, quotas,
//   templates) reflect in the app with zero app updates.
//
// WHAT THIS FILE PROVIDES:
//   - Clean JSON error handling (never leak HTML).
//   - Uniform envelope helpers: ecp_m_ok() / ecp_m_err().
//   - Bearer-token auth that REUSES the existing patient_sessions token
//     (the same 64-char token the web sets in the ecp_pid cookie).
//   - JSON/form body reader.
//
// AUTH MODEL (v1):
//   The web already issues a 64-char session token (patient_sessions.id)
//   and stores it in the httpOnly ecp_pid cookie. For mobile we accept
//   that SAME token via `Authorization: Bearer <token>`. No JWT, no
//   refresh machinery — the 30-day rolling session is enough for v1.
//   ecp_m_current() checks the Bearer header FIRST, then falls back to the
//   cookie (so the same facade also works from a webview if ever needed).
// =====================================================================

declare(strict_types=1);

// Never print PHP warnings/notices into the JSON body. Errors go to log.
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../../partials/patient_auth.php';

// Turn any uncaught throwable into a clean 500 JSON response.
set_exception_handler(function (Throwable $e): void {
    error_log('[api/mobile/v1] uncaught: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

/** Emit JSON on success and stop. Envelope: { ok:true, ...payload }. */
function ecp_m_ok(array $payload = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(['ok' => true] + $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Emit JSON on failure and stop. Envelope: { ok:false, error, ...extra }. */
function ecp_m_err(string $code, int $status = 400, array $extra = []): void {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $code] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Read a JSON body, falling back to form-encoded POST. */
function ecp_m_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_starts_with(trim($raw), '{')) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST;
}

/** The HTTP method, uppercased. */
function ecp_m_method(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/** Require a specific method or 405. */
function ecp_m_require_method(string $method): void {
    if (ecp_m_method() !== strtoupper($method)) {
        ecp_m_err('method_not_allowed', 405);
    }
}

/**
 * Extract the mobile Bearer token, if present and well-formed.
 * The token is the same 64-char hex value stored in patient_sessions.id.
 */
function ecp_m_bearer_token(): string {
    $hdr = '';

    // Apache strips the Authorization header from PHP by default; different
    // stacks expose it in different places. Check every known location so a
    // valid Bearer token is never silently dropped (this exact issue made
    // authed endpoints return login_required despite a good token).
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION']          ?? '',   // ideal (with .htaccess forwarding)
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',   // Apache rewrite fallback
    ];
    foreach ($candidates as $c) {
        if ($c !== '') { $hdr = $c; break; }
    }

    // Last resort: apache_request_headers()/getallheaders() reads the raw
    // request headers directly, bypassing the $_SERVER stripping entirely.
    if ($hdr === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $hdr = (string) $v; break; }
        }
    }

    if ($hdr !== '' && preg_match('/Bearer\s+([0-9a-f]{64})/i', $hdr, $m)) {
        return strtolower($m[1]);
    }
    return '';
}

/**
 * Current logged-in patient identity for a mobile request.
 *
 * Order of resolution:
 *   1. Authorization: Bearer <token>  — the mobile path.
 *   2. ecp_pid cookie                  — fallback (webview / browser).
 *
 * Returns the same identity array ecp_patient_current() returns, or null.
 * Delegates the actual token->identity lookup to the shared web function
 * so session validation/expiry stays in one place.
 */
function ecp_m_current(): ?array {
    $token = ecp_m_bearer_token();
    if ($token !== '') {
        // Reuse the web session lookup by priming the cookie superglobal so
        // ecp_patient_current() validates it against patient_sessions exactly
        // as it does for the website. No duplicate SQL, one source of truth.
        $_COOKIE[ECP_PAT_COOKIE] = $token;
    }
    return ecp_patient_current();
}

/** Require a logged-in patient or 401. Returns the identity array. */
function ecp_m_require_patient(): array {
    $me = ecp_m_current();
    if (!$me) {
        ecp_m_err('login_required', 401);
    }
    return $me;
}

/** Shape an identity for client output (never leak internal columns). */
function ecp_m_shape_patient(array $i): array {
    return [
        'id'         => (int) $i['id'],
        'name'       => $i['name'] ?? null,
        'first_name' => $i['first_name'] ?? null,
        'phone'      => $i['phone'] ?? null,
        'email'      => $i['email'] ?? null,
    ];
}
