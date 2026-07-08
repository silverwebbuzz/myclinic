<?php
// =====================================================================
// fetch_blog/_gemini.php — hero image via the Gemini API image model.
// One fixed brand-style prompt is reused across all 90 guides so the
// blog keeps a consistent look (per the AI-stack plan); only the
// condition theme (from Claude's image_brief) changes per post.
//
// Compliance guardrails baked into the prompt: illustrative style,
// no text inside the image, no anatomy labels, no real-patient or
// before/after look (risky under Indian medical advertising norms).
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';

const FB_GEMINI_STYLE =
    'Wide 16:9 hero illustration for a medical patient-education blog. '
    . 'Clean modern flat illustration style, soft teal (#0F766E) and warm off-white palette, '
    . 'gentle rounded shapes, calm and reassuring mood, Indian everyday-life context. '
    . 'Strictly NO text, NO letters, NO numbers, NO logos, NO watermarks inside the image. '
    . 'NO labeled anatomy diagrams, NO realistic patient faces, NO before/after comparison, NO gore. '
    . 'Theme: ';

/**
 * Generate the hero image. Returns ['bytes' => raw, 'mime' => 'image/png'].
 * Throws on hard failure — caller treats the image as optional.
 */
function fb_gemini_hero(string $themePrompt): array {
    $apiKey = fb_env('GEMINI_API_KEY');
    if ($apiKey === '') throw new RuntimeException('GEMINI_API_KEY is empty in fetch_blog/.env');
    $model = fb_env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image');

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . rawurlencode($model) . ':generateContent';

    $payload = [
        'contents' => [[
            'parts' => [['text' => FB_GEMINI_STYLE . $themePrompt]],
        ]],
    ];

    [$code, $body] = fb_http('POST', $url, [
        'x-goog-api-key: ' . $apiKey,
        'content-type: application/json',
    ], json_encode($payload, JSON_UNESCAPED_UNICODE), 180);

    $resp = json_decode($body, true);
    if ($code !== 200 || !is_array($resp)) {
        $msg = is_array($resp) ? ($resp['error']['message'] ?? $body) : $body;
        throw new RuntimeException("Gemini API error (HTTP {$code}): " . substr((string) $msg, 0, 300));
    }

    foreach ($resp['candidates'][0]['content']['parts'] ?? [] as $part) {
        $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
        if (is_array($inline) && !empty($inline['data'])) {
            $bytes = base64_decode((string) $inline['data'], true);
            if ($bytes === false || $bytes === '') break;
            return [
                'bytes' => $bytes,
                'mime'  => (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png'),
            ];
        }
    }
    throw new RuntimeException('Gemini returned no image data (model may have refused the prompt).');
}

/**
 * Shrink + recompress the raw Gemini image so blog pages load fast:
 * resize to max 1280px wide, then encode as WebP (best size/quality;
 * WordPress 5.8+ and all modern browsers support it) with JPEG as
 * fallback when the server's GD has no WebP support. Raw Gemini PNGs
 * are typically 1.5–2.5 MB; this brings the hero to ~60–150 KB.
 *
 * @param array{bytes:string, mime:string} $img
 * @return array{bytes:string, mime:string, ext:string}
 */
function fb_image_optimize(array $img): array {
    if (!function_exists('imagecreatefromstring')) {
        // GD missing on this server — upload as-is rather than fail.
        $ext = str_contains($img['mime'], 'jpeg') ? 'jpg' : 'png';
        return ['bytes' => $img['bytes'], 'mime' => $img['mime'], 'ext' => $ext];
    }
    $src = @imagecreatefromstring($img['bytes']);
    if ($src === false) {
        $ext = str_contains($img['mime'], 'jpeg') ? 'jpg' : 'png';
        return ['bytes' => $img['bytes'], 'mime' => $img['mime'], 'ext' => $ext];
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $maxW = 1280;
    if ($w > $maxW) {
        $newW = $maxW;
        $newH = (int) round($h * $maxW / $w);
        $dst = imagecreatetruecolor($newW, $newH);
        // flatten any transparency onto white (needed for JPEG anyway)
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    ob_start();
    if (function_exists('imagewebp') && imagewebp($src, null, 82)) {
        $bytes = (string) ob_get_clean();
        imagedestroy($src);
        return ['bytes' => $bytes, 'mime' => 'image/webp', 'ext' => 'webp'];
    }
    ob_end_clean();

    ob_start();
    imagejpeg($src, null, 82);
    $bytes = (string) ob_get_clean();
    imagedestroy($src);
    return ['bytes' => $bytes, 'mime' => 'image/jpeg', 'ext' => 'jpg'];
}
