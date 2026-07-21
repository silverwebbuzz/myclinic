<?php
// =====================================================================
// lab.php — STATIC BLUEPRINT for Lab Tests & Health Packages.
//
// Marketing/preview page for the upcoming diagnostics partnership
// (Sun Pathology Lab + others). Modeled on eclinicpro-health-store.php:
// no DB, no cart, no checkout. Every clickable element triggers a
// "Coming soon" toast via [data-soon].
//
// Layout strategy: REUSE the existing .store-* design system (hero,
// sections, gradient tiles, toast, CTA banner) so this page needs no
// new infrastructure. A small .lab-* block in styles.css adds the few
// lab-specific pieces (package cards with test lists, organ grid, steps).
//
// Placeholder packages/prices below — swap for Sun Pathology's real
// package + price sheet once the partnership is signed.
//
// NOTE: do NOT require/run the clean-URL router here. This page IS a
// dispatch target (/lab -> this file). Re-requiring the router recurses.
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/lab_catalog.php';

$labPhotos = ecp_lab_photos();
$raw = ecp_lab_raw_catalog();
$packages = $raw['packages'];
// India-tuned storefront tabs (Full Body, Diabetes, Thyroid, Vitamins, Women,
// Heart, Fever & Infection, Senior) — replaces the old static $raw list.
$packageFilters = ecp_lab_package_filters();
$organs = $raw['organs'];
$concerns = $raw['concerns'];
$lifeStage = $raw['lifeStage'];
$why = $raw['why'];
$steps = $raw['steps'];
$partners = $raw['partners'];

$pageTitle  = 'Lab Tests & Health Packages — Book Online | eClinicPro';
$metaDesc   = 'Book diagnostic lab tests & full-body health packages online. NABL-accredited labs, free home sample collection, digital reports and doctor consults. Launching soon.';
$activePage = 'lab';
$hideFinalCta = true; // renders its own footer CTA banner
$noindex = true; // keep out of Google until bookings/partners are live —
// URL stays shareable for partner demos.
// Flaticon icons for the "How It Works" flow (same set as the detail page).
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@flaticon/flaticon-uicons@3.3.1/css/regular/rounded.css">';

// 6-step "How It Works" flow — kept in sync with lab-detail.php's $stepsHow
// so the landing and package pages tell the same story. [icon, title, blurb].
$howSteps = [
    ['fi-rr-clipboard-list-check', 'Choose',          'Select a test or package as per your need'],
    ['fi-rr-calendar-clock',       'Book Slot',       'Pick a convenient slot for sample collection'],
    ['fi-rr-scooter',              'Home Collection', 'Our phlebotomist collects the sample from your home'],
    ['fi-rr-flask',                'Testing',         'Sample tested at NABL & CAP accredited labs'],
    ['fi-rr-smartphone',           'Digital Report',  'Get your reports on the app within 24 hours'],
    ['fi-rr-doctor',               'Doctor Review',   'Free consultation with expert report review'],
];

require __DIR__ . '/partials/header.php';
?>

<!-- Preview banner ------------------------------------------------------ -->
<div class="store-preview-bar">
    <span class="store-preview-dot"></span>
    Preview only — lab tests &amp; home collection launching soon. Buttons are not active yet.
</div>

<main class="store">

    <!-- Hero banner --------------------------------------------------------- -->
    <section class="lab-banner">
        <div class="wrap">
            <div class="lab-banner-shell">
                <div class="lab-banner-main">
                    <div class="lab-banner-copy">
                        <span class="lab-banner-badge">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <circle cx="7" cy="7" r="7" fill="currentColor" opacity=".18" />
                                <path d="M4 7.2l2 2 4-4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Accurate Results, Better Health
                        </span>
                        <h1>Lab Tests &amp; <span>Health Packages</span></h1>
                        <p class="lab-banner-sub">Get precise reports, expert insights, and personalized packages for you and your family's well-being.</p>

                        <div class="lab-banner-search-wrap">
                            <form class="lab-banner-search" id="labSearchForm" role="search" autocomplete="off">
                                <svg class="lab-banner-search-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                                    <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                                <input type="search" id="labSearchInput" name="q" placeholder="Search for tests, health packages, or symptoms (e.g. Thyroid, Diabetes, Vitamin D)" aria-label="Search lab tests" aria-autocomplete="list" aria-controls="labSearchSuggest" aria-expanded="false">
                                <button type="submit" class="lab-banner-search-btn">Search</button>
                            </form>
                            <div class="lab-search-suggest" id="labSearchSuggest" hidden role="listbox" aria-label="Search suggestions"></div>
                            <div class="lab-search-popular" id="labSearchPopular">
                                <span>Popular Searches:</span>
                                <?php
                                // India-tuned popular searches. Each = [patient-friendly label, term that
                                // actually matches the catalog]. Labels use the acronyms patients know
                                // (LFT, CBC); the search term is what the DB names/parameters contain, so
                                // every chip returns real results. Verified against the seeded catalog.
                                $popularSearches = [
                                    ['Full Body checkup', 'Full Body'],
                                    ['Thyroid test',       'Thyroid'],
                                    ['Diabetes',           'Diabetes'],
                                    ['Vitamin D',          'Vitamin D'],
                                    ['HbA1c',              'HbA1c'],
                                    ['Sugar test',         'Sugar'],
                                    ['LFT (Liver)',        'Liver'],
                                    ['Uric acid',          'Uric Acid'],
                                    ['Lipid profile',      'Lipid'],
                                    ['CBC / Blood count',  'Hemoglobin'],
                                    ['Iron / Anaemia',     'Iron'],
                                    ['Fever panel',        'Fever'],
                                ];
                                foreach ($popularSearches as [$label, $term]): ?>
                                <button type="button" class="lab-search-chip" data-lab-q="<?= e($term) ?>"><?= e($label) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="lab-banner-cta">
                            <a href="#lab-packages" class="lab-banner-btn lab-banner-btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M3 17l6-6 4 4 7-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M14 7h6v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Popular Tests
                            </a>
                            <a href="#lab-packages" class="lab-banner-btn lab-banner-btn-ghost">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M20 12v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    <path d="M12 2v6M8.5 5.5L12 2l3.5 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M2 12h20" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                                View Packages
                            </a>
                        </div>
                    </div>

                    <div class="lab-banner-visual" aria-hidden="true">
                        <div class="lab-banner-blob"></div>
                        <div class="lab-banner-pulse"></div>
                        <!-- <div class="lab-banner-shield">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3z" fill="currentColor"/><path d="M12 8v8M8 12h8" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
                            <svg width="56" height="56" viewBox="0 0 24 24" fill="none">
                                <path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3z" fill="currentColor" />
                                <path d="M12 8v8M8 12h8" stroke="#fff" stroke-width="2" stroke-linecap="round" />
                            </svg>

                        </div> -->
                        <div class="lab-banner-photo lab-banner-photo-main">
                            <img src="<?= e(lab_photo($labPhotos['hero-lab'], 640, 640)) ?>" alt="Laboratory technician examining samples under a microscope" width="640" height="640" loading="eager">
                        </div>
                        <div class="lab-banner-photo lab-banner-photo-family">
                            <img src="<?= e(lab_photo($labPhotos['hero-family'], 400, 400)) ?>" alt="Happy family smiling together" width="400" height="400" loading="eager">
                        </div>
                    </div>
                </div>

                <div class="lab-banner-features">
                    <div class="lab-banner-feat">
                        <span class="lab-banner-feat-ico" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M9 3h6M10 3v6.5L5.5 18a3 3 0 0 0 2.6 4.5h8a3 3 0 0 0 2.6-4.5L14 9.5V3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M8.5 14h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </span>
                        <div>
                            <strong>NABL Certified Labs</strong>
                            <span>Trusted &amp; Accurate Reports</span>
                        </div>
                    </div>
                    <div class="lab-banner-feat">
                        <span class="lab-banner-feat-ico" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <strong>Home Sample Collection</strong>
                            <span>Safe &amp; Convenient</span>
                        </div>
                    </div>
                    <div class="lab-banner-feat">
                        <span class="lab-banner-feat-ico" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8" />
                                <path d="M12 8v4.5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <strong>Reports in 24 Hours*</strong>
                            <span>Quick &amp; Hassle-Free</span>
                        </div>
                    </div>
                    <div class="lab-banner-feat">
                        <span class="lab-banner-feat-ico" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                <rect x="9.5" y="10" width="5" height="4.5" rx="1" stroke="currentColor" stroke-width="1.6" />
                                <path d="M11 10V9a1 1 0 0 1 2 0v1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                        </span>
                        <div>
                            <strong>Secure &amp; Private</strong>
                            <span>Your Data Is Safe</span>
                        </div>
                    </div>
                </div>

                <p class="lab-banner-assurances">
                    <span><svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M3.5 7.2l2.2 2.2 4.8-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg> Advanced technology</span>
                    <span class="lab-banner-dot" aria-hidden="true">•</span>
                    <span><svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M3.5 7.2l2.2 2.2 4.8-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg> Expert pathologists</span>
                    <span class="lab-banner-dot" aria-hidden="true">•</span>
                    <span><svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M3.5 7.2l2.2 2.2 4.8-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg> 100% Quality Assured</span>
                </p>
            </div>
        </div>
    </section>

    <!-- Section — Popular Health Packages ----------------------------------- -->
    <section id="lab-packages" class="lab-pkgs">
        <div class="wrap">
            <div class="lab-pkgs-head">
                <div class="lab-pkgs-intro">
                    <h2>Popular Health Packages</h2>
                    <p>Carefully curated health checkups for you and your family.</p>
                </div>
                <div class="lab-pkgs-trust" aria-label="Trust highlights">
                    <div class="lab-pkgs-trust-item">
                        <span class="lab-pkgs-trust-ico" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M12 3l2.2 4.4 4.8.7-3.5 3.4.8 4.8L12 14.8 7.7 16.3l.8-4.8L5 8.1l4.8-.7L12 3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <strong>NABL Certified Labs</strong>
                            <span>Trusted &amp; Accurate</span>
                        </div>
                    </div>
                    <div class="lab-pkgs-trust-item">
                        <span class="lab-pkgs-trust-ico" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <strong>Home Sample Collection</strong>
                            <span>Safe &amp; Convenient</span>
                        </div>
                    </div>
                    <div class="lab-pkgs-trust-item">
                        <span class="lab-pkgs-trust-ico" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6" />
                                <path d="M12 8v4.5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <strong>Reports in 24 Hours</strong>
                            <span>Quick &amp; Hassle-Free</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lab-pkgs-filters" role="tablist" aria-label="Filter packages">
                <?php $firstFilter = true;
                foreach ($packageFilters as $key => $label): ?>
                    <button type="button"
                        class="lab-pkgs-filter<?= $firstFilter ? ' is-active' : '' ?>"
                        role="tab"
                        aria-selected="<?= $firstFilter ? 'true' : 'false' ?>"
                        data-filter="<?= e($key) ?>">
                        <?= e($label) ?>
                    </button>
                <?php $firstFilter = false;
                endforeach; ?>
            </div>

            <div class="lab-pkgs-grid" id="labPkgsGrid">
                <?php foreach ($packages as [$slug, $name, $highlights, $tests, $params, $price, $mrp, $off, $cats, $badge]): ?>
                    <article class="lab-pkg-card"
                        data-cats="<?= e(implode(' ', $cats)) ?>"
                        data-name="<?= e($name) ?>"
                        data-search="<?= e(strtolower($name . ' ' . implode(' ', $tests) . ' ' . implode(' ', $highlights))) ?>">
                        <div class="lab-pkg-card-media">
                            <a href="<?= e(ecp_lab_detail_url('package', $slug)) ?>" tabindex="-1" aria-hidden="true">
                                <?php
                                // DB-sourced packages won't have a curated photo key; rotate
                                // through the gallery pool by slug so every card has an image.
                                $pkgPhotoId = $labPhotos['pkg-' . $slug]
                                    ?? $labPhotos['gal-' . (1 + (abs(crc32($slug)) % 8))]
                                    ?? $labPhotos['hero-lab'];
                                ?>
                                <img src="<?= e(lab_photo($pkgPhotoId, 640, 400)) ?>" alt="<?= e($name) ?>" width="640" height="400" loading="lazy">
                            </a>
                        </div>
                        <div class="lab-pkg-card-body">
                            <h3 class="lab-pkg-card-title"><a href="<?= e(ecp_lab_detail_url('package', $slug)) ?>"><?= e($name) ?></a></h3>
                            <p class="lab-pkg-card-count"><?= e($params) ?> Tests Included</p>
                            <ul class="lab-pkg-card-points">
                                <?php foreach ($highlights as $point): ?>
                                    <li><?= e($point) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="lab-pkg-card-price">
                                <span class="lab-pkg-card-now">₹<?= e($price) ?></span>
                                <?php // Only show the "% OFF" chip + struck MRP when there IS a discount. ?>
                                <?php if ((int) $off > 0 && (float) $mrp > (float) $price): ?>
                                    <span class="lab-pkg-card-off"><?= e($off) ?>% OFF</span>
                                    <s class="lab-pkg-card-mrp">₹<?= e($mrp) ?></s>
                                <?php endif; ?>
                            </div>
                            <div class="lab-pkg-card-actions">
                                <a href="<?= e(ecp_lab_detail_url('package', $slug)) ?>" class="lab-pkg-card-link">View details →</a>
                                <button type="button" class="lab-pkg-card-book lab-book" data-book="<?= e($name) ?>">Book Now</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="lab-pkgs-empty" id="labPkgsEmpty" hidden>No packages in this category yet — try another filter.</p>
        </div>
    </section>

    <!-- Section — Explore by Body Organ ------------------------------------- -->
    <section id="lab-organs" class="lab-organs">
        <div class="wrap">
            <div class="lab-organs-hero">
                <div class="lab-organs-copy">
                    <span class="lab-organs-eyebrow">
                        Explore by Health Need
                        <svg width="28" height="12" viewBox="0 0 28 12" fill="none" aria-hidden="true">
                            <path d="M0 6h6l2-4 3 8 3-10 2 6h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <h2>Explore by Body Organ &amp; System</h2>
                    <p>Choose a health category to find the right test for your specific needs.</p>

                    <div class="lab-organs-trust" aria-label="Trust highlights">
                        <div class="lab-organs-trust-item">
                            <span class="lab-organs-trust-ico" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M9 3h6M10 3v6.5L5.5 18a3 3 0 0 0 2.6 4.5h8a3 3 0 0 0 2.6-4.5L14 9.5V3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M8.5 14h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                </svg>
                            </span>
                            <div>
                                <strong>NABL Certified Labs</strong>
                                <span>Accurate &amp; Reliable</span>
                            </div>
                        </div>
                        <div class="lab-organs-trust-item">
                            <span class="lab-organs-trust-ico" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <div>
                                <strong>Home Sample Collection</strong>
                                <span>Safe &amp; Convenient</span>
                            </div>
                        </div>
                        <div class="lab-organs-trust-item">
                            <span class="lab-organs-trust-ico" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7" />
                                    <path d="M12 8v4.5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <div>
                                <strong>Reports in 24 Hours*</strong>
                                <span>Quick &amp; Hassle-Free</span>
                            </div>
                        </div>
                        <div class="lab-organs-trust-item">
                            <span class="lab-organs-trust-ico" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                                    <path d="M9.5 12l1.8 1.8 3.5-3.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <div>
                                <strong>Secure &amp; Private</strong>
                                <span>Your Data is Safe</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lab-organs-visual" aria-hidden="true">
                    <div class="lab-organs-shield">
                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none">
                            <path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3z" fill="currentColor" />
                            <path d="M12 8v8M8 12h8" stroke="#fff" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                    <div class="lab-organs-photo">
                        <img src="<?= e(lab_photo($labPhotos['organs-family'], 720, 560)) ?>" alt="Family exploring health together" width="720" height="560" loading="lazy">
                    </div>
                </div>
            </div>

            <div class="lab-organs-grid">
                <?php
                // Organ tiles now link to the DB-backed category LISTING page
                // (/lab/category/{slug}) instead of a thin detail page. The map
                // resolves each organ to its lab_categories slug.
                $organCatMap = ecp_lab_organ_category_map();
                foreach ($organs as [$slug, $label, $desc]):
                    $organUrl = isset($organCatMap[$slug])
                        ? ecp_lab_category_url($organCatMap[$slug])
                        : ecp_lab_detail_url('organ', $slug);
                ?>
                    <a href="<?= e($organUrl) ?>" class="lab-organ-card">
                        <div class="lab-organ-card-media">
                            <img src="<?= e(lab_photo($labPhotos['organ-' . $slug], 480, 320)) ?>" alt="<?= e($label) ?> health tests" width="480" height="320" loading="lazy">
                        </div>
                        <div class="lab-organ-card-body">
                            <h3><span class="lab-organ-bar" aria-hidden="true"></span><?= e($label) ?></h3>
                            <p><?= e($desc) ?></p>
                            <span class="lab-organ-link">Explore Tests <span aria-hidden="true">→</span></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="lab-organs-cta">
                <span class="lab-organs-cta-ico" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                        <path d="M8 4h8a2 2 0 0 1 2 2v14l-6-3-6 3V6a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        <path d="M9.5 10.5l2 2 3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <div class="lab-organs-cta-text">
                    <strong>Not sure which test to choose?</strong>
                    <span>Take our quick health assessment and we'll suggest the right tests for you.</span>
                </div>
                <button type="button" class="lab-organs-cta-btn" data-soon>Take Health Assessment →</button>
            </div>
        </div>
    </section>

    <!-- Section — Book by Symptom ------------------------------------------- -->
    <section id="lab-symptoms" class="lab-symptoms">
        <div class="wrap">
            <header class="lab-symptoms-head">
                <span class="lab-symptoms-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                        <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    Find the Right Test, Right Away
                </span>
                <h2>Book by <span>Symptom</span></h2>
                <p>Select a symptom to discover the most relevant tests and health packages for your concern.</p>
            </header>

            <div class="lab-symptoms-grid">
                <?php foreach ($concerns as [$slug, $title, $desc]): ?>
                    <a href="<?= e(ecp_lab_detail_url('symptom', $slug)) ?>" class="lab-symptom-card">
                        <span class="lab-symptom-ico" aria-hidden="true">
                            <img src="<?= e(lab_photo($labPhotos['symptom-' . $slug], 160, 160)) ?>" alt="" width="160" height="160" loading="lazy">
                        </span>
                        <span class="lab-symptom-text">
                            <strong><?= e($title) ?></strong>
                            <span><?= e($desc) ?></span>
                        </span>
                        <span class="lab-symptom-arrow" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="lab-symptoms-features">
                <div class="lab-symptoms-feat">
                    <span class="lab-symptoms-feat-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M9 3h6M10 3v6.5L5.5 18a3 3 0 0 0 2.6 4.5h8a3 3 0 0 0 2.6-4.5L14 9.5V3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M8.5 14h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </span>
                    <div>
                        <strong>NABL Certified Labs</strong>
                        <span>Trusted &amp; Accurate Results</span>
                    </div>
                </div>
                <div class="lab-symptoms-feat">
                    <span class="lab-symptoms-feat-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div>
                        <strong>Home Sample Collection</strong>
                        <span>Safe &amp; Convenient</span>
                    </div>
                </div>
                <div class="lab-symptoms-feat">
                    <span class="lab-symptoms-feat-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7" />
                            <path d="M12 8v4.5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div>
                        <strong>Reports in 24 Hours*</strong>
                        <span>Quick &amp; Hassle-Free</span>
                    </div>
                </div>
                <div class="lab-symptoms-feat">
                    <span class="lab-symptoms-feat-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                            <rect x="9.5" y="10" width="5" height="4.5" rx="1" stroke="currentColor" stroke-width="1.5" />
                            <path d="M11 10V9a1 1 0 0 1 2 0v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </span>
                    <div>
                        <strong>Secure &amp; Private</strong>
                        <span>Your Data is Safe</span>
                    </div>
                </div>
            </div>

            <p class="lab-symptoms-assurances">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3z" fill="currentColor" />
                    <path d="M8.5 12l2.2 2.2 4.5-4.8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>Powered by advanced technology</span>
                <span class="lab-symptoms-pipe" aria-hidden="true">|</span>
                <span>Expert pathologists</span>
                <span class="lab-symptoms-pipe" aria-hidden="true">|</span>
                <span>100% Quality Assured</span>
            </p>
        </div>
    </section>

    <!-- Section — Life-stage Packages --------------------------------------- -->
    <section id="lab-lifestage" class="lab-lifestage">
        <div class="wrap">
            <header class="lab-lifestage-head">
                <span class="lab-lifestage-badge">
                    <svg width="16" height="12" viewBox="0 0 16 12" fill="none" aria-hidden="true">
                        <path d="M0 6h3l1.5-3 2 6 2-8 1.5 5H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Complete Care for Every Stage
                </span>
                <h2>Packages for <span>Every Stage of Life</span></h2>
                <p>Specially curated health packages for you and your loved ones.</p>
            </header>

            <div class="lab-lifestage-grid">
                <?php foreach ($lifeStage as [$slug, $ico, $name, $blurb, $accent]): ?>
                    <?php
                    // Null-safe photo: fall back to the gallery pool if this life
                    // slug has no curated key (so a new tile never breaks the grid).
                    $lifePhotoId = $labPhotos['life-' . $slug]
                        ?? $labPhotos['gal-' . (1 + (abs(crc32($slug)) % 8))]
                        ?? $labPhotos['hero-lab'];
                    ?>
                    <a href="<?= e(ecp_lab_life_url($slug)) ?>" class="lab-life-card lab-life-<?= e($accent) ?>">
                        <div class="lab-life-media">
                            <img src="<?= e(lab_photo($lifePhotoId, 640, 440)) ?>" alt="<?= e($name) ?>" width="640" height="440" loading="lazy">
                            <span class="lab-life-ico" aria-hidden="true"><?= $ico ?></span>
                        </div>
                        <div class="lab-life-body">
                            <span class="lab-life-accent" aria-hidden="true"></span>
                            <h3><?= e($name) ?></h3>
                            <p><?= e($blurb) ?></p>
                            <span class="lab-life-link">View package →</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Section — How it works ---------------------------------------------- -->
    <section id="lab-how" class="lab-how">
        <div class="wrap">
            <header class="lab-how-head">
                <span class="lab-how-badge">Simple, Fast &amp; Reliable</span>
                <h2>How It Works</h2>
                <p>From booking to reports in six simple steps.</p>
            </header>

            <ol class="lab-how-flow">
                <?php foreach ($howSteps as $i => [$ico, $title, $blurb]): ?>
                    <li class="lab-how-flowstep">
                        <span class="lab-how-flownum"><?= sprintf('%02d', $i + 1) ?></span>
                        <span class="lab-how-flowico" aria-hidden="true"><i class="fi <?= e($ico) ?>"></i></span>
                        <strong><?= e($title) ?></strong>
                        <span class="lab-how-flowtext"><?= e($blurb) ?></span>
                        <?php if ($i < count($howSteps) - 1): ?>
                        <span class="lab-how-flowarrow" aria-hidden="true"><i class="fi fi-rr-arrow-small-right"></i></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- Section — Why book with us ------------------------------------------ -->
    <section id="lab-why" class="lab-why">
        <div class="wrap">
            <div class="lab-why-shell">
                <header class="lab-why-head">
                    <h2>Why Book Lab Tests on <span>eClinicPro?</span></h2>
                </header>

                <div class="lab-why-grid">
                    <?php foreach ($why as [$slug, $title, $blurb]): ?>
                        <a href="<?= e(ecp_lab_detail_url('why', $slug)) ?>" class="lab-why-card">
                            <div class="lab-why-ico" aria-hidden="true">
                                <img src="<?= e(lab_photo($labPhotos['why-' . $slug], 160, 160)) ?>" alt="" width="160" height="160" loading="lazy">
                            </div>
                            <div>
                                <strong><?= e($title) ?></strong>
                                <span><?= e($blurb) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="lab-why-trust">
                    <span class="lab-why-trust-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3z" fill="currentColor" />
                            <path d="M8.5 12l2.2 2.2 4.5-4.8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Secure
                    </span>
                    <span class="lab-why-trust-pipe" aria-hidden="true"></span>
                    <span class="lab-why-trust-item">Reliable</span>
                    <span class="lab-why-trust-pipe" aria-hidden="true"></span>
                    <span class="lab-why-trust-item">Confidential</span>
                    <span class="lab-why-trust-pipe lab-why-trust-pipe-wide" aria-hidden="true"></span>
                    <span class="lab-why-trust-priority">Your health. Our priority.</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Section — Partner labs (generic, no named brands until signed) ------- -->
    <section id="lab-partners" class="lab-partners">
        <div class="wrap">
            <header class="lab-partners-head">
                <span class="lab-partners-badge">
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                        <circle cx="7" cy="7" r="7" fill="currentColor" opacity=".2" />
                        <path d="M4 7.2l2 2 4-4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Trusted &amp; Accredited
                </span>
                <h2>Processed at <span>Accredited Labs</span></h2>
                <p>Every sample is tested at certified, NABL-accredited diagnostic partners.</p>
            </header>

            <div class="lab-partners-grid">
                <?php foreach ($partners as [$slug, $title, $blurb]): ?>
                    <a href="<?= e(ecp_lab_detail_url('partner', $slug)) ?>" class="lab-partner-card">
                        <div class="lab-partner-ico" aria-hidden="true">
                            <img src="<?= e(lab_photo($labPhotos['partner-' . $slug], 160, 160)) ?>" alt="" width="160" height="160" loading="lazy">
                        </div>
                        <div>
                            <strong><?= e($title) ?></strong>
                            <span><?= e($blurb) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Accreditation logos (same set as the package detail page). -->
            <div class="lab-accred-strip" aria-label="Lab partners and certifications">
                <span class="lab-accred-label">In association with</span>
                <img src="/assets/img/logos/thyrocare-logo.webp" alt="Thyrocare" class="lab-accred-logo" loading="lazy">
                <img src="/assets/img/logos/nabl-logo.webp" alt="100% NABL Accreditation" class="lab-accred-logo lab-accred-badge" loading="lazy">
                <img src="/assets/img/logos/cap-accredited-logo.webp" alt="CAP Accredited — College of American Pathologists" class="lab-accred-logo" loading="lazy">
                <img src="/assets/img/logos/isologo.webp" alt="ISO 9001 certified" class="lab-accred-logo lab-accred-badge" loading="lazy">
            </div>
        </div>
    </section>

    <!-- Search results (shown after Search / popular chip) ---------------- -->
    <section id="lab-search-results" class="lab-search-results" hidden>
        <div class="wrap">
            <p class="lab-search-summary" id="labSearchSummary"></p>
            <div class="lab-search-results-list" id="labSearchResultsList"></div>
            <p class="lab-search-none" id="labSearchNone" hidden>No matching tests or packages found. Try another keyword.</p>
        </div>
    </section>

    <!-- Footer CTA banner --------------------------------------------------- -->
    <section class="lab-final-cta">
        <div class="wrap">
            <div class="lab-final-cta-shell">
                <div class="lab-final-cta-copy">
                    <span class="lab-final-cta-badge">
                        <svg width="14" height="12" viewBox="0 0 14 12" fill="none" aria-hidden="true">
                            <path d="M0 6h2.5l1.2-2.5 1.6 5 1.4-6.5L8.2 6H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Accurate Results. Better Health.
                    </span>
                    <h2>Health Checkups, Made <span>Effortless</span></h2>
                    <p>Book diagnostic tests with free home sample collection, digital reports and a free doctor consultation – all in one place.</p>
                    <div class="lab-final-cta-actions">
                        <button type="button" class="lab-final-cta-btn lab-final-cta-btn-solid lab-book" data-book="Lab Test Booking">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8" />
                                <path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            Book a Test
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button type="button" class="lab-final-cta-btn lab-final-cta-btn-ghost" data-soon>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M5 17h12a3 3 0 0 0 0-6h-1.2A4.5 4.5 0 0 0 7 8.5c0 .2 0 .4.05.6A3.5 3.5 0 0 0 5 17z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                <circle cx="8" cy="17" r="1.6" fill="currentColor" />
                                <circle cx="15" cy="17" r="1.6" fill="currentColor" />
                            </svg>
                            Book a Home Collection
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <a href="/eclinicpro-health-store" class="lab-final-cta-btn lab-final-cta-btn-ghost">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.8" />
                                <path d="M5 20c1.5-3.2 4-5 7-5s5.5 1.8 7 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                <circle cx="17.5" cy="6.5" r="3" fill="currentColor" />
                                <path d="M17.5 5v3M16 6.5h3" stroke="#0B7F5A" stroke-width="1.4" stroke-linecap="round" />
                            </svg>
                            Health Store
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="lab-final-cta-visual" aria-hidden="true">
                    <div class="lab-final-cta-rings"></div>
                    <div class="lab-final-cta-dots"></div>
                    <!-- Decorative shield — SVG only (no photo needed for this graphic) -->
                    <div class="lab-final-cta-shield">
                        <svg width="140" height="160" viewBox="0 0 140 160" fill="none" aria-hidden="true">
                            <path d="M70 8l52 20v36c0 42-28 70-52 80C46 134 18 106 18 64V28L70 8z" fill="url(#labCtaShieldGrad)" fill-opacity=".9" />
                            <path d="M70 20l40 15.5v28c0 32-21.5 54-40 62-18.5-8-40-30-40-62v-28L70 20z" fill="url(#labCtaShieldInner)" fill-opacity=".55" />
                            <path d="M48 78l16 16 30-34" stroke="#fff" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" />
                            <defs>
                                <linearGradient id="labCtaShieldGrad" x1="18" y1="8" x2="122" y2="148" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#5EE0B0" />
                                    <stop offset="1" stop-color="#0F9B6E" />
                                </linearGradient>
                                <linearGradient id="labCtaShieldInner" x1="30" y1="20" x2="110" y2="120" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#fff" stop-opacity=".35" />
                                    <stop offset="1" stop-color="#fff" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php
// Search index for autocomplete + results (packages, organs, symptoms, life).
// Prefer the full DB index (all packages/offers + top individual tests); fall
// back to just the grid packages when the DB/lab tables are unavailable.
$labSearchIndex = ecp_lab_db_search_index(150);
if ($labSearchIndex === []) {
    foreach ($packages as [$slug, $name, $highlights, $tests, $params, $price, $mrp, $off, $cats, $badge]) {
        $labSearchIndex[] = [
            'type' => 'package',
            'slug' => $slug,
            'title' => $name,
            'meta' => 'Package | Includes ' . $params . ' Tests',
            'params' => $params,
            'price' => $price,
            'mrp' => $mrp,
            'off' => $off,
            'url' => ecp_lab_detail_url('package', $slug),
            'q' => strtolower($name . ' ' . implode(' ', $tests) . ' ' . implode(' ', $highlights) . ' ' . implode(' ', $cats)),
        ];
    }
}
$organCatMapIdx = ecp_lab_organ_category_map();
foreach ($organs as [$slug, $label, $desc]) {
    $labSearchIndex[] = [
        'type' => 'organ',
        'slug' => $slug,
        'title' => $label,
        'meta' => 'Body System | ' . $desc,
        'url' => isset($organCatMapIdx[$slug])
            ? ecp_lab_category_url($organCatMapIdx[$slug])
            : ecp_lab_detail_url('organ', $slug),
        'q' => strtolower($label . ' ' . $desc),
    ];
}
foreach ($concerns as [$slug, $title, $desc]) {
    $labSearchIndex[] = [
        'type' => 'symptom',
        'slug' => $slug,
        'title' => $title,
        'meta' => 'Symptom | ' . $desc,
        'url' => ecp_lab_detail_url('symptom', $slug),
        'q' => strtolower($title . ' ' . $desc),
    ];
}
foreach ($lifeStage as [$slug, $ico, $name, $blurb, $accent]) {
    $labSearchIndex[] = [
        'type' => 'life',
        'slug' => $slug,
        'title' => $name,
        'meta' => 'Life Stage | ' . $blurb,
        'url' => ecp_lab_life_url($slug),
        'q' => strtolower($name . ' ' . $blurb),
    ];
}
?>
<script type="application/json" id="labSearchData"><?= json_encode($labSearchIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<!-- "Coming soon" toast (shared pattern) -------------------------------- -->
<div id="storeToast" class="store-toast" role="status" aria-live="polite">Coming soon — lab tests are launching shortly. 🧪</div>
<script>
    (function() {
        var toast = document.getElementById('storeToast');
        var timer = null;

        function showToast(msg) {
            if (!toast) return;
            if (msg) toast.textContent = msg;
            toast.classList.add('is-on');
            clearTimeout(timer);
            timer = setTimeout(function() {
                toast.classList.remove('is-on');
            }, 2600);
        }

        document.addEventListener('click', function(ev) {
            var book = ev.target.closest('[data-book]');
            if (book) {
                ev.preventDefault();
                var pkg = book.getAttribute('data-book') || 'Lab Test';
                if (window.ecpAuth && typeof window.ecpAuth.require === 'function') {
                    window.ecpAuth.require('lab_booking', function() {
                        showToast('You’re signed in — “' + pkg + '” booking opens soon. 🧪');
                    });
                } else {
                    showToast();
                }
                return;
            }

            var soon = ev.target.closest('[data-soon]');
            if (soon) {
                ev.preventDefault();
                showToast('Coming soon — lab tests are launching shortly. 🧪');
            }
        });

        // Package category filters
        var filterBar = document.querySelector('.lab-pkgs-filters');
        var cards = document.querySelectorAll('.lab-pkg-card');
        var empty = document.getElementById('labPkgsEmpty');
        if (filterBar && cards.length) {
            filterBar.addEventListener('click', function(ev) {
                var btn = ev.target.closest('[data-filter]');
                if (!btn) return;
                var filter = btn.getAttribute('data-filter') || 'all';
                filterBar.querySelectorAll('.lab-pkgs-filter').forEach(function(el) {
                    var on = el === btn;
                    el.classList.toggle('is-active', on);
                    el.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                var shown = 0;
                cards.forEach(function(card) {
                    var cats = (card.getAttribute('data-cats') || '').split(/\s+/);
                    var match = filter === 'all' || cats.indexOf(filter) !== -1;
                    card.classList.toggle('is-hidden', !match);
                    if (match) shown++;
                });
                if (empty) empty.hidden = shown > 0;
            });
        }

        // ---- Lab search (autocomplete + results) ----
        var searchDataEl = document.getElementById('labSearchData');
        var form = document.getElementById('labSearchForm');
        var input = document.getElementById('labSearchInput');
        var suggest = document.getElementById('labSearchSuggest');
        var resultsSec = document.getElementById('lab-search-results');
        var resultsList = document.getElementById('labSearchResultsList');
        var summary = document.getElementById('labSearchSummary');
        var noneEl = document.getElementById('labSearchNone');
        var index = [];
        try {
            index = searchDataEl ? JSON.parse(searchDataEl.textContent || '[]') : [];
        } catch (e) {
            index = [];
        }

        function normalize(s) {
            return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
        }

        function matchItems(q) {
            q = normalize(q);
            if (!q) return [];
            var tokens = q.split(' ').filter(Boolean);
            return index.filter(function(item) {
                var hay = item.q || normalize(item.title);
                return tokens.every(function(t) { return hay.indexOf(t) !== -1; });
            });
        }

        function hideSuggest() {
            if (!suggest) return;
            suggest.hidden = true;
            suggest.innerHTML = '';
            if (input) input.setAttribute('aria-expanded', 'false');
        }

        function renderSuggest(items, q) {
            if (!suggest) return;
            if (!q || !items.length) {
                hideSuggest();
                return;
            }
            var html = '<div class="lab-search-suggest-head">Popular Tests And Health Packages</div>';
            items.slice(0, 8).forEach(function(item) {
                html += '<a class="lab-search-suggest-item" role="option" href="' + item.url + '">' +
                    '<span class="lab-search-suggest-ico" aria-hidden="true">❤</span>' +
                    '<span class="lab-search-suggest-text">' +
                    '<strong>' + escapeHtml(item.title) + '</strong>' +
                    '<span>' + escapeHtml(item.meta) + '</span>' +
                    '</span>' +
                    '<span class="lab-search-suggest-chev" aria-hidden="true">›</span>' +
                    '</a>';
            });
            suggest.innerHTML = html;
            suggest.hidden = false;
            if (input) input.setAttribute('aria-expanded', 'true');
        }

        function escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function filterPackageCards(q) {
            q = normalize(q);
            var shown = 0;
            cards.forEach(function(card) {
                var hay = card.getAttribute('data-search') || normalize(card.getAttribute('data-name'));
                var match = !q || hay.indexOf(q) !== -1 || q.split(' ').every(function(t) {
                    return !t || hay.indexOf(t) !== -1;
                });
                card.classList.toggle('is-hidden', !match);
                if (match) shown++;
            });
            if (empty) empty.hidden = shown > 0;
            if (filterBar && q) {
                filterBar.querySelectorAll('.lab-pkgs-filter').forEach(function(el) {
                    el.classList.remove('is-active');
                    el.setAttribute('aria-selected', 'false');
                });
            }
            return shown;
        }

        function runSearch(q) {
            q = normalize(q);
            if (input) input.value = q;
            hideSuggest();
            var items = matchItems(q);
            // NOTE: search results render in the dedicated #lab-search-results
            // section below — we do NOT filter the "Popular Health Packages"
            // grid here (that grid is controlled only by its own tab buttons).
            // Filtering it on search emptied the grid for terms not in a card's
            // name (e.g. "Vitamin D", "Iron"), which looked like a blank grid.

            if (!resultsSec || !resultsList) return;
            if (!q) {
                resultsSec.hidden = true;
                resultsList.innerHTML = '';
                return;
            }

            resultsSec.hidden = false;
            if (summary) {
                summary.innerHTML = 'Showing <strong>' + items.length + '</strong> result' +
                    (items.length === 1 ? '' : 's') + ' for “<strong>' + escapeHtml(q) + '</strong>”';
            }
            if (!items.length) {
                resultsList.innerHTML = '';
                if (noneEl) noneEl.hidden = false;
            } else {
                if (noneEl) noneEl.hidden = true;
                resultsList.innerHTML = items.map(function(item) {
                    var priceHtml = '';
                    if (item.type === 'package') {
                        priceHtml =
                            '<div class="lab-search-result-price">' +
                            '<strong>₹' + escapeHtml(item.price) + '</strong>' +
                            '<s>₹' + escapeHtml(item.mrp) + '</s>' +
                            '<span>' + escapeHtml(item.off) + '% OFF</span>' +
                            '</div>';
                    }
                    return '<article class="lab-search-result-card">' +
                        '<a class="lab-search-result-main" href="' + item.url + '">' +
                        '<span class="lab-search-suggest-ico" aria-hidden="true">❤</span>' +
                        '<span class="lab-search-suggest-text">' +
                        '<strong>' + escapeHtml(item.title) + '</strong>' +
                        '<span>' + escapeHtml(item.meta) + '</span>' +
                        '</span>' +
                        '</a>' +
                        '<div class="lab-search-result-foot">' +
                        priceHtml +
                        '<div class="lab-search-result-actions">' +
                        '<a class="lab-search-result-link" href="' + item.url + '">View details</a>' +
                        (item.type === 'package'
                            ? '<button type="button" class="lab-search-result-book lab-book" data-book="' + escapeHtml(item.title) + '">Book</button>'
                            : '') +
                        '</div></div></article>';
                }).join('');
            }

            resultsSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        if (input) {
            input.addEventListener('input', function() {
                var q = input.value;
                renderSuggest(matchItems(q), normalize(q));
            });
            input.addEventListener('keydown', function(ev) {
                if (ev.key === 'Escape') hideSuggest();
            });
            input.addEventListener('focus', function() {
                var q = normalize(input.value);
                if (q) renderSuggest(matchItems(q), q);
            });
        }

        if (form) {
            form.addEventListener('submit', function(ev) {
                ev.preventDefault();
                runSearch(input ? input.value : '');
            });
        }

        document.addEventListener('click', function(ev) {
            var chip = ev.target.closest('[data-lab-q]');
            if (chip) {
                ev.preventDefault();
                runSearch(chip.getAttribute('data-lab-q') || '');
                return;
            }
            if (suggest && !suggest.hidden && !ev.target.closest('.lab-banner-search-wrap')) {
                hideSuggest();
            }
        });
    })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>