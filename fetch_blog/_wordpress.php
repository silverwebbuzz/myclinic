<?php
// =====================================================================
// fetch_blog/_wordpress.php — talks to the LIVE WordPress blog via the
// built-in REST API (no plugin needed). Auth = Application Password
// (Users → Profile → Application Passwords), sent as HTTP Basic auth.
// Everything is created as DRAFT — you publish manually in wp-admin.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';

/** @return array{base:string, auth:string} */
function fb_wp_config(): array {
    $base = rtrim(fb_env('WP_BASE_URL'), '/');
    $user = fb_env('WP_USERNAME');
    $pass = fb_env('WP_APP_PASSWORD');
    if ($base === '' || $user === '' || $pass === '') {
        throw new RuntimeException('WP_BASE_URL / WP_USERNAME / WP_APP_PASSWORD missing in fetch_blog/.env');
    }
    return ['base' => $base, 'auth' => 'Basic ' . base64_encode($user . ':' . $pass)];
}

/**
 * Upload the hero image to the WP media library.
 * @return array{id:int, url:string}
 */
function fb_wp_upload_media(string $bytes, string $mime, string $filename, string $altText): array {
    $wp = fb_wp_config();
    [$code, $body] = fb_http('POST', $wp['base'] . '/wp-json/wp/v2/media', [
        'Authorization: ' . $wp['auth'],
        'Content-Type: ' . $mime,
        'Content-Disposition: attachment; filename="' . addslashes($filename) . '"',
    ], $bytes, 180);

    $resp = json_decode($body, true);
    if ($code !== 201 || !is_array($resp) || empty($resp['id'])) {
        throw new RuntimeException("WP media upload failed (HTTP {$code}): " . substr($body, 0, 300));
    }
    $mediaId = (int) $resp['id'];

    // Set alt text (best-effort; ignore failure).
    try {
        fb_http('POST', $wp['base'] . '/wp-json/wp/v2/media/' . $mediaId, [
            'Authorization: ' . $wp['auth'],
            'Content-Type: application/json',
        ], json_encode(['alt_text' => $altText], JSON_UNESCAPED_UNICODE), 60);
    } catch (Throwable $e) { /* non-fatal */ }

    return ['id' => $mediaId, 'url' => (string) ($resp['source_url'] ?? '')];
}

/**
 * Find-or-create taxonomy terms (tags/categories) and return their IDs.
 * @param string[] $names
 * @return int[]
 */
function fb_wp_ensure_terms(string $taxonomy, array $names): array {
    $wp = fb_wp_config();
    $ids = [];
    foreach ($names as $name) {
        $name = trim((string) $name);
        if ($name === '') continue;
        try {
            // Search first (exact-name match among results).
            [$code, $body] = fb_http('GET',
                $wp['base'] . '/wp-json/wp/v2/' . $taxonomy . '?per_page=20&search=' . rawurlencode($name),
                ['Authorization: ' . $wp['auth']], null, 60);
            $found = 0;
            foreach (json_decode($body, true) ?: [] as $term) {
                if (is_array($term) && strcasecmp((string) ($term['name'] ?? ''), $name) === 0) {
                    $found = (int) $term['id'];
                    break;
                }
            }
            if ($found === 0) {
                [$code, $body] = fb_http('POST', $wp['base'] . '/wp-json/wp/v2/' . $taxonomy, [
                    'Authorization: ' . $wp['auth'],
                    'Content-Type: application/json',
                ], json_encode(['name' => $name], JSON_UNESCAPED_UNICODE), 60);
                $resp = json_decode($body, true);
                if ($code === 201 && !empty($resp['id'])) {
                    $found = (int) $resp['id'];
                } elseif ($code === 400 && ($resp['code'] ?? '') === 'term_exists') {
                    $found = (int) ($resp['data']['term_id'] ?? 0); // race: someone created it
                }
            }
            if ($found > 0) $ids[] = $found;
        } catch (Throwable $e) { /* tags are nice-to-have; skip on failure */ }
    }
    return array_values(array_unique($ids));
}

/**
 * Best-effort Yoast SEO fields (meta description + focus keyword).
 * Yoast's post meta is often not exposed to the REST API, so a failure
 * here is expected on some sites and must never break the draft —
 * the excerpt already carries the meta description as fallback.
 */
function fb_wp_try_yoast(int $postId, string $metaDescription, string $focusKeyword): bool {
    $wp = fb_wp_config();
    try {
        [$code] = fb_http('POST', $wp['base'] . '/wp-json/wp/v2/posts/' . $postId, [
            'Authorization: ' . $wp['auth'],
            'Content-Type: application/json',
        ], json_encode(['meta' => [
            '_yoast_wpseo_metadesc' => $metaDescription,
            '_yoast_wpseo_focuskw'  => $focusKeyword,
        ]], JSON_UNESCAPED_UNICODE), 60);
        return $code === 200;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Create the draft post.
 * @param int[] $tagIds
 * @param int[] $categoryIds
 * @return array{id:int, link:string, edit_link:string}
 */
function fb_wp_create_draft(string $title, string $slug, string $html, string $excerpt, int $featuredMediaId = 0, array $tagIds = [], array $categoryIds = []): array {
    $wp = fb_wp_config();
    $post = [
        'status'  => fb_env('WP_POST_STATUS', 'draft'),
        'title'   => $title,
        'slug'    => $slug,
        'content' => $html,
        'excerpt' => $excerpt,
    ];
    if ($featuredMediaId > 0) $post['featured_media'] = $featuredMediaId;
    if ($tagIds !== [])       $post['tags'] = $tagIds;
    if ($categoryIds !== [])  $post['categories'] = $categoryIds;

    [$code, $body] = fb_http('POST', $wp['base'] . '/wp-json/wp/v2/posts', [
        'Authorization: ' . $wp['auth'],
        'Content-Type: application/json',
    ], json_encode($post, JSON_UNESCAPED_UNICODE), 120);

    $resp = json_decode($body, true);
    if (($code !== 201 && $code !== 200) || !is_array($resp) || empty($resp['id'])) {
        throw new RuntimeException("WP post create failed (HTTP {$code}): " . substr($body, 0, 300));
    }
    $id = (int) $resp['id'];
    return [
        'id'        => $id,
        'link'      => (string) ($resp['link'] ?? ''),
        'edit_link' => $wp['base'] . '/wp-admin/post.php?post=' . $id . '&action=edit',
    ];
}
