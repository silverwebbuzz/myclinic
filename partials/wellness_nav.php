<?php
// =====================================================================
// partials/wellness_nav.php — the ONE Wellness Hub section menu.
//
// Same items, order and look on every Wellness Hub page (hub, recipes /
// exercises / tips / videos, camps, events, posts, creator profiles,
// search, saved). Put it right after <main>.
//
// Set before require (both optional):
//   $whNavActive  'home' | 'camps' | 'events' | 'recipes' | 'exercises' | 'tips' | 'videos' | 'saved' | ''
//   $whNavCity    city slug — keeps the city when switching camps ↔ events
// Styles: assets/css/wellness-nav.css (linked here, so every page gets it).
// =====================================================================

$whNavActive = (string) ($whNavActive ?? '');
$whNavCity = isset($whNavCity) && preg_match('/^[a-z0-9\-]{1,80}$/', (string) $whNavCity) ? (string) $whNavCity : '';
$whNavItems = [
    'home'      => ['/wellness', '🌿', 'Wellness Hub'],
    'camps'     => ['/wellness/camps' . ($whNavCity !== '' ? '/' . $whNavCity : ''), '🩺', 'Free camps'],
    'events'    => ['/wellness/events' . ($whNavCity !== '' ? '/' . $whNavCity : ''), '📅', 'Events'],
    'recipes'   => ['/wellness/recipes', '🥗', 'Recipes'],
    'exercises' => ['/wellness/exercises', '🧘', 'Exercises'],
    'tips'      => ['/wellness/tips', '💡', 'Quick tips'],
    'videos'    => ['/wellness/videos', '▶️', 'Videos'],
    'saved'     => ['/wellness/saved', '♥', 'Saved'],
];
?>
<link rel="stylesheet" href="/assets/css/wellness-nav.css?v=<?= @filemtime(__DIR__ . '/../assets/css/wellness-nav.css') ?: 1 ?>">
<nav class="whn" aria-label="Wellness Hub sections">
  <div class="wrap whn-inner">
    <?php foreach ($whNavItems as $key => [$href, $icon, $label]): $on = $key === $whNavActive; ?>
      <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="whn-item <?= $key === 'home' ? 'is-home' : '' ?> <?= $on ? 'is-active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>>
        <span class="whn-ic" aria-hidden="true"><?= $icon ?></span><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
      </a>
      <?php if ($key === 'home'): ?><span class="whn-sep" aria-hidden="true"></span><?php endif; ?>
    <?php endforeach; ?>
    <a href="/wellness/search" class="whn-item whn-search <?= $whNavActive === 'search' ? 'is-active' : '' ?>" aria-label="Search the Wellness Hub"><span class="whn-ic" aria-hidden="true">🔍</span><span>Search</span></a>
  </div>
</nav>
<script>
  // Phones: bring the current section into view inside the scrolling menu.
  (function () {
    var bar = document.currentScript.previousElementSibling.querySelector('.whn-inner');
    var on = bar && bar.querySelector('.is-active');
    if (on && bar.scrollWidth > bar.clientWidth) {
      bar.scrollLeft = Math.max(0, on.offsetLeft - (bar.clientWidth - on.offsetWidth) / 2);
    }
  })();
</script>
