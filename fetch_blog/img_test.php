<?php
// =====================================================================
// fetch_blog/img_test.php — Gemini image DEBUG page.
// Open as:  /fetch_blog/img_test.php?key=YOUR_TOOL_KEY
//
// Shows, step by step, exactly where image generation breaks:
//   0. .env + PHP capability check (curl, GD, WebP)
//   1. Is the API key valid at all? (cheap models-list call)
//   2. Which image-capable models does YOUR key actually see?
//   3. Three generateContent attempts with different generationConfig
//      (none / TEXT+IMAGE / IMAGE) — full HTTP code, block reasons,
//      part types returned.
//   4. Any image found is saved into fetch_blog/img/ and displayed.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';
require_once __DIR__ . '/_gemini.php';

fb_require_key();
set_time_limit(0);

$imgDir = __DIR__ . '/img';
if (!is_dir($imgDir)) @mkdir($imgDir, 0755, true);

$apiKey = fb_env('GEMINI_API_KEY');
$model  = fb_env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image');
$prompt = trim((string) ($_POST['prompt'] ?? ''));
$run    = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($prompt === '') {
    $prompt = 'Flat illustration of a person drinking warm water on a rainy Indian monsoon day, soft teal palette, no text';
}

/** One raw generateContent attempt. Returns a report array. */
function fb_dbg_attempt(string $apiKey, string $model, string $prompt, ?array $modalities): array {
    $r = ['label' => $modalities === null ? 'no generationConfig' : 'responseModalities: [' . implode(',', $modalities) . ']'];
    $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];
    if ($modalities !== null) $payload['generationConfig'] = ['responseModalities' => $modalities];
    try {
        [$code, $body] = fb_http('POST',
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
            ['x-goog-api-key: ' . $apiKey, 'content-type: application/json'],
            json_encode($payload), 180);
        $r['http'] = $code;
        $resp = json_decode($body, true);
        if (!is_array($resp)) { $r['error'] = 'Non-JSON reply: ' . substr($body, 0, 300); return $r; }
        if ($code !== 200) { $r['error'] = (string) ($resp['error']['message'] ?? substr($body, 0, 300)); return $r; }

        if (!empty($resp['promptFeedback']['blockReason'])) $r['blockReason'] = $resp['promptFeedback']['blockReason'];
        $cand = $resp['candidates'][0] ?? [];
        if (!empty($cand['finishReason'])) $r['finishReason'] = $cand['finishReason'];

        $types = [];
        foreach ($cand['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) { $types[] = 'text'; $r['text_said'] = substr((string) $part['text'], 0, 200); }
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (is_array($inline) && !empty($inline['data'])) {
                $types[] = 'image(' . ($inline['mimeType'] ?? $inline['mime_type'] ?? '?') . ')';
                if (empty($r['image_bytes'])) {
                    $bytes = base64_decode((string) $inline['data'], true);
                    if ($bytes !== false && $bytes !== '') {
                        $r['image_bytes'] = $bytes;
                        $r['image_mime']  = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png');
                    }
                }
            }
        }
        $r['parts'] = $types ? implode(', ', $types) : 'NONE';
    } catch (Throwable $e) {
        $r['error'] = $e->getMessage();
    }
    return $r;
}

// ---------------------------------------------------------------------
$reports = [];
$modelsInfo = '';
$keyCheck = '';

if ($run) {
    // 1. key check
    try {
        [$code, $body] = fb_http('GET', 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1',
            ['x-goog-api-key: ' . $apiKey], null, 30);
        $keyCheck = $code === 200
            ? 'OK — key is accepted by the Gemini API.'
            : 'FAILED (HTTP ' . $code . '): ' . substr((string) (json_decode($body, true)['error']['message'] ?? $body), 0, 300);
    } catch (Throwable $e) {
        $keyCheck = 'FAILED (network): ' . $e->getMessage() . ' — the server may block outbound HTTPS to googleapis.com.';
    }

    // 2. image-capable models visible to this key
    try {
        [$code, $body] = fb_http('GET', 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200',
            ['x-goog-api-key: ' . $apiKey], null, 30);
        $names = [];
        foreach (json_decode($body, true)['models'] ?? [] as $m) {
            $n = (string) ($m['name'] ?? '');
            if (stripos($n, 'image') !== false) $names[] = str_replace('models/', '', $n);
        }
        $modelsInfo = $names ? implode("\n", $names) : '(none found — HTTP ' . $code . ')';
    } catch (Throwable $e) {
        $modelsInfo = 'lookup failed: ' . $e->getMessage();
    }

    // 3. three attempts
    if ($apiKey !== '') {
        $reports[] = fb_dbg_attempt($apiKey, $model, $prompt, null);
        $reports[] = fb_dbg_attempt($apiKey, $model, $prompt, ['TEXT', 'IMAGE']);
        $reports[] = fb_dbg_attempt($apiKey, $model, $prompt, ['IMAGE']);
    }

    // 4. save any images found
    foreach ($reports as $i => &$r) {
        if (!empty($r['image_bytes'])) {
            $opt = fb_image_optimize(['bytes' => $r['image_bytes'], 'mime' => $r['image_mime']]);
            $file = 'eclinicpro-test-' . date('Ymd-His') . '-v' . ($i + 1) . '.' . $opt['ext'];
            file_put_contents($imgDir . '/' . $file, $opt['bytes']);
            $r['saved'] = $file;
            $r['saved_kb'] = (int) round(strlen($opt['bytes']) / 1024);
            $r['raw_kb'] = (int) round(strlen($r['image_bytes']) / 1024);
        }
        unset($r['image_bytes']);
    }
    unset($r);
}

$existing = is_dir($imgDir) ? array_values(array_diff(scandir($imgDir) ?: [], ['.', '..'])) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gemini image debug — fetch_blog</title>
<style>
  body { font-family:-apple-system,Segoe UI,Roboto,sans-serif; margin:0; background:#f4f8f7; color:#1d2b2a; }
  header { background:#0F766E; color:#fff; padding:14px 22px; }
  header h1 { margin:0; font-size:18px; }
  main { max-width:900px; margin:0 auto; padding:18px; }
  .card { background:#fff; border:1px solid #dbe7e5; border-radius:10px; padding:16px 18px; margin-bottom:16px; }
  .card h2 { margin:0 0 10px; font-size:16px; color:#0F766E; }
  pre { background:#f0f5f4; padding:10px 12px; border-radius:8px; font-size:12.5px; overflow-x:auto; white-space:pre-wrap; }
  .ok { color:#166534; font-weight:700; } .bad { color:#b91c1c; font-weight:700; }
  textarea { width:100%; min-height:70px; padding:10px; border:1px solid #dbe7e5; border-radius:8px; font-size:14px; }
  button { background:#0F766E; color:#fff; border:0; border-radius:6px; padding:9px 20px; cursor:pointer; font-size:14px; margin-top:8px; }
  img.result { max-width:100%; border-radius:8px; border:1px solid #dbe7e5; margin-top:8px; }
  table { border-collapse:collapse; width:100%; font-size:13px; }
  td, th { border:1px solid #dbe7e5; padding:6px 10px; text-align:left; vertical-align:top; }
  a { color:#0F766E; }
</style>
</head>
<body>
<header><h1>Gemini image debug — <a href="index.php?key=<?= rawurlencode(fb_env('TOOL_KEY')) ?>" style="color:#c8ece6;">back to dashboard</a></h1></header>
<main>

  <div class="card">
    <h2>0) Environment</h2>
    <table>
      <tr><th>GEMINI_API_KEY</th><td><?= $apiKey === '' ? '<span class="bad">EMPTY — fill it in fetch_blog/.env</span>'
          : '<span class="ok">set</span> (' . fb_e(substr($apiKey, 0, 6)) . '…, ' . strlen($apiKey) . ' chars)' ?></td></tr>
      <tr><th>GEMINI_IMAGE_MODEL</th><td><?= fb_e($model) ?></td></tr>
      <tr><th>PHP curl</th><td><?= function_exists('curl_init') ? '<span class="ok">available</span>' : '<span class="bad">MISSING</span>' ?></td></tr>
      <tr><th>PHP GD (resize)</th><td><?= function_exists('imagecreatefromstring') ? '<span class="ok">available</span>' : '<span class="bad">missing — images upload unoptimized</span>' ?></td></tr>
      <tr><th>WebP encode</th><td><?= function_exists('imagewebp') ? '<span class="ok">available</span>' : 'missing — JPEG fallback will be used' ?></td></tr>
      <tr><th>img/ folder writable</th><td><?= is_writable($imgDir) ? '<span class="ok">yes</span>' : '<span class="bad">NO — chmod 755/775 fetch_blog/img</span>' ?></td></tr>
    </table>
  </div>

  <div class="card">
    <h2>Run a test generation</h2>
    <form method="post">
      <input type="hidden" name="key" value="<?= fb_e(fb_env('TOOL_KEY')) ?>">
      <textarea name="prompt"><?= fb_e($prompt) ?></textarea>
      <button type="submit">Test image generation</button>
    </form>
  </div>

<?php if ($run): ?>
  <div class="card">
    <h2>1) API key check</h2>
    <pre><?= fb_e($keyCheck) ?></pre>
  </div>

  <div class="card">
    <h2>2) Image models your key can see</h2>
    <p style="font-size:13px;">If your GEMINI_IMAGE_MODEL is not in this list, change it in .env to one that is.</p>
    <pre><?= fb_e($modelsInfo) ?></pre>
  </div>

  <?php foreach ($reports as $i => $r): ?>
  <div class="card">
    <h2>3.<?= $i + 1 ?>) Attempt — <?= fb_e($r['label']) ?></h2>
    <table>
      <?php if (isset($r['http'])): ?><tr><th>HTTP</th><td><?= (int) $r['http'] ?></td></tr><?php endif; ?>
      <?php if (isset($r['error'])): ?><tr><th>Error</th><td class="bad"><?= fb_e($r['error']) ?></td></tr><?php endif; ?>
      <?php if (isset($r['blockReason'])): ?><tr><th>blockReason</th><td class="bad"><?= fb_e($r['blockReason']) ?> (prompt was blocked — adjust wording)</td></tr><?php endif; ?>
      <?php if (isset($r['finishReason'])): ?><tr><th>finishReason</th><td><?= fb_e($r['finishReason']) ?></td></tr><?php endif; ?>
      <?php if (isset($r['parts'])): ?><tr><th>Parts returned</th><td><?= fb_e($r['parts']) ?></td></tr><?php endif; ?>
      <?php if (isset($r['text_said'])): ?><tr><th>Model text</th><td><?= fb_e($r['text_said']) ?></td></tr><?php endif; ?>
      <?php if (isset($r['saved'])): ?>
        <tr><th>Saved</th><td class="ok">img/<?= fb_e($r['saved']) ?> — <?= (int) $r['saved_kb'] ?> KB optimized (raw <?= (int) $r['raw_kb'] ?> KB)</td></tr>
      <?php endif; ?>
    </table>
    <?php if (isset($r['saved'])): ?><img class="result" src="img/<?= fb_e($r['saved']) ?>" alt="test result"><?php endif; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

  <div class="card">
    <h2>Previously saved test images (fetch_blog/img/)</h2>
    <?php if ($existing === []): ?><p>None yet.</p>
    <?php else: ?><ul><?php foreach ($existing as $f): ?>
      <li><a href="img/<?= fb_e($f) ?>" target="_blank"><?= fb_e($f) ?></a></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>

</main>
</body>
</html>
