<?php
// =====================================================================
// fetch_blog/_env.php — tiny .env loader + shared helpers.
// Same pattern as fetch_doctor: plain KEY=value lines, # comments.
// =====================================================================

declare(strict_types=1);

/** @return array<string,string> */
function fb_env_all(): array {
    static $env = null;
    if ($env !== null) return $env;
    $env = [];
    $file = __DIR__ . '/.env';
    if (is_file($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
    return $env;
}

function fb_env(string $key, string $default = ''): string {
    $all = fb_env_all();
    return ($all[$key] ?? '') !== '' ? $all[$key] : $default;
}

/**
 * Gate the tool behind TOOL_KEY (?key=... or X-Tool-Key header).
 * If TOOL_KEY is empty in .env the tool is open (local/dev use).
 */
function fb_require_key(): void {
    $expected = fb_env('TOOL_KEY');
    if ($expected === '') return;
    $given = $_GET['key'] ?? $_POST['key'] ?? ($_SERVER['HTTP_X_TOOL_KEY'] ?? '');
    if (!hash_equals($expected, (string) $given)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Forbidden. Open this tool with ?key=YOUR_TOOL_KEY (set in fetch_blog/.env).\n");
    }
}

/** @return array<int,array<string,mixed>> the 90 calendar rows */
function fb_load_calendar(): array {
    $rows = json_decode((string) file_get_contents(__DIR__ . '/blogs.json'), true);
    if (!is_array($rows)) {
        throw new RuntimeException('fetch_blog/blogs.json missing or invalid');
    }
    return $rows;
}

/** @return array<string,mixed> per-day generation state, keyed by day number */
function fb_load_state(): array {
    $file = __DIR__ . '/state/state.json';
    if (!is_file($file)) return [];
    $s = json_decode((string) file_get_contents($file), true);
    return is_array($s) ? $s : [];
}

/** @param array<string,mixed> $state */
function fb_save_state(array $state): void {
    $dir = __DIR__ . '/state';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    file_put_contents($dir . '/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** HTML-escape helper used by index.php and _render.php. */
function fb_e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** JSON response helper for AJAX endpoints (echoes and exits). */
function fb_json_out(array $payload, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Shared cURL wrapper. Returns [httpCode, bodyString].
 * @param array<int,string> $headers
 */
function fb_http(string $method, string $url, array $headers, ?string $body, int $timeout = 120): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 20,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP request failed: {$err} ({$url})");
    }
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, (string) $resp];
}
