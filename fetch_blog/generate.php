<?php
// =====================================================================
// fetch_blog/generate.php — AJAX endpoint hit by the "Create" button.
//
//   POST day=N [force=1] [key=TOOL_KEY]
//
// Pipeline for one calendar row:
//   1. Claude writes the guide as structured JSON blocks
//   2. Gemini draws the hero image (optional — failure won't stop us)
//   3. Hero uploaded to the WP media library
//   4. Blocks rendered to HTML → WP post created as DRAFT
//   5. State saved so index.php shows the draft link + review flags
//
// The whole run takes ~1-3 minutes; the listing page keeps the row in
// "Working…" state while this executes.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';
require_once __DIR__ . '/_claude.php';
require_once __DIR__ . '/_wordpress.php';
require_once __DIR__ . '/_render.php';

fb_require_key();
set_time_limit(0);
ignore_user_abort(true);

// Blog id: "1".."90" = 90-day calendar rows, "s1".."s255" = specialty backlog.
$id = (string) ($_POST['id'] ?? $_POST['day'] ?? '');
$force = ($_POST['force'] ?? '') === '1';
if ($id === '' || !preg_match('/^s?\d+$/', $id)) {
    fb_json_out(['ok' => false, 'error' => 'Missing/invalid blog id.'], 400);
}

$row = null;
foreach (fb_load_calendar() as $r) {
    if ((string) ($r['id'] ?? '') === $id) { $row = $r; break; }
}
if ($row === null) fb_json_out(['ok' => false, 'error' => "Blog {$id} not found in blogs.json."], 404);

$state = fb_load_state();
$key = $id;
if (!$force && ($state[$key]['status'] ?? '') === 'created') {
    fb_json_out([
        'ok' => false,
        'error' => 'Already created. Use the "Redo" button to generate a fresh draft.',
        'state' => $state[$key],
    ], 409);
}

try {
    // ---- 1. Claude writes the blog --------------------------------
    $blog = fb_claude_generate($row);
    $meta = is_array($blog['meta'] ?? null) ? $blog['meta'] : [];
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($meta['slug'] ?? ''))), '-')
        ?: 'blog-' . $id . '-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $row['keyword'])), '-');

    // ---- 2. Images are added manually in wp-admin — the rendered HTML
    //         contains visible placeholders with ready alt text.
    $outDir = __DIR__ . '/output';
    if (!is_dir($outDir)) @mkdir($outDir, 0755, true);

    // ---- 3. Tags + category + render + create the WP draft ---------
    $tags = array_values(array_filter((array) ($meta['tags'] ?? []), 'is_string'));
    $tagIds = $tags !== [] ? fb_wp_ensure_terms('tags', $tags) : [];
    $catIds = fb_wp_ensure_terms('categories', [(string) $row['campaign']]);

    $html = fb_render_html($row, $blog);
    $metaDescription = (string) ($meta['meta_description'] ?? '');
    // Calendar title stays the post H1; seo_title/meta go to Yoast below.
    $post = fb_wp_create_draft(
        (string) $row['title'],
        $slug,
        $html,
        $metaDescription,
        0,          // featured image: set manually in wp-admin
        $tagIds,
        $catIds
    );

    // Yoast SEO fields — best-effort; excerpt already carries the
    // description if Yoast meta isn't writable over REST on this site.
    $yoastOk = fb_wp_try_yoast($post['id'], $metaDescription, (string) $row['keyword']);

    // ---- 4. Local HTML preview + save state -------------------------
    $previewName = 'blog-' . $id . '-' . $slug . '.html';
    file_put_contents($outDir . '/' . $previewName, fb_render_preview_page($row, $html));

    $state[$key] = [
        'status'       => 'created',
        'wp_post_id'   => $post['id'],
        'wp_link'      => $post['link'],
        'edit_link'    => $post['edit_link'],
        'preview_file' => $previewName,
        'seo_title'    => (string) ($meta['seo_title'] ?? $row['title']),
        'tags'         => $tags,
        'yoast'        => $yoastOk ? 'ok' : 'not writable — check SEO fields in wp-admin',
        'review_flags' => array_values(array_filter((array) ($blog['review_flags'] ?? []), 'is_string')),
        'created_at'   => date('Y-m-d H:i:s'),
    ];
    fb_save_state($state);

    fb_json_out(['ok' => true, 'state' => $state[$key]]);

} catch (Throwable $e) {
    $state[$key] = [
        'status' => 'error',
        'error'  => $e->getMessage(),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    fb_save_state($state);
    fb_json_out(['ok' => false, 'error' => $e->getMessage(), 'state' => $state[$key]], 500);
}
