<?php
// =====================================================================
// lab-listing.php — Lab tests/packages LISTING + browse hub.
//
// Serves two SEO-friendly surfaces from one file:
//   /lab/tests                 → the hub ("All"): every package, offer & test
//   /lab/category/{slug}       → one category's listing (e.g. Heart, Thyroid)
//
// Dispatched by request_router.php / .htaccess, which set $_GET:
//   type=hub                    (the /lab/tests hub)
//   type=category & slug=…      (a category listing)
//
// Data comes from the DB via ecp_lab_listing() / ecp_lab_categories().
// Degrades to an empty-but-valid page if the DB is unavailable.
//
// Do NOT re-enter the router here — this file IS a dispatch target.
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/lab_catalog.php';

$view = strtolower(trim((string) ($_GET['type'] ?? 'hub')));   // 'hub' | 'category'
$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));

if ($view === 'category') {
    if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return;
    }
    $listing = ecp_lab_listing($slug);
    if (!$listing['category']) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return;
    }
    $catName   = ecp_lab_titlecase($listing['category']['name']);
    $pageTitle = $catName . ' Tests & Packages — Book Online | eClinicPro';
    $metaDesc  = 'Book ' . $catName . ' lab tests and health packages online at eClinicPro — '
        . $listing['total'] . '+ options, NABL-accredited labs, free home sample collection, '
        . 'digital reports and a free doctor consult.';
    $heading    = $catName . ' Tests & Health Packages';
    $subheading = 'Book ' . $catName . ' diagnostic tests and packages with free home sample collection.';
    $canonicalUrl = ecp_site_url(ecp_lab_category_url($slug));
    $crumbLabel = $catName;
} elseif ($view === 'symptom') {
    if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return;
    }
    $listing = ecp_lab_symptom_listing($slug);
    if (!$listing['category']) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return;
    }
    $symName    = $listing['category']['name'];
    $symDesc    = $listing['category']['desc'] ?? '';
    $pageTitle  = 'Tests for ' . $symName . ' — Book Online | eClinicPro';
    $metaDesc   = 'Not sure which test to book for ' . strtolower($symName) . '? '
        . 'See the ' . $listing['total'] . '+ most relevant lab tests and health packages at eClinicPro — '
        . 'NABL-accredited labs, free home sample collection and a free doctor consult.';
    $heading    = 'Tests for ' . $symName;
    $subheading = $symDesc !== '' ? $symDesc . ' — recommended tests & packages.'
        : 'The most relevant tests and packages for this concern.';
    $canonicalUrl = ecp_site_url(ecp_lab_symptom_url($slug));
    $crumbLabel = $symName;
} else {
    $view = 'hub';
    $listing = ecp_lab_listing(null);
    $pageTitle = 'All Lab Tests, Health Packages & Offers — Book Online | eClinicPro';
    $metaDesc  = 'Browse and book all diagnostic lab tests, full-body health packages and offers '
        . 'online at eClinicPro. NABL-accredited labs, free home sample collection, digital reports.';
    $heading    = 'All Lab Tests, Packages & Offers';
    $subheading = 'Browse everything in one place — filter by packages, offers or individual tests.';
    $canonicalUrl = ecp_site_url('/lab/tests');
    $crumbLabel = 'All Tests';
}

$activePage = 'lab';
$hideFinalCta = true;
$noindex = false; // these ARE the SEO pages — let Google index them

// Sections in tab order. 'all' is synthetic (shows everything).
$packages = $listing['packages'];
$offers   = $listing['offers'];
$tests    = $listing['tests'];
$allItems = array_merge($packages, $offers, $tests);
$categories = ecp_lab_categories(2); // rail of other categories to explore

require __DIR__ . '/partials/header.php';

/** Render one product card (reused across tabs). */
$renderCard = static function (array $it): string {
    $typeLabel = ['package' => 'Package', 'offer' => 'Offer', 'test' => 'Test'][$it['type']] ?? 'Test';
    $hasOff = $it['off'] > 0 && $it['mrp'] > $it['price'];
    ob_start(); ?>
    <?php $fasting = $it['fasting'] ?? ''; ?>
    <article class="lab-list-card lab-pkg-card" data-type="<?= e($it['type']) ?>"
             data-search="<?= e(strtolower($it['title'])) ?>">
        <div class="lab-pkg-card-body">
            <!-- Top meta chips: audience + fasting (both real / accurate). -->
            <div class="lab-pkg-chips">
                <span class="lab-pkg-chip lab-pkg-chip-gender">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M6 21c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    For Male &amp; Female
                </span>
                <?php if ($fasting === 'NF'): ?>
                    <span class="lab-pkg-chip lab-pkg-chip-fast-ok">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 3v7a3 3 0 0 0 6 0V3M9 3v18M17 3c-1.5 1.5-2 3.5-2 6s.5 4.5 2 6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        No Fasting Required
                    </span>
                <?php elseif ($fasting === 'CF'): ?>
                    <span class="lab-pkg-chip lab-pkg-chip-fast-req">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 3v7a3 3 0 0 0 6 0V3M9 3v18M17 3c-1.5 1.5-2 3.5-2 6s.5 4.5 2 6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Fasting Required
                    </span>
                <?php endif; ?>
                <span class="lab-pkg-chip lab-list-card-tag-<?= e($it['type']) ?> lab-list-type-chip"><?= e($typeLabel) ?></span>
            </div>

            <h3 class="lab-pkg-card-title">
                <a href="<?= e($it['url']) ?>"><?= e($it['title']) ?></a>
            </h3>

            <!-- Stat row: test count + report time, with icons. -->
            <div class="lab-pkg-stats">
                <div class="lab-pkg-stat">
                    <span class="lab-pkg-stat-ico" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M9 3h6M10 3v5.5L5.8 17a3 3 0 0 0 2.7 4.3h7a3 3 0 0 0 2.7-4.3L14 8.5V3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M8 14h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="lab-pkg-stat-meta">
                        <small>Tests Included</small>
                        <strong><?= (int) $it['params'] ?> Test<?= $it['params'] == 1 ? '' : 's' ?></strong>
                    </span>
                </div>
                <div class="lab-pkg-stat">
                    <span class="lab-pkg-stat-ico" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M12 7.5V12l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="lab-pkg-stat-meta">
                        <small>Reports within</small>
                        <strong>24 Hours*</strong>
                    </span>
                </div>
            </div>

            <?php if (!empty($it['groups'])): ?>
            <div class="lab-list-card-groups">
                <?php foreach ($it['groups'] as $grp => $cnt): ?>
                <span class="lab-list-card-group"><?= e($grp) ?> <b><?= (int) $cnt ?></b></span>
                <?php endforeach; ?>
                <?php if (!empty($it['groups_remaining'])): ?>
                <a href="<?= e($it['url']) ?>" class="lab-list-card-group lab-pkg-card-group-more">+<?= (int) $it['groups_remaining'] ?> more</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Exclusive offer: two-colour price + % chip. -->
            <div class="lab-pkg-offer">
                <?php if ($hasOff): ?>
                    <span class="lab-pkg-offer-label">Exclusive Offer</span>
                <?php endif; ?>
                <div class="lab-pkg-offer-price">
                    <span class="lab-pkg-offer-now">₹<?= e(number_format($it['price'])) ?></span>
                    <?php if ($hasOff): ?>
                        <s class="lab-pkg-offer-mrp">₹<?= e(number_format($it['mrp'])) ?></s>
                        <span class="lab-pkg-offer-off">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12V6a2 2 0 0 1 2-2h6l8 8-8 8-8-8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="8.5" cy="8.5" r="1.3" fill="currentColor"/></svg>
                            <?= (int) $it['off'] ?>% off
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lab-pkg-card-actions">
                <a href="<?= e($it['url']) ?>" class="lab-pkg-card-detail">Detail</a>
                <button type="button" class="lab-pkg-card-book lab-book" data-book="<?= e($it['title']) ?>">Book Now</button>
            </div>
        </div>
    </article>
    <?php return (string) ob_get_clean();
};
?>

<main class="store lab-listing">

    <!-- Breadcrumb + heading ------------------------------------------------ -->
    <section class="lab-list-head">
        <div class="wrap">
            <nav class="lab-list-crumbs" aria-label="Breadcrumb">
                <a href="/lab">Lab Tests</a>
                <span aria-hidden="true">›</span>
                <span><?= e($crumbLabel) ?></span>
            </nav>
            <h1 class="lab-list-title"><?= e($heading) ?></h1>
            <p class="lab-list-sub"><?= e($subheading) ?></p>
        </div>
    </section>

    <div class="wrap lab-list-wrap">

        <!-- Explore by health concern (internal links = SEO) — shown on top -->
        <?php if ($categories): ?>
        <section class="lab-list-explore lab-list-explore-top">
            <h2>Explore by health concern</h2>
            <div class="lab-list-cats">
                <?php foreach ($categories as $c): ?>
                <a href="<?= e(ecp_lab_category_url($c['slug'])) ?>"
                   class="lab-list-cat<?= (isset($listing['category']['slug']) && $listing['category']['slug'] === $c['slug']) ? ' is-current' : '' ?>">
                    <?= e(ecp_lab_titlecase($c['name'])) ?>
                    <span><?= (int) $c['n'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Tabs + view toggle -->
        <div class="lab-list-toolbar">
            <div class="lab-list-tabs" role="tablist" aria-label="Filter by type">
                <?php
                $tabs = [
                    'all'     => ['All', count($allItems)],
                    'package' => ['Packages', count($packages)],
                    'offer'   => ['Offers', count($offers)],
                    'test'    => ['Tests', count($tests)],
                ];
                $first = true;
                foreach ($tabs as $key => [$label, $count]):
                    if ($count === 0 && $key !== 'all') continue; ?>
                    <button type="button" class="lab-list-tab<?= $first ? ' is-active' : '' ?>"
                            role="tab" aria-selected="<?= $first ? 'true' : 'false' ?>"
                            data-list-tab="<?= e($key) ?>">
                        <?= e($label) ?> <span class="lab-list-tab-n"><?= (int) $count ?></span>
                    </button>
                <?php $first = false; endforeach; ?>
            </div>
            <div class="lab-list-view" role="group" aria-label="View style">
                <button type="button" class="lab-list-viewbtn is-active" data-view="grid" aria-label="Grid view" title="Grid view">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                </button>
                <button type="button" class="lab-list-viewbtn" data-view="list" aria-label="List view" title="List view">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Grid (data-view flips between box grid and full-width list) -->
        <?php if ($allItems): ?>
        <div class="lab-list-grid" id="labListGrid" data-view="grid">
            <?php foreach ($allItems as $it) echo $renderCard($it); ?>
        </div>
        <p class="lab-list-empty" id="labListEmpty" hidden>No items in this tab.</p>
        <?php else: ?>
        <div class="lab-list-none">
            <p>No tests found here yet. <a href="/lab/tests">Browse all tests →</a></p>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
    // Type tabs: filter the grid client-side (All / Packages / Offers / Tests).
    (function () {
        var tabs = document.querySelectorAll('[data-list-tab]');
        var grid = document.getElementById('labListGrid');
        var empty = document.getElementById('labListEmpty');
        if (!tabs.length || !grid) return;
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.lab-list-card'));
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var t = tab.getAttribute('data-list-tab');
                tabs.forEach(function (x) {
                    x.classList.toggle('is-active', x === tab);
                    x.setAttribute('aria-selected', x === tab ? 'true' : 'false');
                });
                var shown = 0;
                cards.forEach(function (card) {
                    var show = (t === 'all') || card.getAttribute('data-type') === t;
                    card.style.display = show ? '' : 'none';
                    if (show) shown++;
                });
                if (empty) empty.hidden = shown > 0;
            });
        });
    })();

    // Grid / list view toggle. Flips the container's data-view (CSS does the
    // layout change) and remembers the choice in localStorage.
    (function () {
        var grid = document.getElementById('labListGrid');
        var btns = document.querySelectorAll('.lab-list-viewbtn');
        if (!grid || !btns.length) return;
        function setView(v) {
            grid.setAttribute('data-view', v);
            btns.forEach(function (b) {
                b.classList.toggle('is-active', b.getAttribute('data-view') === v);
            });
            try { localStorage.setItem('labListView', v); } catch (e) {}
        }
        btns.forEach(function (b) {
            b.addEventListener('click', function () { setView(b.getAttribute('data-view')); });
        });
        try {
            var saved = localStorage.getItem('labListView');
            if (saved === 'list' || saved === 'grid') setView(saved);
        } catch (e) {}
    })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
