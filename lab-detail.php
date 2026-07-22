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
$badge = trim((string) ($d['badge'] ?? ''));
if ($badge === '') {
    $badge = $isPkg ? 'Best Seller' : ($typeLabels[$type] ?? 'Lab');
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

$detailTags = [];
if ($isPkg && $params) {
    $detailTags[] = ['fi-rr-flask', $params . ' Tests Included'];
}
if ($fastingCode === 'CF') {
    $detailTags[] = ['fi-rr-clock', 'Fasting Required (8–10 hrs)'];
} elseif ($fastingCode === 'NF') {
    $detailTags[] = ['fi-rr-check', 'No Fasting Required'];
}
$detailTags[] = ['fi-rr-file-medical', 'Digital Reports'];
$detailTags[] = ['fi-rr-house-blank', 'Free Home Collection'];
if ($loginPct > 0) {
    $detailTags[] = ['fi-rr-badge-percent', 'Extra ' . $loginPct . '% off on login'];
}
// Condition/disease tags (Thyroid, Infertility, …) from disease_group.
foreach (array_slice($diseaseTags, 0, 4) as $dt) {
    $detailTags[] = ['fi-rr-heart', $dt];
}

// Accordion test groups — driven by the REAL Thyrocare grouping stored in the
// DB (lab_parameters.group_name). No hardcoded test names: each panel is a
// genuine group with its actual parameters and true count.
$icoBase = '/assets/img/lab/icons/';
$testGroups = [];

// Map a real group name -> the closest existing accordion icon (best-effort;
// falls back to a generic flask). Purely cosmetic — data is from the DB.
$groupIcon = static function (string $name): string {
    $n = strtolower($name);
    return match (true) {
        (bool) preg_match('/sugar|diabet|glucose|hba1c|insulin/', $n) => 'sugar',
        (bool) preg_match('/kidney|renal|urea|creat|electrolyte/', $n) => 'kidney',
        (bool) preg_match('/liver|hepat/', $n)                        => 'liver',
        (bool) preg_match('/lipid|cardiac|cholesterol|heart/', $n)    => 'lipid',
        (bool) preg_match('/thyroid/', $n)                            => 'thyroid',
        (bool) preg_match('/iron|vitamin|anaemia|anemia|b12|ferritin/', $n) => 'iron',
        (bool) preg_match('/blood|cbc|hemogram|haemogram|complete/', $n) => 'blood-test',
        default                                                       => 'flask',
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
        $icons = ['clipboard', 'report', 'shield', 'doctor', 'flask', 'check'];
        $testGroups[] = [
            'name' => $f,
            'icon' => $icons[$i % count($icons)],
            'count' => 1,
            'items' => [$d['overview'] ?? ''],
        ];
    }
}

$whyReasons = [
    ['doc-check', 'Monitors blood sugar & HbA1c', 'Track long & short term sugar levels'],
    ['molecule', 'Detects early complications', 'Kidney, liver, heart & nerve related'],
    ['shield', 'Prevents future health risks', 'Timely detection, better outcomes'],
    ['report', 'Doctor reviewed reports', 'Expert insights with every report'],
    ['value', 'Great value for comprehensive care', ($params ?: '42') . ' tests at an affordable price'],
];
if (!$isPkg) {
    $whyReasons = [];
    foreach (array_slice($d['benefits'] ?? $d['features'] ?? [], 0, 5) as $i => $b) {
        $icons = ['doc-check', 'molecule', 'shield', 'report', 'value'];
        $whyReasons[] = [$icons[$i % 5], $b, 'Why this matters for your care'];
    }
}

$prep = [
    ['fi-rr-clock', 'Fasting 8–10 hours recommended'],
    ['fi-rr-glass-water', 'Drink plenty of water'],
    ['fi-rr-pills', 'Continue regular medicines'],
    ['fi-rr-hand-holding-droplet', 'Morning sample collection preferred'],
];

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
    ['24 Hr Report Delivery', 'clock'],
    ['NABL Accredited Labs', 'lab'],
    ['Free Home Collection', 'home'],
    ['Free Doctor Consultation', 'doc'],
    ['96% Customer Satisfaction', 'star'],
];

$testimonials = [
    ['Priya S.', 'Mumbai', 'Reports came the next morning and the doctor consult helped me understand my sugar trends clearly.'],
    ['Rahul K.', 'Pune', 'Home collection was on time and hygienic. Booking on eClinicPro was very simple.'],
    ['Ananya M.', 'Bengaluru', 'Good value versus MRP. The package covered everything my physician asked for.'],
];

$seed = crc32($type . ':' . $slug);
$bookings = number_format(8000 + ($seed % 9000));
$rating = number_format(4.6 + (($seed % 4) / 10), 1);

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
$addonTests = [
    ['id' => 'vitd', 'label' => 'Vitamin D Total', 'count' => 1, 'price' => 899],
    ['id' => 'vitb12', 'label' => 'Vitamin B-12', 'count' => 1, 'price' => 699],
    ['id' => 'tft', 'label' => 'Thyroid Profile Total', 'count' => 3, 'price' => 499],
    ['id' => 'hba1c', 'label' => 'HbA1c', 'count' => 1, 'price' => 399],
    ['id' => 'iron', 'label' => 'Iron Studies', 'count' => 4, 'price' => 550],
    ['id' => 'lipid', 'label' => 'Lipid Profile', 'count' => 5, 'price' => 450],
];

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
                <span class="ldp-badge"><?= e($badge) ?></span>
                <h1><?= e($d['title']) ?></h1>
                <div class="ldp-hero-meta">
                    <span class="ldp-stars" aria-label="Rated <?= e($rating) ?> out of 5">★★★★★</span>
                    <strong><?= e($rating) ?></strong>
                    <span>(<?= e($bookings) ?>+ Bookings)</span>
                    <?php if ($isPkg || $params): ?>
                    <span class="ldp-pill"><?= e($params) ?> Tests Included</span>
                    <?php endif; ?>
                </div>
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
                                <img src="<?= e($icoBase . ($g['icon'] ?? 'flask') . '.png') ?>" alt="" width="128" height="128" loading="lazy">
                            </span>
                            <span class="ldp-tw-acc-title"><?= e($g['name']) ?></span>
                            <span class="ldp-tw-acc-count"><?= (int) $g['count'] ?> <?= $isPkg ? 'Tests' : 'Points' ?></span>
                            <span class="ldp-tw-acc-chev" aria-hidden="true">
                                <img src="<?= e($icoBase . 'chevron.png') ?>" alt="" width="128" height="128" loading="lazy">
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
                                <img src="<?= e($icoBase . $wIco . '.png') ?>" alt="" width="128" height="128" loading="lazy">
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
                    <form class="ldp-bf-form" id="ldpLabBookForm" novalidate
                          data-pkg-price="<?= (int) $pkgPriceNum ?>"
                          data-pkg-mrp="<?= (int) $pkgMrpNum ?>"
                          data-pkg-name="<?= e($d['title']) ?>"
                          data-hardcopy="75">

                        <div class="ldp-bf-block">
                            <h3 class="ldp-bf-title">Price Details</h3>
                            <div class="ldp-bf-price-rows">
                                <div class="ldp-bf-row">
                                    <span>MRP</span>
                                    <s id="ldpBfMrp">₹<?= number_format(max($pkgMrpNum, $pkgPriceNum)) ?></s>
                                </div>
                                <div class="ldp-bf-row">
                                    <span>Discount</span>
                                    <span class="ldp-bf-discount" id="ldpBfDiscount">- ₹<?= number_format($pkgDiscount) ?></span>
                                </div>
                                <div class="ldp-bf-row ldp-bf-row-pay">
                                    <span>You Pay</span>
                                    <strong id="ldpBfYouPay">₹<?= number_format($pkgPriceNum) ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="ldp-bf-block">
                            <label class="ldp-bf-label" for="ldpBfPersons">Number of Persons</label>
                            <div class="ldp-bf-qty" role="group" aria-label="Number of persons">
                                <button type="button" class="ldp-bf-qty-btn" id="ldpBfQtyMinus" aria-label="Decrease">−</button>
                                <input type="number" name="persons" id="ldpBfPersons" value="1" min="1" max="10" readonly>
                                <button type="button" class="ldp-bf-qty-btn" id="ldpBfQtyPlus" aria-label="Increase">+</button>
                            </div>

                            <p class="ldp-bf-hint">Write <strong>Exact Pincode</strong>, not nearby Pincode</p>
                            <div class="ldp-bf-pinrow">
                                <input type="text" name="pincode" id="ldpBfPincode" inputmode="numeric" maxlength="6" placeholder="Pincode" pattern="[1-9][0-9]{5}" required autocomplete="postal-code">
                                <button type="button" class="ldp-bf-check" id="ldpBfPinCheck">Check Availability</button>
                            </div>
                            <p class="ldp-bf-pinmsg" id="ldpBfPinMsg" hidden></p>

                            <label class="ldp-bf-label" for="ldpBfDate">Preferred Date</label>
                            <div class="ldp-bf-icon-field">
                                <input type="date" id="ldpBfDate" name="appointment_date" required aria-label="Select Preferred Appointment Date">
                                <span class="ldp-bf-ico" aria-hidden="true"><i class="fi fi-rr-calendar"></i></span>
                            </div>

                            <label class="ldp-bf-label" for="ldpBfTime">Time Slot</label>
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
                            <p class="ldp-bf-note-soft">Order with incomplete/invalid address will be rejected.</p>
                        </div>

                        <div class="ldp-bf-block">
                            <h3 class="ldp-bf-title">Additional Information</h3>
                            <label class="ldp-bf-label" for="ldpBfCoupon">Coupon Code <span>(Optional)</span></label>
                            <input type="text" id="ldpBfCoupon" name="coupon" placeholder="Enter coupon code" autocomplete="off">
                            <p class="ldp-bf-coupon-msg" id="ldpBfCouponMsg" hidden></p>

                            <label class="ldp-bf-label" for="ldpBfNotes">Add Notes <span>(Optional)</span></label>
                            <textarea id="ldpBfNotes" name="notes" rows="3" placeholder="Write any instructions"></textarea>
                        </div>

                        <div class="ldp-bf-block" id="ldpBookAddons">
                            <h3 class="ldp-bf-title">Tick To Add Additional Tests (Optional)</h3>
                            <div class="ldp-bf-addons">
                                <?php foreach ($addonTests as $addon): ?>
                                <label class="ldp-bf-addon">
                                    <input type="checkbox" name="addons[]" value="<?= e($addon['id']) ?>" data-price="<?= (int) $addon['price'] ?>">
                                    <span><?= e($addon['label']) ?> (<?= (int) $addon['count'] ?>) @ Rs. <?= (int) $addon['price'] ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <label class="ldp-bf-hardcopy">
                                <input type="checkbox" name="hard_copy" id="ldpBfHardCopy" value="1">
                                <span>Please tick to receive hard copy / SMS updates. Courier charges <strong>Rs. 75 Extra.</strong></span>
                            </label>
                        </div>

                        <button type="submit" class="ldp-bf-submit">Book Now</button>

                        <div class="ldp-bf-paylater">
                            <span class="ldp-bf-paylater-ico" aria-hidden="true"><i class="fi fi-rr-wallet"></i></span>
                            <div>
                                <strong>Book Now, Pay Later!</strong>
                                <p>Simple process. No cost EMI options available.</p>
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
    var couponOff = 0;
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

    function checkPincode() {
        var pin = ((pinInput && pinInput.value) || '').replace(/\D/g, '');
        if (pinInput) pinInput.value = pin;
        if (!/^[1-9][0-9]{5}$/.test(pin)) {
            pinOk = false;
            setPinMsg('Enter a valid 6-digit Indian pincode.', false);
            return false;
        }
        var first = parseInt(pin.charAt(0), 10);
        pinOk = first >= 1 && first <= 8;
        if (pinOk) {
            setPinMsg('Home collection available for pincode ' + pin + '.', true);
        } else {
            setPinMsg('Sorry, home collection is not available for this pincode yet.', false);
        }
        return pinOk;
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

    function applyCoupon() {
        couponOff = 0;
        if (!couponMsg) return;
        var code = ((couponInput && couponInput.value) || '').trim().toUpperCase();
        if (!code) {
            couponMsg.hidden = true;
            couponMsg.textContent = '';
            return;
        }
        if (code === 'SAVE100') {
            couponOff = 100;
            couponMsg.hidden = false;
            couponMsg.className = 'ldp-bf-coupon-msg is-ok';
            couponMsg.textContent = 'Coupon applied: ₹100 off';
        } else if (code === 'LAB50') {
            couponOff = 50;
            couponMsg.hidden = false;
            couponMsg.className = 'ldp-bf-coupon-msg is-ok';
            couponMsg.textContent = 'Coupon applied: ₹50 off';
        } else {
            couponMsg.hidden = false;
            couponMsg.className = 'ldp-bf-coupon-msg is-bad';
            couponMsg.textContent = 'Invalid coupon code';
        }
    }

    function updateTotals() {
        var p = persons();
        var mrpLine = Math.max(pkgMrp, pkgPrice) * p;
        var pkgLine = pkgPrice * p;
        var addons = addonTotal() * p;
        var hard = (hardCopy && hardCopy.checked) ? hardFee : 0;
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

    if (couponInput) {
        couponInput.addEventListener('change', function () { applyCoupon(); updateTotals(); });
        couponInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                applyCoupon();
                updateTotals();
            }
        });
    }

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
        applyCoupon();
        updateTotals();

        if (!checkPincode()) {
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
            couponOff = 0;
            pinOk = false;
            setPinMsg('', false);
            if (couponMsg) { couponMsg.hidden = true; couponMsg.textContent = ''; }
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
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
