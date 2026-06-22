<?php
// =====================================================================
// health-insurance.php — STATIC LEAD-CAPTURE page for Health Insurance.
//
// Modeled on apollo247insurance.com/health-insurance. Marketing/preview
// page for an upcoming insurance partnership: no policies are sold here.
// "Get a Quote" / "Talk to an Advisor" CTAs are LOGIN-GATED via
// window.ecpAuth.require() so every lead is captured under a registered
// patient account (same model as lab.php booking).
//
// Reuses the .store-* design system (hero, sections, gradient tiles,
// toast, CTA banner) + a small .ins-* block in styles.css.
//
// Linked from the FOOTER (Product column), not the header.
//
// Placeholder plans/prices below — swap for the real partner's products
// once an insurance partnership is signed.
//
// NOTE: do NOT require/run the clean-URL router here — this page IS a
// dispatch target (/health-insurance -> this file).
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'Health Insurance Plans — Compare & Buy Online | eClinicPro';
$metaDesc   = 'Compare health insurance plans for individuals, families & seniors. Cashless hospitalisation, tax savings and expert advisor support. Get a free quote — launching soon.';
$activePage = 'insurance';
$hideFinalCta = true; // renders its own footer CTA banner
$noindex = true; // keep out of Google until plans/partner are live —
                 // URL stays shareable for partner demos.

// ---------------------------------------------------------------------
// PLACEHOLDER DATA (hardcoded — swap for the real partner's products)
// ---------------------------------------------------------------------

// Section — Plan types (the core cards). [emoji, name, blurb, [points], gradient, badge]
$plans = [
    ['👤', 'Individual Health Plan', 'Comprehensive cover for a single person.',
        ['Cover from ₹5L to ₹1Cr', 'Cashless at 10,000+ hospitals', 'No-claim bonus', 'Day-care procedures'], 'g-teal', 'Popular'],
    ['👨‍👩‍👧', 'Family Floater Plan', 'One policy that protects your whole family.',
        ['Single shared sum insured', 'Covers spouse, kids & parents', 'Maternity add-on', 'Annual health check-up'], 'g-purple', 'Best value'],
    ['👴', 'Senior Citizen Plan', 'Designed for parents and elders 60+.',
        ['Higher entry age', 'Pre-existing cover (after waiting)', 'Domiciliary treatment', 'Dedicated claims help'], 'g-amber', ''],
    ['🛡️', 'Critical Illness Plan', 'Lump-sum payout on major illness diagnosis.',
        ['Covers cancer, heart, kidney & more', 'Lump-sum on diagnosis', 'Income protection', 'Tax benefits u/s 80D'], 'g-red', ''],
    ['🏥', 'Top-Up / Super Top-Up', 'Boost your existing cover affordably.',
        ['Extra cover at low premium', 'Works above a deductible', 'Family floater option', 'Ideal with employer cover'], 'g-blue', ''],
    ['🦷', 'OPD & Wellness Plan', 'Everyday care — consults, tests & pharmacy.',
        ['Doctor consultations', 'Diagnostic tests', 'Pharmacy benefits', 'Annual wellness check'], 'g-teal', 'New'],
];

// Section — Why insure with us. [emoji, label]
$why = [
    ['💳', 'Cashless Hospitalisation'],
    ['🏥', '10,000+ Network Hospitals'],
    ['⚡', 'Quick Claim Settlement'],
    ['🧑‍💼', 'Free Expert Advisor'],
    ['💰', 'Tax Savings u/s 80D'],
    ['🔄', 'Easy Online Renewals'],
    ['📄', 'Transparent — No Hidden Terms'],
    ['📞', 'Support When You Claim'],
];

// Section — How it works. [step, emoji, title, blurb]
$steps = [
    ['1', '📝', 'Share a Few Details', 'Age, members to cover and your city.'],
    ['2', '🔍', 'Compare Plans', 'See matched plans, premiums and benefits side by side.'],
    ['3', '🧑‍💼', 'Talk to an Advisor', 'Free guidance — pick the right cover, no pressure.'],
    ['4', '✅', 'Get Covered', 'Buy online and get your policy instantly.'],
];

// Section — What's covered. [emoji, label]
$covered = [
    ['🛏️', 'In-patient Hospitalisation'],
    ['🚑', 'Ambulance Charges'],
    ['🔬', 'Pre & Post Hospitalisation'],
    ['💉', 'Day-Care Procedures'],
    ['🤰', 'Maternity (add-on)'],
    ['🩺', 'Annual Health Check-up'],
    ['🧠', 'Mental Wellness (select plans)'],
    ['🦠', 'Modern Treatments'],
];

// Section — FAQ. [q, a]
$faqs = [
    ['What is cashless hospitalisation?', 'At a network hospital, the insurer settles the bill directly — you don’t pay upfront (except non-covered items). We help you find the nearest network hospital.'],
    ['Can I cover my parents on the same plan?', 'Yes — a Family Floater can include parents, or a dedicated Senior Citizen plan may suit them better. An advisor will help you choose.'],
    ['Are pre-existing illnesses covered?', 'Most plans cover pre-existing conditions after a waiting period (typically 2–4 years). Terms vary by plan.'],
    ['Do I get tax benefits?', 'Yes — premiums qualify for deduction under Section 80D of the Income Tax Act, subject to limits.'],
    ['How fast are claims settled?', 'Cashless approvals are often within hours at network hospitals; reimbursement claims depend on document submission.'],
];

require __DIR__ . '/partials/header.php';
?>

<!-- Preview banner ------------------------------------------------------ -->
<div class="store-preview-bar">
    <span class="store-preview-dot"></span>
    Preview only — insurance plans launching soon. Request a quote and an advisor will reach out.
</div>

<main class="store">

<!-- Hero ---------------------------------------------------------------- -->
<section class="store-hero">
    <div class="wrap">
        <span class="store-eyebrow">Health Insurance</span>
        <h1>Health Insurance, Made Simple</h1>
        <p class="store-sub">Compare plans for you, your family and your parents — with cashless hospitalisation, tax savings and a free expert advisor.</p>

        <!-- Quick quote form (login-gated on submit) -->
        <form class="ins-quote store-card" data-quote onsubmit="return false">
            <div class="ins-quote-row">
                <label class="ins-field">
                    <span>Who to cover</span>
                    <select aria-label="Who to cover">
                        <option>Myself</option>
                        <option>Myself &amp; Family</option>
                        <option>Parents / Seniors</option>
                    </select>
                </label>
                <label class="ins-field">
                    <span>Eldest member age</span>
                    <select aria-label="Eldest member age">
                        <option>18–35</option>
                        <option>36–45</option>
                        <option>46–60</option>
                        <option>60+</option>
                    </select>
                </label>
                <label class="ins-field">
                    <span>City</span>
                    <input type="text" placeholder="e.g. Mumbai" aria-label="City">
                </label>
                <button type="button" class="btn btn-primary ins-lead" data-lead="Insurance Quote">Get Free Quote</button>
            </div>
            <p class="ins-quote-note">🔒 Free, no-obligation. An advisor will call to help you choose.</p>
        </form>

        <ul class="store-trust">
            <li>💳 Cashless Hospitalisation</li>
            <li>🏥 10,000+ Hospitals</li>
            <li>💰 Tax Savings 80D</li>
            <li>🧑‍💼 Free Advisor</li>
            <li>⚡ Quick Claims</li>
        </ul>
    </div>
</section>

<!-- Section — Plan types ------------------------------------------------ -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Choose the Right Cover</h2>
        <p>Plans for every need and life stage. <em>Sample plans for layout preview.</em></p>
    </header>
    <div class="store-grid ins-grid-plan">
        <?php foreach ($plans as [$ico, $name, $blurb, $points, $g, $badge]): ?>
        <article class="ins-plan store-card">
            <?php if ($badge): ?><span class="ins-plan-badge"><?= e($badge) ?></span><?php endif; ?>
            <div class="ins-plan-top">
                <span class="store-tile <?= $g ?>"><?= $ico ?></span>
                <div>
                    <h3 class="ins-plan-name"><?= e($name) ?></h3>
                    <p class="ins-plan-blurb"><?= e($blurb) ?></p>
                </div>
            </div>
            <ul class="ins-plan-points">
                <?php foreach ($points as $p): ?>
                <li><?= e($p) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn btn-primary ins-lead" data-lead="<?= e($name) ?>">Get a Quote</button>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section — Why insure with us ---------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Why Buy Insurance on eClinicPro?</h2>
        </header>
        <div class="store-grid store-grid-why">
            <?php foreach ($why as [$ico, $label]): ?>
            <div class="store-why">
                <span class="store-why-ico"><?= $ico ?></span>
                <span><?= e($label) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section — How it works ---------------------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>How It Works</h2>
        <p>From quote to cover in four simple steps.</p>
    </header>
    <div class="store-grid lab-grid-steps">
        <?php foreach ($steps as [$n, $ico, $title, $blurb]): ?>
        <div class="lab-step store-card">
            <span class="lab-step-num"><?= e($n) ?></span>
            <span class="lab-step-ico"><?= $ico ?></span>
            <h3><?= e($title) ?></h3>
            <p><?= e($blurb) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section — What's covered -------------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>What’s Typically Covered</h2>
            <p>Core benefits across our plans — exact terms vary by product.</p>
        </header>
        <div class="store-grid store-grid-spec">
            <?php foreach ($covered as [$ico, $label]): ?>
            <div class="store-spec store-card">
                <span class="store-spec-ico"><?= $ico ?></span>
                <span><?= e($label) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section — FAQ ------------------------------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Frequently Asked Questions</h2>
    </header>
    <div class="ins-faq">
        <?php foreach ($faqs as [$q, $a]): ?>
        <details class="ins-faq-item">
            <summary><?= e($q) ?></summary>
            <p><?= e($a) ?></p>
        </details>
        <?php endforeach; ?>
    </div>
</section>

<!-- Footer CTA banner --------------------------------------------------- -->
<section class="store-cta-banner">
    <div class="wrap">
        <h2>Protect What Matters Most</h2>
        <p>Get a free, no-obligation quote and let an expert advisor help you find the right health cover for your family.</p>
        <div class="store-cta-actions">
            <button type="button" class="btn btn-primary ins-lead" data-lead="Insurance Advisor">🧑‍💼 Talk to an Advisor</button>
            <a href="/find-a-doctor" class="btn btn-ghost">👨‍⚕️ Find a Doctor</a>
            <a href="/lab" class="btn btn-ghost">🧪 Lab Tests</a>
        </div>
    </div>
</section>

</main>

<!-- "Coming soon" toast (shared pattern) -------------------------------- -->
<div id="storeToast" class="store-toast" role="status" aria-live="polite">Coming soon 🛡️</div>
<script>
(function () {
    var toast = document.getElementById('storeToast');
    var timer = null;
    function showToast(msg) {
        if (!toast) return;
        if (msg) toast.textContent = msg;
        toast.classList.add('is-on');
        clearTimeout(timer);
        timer = setTimeout(function () { toast.classList.remove('is-on'); }, 2800);
    }

    // Login-gated lead capture: quote / advisor CTAs require a registered
    // patient, so every insurance lead lives under the patient's account.
    document.addEventListener('click', function (ev) {
        var lead = ev.target.closest('[data-lead]');
        if (lead) {
            ev.preventDefault();
            var plan = lead.getAttribute('data-lead') || 'Health Insurance';
            if (window.ecpAuth && typeof window.ecpAuth.require === 'function') {
                window.ecpAuth.require('insurance_lead', function () {
                    // Patient is logged in here. No insurance backend yet —
                    // confirm the lead; swap for the real advisor flow later.
                    showToast('Thanks — you’re signed in. An advisor will reach out about “' + plan + '”. 🛡️');
                });
            } else {
                showToast('Coming soon — insurance plans are launching shortly. 🛡️');
            }
            return;
        }

        // Quote form submit (button inside it is data-lead, handled above).
        // Any other data-quote interaction falls through to no-op.
        var soon = ev.target.closest('[data-soon]');
        if (soon) {
            ev.preventDefault();
            showToast('Coming soon 🛡️');
        }
    });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
