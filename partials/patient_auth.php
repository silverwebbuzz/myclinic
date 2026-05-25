<?php
// =====================================================================
// patient_auth.php — passwordless patient authentication.
//
// Two-step flow:
//   1) send_otp(phone)              -> writes patient_otp_codes row, sends SMS
//   2) verify_otp(phone, code, name?) -> creates or returns patient_identities,
//                                         opens a patient_sessions row,
//                                         sets the ecp_pid cookie
//
// On every patient-aware page, call ecp_patient_current() to get the
// logged-in identity row (or null).
//
// Cookie: ecp_pid    (httpOnly, secure in production, 30-day rolling expiry)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sms.php';

const ECP_PAT_COOKIE          = 'ecp_pid';
const ECP_PAT_SESSION_DAYS    = 30;
const ECP_PAT_OTP_TTL_SECONDS = 600;   // 10 minutes
const ECP_PAT_OTP_MAX_ATTEMPTS = 5;
const ECP_PAT_OTP_RESEND_SECONDS = 30; // throttle re-issue

// ---------------------------------------------------------------------
// OTP issue
// ---------------------------------------------------------------------

/**
 * Issue an OTP for a phone number. Returns the same shape as
 * ecp_sms_send_otp() plus a `phone` field with the normalized number.
 *
 *   ['ok' => bool, 'phone' => '+91...', 'mode' => 'dev'|'live',
 *    'dev_code' => string|null, 'error' => string|null]
 */
function ecp_patient_send_otp(string $rawPhone): array {
    $phone = ecp_normalize_phone($rawPhone);
    if ($phone === '' || strlen($phone) < 8) {
        return ['ok' => false, 'phone' => $phone, 'mode' => 'n/a',
                'dev_code' => null, 'error' => 'invalid_phone'];
    }

    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'phone' => $phone, 'mode' => 'n/a',
                'dev_code' => null, 'error' => 'db_unavailable'];
    }

    // Throttle: don't allow another OTP for this handle within RESEND_SECONDS.
    $stmt = $db->prepare(
        'SELECT created_at FROM patient_otp_codes
         WHERE handle = :h AND consumed_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['h' => $phone]);
    $last = $stmt->fetchColumn();
    if ($last) {
        $age = time() - strtotime((string) $last);
        if ($age < ECP_PAT_OTP_RESEND_SECONDS) {
            return ['ok' => false, 'phone' => $phone, 'mode' => 'n/a',
                    'dev_code' => null, 'error' => 'resend_too_soon',
                    'retry_after' => ECP_PAT_OTP_RESEND_SECONDS - $age];
        }
    }

    // Generate, hash, store.
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = hash('sha256', $code);

    $ins = $db->prepare(
        'INSERT INTO patient_otp_codes (handle, channel, code_hash, expires_at)
         VALUES (:h, "sms", :hash, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
    );
    $ins->execute([
        'h'    => $phone,
        'hash' => $hash,
        'ttl'  => ECP_PAT_OTP_TTL_SECONDS,
    ]);

    // Send it.
    $sent = ecp_sms_send_otp($phone, $code);

    return [
        'ok'       => $sent['ok'],
        'phone'    => $phone,
        'mode'     => $sent['mode'],
        'dev_code' => $sent['dev_code'],   // only set in dev mode
        'error'    => $sent['error'],
    ];
}

// ---------------------------------------------------------------------
// OTP verify (and session start)
// ---------------------------------------------------------------------

/**
 * Verify a code, create/load the identity, start a session.
 *
 * On success returns ['ok' => true, 'identity' => array, 'is_new' => bool]
 * On failure returns ['ok' => false, 'error' => 'invalid_code'|'expired'|...]
 *
 * $name is only used when creating a brand-new identity (first sign-in for
 * this phone). Existing identities ignore it — they update via the profile
 * page, not at sign-in time.
 */
function ecp_patient_verify_otp(string $rawPhone, string $code, ?string $name = null): array {
    $phone = ecp_normalize_phone($rawPhone);
    $code  = preg_replace('/\D/', '', $code) ?? '';

    if ($phone === '' || strlen($code) !== 6) {
        return ['ok' => false, 'error' => 'invalid_input'];
    }

    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'db_unavailable'];
    }

    // Find the newest unconsumed code for this handle.
    $stmt = $db->prepare(
        'SELECT id, code_hash, expires_at, attempts
         FROM patient_otp_codes
         WHERE handle = :h AND consumed_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['h' => $phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['ok' => false, 'error' => 'no_code_issued'];
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'expired'];
    }
    if ((int) $row['attempts'] >= ECP_PAT_OTP_MAX_ATTEMPTS) {
        return ['ok' => false, 'error' => 'too_many_attempts'];
    }

    // Bump attempts FIRST so brute force is rate-limited even on success
    // races (we always pay the write).
    $db->prepare('UPDATE patient_otp_codes SET attempts = attempts + 1 WHERE id = :id')
       ->execute(['id' => $row['id']]);

    if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
        return ['ok' => false, 'error' => 'invalid_code'];
    }

    // Mark consumed.
    $db->prepare('UPDATE patient_otp_codes SET consumed_at = NOW() WHERE id = :id')
       ->execute(['id' => $row['id']]);

    // Get-or-create identity.
    $find = $db->prepare('SELECT * FROM patient_identities WHERE phone = :p LIMIT 1');
    $find->execute(['p' => $phone]);
    $identity = $find->fetch(PDO::FETCH_ASSOC);
    $isNew = false;

    if (!$identity) {
        $displayName = trim((string) $name);
        if ($displayName === '') $displayName = 'Patient';
        $ins = $db->prepare(
            'INSERT INTO patient_identities (phone, name, source, phone_verified_at)
             VALUES (:p, :n, "self_signup", NOW())'
        );
        $ins->execute(['p' => $phone, 'n' => $displayName]);
        $id = (int) $db->lastInsertId();
        $find->execute(['p' => $phone]);
        $identity = $find->fetch(PDO::FETCH_ASSOC);
        $isNew = true;
    } else {
        // Stamp phone_verified_at on first successful OTP if not set.
        if (empty($identity['phone_verified_at'])) {
            $db->prepare('UPDATE patient_identities SET phone_verified_at = NOW() WHERE id = :id')
               ->execute(['id' => $identity['id']]);
        }
    }

    // Start session.
    ecp_patient_session_start((int) $identity['id']);

    return ['ok' => true, 'identity' => $identity, 'is_new' => $isNew];
}

// ---------------------------------------------------------------------
// Sessions
// ---------------------------------------------------------------------

function ecp_patient_session_start(int $identityId): string {
    $db = ecp_db();
    if (!$db) return '';

    $token = bin2hex(random_bytes(32));   // 64 chars, fits CHAR(64)
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $ip = $ip ? substr(explode(',', (string) $ip)[0], 0, 45) : null;

    $stmt = $db->prepare(
        'INSERT INTO patient_sessions (id, identity_id, user_agent, ip, expires_at)
         VALUES (:id, :iid, :ua, :ip, DATE_ADD(NOW(), INTERVAL :days DAY))'
    );
    $stmt->execute([
        'id'   => $token,
        'iid'  => $identityId,
        'ua'   => $ua ?: null,
        'ip'   => $ip,
        'days' => ECP_PAT_SESSION_DAYS,
    ]);

    ecp_patient_set_cookie($token);
    return $token;
}

function ecp_patient_set_cookie(string $token): void {
    // Detect HTTPS — CloudFlare/proxies terminate SSL so $_SERVER['HTTPS']
    // is often empty even on https://. Check the proxy header too.
    $secure = ($_SERVER['HTTPS'] ?? '') === 'on'
           || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
           || (($_SERVER['SERVER_PORT'] ?? '') == '443');

    $expires = time() + ECP_PAT_SESSION_DAYS * 86400;

    if (headers_sent($file, $line)) {
        error_log("[ecp_patient_set_cookie] headers already sent at $file:$line — cookie NOT set");
        return;
    }

    // Use the array form on PHP 7.3+ (supports SameSite). Fall back to the
    // legacy 6-arg form on older PHP, which doesn't support SameSite but
    // at least sets the cookie.
    $ok = false;
    if (PHP_VERSION_ID >= 70300) {
        $ok = setcookie(ECP_PAT_COOKIE, $token, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        // 6-arg legacy form: name, value, expires, path, domain, secure, httponly
        $ok = setcookie(ECP_PAT_COOKIE, $token, $expires, '/; SameSite=Lax', '', $secure, true);
    }

    if (!$ok) {
        error_log('[ecp_patient_set_cookie] setcookie() returned false');
    }
}

function ecp_patient_clear_cookie(): void {
    if (headers_sent()) return;
    if (PHP_VERSION_ID >= 70300) {
        setcookie(ECP_PAT_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie(ECP_PAT_COOKIE, '', time() - 3600, '/; SameSite=Lax', '', false, true);
    }
}

/**
 * Returns the logged-in identity row (assoc array) or null.
 * Cached per request via a static so repeated calls are free.
 */
function ecp_patient_current(): ?array {
    static $cached = null;
    static $resolved = false;
    if ($resolved) return $cached;
    $resolved = true;

    $token = $_COOKIE[ECP_PAT_COOKIE] ?? '';
    if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
        return $cached = null;
    }

    $db = ecp_db();
    if (!$db) return $cached = null;

    $stmt = $db->prepare(
        'SELECT pi.*
         FROM patient_sessions ps
         JOIN patient_identities pi ON pi.id = ps.identity_id
         WHERE ps.id = :id AND ps.expires_at > NOW() AND pi.is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['id' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return $cached = null;

    // Touch last_seen_at (cheap update, lets us prune stale sessions later).
    $db->prepare('UPDATE patient_sessions SET last_seen_at = NOW() WHERE id = :id')
       ->execute(['id' => $token]);

    return $cached = $row;
}

function ecp_patient_logout(): void {
    $token = $_COOKIE[ECP_PAT_COOKIE] ?? '';
    if ($token !== '' && strlen($token) === 64 && ctype_xdigit($token)) {
        $db = ecp_db();
        if ($db) {
            $db->prepare('DELETE FROM patient_sessions WHERE id = :id')
               ->execute(['id' => $token]);
        }
    }
    ecp_patient_clear_cookie();
}

/**
 * Convenience for templates: first name of the logged-in patient,
 * or 'Patient' if unknown. Used by the header greeting.
 */
function ecp_patient_first_name(?array $identity = null): string {
    $i = $identity ?? ecp_patient_current();
    if (!$i) return 'Patient';
    if (!empty($i['first_name']))   return (string) $i['first_name'];
    if (!empty($i['preferred_name'])) return (string) $i['preferred_name'];
    $n = trim((string) ($i['name'] ?? ''));
    if ($n === '') return 'Patient';
    return preg_split('/\s+/', $n)[0] ?: 'Patient';
}

function ecp_patient_initials(?array $identity = null): string {
    $i = $identity ?? ecp_patient_current();
    if (!$i) return 'P';
    $n = trim((string) ($i['name'] ?? ''));
    if ($n === '') return 'P';
    $parts = preg_split('/\s+/', $n) ?: [''];
    $first = strtoupper(mb_substr($parts[0] ?? '', 0, 1)) ?: 'P';
    $last  = count($parts) > 1 ? strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)) : '';
    return $first . $last;
}
