<?php
// =====================================================================
// for-patients.php — patient-facing landing page.
//
// Sells the FREE patient account: what a patient gets on eClinicPro
// (bookings, shortlist, family profiles, e-prescriptions, my profile)
// and drives free registration. Registration == the phone-OTP login on
// /patient (first verify creates the identity), so every CTA points there.
//
// Standalone marketing page — mirrors features.php: helpers → header →
// markup → footer.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'For Patients — Book doctors, keep your family’s health in one place | eClinicPro';
$metaDesc  = 'Create a free eClinicPro patient account: book verified doctors, shortlist favourites, manage family health profiles, store prescriptions, and keep your medical details safe. Free forever — sign in with your phone.';
$activePage = 'for-patients';

// Feature benefit cards — each maps to a real patient-panel capability.
$patientFeatures = [
    ['🔍', 'Find & book verified doctors',
        'Search doctors by city and specialty, see real profiles, and book an appointment online in seconds — even with clinics not yet on eClinicPro.'],
    ['📅', 'All your bookings in one place',
        'Upcoming and past appointments, token numbers, and one-tap call to the clinic. Track pending requests until the clinic confirms.'],
    ['❤️', 'Shortlist your favourite doctors',
        'Save up to 5 doctors you trust so re-booking is one tap away — no searching again next time.'],
    ['👨‍👩‍👧', 'Family health profiles',
        'Add up to 6 family members with their date of birth, gender, blood group and ABHA number. Book for them and share details with the clinic instantly.'],
    ['💊', 'Your e-prescription vault',
        'Upload prescriptions you already have, and receive new ones straight from your eClinicPro doctor — all in one secure place, always with you.'],
    ['👤', 'One profile, everywhere',
        'Keep your contact, address, emergency contact, allergies and chronic conditions ready — so a clinic never asks you to fill the same form twice.'],
];

// Why it's safe / why register — trust points.
$patientTrust = [
    ['🔒', 'Private by default',
        'A clinic only ever sees what you choose to share when you book. Your data is isolated per clinic — never sold, never spammed.'],
    ['📱', 'No password, no hassle',
        'Sign in with just your phone number and a one-time code. Your account is created the first time you verify — nothing to remember.'],
    ['🆓', 'Free forever',
        'The patient account costs nothing. No card, no subscription, no hidden fees — book, store and manage your health at no charge.'],
    ['🇮🇳', 'Built for India',
        'ABHA-ready, works on any phone, and made for how Indian families actually manage health together.'],
];

// FAQ — single source of truth: rendered as the visible accordion AND
// fed into FAQPage JSON-LD for Google rich results.
$patientFaqs = [
    ['Is the patient account really free?',
        'Yes — completely free, forever. There is no card required, no subscription and no hidden fees. You can book doctors, add family members, store prescriptions and manage your health details at no cost.'],
    ['Do I need to create a password?',
        'No. You sign in with just your mobile number and a one-time code (OTP) sent by SMS. Your free account is created automatically the first time you verify — there is nothing to remember.'],
    ['Is my health data safe and private?',
        'Your data is private by default. A clinic only ever sees what you choose to share when you book an appointment, and your information is isolated per clinic. We never sell your data or send you spam.'],
    ['Do I need to download an app?',
        'No app needed. eClinicPro works right in your phone or computer browser — just open the site and sign in. It is built to work smoothly on any phone.'],
    ['Can I manage my family’s health too?',
        'Yes. You can add up to 6 family members with their date of birth, gender, blood group and ABHA number, book appointments for them, and share their details with the clinic instantly.'],
    ['Can I book a doctor who is not yet on eClinicPro?',
        'Yes. If a clinic has not joined yet, you can still send a booking request — we notify the clinic and track it for you until they confirm.'],
    ['What can I do with my prescriptions?',
        'Your e-prescription vault keeps everything in one place. You can upload prescriptions you already have, and receive new ones straight from your eClinicPro doctor — always with you, whenever you need them.'],
    ['What is ABHA and do I need it?',
        'ABHA (Ayushman Bharat Health Account) is India’s digital health ID. It is optional — you can add it to your profile and family members if you have one, but you can use every feature without it.'],
];

// FAQPage structured data (rich results) built from the same array.
$faqLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(static function (array $f): array {
        return [
            '@type'          => 'Question',
            'name'           => $f[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
        ];
    }, $patientFaqs),
];

ob_start(); ?>
<script type="application/ld+json">
<?= json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<?php
$extraHead = ob_get_clean();

require __DIR__ . '/partials/header.php';
?>

<style>
    .fp-hero {
        padding: 104px 0 68px;
        text-align: center;
        position: relative;
        overflow: hidden;
        background: #f0f4f1;
    }
    .fp-hero-glow {
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 50% 0%, rgba(26, 122, 78, 0.08) 0%, transparent 65%);
        pointer-events: none;
    }
    .fp-hero-inner { position: relative; max-width: 780px; margin: 0 auto; }
    .fp-hero h1 {
        font-size: clamp(30px, 5vw, 48px);
        line-height: 1.08;
        letter-spacing: -0.02em;
        margin: 0 0 18px;
    }
    .fp-hero h1 .accent { color: #1a7a4e; }
    .fp-hero .lede {
        font-size: clamp(16px, 2.2vw, 19px);
        color: #4a5a52;
        max-width: 620px;
        margin: 0 auto 30px;
        line-height: 1.55;
    }
    .fp-hero-ctas { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .fp-hero-note { margin-top: 16px; font-size: 13px; color: #6b7d73; }

    .fp-section { padding: 66px 0; }
    .fp-section.alt { background: #f7faf8; }
    .fp-section-head { text-align: center; max-width: 640px; margin: 0 auto 44px; }
    .fp-section-head h2 { font-size: clamp(24px, 3.4vw, 34px); letter-spacing: -0.02em; margin: 8px 0 12px; }
    .fp-section-head p { color: #55655c; font-size: 16px; line-height: 1.55; margin: 0; }

    .fp-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
    .fp-card {
        background: #fff;
        border: 1px solid #e6ece8;
        border-radius: 16px;
        padding: 26px 24px;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .fp-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(26,122,78,0.08);
        border-color: #cfe0d6;
    }
    .fp-card-ic {
        width: 52px; height: 52px;
        display: grid; place-items: center;
        font-size: 26px;
        border-radius: 13px;
        background: linear-gradient(135deg, #e8f5ee, #d6ebdf);
        margin-bottom: 16px;
    }
    .fp-card h3 { font-size: 18px; margin: 0 0 8px; letter-spacing: -0.01em; }
    .fp-card p { color: #55655c; font-size: 14.5px; line-height: 1.55; margin: 0; }

    /* How it works — 3 steps */
    .fp-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; counter-reset: step; }
    .fp-step { position: relative; padding: 26px 22px 22px; background: #fff; border: 1px solid #e6ece8; border-radius: 16px; }
    .fp-step::before {
        counter-increment: step; content: counter(step);
        display: grid; place-items: center;
        width: 38px; height: 38px; border-radius: 50%;
        background: #1a7a4e; color: #fff; font-weight: 700; font-size: 17px;
        margin-bottom: 14px;
    }
    .fp-step h3 { font-size: 17px; margin: 0 0 7px; }
    .fp-step p { color: #55655c; font-size: 14px; line-height: 1.5; margin: 0; }

    .fp-final {
        text-align: center;
        background: linear-gradient(135deg, #10603b, #1a7a4e);
        color: #fff;
        border-radius: 22px;
        padding: 56px 28px;
        margin: 0 auto;
    }
    .fp-final h2 { font-size: clamp(24px, 3.4vw, 34px); margin: 0 0 12px; letter-spacing: -0.02em; }
    .fp-final p { color: rgba(255,255,255,0.9); font-size: 17px; margin: 0 auto 26px; max-width: 520px; line-height: 1.5; }
    .fp-final .btn-primary { background: #fff; color: #10603b; }
    .fp-final .btn-primary:hover { background: #f0f4f1; }

    /* FAQ accordion (native <details>, no JS) */
    .fp-faq { max-width: 760px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
    .fp-faq-item {
        background: #fff;
        border: 1px solid #e6ece8;
        border-radius: 14px;
        overflow: hidden;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .fp-faq-item[open] { border-color: #cfe0d6; box-shadow: 0 8px 24px rgba(26,122,78,0.06); }
    .fp-faq-item summary {
        list-style: none;
        cursor: pointer;
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        padding: 18px 20px;
        font-size: 16px; font-weight: 600; color: #1c2a22;
    }
    .fp-faq-item summary::-webkit-details-marker { display: none; }
    .fp-faq-item summary:hover { color: #1a7a4e; }
    .fp-faq-chev { flex-shrink: 0; color: #6b7d73; transition: transform .2s ease; }
    .fp-faq-item[open] .fp-faq-chev { transform: rotate(180deg); color: #1a7a4e; }
    .fp-faq-a { padding: 0 20px 18px; }
    .fp-faq-a p { margin: 0; color: #55655c; font-size: 15px; line-height: 1.6; }

    @media (max-width: 600px) {
        .fp-hero { padding: 84px 0 52px; }
        .fp-section { padding: 48px 0; }
        .fp-final { padding: 40px 20px; border-radius: 18px; }
        .fp-faq-item summary { font-size: 15px; padding: 16px; }
        .fp-faq-a { padding: 0 16px 16px; }
    }
</style>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="fp-hero">
    <div class="fp-hero-glow"></div>
    <div class="wrap fp-hero-inner reveal">
        <span class="hp-eyebrow">For Patients · Free forever</span>
        <h1>Your health, and your whole family’s — <span class="accent">in one free account</span></h1>
        <p class="lede">
            Book verified doctors, keep everyone’s prescriptions and health details in one safe place,
            and never fill the same clinic form twice. Sign in with just your phone — no password, no cost.
        </p>
        <div class="fp-hero-ctas">
            <a href="/patient" class="btn btn-primary btn-lg">Create your free account</a>
            <a href="/find-a-doctor" class="btn btn-ghost btn-lg">Find a doctor →</a>
        </div>
        <p class="fp-hero-note">Takes 30 seconds · No credit card · Works on any phone</p>
    </div>
</section>

<!-- ═══════════════ WHAT YOU GET ═══════════════ -->
<section class="fp-section">
    <div class="wrap">
        <div class="fp-section-head reveal">
            <span class="hp-eyebrow">What you get</span>
            <h2>Everything you need to manage your family’s health</h2>
            <p>One free account unlocks all of this — for you and up to six family members.</p>
        </div>
        <div class="fp-grid">
            <?php foreach ($patientFeatures as $i => [$ic, $title, $body]): ?>
                <?php
                // Anchor targets for the footer "For patients" links.
                $cardId = $i === 3 ? 'family' : ($i === 4 ? 'rx' : '');
                ?>
                <div class="fp-card reveal"<?= $cardId ? ' id="' . $cardId . '"' : '' ?>>
                    <div class="fp-card-ic"><?= $ic ?></div>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($body) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ HOW IT WORKS ═══════════════ -->
<section class="fp-section alt">
    <div class="wrap">
        <div class="fp-section-head reveal">
            <span class="hp-eyebrow">How it works</span>
            <h2>Registered in three simple steps</h2>
        </div>
        <div class="fp-steps">
            <div class="fp-step reveal">
                <h3>Enter your phone</h3>
                <p>Go to your patient panel and type your mobile number — nothing else needed to start.</p>
            </div>
            <div class="fp-step reveal">
                <h3>Verify with an OTP</h3>
                <p>We text you a one-time code. Enter it and your free account is created instantly.</p>
            </div>
            <div class="fp-step reveal">
                <h3>You’re in</h3>
                <p>Book doctors, add family, save prescriptions and fill your profile — all in one place.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ WHY REGISTER / TRUST ═══════════════ -->
<section class="fp-section">
    <div class="wrap">
        <div class="fp-section-head reveal">
            <span class="hp-eyebrow">Why register</span>
            <h2>Safe, simple, and always free</h2>
        </div>
        <div class="fp-grid">
            <?php foreach ($patientTrust as [$ic, $title, $body]): ?>
                <div class="fp-card reveal">
                    <div class="fp-card-ic"><?= $ic ?></div>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($body) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ FAQ ═══════════════ -->
<section class="fp-section alt" id="faq">
    <div class="wrap">
        <div class="fp-section-head reveal">
            <span class="hp-eyebrow">Questions</span>
            <h2>Frequently asked questions</h2>
            <p>Everything patients usually ask before creating a free account.</p>
        </div>
        <div class="fp-faq reveal">
            <?php foreach ($patientFaqs as $i => [$q, $a]): ?>
                <details class="fp-faq-item"<?= $i === 0 ? ' open' : '' ?>>
                    <summary>
                        <span><?= e($q) ?></span>
                        <svg class="fp-faq-chev" width="20" height="20" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <div class="fp-faq-a"><p><?= e($a) ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ FINAL CTA ═══════════════ -->
<section class="fp-section">
    <div class="wrap">
        <div class="fp-final reveal">
            <h2>Create your free patient account today</h2>
            <p>Join thousands of patients across India managing their family’s health the simple way.</p>
            <a href="/patient" class="btn btn-primary btn-lg">Get started free →</a>
        </div>
    </div>
</section>

<?php
// This page has its own final CTA — suppress the doctor-focused one.
$hideFinalCta = true;
require __DIR__ . '/partials/footer.php';
?>
