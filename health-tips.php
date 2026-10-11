<?php
// =====================================================================
// health-tips.php — public Health Tips.
//
//   /health-tips                    list (?type=recipe|exercise|tip, ?tag=, ?q=, ?page=)
//   /health-tips/{slug}             one post            (?_ht_slug= via rewrite)
//   /health-tips/by/{slug}          contributor's page  (?_ht_by= via rewrite)
//
// Only published posts by active contributors are visible. A contributor can
// preview their own unpublished post (noindex, banner) from their studio.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';
require_once __DIR__ . '/partials/health_tips.php';

$slug = strtolower(trim((string) ($_GET['_ht_slug'] ?? '')));
$by   = strtolower(trim((string) ($_GET['_ht_by'] ?? '')));

$tip = null;
$author = null;
$mode = 'list';

if ($slug !== '') {
    $mode = 'detail';
    $viewer = ecp_patient_current();
    $viewerC = $viewer ? ecp_ht_contributor_for_identity((int) $viewer['id']) : null;
    $tip = ecp_ht_find_by_slug($slug, $viewerC ? (int) $viewerC['id'] : null);
} elseif ($by !== '') {
    $mode = 'author';
    $author = ecp_ht_contributor_by_slug($by);
} elseif (!empty($_GET['saved'])) {
    $mode = 'saved';
    $viewer = ecp_patient_current();
}

if (($mode === 'detail' && !$tip) || ($mode === 'author' && !$author)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$typeFilter = (string) ($_GET['type'] ?? '');
$typeFilter = isset(ECP_HT_TYPES[$typeFilter]) ? $typeFilter : '';
$tagFilter  = preg_match('/^[a-z0-9\-]{1,60}$/', (string) ($_GET['tag'] ?? '')) ? (string) $_GET['tag'] : '';
$q          = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$page       = max(1, (int) ($_GET['page'] ?? 1));

$disclaimer = 'General wellness information shared by our contributors — not medical advice. If you have a health condition, allergy, are pregnant, or take medicines, check with your doctor before changing your diet or exercise.';

// ---------------------------------------------------------------------
// Page meta
// ---------------------------------------------------------------------
$activePage = 'health-tips';
if ($mode === 'detail') {
    $typeMeta  = ECP_HT_TYPES[$tip['type']];
    $pageTitle = $tip['title'] . ' — ' . $typeMeta['label'] . ' by ' . $tip['author_name'] . ' | eClinicPro Health Tips';
    $metaDesc  = (string) ($tip['summary'] ?: mb_substr((string) $tip['body'], 0, 155));
    $canonicalUrl = ecp_site_url('/health-tips/' . $tip['slug']);
    $ogType = 'article';
    if ($tip['media']) {
        $ogImage = ecp_site_url(ecp_ht_image_url((int) $tip['media'][0]['id']));
    }
    if ($tip['is_preview']) {
        $noindex = true;
        header('Cache-Control: private, no-store');
    } elseif (!preg_match('/bot|crawl|spider|slurp|preview/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        ecp_ht_count_view((int) $tip['id']);
    }
    $canSave  = !$tip['is_preview'] && ecp_ht_saves_enabled();
    $isSaved  = $canSave && $viewer && ecp_ht_is_saved((int) $viewer['id'], (int) $tip['id']);
    $saveCount = (int) ($tip['save_count'] ?? 0);
} elseif ($mode === 'saved') {
    $pageTitle = 'Saved health tips — eClinicPro';
    $metaDesc  = 'Recipes, exercises and tips you saved on eClinicPro.';
    $noindex   = true;
    header('Cache-Control: private, no-store, max-age=0');
    $list = $viewer ? ecp_ht_public_list(['saved_by' => (int) $viewer['id'], 'page' => $page]) : ['rows' => [], 'total' => 0];
} elseif ($mode === 'author') {
    $pageTitle = $author['display_name'] . ' — recipes & health tips | eClinicPro';
    $metaDesc  = (string) ($author['headline'] ?: ('Recipes, exercises and health tips by ' . $author['display_name'] . ' on eClinicPro.'));
    $canonicalUrl = ecp_site_url('/health-tips/by/' . $author['slug']);
    $ogType = 'profile';
    $list = ecp_ht_public_list(['contributor_id' => (int) $author['id'], 'page' => $page]);
    if ($list['total'] === 0 || $page > 1) {
        $noindex = true;   // no live posts yet: keep thin pages out of the index
    }
} else {
    $pageTitle = ($typeFilter ? ECP_HT_TYPES[$typeFilter]['plural'] . ' — ' : '') . 'Healthy recipes, exercises & everyday health tips | eClinicPro';
    $metaDesc  = 'Healthy, protein-rich recipes, simple exercises and everyday health habits from contributors we trust — reviewed by the eClinicPro team before publishing.';
    $canonicalUrl = ecp_site_url('/health-tips' . ($typeFilter ? '?type=' . $typeFilter : ''));
    if ($q !== '' || $tagFilter !== '' || $page > 1) {
        $noindex = true;   // filtered/search/paged views: keep the index clean
    }
    $list = ecp_ht_public_list(['type' => $typeFilter, 'tag' => $tagFilter, 'q' => $q, 'page' => $page]);
}

require __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/health-tips.css?v=<?= @filemtime(__DIR__ . '/assets/css/health-tips.css') ?: 1 ?>">

<main class="ht-public">
<?php if ($mode === 'detail'): ?>
  <?php
    $video = ecp_ht_video_embed($tip['video_url']);
    $facts = [];
    if ($tip['type'] === 'recipe') {
        if ($tip['prep_minutes'] !== null) $facts[] = ['Prep', ecp_ht_minutes((int) $tip['prep_minutes']) ?: '—'];
        if ($tip['cook_minutes'] !== null) $facts[] = ['Cook', ecp_ht_minutes((int) $tip['cook_minutes']) ?: 'No cooking'];
        if ($tip['servings']) $facts[] = ['Serves', (string) (int) $tip['servings']];
        if ($tip['protein_g'] !== null) $facts[] = ['Protein', '~' . rtrim(rtrim((string) $tip['protein_g'], '0'), '.') . ' g / serving'];
        if ($tip['calories_kcal'] !== null) $facts[] = ['Energy', '~' . (int) $tip['calories_kcal'] . ' kcal / serving'];
    } elseif ($tip['type'] === 'exercise') {
        if ($tip['duration_minutes']) $facts[] = ['Duration', ecp_ht_minutes((int) $tip['duration_minutes'])];
        if ($tip['difficulty']) $facts[] = ['Level', ucfirst((string) $tip['difficulty'])];
        if ($tip['reps_sets']) $facts[] = ['Reps / sets', (string) $tip['reps_sets']];
        if ($tip['equipment']) $facts[] = ['Equipment', (string) $tip['equipment']];
        if ($tip['body_area']) $facts[] = ['Body area', (string) $tip['body_area']];
    }
  ?>
  <?php if ($tip['is_preview']): ?>
    <div class="ht-preview-bar">Preview — only you can see this. Status: <strong><?= e(ECP_HT_STATUS_LABELS[$tip['status']] ?? $tip['status']) ?></strong> · <a href="/my-health-tips?edit=<?= (int) $tip['id'] ?>">Back to editor</a></div>
  <?php endif; ?>

  <article class="wrap ht-article">
    <nav class="ht-crumbs" aria-label="Breadcrumb">
      <a href="/health-tips">Health tips</a> <span>›</span>
      <a href="/health-tips?type=<?= e($tip['type']) ?>"><?= e($typeMeta['plural']) ?></a>
    </nav>

    <header class="ht-art-head">
      <span class="ht-kind"><?= $typeMeta['icon'] ?> <?= e($typeMeta['label']) ?></span>
      <h1><?= e($tip['title']) ?></h1>
      <?php if ($tip['summary']): ?><p class="ht-lede"><?= e($tip['summary']) ?></p><?php endif; ?>
      <a class="ht-byline" href="/health-tips/by/<?= e($tip['author_slug']) ?>">
        <span class="ht-avatar ht-avatar-sm">
          <?php if ($tip['author_has_avatar']): ?><img src="<?= e(ecp_ht_avatar_url($tip['author_slug'])) ?>" alt=""><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string) $tip['author_name'], 0, 1))) ?></span><?php endif; ?>
        </span>
        <span>By <strong><?= e($tip['author_name']) ?></strong>
          <?php if ($tip['published_at']): ?><small> · <?= e(date('d M Y', strtotime((string) $tip['published_at']))) ?></small><?php endif; ?></span>
      </a>
      <p class="ht-reviewed">✓ Reviewed by the eClinicPro team before publishing</p>
      <?php if ($canSave): ?>
        <div class="ht-save-wrap" x-data="htSave(<?= (int) $tip['id'] ?>, <?= $isSaved ? 'true' : 'false' ?>, <?= $saveCount ?>)">
          <button type="button" class="ht-save" :class="saved ? 'is-saved' : ''" :aria-pressed="saved ? 'true' : 'false'" :disabled="busy" @click="toggle()">
            <span class="ht-save-ic" x-text="saved ? '♥' : '♡'"><?= $isSaved ? '♥' : '♡' ?></span>
            <span x-text="saved ? 'Saved' : 'Save'"><?= $isSaved ? 'Saved' : 'Save' ?></span>
          </button>
          <span class="ht-save-n" x-show="count > 0" x-text="count === 1 ? '1 person saved this' : count + ' people saved this'"></span>
          <a class="ht-save-link" href="/health-tips?saved=1" x-show="saved">Your saved tips →</a>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($tip['media'] || $video): ?>
    <div class="ht-hero-media <?= ($tip['media'] && $video) ? 'has-both' : '' ?>">
      <?php if ($tip['media']): ?>
        <figure class="ht-cover"><img src="<?= e(ecp_ht_image_url((int) $tip['media'][0]['id'])) ?>" alt="<?= e($tip['title']) ?>"></figure>
      <?php endif; ?>
      <?php if ($video): ?>
        <div class="ht-video ht-video-<?= e($video['kind']) ?>">
          <iframe src="<?= e($video['src']) ?>" title="<?= e($tip['title']) ?> — video" loading="lazy" allowfullscreen
            allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
      <?php endif; ?>
    </div>
    <?php if (count($tip['media']) > 1): ?>
      <div class="ht-gallery">
        <?php foreach (array_slice($tip['media'], 1) as $m): ?>
          <img src="<?= e(ecp_ht_image_url((int) $m['id'])) ?>" alt="<?= e($tip['title']) ?>" loading="lazy">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($facts): ?>
      <dl class="ht-facts">
        <?php foreach ($facts as [$k, $v]): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?>
      </dl>
    <?php endif; ?>

    <?php if ($tip['type'] === 'exercise' && $tip['precautions']): ?>
      <div class="ht-caution"><strong>⚠️ Take care:</strong> <?= nl2br(e($tip['precautions'])) ?></div>
    <?php endif; ?>

    <?php if ($tip['body']): ?>
      <div class="ht-prose"><?= nl2br(e($tip['body'])) ?></div>
    <?php endif; ?>

    <?php if ($tip['ingredients'] || $tip['steps']): ?>
    <div class="ht-recipe-grid <?= $tip['ingredients'] ? '' : 'is-single' ?>">
      <?php if ($tip['ingredients']): ?>
        <section class="ht-ingredients">
          <h2>Ingredients</h2>
          <ul>
            <?php foreach ($tip['ingredients'] as $ing): ?>
              <li><?php if ($ing['quantity']): ?><strong><?= e($ing['quantity']) ?></strong> <?php endif; ?><?= e($ing['item']) ?></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
      <?php if ($tip['steps']): ?>
        <section class="ht-steps">
          <h2><?= $tip['type'] === 'recipe' ? 'Method' : 'How to do it' ?></h2>
          <ol>
            <?php foreach ($tip['steps'] as $s): ?><li><?= nl2br(e($s)) ?></li><?php endforeach; ?>
          </ol>
        </section>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tip['tags']): ?>
      <div class="ht-tags">
        <?php foreach ($tip['tags'] as $tg): ?><a href="/health-tips?tag=<?= e($tg['slug']) ?>" class="ht-chip"><?= e($tg['name']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="ht-disclaimer"><?= e($disclaimer) ?></p>

    <aside class="ht-author-card">
      <span class="ht-avatar">
        <?php if ($tip['author_has_avatar']): ?><img src="<?= e(ecp_ht_avatar_url($tip['author_slug'])) ?>" alt=""><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string) $tip['author_name'], 0, 1))) ?></span><?php endif; ?>
      </span>
      <div>
        <p class="ht-mute ht-small">Shared by</p>
        <h3><a href="/health-tips/by/<?= e($tip['author_slug']) ?>"><?= e($tip['author_name']) ?></a></h3>
        <?php if ($tip['author_headline']): ?><p class="ht-mute"><?= e($tip['author_headline']) ?></p><?php endif; ?>
        <?php if ($tip['author_instagram']): ?>
          <a class="ht-insta" href="https://www.instagram.com/<?= e($tip['author_instagram']) ?>/" target="_blank" rel="noopener">@<?= e($tip['author_instagram']) ?> on Instagram ↗</a>
        <?php endif; ?>
      </div>
    </aside>

    <?php
      $related = $tip['is_preview'] ? ['rows' => []] : ecp_ht_public_list(['type' => $tip['type'], 'exclude_id' => (int) $tip['id'], 'limit' => 3]);
    ?>
    <?php if ($related['rows']): ?>
      <section class="ht-related">
        <h2>More <?= e(strtolower($typeMeta['plural'])) ?></h2>
        <div class="ht-grid"><?php foreach ($related['rows'] as $r) ht_card($r); ?></div>
      </section>
    <?php endif; ?>
  </article>

  <?php if (!$tip['is_preview']): ?>
  <script type="application/ld+json"><?= json_encode(ht_json_ld($tip), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
  <?php endif; ?>

<?php elseif ($mode === 'saved'): ?>
  <section class="ht-list-hero">
    <div class="wrap">
      <a class="ht-crumb" href="/health-tips">← All health tips</a>
      <h1>Your saved tips</h1>
      <p class="ht-lede">Recipes, exercises and tips you saved — tap ♥ on any post to add it here.</p>
    </div>
  </section>
  <section class="wrap ht-listing">
    <?php if (!$viewer): ?>
      <div class="ht-empty">
        <p>Sign in to see the tips you saved.</p>
        <button type="button" class="btn btn-primary" onclick="window.ecpAuth ? window.ecpAuth.open('default') : (location.href = '/patient')">Sign in</button>
      </div>
    <?php elseif ($list['rows']): ?>
      <div class="ht-grid"><?php foreach ($list['rows'] as $r) ht_card($r); ?></div>
      <?php ht_pager($list['total'], $page, '/health-tips', ['saved' => 1]); ?>
    <?php else: ?>
      <p class="ht-empty">Nothing saved yet. <a href="/health-tips">Browse health tips</a> and tap ♥ Save on the ones you like.</p>
    <?php endif; ?>
  </section>

<?php elseif ($mode === 'author'): ?>
  <section class="ht-author-hero">
    <div class="wrap ht-author-wrap">
      <span class="ht-avatar ht-avatar-lg">
        <?php if (!empty($author['avatar_path'])): ?><img src="<?= e(ecp_ht_avatar_url($author['slug'])) ?>" alt="<?= e($author['display_name']) ?>"><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string) $author['display_name'], 0, 1))) ?></span><?php endif; ?>
      </span>
      <div>
        <a class="ht-crumb" href="/health-tips">← All health tips</a>
        <h1><?= e($author['display_name']) ?></h1>
        <?php if ($author['headline']): ?><p class="ht-lede"><?= e($author['headline']) ?></p><?php endif; ?>
        <?php if ($author['bio']): ?><p class="ht-bio"><?= nl2br(e($author['bio'])) ?></p><?php endif; ?>
        <div class="ht-links">
          <?php if ($author['instagram_handle']): ?><a href="https://www.instagram.com/<?= e($author['instagram_handle']) ?>/" target="_blank" rel="noopener">Instagram ↗</a><?php endif; ?>
          <?php if ($author['youtube_url']): ?><a href="<?= e($author['youtube_url']) ?>" target="_blank" rel="noopener">YouTube ↗</a><?php endif; ?>
          <?php if ($author['website_url']): ?><a href="<?= e($author['website_url']) ?>" target="_blank" rel="noopener">Website ↗</a><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <section class="wrap ht-listing">
    <?php if ($list['rows']): ?>
      <div class="ht-grid"><?php foreach ($list['rows'] as $r) ht_card($r, false); ?></div>
      <?php ht_pager($list['total'], $page, '/health-tips/by/' . $author['slug'], []); ?>
    <?php else: ?>
      <p class="ht-empty">No posts published yet — check back soon.</p>
    <?php endif; ?>
  </section>

<?php else: ?>
  <section class="ht-list-hero">
    <div class="wrap">
      <span class="eyebrow">Health tips</span>
      <h1>Eat well, move more, feel better</h1>
      <p class="ht-lede">Healthy recipes, simple exercises and everyday habits from contributors we trust. Every post is reviewed by the eClinicPro team before it goes live.</p>
      <form class="ht-search" method="get" action="/health-tips" role="search">
        <?php if ($typeFilter): ?><input type="hidden" name="type" value="<?= e($typeFilter) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search recipes, exercises, tips…" aria-label="Search health tips">
        <button class="btn btn-primary" type="submit">Search</button>
      </form>
    </div>
  </section>

  <section class="wrap ht-listing">
    <nav class="ht-typetabs" aria-label="Type">
      <a href="/health-tips" class="<?= $typeFilter === '' ? 'is-active' : '' ?>">All</a>
      <?php foreach (ECP_HT_TYPES as $t => $meta): ?>
        <a href="/health-tips?type=<?= $t ?>" class="<?= $typeFilter === $t ? 'is-active' : '' ?>"><?= $meta['icon'] ?> <?= e($meta['plural']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php
      $chipGroups = $typeFilter === 'exercise' ? ['focus'] : ($typeFilter === 'recipe' ? ['diet', 'meal', 'goal'] : ['diet', 'meal', 'goal', 'focus']);
      $chips = [];
      foreach ($chipGroups as $g) { $chips = array_merge($chips, ecp_ht_tags($g)); }
    ?>
    <?php if ($chips): ?>
      <div class="ht-chips">
        <?php foreach ($chips as $tg): ?>
          <?php $on = $tagFilter === $tg['slug']; ?>
          <a class="ht-chip <?= $on ? 'is-active' : '' ?>"
             href="/health-tips?<?= e(http_build_query(array_filter(['type' => $typeFilter, 'tag' => $on ? '' : $tg['slug']]))) ?>"><?= e($tg['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($list['rows']): ?>
      <div class="ht-grid"><?php foreach ($list['rows'] as $r) ht_card($r); ?></div>
      <?php ht_pager($list['total'], $page, '/health-tips', ['type' => $typeFilter, 'tag' => $tagFilter, 'q' => $q]); ?>
    <?php elseif ($q !== '' || $tagFilter !== ''): ?>
      <p class="ht-empty">Nothing matches that yet. <a href="/health-tips<?= $typeFilter ? '?type=' . e($typeFilter) : '' ?>">Clear filters</a></p>
    <?php else: ?>
      <p class="ht-empty">Our first recipes and tips are being reviewed — check back soon. 🌱</p>
    <?php endif; ?>
    <p class="ht-disclaimer"><?= e($disclaimer) ?></p>
  </section>
<?php endif; ?>
</main>

<?php if ($mode === 'detail' && $canSave): ?>
<script>
  function htSave(id, saved, count) {
    return {
      saved, count, busy: false,
      async send() {
        const r = await fetch('/api/health_tip_save', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id }),
        });
        return { status: r.status, j: await r.json().catch(() => ({})) };
      },
      async toggle() {
        if (this.busy) return;
        this.busy = true;
        try {
          const { status, j } = await this.send();
          if (status === 401) {
            // Sign in, then save; the modal reloads the page afterwards.
            if (window.ecpAuth) window.ecpAuth.require('save_tip', () => this.send());
            else location.href = '/patient';
            return;
          }
          if (j.ok) { this.saved = j.saved; this.count = j.count; }
        } catch (e) {
          // network error: leave the button as it was
        } finally {
          this.busy = false;
        }
      },
    };
  }
</script>
<?php endif; ?>

<?php
require __DIR__ . '/partials/footer.php';

// ---------------------------------------------------------------------
// View helpers
// ---------------------------------------------------------------------

function ht_card(array $r, bool $showAuthor = true): void
{
    $meta = ECP_HT_TYPES[$r['type']];
    $bits = [];
    if ($r['type'] === 'recipe') {
        $mins = (int) $r['prep_minutes'] + (int) $r['cook_minutes'];
        if ($mins) $bits[] = ecp_ht_minutes($mins);
        if ($r['protein_g'] !== null) $bits[] = '~' . rtrim(rtrim((string) $r['protein_g'], '0'), '.') . ' g protein';
    } elseif ($r['type'] === 'exercise') {
        if ($r['duration_minutes']) $bits[] = ecp_ht_minutes((int) $r['duration_minutes']);
        if ($r['difficulty']) $bits[] = ucfirst((string) $r['difficulty']);
    }
    ?>
    <a class="ht-card-item" href="/health-tips/<?= e($r['slug']) ?>">
      <div class="ht-card-img ht-type-<?= e($r['type']) ?>">
        <?php if ($r['cover_id']): ?>
          <img src="<?= e(ecp_ht_image_url((int) $r['cover_id'])) ?>" alt="" loading="lazy">
        <?php else: ?>
          <span class="ht-card-ic"><?= $meta['icon'] ?></span>
        <?php endif; ?>
        <span class="ht-card-kind"><?= e($meta['label']) ?></span>
      </div>
      <div class="ht-card-body">
        <h3><?= e($r['title']) ?></h3>
        <?php if ($r['summary']): ?><p><?= e($r['summary']) ?></p><?php endif; ?>
        <div class="ht-card-meta">
          <?php if ($showAuthor): ?><span>By <?= e($r['author_name']) ?></span><?php endif; ?>
          <?php foreach ($bits as $b): ?><span><?= e($b) ?></span><?php endforeach; ?>
          <?php if ((int) ($r['save_count'] ?? 0) > 0): ?><span class="ht-card-saves">♥ <?= (int) $r['save_count'] ?></span><?php endif; ?>
        </div>
      </div>
    </a>
    <?php
}

function ht_pager(int $total, int $page, string $base, array $params): void
{
    $pages = (int) ceil($total / ECP_HT_PER_PAGE);
    if ($pages <= 1) {
        return;
    }
    $url = static function (int $p) use ($base, $params): string {
        $qs = http_build_query(array_filter($params + ['page' => $p > 1 ? $p : '']));
        return $base . ($qs ? '?' . $qs : '');
    };
    echo '<nav class="ht-pager">';
    if ($page > 1) echo '<a href="' . e($url($page - 1)) . '">← Newer</a>';
    echo '<span>Page ' . $page . ' of ' . $pages . '</span>';
    if ($page < $pages) echo '<a href="' . e($url($page + 1)) . '">Older →</a>';
    echo '</nav>';
}

/** schema.org Recipe for recipes (rich results); Article for the rest. */
function ht_json_ld(array $tip): array
{
    $url = ecp_site_url('/health-tips/' . $tip['slug']);
    $images = array_map(static fn ($m) => ecp_site_url(ecp_ht_image_url((int) $m['id'])), $tip['media']);
    $author = ['@type' => 'Person', 'name' => $tip['author_name'], 'url' => ecp_site_url('/health-tips/by/' . $tip['author_slug'])];
    $published = $tip['published_at'] ? date('c', strtotime((string) $tip['published_at'])) : null;

    if ($tip['type'] !== 'recipe') {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $tip['title'],
            'description' => $tip['summary'],
            'image' => $images ?: null,
            'author' => $author,
            'datePublished' => $published,
            'dateModified' => date('c', strtotime((string) $tip['updated_at'])),
            'mainEntityOfPage' => $url,
            'publisher' => ['@type' => 'Organization', 'name' => 'eClinicPro', 'logo' => ecp_site_url('/assets/img/logos/logo.png')],
        ], static fn ($v) => $v !== null);
    }

    $iso = static fn ($m) => $m !== null ? 'PT' . (int) $m . 'M' : null;
    $nutrition = array_filter([
        '@type' => 'NutritionInformation',
        'proteinContent' => $tip['protein_g'] !== null ? rtrim(rtrim((string) $tip['protein_g'], '0'), '.') . ' g' : null,
        'calories' => $tip['calories_kcal'] !== null ? (int) $tip['calories_kcal'] . ' calories' : null,
    ]);
    $diet = [];
    foreach ($tip['tags'] as $tg) {
        if ($tg['slug'] === 'vegetarian') $diet[] = 'https://schema.org/VegetarianDiet';
        if ($tg['slug'] === 'vegan') $diet[] = 'https://schema.org/VeganDiet';
        if ($tg['slug'] === 'gluten-free') $diet[] = 'https://schema.org/GlutenFreeDiet';
    }
    $categories = array_column(array_filter($tip['tags'], static fn ($t) => $t['tag_group'] === 'meal'), 'name');
    $total = ($tip['prep_minutes'] !== null || $tip['cook_minutes'] !== null) ? (int) $tip['prep_minutes'] + (int) $tip['cook_minutes'] : null;

    return array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => $tip['title'],
        'description' => $tip['summary'],
        'image' => $images ?: null,
        'author' => $author,
        'datePublished' => $published,
        'prepTime' => $iso($tip['prep_minutes']),
        'cookTime' => $iso($tip['cook_minutes']),
        'totalTime' => $iso($total),
        'recipeYield' => $tip['servings'] ? (int) $tip['servings'] . ' servings' : null,
        'recipeCategory' => $categories ? implode(', ', $categories) : null,
        'suitableForDiet' => $diet ?: null,
        'keywords' => implode(', ', array_column($tip['tags'], 'name')) ?: null,
        'recipeIngredient' => array_map(static fn ($i) => trim(($i['quantity'] ? $i['quantity'] . ' ' : '') . $i['item']), $tip['ingredients']),
        'recipeInstructions' => array_map(static fn ($s) => ['@type' => 'HowToStep', 'text' => $s], $tip['steps']),
        'nutrition' => count($nutrition) > 1 ? $nutrition : null,
    ], static fn ($v) => $v !== null);
}
