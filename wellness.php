<?php
// =====================================================================
// wellness.php — eClinicPro Wellness Hub (Phase 3).
//
//   /wellness                    hub home (?city= filters the camps strip)
//   /wellness/search?q=&tag=     search recipes, exercises, tips, videos, camps, events
//   /wellness/saved              a signed-in patient's saved items (private)
//   /wellness/creators/{slug}    a Health Creator's public profile
//
// Sections live elsewhere: health-tips.php (/wellness/recipes…) and
// wellness-events.php (/wellness/camps…, /wellness/events…).
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';
require_once __DIR__ . '/partials/health_tips.php';
require_once __DIR__ . '/partials/wellness_events.php';

$view = (string) ($_GET['_wh_page'] ?? 'home');
$view = in_array($view, ['home', 'search', 'saved', 'creator'], true) ? $view : 'home';
$viewer = ecp_patient_current();
$db = ecp_db();
$activePage = 'wellness';
$disclaimer = 'Everything on the Wellness Hub is shared by independent Health Creators and organisers for general information — not medical advice. eClinicPro does not guarantee any health result. For your own health condition, consult a doctor.';
$creatorJoinUrl = ecp_portal_url('/creator/login');

/** Creator row by slug (active), with counts. */
function wh_creator(string $slug): ?array
{
    $db = ecp_db();
    if (!$db || !preg_match('/^[a-z0-9\-]{1,80}$/', $slug)) {
        return null;
    }
    try {
        $st = $db->prepare("SELECT * FROM wh_creators WHERE slug = :s AND status = 'active' LIMIT 1");
        $st->execute(['s' => $slug]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Creators with something public, most content first. @return list<array<string,mixed>> */
function wh_featured_creators(int $limit): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        return $db->query(
            "SELECT w.slug, w.display_name, w.headline, w.creator_type, w.verify_status, w.avatar_path, w.city,
                    (SELECT COUNT(*) FROM health_tips t WHERE t.contributor_id = w.legacy_contributor_id AND t.status = 'published' AND t.is_deleted = 0)
                  + (SELECT COUNT(*) FROM wh_events e WHERE e.creator_id = w.id AND e.status = 'published' AND e.is_deleted = 0 AND e.end_at >= NOW()) AS n
             FROM wh_creators w
             WHERE w.status = 'active' AND w.creator_type <> 'platform'
             HAVING n > 0 ORDER BY (w.verify_status = 'verified') DESC, n DESC LIMIT " . max(1, min(24, $limit))
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function wh_badge(array $c): ?string
{
    if (($c['verify_status'] ?? '') !== 'verified' || ($c['creator_type'] ?? '') === 'platform') {
        return null;
    }
    return ($c['creator_type'] ?? '') === 'doctor' ? 'Verified Doctor' : 'Verified Organisation';
}

const WH_TYPE_SHORT = ['individual' => 'Health Creator', 'doctor' => 'Doctor', 'clinic' => 'Clinic / Hospital', 'ngo' => 'NGO / Trust', 'government' => 'Government', 'company' => 'Organiser', 'platform' => 'eClinicPro'];

// ---------------------------------------------------------------------
// Data + meta per view
// ---------------------------------------------------------------------
if ($view === 'creator') {
    $creator = wh_creator(strtolower((string) ($_GET['_wh_creator'] ?? '')));
    if (!$creator) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return;
    }
    $tips = !empty($creator['legacy_contributor_id'])
        ? ecp_ht_public_list(['contributor_id' => (int) $creator['legacy_contributor_id'], 'limit' => 48])
        : ['rows' => [], 'total' => 0];
    $events = ecp_we_list(['creator_id' => (int) $creator['id'], 'limit' => 24]);
    $badge = wh_badge($creator);
    $pageTitle = $creator['display_name'] . ($badge ? ' (' . $badge . ')' : '') . ' — recipes, health tips & camps | eClinicPro Wellness Hub';
    $metaDesc = (string) ($creator['headline'] ?: ($creator['display_name'] . ' on the eClinicPro Wellness Hub: recipes, exercises, health tips and health camps.'));
    $canonicalUrl = ecp_site_url('/wellness/creators/' . $creator['slug']);
    $ogType = 'profile';
    if (!empty($creator['avatar_path'])) {
        $ogImage = ecp_site_url(ecp_we_avatar_url((string) $creator['slug']));
    }
    if ($tips['total'] + $events['total'] === 0) {
        $noindex = true;   // nothing public yet: keep thin pages out of the index
    }
} elseif ($view === 'saved') {
    $pageTitle = 'Saved — eClinicPro Wellness Hub';
    $metaDesc = 'Recipes, exercises, tips, camps and events you saved on eClinicPro.';
    $noindex = true;
    header('Cache-Control: private, no-store, max-age=0');
    $savedTips = $viewer ? ecp_ht_public_list(['saved_by' => (int) $viewer['id'], 'limit' => 60]) : ['rows' => [], 'total' => 0];
    $savedEvents = $viewer ? ecp_we_saved((int) $viewer['id']) : [];
} elseif ($view === 'search') {
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
    $tag = preg_match('/^[a-z0-9\-]{1,60}$/', (string) ($_GET['tag'] ?? '')) ? (string) $_GET['tag'] : '';
    $pageTitle = ($q !== '' ? '“' . $q . '” — ' : '') . 'Search the Wellness Hub | eClinicPro';
    $metaDesc = 'Search recipes, exercises, health tips, videos, free health camps and events on eClinicPro.';
    $noindex = true;
    $foundTips = ($q !== '' || $tag !== '') ? ecp_ht_public_list(['q' => $q, 'tag' => $tag, 'limit' => 24]) : ['rows' => [], 'total' => 0];
    $foundEvents = $q !== '' ? ecp_we_search($q, 12) : [];
} else {
    $campCities = ecp_we_cities('free_camp');
    $city = (string) ($_GET['city'] ?? '');
    $city = in_array($city, array_column($campCities, 'slug'), true) ? $city : '';
    $camps = ecp_we_list(['kind' => 'free_camp', 'city' => $city, 'limit' => 4]);
    $eventsUp = ecp_we_list(['kind' => 'event', 'limit' => 4]);
    $sections = [];
    foreach (ECP_HT_TYPES as $t => $m) {
        $sections[$t] = ecp_ht_public_list(['type' => $t, 'limit' => 4]);
    }
    $creators = wh_featured_creators(8);
    $pageTitle = 'Wellness Hub — healthy recipes, exercises, health tips & free health camps | eClinicPro';
    $metaDesc = 'Healthy recipes, simple exercises, everyday health tips and videos from trusted Health Creators — plus free health camps and health events in your city. Every post is reviewed by the eClinicPro team.';
    $canonicalUrl = ecp_site_url('/wellness');
    if ($city !== '') {
        $noindex = true;
    }
}

require __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/health-tips.css?v=<?= @filemtime(__DIR__ . '/assets/css/health-tips.css') ?: 1 ?>">
<link rel="stylesheet" href="/assets/css/wellness.css?v=<?= @filemtime(__DIR__ . '/assets/css/wellness.css') ?: 1 ?>">

<main class="ht-public we-public">
<?php if ($view === 'creator'): ?>
  <section class="ht-author-hero">
    <div class="wrap wh-profile">
      <span class="ht-avatar ht-avatar-lg">
        <?php if (!empty($creator['avatar_path'])): ?><img src="<?= e(ecp_we_avatar_url((string) $creator['slug'])) ?>" alt="<?= e($creator['display_name']) ?>"><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string) $creator['display_name'], 0, 1))) ?></span><?php endif; ?>
      </span>
      <div>
        <a class="ht-crumb" href="/wellness">← Wellness Hub</a>
        <h1><?= e($creator['display_name']) ?></h1>
        <p class="ht-mute"><?= e(WH_TYPE_SHORT[$creator['creator_type']] ?? '') ?><?= $creator['org_name'] && $creator['org_name'] !== $creator['display_name'] ? ' · ' . e($creator['org_name']) : '' ?><?= $creator['city'] ? ' · ' . e($creator['city']) : '' ?>
          <?php if ($badge): ?> <span class="ht-verified">✓ <?= e($badge) ?></span><?php endif; ?></p>
        <?php if ($badge === 'Verified Doctor' && $creator['med_qualification']): ?><p class="ht-mute"><?= e($creator['med_qualification']) ?><?= $creator['specialty'] ? ' · ' . e($creator['specialty']) : '' ?></p><?php endif; ?>
        <?php if ($creator['headline']): ?><p class="ht-lede"><?= e($creator['headline']) ?></p><?php endif; ?>
        <?php if ($creator['bio']): ?><p class="ht-bio"><?= nl2br(e($creator['bio'])) ?></p><?php endif; ?>
        <div class="ht-links">
          <?php if ($creator['instagram_handle']): ?><a href="https://www.instagram.com/<?= e($creator['instagram_handle']) ?>/" target="_blank" rel="noopener">Instagram ↗</a><?php endif; ?>
          <?php if ($creator['youtube_url']): ?><a href="<?= e($creator['youtube_url']) ?>" target="_blank" rel="noopener">YouTube ↗</a><?php endif; ?>
          <?php if ($creator['website_url']): ?><a href="<?= e($creator['website_url']) ?>" target="_blank" rel="noopener nofollow">Website ↗</a><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <section class="wrap ht-listing">
    <?php if ($events['rows']): ?>
      <div class="wh-section-head"><h2>Upcoming camps &amp; events</h2></div>
      <div class="we-grid"><?php foreach ($events['rows'] as $r) ecp_we_card($r); ?></div>
    <?php endif; ?>
    <?php if ($tips['rows']): ?>
      <div class="wh-section-head wh-section"><h2>Recipes, exercises &amp; tips</h2></div>
      <div class="ht-grid"><?php foreach ($tips['rows'] as $r) ecp_ht_card($r, false); ?></div>
    <?php endif; ?>
    <?php if (!$events['rows'] && !$tips['rows']): ?>
      <p class="ht-empty">Nothing published yet — check back soon.</p>
    <?php endif; ?>
    <?php if (!$viewer) { require __DIR__ . '/partials/wellness_signup_card.php'; } ?>
    <p class="ht-disclaimer"><?= e($disclaimer) ?></p>
  </section>

<?php elseif ($view === 'saved'): ?>
  <section class="ht-list-hero">
    <div class="wrap">
      <a class="ht-crumb" href="/wellness">← Wellness Hub</a>
      <h1>Saved</h1>
      <p class="ht-lede">Recipes, exercises, tips, camps and events you saved — tap ♡ Save on any of them to add it here.</p>
    </div>
  </section>
  <section class="wrap ht-listing">
    <?php if (!$viewer): ?>
      <div class="ht-empty">
        <p>Sign in to see what you saved.</p>
        <button type="button" class="btn btn-primary" onclick="window.ecpAuth ? window.ecpAuth.require('wellness_saved', () => location.reload()) : (location.href = '/patient')">Sign in</button>
      </div>
    <?php else: ?>
      <?php if ($savedEvents): ?>
        <div class="wh-section-head"><h2>Camps &amp; events</h2></div>
        <div class="we-grid"><?php foreach ($savedEvents as $r) ecp_we_card($r); ?></div>
      <?php endif; ?>
      <?php if ($savedTips['rows']): ?>
        <div class="wh-section-head wh-section"><h2>Recipes, exercises &amp; tips</h2></div>
        <div class="ht-grid"><?php foreach ($savedTips['rows'] as $r) ecp_ht_card($r); ?></div>
      <?php endif; ?>
      <?php if (!$savedEvents && !$savedTips['rows']): ?>
        <p class="ht-empty">Nothing saved yet. <a href="/wellness">Explore the Wellness Hub</a> and tap ♡ Save on anything you like.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

<?php elseif ($view === 'search'): ?>
  <section class="ht-list-hero">
    <div class="wrap">
      <a class="ht-crumb" href="/wellness">← Wellness Hub</a>
      <h1>Search</h1>
      <form class="ht-search" method="get" action="/wellness/search" role="search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Recipes, exercises, camps, a city…" aria-label="Search the Wellness Hub" autofocus>
        <button class="btn btn-primary" type="submit">Search</button>
      </form>
    </div>
  </section>
  <section class="wrap ht-listing">
    <?php if ($q === '' && $tag === ''): ?>
      <p class="ht-empty">Type what you’re looking for — e.g. “protein breakfast”, “back pain”, “eye camp”, “Surat”.</p>
    <?php else: ?>
      <?php if ($foundEvents): ?>
        <div class="wh-section-head"><h2>Upcoming camps &amp; events</h2></div>
        <div class="we-grid"><?php foreach ($foundEvents as $r) ecp_we_card($r); ?></div>
      <?php endif; ?>
      <?php if ($foundTips['rows']): ?>
        <div class="wh-section-head <?= $foundEvents ? 'wh-section' : '' ?>"><h2>Recipes, exercises &amp; tips</h2></div>
        <div class="ht-grid"><?php foreach ($foundTips['rows'] as $r) ecp_ht_card($r); ?></div>
      <?php endif; ?>
      <?php if (!$foundEvents && !$foundTips['rows']): ?>
        <p class="ht-empty">Nothing matches “<?= e($q !== '' ? $q : $tag) ?>” yet. Try a shorter word, or <a href="/wellness">browse the Wellness Hub</a>.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

<?php else: ?>
  <section class="wh-hero">
    <div class="wrap">
      <span class="eyebrow">eClinicPro Wellness Hub</span>
      <h1>Eat well, move more, and find free health camps near you</h1>
      <p class="ht-lede">Healthy recipes, simple exercises, everyday tips and videos from trusted Health Creators — plus free health camps and events in your city. Every post is reviewed by the eClinicPro team before it goes live.</p>
      <form class="ht-search" method="get" action="/wellness/search" role="search">
        <input type="search" name="q" placeholder="Search recipes, exercises, camps, a city…" aria-label="Search the Wellness Hub">
        <button class="btn btn-primary" type="submit">Search</button>
      </form>
      <nav class="wh-nav" aria-label="Wellness Hub sections">
        <a href="/wellness/camps">🩺 Free health camps</a>
        <a href="/wellness/events">📅 Events</a>
        <?php foreach (ECP_HT_TYPES as $t => $m): ?><a href="<?= e(ecp_ht_section_url($t)) ?>"><?= $m['icon'] ?> <?= e($m['plural']) ?></a><?php endforeach; ?>
        <a href="/wellness/saved">♥ Saved</a>
      </nav>
    </div>
  </section>

  <div class="wrap ht-listing">
    <section>
      <div class="wh-section-head">
        <h2>Upcoming free health camps</h2>
        <?php if ($campCities): ?>
          <form method="get" action="/wellness" onsubmit="event.preventDefault(); location.href = '/wellness' + (this.city.value ? '?city=' + this.city.value : '') + '#camps';">
            <select name="city" aria-label="City" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
              <option value="">All cities</option>
              <?php foreach ($campCities as $c): ?><option value="<?= e($c['slug']) ?>" <?= $city === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </form>
        <?php endif; ?>
        <a href="/wellness/camps<?= $city ? '/' . e($city) : '' ?>" id="camps">All free camps →</a>
      </div>
      <?php if ($camps['rows']): ?>
        <div class="we-grid"><?php foreach ($camps['rows'] as $r) ecp_we_card($r); ?></div>
      <?php else: ?>
        <p class="ht-empty">No upcoming free camps listed<?= $city ? ' in this city' : '' ?> yet. Organising one? <a href="<?= e($creatorJoinUrl) ?>">List it free</a>.</p>
      <?php endif; ?>
    </section>

    <?php foreach (ECP_HT_TYPES as $t => $m): if (!$sections[$t]['rows']) { continue; } ?>
      <section class="wh-section">
        <div class="wh-section-head">
          <h2><?= e($m['plural']) ?></h2>
          <a href="<?= e(ecp_ht_section_url($t)) ?>">All <?= e(strtolower($m['plural'])) ?> →</a>
        </div>
        <div class="ht-grid"><?php foreach ($sections[$t]['rows'] as $r) ecp_ht_card($r); ?></div>
      </section>
    <?php endforeach; ?>

    <?php if ($eventsUp['rows']): ?>
      <section class="wh-section">
        <div class="wh-section-head"><h2>Health events</h2><a href="/wellness/events">All events →</a></div>
        <div class="we-grid"><?php foreach ($eventsUp['rows'] as $r) ecp_we_card($r); ?></div>
      </section>
    <?php endif; ?>

    <?php if ($creators): ?>
      <section class="wh-section">
        <div class="wh-section-head"><h2>Health Creators</h2></div>
        <div class="wh-creators">
          <?php foreach ($creators as $c): $b = wh_badge($c); ?>
            <a class="wh-creator" href="/wellness/creators/<?= e($c['slug']) ?>">
              <span class="ht-avatar ht-avatar-sm" style="width:48px;height:48px">
                <?php if (!empty($c['avatar_path'])): ?><img src="<?= e(ecp_we_avatar_url((string) $c['slug'])) ?>" alt="" loading="lazy"><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string) $c['display_name'], 0, 1))) ?></span><?php endif; ?>
              </span>
              <span><strong><?= e($c['display_name']) ?></strong>
                <small><?= $b ? '✓ ' . e($b) : e(WH_TYPE_SHORT[$c['creator_type']] ?? '') ?><?= $c['city'] ? ' · ' . e($c['city']) : '' ?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <aside class="wh-become">
      <div>
        <h2>Share your healthy recipes, workouts or health camps</h2>
        <p>Fitness coaches, dietitians, doctors, hospitals, NGOs and organisers can become Health Creators. Listing free health camps is always free.</p>
      </div>
      <a class="btn btn-primary" href="<?= e($creatorJoinUrl) ?>">Become a Health Creator →</a>
    </aside>

    <?php if (!$viewer) { require __DIR__ . '/partials/wellness_signup_card.php'; } ?>
    <p class="ht-disclaimer"><?= e($disclaimer) ?></p>
  </div>
<?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
