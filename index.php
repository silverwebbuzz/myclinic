<?php
// =====================================================================
// index.php — eClinicPro homepage
// =====================================================================

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/specialty-mocks.php';

$pageTitle = 'eClinicPro — The clinic OS doctors love';
$metaDesc = 'Pick your modules. Pay for what you use. One beautifully simple clinic system for GPs, dentists, homeopaths, and every specialty in between.';
$activePage = '';

$clinicCount = ecp_active_clinic_count();
$countryCount = ecp_country_count();

// WebSite + SearchAction JSON-LD — lets Google show a search box for the brand
// in the SERP (a "sitelinks searchbox") that submits to /find-a-doctor.
$extraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => 'eClinicPro',
    'url'      => 'https://eclinicpro.com',
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => 'https://eclinicpro.com/find-a-doctor?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES) . '</script>';

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
            <div class="reveal" style="display: flex; justify-content: center;">
                <?php render_spec_mock($current[0]); ?>
            </div>
        </div>
    </div>
</section>

<!-- ============ FEATURE DEEP DIVES ============ -->
<div id="features">

<!-- 1. QR PATIENT CARD -->
<section style="background: #fff; padding: 120px 0;">
    <div class="wrap">
        <div class="feature-row">
            <div class="feature-text reveal">
                <span class="eyebrow">QR patient card</span>
                <h3>Scan. Load. See everything.</h3>
                <p class="lede">Every patient gets a printable card with a unique encrypted QR. Tap it with any phone or webcam and the full chart loads in under 200 milliseconds — vitals, allergies, last visit, the works.</p>
                <a href="/features" class="btn-link">Learn how QR cards work →</a>
            </div>
            <div class="reveal">
                <!-- QRMock ported from feature-mocks.jsx -->
                <div class="feature-mock" style="aspect-ratio: 4/3; position: relative; background: linear-gradient(135deg, #F8F9FB 0%, #E8F1FC 100%); display: flex; align-items: center; justify-content: center; border-radius: 14px; overflow: hidden;">
                    <div style="background: #fff; border-radius: 16px; padding: 24px; width: 240px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); transform: rotate(-3deg);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                            <div>
                                <div style="font-size: 9px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600;">Patient ID</div>
                                <div style="font-size: 11px; font-family: 'JetBrains Mono', monospace; color: var(--ink); margin-top: 2px;">MC-AAR-8841</div>
                            </div>
                            <div style="font-size: 11px; font-weight: 600; color: var(--teal-600);">e<span style="color: var(--ink);">ClinicPro</span></div>
                        </div>
                        <!-- QR grid -->
                        <div style="width: 100%; aspect-ratio: 1; background: #fff; display: grid; grid-template-columns: repeat(11, 1fr); gap: 1px;">
                            <?php
                            for ($i = 0; $i < 121; $i++) {
                                $r = intdiv($i, 11); $c = $i % 11;
                                $isCorner = ($r < 3 && $c < 3) || ($r < 3 && $c > 7) || ($r > 7 && $c < 3);
                                $on = ((($i * 7 + intdiv($i, 11) * 3) % 5) < 2) || ($i < 11 && ($i % 4) !== 1) || ($i > 109);
                                $bg = ($isCorner || $on) ? '#0A0A0A' : 'transparent';
                                echo '<div style="background:' . $bg . ';"></div>';
                            }
                            ?>
                        </div>
                        <div style="font-size: 13px; font-weight: 500; margin-top: 14px;">Aarav Sharma</div>
                        <div style="font-size: 10.5px; color: var(--mute);">DOB 1986 · A+ · Allergies: Penicillin</div>
                    </div>

                    <!-- Scan beam -->
                    <div style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); width: 110px; height: 200px; border: 2px solid var(--teal-600); border-radius: 14px; padding: 12px;">
                        <div style="width: 100%; height: 2px; background: var(--teal-400); box-shadow: 0 0 12px var(--teal-400); margin-top: 90px;"></div>
                        <div style="position: absolute; top: -10px; left: -1px; right: -1px; height: 8px; display: flex; justify-content: space-between;">
                            <span style="width: 14px; height: 14px; border-top: 2px solid var(--teal-600); border-left: 2px solid var(--teal-600); border-radius: 4px 0 0 0;"></span>
                            <span style="width: 14px; height: 14px; border-top: 2px solid var(--teal-600); border-right: 2px solid var(--teal-600); border-radius: 0 4px 0 0;"></span>
                        </div>
                    </div>

                    <!-- Status pill -->
                    <div style="position: absolute; bottom: 20px; left: 24px; background: #fff; border-radius: 10px; padding: 8px 12px; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--teal-400);"></span>
                        <span style="font-size: 11px; font-weight: 500;">Loaded in 184ms</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 2. WHATSAPP RX -->
<section style="background: var(--bg-2); padding: 120px 0;">
    <div class="wrap">
        <div class="feature-row reverse">
            <div class="feature-text reveal">
                <span class="eyebrow">Rx via WhatsApp</span>
                <h3>Prescriptions, delivered before they leave the chair.</h3>
                <p class="lede">Every signed digital Rx gets a PDF copy and a WhatsApp message sent automatically — to your patient, to the partner pharmacy if you want, and to the family member they share with. No more lost paper, no more 11pm photo requests.</p>
                <a href="/features" class="btn-link">See the full prescription workflow →</a>
            </div>
            <div class="reveal">
                <!-- WhatsAppMock ported from feature-mocks.jsx -->
                <div class="feature-mock" style="aspect-ratio: 4/3; position: relative; background: #E5DDD5; background-image: radial-gradient(circle, rgba(0,0,0,0.04) 1px, transparent 1px); background-size: 10px 10px; padding: 28px; display: flex; flex-direction: column; justify-content: flex-end; gap: 10px; border-radius: 14px; overflow: hidden;">
                    <!-- Incoming (clinic) -->
                    <div style="align-self: flex-start; max-width: 75%; background: #fff; border-radius: 12px 12px 12px 2px; padding: 8px 12px; box-shadow: 0 1px 1px rgba(0,0,0,0.06);">
                        <div style="font-size: 11px; font-weight: 600; color: var(--teal-700); margin-bottom: 2px;">Sunrise Clinic</div>
                        <div style="font-size: 13px; color: var(--ink-2); line-height: 1.4;">Hi Riya — your prescription from today is ready.</div>
                        <div style="font-size: 10px; color: var(--mute); text-align: right; margin-top: 2px;">9:47</div>
                    </div>
                    <!-- Attachment -->
                    <div style="align-self: flex-start; max-width: 75%; background: #fff; border-radius: 12px 12px 12px 2px; padding: 8px; box-shadow: 0 1px 1px rgba(0,0,0,0.06);">
                        <div style="background: var(--bg-2); border-radius: 8px; padding: 12px 14px; display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 44px; background: #fff; border: 0.5px solid var(--line); border-radius: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0;">
                                <div style="font-size: 8px; font-weight: 700; color: var(--red, #c00);">PDF</div>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 12px; font-weight: 500; color: var(--ink);">Rx_Mehta_18May.pdf</div>
                                <div style="font-size: 10px; color: var(--mute); margin-top: 1px;">2 medicines · Dr. A. Sharma</div>
                            </div>
                        </div>
                        <div style="font-size: 10px; color: var(--mute); text-align: right; margin-top: 4px;">9:47</div>
                    </div>
                    <!-- Reply -->
                    <div style="align-self: flex-end; max-width: 70%; background: #DCF8C6; border-radius: 12px 12px 2px 12px; padding: 8px 12px;">
                        <div style="font-size: 13px; color: var(--ink-2);">Got it! Thank you doctor 🙏</div>
                        <div style="font-size: 10px; color: var(--mute); text-align: right; margin-top: 2px;">9:48 ✓✓</div>
                    </div>
                    <!-- Delivered toast -->
                    <div style="position: absolute; top: 24px; right: 24px; background: #fff; border-radius: 10px; padding: 8px 12px; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                        <span style="width: 18px; height: 18px; border-radius: 50%; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center; font-size: 11px; font-weight: 700;">✓</span>
                        <span style="font-size: 11px; font-weight: 500;">Delivered automatically</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. VITALS TRENDS -->
<section style="background: #fff; padding: 120px 0;">
    <div class="wrap">
        <div class="feature-row">
            <div class="feature-text reveal">
                <span class="eyebrow">Vitals trends</span>
                <h3>See the full picture, visit by visit.</h3>
                <p class="lede">BP, blood sugar, weight, oxygen — every reading flows into a clean trend chart you can review during a 30-second walk into the room. The chart knows your target band. Your patient sees the same view on their app.</p>
                <a href="/features" class="btn-link">Explore vitals & chart modules →</a>
            </div>
            <div class="reveal">
                <!-- VitalsMock ported from feature-mocks.jsx -->
                <?php
                $bpSys = [148, 142, 138, 140, 136, 134, 132];
                $bpDia = [92, 90, 86, 88, 84, 82, 80];
                $months = ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May'];
                $pathFrom = function (array $vals, int $min, int $max): array {
                    $w = 360; $h = 100;
                    $pts = [];
                    $n = count($vals);
                    foreach ($vals as $i => $v) {
                        $x = ($i / ($n - 1)) * $w;
                        $y = $h - (($v - $min) / ($max - $min)) * $h;
                        $pts[] = [$x, $y];
                    }
                    $d = '';
                    foreach ($pts as $i => $p) {
                        $d .= ($i === 0 ? 'M' : 'L') . round($p[0], 1) . ' ' . round($p[1], 1) . ' ';
                    }
                    return ['d' => trim($d), 'pts' => $pts];
                };
                $sys = $pathFrom($bpSys, 120, 160);
                $dia = $pathFrom($bpDia, 70, 100);
                ?>
                <div class="feature-mock" style="aspect-ratio: 4/3; background: #fff; border: 0.5px solid var(--line); border-radius: 14px; overflow: hidden;">
                    <div class="ui-bar" style="display: flex; align-items: center; gap: 6px; padding: 10px 14px; background: var(--bg-2); border-bottom: 0.5px solid var(--line);">
                        <span class="dot r" style="width: 10px; height: 10px; border-radius: 50%; background: #FF5F57;"></span>
                        <span class="dot y" style="width: 10px; height: 10px; border-radius: 50%; background: #FEBC2E;"></span>
                        <span class="dot g" style="width: 10px; height: 10px; border-radius: 50%; background: #28C840;"></span>
                        <span class="url" style="margin-left: 14px; font-size: 11px; color: var(--mute); font-family: 'JetBrains Mono', monospace;">eclinicpro.app/p/aaravsharma/vitals</span>
                    </div>
                    <div style="padding: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 18px;">
                            <div>
                                <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Blood pressure · 7 visits</div>
                                <div style="font-size: 26px; font-weight: 300; letter-spacing: -0.6px; margin-top: 4px;">132<span style="color: var(--mute); font-size: 18px;">/80</span> <span style="font-size: 12px; font-weight: 500; color: #1B8B3D; margin-left: 4px;">↓ improving</span></div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <span style="font-size: 11px; padding: 4px 10px; border-radius: 8px; background: var(--ink); color: #fff; font-weight: 500;">6 mo</span>
                                <span style="font-size: 11px; padding: 4px 10px; border-radius: 8px; color: var(--mute);">1 yr</span>
                                <span style="font-size: 11px; padding: 4px 10px; border-radius: 8px; color: var(--mute);">All</span>
                            </div>
                        </div>

                        <!-- SVG chart -->
                        <svg viewBox="0 0 360 110" style="width: 100%; height: 130px; overflow: visible;">
                            <?php foreach ([0, 25, 50, 75, 100] as $y): ?>
                            <line x1="0" x2="360" y1="<?= $y ?>" y2="<?= $y ?>" stroke="rgba(0,0,0,0.05)" stroke-dasharray="2 3"/>
                            <?php endforeach; ?>
                            <rect x="0" y="30" width="360" height="40" fill="rgba(15,155,110,0.05)"/>
                            <path d="<?= e($sys['d']) ?>" fill="none" stroke="var(--teal-600)" stroke-width="2.2" stroke-linecap="round"/>
                            <?php foreach ($sys['pts'] as $p): ?>
                            <circle cx="<?= round($p[0], 1) ?>" cy="<?= round($p[1], 1) ?>" r="3" fill="var(--teal-600)"/>
                            <?php endforeach; ?>
                            <path d="<?= e($dia['d']) ?>" fill="none" stroke="#3B82F6" stroke-width="2.2" stroke-linecap="round"/>
                            <?php foreach ($dia['pts'] as $p): ?>
                            <circle cx="<?= round($p[0], 1) ?>" cy="<?= round($p[1], 1) ?>" r="3" fill="#3B82F6"/>
                            <?php endforeach; ?>
                        </svg>
                        <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--mute); font-family: 'JetBrains Mono', monospace; margin-top: 4px;">
                            <?php foreach ($months as $m): ?><span><?= e($m) ?></span><?php endforeach; ?>
                        </div>

                        <div style="display: flex; gap: 18px; margin-top: 14px; font-size: 11px; color: var(--mute);">
                            <span style="display: inline-flex; align-items: center; gap: 6px;"><span style="width: 8px; height: 8px; border-radius: 50%; background: var(--teal-600);"></span>Systolic</span>
                            <span style="display: inline-flex; align-items: center; gap: 6px;"><span style="width: 8px; height: 8px; border-radius: 50%; background: #3B82F6;"></span>Diastolic</span>
                            <span style="display: inline-flex; align-items: center; gap: 6px; margin-left: auto;"><span style="width: 12px; height: 8px; border-radius: 2px; background: rgba(15,155,110,0.15);"></span>Target 120/80–130/85</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

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
