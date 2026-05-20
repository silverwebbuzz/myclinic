<?php
// =====================================================================
// index.php — eClinicPro homepage
// =====================================================================

require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'eClinicPro — The clinic OS doctors love';
$metaDesc = 'Pick your modules. Pay for what you use. One beautifully simple clinic system for GPs, dentists, homeopaths, and every specialty in between.';
$activePage = '';  // homepage has no active nav item

// Live stats from the portal DB; fall back to floors if DB is unreachable.
$clinicCount = ecp_active_clinic_count();
$countryCount = ecp_country_count();

require __DIR__ . '/partials/header.php';
?>

<!-- ============ Hero ============ -->
<section class="hero" id="top">
    <div class="hero-bg"></div>
    <div class="hero-dots"></div>
    <div class="hero-inner">
        <span class="eyebrow">The global clinic OS</span>
        <h1>
            The clinic software<br>
            doctors <span class="grad">actually love.</span>
        </h1>
        <p class="lede">
            Pick your modules. Pay for what you use. One beautifully simple
            system for GPs, dentists, homeopaths, and every specialty in between.
        </p>
        <div class="hero-ctas">
            <a href="#cta" class="btn btn-primary btn-lg">Start free — no card needed</a>
            <a href="#features" class="btn btn-ghost-dark btn-lg">▶ Watch 2-min demo</a>
        </div>
        <div class="hero-trust">
            <div class="dots"><span></span><span></span><span></span><span></span></div>
            <span>
                Trusted by <strong style="color: var(--ink); font-weight: 500;"><?= ecp_num($clinicCount) ?> clinics</strong>
                in <?= ecp_num($countryCount) ?> countries
            </span>
        </div>
    </div>
</section>

<!-- ============ Marquee ============ -->
<?php
$marquee = [
    'Sunrise Dental · London',
    'Dr. Sharma OPD · Mumbai',
    'PediaCare · Toronto',
    'Skin & Co · Madrid',
    'Dr. Patel Homeo · Pune',
    'Hill Family Practice · Sydney',
    'Bright Smiles · Dubai',
    'Riverside Physio · Bristol',
    'Akin Pediatrics · Lagos',
    'Dr. Liang GP · Singapore',
    'Northside Derma · Chicago',
    'Dr. Sato Clinic · Tokyo',
];
?>
<div class="marquee">
    <div class="marquee-track">
        <?php foreach (array_merge($marquee, $marquee) as $m): ?>
            <span class="marquee-item"><span class="dot"></span><?= e($m) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============ Stats ============ -->
<section style="padding: 72px 0; background: #fff;">
    <div class="wrap">
        <div class="stats">
            <div class="stat reveal">
                <div class="stat-num"><?= ecp_num($clinicCount) ?></div>
                <div class="stat-label">Clinics worldwide</div>
            </div>
            <div class="stat reveal">
                <div class="stat-num"><?= ecp_num($countryCount) ?></div>
                <div class="stat-label">Countries</div>
            </div>
            <div class="stat reveal">
                <div class="stat-num">1.2M</div>
                <div class="stat-label">Patients managed</div>
            </div>
            <div class="stat reveal">
                <div class="stat-num">24</div>
                <div class="stat-label">Modular tools</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ Problem / Solution ============ -->
<section class="bg-grey" id="problem">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">A clinic, simplified</span>
            <h2 class="h-section">There's the old way of running a clinic.<br>And then there's eClinicPro.</h2>
        </div>
        <div class="psgrid">
            <div class="ps-col reveal">
                <h3 style="color: var(--mute);">The old way</h3>
                <ul class="ps-list">
                    <?php
                    $oldWay = [
                        ['Paper registers', 'piled on a shelf and lost when it matters.'],
                        ['WhatsApp Rx photos', 'sent at 11pm, untracked, unsigned.'],
                        ['Five different apps', 'one for billing, one for scheduling, none that talk.'],
                        ['Per-seat pricing', 'paying for features your clinic will never use.'],
                        ['No specialty support', 'a dental chart that\'s just a checkbox.'],
                    ];
                    foreach ($oldWay as $p): ?>
                    <li class="ps-item">
                        <span class="ps-icon bad">✕</span>
                        <span class="ps-text"><strong><?= e($p[0]) ?></strong> — <?= e($p[1]) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="divider"></div>
            <div class="ps-col reveal">
                <h3>eClinicPro</h3>
                <ul class="ps-list">
                    <?php
                    $newWay = [
                        ['One encrypted record', 'searchable in 200ms, exportable in one click.'],
                        ['Signed digital Rx', 'delivered by WhatsApp before the patient leaves.'],
                        ['One system, 24 modules', 'every part designed to work together.'],
                        ['Pay per module', 'turn off what you don\'t need. Your bill drops.'],
                        ['Built for your specialty', 'real tools for dental, homeo, derma, peds, physio.'],
                    ];
                    foreach ($newWay as $p): ?>
                    <li class="ps-item">
                        <span class="ps-icon good">✓</span>
                        <span class="ps-text"><strong><?= e($p[0]) ?></strong> — <?= e($p[1]) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ============ Specialties teaser ============ -->
<section id="specialties" style="padding: 80px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Built for your specialty</span>
            <h2 class="h-section">Real tools for the way your clinic actually works.</h2>
            <p class="lede">Tap a specialty to see the modules and templates we ship for it.</p>
        </div>

        <div class="specialty-grid">
            <?php
            $specialties = [
                ['slug' => 'gps', 'icon' => '🩺', 'name' => 'General Practice', 'tag' => 'OPD, vitals, Rx, follow-ups'],
                ['slug' => 'dentists', 'icon' => '🦷', 'name' => 'Dentistry', 'tag' => 'Tooth chart, procedures, lab work'],
                ['slug' => 'homeopaths', 'icon' => '🌿', 'name' => 'Homeopathy', 'tag' => 'Remedies, potency tracking, dietetics'],
                ['slug' => 'dermatologists', 'icon' => '✨', 'name' => 'Dermatology', 'tag' => 'Before/after photos, skin charts'],
                ['slug' => 'pediatricians', 'icon' => '👶', 'name' => 'Pediatrics', 'tag' => 'Growth curves, vaccination tracker'],
                ['slug' => 'physiotherapists', 'icon' => '🤸', 'name' => 'Physiotherapy', 'tag' => 'Pain charts, exercise prescriptions'],
            ];
            foreach ($specialties as $sp): ?>
            <a href="/for-<?= e($sp['slug']) ?>" class="specialty-card reveal">
                <div class="specialty-icon"><?= $sp['icon'] ?></div>
                <div class="specialty-name"><?= e($sp['name']) ?></div>
                <div class="specialty-tag"><?= e($sp['tag']) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TODO: Module marketplace, Features deep-dive, Testimonials, FAQ
     — these come next round with the remaining 12 page ports.
     For now they're omitted so we ship a working homepage skeleton you can review. -->

<?php require __DIR__ . '/partials/footer.php'; ?>
