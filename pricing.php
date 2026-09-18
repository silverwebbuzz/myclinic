<?php
// =====================================================================
// pricing.php — standalone Software Pricing page, served at /pricing.
//
// One plan only: Standard at ₹999/month + GST, billed monthly. No free
// trial — every "Sign up" goes to the app's /register flow
// (phone → code → details → checkout → payment).
//
// The price here MUST match BillingGatewayService::MONTHLY_PRICE_INR in the
// app — that is what Razorpay actually charges.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$activePage = 'pricing';
$pageTitle = 'Pricing — eClinicPro Clinic Management Software';
$metaDesc  = 'Simple, transparent pricing for eClinicPro clinic management software. One plan with everything included — ₹999/month + GST, billed monthly. Unlimited patients and staff.';

$gstPct = 18;
$perMonth = 999;
$gst = round($perMonth * $gstPct / 100, 2);
$total = $perMonth + $gst;
$signupUrl = ecp_portal_url('/register');

$inr = static fn (float $n, int $dec = 0): string => '₹' . number_format($n, $dec);

$included = [
    ['🗂️', 'Patient records', 'Visits, history, allergies, attachments — searchable in a second.'],
    ['💊', 'E-prescriptions', 'Print or send on WhatsApp. Drug database with smart dosing.'],
    ['📅', 'Appointments & queue', 'Online booking, walk-ins and a live token queue.'],
    ['🧾', 'Billing & GST invoices', 'Invoices, receipts, payments and Tally-ready exports.'],
    ['🩺', 'Vitals & follow-ups', 'Diagnosis, vitals charts and automatic follow-up tracking.'],
    ['🧬', '50+ specialty forms', 'Dental charts, growth charts, physio plans and more.'],
    ['🎥', 'Teleconsultation', 'Video consults built in — no extra app for patients.'],
    ['🌐', 'Public doctor profile', 'Get found and booked on eclinicpro.com.'],
    ['📊', 'Reports & analytics', 'Daily collections, visits and patient trends.'],
    ['👥', 'Unlimited staff & patients', 'Add doctors, nurses and receptionists at no extra cost.'],
    ['🔒', 'Secure & backed up', 'Encrypted data, role-based access and full audit log.'],
    ['🤝', 'Free onboarding help', 'We help you set up and import your existing data.'],
];

$addons = [
    ['/assets/img/icon/whatsapp-summaries.png', 'Patient Connect', 'WhatsApp automation: appointment reminders, prescription delivery and follow-up nudges. Cuts no-shows in half.', '+₹499 / month'],
    ['/assets/img/icon/clinic-network.png', 'Clinic Network', 'Add an extra branch under one account. Shared patient records, separate queues per branch.', '+₹999 / month per branch'],
];

$steps = [
    ['Verify phone', 'Enter your WhatsApp number.'],
    ['Enter code', 'Confirm the 6-digit code we send.'],
    ['Your details', 'Your name, clinic name and password.'],
    ['Checkout', 'Review your plan and the monthly total.'],
    ['Payment', 'Pay securely with UPI, card or net banking.'],
];

$faqs = [
    ['Is there a free trial?', 'No — you sign up and pay for your first month. If you would like to see the software first, book a free 15-minute demo and we will walk you through everything.'],
    ['What does the price include?', 'Everything listed on this page: every module, unlimited patients, unlimited staff users, updates and support. The only extras are the optional add-ons.'],
    ['How is GST charged?', '18% GST is added at checkout and shown clearly before you pay. You get a GST invoice by email right after payment.'],
    ['How do I pay?', 'Payment is handled by Razorpay. You can pay with UPI, debit or credit card, net banking or wallets.'],
    ['When can I start using it?', 'Immediately. As soon as the payment is confirmed your account is activated and you can log in and set up your clinic.'],
    ['How does monthly billing work?', 'You pay ₹999 + GST for one month at a time. We remind you before the month ends and you renew from Settings → Subscription in a couple of clicks. No long contract, and your data is never deleted.'],
    ['Can I add more doctors or staff later?', 'Yes. Staff users are unlimited on every plan. Only extra clinic branches are charged, through the Clinic Network add-on.'],
];

require __DIR__ . '/partials/header.php';
?>

<style>
    .pr-hero {
        position: relative;
        overflow: hidden;
        padding: 120px 0 72px;
        text-align: center;
        background:
            radial-gradient(1000px 420px at 50% -10%, rgba(45, 192, 138, .22), transparent 70%),
            linear-gradient(180deg, #f2fbf7 0%, #ffffff 100%);
    }
    .pr-hero h1 {
        font-size: clamp(32px, 5vw, 56px);
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1.08;
        color: #0d1f12;
        margin: 14px auto 18px;
        max-width: 820px;
    }
    .pr-hero h1 em { font-style: normal; color: #0F9B6E; }
    .pr-hero .pr-lede {
        font-size: 17px;
        line-height: 1.7;
        color: #4e6e56;
        max-width: 640px;
        margin: 0 auto;
    }
    .pr-trust {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px 22px;
        margin-top: 26px;
        font-size: 13.5px;
        color: #3a5742;
    }
    .pr-trust span::before { content: '✓ '; color: #0F9B6E; font-weight: 700; }

    /* ── Plan cards ── */
    .pr-plans { padding: 0 0 80px; margin-top: -24px; position: relative; }
    .pr-plan-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 28px;
        max-width: 520px;
        margin: 0 auto;
    }
    .pr-plan {
        position: relative;
        background: #fff;
        border: 1.5px solid #e3efe8;
        border-radius: 24px;
        padding: 34px 32px 30px;
        box-shadow: 0 6px 30px rgba(3, 56, 42, .06);
        display: flex;
        flex-direction: column;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .pr-plan:hover { transform: translateY(-4px); box-shadow: 0 16px 44px rgba(3, 56, 42, .12); }
    .pr-plan.is-featured {
        border: 2px solid #0F9B6E;
        box-shadow: 0 18px 50px rgba(15, 155, 110, .18);
    }
    .pr-plan-ribbon {
        position: absolute;
        top: -14px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(90deg, #076B4C, #0F9B6E);
        color: #fff;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 999px;
        white-space: nowrap;
        box-shadow: 0 6px 16px rgba(15, 155, 110, .3);
    }
    .pr-plan-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .pr-plan-term { font-size: 20px; font-weight: 700; color: #0d1f12; }
    .pr-plan-off {
        background: #e8f6ef;
        color: #0B7F5A;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 999px;
    }
    .pr-plan-note { font-size: 14px; color: #6b8a72; margin: 6px 0 22px; }
    .pr-plan-price { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
    .pr-plan-amt { font-size: 56px; font-weight: 700; letter-spacing: -0.04em; color: #0d1f12; line-height: 1; }
    .pr-plan-amt small { font-size: 26px; font-weight: 500; vertical-align: 18px; margin-right: 2px; }
    .pr-plan-per { font-size: 16px; color: #0F9B6E; font-weight: 500; }
    .pr-plan-bill {
        margin: 18px 0 22px;
        padding: 14px 16px;
        background: #f4faf6;
        border-radius: 14px;
        font-size: 13.5px;
        color: #3a5742;
        display: grid;
        gap: 6px;
    }
    .pr-plan-bill div { display: flex; justify-content: space-between; gap: 12px; }
    .pr-plan-bill .pr-total { border-top: 1px dashed #cfe5d8; padding-top: 8px; margin-top: 2px; font-weight: 700; color: #0d1f12; }
    .pr-plan-list { list-style: none; padding: 0; margin: 0 0 26px; display: grid; gap: 9px; flex: 1; }
    .pr-plan-list li { display: flex; gap: 10px; font-size: 14px; color: #3a5742; }
    .pr-plan-list li::before { content: '✓'; color: #0F9B6E; font-weight: 800; flex-shrink: 0; }
    .pr-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 15px 24px;
        border-radius: 999px;
        font-size: 15.5px;
        font-weight: 700;
        text-decoration: none;
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
        border: 2px solid #0F9B6E;
    }
    .pr-btn-solid { background: #0F9B6E; color: #fff; }
    .pr-btn-solid:hover { background: #0B7F5A; border-color: #0B7F5A; transform: translateY(-2px); box-shadow: 0 10px 24px rgba(15, 155, 110, .3); }
    .pr-btn-line { background: #fff; color: #0B7F5A; }
    .pr-btn-line:hover { background: #f0faf5; transform: translateY(-2px); }
    .pr-plan-fine { font-size: 12px; color: #6b8a72; text-align: center; margin-top: 12px; }
    .pr-secure {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 8px 18px;
        margin-top: 30px;
        font-size: 13px;
        color: #6b8a72;
    }
    .pr-secure b { color: #3a5742; }

    /* ── Section shell ── */
    .pr-section { padding: 84px 0; }
    .pr-section.alt { background: #f6faf8; border-top: 1px solid #e6f0ea; border-bottom: 1px solid #e6f0ea; }
    .pr-head { text-align: center; max-width: 660px; margin: 0 auto 48px; }
    .pr-head h2 { font-size: clamp(26px, 3.6vw, 40px); font-weight: 800; letter-spacing: -0.025em; color: #0d1f12; margin: 12px 0 12px; }
    .pr-head p { font-size: 16px; line-height: 1.7; color: #4e6e56; margin: 0; }

    /* ── Everything included ── */
    .pr-inc-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        max-width: 1120px;
        margin: 0 auto;
    }
    .pr-inc {
        background: #fff;
        border: 1.5px solid #e6f0ea;
        border-radius: 18px;
        padding: 22px 20px;
        transition: border-color .2s ease, transform .2s ease;
    }
    .pr-inc:hover { border-color: #a8d8be; transform: translateY(-3px); }
    .pr-inc-ic {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #e8f6ef;
        display: grid;
        place-items: center;
        font-size: 22px;
        margin-bottom: 14px;
    }
    .pr-inc b { display: block; font-size: 15px; color: #0d1f12; margin-bottom: 6px; }
    .pr-inc p { font-size: 13.5px; line-height: 1.6; color: #6b8a72; margin: 0; }

    /* ── Add-ons ── */
    .pr-addons { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; max-width: 900px; margin: 0 auto; }
    .pr-addon { display: flex; gap: 16px; background: #fff; border: 1.5px solid #e6f0ea; border-radius: 18px; padding: 24px; }
    .pr-addon-ic { width: 52px; height: 52px; border-radius: 14px; background: #e8f6ef; display: grid; place-items: center; flex-shrink: 0; }
    .pr-addon-ic img { width: 30px; height: auto; filter: brightness(0) saturate(100%) invert(28%) sepia(63%) saturate(1045%) hue-rotate(134deg) brightness(92%) contrast(93%); }
    .pr-addon b { display: block; font-size: 16px; color: #0d1f12; margin-bottom: 6px; }
    .pr-addon p { font-size: 13.5px; line-height: 1.6; color: #6b8a72; margin: 0 0 10px; }
    .pr-addon span { font-size: 14px; font-weight: 700; color: #0B7F5A; }

    /* ── How signup works ── */
    .pr-steps { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; max-width: 1080px; margin: 0 auto; counter-reset: prstep; }
    .pr-step { position: relative; text-align: center; padding: 0 6px; }
    .pr-step::before {
        counter-increment: prstep;
        content: counter(prstep);
        display: grid;
        place-items: center;
        width: 46px;
        height: 46px;
        margin: 0 auto 14px;
        border-radius: 50%;
        background: #0F9B6E;
        color: #fff;
        font-weight: 700;
        font-size: 17px;
        position: relative;
        z-index: 1;
        box-shadow: 0 0 0 6px #e8f6ef;
    }
    .pr-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 23px;
        left: calc(50% + 30px);
        right: calc(-50% + 30px);
        height: 2px;
        background: repeating-linear-gradient(90deg, #a8d8be 0 6px, transparent 6px 12px);
    }
    .pr-step b { display: block; font-size: 15px; color: #0d1f12; margin-bottom: 4px; }
    .pr-step p { font-size: 13px; color: #6b8a72; line-height: 1.55; margin: 0; }
    .pr-steps-done { text-align: center; margin-top: 30px; font-size: 14.5px; color: #3a5742; }
    .pr-steps-done b { color: #0B7F5A; }

    /* ── FAQ ── */
    .pr-faq { max-width: 780px; margin: 0 auto; display: grid; gap: 12px; }
    .pr-faq details { background: #fff; border: 1.5px solid #e6f0ea; border-radius: 14px; padding: 0 20px; transition: border-color .2s ease; }
    .pr-faq details[open] { border-color: #a8d8be; }
    .pr-faq summary { cursor: pointer; list-style: none; padding: 18px 0; font-weight: 600; font-size: 15.5px; color: #0d1f12; display: flex; justify-content: space-between; gap: 16px; }
    .pr-faq summary::-webkit-details-marker { display: none; }
    .pr-faq summary::after { content: '+'; color: #0F9B6E; font-size: 22px; line-height: 1; transition: transform .2s ease; }
    .pr-faq details[open] summary::after { transform: rotate(45deg); }
    .pr-faq details p { margin: 0 0 18px; font-size: 14.5px; line-height: 1.7; color: #4e6e56; }

    /* ── Final CTA ── */
    .pr-cta {
        max-width: 1000px;
        margin: 0 auto;
        border-radius: 28px;
        padding: 52px 40px;
        text-align: center;
        color: #fff;
        background: linear-gradient(120deg, #03382A, #076B4C 55%, #0F9B6E);
        box-shadow: 0 24px 60px rgba(3, 56, 42, .25);
    }
    .pr-cta h2 { font-size: clamp(26px, 3.6vw, 38px); font-weight: 800; letter-spacing: -0.02em; margin: 0 0 12px; }
    .pr-cta p { font-size: 16px; opacity: .88; margin: 0 auto 28px; max-width: 560px; line-height: 1.7; }
    .pr-cta-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; }
    .pr-cta .pr-btn { width: auto; min-width: 200px; }
    .pr-cta .pr-btn-solid { background: #fff; color: #03382A; border-color: #fff; }
    .pr-cta .pr-btn-solid:hover { background: #e8f6ef; border-color: #e8f6ef; box-shadow: none; }
    .pr-cta .pr-btn-line { background: transparent; color: #fff; border-color: rgba(255, 255, 255, .55); }
    .pr-cta .pr-btn-line:hover { background: rgba(255, 255, 255, .1); }

    @media (max-width: 1000px) {
        .pr-inc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pr-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: 28px; }
        .pr-step::after { display: none; }
    }
    @media (max-width: 760px) {
        .pr-hero { padding: 100px 0 56px; }
        .pr-plan-grid, .pr-addons { grid-template-columns: 1fr; }
        .pr-plan { padding: 30px 22px 24px; }
        .pr-section { padding: 64px 0; }
        .pr-cta { padding: 40px 22px; border-radius: 22px; }
    }
    @media (max-width: 480px) {
        .pr-inc-grid { grid-template-columns: 1fr; }
        .pr-steps { grid-template-columns: 1fr; }
    }
</style>

<!-- ═══════ HERO ═══════ -->
<section class="pr-hero">
    <div class="wrap reveal">
        <p class="hp-eyebrow">Pricing</p>
        <h1>One plan. <em>Everything included.</em></h1>
        <p class="pr-lede">
            No tiers, no per-seat games, no surprise upsells. One monthly price gets you every
            feature, every specialty and unlimited staff — no long contract.
        </p>
        <div class="pr-trust">
            <span>Unlimited patients</span>
            <span>Unlimited staff users</span>
            <span>GST invoice included</span>
            <span>Start using right after payment</span>
        </div>
    </div>
</section>

<!-- ═══════ PLAN ═══════ -->
<section class="pr-plans" id="plans">
    <div class="wrap">
        <div class="pr-plan-grid reveal">
            <article class="pr-plan is-featured">
                <span class="pr-plan-ribbon">★ Everything included</span>
                <div class="pr-plan-top">
                    <span class="pr-plan-term">Standard plan</span>
                    <span class="pr-plan-off">Billed monthly</span>
                </div>
                <p class="pr-plan-note">The complete clinic system. Pay month to month — no long contract.</p>

                <div class="pr-plan-price">
                    <span class="pr-plan-amt"><small>₹</small><?= number_format($perMonth) ?></span>
                    <span class="pr-plan-per">/month + GST</span>
                </div>

                <div class="pr-plan-bill">
                    <div><span>Plan price (1 month)</span><span><?= $inr($perMonth, 2) ?></span></div>
                    <div><span>GST (<?= (int) $gstPct ?>%)</span><span><?= $inr($gst, 2) ?></span></div>
                    <div class="pr-total"><span>Total per month</span><span><?= $inr($total, 2) ?></span></div>
                </div>

                <ul class="pr-plan-list">
                    <li>Patient Records</li>
                    <li>Appointments &amp; walk-in queue</li>
                    <li>Prescriptions &amp; Pharmacy</li>
                    <li>Billing &amp; invoicing (GST-ready)</li>
                    <li>Vitals, diagnosis, follow-up tracking</li>
                    <li>Daily reports &amp; analytics</li>
                    <li>Unlimited patients, unlimited staff users</li>
                    <li>Public doctor profile on eclinicpro.com</li>
                    <li>1 Instagram Reel post per month</li>
                </ul>

                <a href="<?= e($signupUrl) ?>" class="pr-btn pr-btn-solid">Sign up →</a>
                <p class="pr-plan-fine">Secure payment via Razorpay · UPI, cards, net banking</p>
            </article>
        </div>

        <div class="pr-secure">
            <span>🔒 <b>256-bit SSL</b> secure checkout</span>
            <span>🧾 <b>GST invoice</b> emailed instantly</span>
            <span>⚡ <b>Instant activation</b> after payment</span>
        </div>
    </div>
</section>

<!-- ═══════ EVERYTHING INCLUDED ═══════ -->
<section class="pr-section alt">
    <div class="wrap">
        <div class="pr-head reveal">
            <p class="hp-eyebrow">What you get</p>
            <h2>Everything your clinic needs, in one price</h2>
            <p>No modules to unlock and no feature gates. Every plan gets the full system from day one.</p>
        </div>
        <div class="pr-inc-grid reveal">
            <?php foreach ($included as [$ic, $name, $desc]): ?>
                <div class="pr-inc">
                    <div class="pr-inc-ic" aria-hidden="true"><?= $ic ?></div>
                    <b><?= e($name) ?></b>
                    <p><?= e($desc) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════ ADD-ONS ═══════ -->
<section class="pr-section alt">
    <div class="wrap">
        <div class="pr-head reveal">
            <p class="hp-eyebrow">Optional add-ons</p>
            <h2>Grow when you are ready</h2>
            <p>Add these any time from inside the app. You never pay for what you don't use.</p>
        </div>
        <div class="pr-addons reveal">
            <?php foreach ($addons as [$img, $name, $desc, $price]): ?>
                <div class="pr-addon">
                    <div class="pr-addon-ic"><img src="<?= e($img) ?>" alt="" width="30" height="30"></div>
                    <div>
                        <b><?= e($name) ?></b>
                        <p><?= e($desc) ?></p>
                        <span><?= e($price) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════ HOW SIGNUP WORKS ═══════ -->
<section class="pr-section">
    <div class="wrap">
        <div class="pr-head reveal">
            <p class="hp-eyebrow">How it works</p>
            <h2>Up and running in 5 minutes</h2>
            <p>Five quick steps from sign up to your first patient.</p>
        </div>
        <div class="pr-steps reveal">
            <?php foreach ($steps as [$name, $desc]): ?>
                <div class="pr-step">
                    <b><?= e($name) ?></b>
                    <p><?= e($desc) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="pr-steps-done">✅ Payment confirmed — <b>log in and start seeing patients.</b></p>
    </div>
</section>

<!-- ═══════ FAQ ═══════ -->
<section class="pr-section alt">
    <div class="wrap">
        <div class="pr-head reveal">
            <p class="hp-eyebrow">FAQ</p>
            <h2>Pricing questions, answered</h2>
        </div>
        <div class="pr-faq reveal">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
                <details<?= $i === 0 ? ' open' : '' ?>>
                    <summary><?= e($q) ?></summary>
                    <p><?= e($a) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════ FINAL CTA ═══════ -->
<section class="pr-section">
    <div class="wrap">
        <div class="pr-cta reveal">
            <h2>Ready to run your clinic beautifully?</h2>
            <p>Sign up in a few minutes, pay securely, and start using eClinicPro right away.</p>
            <div class="pr-cta-actions">
                <a href="<?= e($signupUrl) ?>" class="pr-btn pr-btn-solid">Sign up now →</a>
                <a href="/book-a-demo" data-open-demo-modal class="pr-btn pr-btn-line">Book a free demo</a>
            </div>
        </div>
    </div>
</section>

<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn ($f) => [
        '@type' => 'Question',
        'name' => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faqs),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<?php
$hideFinalCta = true;
$demoDefaultSpecialty = 'General practice';
$demoSpecKey = 'pricing';
require __DIR__ . '/partials/demo-modal.php';
require __DIR__ . '/partials/footer.php';
