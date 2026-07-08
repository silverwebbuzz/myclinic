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
require_once __DIR__ . '/_gemini.php';
require_once __DIR__ . '/_wordpress.php';
require_once __DIR__ . '/_render.php';

fb_require_key();
set_time_limit(0);
ignore_user_abort(true);

$day = (int) ($_POST['day'] ?? 0);
$force = ($_POST['force'] ?? '') === '1';
if ($day < 1) fb_json_out(['ok' => false, 'error' => 'Missing day number.'], 400);

$row = null;
foreach (fb_load_calendar() as $r) {
    if ((int) $r['day'] === $day) { $row = $r; break; }
}
if ($row === null) fb_json_out(['ok' => false, 'error' => "Day {$day} not found in blogs.json."], 404);

$state = fb_load_state();
$key = (string) $day;
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
        ?: 'day-' . $day . '-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $row['keyword'])), '-');

    // ---- 2 + 3. Gemini hero image → optimize → WP media (optional) --
    $mediaId = 0;
    $imageError = '';
    $heroLocalFile = '';
    $outDir = __DIR__ . '/output';
    if (!is_dir($outDir)) @mkdir($outDir, 0755, true);
    $heroPrompt = (string) ($blog['image_brief']['hero_prompt'] ?? $row['title']);
    $heroAlt    = (string) ($blog['image_brief']['hero_alt'] ?? $row['title']);
    try {
        $img = fb_image_optimize(fb_gemini_hero($heroPrompt));
        $heroName = 'eclinicpro-' . $slug . '.' . $img['ext'];
        // keep a local copy next to the HTML preview
        file_put_contents($outDir . '/' . $heroName, $img['bytes']);
        $heroLocalFile = $heroName;
        $media = fb_wp_upload_media($img['bytes'], $img['mime'], $heroName, $heroAlt);
        $mediaId = $media['id'];
    } catch (Throwable $e) {
        $imageError = $e->getMessage(); // post still goes out; add image in wp-admin
    }

    // ---- 4. Tags + category + render + create the WP draft ---------
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
        $mediaId,
        $tagIds,
        $catIds
    );

    // Yoast SEO fields — best-effort; excerpt already carries the
    // description if Yoast meta isn't writable over REST on this site.
    $yoastOk = fb_wp_try_yoast($post['id'], $metaDescription, (string) $row['keyword']);

    // ---- 5. Local HTML preview + save state -------------------------
    $previewName = sprintf('day-%02d-%s.html', $day, $slug);
    file_put_contents($outDir . '/' . $previewName, fb_render_preview_page($row, $html, $heroLocalFile));

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
        'image'        => $mediaId > 0 ? 'ok' : ('failed: ' . $imageError),
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
