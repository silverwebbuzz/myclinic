<?php
// =====================================================================
// health-tips.php — Wellness Hub recipes, exercises, quick tips, videos.
//
//   /wellness/recipes|exercises|tips|videos      section (?tag=, ?q=, ?page=)   _wh_section
//   /wellness/recipe|exercise|tip|video/{slug}   one post                       _wh_kind + _wh_slug
//
// Old URLs 301 here (Phase 3):
//   /health-tips[?type=…]   → section (or /wellness)      /health-tips?saved=1 → /wellness/saved
//   /health-tips/{slug}     → /wellness/{type}/{slug}     /health-tips/by/{x}  → /wellness/creators/{creator}
//
// Only published posts by active contributors are visible. A contributor can
// preview their own unpublished post (noindex, banner) from their editor.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';
require_once __DIR__ . '/partials/health_tips.php';

$section = strtolower(trim((string) ($_GET['_wh_section'] ?? '')));
$kindWord = strtolower(trim((string) ($_GET['_wh_kind'] ?? '')));
$slug = strtolower(trim((string) ($_GET['_wh_slug'] ?? '')));

// ---------------------------------------------------------------------
// Legacy /health-tips URLs → permanent redirects
// ---------------------------------------------------------------------
if ($section === '' && $kindWord === '') {
    $to = '/wellness';
    $oldSlug = strtolower(trim((string) ($_GET['_ht_slug'] ?? '')));
    $oldBy = strtolower(trim((string) ($_GET['_ht_by'] ?? '')));
    $db = ecp_db();
    if ($oldSlug !== '' && $db) {
        $st = $db->prepare('SELECT type, slug FROM health_tips WHERE slug = :s AND is_deleted = 0 LIMIT 1');
        $st->execute(['s' => $oldSlug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            require __DIR__ . '/404.php';
            return;
        }
        $to = ecp_ht_post_url($row);
    } elseif ($oldBy !== '' && $db) {
        $c = ecp_ht_contributor_by_slug($oldBy);
        $to = $c ? ecp_ht_author_url(['contributor_id' => (int) $c['id'], 'author_slug' => $c['slug']]) : '/wellness';
    } elseif (!empty($_GET['saved'])) {
        $to = '/wellness/saved';
    } else {
        $type = (string) ($_GET['type'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        $tag = (string) ($_GET['tag'] ?? '');
        if (isset(ECP_HT_TYPES[$type])) {
            $to = ecp_ht_section_url($type);
            $qs = http_build_query(array_filter(['tag' => $tag, 'q' => $q]));
            $to .= $qs ? '?' . $qs : '';
        } elseif ($q !== '' || $tag !== '') {
            $to = '/wellness/search?' . http_build_query(array_filter(['q' => $q, 'tag' => $tag]));
        }
    }
    header('Location: ' . $to, true, 301);
    return;
}

$tip = null;
$mode = 'list';
$typeFilter = '';
$viewer = ecp_patient_current();

if ($kindWord !== '') {
    $mode = 'detail';
    $viewerC = $viewer ? ecp_ht_contributor_for_identity((int) $viewer['id']) : null;
    $tip = isset(ECP_HT_TYPES[$kindWord]) ? ecp_ht_find_by_slug($slug, $viewerC ? (int) $viewerC['id'] : null) : null;
    if ($tip && $tip['type'] !== $kindWord) {
        header('Location: ' . ecp_ht_post_url($tip), true, 301);   // wrong type word in the URL
        return;
    }
} else {
    $typeFilter = ecp_ht_type_from_path($section) ?? '';
}

if (($mode === 'detail' && !$tip) || ($mode === 'list' && $typeFilter === '')) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$tagFilter = preg_match('/^[a-z0-9\-]{1,60}$/', (string) ($_GET['tag'] ?? '')) ? (string) $_GET['tag'] : '';
$q         = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$page      = max(1, (int) ($_GET['page'] ?? 1));

$disclaimer = 'General wellness information shared by independent Health Creators — not medical advice, and eClinicPro does not guarantee any health result. If you have a health condition, allergy, are pregnant, or take medicines, check with your doctor before changing your diet or exercise.';

// ---------------------------------------------------------------------
// Page meta
// ---------------------------------------------------------------------
$activePage = 'wellness';
if ($mode === 'detail') {
    $typeMeta  = ECP_HT_TYPES[$tip['type']];
    $pageTitle = $tip['title'] . ' — ' . $typeMeta['label'] . ' by ' . $tip['author_name'] . ' | eClinicPro Wellness Hub';
    $metaDesc  = (string) ($tip['summary'] ?: mb_substr((string) $tip['body'], 0, 155));
    $canonicalUrl = ecp_site_url(ecp_ht_post_url($tip));
    $ogType = $tip['type'] === 'video' ? 'video.other' : 'article';
    $video = ecp_ht_video_embed($tip['video_url']);
    if ($tip['media']) {
        $ogImage = ecp_site_url(ecp_ht_image_url((int) $tip['media'][0]['id']));
    } elseif ($video && $video['thumb']) {
        $ogImage = $video['thumb'];
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
    $authorUrl = ecp_ht_author_url($tip);
    $authorBadge = ecp_ht_author_badge($tip);
} else {
    $meta = ECP_HT_TYPES[$typeFilter];
    $intro = [
        'recipe'   => ['Healthy recipes', 'Protein-rich, low-oil and everyday Indian recipes from Health Creators we trust.'],
        'exercise' => ['Simple exercises', 'Short routines for mobility, strength and posture — with clear steps and safety notes.'],
        'tip'      => ['Quick health tips', 'Small everyday habits that help — explained in plain words.'],
        'video'    => ['Health videos', 'Short videos and reels from Health Creators: recipes, workouts and everyday habits.'],
    ][$typeFilter];
    $pageTitle = $intro[0] . ' — ' . strtolower($meta['plural']) . ' from trusted Health Creators | eClinicPro Wellness Hub';
    $metaDesc  = $intro[1] . ' Every post is reviewed by the eClinicPro team before it goes live.';
    $canonicalUrl = ecp_site_url(ecp_ht_section_url($typeFilter));
    $list = ecp_ht_public_list(['type' => $typeFilter, 'tag' => $tagFilter, 'q' => $q, 'page' => $page]);
    if ($q !== '' || $tagFilter !== '' || $page > 1 || $list['total'] === 0) {
        $noindex = true;   // filtered / search / paged / empty views stay out of the index
    }
}

require __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/health-tips.css?v=<?= @filemtime(__DIR__ . '/assets/css/health-tips.css') ?: 1 ?>">

<main class="ht-public">
<?php $whNavActive = ECP_HT_TYPES[$mode === 'detail' ? $tip['type'] : $typeFilter]['path'] ?? ''; require __DIR__ . '/partials/wellness_nav.php'; ?>
<?php if ($mode === 'detail'): ?>
  <?php
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
    // Video poster: the creator's own cover photo, else YouTube's thumbnail.
    $videoPoster = $video ? ($tip['media'] ? ecp_ht_image_url((int) $tip['media'][0]['id']) : $video['thumb']) : null;
    $photos = $tip['type'] === 'video' ? array_slice($tip['media'], 1) : $tip['media'];
  ?>
  <?php if ($tip['is_preview']): ?>
    <div class="ht-preview-bar">Preview — only you can see this. Status: <strong><?= e(ECP_HT_STATUS_LABELS[$tip['status']] ?? $tip['status']) ?></strong> · <a href="<?= e(ecp_portal_url('/creator/posts/' . (int) $tip['id'])) ?>">Back to editor</a></div>
  <?php endif; ?>

  <article class="wrap ht-article">
    <nav class="ht-crumbs" aria-label="Breadcrumb">
      <a href="/wellness">Wellness Hub</a> <span>›</span>
      <a href="<?= e(ecp_ht_section_url($tip['type'])) ?>"><?= e($typeMeta['plural']) ?></a>
    </nav>

    <header class="ht-art-head">
      <span class="ht-kind"><?= $typeMeta['icon'] ?> <?= e($typeMeta['label']) ?></span>
      <h1><?= e($tip['title']) ?></h1>
      <?php if ($tip['summary']): ?><p class="ht-lede"><?= e($tip['summary']) ?></p><?php endif; ?>
      <a class="ht-byline" href="<?= e($authorUrl) ?>">
        <span class="ht-avatar ht-avatar-sm">
          <?php if ($tip['author_has_avatar']): ?><img src="<?= e(ecp_ht_avatar_url($tip['author_slug'])) ?>" alt=""><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string) $tip['author_name'], 0, 1))) ?></span><?php endif; ?>
        </span>
        <span>By <strong><?= e($tip['author_name']) ?></strong><?php if ($authorBadge): ?> <span class="ht-verified">✓ <?= e($authorBadge) ?></span><?php endif; ?>
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
          <a class="ht-save-link" href="/wellness/saved" x-show="saved">Your saved items →</a>
        </div>
      <?php endif; ?>
    </header>

    <?php if (($tip['type'] !== 'video' && $tip['media']) || $video): ?>
    <div class="ht-hero-media <?= ($tip['type'] !== 'video' && $tip['media'] && $video) ? 'has-both' : '' ?>">
      <?php if ($tip['type'] !== 'video' && $tip['media']): ?>
        <figure class="ht-cover"><img src="<?= e(ecp_ht_image_url((int) $tip['media'][0]['id'])) ?>" alt="<?= e($tip['title']) ?>"></figure>
      <?php endif; ?>
      <?php if ($video): ?>
        <?php // Click-to-load: the YouTube / Instagram player (heavy) loads only when someone presses play. ?>
        <div class="ht-video ht-video-<?= e($video['kind']) ?>" x-data="{ on: false }">
          <template x-if="on">
            <iframe src="<?= e($video['src']) ?><?= $video['kind'] === 'youtube' ? '?autoplay=1' : '' ?>" title="<?= e($tip['title']) ?> — video" allowfullscreen
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin"></iframe>
          </template>
          <button type="button" class="ht-video-facade" x-show="!on" @click="on = true" aria-label="Play video: <?= e($tip['title']) ?>">
            <?php if ($videoPoster): ?><img src="<?= e($videoPoster) ?>" alt="" loading="lazy"><?php endif; ?>
            <span class="ht-play">▶</span>
            <span class="ht-video-src"><?= $video['kind'] === 'youtube' ? 'YouTube' : 'Instagram' ?> · tap to play</span>
          </button>
          <noscript><a href="<?= e((string) $tip['video_url']) ?>" target="_blank" rel="noopener">Watch the video ↗</a></noscript>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (count($photos) > ($tip['type'] === 'video' ? 0 : 1)): ?>
      <div class="ht-gallery">
        <?php foreach ($tip['type'] === 'video' ? $photos : array_slice($photos, 1) as $m): ?>
          <img src="<?= e(ecp_ht_image_url((int) $m['id'])) ?>" alt="<?= e($tip['title']) ?>" loading="lazy">
        <?php endforeach; ?>
      </div>
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
        <?php foreach ($tip['tags'] as $tg): ?><a href="<?= e(ecp_ht_section_url($tip['type']) . '?tag=' . $tg['slug']) ?>" class="ht-chip"><?= e($tg['name']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="ht-disclaimer"><?= e($disclaimer) ?></p>

    <aside class="ht-author-card">
      <span class="ht-avatar">
        <?php if ($tip['author_has_avatar']): ?><img src="<?= e(ecp_ht_avatar_url($tip['author_slug'])) ?>" alt=""><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string) $tip['author_name'], 0, 1))) ?></span><?php endif; ?>
      </span>
      <div>
        <p class="ht-mute ht-small">Shared by</p>
        <h3><a href="<?= e($authorUrl) ?>"><?= e($tip['author_name']) ?></a><?php if ($authorBadge): ?> <span class="ht-verified">✓ <?= e($authorBadge) ?></span><?php endif; ?></h3>
        <?php if ($tip['author_headline']): ?><p class="ht-mute"><?= e($tip['author_headline']) ?></p><?php endif; ?>
        <?php if ($tip['author_instagram']): ?>
          <a class="ht-insta" href="https://www.instagram.com/<?= e($tip['author_instagram']) ?>/" target="_blank" rel="noopener">@<?= e($tip['author_instagram']) ?> on Instagram ↗</a>
        <?php endif; ?>
      </div>
    </aside>

    <?php if (!$tip['is_preview']) { $whcType = 'tip'; $whcId = (int) $tip['id']; require __DIR__ . '/partials/wellness_comments_block.php'; } ?>

    <?php if (!$viewer && !$tip['is_preview']) { require __DIR__ . '/partials/wellness_signup_card.php'; } ?>

    <?php
      $related = $tip['is_preview'] ? ['rows' => []] : ecp_ht_public_list(['type' => $tip['type'], 'exclude_id' => (int) $tip['id'], 'limit' => 3]);
    ?>
    <?php if ($related['rows']): ?>
      <section class="ht-related">
        <h2>More <?= e(strtolower($typeMeta['plural'])) ?></h2>
        <div class="ht-grid"><?php foreach ($related['rows'] as $r) ecp_ht_card($r); ?></div>
      </section>
    <?php endif; ?>
  </article>

  <?php if (!$tip['is_preview']): ?>
  <script type="application/ld+json"><?= json_encode(ht_json_ld($tip, $video, $videoPoster), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
  <?php endif; ?>

<?php else: ?>
  <section class="ht-list-hero">
    <div class="wrap">
      <h1><?= e($intro[0]) ?></h1>
      <p class="ht-lede"><?= e($intro[1]) ?> Every post is reviewed by the eClinicPro team before it goes live.</p>
      <form class="ht-search" method="get" action="<?= e(ecp_ht_section_url($typeFilter)) ?>" role="search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search <?= e(strtolower($meta['plural'])) ?>…" aria-label="Search <?= e(strtolower($meta['plural'])) ?>">
        <button class="btn btn-primary" type="submit">Search</button>
      </form>
    </div>
  </section>

  <section class="wrap ht-listing">
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
             href="<?= e(ecp_ht_section_url($typeFilter) . ($on ? '' : '?tag=' . $tg['slug'])) ?>"><?= e($tg['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($list['rows']): ?>
      <div class="ht-grid"><?php foreach ($list['rows'] as $r) ecp_ht_card($r); ?></div>
      <?php ht_pager($list['total'], $page, ecp_ht_section_url($typeFilter), ['tag' => $tagFilter, 'q' => $q]); ?>
    <?php elseif ($q !== '' || $tagFilter !== ''): ?>
      <p class="ht-empty">Nothing matches that yet. <a href="<?= e(ecp_ht_section_url($typeFilter)) ?>">Clear filters</a></p>
    <?php else: ?>
      <p class="ht-empty">Our first <?= e(strtolower($meta['plural'])) ?> are being reviewed — check back soon. 🌱</p>
    <?php endif; ?>

    <?php if (!$viewer) { require __DIR__ . '/partials/wellness_signup_card.php'; } ?>
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

/** schema.org Recipe for recipes (rich results), VideoObject for videos, Article for the rest. */
function ht_json_ld(array $tip, ?array $video, ?string $videoPoster): array
{
    $url = ecp_site_url(ecp_ht_post_url($tip));
    $images = array_map(static fn ($m) => ecp_site_url(ecp_ht_image_url((int) $m['id'])), $tip['media']);
    $author = ['@type' => 'Person', 'name' => $tip['author_name'], 'url' => ecp_site_url(ecp_ht_author_url($tip))];
    $published = $tip['published_at'] ? date('c', strtotime((string) $tip['published_at'])) : null;

    if ($tip['type'] === 'video' && $video) {
        $thumb = $videoPoster ? (str_starts_with($videoPoster, 'http') ? $videoPoster : ecp_site_url($videoPoster)) : null;
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $tip['title'],
            'description' => $tip['summary'] ?: mb_substr((string) $tip['body'], 0, 300) ?: $tip['title'],
            'thumbnailUrl' => $thumb ? [$thumb] : null,
            'uploadDate' => $published,
            'embedUrl' => $video['src'],
            'url' => $url,
            'author' => $author,
            'publisher' => ['@type' => 'Organization', 'name' => 'eClinicPro', 'logo' => ['@type' => 'ImageObject', 'url' => ecp_site_url('/assets/img/logos/logo.png')]],
        ], static fn ($v) => $v !== null);
    }

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
