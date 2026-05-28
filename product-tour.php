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
                [['✓', 'Smart defaults', 'The right sections show for your specialty.'], ['🧾', 'One plan', '₹1,499/month — everything included.'], ['🛡️', 'Your control', 'Toggle optional sections on or off per clinic.']]],
        ],
    ],
];

require __DIR__ . '/partials/header.php';
?>

<section style="padding: 140px 0 60px; text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(15,155,110,0.06) 0%, transparent 60%); pointer-events: none;"></div>
    <div class="wrap" style="position: relative; max-width: 820px;">
        <span class="eyebrow" style="display: block; margin-bottom: 16px;">Product tour</span>
        <h1 class="h-display" style="font-size: clamp(40px, 5.5vw, 60px); letter-spacing: -1.3px;">A guided walk through every screen.</h1>
        <p class="lede" style="font-size: 19px; margin-top: 22px; max-width: 640px; margin-left: auto; margin-right: auto;">
            Seven chapters, twenty-plus screens. The complete tour of how eClinicPro feels in your hands.
        </p>
    </div>
</section>

<!-- Sticky chapter TOC -->
<div style="position: sticky; top: 56px; z-index: 50; background: rgba(255,255,255,0.85); backdrop-filter: saturate(180%) blur(20px); border-bottom: 0.5px solid var(--line); padding: 14px 0;">
    <div class="wrap" style="display: flex; gap: 4px; overflow-x: auto; justify-content: center; flex-wrap: wrap;">
        <?php foreach ($chapters as $i => $c): ?>
        <a href="#<?= e($c['id']) ?>" class="spec-tab" style="white-space: nowrap;">
            <span style="color: var(--mute); font-family: 'JetBrains Mono', monospace; font-size: 11px; margin-right: 6px;"><?= $i + 1 ?></span><?= e($c['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Chapters -->
<?php foreach ($chapters as $cIdx => $c): ?>
<section id="<?= e($c['id']) ?>" style="padding: 80px 0; border-top: 0.5px solid var(--line);">
    <div class="wrap">
        <div class="reveal" style="max-width: 720px; margin: 0 auto 60px; text-align: center;">
            <span class="eyebrow">Chapter <?= $cIdx + 1 ?></span>
            <h2 class="h-section" style="margin-top: 10px;"><?= e($c['title']) ?></h2>
            <p class="lede"><?= e($c['blurb']) ?></p>
        </div>

        <?php foreach ($c['screens'] as $s ): list($num, $title, $desc, $icon, $bullets) = $s; ?>
        <div class="screen-block reveal" style="display: grid; grid-template-columns: 1fr 1.4fr; gap: 60px; padding: 50px 0; border-top: 0.5px solid var(--line); align-items: start;">
            <div class="meta">
                <div>
                    <span class="num" style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--mute); letter-spacing: 0.04em;"><?= e($num) ?></span>
                    <h3 style="font-size: 26px; font-weight: 500; letter-spacing: -0.4px; margin-top: 6px;"><?= e($title) ?></h3>
                </div>
                <p style="margin-top: 14px; font-size: 15px; color: var(--ink-2); line-height: 1.65;"><?= e($desc) ?></p>

                <div class="bullets" style="margin-top: 24px; display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($bullets as [$bIcon, $bTitle, $bSub]): ?>
                    <div class="bullet" style="display: flex; gap: 12px; align-items: flex-start;">
                        <div class="ico" style="width: 24px; height: 24px; border-radius: 6px; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center; font-size: 11px; flex-shrink: 0; margin-top: 2px;"><?= $bIcon ?></div>
                        <div>
                            <b style="font-size: 13.5px; font-weight: 500; display: block;"><?= e($bTitle) ?></b>
                            <span style="font-size: 12.5px; color: var(--mute);"><?= e($bSub) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="screen-frame" style="background: linear-gradient(135deg, var(--bg-2) 0%, var(--bg-3) 100%); border: 0.5px solid var(--line); border-radius: 16px; padding: 36px; min-height: 360px; display: grid; place-items: center; text-align: center;">
                <div>
                    <div style="font-size: 80px; margin-bottom: 14px;"><?= $icon ?></div>
                    <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;">Screen <?= e($num) ?></div>
                    <div style="font-size: 14px; color: var(--ink-2); margin-top: 4px;"><?= e($title) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<style>
@media (max-width: 800px) {
    .screen-block { grid-template-columns: 1fr !important; gap: 30px !important; }
}
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
