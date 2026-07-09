<?php
// =====================================================================
// fetch_blog/index.php — internal dashboard for ALL planned blogs:
//   • 90-day calendar rows (ids "1".."90", carry day/date/city)
//   • 25-specialty master backlog (ids "s1".."s255")
// Grouped SPECIALTY-WISE: a card grid on top (one box per specialty
// with drafted/total counts); clicking a card opens that specialty's
// section with every article title + its own Create button.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_env.php';

fb_require_key();

$rows  = fb_load_calendar();
$state = fb_load_state();
$toolKey = fb_env('TOOL_KEY');

// Group by specialty (alphabetical), track per-group + overall counts.
$groups = [];
foreach ($rows as $r) {
    $spec = (string) ($r['specialty'] ?? $r['campaign'] ?? 'Other');
    $groups[$spec][] = $r;
}
ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

$createdTotal = 0;
$counts = [];
foreach ($groups as $spec => $list) {
    $c = 0;
    foreach ($list as $r) {
        if (($state[(string) $r['id']]['status'] ?? '') === 'created') $c++;
    }
    $counts[$spec] = $c;
    $createdTotal += $c;
}

function fb_spec_anchor(string $spec): string {
    return 'spec-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($spec));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>fetch_blog — blog builder</title>
<style>
  :root { --teal:#0F766E; --border:#dbe7e5; }
  * { box-sizing:border-box; }
  body { font-family:-apple-system,Segoe UI,Roboto,sans-serif; margin:0; background:#f4f8f7; color:#1d2b2a; }
  header { background:var(--teal); color:#fff; padding:16px 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
  header h1 { margin:0; font-size:19px; }
  header .count { font-size:14px; opacity:.9; }
  header a { color:#c8ece6; font-size:13px; }
  main { padding:18px; max-width:1280px; margin:0 auto; }
  .hint { background:#fff8e6; border:1px solid #f1e2b0; border-radius:8px; padding:10px 14px; font-size:13.5px; margin-bottom:14px; }

  .spec-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); gap:10px; margin-bottom:24px; }
  .spec-card { background:#fff; border:1px solid var(--border); border-radius:10px; padding:12px 14px; cursor:pointer; transition:box-shadow .15s; }
  .spec-card:hover { box-shadow:0 2px 10px rgba(15,118,110,.18); border-color:var(--teal); }
  .spec-card h3 { margin:0 0 6px; font-size:14.5px; color:var(--teal); }
  .spec-card .nums { font-size:12.5px; color:#6b7f7c; }
  .spec-card .bar { height:5px; background:#e8f0ee; border-radius:99px; margin-top:8px; overflow:hidden; }
  .spec-card .bar i { display:block; height:100%; background:var(--teal); }

  details.spec { background:#fff; border:1px solid var(--border); border-radius:10px; margin-bottom:12px; }
  details.spec > summary { cursor:pointer; padding:12px 16px; font-weight:700; color:var(--teal); font-size:15px; list-style:none; display:flex; justify-content:space-between; }
  details.spec > summary::-webkit-details-marker { display:none; }
  details.spec > summary .nums { font-weight:400; font-size:13px; color:#6b7f7c; }
  details.spec[open] > summary { border-bottom:1px solid var(--border); }

  table { border-collapse:collapse; width:100%; background:#fff; font-size:13.5px; }
  th, td { border:1px solid var(--border); padding:8px 10px; text-align:left; vertical-align:top; }
  th { background:#e8f2f0; color:#134e4a; }
  tr.done td { background:#f0faf5; }
  tr.err td  { background:#fdf2f2; }
  .title { max-width:420px; }
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
  .b-cal     { background:#e6ecfb; color:#3949ab; }
  .b-type    { background:#f0ebfa; color:#6a4fb3; }
</style>
</head>
<body>
<header>
  <h1>eClinicPro — Blog Builder (<?= count($groups) ?> specialties)</h1>
  <div>
    <span class="count"><?= $createdTotal ?> / <?= count($rows) ?> drafted</span>
    &nbsp; <button id="testBtn" onclick="testConnections()" style="background:#fff;color:var(--teal);">Test connections</button>
    &nbsp; <a href="img_test.php?key=<?= rawurlencode($toolKey) ?>">Image debug</a>
  </div>
</header>
<main>
  <div class="hint" id="testResult" style="display:none;"></div>
  <div class="hint">
    <strong>How it works:</strong> pick a specialty box &rarr; Create &rarr; Claude writes a 2000+ word guide
    &rarr; a <em>draft</em> lands in WordPress with tags, category and image placeholders.
    Each run takes 1&ndash;3 minutes. In wp-admin: replace the placeholder images, set the featured image, and check
    the <strong>review points</strong> (costs, flagged claims) before publishing. First time here? Click <strong>Test connections</strong>.
  </div>

  <div class="spec-grid">
<?php foreach ($groups as $spec => $list): $c = $counts[$spec]; $n = count($list); ?>
    <div class="spec-card" onclick="openSpec('<?= fb_e(fb_spec_anchor($spec)) ?>')">
      <h3><?= fb_e($spec) ?></h3>
      <div class="nums"><?= $c ?> drafted / <?= $n ?> planned</div>
      <div class="bar"><i style="width:<?= $n > 0 ? (int) round($c * 100 / $n) : 0 ?>%"></i></div>
    </div>
<?php endforeach; ?>
  </div>

<?php foreach ($groups as $spec => $list): ?>
  <details class="spec" id="<?= fb_e(fb_spec_anchor($spec)) ?>">
    <summary><?= fb_e($spec) ?> <span class="nums" id="nums-<?= fb_e(fb_spec_anchor($spec)) ?>"><?= $counts[$spec] ?> / <?= count($list) ?> drafted</span></summary>
    <table>
      <tr>
        <th style="width:60px;">ID</th><th style="width:150px;">Info</th>
        <th class="title">Blog title</th><th style="width:120px;">Template</th>
        <th style="width:100px;">Status</th><th style="min-width:220px;">Action / result</th>
      </tr>
<?php foreach ($list as $r):
    $id = (string) $r['id'];
    $s = $state[$id] ?? null;
    $status = $s['status'] ?? 'planned';
    $trClass = $status === 'created' ? 'done' : ($status === 'error' ? 'err' : '');
?>
      <tr id="row-<?= fb_e($id) ?>" class="<?= $trClass ?>">
        <td><?= fb_e($id) ?></td>
        <td>
          <?php if (($r['source'] ?? '') === 'calendar'): ?>
            <span class="badge b-cal">Day <?= (int) $r['day'] ?></span><br>
            <span class="kw"><?= fb_e((string) $r['date']) ?> · <?= fb_e((string) $r['city']) ?></span>
          <?php else: ?>
            <?php if (($r['blog_type'] ?? '') !== ''): ?><span class="badge b-type"><?= fb_e((string) $r['blog_type']) ?></span><br><?php endif; ?>
            <span class="kw"><?= fb_e((string) ($r['tier'] ?? '')) ?></span>
          <?php endif; ?>
        </td>
        <td class="title"><?= fb_e((string) $r['title']) ?><br>
          <span class="kw">kw: <?= fb_e((string) $r['keyword']) ?><?= ($r['secondary_keyword'] ?? '') !== '' ? ' · ' . fb_e((string) $r['secondary_keyword']) : '' ?></span>
        </td>
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
              <button class="redo" onclick="createBlog('<?= fb_e($id) ?>', true)">Redo</button>
            </div>
            <div class="kw">Replace image placeholders + set featured image in wp-admin.</div>
            <?php if (!empty($s['review_flags'])): ?>
              <ul class="flags"><?php foreach ((array) $s['review_flags'] as $f): ?><li><?= fb_e((string) $f) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          <?php elseif ($status === 'error'): ?>
            <button onclick="createBlog('<?= fb_e($id) ?>', false)">Retry</button>
            <div class="errmsg"><?= fb_e((string) ($s['error'] ?? '')) ?></div>
          <?php else: ?>
            <button onclick="createBlog('<?= fb_e($id) ?>', false)">Create</button>
          <?php endif; ?>
        </td>
      </tr>
<?php endforeach; ?>
    </table>
  </details>
<?php endforeach; ?>
</main>
<script>
const TOOL_KEY = <?= json_encode($toolKey) ?>;
let busy = false;

function openSpec(anchor) {
  const d = document.getElementById(anchor);
  if (!d) return;
  d.open = true;
  d.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function createBlog(id, force) {
  if (busy) { alert('One blog is already generating — wait for it to finish.'); return; }
  if (force && !confirm('Redo will create a NEW draft in WordPress (the old draft stays — delete it there). Continue?')) return;
  busy = true;
  const row = document.getElementById('row-' + id);
  const statusCell = row.querySelector('.status');
  const actionCell = row.querySelector('.action');
  const oldAction = actionCell.innerHTML;
  statusCell.innerHTML = '<span class="badge b-working">Working…</span>';
  actionCell.innerHTML = 'Claude is writing… (1–3 min)';

  try {
    const body = new URLSearchParams({ id: String(id), key: TOOL_KEY });
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
        + '<button class="redo" onclick="createBlog(\'' + esc(String(id)) + '\', true)">Redo</button></div>';
      html += '<div class="kw">Replace image placeholders + set featured image in wp-admin.</div>';
      if (s.review_flags && s.review_flags.length) {
        html += '<ul class="flags">' + s.review_flags.map(f => '<li>' + esc(f) + '</li>').join('') + '</ul>';
      }
      actionCell.innerHTML = html;
    } else {
      row.className = 'err';
      statusCell.innerHTML = '<span class="badge b-error">Error</span>';
      actionCell.innerHTML = '<button onclick="createBlog(\'' + esc(String(id)) + '\', ' + force + ')">Retry</button>'
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
