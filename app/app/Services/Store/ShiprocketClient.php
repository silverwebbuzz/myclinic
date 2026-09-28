<?php

declare(strict_types=1);

namespace App\Services\Store;

/**
 * Thin Shiprocket API client (https://apiv2.shiprocket.in/v1/external).
 *
 * Auth: an API user (Shiprocket → Settings → API → Configure) logs in with
 * email + password and gets a bearer token. We cache it in platform_settings
 * and refresh ~1 day before expiry (tokens last ~10 days — VERIFY WITH PROVIDER).
 * The password is stored encrypted (StoreCrypto / STORE_DATA_KEY).
 *
 * Every call returns the decoded JSON array; transport/API failures come back
 * as ['_error' => message, '_http' => code] so callers can persist the reason.
 */
final class ShiprocketClient
{
    private const BASE = 'https://apiv2.shiprocket.in/v1/external';
    private const TOKEN_DAYS = 9;

    public static function configured(): bool
    {
        return StoreSettings::get('store_shiprocket_enabled', '0') === '1'
            && StoreSettings::get('store_shiprocket_email') !== ''
            && StoreSettings::get('store_shiprocket_password') !== '';
    }

    /** Save API-user credentials (password encrypted) and drop any cached token. */
    public static function saveCredentials(string $email, string $password): void
    {
        StoreSettings::set('store_shiprocket_email', trim($email));
        if ($password !== '') {
            StoreSettings::set('store_shiprocket_password', StoreCrypto::encrypt($password), true);
        }
        StoreSettings::set('store_shiprocket_token', '', true);
        StoreSettings::set('store_shiprocket_token_expires', '');
    }

    /** @return array{ok: bool, error?: string} */
    public static function testLogin(): array
    {
        $token = self::token(true);

        return $token !== null ? ['ok' => true] : ['ok' => false, 'error' => self::$lastError ?: 'Login failed.'];
    }

    /** @return array<string, mixed> */
    public static function get(string $path, array $query = []): array
    {
        return self::request('GET', $path . ($query ? '?' . http_build_query($query) : ''), null);
    }

    /** @return array<string, mixed> */
    public static function post(string $path, array $body): array
    {
        return self::request('POST', $path, $body);
    }

    private static string $lastError = '';

    /** @return array<string, mixed> */
    private static function request(string $method, string $path, ?array $body, bool $retried = false): array
    {
        $token = self::token(false);
        if ($token === null) {
            return ['_error' => 'Shiprocket login failed: ' . (self::$lastError ?: 'check the API user in Store settings'), '_http' => 0];
        }
        [$code, $data, $err] = self::http($method, $path, $body, $token);
        if ($code === 401 && !$retried) {
            self::token(true);   // expired/revoked — log in again once

            return self::request($method, $path, $body, true);
        }
        if ($err !== null) {
            return ['_error' => $err, '_http' => $code];
        }
        if ($code >= 400) {
            $msg = is_array($data) ? (string) ($data['message'] ?? json_encode($data['errors'] ?? $data)) : 'HTTP ' . $code;

            return (is_array($data) ? $data : []) + ['_error' => $msg, '_http' => $code];
        }

        return is_array($data) ? $data : ['_error' => 'Unexpected response', '_http' => $code];
    }

    private static function token(bool $forceLogin): ?string
    {
        $cached = StoreSettings::get('store_shiprocket_token');
        $exp = (int) StoreSettings::get('store_shiprocket_token_expires', '0');
        if (!$forceLogin && $cached !== '' && $exp > time()) {
            return $cached;
        }
        $email = StoreSettings::get('store_shiprocket_email');
        $password = StoreCrypto::decrypt(StoreSettings::get('store_shiprocket_password'));
        if ($email === '' || $password === null) {
            self::$lastError = 'API user email/password not set (or STORE_DATA_KEY missing).';

            return null;
        }
        [$code, $data, $err] = self::http('POST', '/auth/login', ['email' => $email, 'password' => $password], null);
        if ($err !== null || $code >= 400 || empty($data['token'])) {
            self::$lastError = $err ?? (string) ($data['message'] ?? ('HTTP ' . $code));
            error_log('[Shiprocket] login failed: ' . self::$lastError);

            return null;
        }
        StoreSettings::set('store_shiprocket_token', (string) $data['token'], true);
        StoreSettings::set('store_shiprocket_token_expires', (string) (time() + self::TOKEN_DAYS * 86400));

        return (string) $data['token'];
    }

    /** @return array{0: int, 1: mixed, 2: ?string} [http code, decoded body, transport error] */
    private static function http(string $method, string $path, ?array $body, ?string $token): array
    {
        $ch = curl_init(self::BASE . $path);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? new \stdClass(), JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = $raw === false ? 'Network error: ' . curl_error($ch) : null;
        curl_close($ch);

        return [$code, $raw === false ? null : json_decode((string) $raw, true), $err];
    }
}
