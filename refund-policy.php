<?php
// =====================================================================
// refund-policy.php — eClinicPro Refund & Cancellation Policy
// Brand of Silver Webbuzz Pvt Ltd. Covers the 30-day free trial,
// annual subscription billing, GST, add-ons, and cancellation.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'Refund & Cancellation Policy — eClinicPro';
$metaDesc   = 'eClinicPro refund and cancellation terms — 30-day free trial, annual subscription, add-ons, GST and how to cancel.';
$activePage = 'legal';
$lastUpdated = 'June 8, 2026';

require __DIR__ . '/partials/header.php';
?>

<section class="legal-hero">
    <div class="wrap-narrow">
        <span class="eyebrow">Legal</span>
        <h1 class="h-display">Refund &amp; Cancellation Policy</h1>
        <p class="legal-meta">Last updated: <?= e($lastUpdated) ?></p>
        <p class="lede">
            This policy explains the free trial, billing and cancellation terms for the eClinicPro
            subscription, operated by <strong>Silver Webbuzz Pvt Ltd</strong>. It forms part of our
            <a href="/terms">Terms of Service</a>.
        </p>
    </div>
</section>

<section class="legal-body">
    <div class="wrap-narrow legal-prose">

        <div class="legal-callout">
            <strong>The short version.</strong> Try eClinicPro free for 30 days — no card needed. We
            only charge if you choose to subscribe. Because we offer a full trial before any payment,
            paid subscriptions are generally non-refundable, except as set out below and as required by
            law.
        </div>

        <h2>1. Free trial</h2>
        <p>
            New clinics get a <strong>30-day free trial</strong> of the full product. No credit card is
            required to start. We don't auto-charge at the end of the trial — you decide whether to
            subscribe. If you don't subscribe, your account simply pauses; you can export your data
            (see our <a href="/privacy-policy">Privacy Policy</a>).
        </p>

        <h2>2. Subscription &amp; billing</h2>
        <ul>
            <li>eClinicPro is billed as a single <strong>annual plan</strong> (currently ₹16,000/year).</li>
            <li>Prices are exclusive of taxes; <strong>GST (currently 18%) is added at checkout</strong>.</li>
            <li>Payments are processed in INR through our payment gateway (Razorpay).</li>
            <li>Optional add-ons (e.g., Patient Connect, Clinic Network) are billed monthly and can be cancelled at any time.</li>
        </ul>

        <h2>3. Refunds</h2>
        <p>Because a full 30-day trial is offered before any charge, fees are generally non-refundable. In particular, we do <strong>not</strong> provide refunds for:</p>
        <ul>
            <li>a change of mind after subscribing;</li>
            <li>not using the Services during the paid term;</li>
            <li>partial/unused periods after cancellation;</li>
            <li>monthly add-ons already started for the current month.</li>
        </ul>
        <p>We <strong>may</strong>, at our discretion, provide a full or pro-rata refund where:</p>
        <ul>
            <li>you were charged in error or charged twice;</li>
            <li>a material defect in the Services prevents core use and we are unable to resolve it within a reasonable time;</li>
            <li>a refund is required by applicable consumer law.</li>
        </ul>
        <p>
            Where a refund is approved, it is issued to the original payment method, in INR, within
            <strong>14 business days</strong>, net of any applicable taxes or currency-conversion
            differences.
        </p>

        <h2>4. How to cancel</h2>
        <p>
            You can cancel your subscription or an add-on at any time by emailing
            <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a> from your registered email,
            or from your account billing settings where available. Cancellation stops future renewals;
            your access continues until the end of the period you've already paid for. Add-on
            cancellations take effect at the end of the current monthly cycle.
        </p>

        <h2>5. Failed or disputed payments</h2>
        <p>
            If a renewal payment fails, we'll notify you and may pause paid features until payment is
            resolved. Payment disputes are handled in line with our payment gateway's processes and
            our <a href="/terms">Terms of Service</a>.
        </p>

        <h2>6. Changes to this policy</h2>
        <p>
            We may update this policy from time to time. Changes are posted here with a new
            "Last updated" date and apply to renewals and purchases made after they take effect.
        </p>

        <h2 id="contact">7. Contact</h2>
        <div class="legal-contact">
            <p><strong>Silver Webbuzz Pvt Ltd</strong> (operating eClinicPro)</p>
            <p>Billing, cancellations &amp; grievances: <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a></p>
            <p>Registered office: Ahmedabad, Gujarat, India</p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
