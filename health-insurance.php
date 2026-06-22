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
    ['👤', 'For just you', 'Covers one person — you. Great when you’re single or want your own separate cover.',
        ['Pick cover from ₹5 lakh upward', 'Cashless at partner hospitals', 'Bonus cover for claim-free years', 'Same-day procedures included'], 'g-teal', 'Popular'],
    ['👨‍👩‍👧', 'For the whole family', 'One plan covering you, your spouse, kids — and parents too. Usually the best value.',
        ['One shared cover for everyone', 'Add spouse, kids & parents', 'Pregnancy cover as an add-on', 'Free yearly health check-up'], 'g-purple', 'Best value'],
    ['👴', 'For parents & seniors', 'Built for elders aged 60+, with the conditions that come with age in mind.',
        ['No upper age limit to join', 'Existing illnesses covered (after a wait)', 'Treatment at home if needed', 'A team to help at claim time'], 'g-amber', ''],
    ['🛡️', 'For a major illness', 'Pays a one-time lump sum if you’re diagnosed with a serious illness — use it any way you need.',
        ['Covers cancer, heart, kidney & more', 'Lump sum paid on diagnosis', 'Replaces lost income', 'Saves tax under 80D'], 'g-red', ''],
    ['🏥', 'Top up your cover', 'Already have a small or office plan? Add more cover for a much lower price.',
        ['Extra cover, low premium', 'Kicks in above your existing cover', 'Can cover the family too', 'Perfect alongside office insurance'], 'g-blue', ''],
    ['🦷', 'Everyday care', 'For the small stuff too — doctor visits, tests and medicines, not just hospital stays.',
        ['Doctor consultations', 'Lab tests', 'Pharmacy benefits', 'Yearly wellness check'], 'g-teal', 'New'],
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

// Section — "Why you need it" reasons, in plain words. [emoji, title, text]
$reasons = [
    ['🏥', 'Hospital bills are huge', 'A single hospital stay can cost a few lakh rupees. One serious illness can wipe out years of savings. Insurance pays the big bills so your money stays yours.'],
    ['📈', 'Treatment gets costlier every year', 'The cost of medical care in India is rising fast — faster than normal prices. What costs ₹2 lakh today may cost much more in a few years. A policy locks in your protection now.'],
    ['❤️', 'Illness can hit anyone, anytime', 'Diabetes, heart trouble, even an accident — these don’t wait for a “good time”. Cover keeps you ready instead of scrambling for money during an emergency.'],
    ['💰', 'You save tax too', 'The premium you pay can reduce your income tax under Section 80D. So you protect your family and lower your tax bill in the same step.'],
];

// Section — What's covered. [emoji, label]
$covered = [
    ['🛏️', 'Hospital stay (room, ICU, surgery)'],
    ['🔬', 'Tests & doctor visits before and after'],
    ['💉', 'Same-day procedures (no overnight stay)'],
    ['🚑', 'Ambulance charges'],
    ['🦠', 'Illnesses like dengue, malaria, typhoid'],
    ['🌿', 'Ayurveda / Homeopathy (AYUSH) on many plans'],
    ['🤰', 'Pregnancy & delivery (as an add-on)'],
    ['🩺', 'A free yearly health check-up'],
];

// Section — What's usually NOT covered. [emoji, label]
$notCovered = [
    ['💄', 'Cosmetic / beauty surgery'],
    ['⏳', 'Old illnesses during the early waiting period'],
    ['🚬', 'Problems from alcohol or drug abuse'],
    ['🦷', 'Routine dental & spectacles (unless added)'],
];

// Section — Jargon in plain English. [term, plain explanation]
$glossary = [
    ['Sum insured', 'The most the company will pay in a year. A ₹5 lakh cover means they’ll pay up to ₹5,00,000 of your bills.'],
    ['Premium', 'The amount you pay (yearly or monthly) to keep the policy active. Think of it like a subscription for protection.'],
    ['Cashless', 'At a partner hospital, the insurer pays the bill directly. You don’t arrange the money first — you just get treated.'],
    ['Reimbursement', 'At any other hospital, you pay first, then send the bills to the insurer and they pay you back.'],
    ['Waiting period', 'A short starting phase where some illnesses (especially ones you already had) aren’t covered yet. After it ends, they’re covered.'],
    ['Pre-existing illness', 'A health problem you already have before buying — like diabetes or BP. It’s usually covered after the waiting period.'],
    ['Co-payment', 'A small share of the bill you agree to pay yourself. A higher share lowers your premium but costs you more at claim time.'],
    ['No-claim bonus', 'A reward for a year with no claim — your cover amount goes up for free, or your premium drops.'],
    ['Network hospital', 'A hospital tied up with the insurer where cashless treatment works smoothly.'],
    ['Room rent limit', 'A cap on how costly a hospital room the plan pays for. No cap = pick any room without extra cost.'],
];

// Section — "Which plan suits you?" life-stage guide.
// [emoji, who, cover suggestion, [what to look for]]
$lifeGuide = [
    ['🧑', 'Young & single (20s–30s)', '₹10–15 lakh',
        ['Big cover, low premium', 'No room-rent cap', 'Free yearly check-up', 'Lock in a low price early']],
    ['👨‍👩‍👧', 'Just married / young family', '₹15–20 lakh',
        ['One family floater plan', 'Maternity & newborn add-on', 'Child vaccination benefits', 'No-claim bonus']],
    ['🧑‍🦱', 'Mid-career (40s–50s)', '₹20–25 lakh',
        ['Higher cover for lifestyle illness', 'Critical illness add-on', 'Low or no co-payment', 'Fast claim settlement']],
    ['👴', 'Parents & seniors (60+)', '₹15–20 lakh',
        ['Covers existing conditions sooner', 'Short waiting period', 'Lots of nearby hospitals', 'Home & AYUSH treatment']],
];

// Section — Add-ons (riders) you can attach. [emoji, name, plain text]
$addons = [
    ['🤰', 'Maternity cover', 'Pays for pregnancy, delivery and often newborn care. Buy it early — it has a waiting period.'],
    ['🛏️', 'Room-rent waiver', 'Removes the cap on room cost, so you can pick a better room without paying extra.'],
    ['🛡️', 'Critical illness', 'A one-time lump sum if you’re diagnosed with cancer, heart attack, stroke and the like — use it for anything.'],
    ['💵', 'Daily hospital cash', 'A fixed amount for each day in hospital, to cover food, travel and other small costs.'],
    ['⏩', 'Faster pre-existing cover', 'Shortens the wait for old conditions like diabetes or BP to be covered.'],
];

// Section — What changes your premium. [emoji, factor, plain text]
$priceFactors = [
    ['🎂', 'Your age', 'Younger people pay less. The earlier you start, the cheaper it stays.'],
    ['❤️', 'Your health history', 'Existing conditions like diabetes or BP can raise the price a little.'],
    ['🛡️', 'How much cover you pick', 'A ₹10 lakh cover costs more than ₹5 lakh — but protects you far better.'],
    ['👨‍👩‍👧', 'How many people', 'More members on one family plan means a higher premium.'],
    ['📍', 'Your city', 'Treatment in big metros costs more, so premiums there are higher too.'],
    ['🚬', 'Lifestyle', 'Smoking or heavy drinking raises health risk, and so the premium.'],
];

// Section — Documents you'll need. [emoji, label]
$docs = [
    ['🆔', 'ID proof (Aadhaar / PAN / Passport)'],
    ['🏠', 'Address proof'],
    ['🎂', 'Age proof'],
    ['📷', 'A passport-size photo'],
    ['🩺', 'Basic health check-up (sometimes, for older applicants)'],
];

// Section — FAQ, rewritten in everyday language. [q, a]
$faqs = [
    ['In simple words, what is health insurance?', 'You pay a small yearly amount, and in return the company pays your big hospital bills if you fall sick or have an accident. It protects your savings from sudden, large medical costs.'],
    ['What does “cashless” mean?', 'At a partner (network) hospital, the insurer settles the bill directly with the hospital — you don’t pay upfront. You only pay for things the plan doesn’t cover. We’ll help you find the nearest network hospital.'],
    ['Can I cover my parents on the same plan?', 'Yes. A family floater can include parents, or a dedicated senior-citizen plan may suit them better. An advisor will help you pick the right one — no pressure.'],
    ['I already have diabetes / BP. Can I still get cover?', 'Yes. By rule, you can’t be refused for an existing illness. It’s usually covered after a short waiting period, and an add-on can make that wait even shorter.'],
    ['What is a “waiting period”?', 'It’s a short starting phase when certain illnesses — especially ones you already had — aren’t covered yet. Once it’s over, they’re covered like everything else.'],
    ['How much cover should I take?', 'A rough guide: ₹10–15 lakh if you’re young and single, ₹15–20 lakh for a young family, and ₹20–25 lakh as you get older. Big-city treatment costs more, so lean higher there.'],
    ['Do I really save tax?', 'Yes. The premium you pay reduces your taxable income under Section 80D — up to ₹25,000 for yourself and family, plus up to ₹50,000 more for senior-citizen parents.'],
    ['Already have insurance from my employer — do I need my own?', 'It’s wise to. Office cover ends the day you leave the job, is often small, and doesn’t cover your parents. A personal plan stays with you for life. A top-up plan is a cheap way to add more cover on top.'],
    ['How fast are claims paid?', 'Cashless approvals at network hospitals often come within a few hours. If you pay first and claim later (reimbursement), it depends on how quickly you submit the bills and reports.'],
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

<!-- Section — What is health insurance (plain definition) --------------- -->
<section class="store-section wrap">
    <div class="ins-define store-card">
        <span class="store-tile g-teal">🛡️</span>
        <div>
            <h2>What is health insurance, really?</h2>
            <p>It’s simple: you pay a small amount every year, and in return the insurance company pays your big hospital bills if you fall sick or have an accident. Think of it as a safety net for your savings — so one illness doesn’t cost you years of hard-earned money. You get treated; they handle the bill.</p>
        </div>
    </div>
</section>

<!-- Section — Why you need it ------------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Why a health plan matters</h2>
            <p>Four down-to-earth reasons people get covered.</p>
        </header>
        <div class="store-grid ins-grid-reason">
            <?php foreach ($reasons as [$ico, $title, $text]): ?>
            <article class="ins-reason store-card">
                <span class="store-tile g-teal"><?= $ico ?></span>
                <h3><?= e($title) ?></h3>
                <p><?= e($text) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
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

<!-- Section — Which plan suits you (life-stage guide) ------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Which plan suits you?</h2>
            <p>A quick guide by life stage — with a rough cover amount to aim for.</p>
        </header>
        <div class="store-grid ins-grid-guide">
            <?php foreach ($lifeGuide as [$ico, $who, $cover, $points]): ?>
            <article class="ins-guide store-card">
                <div class="ins-guide-top">
                    <span class="store-tile g-purple"><?= $ico ?></span>
                    <div>
                        <h3><?= e($who) ?></h3>
                        <span class="ins-guide-cover">Aim for <strong><?= e($cover) ?></strong> cover</span>
                    </div>
                </div>
                <ul class="ins-plan-points">
                    <?php foreach ($points as $p): ?>
                    <li><?= e($p) ?></li>
                    <?php endforeach; ?>
                </ul>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section — What's covered / not covered ------------------------------ -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>What’s covered — and what isn’t</h2>
        <p>Exact terms vary by plan, but here’s the general picture.</p>
    </header>
    <div class="ins-cover-split">
        <div class="ins-cover-col ins-cover-yes store-card">
            <h3>✅ Usually covered</h3>
            <ul>
                <?php foreach ($covered as [$ico, $label]): ?>
                <li><span><?= $ico ?></span><?= e($label) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="ins-cover-col ins-cover-no store-card">
            <h3>🚫 Usually not covered</h3>
            <ul>
                <?php foreach ($notCovered as [$ico, $label]): ?>
                <li><span><?= $ico ?></span><?= e($label) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<!-- Section — Jargon in plain English ----------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>Insurance words, in plain English</h2>
            <p>The terms you’ll hear — explained the way a friend would.</p>
        </header>
        <div class="store-grid ins-grid-gloss">
            <?php foreach ($glossary as [$term, $text]): ?>
            <article class="ins-gloss store-card">
                <h3><?= e($term) ?></h3>
                <p><?= e($text) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Section — Add-ons / riders ------------------------------------------ -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>Add-ons you can attach</h2>
        <p>Optional extras that fill gaps in a basic plan — pay a little more, get more cover.</p>
    </header>
    <div class="store-grid ins-grid-reason">
        <?php foreach ($addons as [$ico, $name, $text]): ?>
        <article class="ins-reason store-card">
            <span class="store-tile g-blue"><?= $ico ?></span>
            <h3><?= e($name) ?></h3>
            <p><?= e($text) ?></p>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section — How a claim works ----------------------------------------- -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <header class="store-head">
            <h2>How a claim works</h2>
            <p>There are just two ways the bill gets paid.</p>
        </header>
        <div class="ins-cover-split">
            <div class="ins-claim store-card">
                <span class="store-tile g-teal">💳</span>
                <h3>Cashless — the easy way</h3>
                <p>Go to a partner (network) hospital, show your policy, and get treated. The insurer pays the hospital directly. You only pay for anything the plan doesn’t cover. Best for planned and emergency hospital stays.</p>
            </div>
            <div class="ins-claim store-card">
                <span class="store-tile g-amber">🧾</span>
                <h3>Reimbursement — pay &amp; claim back</h3>
                <p>At any other hospital, you pay the bill first, then send the bills, reports and prescriptions to the insurer. They check and transfer the money back to your bank account. Keep all documents safe.</p>
            </div>
        </div>
    </div>
</section>

<!-- Section — What changes your premium --------------------------------- -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>What decides the price?</h2>
        <p>Six things that move your premium up or down.</p>
    </header>
    <div class="store-grid ins-grid-gloss">
        <?php foreach ($priceFactors as [$ico, $factor, $text]): ?>
        <article class="ins-gloss store-card">
            <h3><span class="ins-gloss-ico"><?= $ico ?></span> <?= e($factor) ?></h3>
            <p><?= e($text) ?></p>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Section — Tax savings (highlight band) ------------------------------ -->
<section class="store-section store-section-alt">
    <div class="wrap">
        <div class="ins-tax store-card">
            <span class="store-tile g-teal">💰</span>
            <div>
                <h2>You save tax while you stay protected</h2>
                <p>The premium you pay lowers your taxable income under <strong>Section 80D</strong>. You can claim up to <strong>₹25,000</strong> for yourself and your family, plus up to <strong>₹50,000</strong> more if you’re also insuring senior-citizen parents — up to <strong>₹1,00,000</strong> in a year. So protecting your family and cutting your tax bill happen in the same step.</p>
            </div>
        </div>
    </div>
</section>

<!-- Section — Documents needed ------------------------------------------ -->
<section class="store-section wrap">
    <header class="store-head">
        <h2>What you’ll need to sign up</h2>
        <p>Buying is quick — just keep these handy.</p>
    </header>
    <div class="store-grid store-grid-spec">
        <?php foreach ($docs as [$ico, $label]): ?>
        <div class="store-spec store-card">
            <span class="store-spec-ico"><?= $ico ?></span>
            <span><?= e($label) ?></span>
        </div>
        <?php endforeach; ?>
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
