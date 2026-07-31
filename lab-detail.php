<?php
/**
 * Lab item details — /lab/{type}/{slug}
 * Product-style layout matched to lab package detail design.
 */
declare(strict_types=1);

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/lab_catalog.php';

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$allowed = ['package', 'test', 'organ', 'symptom', 'life', 'step', 'why', 'partner'];

$notFound = static function () use ($type): never {
    http_response_code(404);
    $pageTitle = 'Not found — Lab | eClinicPro';
    $metaDesc = 'Lab item not found.';
    $activePage = 'lab';
    $hideFinalCta = true;
    $noindex = true;
    require __DIR__ . '/partials/header.php';
    echo '<main class="ldp"><div class="wrap ldp-missing"><h1>Page not found</h1><p>That lab item does not exist.</p><a class="ldp-btn ldp-btn-primary" href="/lab">Back to Lab</a></div></main>';
    require __DIR__ . '/partials/footer.php';
    exit;
};

if (!in_array($type, $allowed, true) || $slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    $notFound();
}

$item = ecp_lab_find_item($type, $slug);
if (!$item) {
    $notFound();
}

$d = ecp_lab_build_detail($item);
// Individual tests use the same priced/bookable layout as packages.
$isPkg = ($type === 'package' || $type === 'test');
$tests = $d['tests'] ?? [];
$params = $d['params'] ?? (string) max(count($tests), 1);
$price = $d['price'] ?? '';
$mrp = $d['mrp'] ?? '';
$off = $d['off'] ?? '';

// Home collection is free only above Thyrocare's order threshold. Below it they
// bill ₹200, which we pass through at cost — so on those (mostly cheap single
// tests) the page must NOT claim "Free Home Collection". This flags the
// single-unit case; the booking form recomputes live, because adding tests or
// persons can lift the order over the threshold and make collection free again.
$priceNumForFee   = (int) preg_replace('/\D/', '', (string) $price);
$collectionIsFree = $priceNumForFee <= 0 || $priceNumForFee >= ECP_LAB_COLLECTION_MIN_ORDER;

$typeLabels = [
    'package' => 'Health Package',
    'organ' => 'Body System',
    'symptom' => 'Symptom Guide',
    'life' => 'Life Stage',
    'step' => 'How It Works',
    'why' => 'Why eClinicPro',
    'partner' => 'Lab Partner',
];
// Badge comes from the DB: 'Bestseller' only when the product is genuinely
// is_featured. For non-priced types keep the type label; for a plain package
// with no flag, show no badge (don't fake "Best Seller" on everything).
$badge = trim((string) ($d['badge'] ?? ''));
if ($badge === '' && !$isPkg) {
    $badge = $typeLabels[$type] ?? 'Lab';
}

// "What's Included" chips — real Thyrocare group names from the DB (biggest
// first), so the chips reconcile with the accordion. Falls back to test names
// or features when a package has no group metadata.
$dbGroups = $d['test_groups'] ?? [];
$includeChips = [];
if ($isPkg && $dbGroups) {
    foreach (array_slice(array_keys($dbGroups), 0, 7) as $g) {
        $includeChips[] = $g;
    }
    if (count($dbGroups) > 7) {
        $includeChips[] = '& More';
    }
} elseif ($tests) {
    foreach (array_slice($tests, 0, 7) as $t) {
        $includeChips[] = $t;
    }
    if (count($tests) > 7 || (int) $params > 7) {
        $includeChips[] = '& More';
    }
} else {
    foreach (array_slice($d['features'] ?? [], 0, 8) as $f) {
        $includeChips[] = $f;
    }
}

// ── Real DB-driven tags (rendered as pills under the H1) ───────────────────
// fasting: 'CF' = compulsory fasting, 'NF' = no fasting required.
$fastingCode = (string) ($d['fasting'] ?? '');
$diseaseTags = $d['disease_tags'] ?? [];
$loginPct    = (int) ($d['login_pct'] ?? 0);

// The coupon's HEADLINE rate, before any per-product ceiling. $loginPct above is
// this same coupon already capped by THIS package's max_discount_pct. Both are
// needed: the headline rate is what each add-on line caps for itself, so an
// add-on with a higher ceiling than the package isn't held back by it.
$couponHeadlinePct = $loginPct;
if ($loginPct > 0 && function_exists('ecp_db') && ($labDb = ecp_db())) {
    $couponHeadlinePct = max($loginPct, ecp_lab_best_login_discount($labDb, 100));
}

// Note: the test-count is already shown as a green pill in .ldp-hero-meta, so
// we intentionally do NOT repeat it here as a grey tag.
$detailTags = [];
if ($fastingCode === 'CF') {
    $detailTags[] = ['fi-rr-clock', 'Fasting Required (8–10 hrs)'];
} elseif ($fastingCode === 'NF') {
    $detailTags[] = ['fi-rr-check', 'No Fasting Required'];
}
$detailTags[] = ['fi-rr-file-medical', 'Digital Reports'];
$detailTags[] = $collectionIsFree
    ? ['fi-rr-house-blank', 'Free Home Collection']
    : ['fi-rr-house-blank', 'Home Collection ₹' . ECP_LAB_COLLECTION_FEE . ' (free over ₹' . ECP_LAB_COLLECTION_MIN_ORDER . ')'];
if ($loginPct > 0) {
    // "From 5% to 25% off when you order here" — floor is the smallest active
    // login coupon, ceiling is this product's cap (login_pct).
    $minLoginPct = min(5, $loginPct);
    $detailTags[] = [
        'fi-rr-badge-percent',
        $minLoginPct < $loginPct
            ? "From {$minLoginPct}% to {$loginPct}% off if you order here"
            : "Extra {$loginPct}% off if you order here",
    ];
}
// Condition/disease tags (Thyroid, Infertility, …) from disease_group.
foreach (array_slice($diseaseTags, 0, 4) as $dt) {
    $detailTags[] = ['fi-rr-heart', $dt];
}

// Accordion test groups — driven by the REAL Thyrocare grouping stored in the
// DB (lab_parameters.group_name). No hardcoded test names: each panel is a
// genuine group with its actual parameters and true count.
$testGroups = [];

// Map a real group name -> a Flaticon UI-font class (the `fi fi-rr-*` set is
// already loaded on this page). We use icon-font glyphs instead of the PNG
// icon set because several of those PNGs are wrong/placeholder art. Crisp at
// any size and consistent with the rest of the page.
$groupIcon = static function (string $name): string {
    $n = strtolower($name);
    return match (true) {
        (bool) preg_match('/sugar|diabet|glucose|hba1c|insulin/', $n) => 'fi-rr-blood-test-tube',
        (bool) preg_match('/kidney|renal|urea|creat|electrolyte|urine/', $n) => 'fi-rr-kidneys',
        (bool) preg_match('/liver|hepat/', $n)                        => 'fi-rr-inner-nose',
        (bool) preg_match('/lipid|cardiac|cholesterol|heart|homocyst/', $n) => 'fi-rr-heart',
        (bool) preg_match('/thyroid/', $n)                            => 'fi-rr-neck',
        (bool) preg_match('/iron|vitamin|anaemia|anemia|b12|ferritin|mineral|element/', $n) => 'fi-rr-pills',
        (bool) preg_match('/blood|cbc|hemogram|haemogram|complete|count/', $n) => 'fi-rr-blood',
        (bool) preg_match('/hormone|fertil|pregn|reproduct/', $n)     => 'fi-rr-dna',
        (bool) preg_match('/allergy|immun/', $n)                      => 'fi-rr-shield-plus',
        default                                                       => 'fi-rr-flask',
    };
};

if ($isPkg && !empty($d['test_groups'])) {
    foreach ($d['test_groups'] as $groupName => $groupTests) {
        $testGroups[] = [
            'name'  => $groupName,
            'icon'  => $groupIcon((string) $groupName),
            'count' => count($groupTests),
            'items' => $groupTests,
        ];
    }
} elseif ($isPkg && $tests) {
    // Fallback: no group metadata (e.g. static catalog) — one flat panel of the
    // real test names rather than inventing categories.
    $testGroups[] = [
        'name'  => 'Tests Included',
        'icon'  => 'flask',
        'count' => count($tests),
        'items' => array_values($tests),
    ];
} else {
    foreach (array_slice($d['features'] ?? [], 0, 6) as $i => $f) {
        $icons = ['fi-rr-clipboard-list-check', 'fi-rr-file-medical', 'fi-rr-shield-check', 'fi-rr-user-md', 'fi-rr-flask', 'fi-rr-check'];
        $testGroups[] = [
            'name' => $f,
            'icon' => $icons[$i % count($icons)],
            'count' => 1,
            'items' => [$d['overview'] ?? ''],
        ];
    }
}

// "Why Choose This Package?" — derived from REAL DB facts (no invented clinical
// claims). Each row: [fi-icon, title, subtitle]. We build from the package's
// test count, the actual groups/conditions it screens, fasting, login discount,
// and digital-report delivery — all things we can honestly stand behind.
$whyReasons = [];
if ($isPkg) {
    // What it screens: real condition tags, else the biggest test groups.
    $screensList = $diseaseTags;
    if (!$screensList && !empty($d['test_groups'])) {
        $screensList = array_slice(array_keys($d['test_groups']), 0, 3);
    }
    if ($params) {
        $whyReasons[] = ['fi-rr-flask', $params . ' diagnostic tests in one package',
            'A single booking covers ' . $params . ' parameters — no ordering tests one by one.'];
    }
    if ($screensList) {
        $whyReasons[] = ['fi-rr-heart', 'Screens ' . ecp_join_human(array_slice($screensList, 0, 3)),
            'Focused on the markers most relevant to this package.'];
    }
    if ($fastingCode === 'CF') {
        $whyReasons[] = ['fi-rr-clock', 'Fasting sample for accuracy',
            'Requires 8–10 hours fasting so results are reliable.'];
    } elseif ($fastingCode === 'NF') {
        $whyReasons[] = ['fi-rr-check', 'No fasting needed',
            'Book any time of day — no overnight fasting required.'];
    }
    if ($loginPct > 0) {
        $whyReasons[] = ['fi-rr-badge-percent', 'Extra savings on login',
            'Members save up to ' . $loginPct . '% more when they order here.'];
    }
    $whyReasons[] = ['fi-rr-file-medical', 'Digital reports on your account',
        'Reports are saved to your eClinicPro Health account — access them anytime.'];
    $whyReasons[] = $collectionIsFree
        ? ['fi-rr-house-blank', 'Free home sample collection',
            'A trained phlebotomist collects your sample at your doorstep.']
        : ['fi-rr-house-blank', 'Home sample collection',
            'A trained phlebotomist collects your sample at your doorstep. Orders under ₹'
            . ECP_LAB_COLLECTION_MIN_ORDER . ' carry a ₹' . ECP_LAB_COLLECTION_FEE . ' collection charge.'];
    $whyReasons = array_slice($whyReasons, 0, 5);
} else {
    foreach (array_slice($d['benefits'] ?? $d['features'] ?? [], 0, 5) as $b) {
        $whyReasons[] = ['fi-rr-check', $b, 'Why this matters for your care'];
    }
}

// "Before Your Test" — driven by the product's real fasting flag so we never
// tell a no-fasting test to fast (or vice-versa).
$prep = [];
if ($fastingCode === 'CF') {
    $prep[] = ['fi-rr-clock', 'Fasting of 8–10 hours is required'];
    $prep[] = ['fi-rr-glass-water', 'Only plain water is allowed during fasting'];
} elseif ($fastingCode === 'NF') {
    $prep[] = ['fi-rr-check', 'No fasting required — book any time of day'];
    $prep[] = ['fi-rr-glass-water', 'Stay normally hydrated before your sample'];
} else {
    // Unknown fasting requirement — be honest, don't guess.
    $prep[] = ['fi-rr-info', 'Fasting instructions will be confirmed at booking'];
    $prep[] = ['fi-rr-glass-water', 'Drink water normally before your sample'];
}
$prep[] = ['fi-rr-pills', 'Continue your regular medicines unless advised otherwise'];
$prep[] = ['fi-rr-hand-holding-droplet', 'Morning sample collection is preferred'];

$stepsHow = [
    ['fi-rr-clipboard-list-check', 'Choose', 'Select this package as per your need'],
    ['fi-rr-calendar-clock', 'Book Slot', 'Pick a convenient slot for sample collection'],
    ['fi-rr-scooter', 'Home Collection', 'Our phlebotomist will collect sample from your home'],
    ['fi-rr-flask', 'Testing', 'Sample tested at NABL accredited labs'],
    ['fi-rr-smartphone', 'Digital Report', 'Get reports on app within 24 hours'],
    ['fi-rr-shield-check', 'Saved Securely', 'Reports stored in your eClinicPro Health account'],
];

$highlightsBar = [
    [$params . ' Tests Included', 'tests'],
    ['Digital Reports', 'clock'],
    ['NABL Accredited Labs', 'lab'],
    [$collectionIsFree ? 'Free Home Collection' : 'Home Collection ₹' . ECP_LAB_COLLECTION_FEE, 'home'],
    ['Powered by Thyrocare', 'doc'],
];

$testimonials = [
    ['Priya S.', 'Mumbai', 'Reports came the next morning and were saved straight to my account — easy to share with my doctor.'],
    ['Rahul K.', 'Pune', 'Home collection was on time and hygienic. Booking on eClinicPro was very simple.'],
    ['Ananya M.', 'Bengaluru', 'Good value versus MRP. The package covered everything my physician asked for.'],
];


$pageTitle = $d['title'] . ' — Lab Tests | eClinicPro';
$metaDesc = substr(strip_tags($d['overview'] ?? $d['subtitle'] ?? ''), 0, 155);
$activePage = 'lab';
$hideFinalCta = true;
$noindex = true;
$canonicalUrl = ecp_site_url(ecp_lab_detail_url($type, $slug));
$ogImage = $d['hero_image'] ?? null;
$bodyClass = 'ldp-body ldp-theme-' . $type;

$crumbAnchor = match ($type) {
    'life' => 'lab-lifestage',
    'package' => 'lab-packages',
    'organ' => 'lab-organs',
    'symptom' => 'lab-symptoms',
    default => 'lab-packages',
};

$heroSide = lab_photo($d['photo'] ?? 'photo-1579684385127-1ef15d508118', 900, 700);
$doctorImg = lab_photo('photo-1612349317150-e413f6a5b16d', 480, 560);
$waterImg = lab_photo('photo-1548839140-29a749e1cf4d', 560, 420);
$reportImg = lab_photo('photo-1576091160399-112ba8d25d1d', 700, 520);

$pkgPriceNum = (int) preg_replace('/\D/', '', (string) $price);
$pkgMrpNum = (int) preg_replace('/\D/', '', (string) $mrp);
$pkgDiscount = ($pkgMrpNum > $pkgPriceNum) ? ($pkgMrpNum - $pkgPriceNum) : 0;

$bookCities = ['Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Surat', 'Lucknow', 'Indore', 'Chandigarh', 'Nagpur', 'Coimbatore'];
$bookTimeSlots = [
    '06:00 AM - 07:00 AM', '07:00 AM - 08:00 AM', '08:00 AM - 09:00 AM', '09:00 AM - 10:00 AM',
    '10:00 AM - 11:00 AM', '11:00 AM - 12:00 PM', '12:00 PM - 01:00 PM', '02:00 PM - 03:00 PM',
    '03:00 PM - 04:00 PM', '04:00 PM - 05:00 PM', '05:00 PM - 06:00 PM',
];
// Searchable add-on TESTS (individual tests only, DB-driven). Excludes the test
// this page already is, so a /lab/test/... page can't offer to add itself.
$addonTests = ecp_lab_addon_tests(200, $type === 'test' ? $slug : '');

// Bookable collection window: never same-day, 19:00 IST cut-off drops tomorrow
// too, 7 days wide. Rendered into the date input's min/max AND re-derived in
// JS so a long-open tab corrects itself. Server re-validates on submit.
[$bookMinDate, $bookMaxDate] = ecp_lab_booking_window();

$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@flaticon/flaticon-uicons@3.3.1/css/regular/rounded.css">';

require __DIR__ . '/partials/header.php';
?>

<div class="store-preview-bar">
    <span class="store-preview-dot"></span>
    Preview only — lab booking launching soon. Detail pages are for layout &amp; partner demos.
</div>

<main class="ldp ldp-<?= e($type) ?><?= ($isPkg && $pkgPriceNum > 0) ? ' ldp-has-book' : '' ?>">

    <div class="wrap">
        <nav class="ldp-crumbs" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">›</span>
            <a href="/lab">Lab Tests</a>
            <span aria-hidden="true">›</span>
            <a href="/lab#<?= e($crumbAnchor) ?>"><?= e($d['crumb'] ?? 'Catalog') ?></a>
            <span aria-hidden="true">›</span>
            <span><?= e($d['title']) ?></span>
        </nav>

        <div class="ldp-shell<?= ($isPkg && $pkgPriceNum > 0) ? ' has-aside' : '' ?>">
            <div class="ldp-main">

    <!-- Hero -->
    <section class="ldp-hero">
        <div class="ldp-hero-grid">
            <div class="ldp-hero-copy">
                <?php if ($badge !== ''): ?><span class="ldp-badge"><?= e($badge) ?></span><?php endif; ?>
                <h1><?= e($d['title']) ?></h1>
                <?php if ($isPkg || $params): ?>
                <div class="ldp-hero-meta">
                    <span class="ldp-pill"><?= e($params) ?> Tests Included</span>
                </div>
                <?php endif; ?>
                <?php if ($detailTags): ?>
                <ul class="ldp-hero-tags" aria-label="Package details">
                    <?php foreach ($detailTags as [$tagIco, $tagLabel]): ?>
                    <li class="ldp-tag">
                        <i class="fi <?= e($tagIco) ?>" aria-hidden="true"></i>
                        <span><?= e($tagLabel) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <p class="ldp-hero-desc"><?= e($d['overview']) ?></p>
                <ul class="ldp-hero-perks">
                    <li><?= $collectionIsFree ? 'Free Home Collection' : 'Home Collection ₹' . ECP_LAB_COLLECTION_FEE ?></li>
                    <li>NABL Accredited Labs</li>
                    <li>Reports in 24 Hours</li>
                    <li>Saved to Your Health Account</li>
                </ul>
                <?php if ($isPkg && $price !== ''): ?>
                <div class="ldp-hero-price">
                    <strong>₹<?= e($price) ?></strong>
                    <?php if ($mrp !== ''): ?><s>₹<?= e($mrp) ?></s><?php endif; ?>
                    <?php if ($off !== ''): ?><span><?= e($off) ?>% OFF</span><?php endif; ?>
                        <div class="ldp-hero-actions">
                    <?php if ($isPkg): ?>
                    <a href="#ldpBookForm" class="ldp-btn ldp-btn-primary">Book Home Collection</a>
                    <!-- <a href="#ldp-tests" class="ldp-btn ldp-btn-ghost">View Tests</a> -->
                    <?php else: ?>
                    <a href="/lab#lab-packages" class="ldp-btn ldp-btn-primary">Browse Packages</a>
                    <a href="/lab" class="ldp-btn ldp-btn-ghost">Back to Lab</a>
                    <?php endif; ?>
                </div>
                </div>
                <?php endif; ?>
                <!-- <div class="ldp-hero-actions">
                    <?php if ($isPkg): ?>
                    <a href="#ldpBookForm" class="ldp-btn ldp-btn-primary">Book Home Collection</a>
                    <a href="#ldp-tests" class="ldp-btn ldp-btn-ghost">View Tests</a>
                    <?php else: ?>
                    <a href="/lab#lab-packages" class="ldp-btn ldp-btn-primary">Browse Packages</a>
                    <a href="/lab" class="ldp-btn ldp-btn-ghost">Back to Lab</a>
                    <?php endif; ?>
                </div> -->
                <div class="ldp-hero-trust">
                    <span>🔒 Secure booking</span>
                    <span>✔ 100% Safe &amp; Hygienic</span>
                </div>
                <div class="ldp-hero-logos" aria-label="Lab partners and certifications">
                    <img src="/assets/img/logos/thyrocare-logo.webp" alt="Thyrocare" width="140" height="48" loading="lazy">
                    <img src="/assets/img/logos/nabl-logo.webp" alt="100% NABL Accreditation" width="72" height="72" loading="lazy">
                    <img src="/assets/img/logos/cap-accredited-logo.webp" alt="CAP Accredited" width="120" height="48" loading="lazy">
                    <img src="/assets/img/logos/isologo.webp" alt="ISO 9001" width="72" height="72" loading="lazy">
                </div>
                
            </div>
            <div class="ldp-hero-media">
                <img src="<?= e($heroSide) ?>" alt="<?= e($d['title']) ?>" width="900" height="700" loading="eager">
            </div>
        </div>
    </section>

    <!-- Highlights bar -->
    <section class="ldp-bar">
        <div class="ldp-bar-inner">
            <?php foreach ($highlightsBar as [$label]): ?>
            <div class="ldp-bar-item">
                <span class="ldp-bar-ico" aria-hidden="true">●</span>
                <span><?= e($label) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- What's included chips -->
    <?php if ($includeChips): ?>
    <section class="ldp-section">
        <div class="ldp-section-head">
            <h2>What’s Included</h2>
            <?php if ($isPkg): ?><a href="#ldp-tests">View all tests</a><?php endif; ?>
        </div>
        <div class="ldp-chips">
            <?php
            // Best-effort PNG icon for a real group-name chip; generic fallback.
            $chipIcon = static function (string $label): string {
                $n = strtolower($label);
                return match (true) {
                    $label === '& More'                                        => 'MOre-icon.png',
                    (bool) preg_match('/sugar|diabet|glucose|hba1c/', $n)      => 'Hba1c-icon.png',
                    (bool) preg_match('/kidney|renal|urea|creat|electrolyte/', $n) => 'Kidney-icon.png',
                    (bool) preg_match('/liver|hepat/', $n)                     => 'Liver-Profile-icon.png',
                    (bool) preg_match('/lipid|cardiac|cholesterol|heart/', $n) => 'Lipid-Profile-icon.png',
                    (bool) preg_match('/thyroid/', $n)                         => 'Thyroid-icon.png',
                    (bool) preg_match('/iron|vitamin|anaemia|anemia|b12/', $n) => 'Iron-Studies-icon.png',
                    default                                                    => 'Blood-Sugar-icon.png',
                };
            };
            foreach ($includeChips as $chip): ?>
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/<?= e($chipIcon((string) $chip)) ?>" alt="" width="60" height="60" loading="lazy"></span>
                <span><?= e($chip) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Tests + Why choose (two-card layout) -->
    <section class="ldp-section ldp-section-soft" id="ldp-tests">
        <div class="ldp-tw">
            <article class="ldp-tw-card">
                <header class="ldp-tw-head">
                    <h2><?= $isPkg ? 'Tests Included' : 'Key Details' ?></h2>
                    <p><?= $isPkg ? 'Detailed breakdown of all tests in this package' : 'What this page covers and why it matters' ?></p>
                </header>
                <div class="ldp-tw-acc">
                    <?php foreach ($testGroups as $i => $g): ?>
                    <details class="ldp-tw-acc-item" <?= $i === 0 ? '' : '' ?>>
                        <summary>
                            <span class="ldp-tw-acc-ico" aria-hidden="true">
                                <i class="fi <?= e($g['icon'] ?? 'fi-rr-flask') ?>"></i>
                            </span>
                            <span class="ldp-tw-acc-title"><?= e($g['name']) ?></span>
                            <span class="ldp-tw-acc-count"><?= (int) $g['count'] ?> <?= $isPkg ? 'Tests' : 'Points' ?></span>
                            <span class="ldp-tw-acc-chev" aria-hidden="true">
                                <i class="fi fi-rr-angle-small-down"></i>
                            </span>
                        </summary>
                        <ul>
                            <?php foreach ($g['items'] as $row): ?>
                            <li><?= e($row) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="ldp-tw-card ldp-tw-why">
                <header class="ldp-tw-head">
                    <h2>Why Choose This Package?</h2>
                </header>
                <div class="ldp-tw-why-body">
                    <ul class="ldp-tw-why-list">
                        <?php foreach ($whyReasons as [$wIco, $wTitle, $wSub]): ?>
                        <li>
                            <span class="ldp-tw-why-ico" aria-hidden="true">
                                <i class="fi <?= e($wIco) ?>"></i>
                            </span>
                            <div>
                                <strong><?= e($wTitle) ?></strong>
                                <span><?= e($wSub) ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <figure class="ldp-tw-why-photo">
                        <img src="<?= e($doctorImg) ?>" alt="Doctor consultation" width="480" height="560" loading="lazy">
                    </figure>
                </div>
            </aside>
        </div>
    </section>

    <!-- Prep + How it works -->
    <section class="ldp-section ldp-section-prep">
        <div class="ldp-split-prep">
            <article class="ldp-prep-card">
                <h2 class="ldp-h2 ldp-h2-teal">Before Your Test</h2>
                <ul class="ldp-prep-list">
                    <?php foreach ($prep as [$ico, $label]): ?>
                    <li>
                        <span class="ldp-prep-ico" aria-hidden="true"><i class="fi <?= e($ico) ?>"></i></span>
                        <span><?= e($label) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <!-- <img class="ldp-prep-photo" src="<?= e($waterImg) ?>" alt="Stay hydrated before your test" width="560" height="420" loading="lazy" onerror="this.style.display='none'"> -->
            </article>
            <article class="ldp-flow-card">
                <h2 class="ldp-h2">How it Works</h2>
                <ol class="ldp-flow">
                    <?php foreach ($stepsHow as $i => [$ico, $t, $p]): ?>
                    <li class="ldp-flow-step">
                        <span class="ldp-flow-ico" aria-hidden="true"><i class="fi <?= e($ico) ?>"></i></span>
                        <strong><?= e($t) ?></strong>
                        <span><?= e($p) ?></span>
                        <?php if ($i < count($stepsHow) - 1): ?>
                        <span class="ldp-flow-arrow" aria-hidden="true"><i class="fi fi-rr-arrow-small-right"></i></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </article>  
        </div>
    </section>

    <!-- Report + Reviews -->
    <!-- <section class="ldp-section ldp-section-soft">
        <div class="wrap ldp-split">
            <div>
                <h2 class="ldp-h2">Sample Report Preview</h2>
                <div class="ldp-report">
                    <img src="<?= e($reportImg) ?>" alt="Digital lab report on devices" width="700" height="520" loading="lazy">
                    <p>Get clear digital reports on the app — share with your doctor anytime.</p>
                </div>
            </div>
            <div>
                <h2 class="ldp-h2">What Our Customers Say</h2>
                <div class="ldp-reviews">
                    <?php foreach ($testimonials as $rev): ?>
                    <article class="ldp-review">
                        <div class="ldp-stars" aria-hidden="true">★★★★★</div>
                        <p>“<?= e($rev[2]) ?>”</p>
                        <footer>
                            <strong><?= e($rev[0]) ?></strong>
                            <span><?= e($rev[1]) ?></span>
                        </footer>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section> -->

    <!-- FAQ -->
    <section class="ldp-section" id="ldp-faq">
        <div class="ldp-section-head ldp-section-head-center">
            <h2>Frequently Asked Questions</h2>
        </div>
        <div class="ldp-faq-search">
            <input type="search" id="ldpFaqSearch" placeholder="Search your question…" aria-label="Search FAQs">
        </div>
        <div class="ldp-faq-grid" id="ldpFaqGrid">
            <?php foreach ($d['faq'] as $faq): ?>
            <details class="ldp-faq-item" data-faq="<?= e(strtolower($faq['q'] . ' ' . $faq['a'])) ?>">
                <summary><?= e($faq['q']) ?></summary>
                <p><?= e($faq['a']) ?></p>
            </details>
            <?php endforeach; ?>
            <?php
            $loginFaqAnswer = $loginPct > 0
                ? 'Yes — log in with a free eClinicPro account before you book to unlock an extra ' . $loginPct . '% member discount on this package, applied automatically at checkout on top of the price shown.'
                : 'Log in with a free eClinicPro account before you book to unlock any available member discounts and save your reports to your Health account.';
            $extraFaq = [
                ['Do I get a discount if I log in before booking?', $loginFaqAnswer],
                ['Are home collection charges included?',
                 'Home sample collection is free once your test total is ₹' . ECP_LAB_COLLECTION_MIN_ORDER
                 . ' or more after any member discount. Below that our lab partner charges ₹' . ECP_LAB_COLLECTION_FEE
                 . ' for the visit, which we pass on at cost. Collection and hard-copy courier charges are '
                 . 'service fees, so no discount or coupon applies to them. Everything is itemised in your '
                 . 'order summary before you confirm, and adding more tests or booking for more than one '
                 . 'person often takes you past ₹' . ECP_LAB_COLLECTION_MIN_ORDER . ' and makes collection free.'],
                ['Can I reschedule my slot?', 'Yes. You can reschedule your home collection slot from your booking confirmation once bookings go live.'],
            ];
            foreach ($extraFaq as $faq): ?>
            <details class="ldp-faq-item" data-faq="<?= e(strtolower($faq[0] . ' ' . $faq[1])) ?>">
                <summary><?= e($faq[0]) ?></summary>
                <p><?= e($faq[1]) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Related -->
    <?php if (!empty($d['related'])): ?>
    <section class="ldp-section ldp-section-soft">
            <div class="ldp-section-head">
                <h2>You may also like</h2>
                <a href="/lab#lab-packages">View all packages</a>
            </div>
            <div class="ldp-related">
                <?php foreach ($d['related'] as $rel): ?>
                <a href="<?= e(ecp_lab_detail_url($rel['type'], $rel['slug'])) ?>" class="ldp-related-card">
                    <img src="<?= e(lab_photo($rel['photo'], 480, 300)) ?>" alt="<?= e($rel['title']) ?>" width="480" height="300" loading="lazy">
                    <div>
                        <h3><?= e($rel['title']) ?></h3>
                        <p><?= e($rel['subtitle'] ?? $rel['blurb'] ?? '') ?></p>
                        <?php if (($rel['type'] ?? '') === 'package' && !empty($rel['price'])): ?>
                        <div class="ldp-related-price">
                            <strong>₹<?= e($rel['price']) ?></strong>
                            <?php if (!empty($rel['mrp'])): ?><s>₹<?= e($rel['mrp']) ?></s><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
    </section>
    <?php endif; ?>

            </div><!-- /.ldp-main -->

            <?php if ($isPkg && $pkgPriceNum > 0): ?>
            <aside class="ldp-aside" id="ldpBookForm" aria-label="Book this package">
                <?php // Success panel: replaces the form once a booking is stored, so the
                      // outcome is unmissable and the reference number is on screen to
                      // quote. A toast alone was invisible behind the sticky bar. ?>
                <div class="ldp-bf-success" id="ldpBfSuccess" hidden role="status" aria-live="polite">
                    <div class="ldp-bf-success-ico" aria-hidden="true"><i class="fi fi-rr-check"></i></div>
                    <h2 class="ldp-bf-success-title">Booking request received</h2>
                    <p class="ldp-bf-success-sub" id="ldpBfSuccessSub"></p>

                    <div class="ldp-bf-success-ref">
                        <span class="ldp-bf-success-ref-label">Your reference</span>
                        <strong id="ldpBfSuccessRef"></strong>
                    </div>

                    <dl class="ldp-bf-success-meta">
                        <div><dt>Package</dt><dd id="ldpBfSuccessPkg"></dd></div>
                        <div><dt>Collection</dt><dd id="ldpBfSuccessWhen"></dd></div>
                        <div><dt>Total payable</dt><dd id="ldpBfSuccessTotal"></dd></div>
                    </dl>

                    <ol class="ldp-bf-success-steps">
                        <li>We confirm your slot and send a payment link.</li>
                        <li>A trained phlebotomist collects your sample at home.</li>
                        <li>Your report is emailed and saved to your account.</li>
                    </ol>

                    <div class="ldp-bf-success-actions">
                        <a class="ldp-btn ldp-btn-primary" href="/patient#laborders">View my bookings</a>
                        <button type="button" class="ldp-btn ldp-btn-ghost" id="ldpBfBookAnother">Book another test</button>
                    </div>
                </div>

                <div class="ldp-bf" id="ldpBfCard">
                    <header class="ldp-bf-head">
                        <h2>Book This Package</h2>
                    </header>
                    <div class="ldp-bf-paylater">
                        <span class="ldp-bf-paylater-ico" aria-hidden="true"><i class="fi fi-rr-wallet"></i></span>
                        <div class="ldp-bf-paylater-txt">
                            <strong class="ldp-bf-paylater-title">Book Now, Pay Later!</strong>
                            <span class="ldp-bf-paylater-sub">Simple Process, No Spam Calls</span>
                        </div>
                    </div>
                    <form class="ldp-bf-form" id="ldpLabBookForm" novalidate
                          data-pkg-price="<?= (int) $pkgPriceNum ?>"
                          data-pkg-mrp="<?= (int) $pkgMrpNum ?>"
                          data-pkg-name="<?= e($d['title']) ?>"
                          <?php // The slug is what api/lab_book.php re-prices from — the
                                // posted price is never trusted. ?>
                          data-pkg-slug="<?= e($slug) ?>"
                          data-hardcopy="<?= (int) ECP_LAB_HARDCOPY_FEE ?>"
                          data-collection-fee="<?= (int) ECP_LAB_COLLECTION_FEE ?>"
                          data-collection-min="<?= (int) ECP_LAB_COLLECTION_MIN_ORDER ?>"
                          data-collection-on-discounted="<?= ECP_LAB_COLLECTION_ON_DISCOUNTED ? '1' : '0' ?>">

                        <div class="ldp-bf-block">
                            <?php if ($ecpPatient): ?>
                            <div class="ldp-bf-user" id="ldpBfUser">
                                <span class="ldp-bf-user-avatar" aria-hidden="true"><?= e(ecp_patient_initials($ecpPatient)) ?></span>
                                <div class="ldp-bf-user-meta">
                                    <strong class="ldp-bf-user-name"><?= e(trim((string) ($ecpPatient['name'] ?? '')) ?: ecp_patient_first_name($ecpPatient)) ?></strong>
                                    <span class="ldp-bf-user-phone"><?= e((string) ($ecpPatient['phone'] ?? '')) ?></span>
                                </div>
                                <span class="ldp-bf-user-badge"><i class="fi fi-rr-check" aria-hidden="true"></i> Logged in</span>
                            </div>
                            <?php else: ?>
                            <?php // Booking requires an account (the server enforces it too),
                                  // and logging in reloads the page — so say so BEFORE the
                                  // patient fills 10 fields. Their entries are also saved and
                                  // restored across that reload, but not starting over at all
                                  // is the better outcome. ?>
                            <button type="button" class="ldp-bf-login" id="ldpBfLogin" data-auth-reason="lab_book">
                                <span class="ldp-bf-login-ico" aria-hidden="true"><i class="fi fi-rr-user"></i></span>
                                <span class="ldp-bf-login-txt">
                                    <strong>Login / Sign up to book</strong>
                                    <small>Takes 30 seconds — unlocks your member discount</small>
                                </span>
                                <span class="ldp-bf-login-arrow" aria-hidden="true"><i class="fi fi-rr-angle-right"></i></span>
                            </button>
                            <p class="ldp-bf-loginnote">
                                An account is required to book — it keeps your reports and
                                booking history in one place. We’ll save anything you’ve
                                already typed.
                            </p>
                            <?php endif; ?>

                            <div class="ldp-bf-pingate" id="ldpBfPinGate">
                                <p class="ldp-bf-pinhint">Write <strong>Exact Pincode</strong>, not nearby Pincode</p>
                                <div class="ldp-bf-pinrow">
                                    <input type="text" name="pincode" id="ldpBfPincode" inputmode="numeric" maxlength="6" placeholder="Enter your area pincode" pattern="[1-9][0-9]{5}" required autocomplete="postal-code">
                                    <button type="button" class="ldp-bf-check" id="ldpBfPinCheck">Check Availability</button>
                                </div>
                                <p class="ldp-bf-pinmsg" id="ldpBfPinMsg" hidden></p>
                                <!-- Auto-filled from the serviceable-pincode map on a successful check. -->
                                <div class="ldp-bf-pinloc" id="ldpBfPinLoc" hidden>
                                    <div class="ldp-bf-pinloc-field">
                                        <label class="ldp-bf-label" for="ldpBfCity">City</label>
                                        <input type="text" id="ldpBfCity" name="city" readonly autocomplete="off">
                                    </div>
                                    <div class="ldp-bf-pinloc-field">
                                        <label class="ldp-bf-label" for="ldpBfState">State</label>
                                        <input type="text" id="ldpBfState" name="state" readonly autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <div class="ldp-bf-persons-row">
                                <label class="ldp-bf-label" for="ldpBfPersons">Number of Persons</label>
                                <div class="ldp-bf-qty" role="group" aria-label="Number of persons">
                                    <button type="button" class="ldp-bf-qty-btn" id="ldpBfQtyMinus" aria-label="Decrease">−</button>
                                    <input type="number" name="persons" id="ldpBfPersons" value="1" min="1" max="10" readonly>
                                    <button type="button" class="ldp-bf-qty-btn" id="ldpBfQtyPlus" aria-label="Increase">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="ldp-bf-block">
                            <p class="ldp-bf-hint">Write <strong>FULL NAME</strong> for all persons.</p>
                            <div id="ldpBfBeneficiaries" class="ldp-bf-beneficiaries">
                                <div class="ldp-bf-person" data-person="1">
                                    <input type="text" name="beneficiary_name[]" placeholder="Beneficiary Name 1" required autocomplete="name">
                                    <div class="ldp-bf-agegender">
                                        <input type="number" name="beneficiary_age[]" min="1" max="120" placeholder="Age" required>
                                        <select name="beneficiary_gender[]" required aria-label="Select Gender">
                                            <option value="" disabled selected>Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <label class="ldp-bf-label" for="ldpBfEmail">Email</label>
                            <input type="email" id="ldpBfEmail" name="email" placeholder="Email" required autocomplete="email">

                            <label class="ldp-bf-label" for="ldpBfPhone">Phone</label>
                            <?php // Fixed "+91" prefix rather than a free-text field: the old
                                  // input had maxlength="10" AND pattern="[6-9][0-9]{9}", so
                                  // anyone typing "+91…" silently lost the last digits and
                                  // could never submit. The country code is now display-only
                                  // and the field holds exactly the 10 national digits. ?>
                            <div class="ldp-bf-phone">
                                <span class="ldp-bf-phone-cc" aria-hidden="true">+91</span>
                                <input type="tel" id="ldpBfPhone" name="phone" placeholder="10-digit mobile number"
                                       required inputmode="numeric" maxlength="10"
                                       pattern="[6-9][0-9]{9}" autocomplete="tel-national"
                                       aria-describedby="ldpBfPhoneHint">
                            </div>
                            <p class="ldp-bf-phone-hint" id="ldpBfPhoneHint">Enter the 10 digits only — we add +91 for you.</p>

                            <label class="ldp-bf-label" for="ldpBfAddress">Complete Address</label>
                            <textarea id="ldpBfAddress" name="address" rows="3" placeholder="Complete Address" required autocomplete="street-address"></textarea>
                            <p class="ldp-bf-note-danger">Note: Order with incomplete address will be rejected.</p>

                            <hr class="ldp-bf-divider">

                            <label class="ldp-bf-label">Preferred Date &amp; Time</label>
                            <div class="ldp-bf-datetime">
                                <div class="ldp-bf-icon-field">
                                    <input type="date" id="ldpBfDate" name="appointment_date" required
                                           min="<?= e($bookMinDate) ?>" max="<?= e($bookMaxDate) ?>"
                                           aria-label="Select Preferred Appointment Date">
                                    <span class="ldp-bf-ico" aria-hidden="true"><i class="fi fi-rr-calendar"></i></span>
                                </div>
                                <div class="ldp-bf-select-wrap ldp-bf-icon-field">
                                    <select id="ldpBfTime" name="time_slot" required>
                                        <option value="" disabled selected>Select Time Slot</option>
                                        <?php foreach ($bookTimeSlots as $slot): ?>
                                        <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="ldp-bf-ico" aria-hidden="true"><i class="fi fi-rr-clock"></i></span>
                                </div>
                            </div>
                            <?php // Says WHY earlier dates aren't offered, so a patient looking
                                  // for "today" isn't left guessing. Rewritten by JS if the
                                  // cut-off passes while the page is open. ?>
                            <p class="ldp-bf-datehint" id="ldpBfDateHint">
                                Collection starts <?= e(date('D, d M', strtotime($bookMinDate) ?: time())) ?>.
                                Book up to <?= e(date('D, d M', strtotime($bookMaxDate) ?: time())) ?>.
                                Orders placed after <?= (int) ECP_LAB_CUTOFF_HOUR_IST ?>:00 IST start from the following day.
                            </p>

                            <ol class="ldp-bf-notes-list">
                                <li>Appointment confirmation and Technician details will be provided to you through Email &amp; WhatsApp/SMS well in advance.</li>
                                <li>The date and time of collection may change if Technicians are not available at the requested time.</li>
                                <li>Kindly allow 30 minutes (Plus or Minus) for the technician to find and reach your location through possible traffic/weather.</li>
                            </ol>
                        </div>

                        <div class="ldp-bf-block">
                            <h3 class="ldp-bf-title">Additional Information</h3>
                            <?php if ($loginPct > 0): ?>
                            <?php
                            // Single coupon: the best login discount for THIS product, already
                            // capped by lab_product_pricing.max_discount_pct (see $loginPct).
                            // The chip is NAMED for the package's capped rate, but carries the
                            // headline rate too — each line caps that for itself, so a 5%-capped
                            // add-on gets 5% while this package gets its own ceiling.
                            $couponPct  = $loginPct;
                            $couponCode = 'SAVE' . $couponPct;
                            ?>
                            <label class="ldp-bf-label">Coupon Code <span>(Optional)</span></label>
                            <div class="ldp-bf-coupons" id="ldpBfCoupons" data-logged-in="<?= $ecpPatient ? '1' : '0' ?>">
                                <div class="ldp-bf-coupon" data-code="<?= e($couponCode) ?>" data-pct="<?= (int) $couponPct ?>"
                                     data-headline-pct="<?= (int) $couponHeadlinePct ?>"
                                     data-pkg-max-pct="<?= (int) $loginPct ?>">
                                    <div class="ldp-bf-coupon-info">
                                        <span class="ldp-bf-coupon-code"><?= e($couponCode) ?></span>
                                        <span class="ldp-bf-coupon-desc"><?= (int) $couponPct ?>% off</span>
                                    </div>
                                    <button type="button" class="ldp-bf-coupon-apply">Apply</button>
                                </div>
                            </div>
                            <input type="hidden" id="ldpBfCoupon" name="coupon" value="">
                            <p class="ldp-bf-coupon-msg" id="ldpBfCouponMsg" hidden></p>
                            <?php endif; ?>

                            <label class="ldp-bf-label" for="ldpBfNotes">Add Notes <span>(Optional)</span></label>
                            <textarea id="ldpBfNotes" name="notes" rows="3" placeholder="Write any instructions"></textarea>
                        </div>

                        <div class="ldp-bf-block" id="ldpBookAddons">
                            <h3 class="ldp-bf-title">Add More Tests (Optional)</h3>

                            <div class="ldp-bf-testsearch" id="ldpAddonSearch">
                                <span class="ldp-bf-testsearch-ico" aria-hidden="true"><i class="fi fi-rr-search"></i></span>
                                <input type="text" id="ldpAddonInput" class="ldp-bf-testsearch-input"
                                       placeholder="Search and add more tests" autocomplete="off"
                                       role="combobox" aria-expanded="false" aria-controls="ldpAddonResults"
                                       aria-autocomplete="list">
                                <ul class="ldp-bf-testsearch-results" id="ldpAddonResults" role="listbox" hidden></ul>
                            </div>

                            <!-- Chosen tests land here as removable chips; each carries a hidden,
                                 checked addons[] checkbox so the existing total logic picks it up. -->
                            <div class="ldp-bf-testchips" id="ldpAddonChips"></div>

                            <?php
                            // Test catalogue for the picker (individual tests only).
                            // max_pct is each test's OWN discount ceiling — the coupon is
                            // capped per line, so a 5%-capped test never receives the
                            // package's 25%. See ecp_lab_line_discount_pct().
                            $addonJson = array_map(static fn ($a) => [
                                'id'      => (string) $a['id'],
                                'label'   => (string) $a['label'],
                                'price'   => (int) $a['price'],
                                'max_pct' => (int) ($a['max_pct'] ?? 0),
                            ], $addonTests);
                            ?>
                            <script type="application/json" id="ldpAddonData"><?= json_encode($addonJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

                            <label class="ldp-bf-hardcopy">
                                <input type="checkbox" name="hard_copy" id="ldpBfHardCopy" value="1">
                                <span>Please tick to receive hard copy. Courier charges <b>Rs. <?= (int) ECP_LAB_HARDCOPY_FEE ?> Extra.</b></span>
                            </label>
                        </div>

                        <?php // Inline error, right above the button that triggers it. The
                              // old code only used a bottom-fixed toast, which this page's
                              // sticky bar covers — so failures looked like dead clicks. ?>
                        <p class="ldp-bf-formerror" id="ldpBfFormError" role="alert" hidden></p>

                        <button type="submit" class="ldp-bf-submit">
                            <span class="ldp-bf-submit-label">Book Now</span>
                            <span class="ldp-bf-submit-save" id="ldpBfSubmitSave" hidden></span>
                        </button>

                        <div class="ldp-bf-paylater">
                            <span class="ldp-bf-paylater-ico" aria-hidden="true"><i class="fi fi-rr-wallet"></i></span>
                            <div class="ldp-bf-paylater-txt">
                                <strong class="ldp-bf-paylater-title">Book Now, Pay Later!</strong>
                                <span class="ldp-bf-paylater-sub">You will get a payment link shortly. You can make the payment ONLINE (<b>Recommended</b>) using that link or pay using UPI/Cash to the technician.</span>
                            </div>
                        </div>

                        <div class="ldp-bf-summary">
                            <div class="ldp-bf-row">
                                <span class="ldp-bf-sum-name"><?= e($d['title']) ?></span>
                                <span class="ldp-bf-val" id="ldpBfSumPkg">₹<?= number_format($pkgPriceNum) ?></span>
                            </div>

                            <!-- Add-on tests chosen via "Add More Tests" are itemised here. -->
                            <div id="ldpBfSumAddons"></div>

                            <?php // Free above the threshold, ₹200 below it (Thyrocare's
                                  // rule, passed through at cost). Both the value and the
                                  // hint are rewritten by updateTotals() as the order changes. ?>
                            <div class="ldp-bf-row ldp-bf-row-collection">
                                <span>Home Collection Charges</span>
                                <span class="ldp-bf-val" id="ldpBfSumCollection">Free</span>
                            </div>
                            <p class="ldp-bf-collection-hint" id="ldpBfCollectionHint" hidden></p>

                            <!-- Courier line: only shown when the hard-copy option is ticked. -->
                            <div class="ldp-bf-row" id="ldpBfSumCourierRow" hidden>
                                <span>Hard Copy Courier</span>
                                <span class="ldp-bf-val" id="ldpBfSumCourier">₹<?= (int) ECP_LAB_HARDCOPY_FEE ?></span>
                            </div>

                            <!-- Discount line: only shown when a coupon is applied. -->
                            <div class="ldp-bf-row ldp-bf-row-discount" id="ldpBfSumDiscountRow" hidden>
                                <span id="ldpBfSumDiscountLabel">Discount</span>
                                <span class="ldp-bf-val" id="ldpBfSumDiscount">- ₹0</span>
                            </div>

                            <div class="ldp-bf-row ldp-bf-row-total">
                                <span>Total Amount</span>
                                <strong id="ldpBfSumTotal">₹<?= number_format($pkgPriceNum) ?></strong>
                            </div>

                            <?php // The one savings figure, with the base it was measured
                                  // against. The "Save ₹X" button, the sticky "% OFF" badge
                                  // and this row all read from listValue/discount in
                                  // updateTotals(), so they cannot disagree. ?>
                            <div class="ldp-bf-row ldp-bf-row-saved" id="ldpBfSumSavedRow" hidden>
                                <span>You save</span>
                                <span class="ldp-bf-val" id="ldpBfSumSaved">₹0</span>
                            </div>
                            <p class="ldp-bf-savednote" id="ldpBfSumSavedNote" hidden></p>
                            <?php // Stated where the money is, not just in the FAQ — the
                                  // fee/discount interaction is the most likely source of a
                                  // "why is my total higher?" support ticket. ?>
                            <p class="ldp-bf-note" id="ldpBfFeeNote" hidden>Collection and courier charges are service fees — discounts and coupons do not apply to them.</p>
                            <p class="ldp-bf-note">Note: Payment should be made before or at the time of sample collection.</p>
                            <p class="ldp-bf-secure"><i class="fi fi-rr-shield-check" aria-hidden="true"></i> 100% Secure Booking</p>
                        </div>
                    </form>
                </div>
            </aside>
            <?php endif; ?>

        </div><!-- /.ldp-shell -->
    </div><!-- /.wrap -->

</main>

<!-- Sticky CTA -->
<div class="ldp-sticky" id="ldpSticky">
    <div class="wrap ldp-sticky-inner">
        <p>Ready to take control of your health? Book <strong><?= e($d['title']) ?></strong> today with free home sample collection.</p>
        <div class="ldp-sticky-actions">
            <?php if ($isPkg && $price !== ''): ?>
            <div class="ldp-sticky-price">
                <strong id="ldpStickyTotal">₹<?= e($price) ?></strong>
                <?php // Always in the DOM (hidden when 0) so updateTotals() can
                      // fill it in once a coupon applies — a product with no
                      // MRP margin still reaches a real discount via the coupon. ?>
                <span id="ldpStickyOff" <?= ((int) $off) > 0 ? '' : 'hidden' ?>><?= e($off !== '' ? $off : '0') ?>% OFF</span>
            </div>
            <a href="#ldpBookForm" class="ldp-btn ldp-btn-light">Book Now</a>
            <?php else: ?>
            <a href="/lab#lab-packages" class="ldp-btn ldp-btn-light">Browse Packages</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="storeToast" class="store-toast" role="status" aria-live="polite">Coming soon — lab tests are launching shortly.</div>
<script>
(function () {
    var toast = document.getElementById('storeToast');
    var timer = null;
    function showToast(msg) {
        if (!toast) return;
        if (msg) toast.textContent = msg;
        toast.classList.add('is-on');
        clearTimeout(timer);
        timer = setTimeout(function () { toast.classList.remove('is-on'); }, 3200);
    }

    var faqSearch = document.getElementById('ldpFaqSearch');
    var faqItems = document.querySelectorAll('#ldpFaqGrid .ldp-faq-item');
    if (faqSearch && faqItems.length) {
        faqSearch.addEventListener('input', function () {
            var q = (faqSearch.value || '').toLowerCase().trim();
            faqItems.forEach(function (el) {
                var hay = el.getAttribute('data-faq') || '';
                el.hidden = q !== '' && hay.indexOf(q) === -1;
            });
        });
    }

    var form = document.getElementById('ldpLabBookForm');
    if (!form) return;

    var pkgPrice = parseInt(form.getAttribute('data-pkg-price') || '0', 10) || 0;
    var pkgMrp = parseInt(form.getAttribute('data-pkg-mrp') || '0', 10) || pkgPrice;
    var hardFee = parseInt(form.getAttribute('data-hardcopy') || '75', 10) || 75;
    // Home-collection charge config (see ECP_LAB_COLLECTION_* in lab_catalog.php).
    var collectionFee = parseInt(form.getAttribute('data-collection-fee') || '0', 10) || 0;
    var collectionMin = parseInt(form.getAttribute('data-collection-min') || '0', 10) || 0;
    var collectionOnDiscounted = form.getAttribute('data-collection-on-discounted') === '1';
    var personsInput = document.getElementById('ldpBfPersons');
    var minusBtn = document.getElementById('ldpBfQtyMinus');
    var plusBtn = document.getElementById('ldpBfQtyPlus');
    var beneficiaries = document.getElementById('ldpBfBeneficiaries');
    var dateInput = document.getElementById('ldpBfDate');
    var pinInput = document.getElementById('ldpBfPincode');
    var pinCheck = document.getElementById('ldpBfPinCheck');
    var pinMsg = document.getElementById('ldpBfPinMsg');
    var hardCopy = document.getElementById('ldpBfHardCopy');
    var couponInput = document.getElementById('ldpBfCoupon');
    var couponMsg = document.getElementById('ldpBfCouponMsg');
    var couponsWrap = document.getElementById('ldpBfCoupons');
    var pinOk = false;

    // ── Collection date window ──────────────────────────────────────────
    // Never same-day; after 19:00 IST tomorrow closes too; 7 days wide.
    // Seeded from PHP (authoritative, always IST) and recomputed in the browser
    // so a tab left open overnight — or across the cut-off — corrects itself
    // instead of offering a slot the server will reject.
    var bookMinDate = <?= json_encode($bookMinDate) ?>;
    var bookMaxDate = <?= json_encode($bookMaxDate) ?>;
    var CUTOFF_HOUR_IST = <?= (int) ECP_LAB_CUTOFF_HOUR_IST ?>;
    var WINDOW_DAYS = <?= (int) ECP_LAB_BOOKING_WINDOW_DAYS ?>;

    function ymd(d) {
        return d.getUTCFullYear() + '-'
            + String(d.getUTCMonth() + 1).padStart(2, '0') + '-'
            + String(d.getUTCDate()).padStart(2, '0');
    }

    // "Now" as an IST wall-clock instant, independent of the device timezone.
    function istNow() {
        return new Date(Date.now() + 5.5 * 3600 * 1000);
    }

    function refreshDateWindow() {
        var ist = istNow();
        var daysAhead = ist.getUTCHours() >= CUTOFF_HOUR_IST ? 2 : 1;

        var min = new Date(ist.getTime());
        min.setUTCHours(0, 0, 0, 0);
        min.setUTCDate(min.getUTCDate() + daysAhead);

        var max = new Date(min.getTime());
        max.setUTCDate(max.getUTCDate() + (WINDOW_DAYS - 1));

        bookMinDate = ymd(min);
        bookMaxDate = ymd(max);

        if (dateInput) {
            dateInput.min = bookMinDate;
            dateInput.max = bookMaxDate;
            // Drop a selection that just fell out of the window (e.g. the page
            // was open when the cut-off passed).
            if (dateInput.value && (dateInput.value < bookMinDate || dateInput.value > bookMaxDate)) {
                dateInput.value = '';
            }
        }

        var hint = document.getElementById('ldpBfDateHint');
        if (hint) {
            var fmt = function (iso) {
                var parts = iso.split('-');
                var d = new Date(Date.UTC(+parts[0], +parts[1] - 1, +parts[2]));
                return d.toLocaleDateString('en-IN', {
                    weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC'
                });
            };
            hint.textContent = 'Collection starts ' + fmt(bookMinDate)
                + '. Book up to ' + fmt(bookMaxDate)
                + '. Orders placed after ' + CUTOFF_HOUR_IST + ':00 IST start from the following day.';
        }
        return bookMinDate;
    }

    if (dateInput) {
        refreshDateWindow();
        // Re-check when the tab regains focus — the common way a stale window
        // gets used is leaving the page open and coming back later.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshDateWindow();
        });
    }

    function persons() {
        return Math.max(1, Math.min(10, parseInt((personsInput && personsInput.value) || '1', 10) || 1));
    }

    // ── Phone normalisation ─────────────────────────────────────────────
    // Indians type their number every which way: "+91 98765 43210",
    // "091-9876543210", "9876543210". Reduce all of them to the 10 national
    // digits. Only strips a leading 91 when what remains is a plausible mobile
    // (12 digits total), so a genuine number that happens to start "91…" is
    // left alone.
    function normalisePhone(raw) {
        var d = String(raw || '').replace(/\D/g, '');
        if (d.length === 12 && d.indexOf('91') === 0) d = d.slice(2);
        else if (d.length === 11 && d.charAt(0) === '0') d = d.slice(1);
        return d.slice(0, 10);
    }

    // ── Inline form errors ──────────────────────────────────────────────
    // The old code used showToast(), which renders fixed at the bottom of the
    // viewport — directly behind the sticky "Book Now" bar on this page. The
    // message was effectively invisible, so a rejected submit looked like
    // nothing happening at all. Errors now appear in the form, next to the
    // button that was pressed, and the offending field is scrolled into view.
    var errBox = document.getElementById('ldpBfFormError');

    function clearFormError() {
        if (errBox) { errBox.hidden = true; errBox.textContent = ''; }
    }

    function formError(msg, focusEl) {
        if (errBox) {
            errBox.textContent = msg;
            errBox.hidden = false;
            try { errBox.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) {}
        }
        if (focusEl) {
            try { focusEl.focus({ preventScroll: true }); } catch (e) { focusEl.focus(); }
        }
        // Keep the toast as a secondary channel for anyone who missed the box.
        showToast(msg);
    }

    function personBlockHtml(n) {
        return ''
            + '<div class="ldp-bf-person" data-person="' + n + '">'
            + '<input type="text" name="beneficiary_name[]" placeholder="Beneficiary Name ' + n + '" required autocomplete="name">'
            + '<div class="ldp-bf-agegender">'
            + '<input type="number" name="beneficiary_age[]" min="1" max="120" placeholder="Age" required>'
            + '<select name="beneficiary_gender[]" required aria-label="Select Gender">'
            + '<option value="" disabled selected>Select Gender</option>'
            + '<option value="Male">Male</option>'
            + '<option value="Female">Female</option>'
            + '<option value="Other">Other</option>'
            + '</select>'
            + '</div></div>';
    }

    function syncBeneficiaries() {
        if (!beneficiaries) return;
        var n = persons();
        var existing = beneficiaries.querySelectorAll('.ldp-bf-person');
        var cur = existing.length;
        if (n > cur) {
            for (var i = cur + 1; i <= n; i++) {
                beneficiaries.insertAdjacentHTML('beforeend', personBlockHtml(i));
            }
        } else if (n < cur) {
            for (var j = cur; j > n; j--) {
                var el = beneficiaries.querySelector('.ldp-bf-person[data-person="' + j + '"]');
                if (el) el.remove();
            }
        }
    }

    function setPersons(n) {
        n = Math.max(1, Math.min(10, n));
        if (personsInput) {
            personsInput.value = String(n);
            personsInput.classList.add('is-highlight');
        }
        syncBeneficiaries();
        updateTotals();
    }

    function setPinMsg(text, ok) {
        if (!pinMsg) return;
        pinMsg.hidden = !text;
        pinMsg.textContent = text || '';
        pinMsg.classList.toggle('is-ok', !!ok);
        pinMsg.classList.toggle('is-bad', !!text && !ok);
    }

    var cityInput = document.getElementById('ldpBfCity');
    var stateInput = document.getElementById('ldpBfState');
    var pinLoc = document.getElementById('ldpBfPinLoc');
    var pinChecking = false;

    // Enable/disable everything below the pincode gate. The pincode input and
    // its Check button stay live; the rest of the form is unusable until a
    // serviceable pincode is confirmed, so no unfulfillable order can submit.
    function setFormLocked(locked) {
        form.classList.toggle('is-locked', !!locked);
        var controls = form.querySelectorAll('input, select, textarea, button');
        controls.forEach(function (el) {
            if (el === pinInput || el === pinCheck) return;        // gate stays live
            if (el === cityInput || el === stateInput) return;     // auto-filled, always readonly
            // Login/sign-up and coupon Apply sit above the pincode gate — logging in
            // is meant to happen first, so they must stay clickable while locked.
            if (el.id === 'ldpBfLogin' || el.closest('.ldp-bf-coupon')) return;
            el.disabled = !!locked;
        });
    }

    function setLocation(city, state) {
        if (cityInput) cityInput.value = city || '';
        if (stateInput) stateInput.value = state || '';
        if (pinLoc) pinLoc.hidden = !(city && state);
    }

    function checkPincode() {
        var pin = ((pinInput && pinInput.value) || '').replace(/\D/g, '');
        if (pinInput) pinInput.value = pin;
        if (!/^[1-9][0-9]{5}$/.test(pin)) {
            pinOk = false;
            setLocation('', '');
            setFormLocked(true);
            setPinMsg('Enter a valid 6-digit Indian pincode.', false);
            return Promise.resolve(false);
        }
        if (pinChecking) return Promise.resolve(pinOk);
        pinChecking = true;
        setPinMsg('Checking availability…', false);
        if (pinMsg) pinMsg.classList.remove('is-bad');

        // Root-relative: this page is served at /lab/package/{slug}, so a
        // relative 'api/…' would resolve to /lab/package/api/… and 404.
        return fetch('/api/lab_pincode.php?pin=' + pin, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (data) {
                if (data && data.serviceable) {
                    pinOk = true;
                    setLocation(data.city, data.state);
                    setFormLocked(false);
                    setPinMsg('Home collection available in ' + data.city + ', ' + data.state + '.', true);
                } else {
                    pinOk = false;
                    setLocation('', '');
                    setFormLocked(true);
                    setPinMsg('Sorry, home collection is not available for this pincode yet.', false);
                }
                return pinOk;
            })
            .catch(function () {
                pinOk = false;
                setLocation('', '');
                setFormLocked(true);
                setPinMsg('Could not check right now. Please try again.', false);
                return false;
            })
            .finally(function () { pinChecking = false; });
    }

    function addonTotal() {
        var sum = 0;
        form.querySelectorAll('input[name="addons[]"]:checked').forEach(function (cb) {
            sum += parseInt(cb.getAttribute('data-price') || '0', 10) || 0;
        });
        return sum;
    }

    function formatInr(n) {
        return '₹' + Math.round(n).toLocaleString('en-IN');
    }

    // Applied coupon state. couponOff is recomputed as a % of the package line
    // inside updateTotals(), so it scales with the number of persons.
    var appliedCouponPct = 0;
    var appliedCouponCode = '';
    var couponOff = 0;

    // Last total computed by updateTotals(), in rupees. Sent with the booking
    // purely so the server can log a mismatch against its own recomputation —
    // it is never the amount billed.
    var lastTotal = 0;

    // The coupon's headline rate and this package's own ceiling. Each line caps
    // the headline rate for itself (see lineDiscount) so an add-on is never
    // discounted beyond ITS max_discount_pct, and never held back by the
    // package's ceiling either. Mirrors ecp_lab_line_discount_pct() in PHP.
    var couponHeadlinePct = 0;
    var pkgMaxPct = 0;

    function lineDiscount(lineTotal, maxPct) {
        var pct = Math.max(0, Math.min(couponHeadlinePct, maxPct || 0));
        return pct <= 0 ? 0 : Math.round(lineTotal * pct / 100);
    }

    function couponAmount(pkgLine) {
        return lineDiscount(pkgLine, pkgMaxPct);
    }

    function clearCoupon() {
        appliedCouponPct = 0;
        appliedCouponCode = '';
        couponHeadlinePct = 0;
        pkgMaxPct = 0;
        couponOff = 0;
        if (couponInput) couponInput.value = '';
        if (couponsWrap) couponsWrap.querySelectorAll('.ldp-bf-coupon.is-applied')
            .forEach(function (c) { c.classList.remove('is-applied'); });
        if (couponMsg) { couponMsg.hidden = true; couponMsg.textContent = ''; }
    }

    // Apply a predefined percentage coupon. Requires the patient to be logged in;
    // logged-out users get the shared auth modal instead.
    function applyCouponChip(row) {
        if (!row) return;
        var loggedIn = couponsWrap && couponsWrap.getAttribute('data-logged-in') === '1';
        if (!loggedIn) {
            window.dispatchEvent(new CustomEvent('ecp:open-auth', { detail: { reason: 'lab_coupon' } }));
            return;
        }
        var code = (row.getAttribute('data-code') || '').toUpperCase();
        var pct = parseInt(row.getAttribute('data-pct') || '0', 10) || 0;

        // Toggle off if the same coupon is tapped again.
        if (appliedCouponCode === code) { clearCoupon(); updateTotals(); return; }

        appliedCouponPct = pct;
        appliedCouponCode = code;
        // Headline rate drives per-line capping; pct is this package's capped rate.
        couponHeadlinePct = parseInt(row.getAttribute('data-headline-pct') || '0', 10) || pct;
        pkgMaxPct = parseInt(row.getAttribute('data-pkg-max-pct') || '0', 10) || pct;
        if (couponInput) couponInput.value = code;

        if (couponsWrap) couponsWrap.querySelectorAll('.ldp-bf-coupon')
            .forEach(function (c) { c.classList.toggle('is-applied', c === row); });

        if (couponMsg) {
            couponMsg.hidden = false;
            couponMsg.className = 'ldp-bf-coupon-msg is-ok';
            // "on this package", not a blanket promise: added tests carry their
            // own lower ceilings and are discounted at those rates.
            couponMsg.textContent = 'Coupon ' + code + ' applied: ' + pct + '% off this package';
        }
        updateTotals();
    }

    // Each chosen add-on test as {label, price} (unit price, before persons).
    function addonItems() {
        var items = [];
        form.querySelectorAll('input[name="addons[]"]:checked').forEach(function (cb) {
            var chip = cb.closest('.ldp-bf-testchip');
            var name = chip && chip.querySelector('.ldp-bf-testchip-name');
            items.push({
                label: name ? name.textContent : 'Added test',
                price: parseInt(cb.getAttribute('data-price') || '0', 10) || 0,
                maxPct: parseInt(cb.getAttribute('data-max-pct') || '0', 10) || 0
            });
        });
        return items;
    }

    function updateTotals() {
        var p = persons();
        var mrpLine = Math.max(pkgMrp, pkgPrice) * p;
        var pkgLine = pkgPrice * p;
        var items   = addonItems();
        var addons  = addonTotal() * p;
        var hard    = (hardCopy && hardCopy.checked) ? hardFee : 0;

        // The coupon is applied PER LINE, each capped by that product's own
        // max_discount_pct: the package at its ceiling, every add-on at its own.
        // Summing per-line keeps a 5%-capped test from receiving a 25% package
        // discount (which the lab partner never agreed to) without dragging the
        // package down to 5% either. Mirrors ecp_lab_price_order() in PHP.
        couponOff = couponAmount(pkgLine);
        items.forEach(function (it) {
            couponOff += lineDiscount(it.price * p, it.maxPct);
        });

        // ── Home collection fee ─────────────────────────────────────────
        // Thyrocare bills ₹200 when the order value is under ₹300; we pass it
        // through at cost.
        //
        // The threshold is tested on the value of the TESTS (package + add-ons
        // × persons) AFTER the member discount — that's what the order actually
        // settles at, which is what Thyrocare bills on. So ₹325 of tests minus a
        // 25% coupon = ₹244, which is under ₹300 and DOES attract the ₹200.
        //
        // The courier fee is excluded from this test: it's a separate service
        // charge, not order value.
        var orderValue = pkgLine + addons;
        if (collectionOnDiscounted) orderValue -= couponOff;
        var collection = (collectionMin > 0 && orderValue < collectionMin) ? collectionFee : 0;

        // FEES ARE NEVER DISCOUNTED. couponOff comes from the discountable lines
        // only, so adding `collection` and `hard` here — after the subtraction —
        // means the ₹200 and the ₹75 are always paid in full.
        var total = Math.max(0, pkgLine + addons - couponOff + collection + hard);
        lastTotal = total;

        // ── One savings figure, one base ────────────────────────────────
        // These three numbers used to be measured against three different
        // bases, which is why an order could show "25% off", "Save ₹6,842" and
        // "31% OFF" all at once and look broken:
        //   - the chip advertised the coupon's headline rate
        //   - "Save" added the MRP markdown on top of the coupon
        //   - the sticky % divided that by the PACKAGE mrp, ignoring add-ons
        //
        // Now everything derives from the same pair: `listValue` (what the whole
        // order would cost undiscounted) and `discount` (what came off it), so
        // savings ÷ listValue is the percentage actually shown everywhere.
        // Fees are excluded from both — they're never discountable.
        var listValue = Math.max(mrpLine, pkgLine) + addons;
        var discount = (mrpLine - pkgLine) + couponOff;
        var elMrp = document.getElementById('ldpBfMrp');
        var elDisc = document.getElementById('ldpBfDiscount');
        var elPay = document.getElementById('ldpBfYouPay');
        if (elMrp) elMrp.textContent = formatInr(mrpLine);
        if (elDisc) elDisc.textContent = '- ' + formatInr(Math.max(0, discount));
        if (elPay) elPay.textContent = formatInr(total);

        // Collection-charge line + the "add ₹X more" nudge. Showing the gap is
        // the point: a patient one ₹60 test away from free collection will
        // usually add it, which is a better outcome than a silent ₹200.
        var elCollection = document.getElementById('ldpBfSumCollection');
        var elCollHint = document.getElementById('ldpBfCollectionHint');
        if (elCollection) {
            elCollection.textContent = collection > 0 ? formatInr(collection) : 'Free';
            elCollection.classList.toggle('is-charged', collection > 0);
        }
        if (elCollHint) {
            if (collection > 0) {
                // Gap to the threshold, quoted in "more tests to add".
                //
                // Add-ons ARE discountable now (each at its own cap), so ₹X of
                // added tests raises the post-discount order value by less than
                // ₹X. Gross up by the highest rate a new line could receive —
                // the headline coupon — so the advice never falls short. It may
                // over-state slightly for a low-capped test, which is the safe
                // direction to be wrong. Rounded up to ₹10 so it reads as
                // guidance rather than false precision.
                var rawGap = Math.max(0, collectionMin - orderValue);
                var grossUp = Math.max(0, Math.min(100, couponHeadlinePct));
                if (grossUp > 0 && grossUp < 100) rawGap = rawGap / (1 - grossUp / 100);
                var gap = Math.ceil(rawGap / 10) * 10;
                elCollHint.textContent = 'Orders under ' + formatInr(collectionMin)
                    + ' carry a ' + formatInr(collectionFee) + ' home-collection charge'
                    + (appliedCouponPct > 0 ? ' (checked after your discount)' : '') + '. '
                    + 'Add about ' + formatInr(gap) + ' more in tests to get collection free.';
                elCollHint.hidden = false;
            } else {
                elCollHint.hidden = true;
            }
        }

        // The "fees aren't discountable" note only matters when a fee is on the
        // bill — showing it on a clean ₹899 package is just noise.
        var elFeeNote = document.getElementById('ldpBfFeeNote');
        if (elFeeNote) elFeeNote.hidden = (collection <= 0 && hard <= 0);

        // ── Itemised order summary ──────────────────────────────────────
        var elSumPkg = document.getElementById('ldpBfSumPkg');
        if (elSumPkg) elSumPkg.textContent = formatInr(pkgLine);

        // Add-on test lines (one per chosen test, price scaled by persons).
        var addonsBox = document.getElementById('ldpBfSumAddons');
        if (addonsBox) {
            addonsBox.innerHTML = '';
            items.forEach(function (it) {
                var row = document.createElement('div');
                row.className = 'ldp-bf-row';
                var name = document.createElement('span');
                name.className = 'ldp-bf-sum-addon';
                name.textContent = '+ ' + it.label + (p > 1 ? ' × ' + p : '');
                var val = document.createElement('span');
                val.className = 'ldp-bf-val';
                val.textContent = formatInr(it.price * p);
                row.appendChild(name);
                row.appendChild(val);
                addonsBox.appendChild(row);
            });
        }

        // Courier row (only when hard copy is ticked).
        var courierRow = document.getElementById('ldpBfSumCourierRow');
        var courierVal = document.getElementById('ldpBfSumCourier');
        if (courierRow) courierRow.hidden = (hard <= 0);
        if (courierVal) courierVal.textContent = formatInr(hardFee);

        // Discount row (only when a coupon is applied).
        var discRow = document.getElementById('ldpBfSumDiscountRow');
        var discLbl = document.getElementById('ldpBfSumDiscountLabel');
        var discVal = document.getElementById('ldpBfSumDiscount');
        if (discRow) discRow.hidden = (couponOff <= 0);
        if (discLbl && appliedCouponCode) {
            // Say "up to X%" when the order mixes ceilings, so the row never
            // claims a flat rate the amount beside it doesn't match. (₹5,579 on
            // a ₹23k order is not 25% of everything — the add-ons were capped
            // lower — and labelling it "25%" is what made the bill look wrong.)
            var rates = [];
            if (pkgLine > 0 && Math.min(couponHeadlinePct, pkgMaxPct) > 0) {
                rates.push(Math.min(couponHeadlinePct, pkgMaxPct));
            }
            items.forEach(function (it) {
                var r = Math.min(couponHeadlinePct, it.maxPct || 0);
                if (r > 0 && rates.indexOf(r) === -1) rates.push(r);
            });
            var mixed = rates.length > 1;
            discLbl.textContent = 'Discount (' + appliedCouponCode
                + (mixed ? ', up to ' + Math.max.apply(null, rates) + '%' : '') + ')';
        }
        if (discVal) discVal.textContent = '- ' + formatInr(couponOff);

        var elSumTotal = document.getElementById('ldpBfSumTotal');
        var elStickyTotal = document.getElementById('ldpStickyTotal');
        if (elSumTotal) elSumTotal.textContent = formatInr(total);
        if (elStickyTotal) elStickyTotal.textContent = formatInr(total);

        // Sticky "% OFF" badge — savings over the WHOLE order's list value, the
        // same base as the "Save ₹X" button, so the two can never disagree.
        var saved = Math.max(0, discount);
        var savedPct = listValue > 0 ? Math.round((saved / listValue) * 100) : 0;

        var elStickyOff = document.getElementById('ldpStickyOff');
        if (elStickyOff) {
            elStickyOff.textContent = savedPct + '% OFF';
            elStickyOff.hidden = (savedPct <= 0);
        }

        // "Save ₹X" on the Book Now button.
        var elSave = document.getElementById('ldpBfSubmitSave');
        if (elSave) {
            elSave.textContent = 'Save ' + formatInr(saved);
            elSave.hidden = (saved <= 0);
        }

        // Savings line in the summary, stated with its base so "Save ₹6,842"
        // is checkable rather than a number the patient has to take on trust.
        var elSavedRow = document.getElementById('ldpBfSumSavedRow');
        var elSavedVal = document.getElementById('ldpBfSumSaved');
        var elSavedNote = document.getElementById('ldpBfSumSavedNote');
        if (elSavedRow) elSavedRow.hidden = (saved <= 0);
        if (elSavedVal) elSavedVal.textContent = formatInr(saved);
        if (elSavedNote) {
            elSavedNote.textContent = saved > 0
                ? 'That’s ' + savedPct + '% off the ' + formatInr(listValue) + ' list price for this order.'
                : '';
            elSavedNote.hidden = (saved <= 0);
        }
    }

    if (minusBtn) minusBtn.addEventListener('click', function () { setPersons(persons() - 1); });
    if (plusBtn) plusBtn.addEventListener('click', function () { setPersons(persons() + 1); });

    if (pinCheck) pinCheck.addEventListener('click', checkPincode);
    if (pinInput) {
        pinInput.addEventListener('input', function () {
            pinOk = false;
            setPinMsg('', false);
            setLocation('', '');
            setFormLocked(true);
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
        pinInput.addEventListener('blur', function () {
            if (this.value.length === 6) checkPincode();
        });
    }

    form.addEventListener('change', function (ev) {
        var t = ev.target;
        if (t && (t.name === 'addons[]' || t.id === 'ldpBfHardCopy')) updateTotals();
    });

    if (couponsWrap) {
        couponsWrap.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.ldp-bf-coupon-apply');
            if (!btn) return;
            ev.preventDefault();
            applyCouponChip(btn.closest('.ldp-bf-coupon'));
        });
    }

    // ── Add-more-tests search picker ──────────────────────────────────────
    var resetAddons = function () {};   // assigned by the picker IIFE below
    var addTestById = function () {};   // ditto — used to restore a saved draft
    (function initAddonSearch() {
        var input   = document.getElementById('ldpAddonInput');
        var results = document.getElementById('ldpAddonResults');
        var chips   = document.getElementById('ldpAddonChips');
        var dataEl  = document.getElementById('ldpAddonData');
        if (!input || !results || !chips || !dataEl) return;

        var catalog = [];
        try { catalog = JSON.parse(dataEl.textContent || '[]') || []; } catch (e) { catalog = []; }
        var chosen = Object.create(null);   // id -> true, so a test can't be added twice
        var activeIdx = -1;                 // keyboard-highlighted result

        function inr(n) { return '₹' + Math.round(n).toLocaleString('en-IN'); }

        function matches(q) {
            q = q.trim().toLowerCase();
            var pool = catalog.filter(function (t) { return !chosen[t.id]; });
            if (!q) return pool.slice(0, 8);
            return pool.filter(function (t) {
                return t.label.toLowerCase().indexOf(q) !== -1;
            }).slice(0, 8);
        }

        function closeResults() {
            results.hidden = true;
            results.innerHTML = '';
            activeIdx = -1;
            input.setAttribute('aria-expanded', 'false');
        }

        function renderResults() {
            var list = matches(input.value);
            if (!list.length) {
                results.innerHTML = '<li class="ldp-bf-testsearch-empty" role="presentation">No matching tests</li>';
                results.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                activeIdx = -1;
                return;
            }
            results.innerHTML = list.map(function (t, i) {
                return '<li class="ldp-bf-testsearch-opt" role="option" data-id="' + t.id + '"' +
                       (i === activeIdx ? ' aria-selected="true"' : '') + '>' +
                       '<span class="ldp-bf-testsearch-name"></span>' +
                       '<span class="ldp-bf-testsearch-price">' + inr(t.price) + '</span></li>';
            }).join('');
            // Fill names via textContent to avoid any HTML injection from labels.
            Array.prototype.forEach.call(results.children, function (li, i) {
                li.querySelector('.ldp-bf-testsearch-name').textContent = list[i].label;
            });
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function addTest(id) {
            var t = catalog.find(function (x) { return x.id === id; });
            if (!t || chosen[id]) return;
            chosen[id] = true;

            var chip = document.createElement('div');
            chip.className = 'ldp-bf-testchip';
            chip.setAttribute('data-id', id);
            chip.innerHTML =
                '<input type="checkbox" name="addons[]" checked hidden ' +
                       'value="' + id + '" data-price="' + t.price + '" ' +
                       'data-max-pct="' + (t.max_pct || 0) + '">' +
                '<span class="ldp-bf-testchip-name"></span>' +
                '<span class="ldp-bf-testchip-price">' + inr(t.price) + '</span>' +
                '<button type="button" class="ldp-bf-testchip-x" aria-label="Remove">×</button>';
            chip.querySelector('.ldp-bf-testchip-name').textContent = t.label;
            chips.appendChild(chip);

            input.value = '';
            closeResults();
            input.focus();
            updateTotals();
        }

        function removeTest(id) {
            var chip = chips.querySelector('.ldp-bf-testchip[data-id="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]');
            if (chip) chip.remove();
            delete chosen[id];
            updateTotals();
        }

        input.addEventListener('input', function () { activeIdx = -1; renderResults(); });
        input.addEventListener('focus', function () { renderResults(); });

        input.addEventListener('keydown', function (ev) {
            var opts = results.querySelectorAll('.ldp-bf-testsearch-opt');
            if (ev.key === 'ArrowDown') {
                ev.preventDefault();
                if (!opts.length) { renderResults(); return; }
                activeIdx = Math.min(activeIdx + 1, opts.length - 1);
                renderResults();
            } else if (ev.key === 'ArrowUp') {
                ev.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                renderResults();
            } else if (ev.key === 'Enter') {
                if (activeIdx >= 0 && opts[activeIdx]) {
                    ev.preventDefault();
                    addTest(opts[activeIdx].getAttribute('data-id'));
                }
            } else if (ev.key === 'Escape') {
                closeResults();
            }
        });

        results.addEventListener('mousedown', function (ev) {
            // mousedown (not click) so it fires before the input's blur closes the list.
            var opt = ev.target.closest('.ldp-bf-testsearch-opt');
            if (!opt) return;
            ev.preventDefault();
            addTest(opt.getAttribute('data-id'));
        });

        chips.addEventListener('click', function (ev) {
            var x = ev.target.closest('.ldp-bf-testchip-x');
            if (!x) return;
            removeTest(x.closest('.ldp-bf-testchip').getAttribute('data-id'));
        });

        document.addEventListener('click', function (ev) {
            if (!ev.target.closest('#ldpAddonSearch')) closeResults();
        });

        // Let the form-reset flow clear chosen add-ons.
        resetAddons = function () {
            chips.innerHTML = '';
            chosen = Object.create(null);
            input.value = '';
            closeResults();
        };

        // Lets restoreDraft() re-add the tests the patient had picked.
        addTestById = addTest;
    })();

    // Normalise as they type/paste so "+91 98765 43210" becomes "9876543210"
    // in place, instead of being truncated by maxlength into an invalid number.
    var phoneField = document.getElementById('ldpBfPhone');
    if (phoneField) {
        var fixPhone = function () {
            var cleaned = normalisePhone(phoneField.value);
            if (phoneField.value !== cleaned) phoneField.value = cleaned;
        };
        phoneField.addEventListener('input', fixPhone);
        phoneField.addEventListener('paste', function () { setTimeout(fixPhone, 0); });
        phoneField.addEventListener('blur', fixPhone);
    }

    var loginBtn = document.getElementById('ldpBfLogin');
    if (loginBtn) {
        loginBtn.addEventListener('click', function () {
            window.dispatchEvent(new CustomEvent('ecp:open-auth', {
                detail: { reason: loginBtn.getAttribute('data-auth-reason') || 'lab_book' }
            }));
        });
    }

    // ── Surviving the login reload ──────────────────────────────────────
    // The shared auth modal calls location.reload() after a successful OTP so
    // the page re-renders logged-in (identity chip + coupon unlocked). That
    // wiped everything the patient had typed — they came back to an empty form
    // and had to start again, which is exactly when people give up.
    //
    // So: snapshot the form to sessionStorage before any reload that we can
    // see coming, and restore it on load. sessionStorage (not local) because
    // this is one tab's in-progress booking, and it holds a name, phone,
    // address and health choices — it should not outlive the tab.
    var DRAFT_KEY = 'ecp_lab_draft_' + (form.getAttribute('data-pkg-slug') || '');

    function saveDraft() {
        try {
            var names = [], ages = [], genders = [];
            form.querySelectorAll('input[name="beneficiary_name[]"]').forEach(function (el) { names.push(el.value); });
            form.querySelectorAll('input[name="beneficiary_age[]"]').forEach(function (el) { ages.push(el.value); });
            form.querySelectorAll('select[name="beneficiary_gender[]"]').forEach(function (el) { genders.push(el.value); });

            var addons = [];
            form.querySelectorAll('input[name="addons[]"]:checked').forEach(function (cb) { addons.push(cb.value); });

            var v = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };
            sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
                t: Date.now(),
                pincode: v('ldpBfPincode'), city: v('ldpBfCity'), state: v('ldpBfState'),
                email: v('ldpBfEmail'), phone: v('ldpBfPhone'), address: v('ldpBfAddress'),
                date: v('ldpBfDate'), slot: v('ldpBfTime'), notes: v('ldpBfNotes'),
                hard: !!(hardCopy && hardCopy.checked),
                persons: persons(), names: names, ages: ages, genders: genders,
                addons: addons, pinOk: pinOk
            }));
        } catch (e) {}
    }

    function clearDraft() {
        try { sessionStorage.removeItem(DRAFT_KEY); } catch (e) {}
    }

    function restoreDraft() {
        var raw = null;
        try { raw = sessionStorage.getItem(DRAFT_KEY); } catch (e) { return; }
        if (!raw) return;

        var d;
        try { d = JSON.parse(raw); } catch (e) { clearDraft(); return; }
        // Stale drafts are worse than none: prices and slots move on.
        if (!d || !d.t || (Date.now() - d.t) > 60 * 60 * 1000) { clearDraft(); return; }

        var set = function (id, val) {
            var el = document.getElementById(id);
            if (el && val) el.value = val;
        };

        // Rebuild the beneficiary rows first so their inputs exist to fill.
        if (d.persons && d.persons > 1) setPersons(d.persons);

        (d.names || []).forEach(function (v, i) {
            var el = form.querySelectorAll('input[name="beneficiary_name[]"]')[i];
            if (el) el.value = v;
        });
        (d.ages || []).forEach(function (v, i) {
            var el = form.querySelectorAll('input[name="beneficiary_age[]"]')[i];
            if (el) el.value = v;
        });
        (d.genders || []).forEach(function (v, i) {
            var el = form.querySelectorAll('select[name="beneficiary_gender[]"]')[i];
            if (el) el.value = v;
        });

        set('ldpBfPincode', d.pincode);
        set('ldpBfEmail', d.email);
        set('ldpBfPhone', d.phone);
        set('ldpBfAddress', d.address);
        set('ldpBfTime', d.slot);
        set('ldpBfNotes', d.notes);
        if (hardCopy) hardCopy.checked = !!d.hard;

        // Only restore a date that is still inside the (re-derived) window.
        if (d.date && d.date >= bookMinDate && d.date <= bookMaxDate) {
            set('ldpBfDate', d.date);
        }

        (d.addons || []).forEach(function (id) { addTestById(id); });

        // Re-run the real pincode check rather than trusting the stored flag —
        // it is what unlocks the form, and serviceability may have changed.
        if (d.pinOk && d.pincode) {
            checkPincode();
        }

        updateTotals();
        clearDraft();
    }

    // Save before the auth modal reloads the page, and on any unload.
    window.addEventListener('ecp:open-auth', saveDraft);
    window.addEventListener('beforeunload', function () {
        // Don't resurrect a form the patient already submitted successfully.
        if (!document.getElementById('ldpBfSuccess') ||
            document.getElementById('ldpBfSuccess').hidden) {
            saveDraft();
        }
    });
    if (loginBtn) loginBtn.addEventListener('click', saveDraft);

    // Highlight focused fields (as in reference)
    form.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('focus', function () {
            form.querySelectorAll('.is-highlight').forEach(function (h) { h.classList.remove('is-highlight'); });
            var target = el.closest('.ldp-bf-qty') || el.closest('.ldp-bf-pinrow') || el.closest('.ldp-bf-icon-field') || el.closest('.ldp-bf-select-wrap') || el;
            target.classList.add('is-highlight');
        });
    });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        updateTotals();

        if (!pinOk) {
            checkPincode();
            formError('Please check pincode availability before booking.', pinInput);
            return;
        }
        if (!dateInput || !dateInput.value) {
            formError('Please select preferred appointment date.', dateInput);
            return;
        }
        // Re-derive the window at submit time: the cut-off may have passed
        // while the form was being filled in.
        refreshDateWindow();
        if (!dateInput.value) {
            formError('That date is no longer available. Collection now starts '
                + bookMinDate + '. Please pick a new date.', dateInput);
            return;
        }
        if (dateInput.value < bookMinDate || dateInput.value > bookMaxDate) {
            formError('Please choose a collection date between ' + bookMinDate
                + ' and ' + bookMaxDate + '.', dateInput);
            return;
        }
        var time = document.getElementById('ldpBfTime');
        if (!time || !time.value) {
            formError('Please select a time slot.', time);
            return;
        }
        // normalisePhone() strips a +91/91/0 prefix and keeps the 10 national
        // digits — so "+919374249xx", "0937…" and "937…" all validate the same.
        var phone = document.getElementById('ldpBfPhone');
        var phoneVal = normalisePhone(phone && phone.value);
        if (phone) phone.value = phoneVal;
        if (!/^[6-9][0-9]{9}$/.test(phoneVal)) {
            formError('Enter a valid 10-digit mobile number (without +91).', phone);
            return;
        }
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        clearFormError();

        var pkgName = form.getAttribute('data-pkg-name') || 'Lab package';

        // Reset the form only after the server has accepted the booking —
        // clearing it on a failure would throw away everything the patient typed.
        function resetForm() {
            form.reset();
            clearCoupon();
            resetAddons();
            pinOk = false;
            setPinMsg('', false);
            setLocation('', '');
            setFormLocked(true);
            if (beneficiaries) beneficiaries.innerHTML = personBlockHtml(1);
            setPersons(1);
            updateTotals();
        }

        function payload() {
            var names = [], ages = [], genders = [];
            form.querySelectorAll('input[name="beneficiary_name[]"]').forEach(function (el) { names.push(el.value); });
            form.querySelectorAll('input[name="beneficiary_age[]"]').forEach(function (el) { ages.push(el.value); });
            form.querySelectorAll('select[name="beneficiary_gender[]"]').forEach(function (el) { genders.push(el.value); });

            // Slugs only — the server re-prices every add-on from the database.
            var addons = [];
            form.querySelectorAll('input[name="addons[]"]:checked').forEach(function (cb) {
                addons.push(cb.value);
            });

            var val = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };

            return {
                product_slug: form.getAttribute('data-pkg-slug') || '',
                email: val('ldpBfEmail'),
                phone: val('ldpBfPhone'),
                pincode: val('ldpBfPincode'),
                city: val('ldpBfCity'),
                state: val('ldpBfState'),
                address: val('ldpBfAddress'),
                appointment_date: val('ldpBfDate'),
                time_slot: val('ldpBfTime'),
                notes: val('ldpBfNotes'),
                hard_copy: !!(hardCopy && hardCopy.checked),
                coupon: (couponInput && couponInput.value) || '',
                beneficiary_name: names,
                beneficiary_age: ages,
                beneficiary_gender: genders,
                addons: addons,
                // Diagnostic only: the server bills from its own recomputation
                // and just logs a warning if these disagree.
                client_total: lastTotal
            };
        }

        var submitBtn = form.querySelector('.ldp-bf-submit');
        var submitLabel = form.querySelector('.ldp-bf-submit-label');
        var submitting = false;

        // Swap the form for the confirmation panel. Capturing the slot text
        // BEFORE resetForm() matters — the reset blanks the inputs we read.
        function showSuccess(res) {
            var whenDate = dateInput ? dateInput.value : '';
            var whenSlot = (document.getElementById('ldpBfTime') || {}).value || '';
            var whenText = whenDate
                ? (function () {
                    var p = whenDate.split('-');
                    var dt = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
                    return dt.toLocaleDateString('en-IN', {
                        weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC'
                    }) + (whenSlot ? ' · ' + whenSlot : '');
                })()
                : whenSlot;

            var panel = document.getElementById('ldpBfSuccess');
            var card = document.getElementById('ldpBfCard');
            var set = function (id, text) {
                var el = document.getElementById(id);
                if (el) el.textContent = text;
            };

            set('ldpBfSuccessRef', res.order_ref || '');
            set('ldpBfSuccessPkg', pkgName);
            set('ldpBfSuccessWhen', whenText);
            set('ldpBfSuccessTotal', res.total || '');
            set('ldpBfSuccessSub', res.email_sent
                ? 'A confirmation has been emailed to ' + (res.email || 'your inbox')
                  + '. We’ll call to confirm your slot and send a payment link.'
                : 'We’ll call to confirm your slot and send a payment link. '
                  + 'Please save your reference number below.');

            clearFormError();
            clearDraft();   // the booking is placed; never restore it again
            resetForm();

            if (card) card.hidden = true;
            if (panel) {
                panel.hidden = false;
                try { panel.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) {}
            }

            // The sticky bar still advertises "Book Now" for an order already
            // placed — hide it so the confirmation is the only call to action.
            var sticky = document.getElementById('ldpSticky');
            if (sticky) sticky.hidden = true;
        }

        var bookAnother = document.getElementById('ldpBfBookAnother');
        if (bookAnother) {
            bookAnother.addEventListener('click', function () {
                var panel = document.getElementById('ldpBfSuccess');
                var card = document.getElementById('ldpBfCard');
                var sticky = document.getElementById('ldpSticky');
                if (panel) panel.hidden = true;
                if (card) card.hidden = false;
                if (sticky) sticky.hidden = false;
                refreshDateWindow();
                try { card.scrollIntoView({ block: 'start', behavior: 'smooth' }); } catch (e) {}
            });
        }

        function send() {
            if (submitting) return;
            submitting = true;
            if (submitBtn) submitBtn.disabled = true;
            if (submitLabel) submitLabel.textContent = 'Booking…';

            fetch('/api/lab_book.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload())
            })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (res) {
                if (res && res.ok) {
                    showSuccess(res);
                    return;
                }
                if (res && res.error === 'login_required') {
                    // Session expired between the gate and the POST.
                    window.dispatchEvent(new CustomEvent('ecp:open-auth', { detail: { reason: 'lab_book' } }));
                    formError('Please log in to complete your booking.');
                    return;
                }
                formError((res && res.message) || 'Could not save your booking. Please try again.');
            })
            .catch(function () {
                formError('Network error — please check your connection and try again.');
            })
            .finally(function () {
                submitting = false;
                if (submitBtn) submitBtn.disabled = false;
                if (submitLabel) submitLabel.textContent = 'Book Now';
            });
        }

        if (window.ecpAuth && typeof window.ecpAuth.require === 'function') {
            window.ecpAuth.require('lab_booking', send);
        } else {
            send();
        }
    });

    syncBeneficiaries();
    updateTotals();
    setFormLocked(true); // gate everything until a serviceable pincode is confirmed
    restoreDraft();      // bring back anything typed before a login reload
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
