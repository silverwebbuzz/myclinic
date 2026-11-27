<?php
// =====================================================================
// wellness-events.php — public Wellness Hub camps & events (Phase 2).
//
//   /wellness/camps                       upcoming free health camps (?when=today|week|month, ?category=, ?online=1)
//   /wellness/camps/{city}                in one city            (SEO landing)
//   /wellness/camps/{city}/{category}     city + category        (SEO landing)
//   /wellness/camp/{slug}                 one camp
//   /wellness/events[...]  /wellness/event/{slug}   same for events (+ ?free=1)
//
// Rewrites pass _we_kind (camps|events|camp|event), _we_a, _we_b.
// Content is open to everyone and Google; the organiser's phone, the
// registration link and the online link need a free patient sign-in (plan §8A).
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';
require_once __DIR__ . '/partials/wellness_events.php';

$word = strtolower((string) ($_GET['_we_kind'] ?? 'camps'));
$kind = ecp_we_kind_from_path($word) ?? 'free_camp';
$K = ECP_WE_KINDS[$kind];
$isCamp = $kind === 'free_camp';
$a = strtolower(trim((string) ($_GET['_we_a'] ?? '')));
$b = strtolower(trim((string) ($_GET['_we_b'] ?? '')));
$categories = ecp_we_categories($kind);
$creatorJoinUrl = ecp_portal_url('/creator/login');

$mode = in_array($word, ['camp', 'event'], true) ? 'detail' : ($a !== '' ? 'city' : 'list');
$event = null;
$cityName = null;
$category = '';

if ($mode === 'detail') {
    $event = ecp_we_find($kind, $a);
    if (!$event) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return;
    }
} elseif ($mode === 'city') {
    $cityName = ecp_we_city_name($a);
    if ($cityName === null || ($b !== '' && !isset($categories[$b]))) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return;
    }
    $category = $b;
} else {
    $category = isset($categories[(string) ($_GET['category'] ?? '')]) ? (string) $_GET['category'] : '';
}

$when = in_array($_GET['when'] ?? '', ['today', 'week', 'month'], true) ? (string) $_GET['when'] : '';
$freeOnly = !$isCamp && !empty($_GET['free']);
$onlineOnly = !empty($_GET['online']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$viewer = ecp_patient_current();
$disclaimer = 'Camps and events are listed by independent organisers. eClinicPro only lists them and is not responsible for the services, fees or treatment provided there. Information here is general and not medical advice — for your own health condition, consult a doctor.';

// ---------------------------------------------------------------------
// Data + page meta
// ---------------------------------------------------------------------
$activePage = 'wellness';
$basePath = '/wellness/' . $K['path'];
if ($mode === 'detail') {
    $ended = strtotime((string) $event['end_at']) < time();
    $cancelled = $event['status'] === 'cancelled';
    $organiser = ecp_we_organiser($event);
    $catName = $categories[$event['category_slug']] ?? $K['label'];
    $where = $event['mode'] === 'online' ? 'Online' : (string) $event['city_name'];
    $pageTitle = $event['title'] . ' — ' . ($isCamp ? 'Free ' . strtolower($catName) : $catName) . ($where ? ' in ' . $where : '') . ', ' . date('d M Y', strtotime((string) $event['start_at'])) . ' | eClinicPro';
    $metaDesc = (string) ($event['summary'] ?: mb_substr(trim((string) $event['description']), 0, 155));
    $canonicalUrl = ecp_site_url(ecp_we_url($event));
    $ogType = 'website';
    if (!empty($event['poster_path'])) {
        $ogImage = ecp_site_url(ecp_we_poster_url((int) $event['id']));
    }
    $showContact = $viewer !== null && !$cancelled && !$ended;
    if (!preg_match('/bot|crawl|spider|slurp|preview/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        ecp_we_count_view((int) $event['id'], $showContact);
    }
    $more = ecp_we_list(['kind' => $kind, 'city' => $event['city_slug'] ?: '', 'exclude_id' => (int) $event['id'], 'limit' => 3]);
    if (!$more['rows'] && $event['city_slug']) {
        $more = ecp_we_list(['kind' => $kind, 'exclude_id' => (int) $event['id'], 'limit' => 3]);
    }
} else {
    $list = ecp_we_list([
        'kind' => $kind, 'city' => $mode === 'city' ? $a : '', 'category' => $category, 'when' => $when,
        'free' => $freeOnly, 'online' => $onlineOnly, 'page' => $page,
    ]);
    $cities = ecp_we_cities($kind);
    $catName = $category !== '' ? $categories[$category] : '';
    if ($mode === 'city') {
        $subject = $catName !== '' ? ($isCamp ? 'Free ' . strtolower($catName) . ' camps' : $catName . ' events') : $K['plural'];
        $h1 = $subject . ' in ' . $cityName;
        $pageTitle = $h1 . ' — upcoming dates & venues | eClinicPro';
        $metaDesc = 'Upcoming ' . strtolower($subject) . ' in ' . $cityName . ': dates, venues and how to join. Listings are checked by the eClinicPro team before they go live.';
        $canonicalUrl = ecp_site_url($basePath . '/' . $a . ($category !== '' ? '/' . $category : ''));
    } else {
        $h1 = $catName !== '' ? ($isCamp ? 'Free ' . strtolower($catName) . ' camps' : $catName . ' events') : ($isCamp ? 'Free health camps near you' : 'Health events near you');
        $pageTitle = ($isCamp ? 'Free health camps in India — eye, dental, checkup & blood donation camps' : 'Health events, expos & workshops in India') . ' | eClinicPro';
        $metaDesc = $isCamp
            ? 'Find upcoming free health checkup, eye, dental, blood donation and vaccination camps by city. Organised by doctors, hospitals, NGOs and government bodies.'
            : 'Upcoming health expos, workshops, webinars and wellness events by city — dates, venues and how to join.';
        $canonicalUrl = ecp_site_url($basePath);
    }
    // Keep thin / filtered / paged views out of the index.
    if ($list['total'] === 0 || $when !== '' || $freeOnly || $onlineOnly || $page > 1 || ($mode === 'list' && $category !== '')) {
        $noindex = true;
    }
}

require __DIR__ . '/partials/header.php';

/** Querystring builder for filter chips on list/city pages. */
$q = static function (array $over) use ($when, $freeOnly, $onlineOnly, $category, $mode): string {
    $p = array_filter($over + ['when' => $when, 'free' => $freeOnly ? 1 : '', 'online' => $onlineOnly ? 1 : '', 'category' => $mode === 'list' ? $category : ''], static fn ($v) => $v !== '' && $v !== null && $v !== 0);
    return $p ? '?' . http_build_query($p) : '';
};
?>
<link rel="stylesheet" href="/assets/css/wellness.css?v=<?= @filemtime(__DIR__ . '/assets/css/wellness.css') ?: 1 ?>">

<main class="we-public">
<?php if ($mode === 'detail'): ?>
  <article class="wrap we-article">
    <nav class="we-crumbs" aria-label="Breadcrumb">
      <a href="/wellness">Wellness Hub</a><span>›</span>
      <a href="<?= e($basePath) ?>"><?= e($K['plural']) ?></a>
      <?php if ($event['city_slug']): ?><span>›</span><a href="<?= e($basePath . '/' . $event['city_slug']) ?>"><?= e($event['city_name']) ?></a><?php endif; ?>
    </nav>

    <?php if ($cancelled): ?>
      <div class="we-banner is-cancelled"><strong>Cancelled.</strong> <?= e((string) $event['cancel_reason']) ?></div>
    <?php elseif ($ended): ?>
      <div class="we-banner is-ended">This <?= e($K['one']) ?> has ended. <a href="<?= e($basePath . ($event['city_slug'] ? '/' . $event['city_slug'] : '')) ?>">See upcoming <?= e(strtolower($K['plural'])) ?><?= $event['city_slug'] ? ' in ' . e($event['city_name']) : '' ?> →</a></div>
    <?php endif; ?>

    <div class="we-detail <?= empty($event['poster_path']) ? 'no-poster' : '' ?>">
      <?php if (!empty($event['poster_path'])): ?>
        <figure class="we-poster"><img src="<?= e(ecp_we_poster_url((int) $event['id'])) ?>" alt="<?= e($event['title']) ?> poster"></figure>
      <?php endif; ?>
      <div class="we-head">
        <span class="we-kind"><?= e($isCamp ? 'Free health camp' : 'Health event') ?> · <?= e($catName) ?></span>
        <h1><?= e($event['title']) ?></h1>
        <?php if ($event['summary']): ?><p class="we-lede"><?= e($event['summary']) ?></p><?php endif; ?>
        <?php if (ecp_we_saves_enabled()): $weSaved = $viewer && ecp_we_is_saved((int) $viewer['id'], (int) $event['id']); ?>
          <div class="we-save" x-data="weSave(<?= (int) $event['id'] ?>, <?= $weSaved ? 'true' : 'false' ?>)">
            <button type="button" class="we-save-btn" :class="saved ? 'is-saved' : ''" :disabled="busy" @click="toggle()">
              <span x-text="saved ? '♥ Saved' : '♡ Save'"><?= $weSaved ? '♥ Saved' : '♡ Save' ?></span>
            </button>
            <a href="/wellness/saved" class="we-save-link" x-show="saved">Your saved items →</a>
          </div>
        <?php endif; ?>

        <dl class="we-facts">
          <div><dt>When</dt><dd><?= e(ecp_we_when((string) $event['start_at'], (string) $event['end_at'])) ?></dd></div>
          <?php if ($event['mode'] !== 'online'): ?>
            <div><dt>Where</dt><dd>
              <?= e(implode(', ', array_filter([$event['venue_name'], $event['address'], $event['city_name'], $event['state'], $event['pincode']]))) ?>
              <?php if ($event['map_url']): ?><br><a href="<?= e($event['map_url']) ?>" target="_blank" rel="noopener nofollow">Open in Google Maps ↗</a><?php endif; ?>
            </dd></div>
          <?php endif; ?>
          <?php if ($event['mode'] !== 'in_person'): ?><div><dt>Online</dt><dd><?= $event['mode'] === 'hybrid' ? 'Also streamed online' : 'Online session' ?></dd></div><?php endif; ?>
          <div><dt>Fee</dt><dd><span class="we-fee <?= (float) $event['fee_amount'] > 0 ? 'is-paid' : '' ?>"><?= e(ecp_we_fee_label($event)) ?></span><?= $event['fee_note'] ? ' <small>' . e($event['fee_note']) . '</small>' : '' ?></dd></div>
          <div><dt>For</dt><dd><?= e(ECP_WE_AUDIENCE[$event['audience']] ?? 'Everyone') ?><?= $event['languages'] ? ' · ' . e($event['languages']) : '' ?></dd></div>
        </dl>

        <section class="we-join" id="join">
          <h2>How to join</h2>
          <p class="we-join-mode"><?= e(['walk_in' => 'Walk in during the timings above.', 'call' => 'Call the organiser to book a slot.', 'register' => 'Register online before you go.'][$event['join_mode']] ?? '') ?></p>
          <?php if ($cancelled || $ended): ?>
            <p class="we-mute">Contact details are hidden because this <?= e($K['one']) ?> <?= $cancelled ? 'was cancelled' : 'has ended' ?>.</p>
          <?php elseif ($showContact): ?>
            <div class="we-contact">
              <?php if ($event['contact_phone']): ?>
                <a class="btn btn-primary" href="tel:<?= e($event['contact_phone']) ?>">📞 Call <?= e($event['contact_name'] ?: 'organiser') ?> · <?= e(preg_replace('/^\+91(\d{5})(\d{5})$/', '+91 $1 $2', (string) $event['contact_phone'])) ?></a>
              <?php endif; ?>
              <?php if ($event['register_url']): ?>
                <a class="btn btn-secondary" href="<?= e($event['register_url']) ?>" target="_blank" rel="noopener nofollow">Register ↗</a>
              <?php endif; ?>
              <?php if ($event['online_url']): ?>
                <a class="btn btn-secondary" href="<?= e($event['online_url']) ?>" target="_blank" rel="noopener nofollow">Join online ↗</a>
              <?php endif; ?>
            </div>
            <p class="we-mute we-small">Please tell the organiser you found this on eClinicPro.</p>
          <?php else: ?>
            <div class="we-gate">
              <p><strong>Sign in free to see the organiser’s number<?= $event['register_url'] || $event['online_url'] ? ' and joining links' : '' ?>.</strong> One free eClinicPro account also keeps your prescriptions, lab reports and family’s health records in one place.</p>
              <button type="button" class="btn btn-primary" onclick="window.ecpAuth ? window.ecpAuth.require('camp_contact', () => location.reload()) : (location.href = '/patient')">Sign in to see contact</button>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>

    <?php if ($event['description']): ?>
      <section class="we-prose"><h2>About this <?= e($K['one']) ?></h2><p><?= nl2br(e($event['description'])) ?></p></section>
    <?php endif; ?>

    <aside class="we-organiser">
      <span class="we-avatar">
        <?php if (!empty($event['creator_avatar']) && $event['creator_type'] !== 'platform'): ?><img src="<?= e(ecp_we_avatar_url((string) $event['creator_slug'])) ?>" alt=""><?php else: ?><span><?= e(mb_strtoupper(mb_substr($organiser, 0, 1))) ?></span><?php endif; ?>
      </span>
      <div>
        <p class="we-mute we-small">Organised by</p>
        <h3><?= e($organiser) ?><?php if ($badge = ecp_we_badge($event)): ?> <span class="we-verified">✓ <?= e($badge) ?></span><?php endif; ?></h3>
        <?php if ($event['creator_type'] === 'platform'): ?>
          <p class="we-mute we-small">Listed by the eClinicPro team from a public announcement.</p>
        <?php else: ?>
          <?php if ($event['creator_type'] === 'doctor' && $badge && $event['creator_qualification']): ?><p class="we-mute"><?= e($event['creator_qualification']) ?><?= $event['creator_specialty'] ? ' · ' . e($event['creator_specialty']) : '' ?></p><?php endif; ?>
          <?php if ($event['creator_headline']): ?><p class="we-mute"><?= e($event['creator_headline']) ?></p><?php endif; ?>
        <?php endif; ?>
      </div>
    </aside>

    <?php if ($event['city_slug'] && !$ended) { $waKind = $kind; $waCity = (string) $event['city_slug']; $waCityName = (string) $event['city_name']; require __DIR__ . '/partials/wellness_alert_button.php'; } ?>

    <?php $whcType = 'event'; $whcId = (int) $event['id']; require __DIR__ . '/partials/wellness_comments_block.php'; ?>

    <?php if (!$viewer) { require __DIR__ . '/partials/wellness_signup_card.php'; } ?>
    <p class="we-disclaimer"><?= e($disclaimer) ?></p>

    <details class="we-report" x-data="weReport(<?= (int) $event['id'] ?>)">
      <summary>Report this listing</summary>
      <form @submit.prevent="send()" x-show="!done">
        <label>What’s wrong?
          <select x-model="reason" required>
            <option value="">Choose…</option>
            <?php foreach (ECP_WE_REPORT_REASONS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Details (optional)<textarea x-model="details" maxlength="1000" rows="3"></textarea></label>
        <p class="we-err" x-show="error" x-text="error"></p>
        <button class="btn btn-secondary" type="submit" :disabled="busy">Send report</button>
      </form>
      <p x-show="done" class="we-mute">Thank you — our team will check it.</p>
    </details>

    <?php if ($more['rows']): ?>
      <section class="we-more">
        <h2>More upcoming <?= e(strtolower($K['plural'])) ?></h2>
        <div class="we-grid"><?php foreach ($more['rows'] as $r) ecp_we_card($r, $categories); ?></div>
      </section>
    <?php endif; ?>
  </article>
  <script type="application/ld+json"><?= json_encode(ecp_we_json_ld($event), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
  <script>
    function weSave(id, saved) {
      return {
        saved, busy: false,
        async send() {
          const r = await fetch('/api/wellness_save', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
          return { status: r.status, j: await r.json().catch(() => ({})) };
        },
        async toggle() {
          if (this.busy) return;
          this.busy = true;
          try {
            const { status, j } = await this.send();
            if (status === 401) {
              if (window.ecpAuth) window.ecpAuth.require('save_event', async () => { await this.send(); location.reload(); });
              else location.href = '/patient';
              return;
            }
            if (j.ok) this.saved = j.saved;
          } catch (e) {
          } finally { this.busy = false; }
        },
      };
    }
    function weReport(id) {
      return {
        reason: '', details: '', busy: false, done: false, error: '',
        async send() {
          this.error = '';
          if (!this.reason) { this.error = 'Please choose what’s wrong.'; return; }
          this.busy = true;
          try {
            const r = await fetch('/api/wellness_report', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, reason: this.reason, details: this.details }) });
            const j = await r.json().catch(() => ({}));
            if (j.ok) { this.done = true; } else { this.error = j.error === 'too_many' ? 'You’ve sent several reports already. Please try again later.' : 'Could not send. Please try again.'; }
          } catch (e) { this.error = 'Network problem. Please try again.'; }
          finally { this.busy = false; }
        },
      };
    }
  </script>

<?php else: ?>
  <section class="we-hero">
    <div class="wrap">
      <nav class="we-tabs" aria-label="Type">
        <a href="/wellness/camps<?= $mode === 'city' ? '/' . e($a) : '' ?>" class="<?= $isCamp ? 'is-active' : '' ?>">Free health camps</a>
        <a href="/wellness/events<?= $mode === 'city' ? '/' . e($a) : '' ?>" class="<?= !$isCamp ? 'is-active' : '' ?>">Health events</a>
      </nav>
      <h1><?= e($h1) ?></h1>
      <p class="we-lede"><?= $isCamp
          ? 'Free check-ups, screenings, eye and dental camps, blood donation and vaccination drives — run by doctors, hospitals, NGOs and government bodies. Every listing is checked by our team before it goes live.'
          : 'Health expos, workshops, webinars, walkathons and doctor conferences. Every listing is checked by our team before it goes live.' ?></p>
      <form class="we-city" method="get" onsubmit="event.preventDefault(); const v = this.city.value; location.href = '<?= e($basePath) ?>' + (v ? '/' + v : '');">
        <label for="we-city-pick">City</label>
        <select id="we-city-pick" name="city">
          <option value="">All cities</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= $mode === 'city' && $a === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= (int) $c['n'] ?>)</option>
          <?php endforeach; ?>
          <?php if ($mode === 'city' && !in_array($a, array_column($cities, 'slug'), true)): ?><option value="<?= e($a) ?>" selected><?= e($cityName) ?></option><?php endif; ?>
        </select>
        <button class="btn btn-primary" type="submit">Show</button>
      </form>
      <?php if ($mode === 'city') { $waKind = $kind; $waCity = $a; $waCityName = (string) $cityName; require __DIR__ . '/partials/wellness_alert_button.php'; } ?>
    </div>
  </section>

  <section class="wrap we-listing">
    <div class="we-chips">
      <?php foreach (['' => 'Any date', 'today' => 'Today', 'week' => 'Next 7 days', 'month' => 'Next 30 days'] as $k => $l): ?>
        <a class="we-chip <?= $when === $k ? 'is-active' : '' ?>" href="<?= e($q(['when' => $k])) ?: '?' ?>"><?= e($l) ?></a>
      <?php endforeach; ?>
      <a class="we-chip <?= $onlineOnly ? 'is-active' : '' ?>" href="<?= e($q(['online' => $onlineOnly ? '' : 1])) ?: '?' ?>">Online</a>
      <?php if (!$isCamp): ?><a class="we-chip <?= $freeOnly ? 'is-active' : '' ?>" href="<?= e($q(['free' => $freeOnly ? '' : 1])) ?: '?' ?>">Free entry</a><?php endif; ?>
    </div>
    <div class="we-chips we-cats">
      <?php $catBase = $mode === 'city' ? $basePath . '/' . $a : $basePath; ?>
      <a class="we-chip <?= $category === '' ? 'is-active' : '' ?>" href="<?= e($catBase . ($mode === 'list' ? $q(['category' => '']) : '')) ?>">All</a>
      <?php foreach ($categories as $slug => $name): ?>
        <a class="we-chip <?= $category === $slug ? 'is-active' : '' ?>"
           href="<?= e($mode === 'city' ? $catBase . '/' . $slug : $basePath . $q(['category' => $slug])) ?>"><?= e($name) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($list['rows']): ?>
      <div class="we-grid"><?php foreach ($list['rows'] as $r) ecp_we_card($r, $categories); ?></div>
      <?php
        $pages = (int) ceil($list['total'] / ECP_WE_PER_PAGE);
        if ($pages > 1): ?>
        <nav class="we-pager">
          <?php if ($page > 1): ?><a href="<?= e($q(['page' => $page - 1 > 1 ? $page - 1 : ''])) ?: '?' ?>">← Earlier</a><?php endif; ?>
          <span>Page <?= $page ?> of <?= $pages ?></span>
          <?php if ($page < $pages): ?><a href="<?= e($q(['page' => $page + 1])) ?>">Later →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <div class="we-empty">
        <p>No upcoming <?= e(strtolower($catName !== '' ? $catName : $K['plural'])) ?><?= $mode === 'city' ? ' in ' . e($cityName) : '' ?> listed yet.</p>
        <?php if ($mode === 'city' || $category !== '' || $when !== '' || $onlineOnly || $freeOnly): ?><p><a href="<?= e($basePath) ?>">See all <?= e(strtolower($K['plural'])) ?> →</a></p><?php endif; ?>
      </div>
    <?php endif; ?>

    <aside class="we-cta">
      <div>
        <h2><?= $isCamp ? 'Organising a free health camp?' : 'Organising a health event?' ?></h2>
        <p><?= $isCamp ? 'Doctors, hospitals, NGOs and government bodies can list free camps on eClinicPro at no cost, so more people in your city find them.' : 'List your expo, workshop or webinar on eClinicPro so more people find it.' ?></p>
      </div>
      <a class="btn btn-primary" href="<?= e($creatorJoinUrl) ?>">List it on eClinicPro →</a>
    </aside>

    <?php if ($mode === 'list' && $cities): ?>
      <nav class="we-citylinks" aria-label="Cities">
        <h2><?= e($K['plural']) ?> by city</h2>
        <?php foreach ($cities as $c): ?><a href="<?= e($basePath . '/' . $c['slug']) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if (!$viewer) { require __DIR__ . '/partials/wellness_signup_card.php'; } ?>
    <p class="we-disclaimer"><?= e($disclaimer) ?></p>
  </section>
<?php endif; ?>
</main>

<?php
require __DIR__ . '/partials/footer.php';
