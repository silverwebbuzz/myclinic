<?php
// =====================================================================
// product-tour.php — visual walk-through of every major screen
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Product Tour — eClinicPro';
$metaDesc = 'A guided walk-through of every screen — from the daily dashboard to specialty tools, business reporting, and patient experience.';
$activePage = 'tour';

$chapters = [
    [
        'id' => 'daily', 'label' => 'Daily flow',
        'title' => 'A day at the clinic.',
        'blurb' => 'The screens you live in. Designed for speed, scannability, and the rhythm of a busy clinic.',
        'screens' => [
            ['1.1', "Today's dashboard", "Open the app and see exactly what your day looks like. Today's queue, waiting times, key metrics, modules in use. No setup needed — it just knows.", '📊',
                [['⚡', 'Live queue', 'Updates as patients arrive and move through.'], ['📈', 'Daily KPIs', 'Average wait, revenue, retention — at a glance.'], ['🔍', 'Smart greeting', 'AI surfaces the patients needing attention first.']]],
            ['1.2', 'Weekly calendar', 'Drag to reschedule. Click empty space to book. Multi-doctor, multi-room — colour-coded by visit type. Walk-ins join the queue, scheduled visits appear here.', '📅',
                [['📅', 'Drag & drop', 'Reschedule with a single drag. Conflicts auto-block.'], ['💬', 'Auto reminders', 'WhatsApp + SMS 24h and 1h before each visit.'], ['🎥', 'Mixed slots', 'In-person and telemedicine in one calendar.']]],
        ],
    ],
    [
        'id' => 'record', 'label' => 'Patient record',
        'title' => 'The patient, top to bottom.',
        'blurb' => 'Every visit, prescription, lab, photo, payment — one record, instantly searchable.',
        'screens' => [
            ['2.1', 'Patient profile', 'Everything about a patient on one screen. Tabs along the top let you drill into visits, prescriptions, vitals, files, and bills. Allergy and chronic-condition flags follow you everywhere.', '👤',
                [['🛡️', 'Allergy flags', 'Surface across every Rx and procedure screen.'], ['📋', 'Visit history', 'Twelve months on one scrollable timeline.'], ['🖼️', 'Files & images', 'Inline preview for PDFs, X-rays, scans.']]],
            ['2.2', 'Visit notes', 'SOAP, free-form, or specialty templates. Voice-dictate or type. Auto-saves every 2 seconds. The vitals strip stays pinned so you always have the numbers in view.', '📝',
                [['📋', 'SOAP or free', 'Pick the format. Templates by specialty.'], ['⚡', 'Voice dictation', 'English, Hindi & Gujarati. Edit before signing.'], ['✓', 'Auto-save', 'Every 2 seconds. Nothing ever lost.']]],
            ['2.3', 'Vitals trends', 'Every reading flows into a chart you can review during a 30-second walk into the room. Target bands shaded. Trend direction called out. Patient sees the same view on their app.', '📈',
                [['📊', 'Target bands', 'The system knows what your patient should be at.'], ['📈', 'Auto trends', 'Improving, stable, worsening — labelled clearly.'], ['🌐', 'Shared view', 'Patient sees the same chart in their app.']]],
        ],
    ],
    [
        'id' => 'rx', 'label' => 'Prescriptions',
        'title' => 'From writing to delivery.',
        'blurb' => 'A drug DB that catches interactions and allergies. A delivery channel patients actually use.',
        'screens' => [
            ['3.1', 'Prescription writer', 'Type three letters of a drug. The 200,000-item DB suggests with dosage, interaction, and allergy warnings. Common Rx templates are one tap away. Sign with biometric, send anywhere.', '℞',
                [['🔍', 'Smart search', 'Drug DB autocompletes with dose and form.'], ['🛡️', 'Allergy check', 'Cross-references the patient automatically.'], ['✓', 'Refill control', 'Set refill count per drug, per patient.']]],
            ['3.2', 'WhatsApp Rx delivery', "The moment you sign, a clean PDF is sent to the patient's WhatsApp — and optionally to a family caregiver or pharmacy. Read-receipts come back to you. No more 11pm photo requests.", '💬',
                [['💬', 'Instant delivery', 'Sent before the patient leaves the chair.'], ['📋', 'PDF + signed', 'Legally compliant in every region we serve.'], ['📊', 'Read receipts', "See who opened it, who didn't, who picked up."]]],
        ],
    ],
    [
        'id' => 'clinical', 'label' => 'Specialty tools',
        'title' => 'Built for your specialty.',
        'blurb' => "The modules that make eClinicPro feel made for you. Add the ones you need; ignore the rest.",
        'screens' => [
            ['4.1', 'Dental chart', 'Tooth-by-tooth visual chart. Tap a tooth — see every note, image, and procedure since the first visit. FDI, Palmer, or Universal numbering — pick at setup.', '🦷',
                [['🦷', 'Per-tooth log', 'Every action attached to the right tooth.'], ['🖼️', 'Image attach', 'X-ray or intraoral photo, drag and drop.'], ['🧾', 'Plan + quote', 'Auto-generates multi-visit quote PDF.']]],
            ['4.2', 'Photo timeline (derma)', 'Body-map photo logging, side-by-side compare, lesion measurement. Tap a body region, attach photos — they organize by area and date, forever.', '🖼️',
                [['🖼️', 'Body map', 'Photos pinned to anatomical zones.'], ['🔍', 'Compare any two', 'Slide wipe between visits.'], ['📐', 'Measurement', 'Area, diameter, asymmetry tracked.']]],
            ['4.3', 'Growth charts (peds)', 'WHO percentile bands for weight, height, head circumference, BMI. Auto-plotted at every visit. Vaccine reminders woven in. Weight-based dosing flows from the latest measurement.', '🌱',
                [['🌱', 'WHO bands', 'Country-specific schedules supported.'], ['📅', 'Vaccine due', '30-day reminder to parents via WhatsApp.'], ['℞', 'Weight dosing', 'Rx dose calculates from latest weight.']]],
            ['4.4', 'Repertory (homeo)', 'Long-form case taking — mental generals, physical generals, particulars, modalities. A 3,200-remedy database with antidote rules and miasm tags. Built with classical homeopaths.', '🧫',
                [['🧫', '3,200 remedies', 'Searchable with potency and antidotes.'], ['📋', 'Case taking', 'Real long-form template, editable per case.'], ['🛡️', 'Antidote alerts', 'Warns when an antidote shows up in chart.']]],
            ['4.5', 'Exercise plans (physio)', 'Drag from a 600-video library into a 7-day program. Send to the patient on WhatsApp; they tap to play and check off as they go. Adherence rolls back into your view.', '🤸',
                [['🎥', '600+ videos', '8 languages of voice-over.'], ['💬', 'Follow-along', 'Patient checks off exercises in chat.'], ['📊', 'Adherence', "See who's doing it, who isn't."]]],
        ],
    ],
    [
        'id' => 'business', 'label' => 'The business',
        'title' => 'Run the business of medicine.',
        'blurb' => 'Invoices, payments, pharmacy stock, revenue analytics — without an accountant or a spreadsheet.',
        'screens' => [
            ['5.1', 'Analytics & reports', 'Revenue, visits, retention, top procedures — month-over-month, year-over-year. KPIs at the top, charts that respond to your filters, exports to CSV with one click.', '📊',
                [['📊', 'KPI grid', 'Revenue, visits, new patients, no-shows.'], ['📈', 'Cohort retention', 'See who came back, and why.'], ['📋', 'Export', 'CSV, PDF, or scheduled email reports.']]],
            ['5.2', 'Pharmacy inventory', 'Every SKU, every batch, every expiry — tracked. Low-stock and expiring-soon alerts surface before they bite. Reorder drafts assemble themselves from your usage patterns.', '💊',
                [['💊', 'Batch tracking', 'Lot numbers and expiry per pack.'], ['⚡', 'Smart alerts', 'Low stock + expiring within 30 days.'], ['🧾', 'Auto-reorder', 'Drafts the PO so you just confirm.']]],
            ['5.3', 'Invoicing & payments', 'Every visit generates an invoice. Multi-currency, GST/VAT-ready. Pay by card, UPI, bank transfer, Apple/Google Pay, or cash. Reconciled automatically against the calendar.', '🧾',
                [['🧾', 'Multi-currency', 'Pay in local currency, settle in yours.'], ['💳', 'Many methods', 'Card, UPI, bank, wallet, cash.'], ['✓', 'Auto-reconcile', 'Payments match visits automatically.']]],
        ],
    ],
    [
        'id' => 'patient', 'label' => 'Patient experience',
        'title' => 'What your patients see.',
        'blurb' => "The white-labeled side of eClinicPro. Branded with your clinic's name, designed to make patients want to come back.",
        'screens' => [
            ['6.1', 'QR patient card', 'Every patient gets a printable card with an encrypted QR. Tap with any phone or webcam to load the full chart in under 200ms. Cuts check-in time by 80%.', '📱',
                [['📱', '200ms load', 'Faster than typing a name.'], ['🛡️', 'Encrypted', 'No PHI in the QR itself.'], ['📋', 'Wallet card', 'Printable or Apple/Google Wallet.']]],
            ['6.2', 'Patient mobile portal', "Records, prescriptions, bills, upcoming visits, vital trends — in one app, branded with your clinic's name. Patients tap to request refills, see results, message your front desk.", '📱',
                [['🌐', 'White-labeled', 'Your clinic name and brand.'], ['℞', 'Refill request', 'One tap. You approve on your end.'], ['📊', 'Their own data', 'Vitals, growth, lab results — theirs.']]],
            ['6.3', 'Telemedicine', "HD video in the browser — no app install, works on 3G. The patient's record is right there on screen during the call. Sign and send the Rx without leaving the room.", '🎥',
                [['🎥', 'In-browser HD', 'No app for the patient to install.'], ['📋', 'Chart in-call', 'Vitals, allergies, last visit visible.'], ['℞', 'Sign mid-call', 'Rx goes out before the call ends.']]],
        ],
    ],
    [
        'id' => 'setup', 'label' => 'Setup & control',
        'title' => 'Tune the system to your clinic.',
        'blurb' => 'Pick your specialty and the right tools appear automatically. One simple plan — no module juggling, no surprise bills.',
        'screens' => [
            ['7.1', 'Specialty setup', 'Choose your specialty once. The visit screen, vitals, and case forms adapt to how you actually work — homeopathy case-taking, dental charting, pediatric growth, and more. Change it any time from Settings.', '🛠️',
                [['✓', 'Smart defaults', 'The right sections show for your specialty.'], ['🧾', 'One plan', '₹16,000/year — everything included.'], ['🛡️', 'Your control', 'Toggle optional sections on or off per clinic.']]],
        ],
    ],
];

require __DIR__ . '/partials/header.php';

// Flat list of every screen, so the hero can show an honest count instead of
// the hard-coded "twenty-plus".
$screenCount = array_sum(array_map(fn($c) => count($c['screens']), $chapters));
?>

<!-- ============ TOUR HERO ============ -->
<section class="pt-hero">
    <div class="pt-hero-glow" aria-hidden="true"></div>
    <div class="wrap pt-hero-inner">
        <span class="eyebrow">Product tour</span>
        <h1 class="h-display pt-hero-h">A guided walk through every screen.</h1>
        <p class="lede pt-hero-sub">
            <?= count($chapters) ?> chapters, <?= $screenCount ?> screens. See exactly how eClinicPro feels in
            your hands — from the daily queue to specialty tools, billing and the patient app.
        </p>
        <div class="pt-hero-cta">
            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">Start 30-day free trial</a>
            <a href="/features" class="btn btn-ghost-dark btn-lg">See all features</a>
        </div>
    </div>
</section>

<!-- Sticky chapter TOC — offset to clear the 80px fixed nav. -->
<div class="pt-toc">
    <div class="wrap pt-toc-inner">
        <?php foreach ($chapters as $i => $c): ?>
        <a href="#<?= e($c['id']) ?>" class="spec-tab pt-toc-tab">
            <span class="pt-toc-num"><?= $i + 1 ?></span><?= e($c['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Chapters -->
<?php foreach ($chapters as $cIdx => $c): ?>
<section id="<?= e($c['id']) ?>" class="pt-chapter">
    <div class="wrap">
        <div class="pt-chapter-head reveal">
            <span class="eyebrow">Chapter <?= $cIdx + 1 ?></span>
            <h2 class="h-section pt-chapter-title"><?= e($c['title']) ?></h2>
            <p class="lede"><?= e($c['blurb']) ?></p>
        </div>

        <?php foreach ($c['screens'] as $sIdx => $s): list($num, $title, $desc, $icon, $bullets) = $s; ?>
        <div class="pt-screen reveal <?= $sIdx % 2 ? 'is-flip' : '' ?>">
            <div class="pt-screen-meta">
                <span class="pt-screen-num"><?= e($num) ?></span>
                <h3 class="pt-screen-title"><?= e($title) ?></h3>
                <p class="pt-screen-desc"><?= e($desc) ?></p>

                <div class="pt-bullets">
                    <?php foreach ($bullets as [$bIcon, $bTitle, $bSub]): ?>
                    <div class="pt-bullet">
                        <div class="pt-bullet-ico"><?= $bIcon ?></div>
                        <div>
                            <b class="pt-bullet-title"><?= e($bTitle) ?></b>
                            <span class="pt-bullet-sub"><?= e($bSub) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Browser-chrome mockup frame: reads as a real product window. -->
            <div class="pt-frame">
                <div class="pt-frame-bar">
                    <span class="pt-dot"></span><span class="pt-dot"></span><span class="pt-dot"></span>
                    <span class="pt-frame-url">eclinicpro.com · Screen <?= e($num) ?></span>
                </div>
                <div class="pt-frame-body">
                    <div class="pt-frame-icon"><?= $icon ?></div>
                    <div class="pt-frame-label"><?= e($title) ?></div>
                    <div class="pt-frame-rows">
                        <span class="pt-frame-row w70"></span>
                        <span class="pt-frame-row w90"></span>
                        <span class="pt-frame-row w55"></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<!-- ============ CLOSING CTA ============ -->
<section class="pt-end">
    <div class="wrap pt-end-inner reveal">
        <h2 class="h-section pt-end-h">That’s the whole tour. Try it on your own clinic.</h2>
        <p class="lede pt-end-sub">One plan, everything included — ₹16,000/year. 30-day free trial, no card required.</p>
        <div class="pt-hero-cta">
            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">Start 30-day free trial</a>
            <a href="/#pricing" class="btn btn-ghost-dark btn-lg">See pricing</a>
        </div>
    </div>
</section>

<style>
/* ===== Product tour — scoped styles (16px body baseline) ===== */
.pt-hero {
    position: relative;
    padding: 150px 0 64px;
    text-align: center;
    overflow: hidden;
}
.pt-hero-glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 50% 0%, rgba(15, 155, 110, 0.08) 0%, transparent 60%);
    pointer-events: none;
}
.pt-hero-inner { position: relative; max-width: 820px; }
.pt-hero .eyebrow { display: block; margin-bottom: 16px; }
.pt-hero-h { font-size: clamp(40px, 5.5vw, 60px); letter-spacing: -1.3px; }
.pt-hero-sub {
    font-size: 19px;
    margin: 22px auto 0;
    max-width: 660px;
}
.pt-hero-cta {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 30px;
}

/* Sticky chapter nav */
.pt-toc {
    position: sticky;
    top: 80px;
    z-index: 50;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: saturate(180%) blur(20px);
    -webkit-backdrop-filter: saturate(180%) blur(20px);
    border-block: 0.5px solid var(--line);
    padding: 12px 0;
}
.pt-toc-inner {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    justify-content: center;
    flex-wrap: wrap;
}
.pt-toc-tab { white-space: nowrap; }
.pt-toc-num {
    color: var(--teal-600);
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    margin-right: 7px;
}

/* Chapter */
.pt-chapter {
    padding: 80px 0;
    border-top: 0.5px solid var(--line);
}
.pt-chapter-head {
    max-width: 720px;
    margin: 0 auto 56px;
    text-align: center;
}
.pt-chapter-title { margin: 10px 0 14px; }

/* Screen row */
.pt-screen {
    display: grid;
    grid-template-columns: 1fr 1.25fr;
    gap: 56px;
    padding: 48px 0;
    align-items: center;
    border-top: 0.5px solid var(--line);
}
.pt-screen:first-of-type { border-top: 0; }
.pt-screen.is-flip .pt-screen-meta { order: 2; }
.pt-screen.is-flip .pt-frame { order: 1; }

.pt-screen-num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    color: var(--teal-600);
    letter-spacing: 0.04em;
}
.pt-screen-title {
    font-size: 28px;
    font-weight: 500;
    letter-spacing: -0.5px;
    margin: 8px 0 0;
}
.pt-screen-desc {
    margin-top: 14px;
    font-size: 16px;
    color: var(--ink-2);
    line-height: 1.65;
}

.pt-bullets {
    margin-top: 26px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.pt-bullet { display: flex; gap: 12px; align-items: flex-start; }
.pt-bullet-ico {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--teal-50);
    color: var(--teal-700);
    display: grid;
    place-items: center;
    font-size: 14px;
    flex-shrink: 0;
}
.pt-bullet-title {
    font-size: 15px;
    font-weight: 600;
    display: block;
    color: var(--ink);
}
.pt-bullet-sub {
    font-size: 14px;
    color: var(--mute);
    line-height: 1.5;
}

/* Mockup frame */
.pt-frame {
    border: 1px solid var(--line);
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 24px 60px -24px rgba(0, 0, 0, 0.18);
}
.pt-frame-bar {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 12px 16px;
    background: var(--bg-2);
    border-bottom: 1px solid var(--line);
}
.pt-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.14);
}
.pt-frame-url {
    margin-left: 10px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: var(--mute);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pt-frame-body {
    padding: 44px 36px 48px;
    min-height: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    background: linear-gradient(160deg, var(--bg-3) 0%, #fff 100%);
}
.pt-frame-icon {
    font-size: 64px;
    line-height: 1;
    margin-bottom: 18px;
}
.pt-frame-label {
    font-size: 16px;
    font-weight: 600;
    color: var(--ink);
}
.pt-frame-rows {
    width: min(280px, 80%);
    margin-top: 22px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.pt-frame-row {
    height: 9px;
    border-radius: 999px;
    background: var(--line);
}
.pt-frame-row.w90 { width: 90%; }
.pt-frame-row.w70 { width: 70%; }
.pt-frame-row.w55 { width: 55%; background: var(--teal-100); }

/* Closing CTA */
.pt-end {
    padding: 88px 0 110px;
    border-top: 0.5px solid var(--line);
    background: var(--bg-2);
    text-align: center;
}
.pt-end-inner { max-width: 700px; }
.pt-end-h { letter-spacing: -0.6px; }
.pt-end-sub { font-size: 18px; margin: 16px auto 0; }

@media (max-width: 800px) {
    .pt-screen { grid-template-columns: 1fr; gap: 28px; }
    .pt-screen.is-flip .pt-screen-meta { order: 0; }
    .pt-screen.is-flip .pt-frame { order: 0; }
}
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
