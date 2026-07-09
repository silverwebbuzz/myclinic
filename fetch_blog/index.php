<?php
// =====================================================================
// fetch_blog/index.php — internal dashboard listing all 90 planned
// blogs from the 90-day workbook. Each row has a "Create" button that
// calls generate.php (Claude text → Gemini hero → WordPress draft).
// You then review + publish inside wp-admin.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';

fb_require_key();

$rows  = fb_load_calendar();
$state = fb_load_state();
$toolKey = fb_env('TOOL_KEY');
$created = 0;
foreach ($state as $s) { if (($s['status'] ?? '') === 'created') $created++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>fetch_blog — 90-day blog builder</title>
<style>
  :root { --teal:#0F766E; --border:#dbe7e5; }
  * { box-sizing:border-box; }
  body { font-family:-apple-system,Segoe UI,Roboto,sans-serif; margin:0; background:#f4f8f7; color:#1d2b2a; }
  header { background:var(--teal); color:#fff; padding:16px 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
  header h1 { margin:0; font-size:19px; }
  header .count { font-size:14px; opacity:.9; }
  main { padding:18px; max-width:1280px; margin:0 auto; }
  .hint { background:#fff8e6; border:1px solid #f1e2b0; border-radius:8px; padding:10px 14px; font-size:13.5px; margin-bottom:14px; }
  table { border-collapse:collapse; width:100%; background:#fff; font-size:13.5px; }
  th, td { border:1px solid var(--border); padding:8px 10px; text-align:left; vertical-align:top; }
  th { background:#e8f2f0; color:#134e4a; position:sticky; top:0; }
  tr.done td { background:#f0faf5; }
  tr.err td  { background:#fdf2f2; }
  .title { max-width:380px; }
  .kw { color:#6b7f7c; font-size:12px; }
  button { background:var(--teal); color:#fff; border:0; border-radius:6px; padding:7px 14px; cursor:pointer; font-size:13px; }
  button.redo { background:#8a6d1a; }
  button:disabled { background:#9bb5b1; cursor:wait; }
  .links a { display:inline-block; margin-right:10px; font-size:12.5px; color:var(--teal); }
  .flags { margin:6px 0 0; padding-left:16px; color:#8a6d1a; font-size:12px; }
  .errmsg { color:#b91c1c; font-size:12.5px; }
  .badge { display:inline-block; border-radius:999px; padding:2px 10px; font-size:11.5px; font-weight:700; }
  .b-planned { background:#e8eef2; color:#3f5a68; }
  .b-created { background:#d7f0e2; color:#166534; }
  .b-error   { background:#fbdcdc; color:#b91c1c; }
  .b-working { background:#fdf0cd; color:#8a6d1a; }
</style>
</head>
<body>
<header>
  <h1>eClinicPro — 90-Day Blog Builder</h1>
  <div>
    <span class="count"><?= $created ?> / <?= count($rows) ?> drafted</span>
    &nbsp; <button id="testBtn" onclick="testConnections()" style="background:#fff;color:var(--teal);">Test connections</button>
    &nbsp; <a href="img_test.php?key=<?= rawurlencode($toolKey) ?>" style="color:#c8ece6;font-size:13px;">Image debug</a>
  </div>
</header>
<main>
  <div class="hint" id="testResult" style="display:none;"></div>
  <div class="hint">
    <strong>How it works:</strong> Create &rarr; Claude writes the guide (medically-safe wording, simple English)
    &rarr; Gemini draws the hero image &rarr; a <em>draft</em> lands in WordPress.
    Each run takes 1&ndash;3 minutes. Always check the <strong>review points</strong> (costs, flagged claims) in
    wp-admin before you publish. First time here? Click <strong>Test connections</strong>.
  </div>
  <table>
    <tr>
      <th>#</th><th>Date</th><th>City</th><th>Campaign</th>
      <th class="title">Blog title</th><th>Template</th><th>Status</th><th style="min-width:210px;">Action / result</th>
    </tr>
<?php foreach ($rows as $r):
    $d = (int) $r['day'];
    $s = $state[(string) $d] ?? null;
    $status = $s['status'] ?? 'planned';
    $trClass = $status === 'created' ? 'done' : ($status === 'error' ? 'err' : '');
?>
    <tr id="row-<?= $d ?>" class="<?= $trClass ?>" data-day="<?= $d ?>">
      <td><?= $d ?></td>
      <td><?= fb_e((string) $r['date']) ?><br><span class="kw"><?= fb_e((string) $r['weekday']) ?></span></td>
      <td><?= fb_e((string) $r['city']) ?></td>
      <td><?= fb_e((string) $r['campaign']) ?></td>
      <td class="title"><?= fb_e((string) $r['title']) ?><br><span class="kw">kw: <?= fb_e((string) $r['keyword']) ?></span></td>
      <td><?= fb_e((string) $r['template']) ?></td>
      <td class="status">
        <?php if ($status === 'created'): ?><span class="badge b-created">Draft ready</span>
        <?php elseif ($status === 'error'): ?><span class="badge b-error">Error</span>
        <?php else: ?><span class="badge b-planned">Planned</span><?php endif; ?>
      </td>
      <td class="action">
        <?php if ($status === 'created'): ?>
          <div class="links">
            <a href="<?= fb_e((string) ($s['edit_link'] ?? '#')) ?>" target="_blank" rel="noopener">Edit in WP</a>
            <a href="<?= fb_e((string) ($s['wp_link'] ?? '#')) ?>" target="_blank" rel="noopener">WP preview</a>
            <?php if (!empty($s['preview_file'])): ?>
              <a href="output/<?= fb_e((string) $s['preview_file']) ?>" target="_blank" rel="noopener">Local HTML</a>
            <?php endif; ?>
            <button class="redo" onclick="createBlog(<?= $d ?>, true)">Redo</button>
          </div>
          <?php if (($s['image'] ?? '') !== 'ok'): ?><div class="errmsg">Hero image failed — add one in wp-admin.</div><?php endif; ?>
          <?php if (!empty($s['review_flags'])): ?>
            <ul class="flags"><?php foreach ((array) $s['review_flags'] as $f): ?><li><?= fb_e((string) $f) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        <?php elseif ($status === 'error'): ?>
          <button onclick="createBlog(<?= $d ?>, false)">Retry</button>
          <div class="errmsg"><?= fb_e((string) ($s['error'] ?? '')) ?></div>
        <?php else: ?>
          <button onclick="createBlog(<?= $d ?>, false)">Create</button>
        <?php endif; ?>
      </td>
    </tr>
<?php endforeach; ?>
  </table>
</main>
<script>
const TOOL_KEY = <?= json_encode($toolKey) ?>;
let busy = false;

async function createBlog(day, force) {
  if (busy) { alert('One blog is already generating — wait for it to finish.'); return; }
  if (force && !confirm('Redo will create a NEW draft in WordPress (the old draft stays — delete it there). Continue?')) return;
  busy = true;
  const row = document.getElementById('row-' + day);
  const statusCell = row.querySelector('.status');
  const actionCell = row.querySelector('.action');
  const oldAction = actionCell.innerHTML;
  statusCell.innerHTML = '<span class="badge b-working">Working…</span>';
  actionCell.innerHTML = 'Claude is writing, Gemini is drawing… (1–3 min)';

  try {
    const body = new URLSearchParams({ day: String(day), key: TOOL_KEY });
    if (force) body.set('force', '1');
    const res = await fetch('generate.php', { method: 'POST', body });
    const data = await res.json();

    if (data.ok) {
      const s = data.state;
      row.className = 'done';
      statusCell.innerHTML = '<span class="badge b-created">Draft ready</span>';
      let html = '<div class="links">'
        + '<a href="' + esc(s.edit_link) + '" target="_blank" rel="noopener">Edit in WP</a>'
        + '<a href="' + esc(s.wp_link) + '" target="_blank" rel="noopener">WP preview</a>'
        + (s.preview_file ? '<a href="output/' + esc(s.preview_file) + '" target="_blank" rel="noopener">Local HTML</a>' : '')
        + '<button class="redo" onclick="createBlog(' + day + ', true)">Redo</button></div>';
      if (s.image !== 'ok') html += '<div class="errmsg">Hero image failed — add one in wp-admin.</div>';
      if (s.review_flags && s.review_flags.length) {
        html += '<ul class="flags">' + s.review_flags.map(f => '<li>' + esc(f) + '</li>').join('') + '</ul>';
      }
      actionCell.innerHTML = html;
    } else {
      row.className = 'err';
      statusCell.innerHTML = '<span class="badge b-error">Error</span>';
      actionCell.innerHTML = '<button onclick="createBlog(' + day + ', ' + force + ')">Retry</button>'
        + '<div class="errmsg">' + esc(data.error || 'Unknown error') + '</div>';
    }
  } catch (e) {
    row.className = 'err';
    statusCell.innerHTML = '<span class="badge b-error">Error</span>';
    actionCell.innerHTML = oldAction;
    alert('Request failed: ' + e.message + '\nCheck the row again — the draft may still have been created.');
  } finally {
    busy = false;
  }
}

async function testConnections() {
  const btn = document.getElementById('testBtn');
  const box = document.getElementById('testResult');
  btn.disabled = true;
  box.style.display = 'block';
  box.innerHTML = 'Testing Claude, Gemini and WordPress…';
  try {
    const res = await fetch('test.php', { method: 'POST', body: new URLSearchParams({ key: TOOL_KEY }) });
    const data = await res.json();
    box.innerHTML = data.checks.map(c =>
      (c.ok ? '&#9989;' : '&#10060;') + ' <strong>' + esc(c.name) + ':</strong> ' + esc(c.msg)
    ).join('<br>');
  } catch (e) {
    box.innerHTML = '&#10060; Test request failed: ' + esc(e.message);
  } finally {
    btn.disabled = false;
  }
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
</script>
</body>
</html>
