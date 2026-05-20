<?php
// =====================================================================
// index.php — eClinicPro homepage
// =====================================================================

require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'eClinicPro — The clinic OS doctors love';
$metaDesc = 'Pick your modules. Pay for what you use. One beautifully simple clinic system for GPs, dentists, homeopaths, and every specialty in between.';
$activePage = '';

$clinicCount = ecp_active_clinic_count();
$countryCount = ecp_country_count();

require __DIR__ . '/partials/header.php';
?>

<!-- ============ HERO with embedded dashboard preview ============ -->
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
            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">Start free — no card needed</a>
            <a href="#features" class="btn btn-ghost-dark btn-lg">▶ Watch 2-min demo</a>
        </div>
        <div class="hero-trust">
            <div class="dots"><span></span><span></span><span></span><span></span></div>
            <span>Trusted by <strong style="color: var(--ink); font-weight: 500;"><?= ecp_num($clinicCount) ?> clinics</strong> in <?= ecp_num($countryCount) ?> countries</span>
        </div>

        <!-- Embedded dashboard preview (ported from hero-preview.jsx) -->
        <div class="hero-preview reveal">
            <div style="display: grid; grid-template-columns: 220px 1fr; min-height: 460px;">
                <!-- Sidebar -->
                <div style="border-right: 0.5px solid var(--line); background: #FAFAFB; padding: 20px 14px; display: flex; flex-direction: column; gap: 4px;">
                    <div style="display: flex; align-items: center; gap: 8px; padding: 0 8px 16px;">
                        <div style="width: 24px; height: 24px; border-radius: 7px; background: var(--teal-600); color: #fff; display: grid; place-items: center; font-size: 11px; font-weight: 700;">M</div>
                        <span style="font-size: 13px; font-weight: 600; letter-spacing: -0.2px;">Sunrise Clinic</span>
                    </div>
                    <?php
                    $sidebar = [
                        ['📅', 'Today', true, 8],
                        ['👥', 'Patients', false, null],
                        ['℞', 'Prescriptions', false, null],
                        ['💊', 'Pharmacy', false, 3],
                        ['🧪', 'Lab orders', false, null],
                        ['🎥', 'Telemedicine', false, null],
                        ['📊', 'Analytics', false, null],
                    ];
                    foreach ($sidebar as [$icon, $label, $active, $badge]):
                        $bg = $active ? 'background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'background: transparent;';
                        $color = $active ? 'var(--ink)' : 'var(--mute)';
                    ?>
                    <div style="display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 7px; font-size: 12.5px; font-weight: 500; color: <?= $color ?>; <?= $bg ?>">
                        <span style="font-size: 14px;"><?= $icon ?></span>
                        <span style="flex: 1;"><?= e($label) ?></span>
                        <?php if ($badge): ?>
                        <span style="font-size: 10px; background: var(--teal-600); color: #fff; padding: 1px 6px; border-radius: 8px;"><?= (int) $badge ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <div style="flex: 1;"></div>
                    <div style="font-size: 10px; color: var(--mute); padding: 8px 10px; border-top: 0.5px solid var(--line); margin-top: 14px;">
                        <div style="font-weight: 500; color: var(--ink-2); margin-bottom: 3px;">Dr. A. Sharma</div>
                        GP · 12 modules
                    </div>
                </div>

                <!-- Main panel -->
                <div style="padding: 20px 24px; background: #fff;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
                        <div>
                            <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Monday · 18 May</div>
                            <div style="font-size: 22px; font-weight: 300; letter-spacing: -0.5px; margin-top: 2px;">Good morning, Aarav.</div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <div style="font-size: 11px; padding: 5px 10px; background: var(--teal-50); color: var(--teal-800); border-radius: 8px; font-weight: 500;">14 visits today</div>
                            <div style="font-size: 11px; padding: 5px 10px; background: var(--bg-2); color: var(--ink-2); border-radius: 8px; font-weight: 500;">3 in waiting</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px;">
                        <?php
                        $miniStats = [
                            ['Avg wait', '8 min', '−2 vs last wk', false],
                            ['Revenue', '$2,140', '+18% MTD', true],
                            ['Retention', '92%', 'Q1 cohort', true],
                        ];
                        foreach ($miniStats as [$l, $v, $d, $up]):
                            $deltaColor = $up ? '#1B8B3D' : 'var(--mute)';
                        ?>
                        <div style="border: 0.5px solid var(--line); border-radius: 10px; padding: 10px 12px;">
                            <div style="font-size: 11px; color: var(--mute);"><?= e($l) ?></div>
                            <div style="font-size: 20px; font-weight: 400; letter-spacing: -0.5px; margin-top: 2px;"><?= e($v) ?></div>
                            <div style="font-size: 10px; color: <?= $deltaColor ?>; margin-top: 2px;"><?= e($d) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="border: 0.5px solid var(--line); border-radius: 10px; overflow: hidden;">
                        <div style="padding: 10px 14px; background: var(--bg-3); border-bottom: 0.5px solid var(--line); display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 12px; font-weight: 500;">Today's queue</span>
                            <span style="font-size: 11px; color: var(--mute);">9 scheduled · 3 walk-in</span>
                        </div>
                        <?php
                        $queue = [
                            ['09:30', 'Riya Mehta', 'Follow-up · Hypertension', 'Now', 'var(--teal-600)'],
                            ['09:45', 'Karan Vyas', 'New · Cough, 4 days', 'Waiting', 'var(--amber)'],
                            ['10:00', 'Sneha Iyer', 'Pediatric · 14mo well-visit', 'Scheduled', 'var(--mute)'],
                            ['10:15', 'P. Krishnan', 'Diabetes review · A1c due', 'Scheduled', 'var(--mute)'],
                        ];
                        $lastIdx = count($queue) - 1;
                        foreach ($queue as $i => [$t, $n, $s, $st, $stc]):
                            $border = $i < $lastIdx ? 'border-bottom: 0.5px solid var(--line);' : '';
                        ?>
                        <div style="display: grid; grid-template-columns: 60px 1fr auto; gap: 12px; align-items: center; padding: 10px 14px; <?= $border ?>">
                            <span style="font-size: 12px; font-family: 'JetBrains Mono', monospace; color: var(--mute);"><?= e($t) ?></span>
                            <div>
                                <div style="font-size: 13px; font-weight: 500; color: var(--ink);"><?= e($n) ?></div>
                                <div style="font-size: 11px; color: var(--mute); margin-top: 1px;"><?= e($s) ?></div>
                            </div>
                            <span style="font-size: 11px; color: <?= $stc ?>; font-weight: 500;"><?= e($st) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ MARQUEE ============ -->
<?php
$marquee = ['Sunrise Dental · London', 'Dr. Sharma OPD · Mumbai', 'PediaCare · Toronto', 'Skin & Co · Madrid', 'Dr. Patel Homeo · Pune', 'Hill Family Practice · Sydney', 'Bright Smiles · Dubai', 'Riverside Physio · Bristol', 'Akin Pediatrics · Lagos', 'Dr. Liang GP · Singapore', 'Northside Derma · Chicago', 'Dr. Sato Clinic · Tokyo'];
?>
<div class="marquee">
    <div class="marquee-track">
        <?php foreach (array_merge($marquee, $marquee) as $m): ?>
            <span class="marquee-item"><span class="dot"></span><?= e($m) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============ STATS ============ -->
<section style="padding: 72px 0; background: #fff;">
    <div class="wrap">
        <div class="stats">
            <div class="stat reveal"><div class="stat-num"><?= ecp_num($clinicCount) ?></div><div class="stat-label">Clinics worldwide</div></div>
            <div class="stat reveal"><div class="stat-num"><?= ecp_num($countryCount) ?></div><div class="stat-label">Countries</div></div>
            <div class="stat reveal"><div class="stat-num">1.2M</div><div class="stat-label">Patients managed</div></div>
            <div class="stat reveal"><div class="stat-num">24</div><div class="stat-label">Modular tools</div></div>
        </div>
    </div>
</section>

<!-- ============ PROBLEM / SOLUTION ============ -->
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
                    <?php foreach ([
                        ['Paper registers', 'piled on a shelf and lost when it matters.'],
                        ['WhatsApp Rx photos', 'sent at 11pm, untracked, unsigned.'],
                        ['Five different apps', 'one for billing, one for scheduling, none that talk.'],
                        ['Per-seat pricing', 'paying for features your clinic will never use.'],
                        ['No specialty support', "a dental chart that's just a checkbox."],
                    ] as $p): ?>
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
                    <?php foreach ([
                        ['One encrypted record', 'searchable in 200ms, exportable in one click.'],
                        ['Signed digital Rx', 'delivered by WhatsApp before the patient leaves.'],
                        ['One system, 24 modules', 'every part designed to work together.'],
                        ['Pay per module', "turn off what you don't need. Your bill drops."],
                        ['Built for your specialty', 'real tools for dental, homeo, derma, peds, physio.'],
                    ] as $p): ?>
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

<!-- ============ MODULE MARKETPLACE ============ -->
<?php
$modules = [
    ['records', 'Patient records', 'Encrypted, structured notes with attachments.', 0, true, ['gp','dental','homeo','derma','peds','physio'], '📋'],
    ['appts', 'Appointments', 'Calendar, reminders, no-show tracking.', 0, true, ['gp','dental','homeo','derma','peds','physio'], '📅'],
    ['rx', 'Prescriptions', 'Digital Rx with WhatsApp delivery.', 6, false, ['gp','dental','homeo','derma','peds','physio'], '℞'],
    ['qr', 'QR patient card', 'Tap a card, load the chart in 200ms.', 4, false, ['gp','dental','homeo','derma','peds','physio'], '📱'],
    ['vitals', 'Vitals & charts', 'BP, sugar, weight — visual trends.', 5, false, ['gp','derma','peds','physio','homeo'], '❤️'],
    ['pharma', 'Pharmacy', 'Inventory, batches, expiry alerts.', 12, false, ['gp','dental','homeo','derma','peds'], '💊'],
    ['lab', 'Lab orders', 'Order, receive, attach results.', 8, false, ['gp','derma','peds','physio'], '🧪'],
    ['tele', 'Telemedicine', 'HD video visits, in-browser.', 14, false, ['gp','derma','peds','physio','homeo'], '🎥'],
    ['diet', 'Diet plans', 'Templated meal plans, customizable.', 6, false, ['gp','derma','peds','physio'], '🥗'],
    ['billing', 'Billing & invoices', 'Multi-currency, GST/VAT ready.', 9, false, ['gp','dental','homeo','derma','peds','physio'], '🧾'],
    ['chart', 'Dental charting', 'Tooth-by-tooth visual chart.', 9, false, ['dental'], '🦷'],
    ['remedy', 'Remedy database', '3,200 remedies with potency & antidotes.', 7, false, ['homeo'], '🧪'],
    ['derma', 'Skin imaging', 'Before/after with annotation.', 11, false, ['derma'], '🖼️'],
    ['growth', 'Growth charts', 'WHO percentile tracking for kids.', 5, false, ['peds'], '🌱'],
    ['physio', 'Exercise plans', 'Video-led home exercise programs.', 8, false, ['physio'], '🤸'],
    ['reports', 'Analytics & reports', 'Revenue, retention, top diagnoses.', 9, false, ['gp','dental','homeo','derma','peds','physio'], '📊'],
];
?>
<section id="modules">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Module marketplace</span>
            <h2 class="h-section">Buy only what you need.</h2>
            <p class="lede">Every module is independent. Add, remove, or upgrade — anytime. Your bill adjusts the same day.</p>
        </div>

        <div class="spec-tabs reveal">
            <?php foreach ([
                ['all', 'All'], ['gp', 'General'], ['dental', 'Dental'], ['homeo', 'Homeopathy'],
                ['derma', 'Dermatology'], ['peds', 'Pediatrics'], ['physio', 'Physio'],
            ] as [$id, $label]): ?>
            <button class="spec-tab <?= $id === 'all' ? 'active' : '' ?>"><?= e($label) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="modules-grid">
            <?php foreach ($modules as [$id, $name, $desc, $price, $free, $specs, $icon]): ?>
            <div class="module-card reveal">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div class="module-icon"><?= $icon ?></div>
                    <div class="module-add">+</div>
                </div>
                <div>
                    <div class="module-name"><?= e($name) ?></div>
                    <div class="module-desc" style="margin-top: 4px;"><?= e($desc) ?></div>
                </div>
                <div class="module-foot">
                    <?php if ($free): ?>
                        <span class="badge-free">Free forever</span>
                    <?php else: ?>
                        <span class="module-price">$<?= (int) $price ?><span class="per">/mo</span></span>
                        <span style="font-size: 11px; color: var(--mute);"><?= count($specs) === 1 ? 'Specialty' : 'Universal' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 36px;">
            <a href="/features" class="btn-link">See full module catalog →</a>
        </div>
    </div>
</section>

<!-- ============ SPECIALTY SHOWCASE ============ -->
<?php
$specialties = [
    ['gp', 'General practice', 'gps', 'The everyday doctor, finally given great tools.',
     'OPD-ready out of the box. Vitals, history, common Rx templates, and a fast triage flow for walk-ins.',
     ['Triage view with vitals on entry', 'Common Rx templates for top 200 conditions', 'Family history & chronic care tracking', 'Patient queue with average wait estimate']],
    ['homeo', 'Homeopathy', 'homeopaths', 'Built for case taking, not just clicking.',
     'Long-form repertory notes, potency picker, antidote warnings, and a built-in remedy database with 3,200 entries.',
     ['Repertory case-taking template', 'Potency picker (6X — 1M) with notes', 'Antidote & follow-up rules engine', 'Miasmatic classification tags']],
    ['dental', 'Dental', 'dentists', 'A tooth-shaped chart, not a clipboard.',
     'Visual dental charting, treatment plans with quotes, and image attachments per tooth — all in the patient record.',
     ['FDI/Palmer/Universal numbering', 'Treatment plan with multi-visit quotes', 'Image attach per quadrant or tooth', 'Recall reminders by procedure type']],
    ['derma', 'Dermatology', 'dermatologists', 'Before, after, and everything in between.',
     'Photo timelines per body area, side-by-side compare, and annotation tools designed for skin and lesion tracking.',
     ['Body-map photo logging', 'Side-by-side before/after compare', 'Lesion annotation & measurement', 'Procedure-linked consent forms']],
    ['peds', 'Pediatrics', 'pediatricians', 'Watch them grow, on real percentile curves.',
     'WHO growth charts, vaccination scheduler, parent-facing summaries — and dosing by weight, automatically.',
     ['WHO percentile growth charts', 'Vaccination scheduler with reminders', 'Weight-based Rx dosing assistant', 'Parent-facing visit summary']],
    ['physio', 'Physiotherapy', 'physiotherapists', 'Programs your patients actually follow.',
     'Build exercise programs from a 600-video library and send them to your patient as a follow-along plan in WhatsApp.',
     ['Video library: 600+ exercises', 'Weekly program builder', 'Patient follow-along with check-ins', 'Outcome scales (ODI, NDI, etc.)']],
];
$current = $specialties[0];
?>
<section class="bg-grey" id="specialties">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Specialty modes</span>
            <h2 class="h-section">Built for your specialty.</h2>
            <p class="lede">Not a generic record system bent into your workflow. Real tools, real templates, real specialty knowledge baked in.</p>
        </div>

        <div class="spec-tabs reveal" style="margin-bottom: 56px;">
            <?php foreach ($specialties as $i => $s): ?>
            <button class="spec-tab <?= $i === 0 ? 'active' : '' ?>"><?= e($s[1]) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="spec-showcase">
            <div class="desc reveal">
                <span class="eyebrow">For <?= e(strtolower($current[1])) ?></span>
                <h3><?= e($current[3]) ?></h3>
                <p class="lede"><?= e($current[4]) ?></p>
                <div class="spec-features">
                    <?php foreach ($current[5] as $f): ?>
                    <div class="spec-feat">
                        <span class="tick">✓</span>
                        <span><?= e($f) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 28px;">
                    <a href="/<?= e($current[2]) ?>" class="btn btn-dark">Explore <?= e(strtolower($current[1])) ?> setup →</a>
                </div>
            </div>
            <div class="reveal">
                <!-- Simplified specialty mock — a placeholder card. Detailed mocks per specialty live on the per-specialty pages. -->
                <div style="background: #fff; border: 0.5px solid var(--line); border-radius: 14px; padding: 28px; min-height: 360px;">
                    <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 18px;"><?= e($current[1]) ?> · Live demo</div>
                    <div style="font-size: 18px; font-weight: 500; margin-bottom: 24px;"><?= e($current[3]) ?></div>
                    <?php foreach ($current[5] as $f): ?>
                    <div style="display: flex; gap: 12px; padding: 12px 0; border-bottom: 0.5px solid var(--line); align-items: center;">
                        <span style="width: 28px; height: 28px; border-radius: 8px; background: var(--teal-50); color: var(--teal-800); display: grid; place-items: center; font-weight: 600;">✓</span>
                        <span style="font-size: 14px; color: var(--ink-2);"><?= e($f) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FEATURE DEEP DIVES ============ -->
<div id="features">
    <?php
    $featureDives = [
        ['#fff', false, 'QR patient card', 'Scan. Load. See everything.',
         'Every patient gets a printable card with a unique encrypted QR. Tap it with any phone or webcam and the full chart loads in under 200 milliseconds — vitals, allergies, last visit, the works.',
         'Learn how QR cards work', '📱'],
        ['var(--bg-2)', true, 'Rx via WhatsApp', 'Prescriptions, delivered before they leave the chair.',
         'Every signed digital Rx gets a PDF copy and a WhatsApp message sent automatically — to your patient, to the partner pharmacy if you want, and to the family member they share with. No more lost paper, no more 11pm photo requests.',
         'See the full prescription workflow', '💬'],
        ['#fff', false, 'Vitals trends', 'See the full picture, visit by visit.',
         'BP, blood sugar, weight, oxygen — every reading flows into a clean trend chart you can review during a 30-second walk into the room. The chart knows your target band. Your patient sees the same view on their app.',
         'Explore vitals & chart modules', '📈'],
    ];
    foreach ($featureDives as [$bg, $reverse, $eyebrow, $h, $body, $link, $mockIcon]):
    ?>
    <section style="background: <?= $bg ?>; padding: 120px 0;">
        <div class="wrap">
            <div class="feature-row<?= $reverse ? ' reverse' : '' ?>">
                <div class="feature-text reveal">
                    <span class="eyebrow"><?= e($eyebrow) ?></span>
                    <h3><?= e($h) ?></h3>
                    <p class="lede"><?= e($body) ?></p>
                    <a href="/features" class="btn-link"><?= e($link) ?> →</a>
                </div>
                <div class="reveal">
                    <div style="background: #fff; border: 0.5px solid var(--line); border-radius: 14px; padding: 36px; min-height: 320px; display: grid; place-items: center; text-align: center;">
                        <div>
                            <div style="font-size: 72px; margin-bottom: 12px;"><?= $mockIcon ?></div>
                            <div style="font-size: 12px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em;"><?= e($eyebrow) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endforeach; ?>
</div>

<!-- ============ PRICING ============ -->
<?php
$plans = [
    ['Starter', 0, 'For solo doctors getting started', 'Start free', 'btn-dark', false, null, 'starter', [
        'Patient records (up to 200)', 'Appointments + reminders', 'Basic Rx (digital signature)', '1 user, 1 location', 'Community support',
    ]],
    ['Clinic', 29, 'For single-location clinics', 'Start with Clinic', 'btn-dark', false, null, '', [
        'Unlimited records & visits', 'WhatsApp Rx delivery', '5 modules included', 'Up to 3 users', 'Email support',
    ]],
    ['Practice', 79, 'Most popular for multi-doc clinics', 'Get Practice', 'btn-primary', true, 'Most chosen', '', [
        'Everything in Clinic, plus:', '12 modules included', 'Up to 10 users', 'Analytics & reports', 'Priority chat support',
    ]],
    ['Hospital', 199, 'For multi-location & hospitals', 'Talk to sales', 'btn-dark', false, null, '', [
        'Everything in Practice, plus:', 'All 24 modules', 'Unlimited users & locations', 'SSO, audit logs, SLA', 'Dedicated success manager',
    ]],
];
?>
<section id="pricing">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Pricing</span>
            <h2 class="h-section">Simple pricing. No surprises.</h2>
            <p class="lede">Start free, forever. Upgrade only when your clinic outgrows it. Build a custom plan from the marketplace whenever you like.</p>
        </div>

        <div class="pricing-grid">
            <?php foreach ($plans as [$name, $price, $sub, $cta, $ctaStyle, $featured, $tag, $tier, $feats]): ?>
            <div class="price-card reveal<?= $featured ? ' featured' : '' ?><?= $tier === 'starter' ? ' starter' : '' ?>">
                <?php if ($tag): ?><span class="price-tag"><?= e($tag) ?></span><?php endif; ?>
                <div>
                    <div class="price-name"><?= e($name) ?></div>
                    <div class="price-mute" style="font-size: 12px; color: var(--mute); margin-top: 4px; min-height: 32px;"><?= e($sub) ?></div>
                </div>
                <div class="price-amt">
                    <?php if ($price === 0): ?>
                        <span>Free</span>
                    <?php else: ?>
                        <span class="currency">$</span><?= (int) $price ?><span class="per">/month</span>
                    <?php endif; ?>
                </div>
                <ul class="price-feat">
                    <?php foreach ($feats as $f): ?>
                    <li><span class="tick">✓</span><span><?= e($f) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn <?= e($ctaStyle) ?> price-cta"><?= e($cta) ?></a>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 36px; font-size: 14px; color: var(--mute);">
            Or <a href="#modules" style="color: var(--teal-600); font-weight: 500;">build your own plan</a> from the module marketplace.
            <div style="font-size: 12px; margin-top: 6px;">All prices in USD · Local currency at checkout · Cancel anytime</div>
        </div>
    </div>
</section>

<!-- ============ TESTIMONIALS ============ -->
<?php
$testimonials = [
    ['Dr. Aarav Sharma', 'AS', 'GP · Mumbai, India', 'I switched from paper in two weekends. The QR patient cards alone cut my check-in time by 80%. My nurses cried with joy.'],
    ['Dr. Priya Iyer', 'PI', 'Homeopath · Pune, India', 'Finally, software that knows what a repertory case-taking sheet looks like. The remedy DB is shockingly thorough.'],
    ['Dr. James Whitfield', 'JW', 'Dentist · Toronto, Canada', 'The dental chart is genuinely beautiful. We added the imaging module after three weeks and the timelines sold our patients on whitening.'],
    ['Dr. Amara Okonkwo', 'AO', 'Pediatrician · Lagos, Nigeria', 'Parents love the weight-based dosing and the summary they get on WhatsApp. I love that I no longer fight a spreadsheet.'],
    ['Dr. Sofia Marín', 'SM', 'Dermatologist · Madrid, Spain', 'The before/after compare convinced me to stay. The pricing convinced my accountant. We pay €34/month for what we use.'],
    ['Dr. Ben Carter', 'BC', 'GP · Bristol, UK', 'Calm, fast, no junk. It feels like a tool made for clinicians, not for the people selling to clinicians.'],
];
?>
<section class="bg-grey">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">From the clinics</span>
            <h2 class="h-section">Clinics that switched.<br>And never looked back.</h2>
        </div>
        <div class="tgrid">
            <?php foreach ($testimonials as $i => [$name, $initials, $spec, $quote]):
                $delay = ($i % 3) * 80;
            ?>
            <div class="tcard reveal" style="transition-delay: <?= $delay ?>ms;">
                <div class="stars">★★★★★</div>
                <blockquote>"<?= e($quote) ?>"</blockquote>
                <div class="tperson">
                    <div class="tavatar"><?= e($initials) ?></div>
                    <div>
                        <div class="nm"><?= e($name) ?></div>
                        <div class="sp"><?= e($spec) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ FAQ ============ -->
<?php
$faqs = [
    ['Can I really pick only the modules I need?', 'Yes. Every module is independent — you can add, remove, or pause any module at any time. Your bill updates immediately and we never charge for unused modules.'],
    ['What does "free forever" mean?', 'Patient records and appointments stay free forever, up to 200 active patients. No trial expiry, no credit card needed. Real free, not "free until we trap you" free.'],
    ['Is my patient data private and secure?', 'HIPAA, GDPR, and India DPDP compliant. Data is encrypted at rest and in transit. You can export everything as portable JSON or PDF anytime.'],
    ['Can I migrate from another system?', 'Yes — we import from Practo, Drchrono, SimplePractice, Cliniko, and most spreadsheets. Migration is free and our team helps for clinics with over 500 records.'],
    ['Does it work offline?', "Yes. The desktop and tablet apps cache your day's schedule and records locally. When you reconnect, everything syncs automatically."],
    ['What languages are supported?', 'The interface is in 18 languages including English, Hindi, Spanish, Portuguese, Arabic, Mandarin, French, and Bahasa. Prescription printing supports any UTF-8 script.'],
];
?>
<section id="faq" x-data="{ open: 0 }">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Questions</span>
            <h2 class="h-section">Everything you'd ask in a sales call.</h2>
        </div>
        <div class="faq-list reveal">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
            <div class="faq-item" :class="open === <?= $i ?> ? 'open' : ''">
                <button type="button" class="faq-q" @click="open = open === <?= $i ?> ? -1 : <?= $i ?>">
                    <span><?= e($q) ?></span>
                    <span class="plus"></span>
                </button>
                <div class="faq-a" x-show="open === <?= $i ?>" x-collapse><?= e($a) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
