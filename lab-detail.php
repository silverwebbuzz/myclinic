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

// Note: the test-count is already shown as a green pill in .ldp-hero-meta, so
// we intentionally do NOT repeat it here as a grey tag.
$detailTags = [];
if ($fastingCode === 'CF') {
    $detailTags[] = ['fi-rr-clock', 'Fasting Required (8–10 hrs)'];
} elseif ($fastingCode === 'NF') {
    $detailTags[] = ['fi-rr-check', 'No Fasting Required'];
}
$detailTags[] = ['fi-rr-file-medical', 'Digital Reports'];
$detailTags[] = ['fi-rr-house-blank', 'Free Home Collection'];
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
    $whyReasons[] = ['fi-rr-house-blank', 'Free home sample collection',
        'A trained phlebotomist collects your sample at your doorstep.'];
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
    ['fi-rr-doctor', 'Doctor Review', 'Free consultation with report review'],
];

$highlightsBar = [
    [$params . ' Tests Included', 'tests'],
    ['Digital Reports', 'clock'],
    ['NABL Accredited Labs', 'lab'],
    ['Free Home Collection', 'home'],
    ['Powered by Thyrocare', 'doc'],
];

$testimonials = [
    ['Priya S.', 'Mumbai', 'Reports came the next morning and the doctor consult helped me understand my sugar trends clearly.'],
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
                    <li>Free Home Collection</li>
                    <li>NABL Accredited Labs</li>
                    <li>Reports in 24 Hours</li>
                    <li>Free Doctor Consultation</li>
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
                ['Are home collection charges included?', 'Home sample collection is free with this booking on eClinicPro (preview — live fees confirmed at launch).'],
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
                <div class="ldp-bf">
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
                          data-hardcopy="75">

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
                            <button type="button" class="ldp-bf-login" id="ldpBfLogin" data-auth-reason="lab_book">
                                <span class="ldp-bf-login-ico" aria-hidden="true"><i class="fi fi-rr-user"></i></span>
                                <span class="ldp-bf-login-txt">
                                    <strong>Login / Sign up</strong>
                                    <small>Login to unlock extra discount</small>
                                </span>
                                <span class="ldp-bf-login-arrow" aria-hidden="true"><i class="fi fi-rr-angle-right"></i></span>
                            </button>
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
                            <input type="tel" id="ldpBfPhone" name="phone" placeholder="Phone" required inputmode="tel" maxlength="10" pattern="[6-9][0-9]{9}" autocomplete="tel">

                            <label class="ldp-bf-label" for="ldpBfAddress">Complete Address</label>
                            <textarea id="ldpBfAddress" name="address" rows="3" placeholder="Complete Address" required autocomplete="street-address"></textarea>
                            <p class="ldp-bf-note-danger">Note: Order with incomplete address will be rejected.</p>

                            <hr class="ldp-bf-divider">

                            <label class="ldp-bf-label">Preferred Date &amp; Time</label>
                            <div class="ldp-bf-datetime">
                                <div class="ldp-bf-icon-field">
                                    <input type="date" id="ldpBfDate" name="appointment_date" required aria-label="Select Preferred Appointment Date">
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
                            $couponPct  = $loginPct;
                            $couponCode = 'SAVE' . $couponPct;
                            ?>
                            <label class="ldp-bf-label">Coupon Code <span>(Optional)</span></label>
                            <div class="ldp-bf-coupons" id="ldpBfCoupons" data-logged-in="<?= $ecpPatient ? '1' : '0' ?>">
                                <div class="ldp-bf-coupon" data-code="<?= e($couponCode) ?>" data-pct="<?= (int) $couponPct ?>">
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
                            $addonJson = array_map(static fn ($a) => [
                                'id'    => (string) $a['id'],
                                'label' => (string) $a['label'],
                                'price' => (int) $a['price'],
                            ], $addonTests);
                            ?>
                            <script type="application/json" id="ldpAddonData"><?= json_encode($addonJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

                            <label class="ldp-bf-hardcopy">
                                <input type="checkbox" name="hard_copy" id="ldpBfHardCopy" value="1">
                                <span>Please tick to receive hard copy. Courier charges <b>Rs. 75 Extra.</b></span>
                            </label>
                        </div>

                        <button type="submit" class="ldp-bf-submit">Book Now</button>

                        <div class="ldp-bf-paylater">
                            <span class="ldp-bf-paylater-ico" aria-hidden="true"><i class="fi fi-rr-wallet"></i></span>
                            <div class="ldp-bf-paylater-txt">
                                <strong class="ldp-bf-paylater-title">Book Now, Pay Later!</strong>
                                <span class="ldp-bf-paylater-sub">You will get a payment link shortly. You can make the payment ONLINE (<b>Recommended</b>) using that link or pay using UPI/Cash to the technician.</span>
                            </div>
                        </div>

                        <div class="ldp-bf-summary">
                            <div class="ldp-bf-row">
                                <span>Test Package Price</span>
                                <span class="ldp-bf-val" id="ldpBfSumPkg">₹<?= number_format($pkgPriceNum) ?></span>
                            </div>
                            <div class="ldp-bf-row">
                                <span>Home Collection Charges</span>
                                <span class="ldp-bf-val">Free</span>
                            </div>
                            <div class="ldp-bf-row ldp-bf-row-total">
                                <span>Total Amount</span>
                                <strong id="ldpBfSumTotal">₹<?= number_format($pkgPriceNum) ?></strong>
                            </div>
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
        <p>Ready to take control of your health? Book <strong><?= e($d['title']) ?></strong> today &amp; get expert insights.</p>
        <div class="ldp-sticky-actions">
            <?php if ($isPkg && $price !== ''): ?>
            <div class="ldp-sticky-price">
                <strong id="ldpStickyTotal">₹<?= e($price) ?></strong>
                <?php if ($off !== ''): ?><span id="ldpStickyOff"><?= e($off) ?>% OFF</span><?php endif; ?>
            </div>
            <a href="#ldpBookForm" class="ldp-btn ldp-btn-light">Book Now</a>
            <?php else: ?>
            <a href="/lab#lab-packages" class="ldp-btn ldp-btn-light">Browse Packages</a>
            <?php endif; ?>
            <a href="/contact" class="ldp-btn ldp-btn-outline-light">Talk to Expert</a>
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

    if (dateInput) {
        var t = new Date();
        dateInput.min = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
    }

    function persons() {
        return Math.max(1, Math.min(10, parseInt((personsInput && personsInput.value) || '1', 10) || 1));
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

    function couponAmount(pkgLine) {
        return Math.round(pkgLine * appliedCouponPct / 100);
    }

    function clearCoupon() {
        appliedCouponPct = 0;
        appliedCouponCode = '';
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
        if (couponInput) couponInput.value = code;

        if (couponsWrap) couponsWrap.querySelectorAll('.ldp-bf-coupon')
            .forEach(function (c) { c.classList.toggle('is-applied', c === row); });

        if (couponMsg) {
            couponMsg.hidden = false;
            couponMsg.className = 'ldp-bf-coupon-msg is-ok';
            couponMsg.textContent = 'Coupon ' + code + ' applied: ' + pct + '% off';
        }
        updateTotals();
    }

    function updateTotals() {
        var p = persons();
        var mrpLine = Math.max(pkgMrp, pkgPrice) * p;
        var pkgLine = pkgPrice * p;
        var addons = addonTotal() * p;
        var hard = (hardCopy && hardCopy.checked) ? hardFee : 0;
        // Percentage coupon applies to the package line (scales with persons).
        couponOff = couponAmount(pkgLine);
        var discount = (mrpLine - pkgLine) + couponOff;
        var total = Math.max(0, pkgLine + addons + hard - couponOff);

        var elMrp = document.getElementById('ldpBfMrp');
        var elDisc = document.getElementById('ldpBfDiscount');
        var elPay = document.getElementById('ldpBfYouPay');
        var elSumPkg = document.getElementById('ldpBfSumPkg');
        var elSumTotal = document.getElementById('ldpBfSumTotal');
        var elStickyTotal = document.getElementById('ldpStickyTotal');

        if (elMrp) elMrp.textContent = formatInr(mrpLine);
        if (elDisc) elDisc.textContent = '- ' + formatInr(Math.max(0, discount));
        if (elPay) elPay.textContent = formatInr(total);
        if (elSumPkg) elSumPkg.textContent = formatInr(pkgLine + addons);
        if (elSumTotal) elSumTotal.textContent = formatInr(total);
        if (elStickyTotal) elStickyTotal.textContent = formatInr(total);
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
                       'value="' + id + '" data-price="' + t.price + '">' +
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
    })();

    var loginBtn = document.getElementById('ldpBfLogin');
    if (loginBtn) {
        loginBtn.addEventListener('click', function () {
            window.dispatchEvent(new CustomEvent('ecp:open-auth', {
                detail: { reason: loginBtn.getAttribute('data-auth-reason') || 'lab_book' }
            }));
        });
    }

    // Note: on a successful OTP login the shared auth modal calls location.reload()
    // itself, so the page re-renders server-side with the logged-in state (identity
    // chip + coupon unlocked). No client-side DOM swap is needed here.

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
            showToast('Please check pincode availability before booking.');
            pinInput && pinInput.focus();
            return;
        }
        if (!dateInput || !dateInput.value) {
            showToast('Please select preferred appointment date.');
            dateInput && dateInput.focus();
            return;
        }
        var time = document.getElementById('ldpBfTime');
        if (!time || !time.value) {
            showToast('Please select a time slot.');
            time && time.focus();
            return;
        }
        var phone = document.getElementById('ldpBfPhone');
        var phoneVal = ((phone && phone.value) || '').replace(/\D/g, '');
        if (phone) phone.value = phoneVal;
        if (!/^[6-9][0-9]{9}$/.test(phoneVal)) {
            showToast('Enter a valid 10-digit mobile number.');
            phone && phone.focus();
            return;
        }
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var pkgName = form.getAttribute('data-pkg-name') || 'Lab package';
        var totalText = (document.getElementById('ldpBfSumTotal') || {}).textContent || '';
        function finish() {
            showToast('Booking request received for “' + pkgName + '” (' + totalText + '). We’ll confirm shortly.');
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
        if (window.ecpAuth && typeof window.ecpAuth.require === 'function') {
            window.ecpAuth.require('lab_booking', finish);
        } else {
            finish();
        }
    });

    syncBeneficiaries();
    updateTotals();
    setFormLocked(true); // gate everything until a serviceable pincode is confirmed
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
