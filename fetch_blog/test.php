<?php
// =====================================================================
// fetch_blog/test.php — "Test connections" endpoint for the dashboard.
// Verifies all three credentials WITHOUT spending tokens or creating
// anything: Claude key (models lookup), Gemini key (model lookup),
// WordPress app password (authenticated /users/me).
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';

fb_require_key();

$checks = [];

// ---- Claude ---------------------------------------------------------
try {
    $key = fb_env('ANTHROPIC_API_KEY');
    if ($key === '') throw new RuntimeException('ANTHROPIC_API_KEY is empty in .env');
    $model = fb_env('CLAUDE_MODEL', 'claude-opus-4-8');
    [$code, $body] = fb_http('GET', 'https://api.anthropic.com/v1/models/' . rawurlencode($model), [
        'x-api-key: ' . $key,
        'anthropic-version: 2023-06-01',
    ], null, 30);
    if ($code === 200) {
        $checks[] = ['name' => 'Claude', 'ok' => true, 'msg' => "Key valid, model {$model} available."];
    } else {
        $err = json_decode($body, true)['error']['message'] ?? "HTTP {$code}";
        throw new RuntimeException((string) $err);
    }
} catch (Throwable $e) {
    $checks[] = ['name' => 'Claude', 'ok' => false, 'msg' => $e->getMessage()];
}

// ---- Gemini ---------------------------------------------------------
try {
    $key = fb_env('GEMINI_API_KEY');
    if ($key === '') throw new RuntimeException('GEMINI_API_KEY is empty in .env');
    $model = fb_env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image');
    [$code, $body] = fb_http('GET', 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model), [
        'x-goog-api-key: ' . $key,
    ], null, 30);
    if ($code === 200) {
        $checks[] = ['name' => 'Gemini', 'ok' => true, 'msg' => "Key valid, model {$model} available."];
    } else {
        $err = json_decode($body, true)['error']['message'] ?? "HTTP {$code}";
        throw new RuntimeException((string) $err);
    }
} catch (Throwable $e) {
    $checks[] = ['name' => 'Gemini', 'ok' => false, 'msg' => $e->getMessage()];
}

// ---- WordPress ------------------------------------------------------
try {
    require_once __DIR__ . '/_wordpress.php';
    $wp = fb_wp_config(); // throws if any WP_* is empty
    [$code, $body] = fb_http('GET', $wp['base'] . '/wp-json/wp/v2/users/me?context=edit', [
        'Authorization: ' . $wp['auth'],
    ], null, 30);
    $resp = json_decode($body, true);
    if ($code === 200 && is_array($resp) && !empty($resp['name'])) {
        $roles = implode(', ', (array) ($resp['roles'] ?? []));
        $canPost = array_intersect(['administrator', 'editor', 'author'], (array) ($resp['roles'] ?? [])) !== [];
        $checks[] = [
            'name' => 'WordPress',
            'ok'   => $canPost,
            'msg'  => "Logged in as \"{$resp['name']}\" (role: {$roles})."
                . ($canPost ? ' Can create drafts.' : ' This role CANNOT create posts — use an Editor/Administrator user.'),
        ];
    } elseif ($code === 401 || $code === 403) {
        throw new RuntimeException('Auth rejected (HTTP ' . $code . '). Check WP_USERNAME / WP_APP_PASSWORD, that the site is HTTPS, and that no security plugin blocks Application Passwords or the REST API.');
    } else {
        throw new RuntimeException("Unexpected reply (HTTP {$code}). Is WP_BASE_URL right? It must be the WordPress root, e.g. https://eclinicpro.com/blog");
    }
} catch (Throwable $e) {
    $checks[] = ['name' => 'WordPress', 'ok' => false, 'msg' => $e->getMessage()];
}

fb_json_out(['ok' => !in_array(false, array_column($checks, 'ok'), true), 'checks' => $checks]);
