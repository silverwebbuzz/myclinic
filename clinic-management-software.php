<?php
// =====================================================================
// clinic-management-software.php — full feature catalog for doctors.
// Served at /clinic-management-software (was /features, 301 in .htaccess).
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$activePage = 'features';
$pageTitle = 'Clinic Management Software — eClinicPro';
$metaDesc  = 'Clinic management software for Indian clinics — patient records, prescriptions, appointments, billing, WhatsApp/SMS, and reports. All included in one simple plan.';

$cats = [
    [
        'id'    => 'records',
        'title' => 'Patient Records',
        'blurb' => 'Encrypted, structured, searchable — and built around how doctors actually think, not how databases work.',
        'icon'  => '',
        'items' => [
            ['<img src="/assets/img/icon/structured-visit-notes.png" alt="Structured visit notes" />', 'Structured visit notes',    'SOAP, free-form, or specialty-shaped templates. Auto-save every 2 seconds.'],
            ['<img src="/assets/img/icon/qr-patient-cards.png" alt="QR patient cards" />', 'QR patient cards',           'Print or send. Tap to load any chart in under 200ms.'],
            ['<img src="/assets/img/icon/attachments.png" alt="Attachments" />', 'Attachments',                'PDFs, X-rays, lab reports, voice memos. Encrypted, viewable inline.'],
            ['<img src="/assets/img/icon/allergy-alert-flags.png" alt="Allergy & alert flags" />', 'Allergy & alert flags',     'Drug allergies and history conditions surface across every screen.'],
            ['<img src="/assets/img/icon/family-history-graph.png" alt="Family history graph" />', 'Family history graph',  'Visual family tree with hereditary condition tagging.'],
            ['<img src="/assets/img/icon/chronic-care-tracking.png" alt="Chronic care tracking" />', 'Chronic care tracking',      'Long-term condition timelines with target band visuals.'],
        ],
    ],
    [
        'id'    => 'visits',
        'title' => 'Appointments & Visits',
        'blurb' => 'A booking system that respects walk-ins, no-shows, and the messiness of real clinical workflow.',
        'icon'  => '',
        'items' => [
            ['<img src="/assets/img/icon/smart-scheduling.png" alt="Smart scheduling" />', 'Smart scheduling',       'Multi-doctor, multi-room. Drag to reschedule. Conflicts auto-blocked.'],
            ['<img src="/assets/img/icon/sms-reminders.png" alt="WhatsApp + SMS reminders" />', 'WhatsApp + SMS reminders', 'Auto-sent 24h and 1h before. Patient can reply to confirm or cancel.'],
            ['<img src="/assets/img/icon/walk-in-triage.png" alt="Walk-in triage" />', 'Walk-in triage',          'Quick-add a walk-in, place them in the queue with severity.'],
            ['<img src="/assets/img/icon/no-show-analytics.png" alt="No-show analytics" />', 'No-show analytics',       'Which patients no-show, which slots, which time of week — track it all.'],
            ['<img src="/assets/img/icon/online-booking-page.png" alt="Online booking page" />', 'Online booking page',     'Branded booking link patients can share. Sync to your calendar live.'],
            ['<img src="/assets/img/icon/telemedicine-slots.png" alt="Telemedicine slots" />', 'Telemedicine slots',      'Mix in-person and video slots in one calendar. Patients pick what works.'],
        ],
    ],
    [
        'id'    => 'rx',
        'title' => 'Prescriptions & Pharmacy',
        'blurb' => 'From the moment you write a script to the moment your patient picks it up — fully tracked.',
        'icon'  => '',
        'items' => [
            ['<img src="/assets/img/icon/prescriptionl.svg" alt="Prescriptionl" />',  'Digital prescriptions',   '200,000-drug DB with dosage, interaction, and pediatric warnings.'],
            ['<img src="/assets/img/icon/whatsapp-rx-delivery.png" alt="WhatsApp Rx delivery" />', 'WhatsApp Rx delivery',     'Signed PDF sent to patient before they leave the chair.'],
            ['<img src="/assets/img/icon/pharmacy-inventory.svg" alt="Pharmacy inventory" />', 'Pharmacy inventory',       'Batch numbers, expiry alerts, low-stock auto-orders.'],
            ['<img src="/assets/img/icon/controlled-substance-log.svg" alt="Controlled substance log" />', 'Controlled substance log', 'Schedule-II compliant audit trail with DEA-ready exports.'],
            ['<img src="/assets/img/icon/refill-management.svg" alt="Refill management" />',  'Refill management',        'Patients request refills via WhatsApp. Approve with one tap.'],
            ['<img src="/assets/img/icon/adherence-tracking.png" alt="Adherence tracking" />', 'Adherence tracking',       'See who refilled, who didn\'t, and follow up automatically.'],
        ],
    ],
    [
        'id'    => 'clinical',
        'title' => 'Clinical Tools',
        'blurb' => 'The specialty-specific toolkit — the right tools appear automatically for your specialty.',
        'icon'  => '',
        'items' => [
            ['<img src="/assets/img/icon/vitals-trend-charts.png" alt="Vitals trend charts" />', 'Vitals trend charts', 'BP, HR, glucose, weight — visual time series with target bands.'],
            ['<img src="/assets/img/icon/lab-orders-and-results.png" alt="Lab orders & results" />', 'Lab orders & results', 'Order, receive, attach. Auto-flagged abnormals.'],
            ['<img src="/assets/img/icon/dental-charting.svg" alt="Dental charting" />', 'Dental charting',      'FDI/Palmer/Universal. Per-tooth notes, images, and treatment plans.'],
            ['<img src="/assets/img/icon/skin-imaging.png" alt="Skin imaging" />', 'Skin imaging',         'Side-by-side before/after with lesion measurement.'],
            ['<img src="/assets/img/icon/who-growth-charts.png" alt="WHO growth charts" />', 'WHO growth charts',    'Pediatric percentile tracking for weight, height, head circumference.'],
            ['<img src="/assets/img/icon/homeo-remedy-db.png" alt="Homeo remedy DB" />', 'Homeo remedy DB',      '3,200 remedies, potency picker, antidote rules, miasm tags.'],
        ],
    ],
    [
        'id'    => 'business',
        'title' => 'Billing & Business',
        'blurb' => 'Run the business of medicine without spreadsheets — and with no enterprise tax.',
        'icon'  => '',
        'items' => [
            ['<img src="/assets/img/icon/invoicing.png" alt="Invoicing" />', 'Invoicing',               'Multi-currency, GST/VAT-ready, tax codes per region.'],
            ['<img src="/assets/img/icon/payments.png" alt="Payments" />', 'Payments',                'Card, UPI, Apple/Google Pay, bank transfer, cash. Reconciled automatically.'],
            ['<img src="/assets/img/icon/revenue-cohort-reports.png" alt="Revenue & cohort reports" />', 'Revenue & cohort reports', 'New vs repeat, by doctor, by procedure, exportable to CSV.'],
            ['<img src="/assets/img/icon/insurance-claims.png" alt="Insurance claims" />', 'Insurance claims',        'Submit, track, and reconcile claims (US, UK, India, UAE).'],
            ['<img src="/assets/img/icon/patient-packages.png" alt="Patient packages" />',  'Patient packages',        'Sell prepaid visit/treatment packages. Auto-deducted at each visit.'],
            ['<img src="/assets/img/icon/multi-location.png" alt="Multi-location" />', 'Multi-location',          'One brand, many branches. Roll-up reporting, cross-branch records.'],
        ],
    ],
    [
        'id'    => 'patient',
        'title' => 'Patient Experience',
        'blurb' => 'The patient-facing layer your front desk wishes they could build. White-labeled and beautiful.',
        'icon'  => '',
        'items' => [
            ['<img src="/assets/img/icon/patient-web-portal.png" alt="Patient Records" />', 'Patient web portal',    'Records, prescriptions, bills, upcoming visits — all in one tab.'],
            ['<img src="/assets/img/icon/video-consults.png" alt="Video consults" />', 'Video consults',        'HD, browser-based, no app install. Works on 3G.'],
            ['<img src="/assets/img/icon/whatsapp-summaries.png" alt="WhatsApp summaries" />', 'WhatsApp summaries',    'After every visit: a clean summary plus next-step instructions.'],
            ['<img src="/assets/img/icon/diet-and-exercise-plans.png" alt="Diet & exercise plans" />', 'Diet & exercise plans', 'Templated programs with daily WhatsApp check-ins.'],
            ['<img src="/assets/img/icon/refill-requests.svg" alt="Refill requests" />',  'Refill requests',       'Patients tap once. You approve or revise.'],
            ['<img src="/assets/img/icon/feedback-collection.png" alt="Feedback collection" />', 'Feedback collection',   'Post-visit NPS via WhatsApp. Scores roll into your reports.'],
        ],
    ],
    [
        'id'    => 'platform',
        'title' => 'Platform & Integrations',
        'blurb' => 'Everything underneath the surface — engineered for clinics, not enterprises.',
        'icon'  => '',
        'items' => [
            ['<img src="/assets/img/icon/india-DPDP-ready.svg" alt="India DPDP ready" />', 'India DPDP ready', 'Encrypted at rest & in transit, per-clinic isolation, real audit logs.'],
            ['<img src="/assets/img/icon/fast-and-reliable.svg" alt="Fast & reliable" />',  'Fast & reliable',  'Built for Indian clinics and networks — quick even on patchy connections.'],
            ['<img src="/assets/img/icon/multi-language.png" alt="Multi-language" />', 'Multi-language',   'Interface and prescriptions in English, Hindi, Gujarati and more.'],
            ['<img src="/assets/img/icon/easy-migration.svg" alt="Easy migration" />', 'Easy migration',   'Import from Practo, spreadsheets, or your existing system.'],
            ['<img src="/assets/img/icon/api-and-webhooks.png" alt="API & webhooks" />', 'API & webhooks',   'Full REST API. Webhook on every meaningful event.'],
            ['<img src="/assets/img/icon/roles-and-audit-logs.png" alt="Roles & audit logs" />', 'Roles & audit logs', 'Granular staff roles and a signed audit trail on every record.'],
        ],
    ],
];

require __DIR__ . '/partials/header.php';

?>

<style>
    /* ═══════════════════════════════════════════
           RESET & BASE
        ═══════════════════════════════════════════ */


    /* ═══════════════════════════════════════════
           HERO
        ═══════════════════════════════════════════ */
    .feat-hero {
        padding: 110px 0 72px;
        text-align: center;
        position: relative;
        overflow: hidden;
        background: #f0f4f1;
    }

    .feat-hero-glow {
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse at 50% 0%, rgba(26, 122, 78, 0.08) 0%, transparent 65%);
        pointer-events: none;
    }

    .feat-hero-inner {
        position: relative;
        max-width: 820px;
        margin: 0 auto;
    }

    /* Eyebrow */
    .hp-eyebrow {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #2a9d6a;
        margin-bottom: 20px;
        position: relative;
        padding: 0 16px;
    }

    .hp-eyebrow::before,
    .hp-eyebrow::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 28px;
        height: 2px;
        background: #2a9d6a;
        border-radius: 2px;
    }

    .hp-eyebrow::before {
        right: 100%;
        margin-right: -12px;
    }

    .hp-eyebrow::after {
        left: 100%;
        margin-left: -12px;
    }

    .h-display {
        font-size: clamp(38px, 5.5vw, 60px);
        font-weight: 700;
        line-height: 1.08;
        letter-spacing: -0.03em;
        color: #0d1f12;
        margin-bottom: 22px;
    }

    .h-display .grad {
        color: #1e8c5c;
    }

    .feat-hero-sub {
        font-size: 18px;
        line-height: 1.75;
        color: #4e6e56;
        max-width: 620px;
        margin: 0 auto;
    }

    /* ═══════════════════════════════════════════
           STATS BAR
        ═══════════════════════════════════════════ */
    .feat-stats-bar {
        background: #ffffff;
        border-top: 1.5px solid #ddeee5;
        border-bottom: 1.5px solid #ddeee5;
        padding: 48px 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
    }

    .stat-item {
        text-align: center;
        padding: 12px 20px;
        position: relative;
    }

    .stat-item+.stat-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 20%;
        height: 60%;
        width: 1.5px;
        background: #ddeee5;
    }

    .stat-num {
        font-size: clamp(32px, 4vw, 48px);
        font-weight: 700;
        color: #1a7a4e;
        letter-spacing: -0.03em;
        line-height: 1;
        margin-bottom: 8px;
    }

    .stat-label {
        font-size: 13.5px;
        color: #6b8a72;
        font-weight: 500;
    }

    /* ═══════════════════════════════════════════
           STICKY CATEGORY NAV TABS
        ═══════════════════════════════════════════ */
    .feat-nav {
        position: sticky;
        top: 0;
        z-index: 50;
        background: rgba(240, 244, 241, 0.92);
        backdrop-filter: saturate(180%) blur(20px);
        -webkit-backdrop-filter: saturate(180%) blur(20px);
        border-bottom: 1.5px solid #ddeee5;
        padding: 14px 0;
    }

    .feat-nav-inner {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        justify-content: center;
        flex-wrap: wrap;
        scrollbar-width: none;
    }

    .feat-nav-inner::-webkit-scrollbar {
        display: none;
    }

    .tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 22px;
        border-radius: 999px;
        border: 1.5px solid #c8ddd0;
        background: #ffffff;
        font-family: 'Inter', sans-serif;
        font-size: 13.5px;
        font-weight: 500;
        color: #3a5742;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        text-decoration: none;
    }

    .tab:hover {
        border-color: #2a9d6a;
        color: #1a7a4e;
        background: #edf8f3;
        transform: translateY(0px);
    }

    .tab.active {
        background: #1a7a4e;
        border-color: #1a7a4e;
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 4px 14px rgba(26, 122, 78, 0.28);
    }

    /* ═══════════════════════════════════════════
           CATEGORY SECTIONS
        ═══════════════════════════════════════════ */
    .feat-body {
        padding: 60px 0 0px;
    }

    .feat-category {
        padding: 30px 0;
        border-top: 1.5px solid #ddeee5;
    }

    .feat-category:first-child {
        border-top: none;
    }

    /* Category header: label + title left, blurb right */
    .feat-cat-head {
        display: grid;
        grid-template-columns: 1fr 1.4fr;
        gap: 60px;
        align-items: end;
        margin-bottom: 40px;
    }

    .cat-count {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #2a9d6a;
        margin-bottom: 10px;
    }

    .cat-title {
        font-size: clamp(24px, 3vw, 32px);
        font-weight: 800;
        color: #0d1f12;
        letter-spacing: -0.02em;
        line-height: 1.15;
    }

    .cat-icon {
        font-size: 28px;
        margin-right: 10px;
        vertical-align: middle;
    }

    .cat-blurb {
        font-size: 16px;
        line-height: 1.75;
        color: #4e6e56;
        padding-top: 6px;
    }

    /* ═══════════════════════════════════════════
           FEATURE CARDS GRID
        ═══════════════════════════════════════════ */
    .feat-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .feat-card {
        background: #ffffff;
        border-radius: 18px;
        padding: 26px 22px 22px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        border: 1.5px solid #e8f0ea;
        cursor: default;
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        position: relative;
        overflow: hidden;
    }

    /* shimmer */
    .feat-card::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(26, 122, 78, 0.045) 0%, transparent 55%);
        opacity: 0;
        transition: opacity 0.22s ease;
        pointer-events: none;
    }

    .feat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 16px 40px rgba(26, 122, 78, 0.13);
        border-color: #a8d8be;
    }

    .feat-card:hover::after {
        opacity: 1;
    }

    /* Icon bubble */
    .feat-card-ic {
        font-size: 22px;
        width: 48px;
        height: 48px;
        background: #edf8f3;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .feat-card:hover .feat-card-ic {
        background: #d2f0e2;
        transform: scale(1.08);
    }
    .feat-card-ic img{width:25px;height:auto;object-fit: contain;filter: brightness(0) saturate(100%) invert(28%) sepia(63%) saturate(1045%) hue-rotate(134deg) brightness(92%) contrast(93%);}

    .feat-card h4 {
        font-size: 14.5px;
        font-weight: 700;
        color: #0d1f12;
        line-height: 1.3;
    }

    .feat-card p {
        font-size: 13px;
        line-height: 1.65;
        color: #6b8a72;
        flex: 1;
    }

    /* ═══════════════════════════════════════════
           PRICING SECTION
        ═══════════════════════════════════════════ */
    /* Deep links (#pricing, #screenshots from the nav dropdown) must clear the
       80px site header AND the sticky category nav underneath it. */
    #pricing,
    #screenshots {
        scroll-margin-top: 150px;
    }

    .feat-pricing {
        background: #ffffff;
        border-top: 1.5px solid #ddeee5;
        padding: 88px 0 96px;
    }

    .feat-pricing-head {
        text-align: center;
        max-width: 680px;
        margin: 0 auto 56px;
    }

    .h-section {
        font-size: clamp(28px, 4vw, 44px);
        font-weight: 800;
        color: #0d1f12;
        letter-spacing: -0.025em;
        margin: 12px 0 16px;
    }

    .pricing-lede {
        font-size: 16px;
        line-height: 1.75;
        color: #4e6e56;
    }

    .feat-plan-grid {
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 32px;
        max-width: 1060px;
        margin: 0 auto;
    }

    /* Plan card */
    .plan-card {
        background: #ffffff;
        border: 1.5px solid #e8f0ea;
        border-radius: 22px;
        padding: 36px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        transition: box-shadow 0.22s ease;
    }

    .plan-card.primary {
        border: 2px solid #1a7a4e;
        box-shadow: 0 8px 40px rgba(26, 122, 78, 0.12);
    }

    .plan-badge {
        display: inline-block;
        background: #edf8f3;
        color: #1a7a4e;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 4px 12px;
        border-radius: 999px;
        margin-bottom: 16px;
    }

    .plan-price {
        font-size: 52px;
        font-weight: 300;
        letter-spacing: -0.03em;
        color: #0d1f12;
        margin-bottom: 6px;
        line-height: 1;
    }

    .plan-price .currency {
        font-size: 26px;
        vertical-align: super;
        opacity: 0.65;
        margin-right: 2px;
    }

    .plan-price .per {
        font-size: 17px;
        font-weight: 400;
        color: #6b8a72;
        margin-left: 4px;
    }

    .plan-options {
        display: grid;
        gap: 12px;
        margin-bottom: 28px;
    }

    .plan-option {
        display: grid;
        grid-template-columns: 84px 1fr;
        align-items: center;
        gap: 12px;
    }

    .plan-term {
        border: 1.5px solid #1a7a4e;
        border-radius: 6px;
        color: #1a7a4e;
        font-size: 16px;
        line-height: 1.1;
        padding: 7px 6px;
        text-align: center;
    }

    .plan-option-price {
        color: #0d1f12;
        font-size: 34px;
        font-weight: 600;
        letter-spacing: -0.03em;
        line-height: 1;
        white-space: nowrap;
    }

    .plan-option-price .currency {
        font-size: 20px;
        margin-right: 3px;
        vertical-align: super;
    }

    .plan-option-price .plan-strike {
        color: #a4a7ae;
        font-size: 25px;
        font-weight: 400;
        margin-left: 3px;
    }

    .plan-option-price .per {
        color: #1a7a4e;
        font-size: 16px;
        font-weight: 400;
        letter-spacing: 0;
        margin-left: 4px;
    }

    .plan-yearly {
        font-size: 13.5px;
        color: #6b8a72;
        margin-bottom: 28px;
        line-height: 1.7;
    }

    .plan-strike {
        text-decoration: line-through;
        margin-right: 6px;
    }

    .plan-save {
        display: inline-block;
        background: #edf8f3;
        color: #1a7a4e;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        letter-spacing: 0.04em;
    }

    .plan-features {
        list-style: none;
        padding: 0;
        margin: 0 0 28px;
        border-top: 1.5px solid #e8f0ea;
        border-bottom: 1.5px solid #e8f0ea;
        padding: 20px 0;
    }

    .plan-features li {
        padding: 7px 0;
        font-size: 14px;
        color: #3a5742;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .plan-features li::before {
        content: '✓';
        color: #1a7a4e;
        font-weight: 700;
        flex-shrink: 0;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 14px 28px;
        border-radius: 999px;
        font-family: 'Inter', sans-serif;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        text-decoration: none;
        border: 2px solid transparent;
    }

    .btn-teal {
        background: #1a7a4e;
        color: #ffffff;
        border-color: #1a7a4e;
    }

    .btn-teal:hover {
        background: #145f3c;
        border-color: #145f3c;
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(26, 122, 78, 0.28);
    }

    .btn-block {
        display: block;
        text-align: center;
        width: 100%;
    }

    .plan-fineprint {
        font-size: 12px;
        color: #6b8a72;
        text-align: center;
        margin-top: 12px;
    }

    /* Add-on column */
    .addon-column {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .addon-heading {
        font-size: 16px;
        font-weight: 700;
        color: #0d1f12;
        margin-bottom: 4px;
    }

    .addon-card {
        background: #ffffff;
        border: 1.5px solid #e8f0ea;
        border-radius: 16px;
        padding: 22px;
        display: flex;
        gap: 16px;
        transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    }

    .addon-card:hover {
        border-color: #a8d8be;
        transform: translateY(-3px);
        box-shadow: 0 10px 28px rgba(26, 122, 78, 0.10);
    }

    .addon-icon {
        font-size: 32px;
        flex-shrink: 0;
        width: 48px;
        height: 48px;
        background: #edf8f3;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.2s ease, transform 0.2s ease;
    }
.addon-icon img{width:32px;height:auto;object-fit: contain;filter: brightness(0) saturate(100%) invert(28%) sepia(63%) saturate(1045%) hue-rotate(134deg) brightness(92%) contrast(93%);}
    .addon-name {
        font-size: 15px;
        font-weight: 700;
        color: #0d1f12;
        margin-bottom: 6px;
    }

    .addon-desc {
        font-size: 13px;
        color: #6b8a72;
        line-height: 1.6;
        margin-bottom: 8px;
    }

    .addon-price {
        font-size: 14px;
        font-weight: 700;
        color: #1a7a4e;
    }

    .addon-tease {
        font-size: 12.5px;
        color: #6b8a72;
        line-height: 1.6;
        padding: 4px 6px;
    }

    /* ── Talk-to-us card (WhatsApp + demo) ── */
    .addon-help {
        background: #f4faf6;
        border: 1.5px solid #d8ecdf;
        border-radius: 16px;
        padding: 22px;
    }

    .addon-help-title {
        font-size: 15px;
        font-weight: 700;
        color: #0d1f12;
        margin-bottom: 6px;
    }

    .addon-help-desc {
        font-size: 13px;
        color: #6b8a72;
        line-height: 1.6;
        margin-bottom: 16px;
    }

    .addon-help-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .addon-help-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 12px 16px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        border: 1.5px solid transparent;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    }

    .addon-help-btn svg {
        flex: 0 0 auto;
    }

    .addon-help-btn:hover {
        transform: translateY(-2px);
    }

    .addon-help-wa {
        background: #25d366;
        color: #08331b;
        box-shadow: 0 6px 18px rgba(37, 211, 102, 0.28);
    }

    .addon-help-wa:hover {
        background: #1fbb59;
        box-shadow: 0 10px 24px rgba(37, 211, 102, 0.34);
    }

    .addon-help-demo {
        background: #ffffff;
        color: #1a7a4e;
        border-color: #a8d8be;
    }

    .addon-help-demo:hover {
        background: #eef8f1;
        border-color: #1a7a4e;
        box-shadow: 0 10px 24px rgba(26, 122, 78, 0.14);
    }

    /* ═══════════════════════════════════════════
           SCROLL REVEAL
        ═══════════════════════════════════════════ */
    .reveal {
        opacity: 0;
        transform: translateY(28px);
        transition: opacity 0.55s ease, transform 0.55s ease;
    }

    .reveal.visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* ═══════════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════════ */
    @media (max-width: 1080px) {
        .feat-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 900px) {
        .feat-plan-grid {
            grid-template-columns: 1fr;
        }

        .feat-cat-head {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .stat-item:nth-child(3)::before {
            display: none;
        }
    }

    @media (max-width: 600px) {
        .feat-hero {
            padding: 80px 0 52px;
        }

        .feat-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .plan-card {
            padding: 24px;
        }

        .plan-price {
            font-size: 40px;
        }

        .hp-eyebrow::before,
        .hp-eyebrow::after {
            display: none;
        }

        .feat-nav-inner {
            justify-content: flex-start;
            flex-wrap: nowrap;
        }
    }
</style>


<!-- ═══════ HERO ═══════ -->
<section class="feat-hero">
    <div class="feat-hero-glow"></div>
    <div class="wrap feat-hero-inner reveal">
        <p class="hp-eyebrow">Everything eClinicPro does</p>
        <h1 class="h-display">Everything to run your clinic.<br><span class="grad">All in one place.</span></h1>
        <p class="feat-hero-sub">
            42+ features across 7 areas — patient records, prescriptions, billing,
            WhatsApp/SMS and more. All included in one simple Standard Plan.
        </p>
    </div>
</section>

<!-- ═══════ STATS BAR ═══════ -->
<div class="feat-stats-bar">
    <div class="wrap">
        <div class="stats-grid reveal">
            <div class="stat-item">
                <div class="stat-num">42</div>
                <div class="stat-label">Features included</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">50+</div>
                <div class="stat-label">Specialties</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">1</div>
                <div class="stat-label">Simple plan</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">₹899</div>
                <div class="stat-label">Per month</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ PRODUCT SHOWCASE (screen-by-screen) ═══════ -->
<?php require __DIR__ . '/partials/product-showcase.php'; ?>

<!-- ═══════ STICKY CATEGORY NAV ═══════ -->
<nav class="feat-nav" id="featNav">
    <div class="wrap">
        <div class="feat-nav-inner">
            <?php foreach ($cats as $cat): ?>
                <a href="#<?php echo htmlspecialchars($cat['id']); ?>"
                    class="tab"
                    data-target="<?php echo htmlspecialchars($cat['id']); ?>">
                    <span><?php echo $cat['icon']; ?></span>
                    <?php echo htmlspecialchars($cat['title']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<!-- ═══════ FEATURE CATEGORIES ═══════ -->
<div class="feat-body">
    <div class="wrap">
        <?php foreach ($cats as $i => $cat): ?>
            <section id="<?php echo htmlspecialchars($cat['id']); ?>" class="feat-category">
                <!-- Category header -->
                <div class="feat-cat-head reveal">
                    <div>
                        <span class="cat-count"><?php echo count($cat['items']); ?> features</span>
                        <h2 class="cat-title">
                            <!-- <span class="cat-icon"><?php echo $cat['icon']; ?></span> -->
                            <?php echo htmlspecialchars($cat['title']); ?>
                        </h2>
                    </div>
                    <p class="cat-blurb"><?php echo htmlspecialchars($cat['blurb']); ?></p>
                </div>
                <!-- Feature cards -->
                <div class="feat-grid">
                    <?php foreach ($cat['items'] as $j => [$ic, $name, $desc]): ?>
                        <div class="feat-card reveal" style="transition-delay: <?php echo ($j % 3) * 60; ?>ms;">
                            <div class="feat-card-ic"><?php echo $ic; ?></div>
                            <h4><?php echo htmlspecialchars($name); ?></h4>
                            <p><?php echo htmlspecialchars($desc); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══════ PRICING ═══════ -->
<section id="pricing" class="feat-pricing">
    <div class="wrap">
        <div class="feat-pricing-head reveal">
            <p class="hp-eyebrow">Pricing</p>
            <h2 class="h-section">One plan. Everything included.</h2>
            <p class="pricing-lede">
                No tiers, no per-seat games, no surprise upsells. One annual price gets you
                the whole clinic system — and you start with a 30-day free trial, no card needed.
            </p>
        </div>
        <div class="feat-plan-grid reveal">
            <!-- Plan card -->
            <div class="plan-card primary">
                <span class="plan-badge">Standard Plan</span>
                <div class="plan-options">
                    <div class="plan-option">
                        <span class="plan-term">4 Years</span>
                        <div class="plan-option-price">
                            <span class="currency">₹</span>899
                            <span class="plan-strike">₹1,899</span>
                            <span class="per">/month</span>
                        </div>
                    </div>
                    <div class="plan-option">
                        <span class="plan-term">1 Year</span>
                        <div class="plan-option-price">
                            <span class="currency">₹</span>999
                            <span class="plan-strike">₹1,899</span>
                            <span class="per">/month</span>
                        </div>
                    </div>
                </div>
                <p class="plan-yearly">
                    + 18% GST at checkout
                </p>
                <ul class="plan-features">
                    <li>Patient records, visits, prescriptions</li>
                    <li>Appointments &amp; walk-in queue</li>
                    <li>Billing &amp; invoicing (GST-ready)</li>
                    <li>Vitals, diagnosis, follow-up tracking</li>
                    <li>Specialty-aware forms (50+ specialties)</li>
                    <li>Teleconsultation built in</li>
                    <li>Public doctor profile on eclinicpro.com</li>
                    <li>Daily reports &amp; analytics</li>
                    <li>Unlimited patients, unlimited staff users</li>
                    <li>30-day free trial — no credit card</li>
                </ul>
                <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-teal btn-block">
                    Start 30-day free trial →
                </a>
                <p class="plan-fineprint">No card needed. Cancel anytime during trial.</p>
            </div>
            <!-- Add-ons column -->
            <div class="addon-column">
                <h3 class="addon-heading">Optional add-ons</h3>
                <div class="addon-card">
                    <div class="addon-icon"><img src="/assets/img/icon/whatsapp-summaries.png" alt="Patient Connect"></div>
                    <div>
                        <div class="addon-name">Patient Connect</div>
                        <p class="addon-desc">WhatsApp automation: appointment reminders, prescription delivery, follow-up nudges. Cuts no-show rates in half.</p>
                        <div class="addon-price">+₹499 / month</div>
                    </div>
                </div>
                <div class="addon-card">
                    <div class="addon-icon"><img src="/assets/img/icon/clinic-network.png" alt="Clinic network"></div>
                    <div>
                        <div class="addon-name">Clinic Network</div>
                        <p class="addon-desc">Add an extra clinic branch under one account. Unified patient records, separate queues per branch.</p>
                        <div class="addon-price">+₹999 / month per branch</div>
                    </div>
                </div>
                <p class="addon-tease">
                    GST (18%) is added at checkout. After the 30-day trial you decide whether to
                    continue — no automatic charges and no card taken upfront.
                </p>

                <div class="addon-help">
                    <div class="addon-help-title">Still have questions?</div>
                    <p class="addon-help-desc">
                        Chat with us on WhatsApp, or book a free 15-minute demo and we'll walk you
                        through the whole system.
                    </p>
                    <div class="addon-help-actions">
                        <a href="https://wa.me/918200165254?text=<?= rawurlencode('Hi, I would like to know more about the eClinicPro Standard Plan.') ?>"
                           target="_blank" rel="noopener" class="addon-help-btn addon-help-wa">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.48-1.75-1.65-2.05-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.01-1.04 2.47s1.06 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.09 1.75-.72 2-1.41.25-.69.25-1.28.17-1.41-.07-.13-.27-.2-.57-.35z"/>
                                <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.96L2 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18c-1.48 0-2.93-.4-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 01-1.26-4.38c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.83 2.41a8.18 8.18 0 012.41 5.83c0 4.54-3.7 8.23-8.24 8.23z"/>
                            </svg>
                            Chat on WhatsApp
                        </a>
                        <a href="/book-a-demo" data-open-demo-modal class="addon-help-btn addon-help-demo">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            Book a Free Demo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ═══════ JAVASCRIPT ═══════ -->
<script>
    /* ─── Scroll Reveal ─── */
    const revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.08
        }
    );
    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
    /* ─── Active tab highlight on scroll ─── */
    const tabs = document.querySelectorAll('.tab[data-target]');
    const sections = Array.from(document.querySelectorAll('.feat-category'));

    function setActiveTab(id) {
        tabs.forEach(t => {
            t.classList.toggle('active', t.dataset.target === id);
        });
    }
    const sectionObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    setActiveTab(entry.target.id);
                }
            });
        }, {
            rootMargin: '-30% 0px -60% 0px',
            threshold: 0
        }
    );
    sections.forEach(sec => sectionObserver.observe(sec));
    /* ─── Smooth-scroll tab clicks ─── */
    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.getElementById(tab.dataset.target);
            if (target) {
                const offset = document.getElementById('featNav').offsetHeight + 16;
                const top = target.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({
                    top,
                    behavior: 'smooth'
                });
            }
        });
    });
</script>


<?php
$demoDefaultSpecialty = 'General practice';
$demoSpecKey = 'features';
require __DIR__ . '/partials/demo-modal.php';
?>

<?php require __DIR__ . '/partials/footer.php'; ?>