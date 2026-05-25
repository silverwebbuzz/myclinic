<?php
// =====================================================================
// sms.php — outbound SMS abstraction.
//
// In production: routes through MSG91 (transactional, DLT-registered).
// In dev:        writes the message to storage/logs/otp.log and returns
//                a fake message ID so the rest of the flow works without
//                needing an SMS account.
//
// Mode is controlled by ECP_SMS_MODE in /app/.env. Values:
//     dev    — write to log file, never hit MSG91
//     live   — POST to MSG91 API. Requires MSG91_AUTH_KEY + MSG91_OTP_TEMPLATE_ID
//
// All sending happens through ecp_sms_send_otp(). Don't call MSG91
// directly from page code — keep one place to swap providers later
// (WhatsApp / Twilio / etc).
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Reads .env once per request and caches in a static.
 * Same loader as ecp_db() uses but exposed here so we don't need
 * to drag in the whole db.php for SMS-only callers.
 */
function ecp_env(string $key, ?string $default = null): ?string {
    static $env = null;
    if ($env === null) {
        $env = [];
        $path = __DIR__ . '/../app/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (!str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }
    return $env[$key] ?? $default;
}

/**
 * Send a 6-digit OTP to a phone number.
 * Returns ['ok' => bool, 'mode' => 'dev'|'live', 'message_id' => string|null,
 *          'dev_code' => string|null, 'error' => string|null]
 *
 * In dev mode the cleartext code is returned in `dev_code` so the API
 * endpoint can echo it back to the modal — that's intentional, makes
 * local testing painless. NEVER expose dev_code in live mode (we don't).
 */
function ecp_sms_send_otp(string $phone, string $code): array {
    $mode = strtolower((string) ecp_env('ECP_SMS_MODE', 'dev'));
    $phone = ecp_normalize_phone($phone);

    if ($phone === '') {
        return ['ok' => false, 'mode' => $mode, 'message_id' => null, 'dev_code' => null,
                'error' => 'invalid_phone'];
    }

    if ($mode === 'dev') {
        // Dev mode: don't try to write a log file (it depends on storage/
        // being writable, which fails on shared hosting). Just return the
        // code so the modal shows it inline.
        return ['ok' => true, 'mode' => 'dev', 'message_id' => 'dev_' . bin2hex(random_bytes(4)),
                'dev_code' => $code, 'error' => null];
    }

    // ----- live: MSG91 -----
    $key      = ecp_env('MSG91_AUTH_KEY');
    $template = ecp_env('MSG91_OTP_TEMPLATE_ID');
    $sender   = ecp_env('MSG91_SENDER_ID', 'ECLNPR');

    if (!$key || !$template) {
        error_log('[ecp_sms] live mode but MSG91_AUTH_KEY / MSG91_OTP_TEMPLATE_ID not set');
        return ['ok' => false, 'mode' => 'live', 'message_id' => null, 'dev_code' => null,
                'error' => 'sms_not_configured'];
    }

    // MSG91 OTP API: https://docs.msg91.com/p/tf9GTextN/e/PUtAjMVuhEf
    // Strip leading + and country code for MSG91's "mobile" param.
    $mobile = preg_replace('/^\+?/', '', $phone);
    $url = 'https://control.msg91.com/api/v5/otp?' . http_build_query([
        'template_id' => $template,
        'mobile'      => $mobile,
        'authkey'     => $key,
        'sender'      => $sender,
        'otp_length'  => 6,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['OTP' => $code], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $http >= 400) {
        error_log('[ecp_sms] MSG91 failed http=' . $http . ' err=' . $err . ' body=' . substr((string) $body, 0, 400));
        return ['ok' => false, 'mode' => 'live', 'message_id' => null, 'dev_code' => null,
                'error' => 'sms_send_failed'];
    }

    $json = json_decode((string) $body, true);
    return [
        'ok'         => ($json['type'] ?? '') === 'success',
        'mode'       => 'live',
        'message_id' => $json['request_id'] ?? null,
        'dev_code'   => null,
        'error'      => ($json['type'] ?? '') === 'success' ? null : ($json['message'] ?? 'sms_unknown_error'),
    ];
}

/**
 * Dev-mode logger. Appends a single line per OTP issued.
 */
function ecp_sms_log_dev(string $phone, string $code): void {
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $line = sprintf("[%s] phone=%s code=%s\n", date('Y-m-d H:i:s'), $phone, $code);
    @file_put_contents($dir . '/otp.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Strip whitespace + dashes from a phone number and make sure it has a
 * country code (default +91 for India if user typed only 10 digits).
 * Returns '' if it can't be parsed.
 */
function ecp_normalize_phone(string $raw): string {
    $s = preg_replace('/[\s\-\(\)]/', '', $raw) ?? $raw;
    if ($s === '') return '';

    // Already has + → trust it (but strip non-digits after the +)
    if ($s[0] === '+') {
        $rest = preg_replace('/\D/', '', substr($s, 1)) ?? '';
        return $rest === '' ? '' : '+' . $rest;
    }

    $digits = preg_replace('/\D/', '', $s) ?? '';
    if ($digits === '') return '';

    // 10 digits → assume India
    if (strlen($digits) === 10) return '+91' . $digits;
    // 11 digits starting with 0 → drop the leading 0, assume India
    if (strlen($digits) === 11 && $digits[0] === '0') return '+91' . substr($digits, 1);
    // 12 digits starting with 91 → India with country code
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) return '+' . $digits;
    // Anything else → require the user to include +
    return '+' . $digits;
}
