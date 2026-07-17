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
$allowed = ['package', 'organ', 'symptom', 'life', 'step', 'why', 'partner'];

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
$isPkg = $type === 'package';
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

// Build "what's included" chips from tests / features
$includeChips = [];
if ($tests) {
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

// Accordion test groups — package categories match detail mock
$icoBase = '/assets/img/lab/icons/';
$testGroups = [];
if ($isPkg) {
    $catalogTests = array_values($tests);
    $profiles = [
        ['Blood Tests', 'blood-test', 13, ['CBC', 'Hemoglobin', 'RBC Count', 'WBC Count', 'Platelet Count', 'PCV', 'MCV', 'MCH', 'MCHC', 'RDW', 'Neutrophils', 'Lymphocytes', 'ESR']],
        ['Diabetes & Sugar Related', 'sugar', 5, ['Fasting Blood Sugar', 'PPBS', 'HbA1c', 'Insulin Fasting', 'Average Blood Glucose']],
        ['Kidney Profile', 'kidney', 7, ['Serum Creatinine', 'Blood Urea', 'BUN', 'Uric Acid', 'Sodium', 'Potassium', 'Chloride']],
        ['Liver Profile', 'liver', 4, ['SGOT (AST)', 'SGPT (ALT)', 'Bilirubin Total', 'Alkaline Phosphatase']],
        ['Lipid Profile', 'lipid', 5, ['Total Cholesterol', 'HDL', 'LDL', 'Triglycerides', 'VLDL']],
        ['Thyroid Profile', 'thyroid', 4, ['T3', 'T4', 'TSH', 'Free T4']],
        ['Iron & Vitamin Profile', 'iron', 4, ['Serum Iron', 'Ferritin', 'Vitamin D', 'Vitamin B12']],
    ];
    // Prefer real package tests in first matching groups when present
    $used = [];
    foreach ($profiles as [$name, $icon, $count, $defaults]) {
        $matched = [];
        foreach ($catalogTests as $t) {
            $tl = strtolower((string) $t);
            $hit = false;
            if ($icon === 'sugar' && preg_match('/sugar|hba1c|glucose|insulin|ppbs|fbs|diabetes/', $tl)) {
                $hit = true;
            } elseif ($icon === 'kidney' && preg_match('/kidney|renal|crea|urea|uric|sodium|potassium|urine/', $tl)) {
                $hit = true;
            } elseif ($icon === 'liver' && preg_match('/liver|hepatic|sgot|sgpt|bilirubin|alkaline/', $tl)) {
                $hit = true;
            } elseif ($icon === 'lipid' && preg_match('/lipid|cholesterol|hdl|ldl|trigly/', $tl)) {
                $hit = true;
            } elseif ($icon === 'thyroid' && preg_match('/thyroid|tsh|\bt3\b|\bt4\b/', $tl)) {
                $hit = true;
            } elseif ($icon === 'iron' && preg_match('/iron|ferritin|vitamin|b12|folate|calcium/', $tl)) {
                $hit = true;
            } elseif ($icon === 'blood-test' && preg_match('/cbc|blood|hemoglobin|platelet|esr|hemogram/', $tl)) {
                $hit = true;
            }
            if ($hit && !isset($used[$t])) {
                $matched[] = $t;
                $used[$t] = true;
            }
        }
        $items = $matched ? array_values(array_unique(array_merge($matched, $defaults))) : $defaults;
        $items = array_slice($items, 0, max($count, count($matched)));
        $testGroups[] = [
            'name' => $name,
            'icon' => $icon,
            'count' => count($items),
            'items' => $items,
        ];
    }
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

$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@flaticon/flaticon-uicons@3.3.1/css/regular/rounded.css">';

require __DIR__ . '/partials/header.php';
?>

<div class="store-preview-bar">
    <span class="store-preview-dot"></span>
    Preview only — lab booking launching soon. Detail pages are for layout &amp; partner demos.
</div>

<main class="ldp ldp-<?= e($type) ?>">

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
    </div>

    <!-- Hero -->
    <section class="ldp-hero wrap">
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
                </div>
                <?php endif; ?>
                <div class="ldp-hero-actions">
                    <?php if ($isPkg): ?>
                    <button type="button" class="ldp-btn ldp-btn-primary lab-book" data-book="<?= e($d['title']) ?>">Book Home Collection</button>
                    <a href="#ldp-tests" class="ldp-btn ldp-btn-ghost">View Tests</a>
                    <?php else: ?>
                    <a href="/lab#lab-packages" class="ldp-btn ldp-btn-primary">Browse Packages</a>
                    <a href="/lab" class="ldp-btn ldp-btn-ghost">Back to Lab</a>
                    <?php endif; ?>
                </div>
                <div class="ldp-hero-trust">
                    <span>🔒 Secure booking</span>
                    <span>✔ 100% Safe &amp; Hygienic</span>
                </div>
            </div>
            <div class="ldp-hero-media">
                <img src="<?= e($heroSide) ?>" alt="<?= e($d['title']) ?>" width="900" height="700" loading="eager">
            </div>
        </div>
    </section>

    <!-- Highlights bar -->
    <section class="ldp-bar wrap">
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
    <section class="ldp-section wrap">
        <div class="ldp-section-head">
            <h2>What’s Included</h2>
            <?php if ($isPkg): ?><a href="#ldp-tests">View all tests</a><?php endif; ?>
        </div>
        <div class="ldp-chips">
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/Blood-Sugar-icon.png" alt="" width="60" height="60" loading="lazy"></span>
                <span>Blood Tests</span>
            </div>
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/Hba1c-icon.png" alt="" width="60" height="60" loading="lazy"></span>
                <span>HbA1c</span>
            </div>
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/Kidney-icon.png" alt="" width="60" height="60" loading="lazy"></span>
                <span>Kidney Profile</span>
            </div>
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/Liver-Profile-icon.png" alt="" width="60" height="60" loading="lazy"></span>
                <span>Liver Profile</span>
            </div>
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/Lipid-Profile-icon.png" alt="" width="60" height="60" loading="lazy"></span>
                <span>Lipid Profile</span>
            </div>
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/Thyroid-icon.png" alt="" width="60" height="60" loading="lazy"></span>
                <span>Thyroid Profile</span>
            </div>
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/Iron-Studies-icon.png" alt="" width="60" height="60" loading="lazy"></span>
                <span>Iron & Vitamin Profile</span>
            </div>
            <div class="ldp-chip">
                <span class="ldp-chip-ico" aria-hidden="true"><img src="/assets/img/lab/icons/MOre-icon.png" alt="" width="60" height="60" loading="lazy"></span>
                <span>& More</span>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Tests + Why choose (two-card layout) -->
    <section class="ldp-section ldp-section-soft" id="ldp-tests">
        <div class="wrap ldp-tw">
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
        <div class="wrap ldp-split-prep">
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
    <section class="ldp-section wrap" id="ldp-faq">
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
            $extraFaq = [
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
        <div class="wrap">
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
        </div>
    </section>
    <?php endif; ?>

</main>

<!-- Sticky CTA -->
<div class="ldp-sticky" id="ldpSticky">
    <div class="wrap ldp-sticky-inner">
        <p>Ready to take control of your health? Book <strong><?= e($d['title']) ?></strong> today &amp; get expert insights.</p>
        <div class="ldp-sticky-actions">
            <?php if ($isPkg && $price !== ''): ?>
            <div class="ldp-sticky-price">
                <strong>₹<?= e($price) ?></strong>
                <?php if ($off !== ''): ?><span><?= e($off) ?>% OFF</span><?php endif; ?>
            </div>
            <button type="button" class="ldp-btn ldp-btn-light lab-book" data-book="<?= e($d['title']) ?>">Book Now</button>
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
        timer = setTimeout(function () { toast.classList.remove('is-on'); }, 2600);
    }
    document.addEventListener('click', function (ev) {
        var book = ev.target.closest('[data-book]');
        if (!book) return;
        ev.preventDefault();
        var pkg = book.getAttribute('data-book') || 'Lab Test';
        if (window.ecpAuth && typeof window.ecpAuth.require === 'function') {
            window.ecpAuth.require('lab_booking', function () {
                showToast('You’re signed in — “' + pkg + '” booking opens soon.');
            });
        } else {
            showToast();
        }
    });

    var faqSearch = document.getElementById('ldpFaqSearch');
    var faqItems = document.querySelectorAll('#ldpFaqGrid .ldp-faq-item');
    if (faqSearch && faqItems.length) {
        faqSearch.addEventListener('input', function () {
            var q = (faqSearch.value || '').toLowerCase().trim().toggleCase();
            faqItems.forEach(function (el) {
                var hay = el.getAttribute('data-faq') || '';
                el.hidden = q !== '' && hay.indexOf(q) === -1;
            });
        });
    }
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
