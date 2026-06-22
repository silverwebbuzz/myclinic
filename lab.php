<?php
// =====================================================================
// lab.php — STATIC BLUEPRINT for Lab Tests & Health Packages.
//
// Marketing/preview page for the upcoming diagnostics partnership
// (Sun Pathology Lab + others). Modeled on eclinicpro-health-store.php:
// no DB, no cart, no checkout. Every clickable element triggers a
// "Coming soon" toast via [data-soon].
//
// Layout strategy: REUSE the existing .store-* design system (hero,
// sections, gradient tiles, toast, CTA banner) so this page needs no
// new infrastructure. A small .lab-* block in styles.css adds the few
// lab-specific pieces (package cards with test lists, organ grid, steps).
//
// Placeholder packages/prices below — swap for Sun Pathology's real
// package + price sheet once the partnership is signed.
//
// NOTE: do NOT require/run the clean-URL router here. This page IS a
// dispatch target (/lab -> this file). Re-requiring the router recurses.
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'Lab Tests & Health Packages — Book Online | eClinicPro';
$metaDesc   = 'Book diagnostic lab tests & full-body health packages online. NABL-accredited labs, free home sample collection, digital reports and doctor consults. Launching soon.';
$activePage = 'lab';
$hideFinalCta = true; // renders its own footer CTA banner

// ---------------------------------------------------------------------
// PLACEHOLDER DATA (hardcoded — swap for Sun Pathology's real list)
// ---------------------------------------------------------------------

// Section — Popular Health Packages (the core catalog).
// [emoji, name, [tests], paramCount, price, mrp, off, gradient, badge]
$packages = [
    ['🩺', 'Full Body Checkup — Advanced', ['CBC', 'Lipid Profile', 'Liver Function', 'Kidney Function', 'Thyroid (T3 T4 TSH)', 'HbA1c', 'Vitamin D', 'Vitamin B12'], '85', '₹1,499', '₹4,200', '64% off', 'g-teal', 'Bestseller'],
    ['🩸', 'Diabetes Care Profile', ['Fasting Blood Sugar', 'HbA1c', 'Lipid Profile', 'Kidney Function', 'Urine Routine'], '42', '₹699', '₹1,800', '61% off', 'g-red', ''],
    ['🦋', 'Thyroid Profile — Total', ['T3', 'T4', 'TSH', 'Anti-TPO'], '4', '₹499', '₹1,200', '58% off', 'g-purple', ''],
    ['❤️', 'Heart Health Package', ['Lipid Profile', 'ECG Review', 'hs-CRP', 'Homocysteine', 'Blood Pressure'], '30', '₹999', '₹2,600', '62% off', 'g-red', ''],
    ['💊', 'Vitamin Deficiency Package', ['Vitamin D', 'Vitamin B12', 'Calcium', 'Iron Studies', 'Folate'], '12', '₹899', '₹2,400', '63% off', 'g-amber', ''],
    ['👩', "Women's Wellness — Full", ['CBC', 'Thyroid', 'Iron Studies', 'Vitamin D', 'Vitamin B12', 'PCOS Markers', 'Calcium'], '60', '₹1,299', '₹3,500', '63% off', 'g-purple', 'Popular'],
    ['👴', 'Senior Citizen Package', ['Full Body Profile', 'Diabetes', 'Cardiac', 'Bone Health', 'Liver & Kidney'], '90', '₹1,799', '₹5,000', '64% off', 'g-teal', ''],
    ['🛡️', 'Fever / Infection Panel', ['CBC', 'Malaria', 'Dengue NS1', 'Typhoid', 'CRP', 'Urine Routine'], '20', '₹799', '₹2,000', '60% off', 'g-blue', ''],
];

// Section — Shop by Body Organ / System (the 1mg-style explore grid).
// [emoji, label, gradient]
$organs = [
    ['❤️', 'Heart',   'g-red'],
    ['🫁', 'Lungs',   'g-teal'],
    ['🧬', 'Liver',   'g-amber'],
    ['🫘', 'Kidney',  'g-blue'],
    ['🦋', 'Thyroid', 'g-purple'],
    ['🩸', 'Diabetes','g-red'],
    ['🦴', 'Bone',    'g-blue'],
    ['🧠', 'Brain',   'g-purple'],
    ['🩺', 'Full Body','g-teal'],
    ['💊', 'Vitamins','g-amber'],
    ['🛡️', 'Immunity','g-teal'],
    ['🤰', 'Pregnancy','g-purple'],
];

// Section — Book by Symptom / Health Concern. [emoji, label]
$concerns = [
    ['😴', 'Fatigue & Weakness'],
    ['⚖️', 'Weight Changes'],
    ['💇', 'Hair Fall'],
    ['🤒', 'Recurring Fever'],
    ['🫄', 'Digestive Issues'],
    ['😰', 'Stress & Sleep'],
    ['🦵', 'Joint Pain'],
    ['💓', 'Chest Discomfort'],
];

// Section — Life-stage packages. [emoji, name, blurb, gradient]
$lifeStage = [
    ['👶', 'Child Health',    'Growth, nutrition & immunity screening.', 'g-blue'],
    ['👨', "Men's Wellness",  'Cardiac, sugar, vitamin & liver profile.', 'g-teal'],
    ['👩', "Women's Wellness",'Hormones, thyroid, iron & bone health.', 'g-purple'],
    ['🤰', 'Pregnancy Care',  'Trimester-wise antenatal test panels.', 'g-red'],
    ['👴', 'Senior Care',     'Comprehensive ageing & chronic-care panel.', 'g-amber'],
    ['🏃', 'Fitness Check',   'Pre-workout & metabolic health baseline.', 'g-teal'],
];

// Section — Why book with us. [emoji, label]
$why = [
    ['🏅', 'NABL-Accredited Labs'],
    ['🏠', 'Free Home Sample Collection'],
    ['📱', 'Digital Reports on App'],
    ['⏱️', 'Reports Within 24 Hours'],
    ['👨‍⚕️', 'Free Doctor Consultation'],
    ['💰', 'Up to 70% Off MRP'],
    ['🔒', 'Safe & Hygienic Collection'],
    ['🇮🇳', 'Available Across India'],
];

// Section — How it works. [step, emoji, title, blurb]
$steps = [
    ['1', '🔍', 'Choose a Test or Package', 'Search or browse by organ, symptom or life stage.'],
    ['2', '🏠', 'Book Home Collection', 'Pick a time slot — our phlebotomist visits you.'],
    ['3', '🧪', 'Sample Tested at NABL Lab', 'Processed at accredited partner labs near you.'],
    ['4', '📱', 'Get Digital Reports', 'Reports on the app + a free doctor consult.'],
];

// Section — Partner labs. Generic/neutral placeholders only — no real lab
// brands are named here until a partnership is actually signed, so the page
// never implies an affiliation we don't have. [emoji, label]
$partners = [
    ['🏅', 'NABL-Accredited Labs'],
    ['🔬', 'Certified Pathology Network'],
    ['🏥', 'Trusted Diagnostic Partners'],
    ['📍', 'Pan-India Collection Centres'],
];

require __DIR__ . '/partials/header.php';
?>

<!-- Preview banner ------------------------------------------------------ -->
<div class="store-preview-bar">
    <span class="store-preview-dot"></span>
    Preview only — lab tests &amp; home collection launching soon. Buttons are not active yet.
</div>

<main class="store">

<!-- Hero ---------------------------------------------------------------- -->
<section class="store-hero">
    <div class="wrap">
        <span class="store-eyebrow">Diagnostics &amp; Lab Tests</span>
        <h1>Lab Tests &amp; Health Packages</h1>
        <p class="store-sub">Book trusted diagnostic tests and full-body health packages online — with free home sample collection and digital reports.</p>

        <form class="store-search" data-soon onsubmit="return false">
            <span class="store-search-ico">🔍</span>
            <input type="text" placeholder="Search tests, health packages, e.g. Thyroid, Full Body Checkup, Vitamin D…" aria-label="Search lab tests">
            <button type="button" class="btn btn-primary" data-soon>Search</button>
        </form>

        <div class="store-hero-cta">
            <button type="button" class="btn btn-primary lab-book" data-book="Lab Test Booking">Book a Test</button>
            <button type="button" class="btn btn-ghost" data-soon>View Packages</button>
        </div>

        <ul class="store-trust">
            <li>🏅 NABL-Accredited Labs</li>
            <li>🏠 Free Home Collection</li>
            <li>📱 Digital Reports</li>
            <li>👨‍⚕️ Free Doctor Consult</li>
            <li>💰 Up to 70% Off</li>
        </ul>
    </div>
</section>

<!-- Section — Popular Health Packages ----------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Popular Health Packages</h2>
        <p>Curated test bundles for every need — see exactly what's included. <em>Sample data for layout preview.</em></p>
    </header>
    <div class="store-grid lab-grid-pkg">
        <?php foreach ($packages as [$ico, $name, $tests, $params, $price, $mrp, $off, $g, $badge]): ?>
        <article class="lab-pkg store-card">
            <?php if ($badge): ?><span class="lab-pkg-badge"><?= e($badge) ?></span><?php endif; ?>
            <div class="lab-pkg-top">
                <span class="store-tile <?= $g ?>"><?= $ico ?></span>
                <div>
                    <h3 class="lab-pkg-name"><?= e($name) ?></h3>
                    <span class="lab-pkg-params"><?= e($params) ?> parameters included</span>
                </div>
            </div>
            <ul class="lab-pkg-tests">
                <?php foreach (array_slice($tests, 0, 4) as $t): ?>
                <li><?= e($t) ?></li>
                <?php endforeach; ?>
                <?php if (count($tests) > 4): ?>
                <li class="lab-pkg-more">+<?= count($tests) - 4 ?> more tests</li>
                <?php endif; ?>
            </ul>
            <div class="lab-pkg-foot">
                <div class="store-prod-price">
                    <strong><?= e($price) ?></strong>
                    <s><?= e($mrp) ?></s>
                    <span class="store-prod-off"><?= e($off) ?></span>
                </div>
                <button type="button" class="btn btn-primary lab-book" data-book="<?= e($name) ?>">Book Now</button>
            </div>
            <a href="#" class="store-link" data-soon>View tests included →</a>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section — Shop by Body Organ ---------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Test by Body Organ &amp; System</h2>
            <p>Not sure which test? Start with the part of the body you're worried about.</p>
        </header>
        <div class="store-grid lab-grid-organ">
            <?php foreach ($organs as [$ico, $label, $g]): ?>
            <a href="#" class="store-cond" data-soon>
                <span class="store-tile <?= $g ?>"><?= $ico ?></span>
                <span class="store-cond-label"><?= e($label) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section — Book by Symptom ------------------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Book by Symptom</h2>
        <p>Tell us how you feel — we'll suggest the right tests.</p>
    </header>
    <div class="store-grid store-grid-spec">
        <?php foreach ($concerns as [$ico, $label]): ?>
        <a href="#" class="store-spec store-card" data-soon>
            <span class="store-spec-ico"><?= $ico ?></span>
            <span><?= e($label) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section — Life-stage Packages --------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Packages for Every Stage of Life</h2>
            <p>Screening built around where you are in life.</p>
        </header>
        <div class="store-grid store-grid-coll">
            <?php foreach ($lifeStage as [$ico, $name, $blurb, $g]): ?>
            <a href="#" class="store-coll store-card" data-soon>
                <span class="store-tile <?= $g ?>"><?= $ico ?></span>
                <h3><?= e($name) ?></h3>
                <p><?= e($blurb) ?></p>
                <span class="store-link">View package →</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section — How it works ---------------------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>How It Works</h2>
        <p>From booking to reports in four simple steps.</p>
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

<!-- Section — Why book with us ------------------------------------------ -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Why Book Lab Tests on eClinicPro?</h2>
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

<!-- Section — Partner labs (generic, no named brands until signed) ------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Processed at Accredited Labs</h2>
        <p>Every sample is tested at certified, NABL-accredited diagnostic partners.</p>
    </header>
    <div class="store-grid store-grid-spec">
        <?php foreach ($partners as [$ico, $label]): ?>
        <div class="store-spec store-card">
            <span class="store-spec-ico"><?= $ico ?></span>
            <span><?= e($label) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Footer CTA banner --------------------------------------------------- -->
<section class="store-cta-banner">
    <div class="wrap">
        <h2>Health Checkups, Made Effortless</h2>
        <p>Book diagnostic tests with free home sample collection, digital reports and a free doctor consultation — all in one place.</p>
        <div class="store-cta-actions">
            <button type="button" class="btn btn-primary lab-book" data-book="Lab Test Booking">🧪 Book a Test</button>
            <a href="/find-a-doctor" class="btn btn-ghost">👨‍⚕️ Find a Doctor</a>
            <a href="/eclinicpro-health-store" class="btn btn-ghost">🛒 Health Store</a>
        </div>
    </div>
</section>

</main>

<!-- "Coming soon" toast (shared pattern) -------------------------------- -->
<div id="storeToast" class="store-toast" role="status" aria-live="polite">Coming soon — lab tests are launching shortly. 🧪</div>
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

    // Login-gated booking: "Book Now" requires a registered patient so the
    // booking lives under the patient's account. ecpAuth.require() runs the
    // callback immediately if signed in, else opens the login/signup modal
    // and runs it after a successful login.
    document.addEventListener('click', function (ev) {
        var book = ev.target.closest('[data-book]');
        if (book) {
            ev.preventDefault();
            var pkg = book.getAttribute('data-book') || 'Lab Test';
            if (window.ecpAuth && typeof window.ecpAuth.require === 'function') {
                window.ecpAuth.require('lab_booking', function () {
                    // Patient is logged in here. No booking backend yet —
                    // confirm intent; swap this for the real booking flow.
                    showToast('You’re signed in — “' + pkg + '” booking opens soon. 🧪');
                });
            } else {
                showToast();
            }
            return;
        }

        // Everything else marked data-soon: plain "coming soon" toast.
        var soon = ev.target.closest('[data-soon]');
        if (soon) {
            ev.preventDefault();
            showToast('Coming soon — lab tests are launching shortly. 🧪');
        }
    });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
