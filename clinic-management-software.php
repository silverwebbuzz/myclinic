<?php
// =====================================================================
// clinic-management-software.php — the product page for doctors.
// Served at /clinic-management-software (was /features, 301 in .htaccess).
//
// Only what the Standard plan actually includes is shown here — the same
// nine items as the plan card on /pricing. If the plan changes, update
// $modules below and pricing.php together.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

// Plan name + price come from the app admin (/admin/plans → 'standard').
$plan = ecp_standard_plan();
$priceLabel = '₹' . number_format($plan['monthly'], fmod($plan['monthly'], 1.0) ? 2 : 0);

$activePage = 'features';
$pageTitle = 'Clinic Management Software for Small & Large Clinics — eClinicPro';
$metaDesc  = 'eClinicPro clinic management software: patient records, appointments & walk-in queue, prescriptions, GST billing, vitals & follow-ups, daily reports and a public doctor profile — ' . $priceLabel . '/month + GST with unlimited patients and staff.';

$signupUrl = ecp_portal_url('/register');

// Auto-play product walkthrough — real screens from /assets/img/screens.
$walkthrough = [
    ['dashbord.png', 'Dashboard', 'The whole day on one screen — waiting, in consult, completed and collected.'],
    ['Book-an-appointment.png', 'Booking', 'Search or add a patient, pick a free slot — booked in under ten seconds.'],
    ['Walk-in.png', 'Walk-ins', 'Register a walk-in at the desk and they join today’s queue with a token.'],
    ['Calender.png', 'Calendar', 'Day, week and month views, colour-coded by status.'],
    ['Patient-visit.png', 'Consultation', 'Complaint, diagnosis, prescription, notes and charges on one page.'],
    ['Report.png', 'Reports', 'Collected, billed, GST and dues — ready for your CA.'],
];
$walkthrough = array_values(array_filter(
    $walkthrough,
    static fn (array $w): bool => is_file(__DIR__ . '/assets/img/screens/' . $w[0]),
));

// The nine things the Standard plan includes (mirrors the /pricing card).
$modules = [
    [
        'id' => 'records', 'icon' => '🗂️', 'name' => 'Patient Records',
        'title' => 'Every patient’s story in one place',
        'body' => 'Register a patient once and every visit, vital, diagnosis and prescription builds up in their profile — found in seconds, never lost in a paper file.',
        'points' => [
            'Quick registration with a unique patient ID (UHID)',
            'Search by name, phone number or UHID',
            'Full visit history — complaints, diagnosis, notes and medicines',
            'Last visits, vitals and medications shown during every consult',
        ],
        'img' => 'Patient-visit.png',
    ],
    [
        'id' => 'appointments', 'icon' => '📅', 'name' => 'Appointments & walk-in queue',
        'title' => 'Booked patients and walk-ins, in one queue',
        'body' => 'Book appointments in seconds and let walk-ins join the same queue with a token, so reception always knows who is next.',
        'points' => [
            'Day, week, month and list calendar views',
            'Only genuinely free slots offered — no double booking',
            'Walk-ins get a token and join today’s queue',
            'Colour-coded status: waiting, in consult, completed, no-show',
        ],
        'img' => 'Walk-in.png',
    ],
    [
        'id' => 'rx', 'icon' => '💊', 'name' => 'Prescriptions & Pharmacy',
        'title' => 'Clear prescriptions in a few taps',
        'body' => 'Write prescriptions from a built-in medicine list with dose, frequency and duration — then print them or share them with the patient.',
        'points' => [
            'Pick medicines from the built-in drug list',
            'Frequency, duration and tapering doses',
            'Save your own templates for common conditions',
            'Print on your letterhead or send on WhatsApp',
        ],
        'img' => 'Patient-visit.png',
    ],
    [
        'id' => 'billing', 'icon' => '🧾', 'name' => 'Billing & invoicing (GST-ready)',
        'title' => 'Bill at the end of the consult',
        'body' => 'Charges, discounts and GST are settled right along with the visit, and every invoice is ready to print or download.',
        'points' => [
            'Charges, discount and GST calculated for you',
            'Mark paid by cash or online, and track dues',
            'GST invoices as PDF',
            'Export to Excel or Tally for your accountant',
        ],
        'img' => 'dashbord.png',
    ],
    [
        'id' => 'clinical', 'icon' => '🩺', 'name' => 'Vitals, diagnosis & follow-ups',
        'title' => 'Clinical notes that follow the patient',
        'body' => 'Record vitals and diagnosis during the visit and set the next follow-up, so no patient slips through the cracks.',
        'points' => [
            'Vitals recorded and kept with every visit',
            'Diagnosis with ICD-10 codes',
            'Set follow-up dates at the end of a consult',
            'A follow-up list showing who is due and when',
        ],
        'img' => 'Patient-visit.png',
    ],
    [
        'id' => 'reports', 'icon' => '📊', 'name' => 'Daily reports & analytics',
        'title' => 'Know how your clinic did today',
        'body' => 'Today by default, any date range on demand — what you collected, what you billed and what is still due.',
        'points' => [
            'Collected, billed, GST and outstanding at a glance',
            'Cash / online split to reconcile the cash box',
            'GST per rate — taxable value and tax collected',
            'Invoice-level detail, downloadable as CSV',
        ],
        'img' => 'Report.png',
    ],
    [
        'id' => 'team', 'icon' => '👥', 'name' => 'Unlimited patients & staff',
        'title' => 'Grow without paying per seat',
        'body' => 'Add every doctor, nurse and receptionist in your clinic, and register as many patients as you see — the price stays the same.',
        'points' => [
            'Unlimited patient records',
            'Unlimited staff user logins',
            'Roles for doctor, nurse, receptionist and lab staff',
            'Each person only sees what their role needs',
        ],
        'img' => 'Calender.png',
    ],
    [
        'id' => 'profile', 'icon' => '🌐', 'name' => 'Public doctor profile',
        'title' => 'Get found by new patients',
        'body' => 'Your clinic gets its own public profile on eclinicpro.com, where patients can find you and book an appointment.',
        'points' => [
            'Your own doctor profile page on eclinicpro.com',
            'Listed in Find a Doctor for your city',
            'Patients can book online from your profile',
            'Online bookings land straight in your calendar',
        ],
        'img' => 'Book-an-appointment.png',
    ],
    [
        'id' => 'reel', 'icon' => '🎬', 'name' => 'Instagram Reel every month',
        'title' => '1 Instagram Reel post per month',
        'body' => 'Staying visible on social media takes time you don’t have. Every month, the plan includes one Instagram Reel post for your clinic.',
        'points' => [
            '1 Instagram Reel post every month',
            'Included in the plan — no extra charge',
            'Keeps your clinic visible to patients nearby',
        ],
        'img' => null,
    ],
];

$clinicTypes = [
    ['🧑‍⚕️', 'Solo doctors', 'Run appointments, prescriptions and billing yourself — or with one receptionist — without paperwork.'],
    ['🏥', 'Multi-doctor clinics', 'Every doctor gets their own schedule, while patient records, queue and billing stay shared.'],
    ['🦷', 'Specialty clinics', 'Built for the way specialists work — dentistry, dermatology, pediatrics, physiotherapy, homeopathy and general practice.'],
];
$specialtyLinks = [
    ['/gps', 'General practice'],
    ['/dentists', 'Dentists'],
    ['/dermatologists', 'Dermatologists'],
    ['/pediatricians', 'Pediatricians'],
    ['/physiotherapists', 'Physiotherapists'],
    ['/homeopathy-clinic-management-software', 'Homeopathy'],
];

$challenges = [
    ['📂', 'Paper files and lost records', 'Every visit, prescription and vital is saved to the patient’s digital profile and found in seconds.'],
    ['⏳', 'Crowded waiting rooms', 'Appointments and walk-ins share one token queue, so patients know when it’s their turn.'],
    ['🧮', 'Billing and GST headaches', 'GST invoices at the end of each visit, and daily reports your accountant can use directly.'],
    ['📣', 'Hard to be found online', 'A public doctor profile on eclinicpro.com plus a monthly Instagram Reel keep new patients coming.'],
];

$steps = [
    ['Sign up', 'Verify your phone, add your clinic details and pay ' . $priceLabel . ' + GST.'],
    ['Set up your clinic', 'Add timings, doctors and staff. We help with data import.'],
    ['See patients', 'Book appointments, write prescriptions and bill — from day one.'],
];

$faqs = [
    ['What is included in the plan?', 'Patient records, appointments & walk-in queue, prescriptions, GST billing & invoicing, vitals, diagnosis & follow-up tracking, daily reports, unlimited patients and staff users, a public doctor profile on eclinicpro.com and 1 Instagram Reel post per month.'],
    ['How much does it cost?', $priceLabel . ' per month + ' . (int) $plan['gst_percent'] . '% GST (₹' . number_format($plan['total'], 2) . ' in total), billed monthly. There are no per-user or per-patient charges.'],
    ['Do I need to install anything?', 'No. eClinicPro runs in your web browser on any laptop, desktop or tablet — nothing to install or maintain.'],
    ['Can more than one doctor use it?', 'Yes. Staff users are unlimited, so every doctor, nurse and receptionist can have their own login with the right access.'],
    ['Is my patients’ data safe?', 'Data is encrypted, each clinic’s data is kept separate, and staff only see what their role allows.'],
    ['How do I get started?', 'Click Sign up, verify your phone number, add your clinic details and complete the payment. Your account is ready immediately. Prefer a walkthrough first? Book a free demo.'],
];

require __DIR__ . '/partials/header.php';
?>

<style>
    .cms { --c-ink: #0d1f12; --c-body: #4e6e56; --c-mute: #6b8a72; --c-brand: #0F9B6E; --c-brand-d: #0B7F5A; --c-deep: #03382A; --c-soft: #e8f6ef; --c-line: #e3efe8; }
    .cms-eyebrow { display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--c-brand); margin-bottom: 12px; }
    .cms-h2 { font-size: clamp(26px, 3.6vw, 42px); font-weight: 800; letter-spacing: -0.025em; line-height: 1.15; color: var(--c-ink); margin: 0 0 14px; }
    .cms-lede { font-size: 16.5px; line-height: 1.75; color: var(--c-body); margin: 0; }
    .cms-head { text-align: center; max-width: 700px; margin: 0 auto 48px; }
    .cms-sec { padding: 88px 0; }
    #demo, #features, #pricing { scroll-margin-top: 80px; }
    .cms-sec.alt { background: #f6faf8; border-top: 1px solid var(--c-line); border-bottom: 1px solid var(--c-line); }

    .cms-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 28px; border-radius: 12px; font-size: 15.5px; font-weight: 700; text-decoration: none; border: 2px solid var(--c-brand); transition: transform .2s ease, box-shadow .2s ease, background .2s ease; white-space: nowrap; cursor: pointer; }
    .cms-btn-solid { background: var(--c-brand); color: #fff; }
    .cms-btn-solid:hover { background: var(--c-brand-d); border-color: var(--c-brand-d); transform: translateY(-2px); box-shadow: 0 10px 24px rgba(15, 155, 110, .3); }
    .cms-btn-line { background: #fff; color: var(--c-ink); border-color: #cfd8d3; }
    .cms-btn-line:hover { border-color: var(--c-brand); color: var(--c-brand-d); transform: translateY(-2px); }

    /* ── Hero ── */
    .cms-hero { position: relative; overflow: hidden; padding: 96px 0 88px; background: linear-gradient(180deg, #f3faf7 0%, #f8fbfa 100%); }
    .cms-hero-grid { display: grid; grid-template-columns: 1.05fr 1fr; gap: 40px; align-items: center; }
    .cms-hero h1 { font-size: clamp(32px, 4vw, 52px); font-weight: 800; letter-spacing: -0.03em; line-height: 1.1; color: var(--c-ink); margin: 0 0 22px; }
    .cms-hero h1 em { font-style: normal; color: var(--c-brand); }
    .cms-hero-sub { font-size: 18px; line-height: 1.7; color: var(--c-body); margin: 0 0 18px; max-width: 560px; }
    .cms-hero-tag { font-size: 17px; font-weight: 600; color: var(--c-ink); margin: 0 0 34px; }
    .cms-hero-tag b { color: var(--c-brand); }
    .cms-hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; }
    .cms-hero-note { margin-top: 18px; font-size: 13.5px; color: var(--c-mute); }
    .cms-hero-note a { color: var(--c-brand-d); font-weight: 600; }

    .cms-art { position: relative; width: 100%; max-width: 560px; aspect-ratio: 1 / 1; margin: 0 auto; }
    .cms-art-blob { position: absolute; inset: 6% 4% 8% 10%; border-radius: 46% 54% 42% 58% / 52% 44% 56% 48%; background: radial-gradient(circle at 35% 35%, #d9f1e6, #c3e8d7 60%, #b3e0cb); }
    .cms-art-orbits { position: absolute; inset: 0; width: 100%; height: 100%; }
    .cms-art-orbits .o { fill: none; stroke: #9fb8ab; stroke-width: 1.6; }
    .cms-art-orbits .o.dash { stroke-dasharray: 6 8; stroke: #a8d8be; }
    .cms-art-orbits .dot { fill: var(--c-brand); }
    .cms-art-orbits .dot.lt { fill: #7fd1ad; }
    .cms-art-photo { position: absolute; left: 20%; top: 16%; width: 62%; aspect-ratio: 1 / 1; border-radius: 50%; overflow: hidden; border: 8px solid #fff; box-shadow: 0 24px 60px rgba(3, 56, 42, .22); }
    .cms-art-photo img { width: 100%; height: 100%; object-fit: cover; object-position: 50% 30%; display: block; transform: scale(1.45); transform-origin: 52% 42%; }
    .cms-bubble { position: absolute; width: 74px; height: 74px; border-radius: 50%; background: #fff; display: grid; place-items: center; box-shadow: 0 12px 30px rgba(3, 56, 42, .14); animation: cmsFloat 6s ease-in-out infinite; }
    .cms-bubble img { width: 38px; height: 38px; object-fit: contain; filter: brightness(0) saturate(100%) invert(28%) sepia(63%) saturate(1045%) hue-rotate(134deg) brightness(92%) contrast(93%); }
    .cms-bubble.b1 { left: 14%; top: 4%; }
    .cms-bubble.b2 { right: 4%; top: 12%; animation-delay: -1.5s; }
    .cms-bubble.b3 { right: 0; top: 48%; width: 64px; height: 64px; animation-delay: -3s; }
    .cms-bubble.b4 { left: 0; top: 42%; animation-delay: -4.5s; }
    .cms-bubble.b5 { right: 10%; bottom: 8%; width: 60px; height: 60px; animation-delay: -2.2s; }
    @keyframes cmsFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    .cms-ribbon { position: absolute; left: 6%; bottom: 10%; background: linear-gradient(135deg, #0F9B6E, #076B4C); color: #fff; font-weight: 700; font-size: 19px; line-height: 1.3; padding: 14px 20px; border-radius: 6px; box-shadow: 0 14px 30px rgba(3, 56, 42, .3); text-align: center; }
    .cms-ribbon::before, .cms-ribbon::after { content: ''; position: absolute; bottom: -12px; border-style: solid; }
    .cms-ribbon::before { left: 0; border-width: 12px 0 0 14px; border-color: #03382A transparent transparent transparent; }
    .cms-ribbon::after { right: 0; border-width: 12px 14px 0 0; border-color: #03382A transparent transparent transparent; }

    /* ── Stats strip ── */
    .cms-stats { background: var(--c-deep); color: #fff; }
    .cms-stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .cms-stat { padding: 28px 18px; text-align: center; border-left: 1px solid rgba(255, 255, 255, .12); }
    .cms-stat:first-child { border-left: 0; }
    .cms-stat b { display: block; font-size: clamp(22px, 2.6vw, 30px); font-weight: 800; letter-spacing: -0.02em; }
    .cms-stat span { display: block; margin-top: 4px; font-size: 13.5px; opacity: .78; }

    /* ── Walkthrough "video" ── */
    .cms-player { max-width: 1060px; margin: 0 auto; }
    .cms-frame { position: relative; border-radius: 18px; overflow: hidden; background: #0d1f12; box-shadow: 0 30px 80px rgba(3, 56, 42, .25); border: 1px solid #dbe8e1; }
    .cms-frame-bar { display: flex; align-items: center; gap: 7px; padding: 11px 16px; background: #eef4f1; border-bottom: 1px solid #dbe8e1; }
    .cms-frame-bar i { width: 11px; height: 11px; border-radius: 50%; background: #d7e2dc; }
    .cms-frame-bar i:nth-child(1) { background: #ff6159; } .cms-frame-bar i:nth-child(2) { background: #ffbd2e; } .cms-frame-bar i:nth-child(3) { background: #28c941; }
    .cms-frame-url { margin-left: 12px; flex: 1; max-width: 360px; background: #fff; border-radius: 6px; padding: 4px 12px; font-size: 12px; color: var(--c-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cms-screen { position: relative; aspect-ratio: 16 / 9; background: #fff; overflow: hidden; }
    .cms-slide { position: absolute; inset: 0; opacity: 0; transition: opacity .7s ease; }
    .cms-slide img { width: 100%; height: 100%; object-fit: cover; object-position: top left; display: block; transform-origin: 30% 20%; }
    .cms-slide.is-on { opacity: 1; }
    .cms-player.is-playing .cms-slide.is-on img { animation: cmsPan var(--dur, 5s) linear forwards; }
    @keyframes cmsPan { from { transform: scale(1); } to { transform: scale(1.08); } }
    .cms-caption { position: absolute; left: 16px; right: 16px; bottom: 16px; display: flex; gap: 12px; align-items: center; background: rgba(3, 56, 42, .88); color: #fff; border-radius: 12px; padding: 12px 16px; backdrop-filter: blur(6px); }
    .cms-caption b { font-size: 13px; letter-spacing: .08em; text-transform: uppercase; color: #7fe3b6; white-space: nowrap; }
    .cms-caption span { font-size: 14.5px; line-height: 1.45; }
    .cms-play { position: absolute; top: 14px; right: 14px; width: 44px; height: 44px; border-radius: 50%; border: 0; background: rgba(3, 56, 42, .85); color: #fff; display: grid; place-items: center; cursor: pointer; }
    .cms-play:hover { background: var(--c-brand); }
    .cms-play .i-pause { display: none; }
    .cms-player.is-playing .cms-play .i-play { display: none; }
    .cms-player.is-playing .cms-play .i-pause { display: block; }
    .cms-progress { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: 6px; padding: 12px 16px; background: #0d1f12; }
    .cms-progress span { height: 4px; border-radius: 4px; background: rgba(255, 255, 255, .2); overflow: hidden; }
    .cms-progress span i { display: block; height: 100%; width: 0; background: #2DC08A; }
    .cms-progress span.done i { width: 100%; }
    .cms-player.is-playing .cms-progress span.now i { animation: cmsFill var(--dur, 5s) linear forwards; }
    .cms-player:not(.is-playing) .cms-progress span.now i { width: 100%; }
    @keyframes cmsFill { from { width: 0; } to { width: 100%; } }
    .cms-chapters { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 22px; }
    .cms-chapters button { border: 1.5px solid var(--c-line); background: #fff; color: var(--c-body); font: inherit; font-size: 14px; font-weight: 600; padding: 8px 16px; border-radius: 999px; cursor: pointer; transition: all .2s ease; }
    .cms-chapters button:hover { border-color: #a8d8be; }
    .cms-chapters button.is-on { background: var(--c-brand); border-color: var(--c-brand); color: #fff; }

    /* ── Module tabs ── */
    .cms-tabs { display: grid; grid-template-columns: 300px 1fr; gap: 28px; max-width: 1160px; margin: 0 auto; align-items: start; }
    .cms-tablist { display: flex; flex-direction: column; gap: 6px; position: sticky; top: 100px; }
    .cms-tab { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; font: inherit; font-size: 15px; font-weight: 600; color: var(--c-body); background: #fff; border: 1.5px solid var(--c-line); border-radius: 12px; padding: 12px 14px; cursor: pointer; transition: all .2s ease; }
    .cms-tab:hover { border-color: #a8d8be; color: var(--c-ink); }
    .cms-tab .ic { width: 34px; height: 34px; border-radius: 10px; background: var(--c-soft); display: grid; place-items: center; font-size: 17px; flex-shrink: 0; }
    .cms-tab[aria-selected="true"] { background: var(--c-deep); border-color: var(--c-deep); color: #fff; box-shadow: 0 10px 24px rgba(3, 56, 42, .2); }
    .cms-tab[aria-selected="true"] .ic { background: rgba(255, 255, 255, .14); }
    .cms-panel { background: #fff; border: 1.5px solid var(--c-line); border-radius: 22px; padding: 34px; box-shadow: 0 8px 30px rgba(3, 56, 42, .06); }
    .cms-panel h3 { font-size: clamp(22px, 2.4vw, 28px); font-weight: 800; letter-spacing: -0.02em; color: var(--c-ink); margin: 0 0 10px; }
    .cms-panel > p { font-size: 15.5px; line-height: 1.7; color: var(--c-body); margin: 0 0 20px; }
    .cms-points { list-style: none; padding: 0; margin: 0 0 24px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; }
    .cms-points li { display: flex; gap: 10px; font-size: 14.5px; line-height: 1.5; color: #3a5742; }
    .cms-points li::before { content: '✓'; flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%; background: var(--c-soft); color: var(--c-brand); font-weight: 800; font-size: 12px; display: grid; place-items: center; }
    .cms-shot { border-radius: 14px; overflow: hidden; border: 1px solid #dbe8e1; box-shadow: 0 14px 40px rgba(3, 56, 42, .12); }
    .cms-shot img { display: block; width: 100%; height: auto; }
    .cms-reel { display: flex; align-items: center; gap: 22px; padding: 26px; border-radius: 16px; background: linear-gradient(135deg, #fdf2f8, #f5f3ff 50%, #fff7ed); border: 1px solid #f1e4f0; }
    .cms-reel-phone { width: 110px; aspect-ratio: 9 / 16; flex-shrink: 0; border-radius: 18px; background: linear-gradient(160deg, #f58529, #dd2a7b 50%, #8134af); display: grid; place-items: center; color: #fff; font-size: 34px; box-shadow: 0 12px 30px rgba(129, 52, 175, .3); }
    .cms-reel p { margin: 0; font-size: 15px; line-height: 1.65; color: #4a3b52; }
    .cms-reel b { color: #1f1027; }

    /* ── Cards ── */
    .cms-grid3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 22px; max-width: 1120px; margin: 0 auto; }
    .cms-grid4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px; max-width: 1160px; margin: 0 auto; }
    .cms-card { background: #fff; border: 1.5px solid var(--c-line); border-radius: 20px; padding: 28px 24px; transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease; }
    .cms-card:hover { transform: translateY(-4px); border-color: #a8d8be; box-shadow: 0 16px 40px rgba(3, 56, 42, .1); }
    .cms-card .ic { width: 54px; height: 54px; border-radius: 16px; background: var(--c-soft); display: grid; place-items: center; font-size: 26px; margin-bottom: 16px; }
    .cms-card h3 { font-size: 18px; font-weight: 700; color: var(--c-ink); margin: 0 0 8px; }
    .cms-card p { font-size: 14.5px; line-height: 1.65; color: var(--c-mute); margin: 0; }
    .cms-chips { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 30px; }
    .cms-chips a { padding: 8px 16px; border-radius: 999px; background: #fff; border: 1.5px solid var(--c-line); color: var(--c-brand-d); font-size: 14px; font-weight: 600; text-decoration: none; }
    .cms-chips a:hover { border-color: var(--c-brand); background: #f0faf5; }
    .cms-challenge { position: relative; overflow: hidden; }
    .cms-challenge::after { content: ''; position: absolute; right: -40px; top: -40px; width: 110px; height: 110px; border-radius: 50%; background: var(--c-soft); opacity: .7; }

    /* ── Steps ── */
    .cms-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; max-width: 980px; margin: 0 auto; counter-reset: cmsstep; }
    .cms-step { position: relative; text-align: center; padding: 0 10px; }
    .cms-step::before { counter-increment: cmsstep; content: counter(cmsstep); display: grid; place-items: center; width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 50%; background: var(--c-brand); color: #fff; font-weight: 800; font-size: 20px; box-shadow: 0 0 0 8px var(--c-soft); position: relative; z-index: 1; }
    .cms-step:not(:last-child)::after { content: ''; position: absolute; top: 28px; left: calc(50% + 40px); right: calc(-50% + 40px); height: 2px; background: repeating-linear-gradient(90deg, #a8d8be 0 6px, transparent 6px 12px); }
    .cms-step h3 { font-size: 17px; font-weight: 700; color: var(--c-ink); margin: 0 0 6px; }
    .cms-step p { font-size: 14.5px; line-height: 1.6; color: var(--c-mute); margin: 0; }

    /* ── Price band ── */
    .cms-price { display: grid; grid-template-columns: 1.1fr 1fr; gap: 36px; align-items: center; max-width: 1040px; margin: 0 auto; background: #fff; border: 2px solid var(--c-brand); border-radius: 26px; padding: 40px; box-shadow: 0 20px 60px rgba(15, 155, 110, .14); }
    .cms-price-amt { font-size: 64px; font-weight: 800; letter-spacing: -0.04em; color: var(--c-ink); line-height: 1; }
    .cms-price-amt small { font-size: 28px; font-weight: 600; vertical-align: 24px; margin-right: 2px; }
    .cms-price-amt span { font-size: 17px; font-weight: 500; color: var(--c-brand); letter-spacing: 0; }
    .cms-price-sub { margin: 10px 0 24px; font-size: 14.5px; color: var(--c-mute); }
    .cms-price-actions { display: flex; flex-wrap: wrap; gap: 12px; }
    .cms-price ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
    .cms-price li { display: flex; gap: 10px; font-size: 14.5px; color: #3a5742; }
    .cms-price li::before { content: '✓'; color: var(--c-brand); font-weight: 800; }

    /* ── FAQ ── */
    .cms-faq { max-width: 820px; margin: 0 auto; display: grid; gap: 12px; }
    .cms-faq details { background: #fff; border: 1.5px solid var(--c-line); border-radius: 14px; padding: 0 22px; }
    .cms-faq details[open] { border-color: #a8d8be; }
    .cms-faq summary { cursor: pointer; list-style: none; padding: 18px 0; font-weight: 600; font-size: 16px; color: var(--c-ink); display: flex; justify-content: space-between; gap: 16px; }
    .cms-faq summary::-webkit-details-marker { display: none; }
    .cms-faq summary::after { content: '+'; color: var(--c-brand); font-size: 22px; line-height: 1; transition: transform .2s ease; }
    .cms-faq details[open] summary::after { transform: rotate(45deg); }
    .cms-faq details p { margin: 0 0 18px; font-size: 15px; line-height: 1.7; color: var(--c-body); }

    /* ── CTA ── */
    .cms-cta { max-width: 1060px; margin: 0 auto; border-radius: 28px; padding: 56px 40px; text-align: center; color: #fff; background: linear-gradient(120deg, #03382A, #076B4C 55%, #0F9B6E); box-shadow: 0 24px 60px rgba(3, 56, 42, .25); }
    .cms-cta h2 { font-size: clamp(26px, 3.6vw, 40px); font-weight: 800; letter-spacing: -0.02em; margin: 0 0 12px; }
    .cms-cta p { font-size: 16.5px; opacity: .88; margin: 0 auto 28px; max-width: 580px; line-height: 1.7; }
    .cms-cta .cms-hero-ctas { justify-content: center; }
    .cms-cta .cms-btn-solid { background: #fff; color: var(--c-deep); border-color: #fff; }
    .cms-cta .cms-btn-solid:hover { background: var(--c-soft); border-color: var(--c-soft); box-shadow: none; }
    .cms-cta .cms-btn-line { background: transparent; color: #fff; border-color: rgba(255, 255, 255, .55); }
    .cms-cta .cms-btn-line:hover { background: rgba(255, 255, 255, .1); color: #fff; }

    @media (max-width: 1000px) {
        .cms-hero-grid { grid-template-columns: 1fr; }
        .cms-hero-copy { text-align: center; }
        .cms-hero-sub { margin-left: auto; margin-right: auto; }
        .cms-hero-ctas { justify-content: center; }
        .cms-art { max-width: 460px; }
        .cms-tabs { grid-template-columns: 1fr; }
        .cms-tablist { position: static; flex-direction: row; overflow-x: auto; padding-bottom: 6px; scroll-snap-type: x mandatory; }
        .cms-tab { width: auto; flex-shrink: 0; scroll-snap-align: start; }
        .cms-grid4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cms-price { grid-template-columns: 1fr; }
    }
    @media (max-width: 760px) {
        .cms-hero { padding: 96px 0 56px; }
        .cms-sec { padding: 64px 0; }
        .cms-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cms-stat:nth-child(3) { border-left: 0; }
        .cms-stat:nth-child(n+3) { border-top: 1px solid rgba(255, 255, 255, .12); }
        .cms-grid3, .cms-steps { grid-template-columns: 1fr; }
        .cms-step::after { display: none; }
        .cms-points { grid-template-columns: 1fr; }
        .cms-panel { padding: 24px 20px; }
        .cms-caption { left: 8px; right: 8px; bottom: 8px; padding: 8px 12px; }
        .cms-caption span { font-size: 12.5px; }
        .cms-caption b { font-size: 11px; }
        .cms-bubble { width: 56px; height: 56px; } .cms-bubble img { width: 28px; height: 28px; }
        .cms-bubble.b3, .cms-bubble.b5 { width: 48px; height: 48px; }
        .cms-ribbon { font-size: 15px; padding: 10px 14px; }
        .cms-price { padding: 28px 22px; }
        .cms-cta { padding: 40px 22px; border-radius: 22px; }
        .cms-reel { flex-direction: column; text-align: center; }
    }
    @media (max-width: 480px) {
        .cms-grid4 { grid-template-columns: 1fr; }
        .cms-btn { width: 100%; }
        .cms-frame-url { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .cms-bubble { animation: none; }
        .cms-slide { transition: none; }
        .cms-player.is-playing .cms-slide.is-on img { animation: none; }
    }
</style>

<div class="cms">

<!-- ═══════ HERO ═══════ -->
<section class="cms-hero">
    <div class="wrap cms-hero-grid">
        <div class="cms-hero-copy reveal">
            <h1>Smart <em>Clinic</em> Management Software for Small &amp; Large Clinics</h1>
            <p class="cms-hero-sub">
                eClinicPro runs your whole clinic — appointments, patient records, prescriptions
                and GST billing — in one simple system. Made in India, for Indian clinics.
            </p>
            <p class="cms-hero-tag"><b>One plan,</b> everything included — <?= e($priceLabel) ?>/month.</p>
            <div class="cms-hero-ctas">
                <a href="/book-a-demo" data-open-demo-modal class="cms-btn cms-btn-solid">Book a Demo</a>
                <a href="/contact" class="cms-btn cms-btn-line">Contact Us</a>
            </div>
            <p class="cms-hero-note">Ready to go? <a href="<?= e($signupUrl) ?>">Sign up now →</a></p>
        </div>

        <div class="cms-art reveal" aria-hidden="true">
            <div class="cms-art-blob"></div>
            <svg class="cms-art-orbits" viewBox="0 0 560 560">
                <ellipse class="o" cx="290" cy="290" rx="250" ry="120" transform="rotate(-28 290 290)"/>
                <ellipse class="o dash" cx="280" cy="280" rx="235" ry="205"/>
                <ellipse class="o" cx="290" cy="300" rx="120" ry="255" transform="rotate(-18 290 300)"/>
                <circle class="dot" cx="470" cy="160" r="11"/>
                <circle class="dot lt" cx="96" cy="370" r="8"/>
                <circle class="dot" cx="400" cy="470" r="7"/>
                <circle class="dot lt" cx="160" cy="110" r="6"/>
                <circle class="dot" cx="515" cy="300" r="5"/>
            </svg>
            <div class="cms-art-photo">
                <img src="/assets/img/logos/carely-hero-img.webp" alt="" width="1342" height="930" fetchpriority="high">
            </div>
            <span class="cms-bubble b1"><img src="/assets/img/icon/dental.svg" alt=""></span>
            <span class="cms-bubble b2"><img src="/assets/img/icon/eclinicpro-doctor-fil.png" alt=""></span>
            <span class="cms-bubble b3"><img src="/assets/img/icon/who-growth-charts.png" alt=""></span>
            <span class="cms-bubble b4"><img src="/assets/img/icon/skin-imaging.png" alt=""></span>
            <span class="cms-bubble b5"><img src="/assets/img/icon/physiotherapist.png" alt=""></span>
            <div class="cms-ribbon">Simplify Your<br>Clinic Operations!</div>
        </div>
    </div>
</section>

<!-- ═══════ STATS ═══════ -->
<div class="cms-stats">
    <div class="wrap cms-stats-grid">
        <div class="cms-stat"><b>9</b><span>Things included in one plan</span></div>
        <div class="cms-stat"><b>Unlimited</b><span>Patients &amp; staff users</span></div>
        <div class="cms-stat"><b><?= e($priceLabel) ?></b><span>Per month + GST</span></div>
        <div class="cms-stat"><b>No install</b><span>Runs in your web browser</span></div>
    </div>
</div>

<!-- ═══════ DEMO WALKTHROUGH ═══════ -->
<?php if ($walkthrough !== []): ?>
<section class="cms-sec" id="demo">
    <div class="wrap">
        <div class="cms-head reveal">
            <span class="cms-eyebrow">Product demo</span>
            <h2 class="cms-h2">See eClinicPro in action</h2>
            <p class="cms-lede">A quick walkthrough of a real clinic day — from the morning dashboard to the evening report.</p>
        </div>

        <div class="cms-player reveal" id="cmsPlayer" style="--dur: 5s">
            <div class="cms-frame">
                <div class="cms-frame-bar" aria-hidden="true"><i></i><i></i><i></i><span class="cms-frame-url">app.eclinicpro.com</span></div>
                <div class="cms-screen">
                    <?php foreach ($walkthrough as $i => [$file, $label, $caption]): ?>
                        <div class="cms-slide<?= $i === 0 ? ' is-on' : '' ?>" data-label="<?= e($label) ?>" data-caption="<?= e($caption) ?>">
                            <img src="/assets/img/screens/<?= e($file) ?>" alt="eClinicPro <?= e($label) ?> screen" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" width="2377" height="1339">
                        </div>
                    <?php endforeach; ?>
                    <div class="cms-caption" aria-live="polite">
                        <b id="cmsCapLabel"><?= e($walkthrough[0][1]) ?></b>
                        <span id="cmsCapText"><?= e($walkthrough[0][2]) ?></span>
                    </div>
                    <button type="button" class="cms-play" id="cmsPlay" aria-label="Pause walkthrough">
                        <svg class="i-play" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="i-pause" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
                    </button>
                </div>
                <div class="cms-progress" aria-hidden="true">
                    <?php foreach ($walkthrough as $i => $w): ?><span class="<?= $i === 0 ? 'now' : '' ?>"><i></i></span><?php endforeach; ?>
                </div>
            </div>
            <div class="cms-chapters" role="tablist" aria-label="Walkthrough chapters">
                <?php foreach ($walkthrough as $i => [, $label]): ?>
                    <button type="button" data-go="<?= $i ?>" class="<?= $i === 0 ? 'is-on' : '' ?>"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════ WHAT'S INCLUDED (tabs) ═══════ -->
<section class="cms-sec alt" id="features">
    <div class="wrap">
        <div class="cms-head reveal">
            <span class="cms-eyebrow">Why eClinicPro</span>
            <h2 class="cms-h2">Everything your clinic needs, in one plan</h2>
            <p class="cms-lede">Nine things every eClinicPro clinic gets from day one. Pick one to see how it works.</p>
        </div>

        <div class="cms-tabs reveal">
            <div class="cms-tablist" role="tablist" aria-label="What is included">
                <?php foreach ($modules as $i => $m): ?>
                    <button type="button" class="cms-tab" role="tab" id="tab-<?= e($m['id']) ?>"
                            aria-controls="panel-<?= e($m['id']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>">
                        <span class="ic" aria-hidden="true"><?= $m['icon'] ?></span><?= e($m['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div>
                <?php foreach ($modules as $i => $m): ?>
                    <div class="cms-panel" role="tabpanel" id="panel-<?= e($m['id']) ?>" aria-labelledby="tab-<?= e($m['id']) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                        <h3><?= e($m['title']) ?></h3>
                        <p><?= e($m['body']) ?></p>
                        <ul class="cms-points">
                            <?php foreach ($m['points'] as $pt): ?><li><?= e($pt) ?></li><?php endforeach; ?>
                        </ul>
                        <?php if ($m['img'] && is_file(__DIR__ . '/assets/img/screens/' . $m['img'])): ?>
                            <div class="cms-shot"><img src="/assets/img/screens/<?= e($m['img']) ?>" alt="<?= e($m['name']) ?> in eClinicPro" loading="lazy" width="2377" height="1339"></div>
                        <?php elseif ($m['id'] === 'reel'): ?>
                            <div class="cms-reel">
                                <div class="cms-reel-phone" aria-hidden="true">▶</div>
                                <p><b>Your clinic on Instagram, every month.</b><br>One Reel post per month is part of your plan — a simple way to stay in front of patients in your area.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ═══════ CLINIC TYPES ═══════ -->
<section class="cms-sec">
    <div class="wrap">
        <div class="cms-head reveal">
            <span class="cms-eyebrow">Built for your clinic</span>
            <h2 class="cms-h2">From a one-doctor practice to a busy multi-doctor clinic</h2>
            <p class="cms-lede">The same simple system scales with you — add doctors and staff anytime at no extra cost.</p>
        </div>
        <div class="cms-grid3 reveal">
            <?php foreach ($clinicTypes as [$ic, $name, $desc]): ?>
                <div class="cms-card">
                    <div class="ic" aria-hidden="true"><?= $ic ?></div>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($desc) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="cms-chips reveal">
            <?php foreach ($specialtyLinks as [$href, $label]): ?>
                <a href="<?= e($href) ?>"><?= e($label) ?> →</a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════ CHALLENGES ═══════ -->
<section class="cms-sec alt">
    <div class="wrap">
        <div class="cms-head reveal">
            <span class="cms-eyebrow">Problems we solve</span>
            <h2 class="cms-h2">The everyday clinic headaches, sorted</h2>
        </div>
        <div class="cms-grid4 reveal">
            <?php foreach ($challenges as [$ic, $name, $desc]): ?>
                <div class="cms-card cms-challenge">
                    <div class="ic" aria-hidden="true"><?= $ic ?></div>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($desc) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════ HOW TO START ═══════ -->
<section class="cms-sec">
    <div class="wrap">
        <div class="cms-head reveal">
            <span class="cms-eyebrow">Getting started</span>
            <h2 class="cms-h2">Live in three simple steps</h2>
        </div>
        <div class="cms-steps reveal">
            <?php foreach ($steps as [$name, $desc]): ?>
                <div class="cms-step">
                    <h3><?= e($name) ?></h3>
                    <p><?= e($desc) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════ PRICE ═══════ -->
<section class="cms-sec alt" id="pricing">
    <div class="wrap">
        <div class="cms-price reveal">
            <div>
                <span class="cms-eyebrow">Simple pricing</span>
                <div class="cms-price-amt"><small>₹</small><?= e(ltrim($priceLabel, '₹')) ?> <span>/month + GST</span></div>
                <p class="cms-price-sub">One plan. Everything included. Billed monthly — no long contract.</p>
                <div class="cms-price-actions">
                    <a href="<?= e($signupUrl) ?>" class="cms-btn cms-btn-solid">Sign up →</a>
                    <a href="/pricing" class="cms-btn cms-btn-line">See pricing details</a>
                </div>
            </div>
            <ul>
                <?php foreach ($modules as $m): ?><li><?= e($m['name']) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<!-- ═══════ FAQ ═══════ -->
<section class="cms-sec">
    <div class="wrap">
        <div class="cms-head reveal">
            <span class="cms-eyebrow">FAQ</span>
            <h2 class="cms-h2">Frequently asked questions</h2>
        </div>
        <div class="cms-faq reveal">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
                <details<?= $i === 0 ? ' open' : '' ?>>
                    <summary><?= e($q) ?></summary>
                    <p><?= e($a) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════ CTA ═══════ -->
<section class="cms-sec" style="padding-top: 0;">
    <div class="wrap">
        <div class="cms-cta reveal">
            <h2>Get started with eClinicPro</h2>
            <p>Spend less time on paperwork and more time with patients. Sign up in minutes, or let us show you around first.</p>
            <div class="cms-hero-ctas">
                <a href="<?= e($signupUrl) ?>" class="cms-btn cms-btn-solid">Sign up now</a>
                <a href="/book-a-demo" data-open-demo-modal class="cms-btn cms-btn-line">Book a free demo</a>
            </div>
        </div>
    </div>
</section>

</div>

<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'eClinicPro',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => $metaDesc,
            'offers' => ['@type' => 'Offer', 'price' => (string) $plan['monthly'], 'priceCurrency' => 'INR'],
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn ($f) => [
                '@type' => 'Question',
                'name' => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ], $faqs),
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<script>
    /* ─── Feature tabs (roving tabindex, arrow keys) ─── */
    (function () {
        const tabs = Array.from(document.querySelectorAll('.cms-tab'));
        if (!tabs.length) return;
        function select(tab, focus) {
            tabs.forEach(t => {
                const on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
            });
            if (focus) tab.focus();
            tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => select(tab, false));
            tab.addEventListener('keydown', e => {
                const next = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
                if (next === undefined) return;
                e.preventDefault();
                select(tabs[(i + next + tabs.length) % tabs.length], true);
            });
        });
    })();

    /* ─── Auto-play walkthrough: plays while on screen, pausable ─── */
    (function () {
        const player = document.getElementById('cmsPlayer');
        if (!player) return;
        const slides = Array.from(player.querySelectorAll('.cms-slide'));
        const bars = Array.from(player.querySelectorAll('.cms-progress span'));
        const chips = Array.from(player.querySelectorAll('.cms-chapters button'));
        const btn = document.getElementById('cmsPlay');
        const capLabel = document.getElementById('cmsCapLabel');
        const capText = document.getElementById('cmsCapText');
        const DURATION = 5000;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let idx = 0, timer = null, userPaused = reduce, visible = false;

        function show(n) {
            idx = (n + slides.length) % slides.length;
            slides.forEach((s, i) => s.classList.toggle('is-on', i === idx));
            chips.forEach((c, i) => c.classList.toggle('is-on', i === idx));
            bars.forEach((b, i) => {
                b.classList.toggle('done', i < idx);
                b.classList.remove('now');
            });
            void player.offsetWidth; // restart the fill + pan animations
            bars[idx].classList.add('now');
            capLabel.textContent = slides[idx].dataset.label;
            capText.textContent = slides[idx].dataset.caption;
            schedule();
        }
        function schedule() {
            clearTimeout(timer);
            if (player.classList.contains('is-playing')) timer = setTimeout(() => show(idx + 1), DURATION);
        }
        function setPlaying(on) {
            player.classList.toggle('is-playing', on);
            btn.setAttribute('aria-label', on ? 'Pause walkthrough' : 'Play walkthrough');
            if (on) show(idx); else clearTimeout(timer);
        }
        btn.addEventListener('click', () => { userPaused = player.classList.contains('is-playing'); setPlaying(!userPaused); });
        chips.forEach((c, i) => c.addEventListener('click', () => show(i)));

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(entries => {
                visible = entries[0].isIntersecting;
                setPlaying(visible && !userPaused);
            }, { threshold: 0.35 }).observe(player);
        } else {
            setPlaying(!userPaused);
        }
        document.addEventListener('visibilitychange', () => setPlaying(!document.hidden && visible && !userPaused));
    })();
</script>

<?php
$hideFinalCta = true;
$demoDefaultSpecialty = 'General practice';
$demoSpecKey = 'features';
require __DIR__ . '/partials/demo-modal.php';
require __DIR__ . '/partials/footer.php';
