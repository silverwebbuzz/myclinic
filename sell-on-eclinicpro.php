<?php
// =====================================================================
// sell-on-eclinicpro.php — seller recruitment landing page (/sell-on-eclinicpro).
//
// Sells becoming a seller on eClinicPro Store and drives sign-ups to the
// seller portal (/vendor/register on app.eclinicpro.com). Always public,
// even before the store goes live, so sellers can onboard early.
//
// Numbers (payout minimum, return window, accept time) are read from the
// same store_* settings as the seller terms. Commission and delivery
// charges are negotiated per seller / per product and set by admin, so
// this page deliberately quotes no rate for either.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/store/_lib.php';

$sellMinPayout = store_rupees((int) store_setting('store_min_payout_paise', '10000'));
$sellReturnWindow = store_return_window_label();
$sellPayoutDay = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'][max(1, min(7, (int) store_setting('store_payout_weekday', '2')))];
$sellDisputeDays = (int) store_setting('store_charge_dispute_days', '7');
$sellAcceptHours = (int) store_setting('store_vendor_accept_sla_hours', '48');
$sellNeedsGstin = store_setting('store_require_gstin', '1') === '1';
$sellStoreLive = store_is_live();
$sellRegisterUrl = ecp_portal_url('/vendor/register');
$sellLoginUrl = ecp_portal_url('/vendor/login');

$pageTitle = 'Sell on eClinicPro Store: reach health-conscious customers | eClinicPro';
$metaDesc = 'Sell health, wellness and personal-care products on eClinicPro Store. No upfront fees, '
    . 'commission agreed with you, delivery handled for you, automatic GST invoices and regular payouts to your bank.';
$activePage = 'sell';
$canonicalUrl = ecp_site_url('/sell-on-eclinicpro');

// Why sell with us — every card maps to something the platform really does.
$sellBenefits = [
    ['🩺', 'Customers who already trust us',
        'Your products are shown to patients who use eClinicPro to find doctors and manage their family’s health: people actively looking after their wellbeing.'],
    ['🆓', 'Nothing upfront, pay only when you sell',
        'No sign-up, listing or monthly fee. Our commission is agreed with you for your products and is deducted only from what you actually sell.'],
    ['🚚', 'Delivery handled for you',
        'Book the courier in one click from your order page. We arrange pickup from your address and delivery to the customer through our courier partners. You pay the actual courier charge for each package, based on its size, weight and destination, and on small orders the customer’s delivery fee is taken off it. It is deducted from your payouts, so there is nothing to pay upfront.'],
    ['💳', 'Prepaid orders only',
        'Every customer pays online before you ship. There is no cash-on-delivery, so no unpaid parcels and no chasing money.'],
    ['🧾', 'GST invoices made automatically',
        'A tax invoice in your name and GSTIN is created for every package, and credit notes for refunds. Download your GST register as a CSV for your CA.'],
    ['🏦', 'Paid every ' . $sellPayoutDay,
        'Every ' . $sellPayoutDay . ' we pay all earnings that have cleared the ' . $sellReturnWindow . ' return window to your verified bank account, with a statement listing every order and deduction. Disagree with a charge? Dispute it within ' . $sellDisputeDays . ' days and it is held out of your payout until we reply.'],
    ['🎁', 'Our offers don’t cost you',
        'When eClinicPro runs a coupon, we fund the discount. Your earnings stay exactly the same.'],
    ['📊', 'A simple seller dashboard',
        'Orders, stock, returns, payouts and GST documents in one place: easy on a laptop or phone.'],
];

// What a seller needs to get approved (mirrors VendorService::checklist).
$sellNeeds = [
    'Business details and PAN',
    'Bank account + cancelled cheque (for payouts)',
    'Pickup address (where couriers collect orders) and a return address',
    'Licences your products need, for example FSSAI, drug/AYUSH licence or medical-device registration',
];
if ($sellNeedsGstin) {
    array_unshift($sellNeeds, 'GSTIN and GST certificate');
}

$sellFaqs = [
    ['Is there any fee to register or list products?',
        'No. Registering, listing products and using the seller dashboard are free, and there is no monthly fee. We earn a commission only on items you actually sell, plus 18% GST on that commission.'],
    ['How much is the commission?',
        'It depends on your products. Our team agrees the commission with you, for your whole store or product by product, before your products go live. The agreed rate is applied automatically and shown in the breakdown of every order and payout.'],
    ['Who pays for delivery?',
        'You do, at the actual courier charge. We book pickup and delivery through our courier partners, so you never deal with the courier directly. Because products differ in size and weight, the charge is worked out per package from its packed weight, dimensions and delivery pincode. When the customer pays a delivery fee (on small orders), that fee is taken off your courier charge. You see the charge on the order, and it is deducted from your payouts, so you pay nothing upfront. When you add a product, the seller portal shows an estimate of what you will earn per unit after commission and courier.'],
    ['When do I get paid?',
        'Customers pay eClinicPro online at checkout. Your earnings for a package become available ' . $sellReturnWindow . ' after it is delivered (the return window). Every ' . $sellPayoutDay . ' we pay everything that is available to your verified bank account, once your balance is at least ' . $sellMinPayout . '.'],
    ['Do I need a GSTIN?',
        $sellNeedsGstin
            ? 'Yes. You are the seller of record, so every tax invoice is issued in your name and GSTIN. You will also need to report these sales in your GST returns.'
            : 'You are the seller of record, so tax invoices are issued in your name. Please check with your CA whether you need a GSTIN for your turnover and products.'],
    ['What can I sell?',
        'Genuine, sealed health, wellness, personal-care, baby-care and medical-device products that you are legally allowed to sell, with MRP, batch and expiry printed and at least 3 months of shelf life left. Prescription medicines, banned or restricted products and counterfeit goods are not allowed.'],
    ['How do I handle an order?',
        'Accept it within ' . $sellAcceptHours . ' hours, pack it with the invoice, and book the courier from the order page. Print the label and hand the package to the courier at pickup. Tracking updates automatically for you and the customer.'],
    ['What happens with returns?',
        'There are no change-of-mind returns. A customer can ask for a return only within ' . $sellReturnWindow . ' of delivery, with photos, when an item is damaged, defective, wrong or expired. You review each request from your Returns page. When a refund is made, a credit note is created automatically and your earnings are adjusted for those units only.'],
    ['What if a courier loses or damages a package?',
        'The customer is refunded in full. As long as the package was packed properly, eClinicPro normally pays you what you would have earned and claims from the courier itself.'],
    ['How long does approval take?',
        'Once you finish every step of the checklist in the seller portal and submit, our team reviews your documents. You will see your status in the portal, and you can prepare your products while you wait.'],
];

$faqLd = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn (array $f): array => [
        '@type' => 'Question',
        'name' => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $sellFaqs),
];

ob_start(); ?>
<script type="application/ld+json">
<?= json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT) ?>
</script>
<?php
$extraHead = ob_get_clean();

require __DIR__ . '/partials/header.php';
?>

<style>
    .sl-hero { padding: 104px 0 64px; position: relative; overflow: hidden; background: #f0f4f1; }
    .sl-hero-glow { position: absolute; inset: 0; background: radial-gradient(ellipse at 70% 0%, rgba(26,122,78,.10) 0%, transparent 60%); pointer-events: none; }
    .sl-hero-grid { position: relative; display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 48px; align-items: center; }
    .sl-hero h1 { font-size: clamp(30px, 5vw, 50px); line-height: 1.08; letter-spacing: -0.02em; margin: 10px 0 18px; }
    .sl-hero h1 .accent { color: #1a7a4e; }
    .sl-hero .lede { font-size: clamp(16px, 2.1vw, 19px); color: #4a5a52; line-height: 1.55; margin: 0 0 28px; max-width: 560px; }
    .sl-ctas { display: flex; gap: 12px; flex-wrap: wrap; }
    .sl-note { margin-top: 16px; font-size: 13px; color: #6b7d73; }
    .sl-soon { display: inline-block; margin-bottom: 6px; padding: 5px 12px; border-radius: 999px; background: #fff4dc; color: #8a5a00; font-size: 13px; font-weight: 600; }

    .sl-stats { background: #fff; border: 1px solid #e0e9e3; border-radius: 20px; padding: 26px; box-shadow: 0 20px 50px rgba(16,96,59,.08); }
    .sl-stats h2 { font-size: 15px; margin: 0 0 16px; color: #1c2a22; letter-spacing: 0; }
    .sl-stat { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 13px 0; border-top: 1px solid #eef3f0; }
    .sl-stat:first-of-type { border-top: 0; padding-top: 0; }
    .sl-stat span { color: #55655c; font-size: 14.5px; }
    .sl-stat b { font-size: 20px; color: #10603b; white-space: nowrap; }
    .sl-stats-note { margin: 12px 0 0; padding-top: 12px; border-top: 1px solid #eef3f0; font-size: 13px; line-height: 1.5; color: #6b7d73; }

    .sl-section { padding: 66px 0; }
    .sl-section.alt { background: #f7faf8; }
    .sl-head { text-align: center; max-width: 660px; margin: 0 auto 44px; }
    .sl-head h2 { font-size: clamp(24px, 3.4vw, 34px); letter-spacing: -0.02em; margin: 8px 0 12px; }
    .sl-head p { color: #55655c; font-size: 16px; line-height: 1.55; margin: 0; }

    .sl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .sl-card { background: #fff; border: 1px solid #e6ece8; border-radius: 16px; padding: 24px 22px; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .sl-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(26,122,78,.08); border-color: #cfe0d6; }
    .sl-ic { width: 50px; height: 50px; display: grid; place-items: center; font-size: 25px; border-radius: 13px; background: linear-gradient(135deg, #e8f5ee, #d6ebdf); margin-bottom: 14px; }
    .sl-card h3 { font-size: 17px; margin: 0 0 8px; letter-spacing: -0.01em; }
    .sl-card p { color: #55655c; font-size: 14.5px; line-height: 1.55; margin: 0; }

    .sl-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; counter-reset: step; }
    .sl-step { padding: 24px 20px 20px; background: #fff; border: 1px solid #e6ece8; border-radius: 16px; }
    .sl-step::before { counter-increment: step; content: counter(step); display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; background: #1a7a4e; color: #fff; font-weight: 700; font-size: 16px; margin-bottom: 12px; }
    .sl-step h3 { font-size: 16.5px; margin: 0 0 6px; }
    .sl-step p { color: #55655c; font-size: 14px; line-height: 1.5; margin: 0; }

    .sl-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    .sl-box { background: #fff; border: 1px solid #e6ece8; border-radius: 18px; padding: 28px 26px; }
    .sl-box h3 { font-size: 19px; margin: 0 0 16px; }
    .sl-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 11px; }
    .sl-list li { position: relative; padding-left: 28px; color: #3d4d44; font-size: 15px; line-height: 1.5; }
    .sl-list li::before { content: '✓'; position: absolute; left: 0; top: 0; width: 20px; height: 20px; border-radius: 50%; background: #e3f2ea; color: #1a7a4e; font-size: 12px; font-weight: 700; display: grid; place-items: center; }
    .sl-list.no li::before { content: '✕'; background: #fbe9e7; color: #b3261e; }
    .sl-box-note { margin: 16px 0 0; font-size: 13.5px; color: #6b7d73; line-height: 1.5; }

    .sl-faq { max-width: 760px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
    .sl-faq details { background: #fff; border: 1px solid #e6ece8; border-radius: 14px; overflow: hidden; }
    .sl-faq details[open] { border-color: #cfe0d6; box-shadow: 0 8px 24px rgba(26,122,78,.06); }
    .sl-faq summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 20px; font-size: 16px; font-weight: 600; color: #1c2a22; }
    .sl-faq summary::-webkit-details-marker { display: none; }
    .sl-faq summary:hover { color: #1a7a4e; }
    .sl-chev { flex-shrink: 0; color: #6b7d73; transition: transform .2s ease; }
    .sl-faq details[open] .sl-chev { transform: rotate(180deg); color: #1a7a4e; }
    .sl-faq p { margin: 0; padding: 0 20px 18px; color: #55655c; font-size: 15px; line-height: 1.6; }

    .sl-final { text-align: center; background: linear-gradient(135deg, #10603b, #1a7a4e); color: #fff; border-radius: 22px; padding: 56px 28px; }
    .sl-final h2 { font-size: clamp(24px, 3.4vw, 34px); margin: 0 0 12px; letter-spacing: -0.02em; color: #fff; }
    .sl-final p { color: rgba(255,255,255,.9); font-size: 17px; margin: 0 auto 26px; max-width: 540px; line-height: 1.5; }
    .sl-final .btn-primary { background: #fff; color: #10603b; }
    .sl-final .btn-primary:hover { background: #f0f4f1; }
    .sl-final .sl-final-login { display: inline-block; margin-top: 16px; color: rgba(255,255,255,.9); font-size: 14px; }

    @media (max-width: 860px) {
        .sl-hero-grid { grid-template-columns: minmax(0, 1fr); gap: 32px; }
    }
    @media (max-width: 600px) {
        .sl-hero { padding: 88px 0 48px; }
        .sl-section { padding: 48px 0; }
        .sl-ctas .btn { flex: 1 1 100%; justify-content: center; text-align: center; }
        .sl-stats, .sl-box { padding: 22px 18px; }
        .sl-final { padding: 40px 20px; border-radius: 18px; }
        .sl-faq summary { font-size: 15px; padding: 16px; }
        .sl-faq p { padding: 0 16px 16px; }
    }
</style>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="sl-hero">
    <div class="sl-hero-glow"></div>
    <div class="wrap sl-hero-grid">
        <div class="reveal">
            <?php if (!$sellStoreLive): ?><span class="sl-soon">Store opening soon · register early</span><br><?php endif; ?>
            <span class="hp-eyebrow">For brands, manufacturers &amp; distributors</span>
            <h1>Sell your health products to <span class="accent">customers who care about their health</span></h1>
            <p class="lede">
                List on eClinicPro Store for free. We bring the customers, collect the payment, arrange delivery and
                create your GST invoices. You pack the order and get paid to your bank.
            </p>
            <div class="sl-ctas">
                <a href="<?= e($sellRegisterUrl) ?>" class="btn btn-primary btn-lg">Register as a seller →</a>
                <a href="#how" class="btn btn-ghost-dark btn-lg">See how it works</a>
            </div>
            <p class="sl-note">Free to join · No listing fee · Takes about 10 minutes</p>
        </div>
        <aside class="sl-stats reveal" aria-label="Key numbers">
            <h2>Simple, transparent terms</h2>
            <div class="sl-stat"><span>Sign-up, listing &amp; monthly fee</span><b>₹0</b></div>
            <div class="sl-stat"><span>Paid upfront</span><b>Nothing</b></div>
            <div class="sl-stat"><span>Commission</span><b>Agreed with you</b></div>
            <div class="sl-stat"><span>Delivery</span><b>Actual courier cost</b></div>
            <div class="sl-stat"><span>Payment collection</span><b>100% prepaid</b></div>
            <div class="sl-stat"><span>Minimum payout</span><b><?= e($sellMinPayout) ?></b></div>
            <p class="sl-stats-note">Commission is agreed with you, per product or for your whole store. Delivery is booked by us and charged at actual cost per package. Both are deducted from your payouts.</p>
        </aside>
    </div>
</section>

<!-- ═══════════════ BENEFITS ═══════════════ -->
<section class="sl-section">
    <div class="wrap">
        <div class="sl-head reveal">
            <span class="hp-eyebrow">Why sell with us</span>
            <h2>Everything you need to sell online, without the hassle</h2>
            <p>Focus on your products. We take care of the customers, payments, shipping and paperwork.</p>
        </div>
        <div class="sl-grid">
            <?php foreach ($sellBenefits as [$ic, $title, $body]): ?>
                <div class="sl-card reveal">
                    <div class="sl-ic"><?= $ic ?></div>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($body) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ HOW IT WORKS ═══════════════ -->
<section class="sl-section alt" id="how">
    <div class="wrap">
        <div class="sl-head reveal">
            <span class="hp-eyebrow">How it works</span>
            <h2>Start selling in five steps</h2>
        </div>
        <div class="sl-steps">
            <div class="sl-step reveal"><h3>Register</h3><p>Create your seller account with your business name, email and mobile number.</p></div>
            <div class="sl-step reveal"><h3>Complete your profile</h3><p>Add business details, addresses and bank account, and upload your documents.</p></div>
            <div class="sl-step reveal"><h3>Get approved</h3><p>Our team checks your documents. You can prepare your product listings meanwhile.</p></div>
            <div class="sl-step reveal"><h3>List your products</h3><p>Add photos, price, stock, HSN and GST rate. Products go live after a quick review.</p></div>
            <div class="sl-step reveal"><h3>Ship &amp; get paid</h3><p>Accept orders, pack them, book the courier in one click, and get paid every <?= htmlspecialchars($sellPayoutDay, ENT_QUOTES, 'UTF-8') ?>.</p></div>
        </div>
    </div>
</section>

<!-- ═══════════════ REQUIREMENTS / WHAT YOU CAN SELL ═══════════════ -->
<section class="sl-section">
    <div class="wrap">
        <div class="sl-head reveal">
            <span class="hp-eyebrow">Before you start</span>
            <h2>What you’ll need, and what you can sell</h2>
        </div>
        <div class="sl-two">
            <div class="sl-box reveal">
                <h3>Keep these ready</h3>
                <ul class="sl-list">
                    <?php foreach ($sellNeeds as $need): ?><li><?= e($need) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <div class="sl-box reveal">
                <h3>You can sell</h3>
                <ul class="sl-list">
                    <li>Vitamins, supplements and nutrition</li>
                    <li>Personal care, skin and hair care</li>
                    <li>Baby and mother care</li>
                    <li>Medical devices, first aid and home health</li>
                    <li>Ayurveda, homeopathy and wellness</li>
                </ul>
                <h3 style="margin-top:22px">Not allowed</h3>
                <ul class="sl-list no">
                    <li>Prescription medicines</li>
                    <li>Banned, restricted, counterfeit or grey-market goods</li>
                    <li>Products with medical claims such as “cures diabetes”</li>
                </ul>
                <p class="sl-box-note">Every item must be genuine, sealed, and have at least 3 months of shelf life left when dispatched.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ FAQ ═══════════════ -->
<section class="sl-section alt" id="faq">
    <div class="wrap">
        <div class="sl-head reveal">
            <span class="hp-eyebrow">Questions</span>
            <h2>Frequently asked questions</h2>
            <p>The full seller rules are shown in the seller portal before you start selling.</p>
        </div>
        <div class="sl-faq reveal">
            <?php foreach ($sellFaqs as $i => [$q, $a]): ?>
                <details<?= $i === 0 ? ' open' : '' ?>>
                    <summary>
                        <span><?= e($q) ?></span>
                        <svg class="sl-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <p><?= e($a) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ FINAL CTA ═══════════════ -->
<section class="sl-section">
    <div class="wrap">
        <div class="sl-final reveal">
            <h2>Ready to grow your health brand?</h2>
            <p>Register for free today. Our team will help you get your first products live.</p>
            <a href="<?= e($sellRegisterUrl) ?>" class="btn btn-primary btn-lg">Register as a seller →</a><br>
            <a href="<?= e($sellLoginUrl) ?>" class="sl-final-login">Already a seller? Sign in to your seller portal</a>
        </div>
    </div>
</section>

<?php
$hideFinalCta = true;   // this page has its own seller CTA
require __DIR__ . '/partials/footer.php';
?>
