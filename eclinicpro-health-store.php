<?php
// =====================================================================
// eclinicpro-health-store.php — STATIC BLUEPRINT for the future store.
//
// This is a visual reference page (not a working store). It shows the
// full taxonomy — health conditions, categories + subcategories, brands,
// specialist recommendations, collections — so the same structure can be
// rebuilt in Shopify later. No DB, no cart, no checkout. Every clickable
// element triggers a "Coming soon" toast.
//
// Built on the shared site shell (header/footer partials) and styled with
// the existing assets/css/styles.css design tokens (.store-* block).
// =====================================================================

// NOTE: do NOT require/run the clean-URL router here. This page IS a target
// the router dispatches to (/eclinicpro-health-store -> this file). Calling
// the router again would re-require this file and recurse → HTTP 500.
// Pages like features.php / find-a-doctor.php render directly, same as here.
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'eClinicPro Health Store — Doctor-Recommended Wellness & Care';
$metaDesc   = 'Trusted health, wellness & care products recommended by healthcare professionals — supplements, devices, baby care, Ayurveda and more. Launching soon.';
$activePage = 'store';
$hideFinalCta = true; // this page renders its own footer CTA banner
$noindex = true; // keep out of Google until the store is live —
                 // URL stays shareable for partner demos.

// ---------------------------------------------------------------------
// TAXONOMY (all hardcoded — this is a Shopify blueprint, not live data)
// ---------------------------------------------------------------------

// Section 1 — Shop by Health Condition. [emoji, label, gradient-class]
$conditions = [
    ['🩸', 'Diabetes Care', 'g-teal'],
    ['❤️', 'Heart Health', 'g-red'],
    ['⚖️', 'Weight Management', 'g-amber'],
    ['🛡️', 'Immunity Support', 'g-teal'],
    ['🍽️', 'Digestive Health', 'g-amber'],
    ['🦴', 'Joint & Bone Care', 'g-blue'],
    ['😴', 'Sleep & Stress Care', 'g-purple'],
    ['👩', "Women's Wellness", 'g-purple'],
    ['🤰', 'Pregnancy Care', 'g-red'],
    ['👶', 'Baby Care', 'g-blue'],
    ['👴', 'Senior Care', 'g-teal'],
    ['👁️', 'Eye Care', 'g-blue'],
    ['🫁', 'Respiratory Care', 'g-teal'],
    ['💇', 'Hair Care', 'g-amber'],
    ['✨', 'Skin Care', 'g-purple'],
    ['🦷', 'Oral Care', 'g-blue'],
];

// Section 2 — Shop by Category. Each: [emoji, name, gradient, [subcategories]]
$categories = [
    ['💊', 'Vitamins & Supplements', 'g-teal',
        ['Multivitamins', 'Vitamin D', 'Vitamin B12', 'Calcium', 'Iron', 'Omega 3', 'Probiotics']],
    ['🥤', 'Nutrition & Healthy Foods', 'g-amber',
        ['Protein Powders', 'Meal Replacements', 'Healthy Drinks', 'Diabetic Nutrition', 'Healthy Snacks', 'Herbal Drinks']],
    ['🧴', 'Personal Care', 'g-purple',
        ['Skin Care', 'Hair Care', 'Oral Care', "Men's Grooming", "Women's Hygiene"]],
    ['👶', 'Baby & Mother Care', 'g-blue',
        ['Baby Food', 'Formula Milk', 'Diapers', 'Wipes', 'Baby Skin Care', 'Nursing Products']],
    ['🩺', 'Medical Devices', 'g-teal',
        ['BP Monitors', 'Glucometers', 'Test Strips', 'Pulse Oximeters', 'Thermometers', 'Nebulizers']],
    ['🦵', 'Orthopedic & Pain Relief', 'g-blue',
        ['Knee Supports', 'Back Supports', 'Cervical Pillows', 'Compression Wear', 'Heating Pads', 'Orthopedic Accessories']],
    ['🏋️', 'Fitness & Recovery', 'g-amber',
        ['Resistance Bands', 'Yoga Mats', 'Foam Rollers', 'Recovery Tools', 'Massage Devices']],
    ['🌿', 'Ayurveda & Natural Care', 'g-teal',
        ['Herbal Supplements', 'Immunity Boosters', 'Digestive Care', 'Stress Relief', 'Ayurvedic Wellness']],
];

// Section 3 — Browse by Specialist Recommendation (the USP). [emoji, label]
$specialists = [
    ['🧴', 'Dermatologist Recommended'],
    ['🩸', 'Diabetologist Recommended'],
    ['🦵', 'Physiotherapist Recommended'],
    ['🥗', 'Nutritionist Recommended'],
    ['👩‍⚕️', 'Gynecologist Recommended'],
    ['👶', 'Pediatrician Recommended'],
    ['❤️', 'Cardiologist Recommended'],
    ['🦴', 'Orthopedic Recommended'],
];

// Section 4 — Doctor-Recommended Collections (kits). [emoji, name, blurb, gradient]
$collections = [
    ['🩸', 'Diabetes Essentials', 'Glucometer, test strips, diabetic nutrition & foot care.', 'g-teal'],
    ['❤️', 'Heart Health Kit', 'BP monitor, omega-3, heart-smart nutrition.', 'g-red'],
    ['💇', 'Hair Fall Care Kit', 'Serums, supplements & gentle care for hair fall.', 'g-amber'],
    ['✨', 'Skin Care Essentials', 'Cleanse, treat & protect — dermatologist picks.', 'g-purple'],
    ['⚖️', 'Weight Loss Starter Kit', 'Meal replacements, fitness bands & guidance.', 'g-amber'],
    ['👩', 'PCOS Care Collection', 'Supplements & nutrition for PCOS support.', 'g-purple'],
    ['🦴', 'Joint Pain Relief Kit', 'Supports, supplements & recovery tools.', 'g-blue'],
    ['👴', 'Senior Wellness Kit', 'Daily essentials for healthy ageing.', 'g-teal'],
];

// Section 5 — Popular Health Concerns (dummy counts — placeholder data).
$concerns = [
    ['Hair Fall', '250+'],
    ['Weight Loss', '180+'],
    ['Diabetes', '320+'],
    ['Joint Pain', '145+'],
    ['Skin Care', '500+'],
    ["Women's Wellness", '220+'],
];

// Section — Shop by Brand (styled text tiles, no logo files needed).
$brands = [
    'Himalaya', 'Dabur', 'Baidyanath', 'Patanjali', 'Cetaphil', 'Sebamed',
    'Omron', 'Accu-Chek', 'Dr Trust', 'Ensure', 'Protinex', 'Nestlé',
    'Mamaearth', 'Johnson & Johnson', 'Pampers', 'Pediasure',
];

// Section 6 — Featured Products (dummy product cards — placeholder data).
$products = [
    ['Omron', 'HEM-7124 BP Monitor', '🩺', '₹1,899', '₹2,499', '24% off', 'g-teal'],
    ['Accu-Chek', 'Active Glucometer Kit', '🩸', '₹699', '₹999', '30% off', 'g-red'],
    ['Ensure', 'Diabetes Care Nutrition', '🥤', '₹720', '₹850', '15% off', 'g-amber'],
    ['Cetaphil', 'Gentle Skin Cleanser 250ml', '🧴', '₹449', '₹560', '20% off', 'g-purple'],
    ['Himalaya', 'Ashwagandha Tablets', '🌿', '₹180', '₹220', '18% off', 'g-teal'],
    ['Dr Trust', 'Pulse Oximeter', '🫁', '₹999', '₹1,499', '33% off', 'g-blue'],
];

// Section 7 — Why shop. [emoji, label]
$why = [
    ['👨‍⚕️', 'Doctor Recommended'],
    ['🏆', 'Trusted Brands Only'],
    ['🚚', 'Fast Delivery Across India'],
    ['🔒', 'Secure Payments'],
    ['💯', 'Genuine Products'],
    ['📞', 'Healthcare Support'],
    ['↩️', 'Easy Returns'],
    ['⭐', 'Thousands of Happy Customers'],
];

// Section 8 — Articles / buying guides. [emoji, title, blurb]
$articles = [
    ['🩸', 'Best Glucometers in India', 'How to pick an accurate, easy-to-use glucometer.'],
    ['🩺', 'How to Choose a BP Monitor', 'Cuff size, validation & features that matter.'],
    ['🛡️', 'Best Vitamins for Immunity', 'Evidence-based picks for daily immune support.'],
    ['💇', 'Hair Fall Prevention Guide', 'What actually works — and what to skip.'],
    ['⚖️', 'Weight Loss Nutrition Guide', 'Build a sustainable, doctor-backed plan.'],
    ['👶', 'Baby Care Essentials', 'A new-parent checklist for the first year.'],
];

// Mega-menu blueprint columns shown on-page. [title, [items]]
$megaMenu = [
    ['Shop by Health Condition', ['Diabetes Care', 'Heart Health', 'Weight Management', 'Immunity', 'Digestive Health', 'Skin Care', 'Hair Care', "Women's Health", 'Pregnancy', 'Baby Care', 'Senior Care']],
    ['Vitamins & Supplements', ['Multivitamins', 'Calcium', 'Omega 3', 'Iron', 'Vitamin D', 'Vitamin B12']],
    ['Nutrition', ['Protein Powders', 'Meal Replacements', 'Healthy Drinks', 'Healthy Foods']],
    ['Personal Care', ['Skin Care', 'Hair Care', 'Oral Care', 'Hygiene']],
    ['Medical Devices', ['BP Monitor', 'Glucometer', 'Nebulizer', 'Thermometer', 'Oximeter']],
    ['Orthopedic Care', ['Knee Support', 'Back Support', 'Cervical Pillow', 'Physiotherapy']],
    ['Ayurveda & Natural Care', ['Digestive Care', 'Immunity', 'Stress Relief', 'Herbal Wellness']],
];

require __DIR__ . '/partials/header.php';
?>

<!-- Preview banner ------------------------------------------------------ -->
<div class="store-preview-bar">
    <span class="store-preview-dot"></span>
    Preview only — store launching soon. This page is a design blueprint; buttons are not active yet.
</div>

<main class="store">

<!-- Hero ---------------------------------------------------------------- -->
<section class="store-hero">
    <div class="wrap">
        <span class="store-eyebrow">Doctor-Recommended Wellness Store</span>
        <h1>eClinicPro Health Store</h1>
        <p class="store-sub">Trusted health, wellness &amp; care products from leading brands and healthcare experts.</p>

        <form class="store-search" data-soon onsubmit="return false">
            <span class="store-search-ico">🔍</span>
            <input type="text" placeholder="Search vitamins, supplements, health devices, baby care, skin care and more…" aria-label="Search the store">
            <button type="button" class="btn btn-primary" data-soon>Search</button>
        </form>

        <div class="store-hero-cta">
            <button type="button" class="btn btn-primary" data-soon>Shop Now</button>
            <button type="button" class="btn btn-ghost" data-soon>Browse Categories</button>
        </div>

        <ul class="store-trust">
            <li>✅ Genuine Products</li>
            <li>🚚 Fast Delivery</li>
            <li>👨‍⚕️ Doctor Recommended</li>
            <li>🔒 Secure Payments</li>
            <li>⭐ Trusted Brands</li>
        </ul>
    </div>
</section>

<!-- Section 1 — Shop by Health Condition -------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Shop by Health Condition</h2>
        <p>People think in conditions, not categories — start where it matters to you.</p>
    </header>
    <div class="store-grid store-grid-cond">
        <?php foreach ($conditions as [$ico, $label, $g]): ?>
        <a href="#" class="store-cond" data-soon>
            <span class="store-tile <?= $g ?>"><?= $ico ?></span>
            <span class="store-cond-label"><?= e($label) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section 2 — Shop by Category ---------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Shop by Category</h2>
            <p>Every shelf of the store, organised the way you shop.</p>
        </header>
        <div class="store-grid store-grid-cat">
            <?php foreach ($categories as [$ico, $name, $g, $subs]): ?>
            <article class="store-cat store-card">
                <span class="store-tile <?= $g ?>"><?= $ico ?></span>
                <h3><?= e($name) ?></h3>
                <ul class="store-sublist">
                    <?php foreach ($subs as $sub): ?>
                    <li><a href="#" data-soon><?= e($sub) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section 3 — Browse by Specialist Recommendation (USP) --------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Browse by Specialist Recommendation</h2>
        <p>Curated by the specialists you already trust on eClinicPro.</p>
    </header>
    <div class="store-grid store-grid-spec">
        <?php foreach ($specialists as [$ico, $label]): ?>
        <a href="#" class="store-spec store-card" data-soon>
            <span class="store-spec-ico"><?= $ico ?></span>
            <span><?= e($label) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section 4 — Doctor-Recommended Collections -------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Doctor-Recommended Collections</h2>
            <p>Ready-made kits for common goals — everything in one place.</p>
        </header>
        <div class="store-grid store-grid-coll">
            <?php foreach ($collections as [$ico, $name, $blurb, $g]): ?>
            <a href="#" class="store-coll store-card" data-soon>
                <span class="store-tile <?= $g ?>"><?= $ico ?></span>
                <h3><?= e($name) ?></h3>
                <p><?= e($blurb) ?></p>
                <span class="store-link">Explore kit →</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section 5 — Popular Health Concerns --------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Popular Health Concerns</h2>
        <p>What people are shopping for most.</p>
    </header>
    <div class="store-grid store-grid-concern">
        <?php foreach ($concerns as [$label, $count]): ?>
        <a href="#" class="store-concern store-card" data-soon>
            <span class="store-concern-count"><?= e($count) ?></span>
            <span class="store-concern-label"><?= e($label) ?></span>
            <span class="store-concern-meta">products</span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section 6 — Featured Products --------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Featured Products</h2>
            <p>A taste of the catalog. <em>Sample data for layout preview.</em></p>
        </header>
        <div class="store-grid store-grid-prod">
            <?php foreach ($products as [$brand, $name, $ico, $price, $mrp, $off, $g]): ?>
            <article class="store-prod store-card">
                <span class="store-prod-fav" data-soon title="Wishlist">♡</span>
                <span class="store-prod-img <?= $g ?>"><?= $ico ?></span>
                <span class="store-prod-brand"><?= e($brand) ?></span>
                <h3 class="store-prod-name"><?= e($name) ?></h3>
                <div class="store-prod-price">
                    <strong><?= e($price) ?></strong>
                    <s><?= e($mrp) ?></s>
                    <span class="store-prod-off"><?= e($off) ?></span>
                </div>
                <button type="button" class="btn btn-primary store-prod-add" data-soon>Add to Cart</button>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section — Shop by Brand --------------------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Shop by Brand</h2>
        <p>Genuine products from the brands you know.</p>
    </header>
    <div class="store-grid store-grid-brand">
        <?php foreach ($brands as $brand): ?>
        <a href="#" class="store-brand store-card" data-soon>
            <span class="store-brand-mark"><?= e(mb_substr($brand, 0, 1)) ?></span>
            <span class="store-brand-name"><?= e($brand) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section 7 — Why shop ------------------------------------------------ -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Why Shop From eClinicPro Health Store?</h2>
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

<!-- Section 8 — Articles & Buying Guides -------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Health Articles &amp; Buying Guides</h2>
        <p>Make confident choices with doctor-backed guides.</p>
    </header>
    <div class="store-grid store-grid-article">
        <?php foreach ($articles as [$ico, $title, $blurb]): ?>
        <a href="#" class="store-article store-card" data-soon>
            <span class="store-article-ico"><?= $ico ?></span>
            <h3><?= e($title) ?></h3>
            <p><?= e($blurb) ?></p>
            <span class="store-link">Read guide →</span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Mega-menu blueprint ------------------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>“Shop” Mega-Menu Blueprint</h2>
            <p>Reference structure for the future store navigation.</p>
        </header>
        <div class="store-grid store-grid-mega">
            <?php foreach ($megaMenu as [$title, $items]): ?>
            <div class="store-mega-col">
                <h4><?= e($title) ?></h4>
                <ul>
                    <?php foreach ($items as $it): ?>
                    <li><a href="#" data-soon><?= e($it) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Newsletter ---------------------------------------------------------- -->
<section class="store-section wrap">
    <div class="store-newsletter store-card">
        <h2>Stay Healthy. Stay Updated.</h2>
        <p>Get doctor-backed wellness tips and launch news in your inbox.</p>
        <form class="store-news-form" data-soon onsubmit="return false">
            <input type="email" placeholder="Email address" aria-label="Email address">
            <button type="button" class="btn btn-primary" data-soon>Subscribe</button>
        </form>
    </div>
</section>

<!-- Footer CTA banner --------------------------------------------------- -->
<section class="store-cta-banner">
    <div class="wrap">
        <h2>Your Complete Health &amp; Wellness Destination</h2>
        <p>Discover doctor-recommended wellness products, health devices, supplements, baby care and more.</p>
        <div class="store-cta-actions">
            <button type="button" class="btn btn-primary" data-soon>🛒 Start Shopping</button>
            <a href="/find-a-doctor" class="btn btn-ghost">👨‍⚕️ Find a Doctor</a>
            <a href="/find-a-doctor" class="btn btn-ghost">📅 Book Appointment</a>
        </div>
    </div>
</section>

</main>

<!-- "Coming soon" toast (shared) ---------------------------------------- -->
<div id="storeToast" class="store-toast" role="status" aria-live="polite">Coming soon — the store is launching shortly. 🚀</div>
<script>
(function () {
    var toast = document.getElementById('storeToast');
    var timer = null;
    function showToast() {
        if (!toast) return;
        toast.classList.add('is-on');
        clearTimeout(timer);
        timer = setTimeout(function () { toast.classList.remove('is-on'); }, 2200);
    }
    // Any element marked data-soon (or inside one) shows the toast and
    // never navigates — this whole page is a non-functional preview.
    document.addEventListener('click', function (ev) {
        var el = ev.target.closest('[data-soon]');
        if (!el) return;
        ev.preventDefault();
        showToast();
    });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
