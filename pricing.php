<?php
// =====================================================================
// pricing.php — eClinicPro pricing (single plan, two add-ons, founding clinic)
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Pricing — eClinicPro';
$metaDesc = 'One simple plan. ₹1,499/month. Everything to run your clinic. Plus optional add-ons for WhatsApp and multi-branch.';
$activePage = 'pricing';

// Pull founding clinic counter from DB if available; fall back to constants
// when the table isn't migrated yet (don't 500 the marketing page).
$fcCap = 100;
$fcClaimed = 0;
$fcOpen = true;
try {
    $db = ecp_db();
    if ($db) {
        $row = $db->query("SELECT cap, claimed, closed_at FROM founding_clinic_state WHERE id = 1")
            ->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fcCap = (int) $row['cap'];
            $fcClaimed = (int) $row['claimed'];
            $fcOpen = empty($row['closed_at']) && $fcClaimed < $fcCap;
        }
    }
} catch (Throwable $e) {
    // Table doesn't exist yet during phased rollout — silently fall back.
}
$fcRemaining = max(0, $fcCap - $fcClaimed);

require __DIR__ . '/partials/header.php';
?>

<section class="hero pricing-hero">
    <div class="wrap">
        <span class="eyebrow">Pricing</span>
        <h1 class="h-display">One plan. Everything to run your clinic.</h1>
        <p class="lede center">
            No tiers. No upsells hidden behind a paywall. Pay one price, get the whole clinic system.
            Try it free for 30 days — no card needed.
        </p>
    </div>
</section>

<?php if ($fcOpen): ?>
<section class="founding-banner">
    <div class="wrap">
        <div class="fc-card">
            <div class="fc-badge">Founding clinic deal</div>
            <h2 class="fc-title">
                ₹999/month <span class="fc-strike">₹1,499</span>
                <span class="fc-locked">locked for 24 months</span>
            </h2>
            <p class="fc-sub">
                First <strong><?= $fcCap ?></strong> clinics to sign up get this rate, locked in.
                <strong><?= $fcRemaining ?></strong> of <?= $fcCap ?> spots remaining.
            </p>
            <a href="https://app.eclinicpro.com/register?fc=1" class="btn btn-primary btn-lg">
                Claim founding clinic price
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="plan-section">
    <div class="wrap">
        <div class="plan-grid">

            <!-- Base plan card -->
            <div class="plan-card primary">
                <div class="plan-head">
                    <span class="plan-name">Standard</span>
                    <h3 class="plan-price">
                        <span class="currency">₹</span>1,499<span class="per">/month</span>
                    </h3>
                    <p class="plan-yearly">
                        or <strong>₹14,999/year</strong> — one month free
                    </p>
                </div>

                <ul class="plan-features">
                    <li>✓ Patient records, visits, prescriptions</li>
                    <li>✓ Appointments &amp; walk-in queue</li>
                    <li>✓ Billing &amp; invoicing (GST-ready)</li>
                    <li>✓ Vitals, diagnosis, follow-up tracking</li>
                    <li>✓ Specialty-aware forms (50+ specialties)</li>
                    <li>✓ Teleconsultation built in</li>
                    <li>✓ Public doctor profile on eclinicpro.com</li>
                    <li>✓ Daily reports &amp; analytics</li>
                    <li>✓ Unlimited patients, unlimited staff users</li>
                    <li>✓ 30-day free trial — no credit card</li>
                </ul>

                <a href="https://app.eclinicpro.com/register" class="btn btn-primary btn-lg btn-block">
                    Start 30-day free trial
                </a>
                <p class="plan-fineprint">No card needed. Cancel anytime during trial.</p>
            </div>

            <!-- Add-ons column -->
            <div class="addon-column">
                <h3 class="addon-heading">Optional add-ons</h3>

                <div class="addon-card">
                    <div class="addon-icon">💬</div>
                    <div>
                        <h4 class="addon-name">Patient Connect</h4>
                        <p class="addon-desc">
                            WhatsApp automation: appointment reminders, prescription delivery,
                            follow-up nudges. Cuts no-show rates in half.
                        </p>
                        <div class="addon-price">+₹499/month</div>
                    </div>
                </div>

                <div class="addon-card">
                    <div class="addon-icon">🌿</div>
                    <div>
                        <h4 class="addon-name">Clinic Network</h4>
                        <p class="addon-desc">
                            Add an extra clinic branch under one account.
                            Unified patient records, separate queues per branch.
                        </p>
                        <div class="addon-price">+₹999/month per branch</div>
                    </div>
                </div>

                <p class="addon-tease">
                    More add-ons launching soon: AI voice notes, advanced analytics, lab management.
                </p>
            </div>

        </div>
    </div>
</section>

<section class="faq-section">
    <div class="wrap-narrow">
        <h2 class="h-section center">Pricing FAQ</h2>

        <div class="faq-item">
            <h3>Is there really only one plan?</h3>
            <p>
                Yes. We removed Basic/Pro/Enterprise tiers because Indian clinics don't want to
                guess which one they need. Everything required to run a clinic is included in
                ₹1,499/month. Two optional add-ons cover the extras most clinics ask for.
            </p>
        </div>

        <div class="faq-item">
            <h3>What happens after the 30-day trial?</h3>
            <p>
                You decide whether to continue. If you forget, we email you 3 days before expiry
                and grant a one-time 15-day extension if you ask. No automatic charges; no card
                taken upfront.
            </p>
        </div>

        <div class="faq-item">
            <h3>Can I add or remove add-ons anytime?</h3>
            <p>
                Yes. Add-ons are month-to-month. Cancel any time — no penalty, no notice period.
            </p>
        </div>

        <div class="faq-item">
            <h3>Do you offer annual discounts?</h3>
            <p>
                Yes. Pay ₹14,999/year (one month free) or ₹1,499/month. Same features.
            </p>
        </div>

        <div class="faq-item">
            <h3>Is GST included in the price?</h3>
            <p>
                GST (18%) is added at checkout for India billing. Invoices are GST-compliant.
            </p>
        </div>

        <div class="faq-item">
            <h3>What about countries other than India?</h3>
            <p>
                Right now we're optimized for Indian clinics — pricing is in INR, payments via
                Razorpay, GST-ready invoicing. International clinics can still sign up; we're
                rolling out multi-currency support based on customer demand.
            </p>
        </div>

        <div class="faq-item">
            <h3>What's the Founding Clinic deal?</h3>
            <p>
                The first <?= $fcCap ?> clinics to sign up lock in ₹999/month for 24 months —
                a permanent discount as a thank-you for being early. After 24 months your
                account converts to the standard ₹1,499/month rate.
                <?php if ($fcOpen): ?>
                <strong><?= $fcRemaining ?> spots left.</strong>
                <?php else: ?>
                Sold out.
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<section class="cta-block">
    <div class="wrap-narrow center">
        <h2 class="h-section">Try eClinicPro for 30 days</h2>
        <p class="lede">No credit card. Full product. Cancel anytime.</p>
        <a href="https://app.eclinicpro.com/register" class="btn btn-primary btn-lg">
            Start free trial
        </a>
    </div>
</section>

<style>
.pricing-hero { padding-bottom: 24px; }
.lede.center { text-align: center; max-width: 640px; margin: 0 auto 32px; }

.founding-banner { padding: 24px 0 16px; }
.fc-card {
    background: linear-gradient(135deg, #fff8e1, #fff);
    border: 2px solid #f59e0b;
    border-radius: 18px;
    padding: 28px 32px;
    text-align: center;
    max-width: 720px;
    margin: 0 auto;
    box-shadow: 0 10px 30px rgba(245, 158, 11, 0.12);
}
.fc-badge {
    display: inline-block;
    background: #f59e0b;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 4px 12px;
    border-radius: 999px;
    margin-bottom: 12px;
}
.fc-title {
    font-size: 32px;
    font-weight: 300;
    letter-spacing: -0.8px;
    margin: 0 0 8px;
}
.fc-strike { text-decoration: line-through; color: var(--mute); font-size: 18px; margin-left: 8px; }
.fc-locked { display: block; font-size: 13px; font-weight: 500; color: var(--ink-2); margin-top: 4px; }
.fc-sub { color: var(--ink-2); margin: 8px 0 18px; }

.plan-section { padding: 32px 0 64px; }
.plan-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 32px;
    max-width: 1100px;
    margin: 0 auto;
}
@media (max-width: 900px) {
    .plan-grid { grid-template-columns: 1fr; }
}

.plan-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.04);
}
.plan-card.primary { border: 2px solid var(--teal-600); }
.plan-name {
    display: inline-block;
    background: var(--teal-50);
    color: var(--teal-700);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 999px;
}
.plan-price {
    font-size: 48px;
    font-weight: 300;
    letter-spacing: -1.2px;
    margin: 12px 0 4px;
}
.plan-price .currency { font-size: 24px; vertical-align: super; opacity: 0.7; margin-right: 2px; }
.plan-price .per { font-size: 16px; font-weight: 400; color: var(--mute); margin-left: 4px; }
.plan-yearly { color: var(--ink-2); margin: 0 0 24px; }
.plan-features { list-style: none; padding: 0; margin: 0 0 24px; }
.plan-features li { padding: 6px 0; font-size: 14.5px; color: var(--ink-2); }
.btn-block { display: block; text-align: center; width: 100%; }
.plan-fineprint { font-size: 12px; color: var(--mute); margin: 10px 0 0; text-align: center; }

.addon-column { display: flex; flex-direction: column; gap: 16px; }
.addon-heading { font-size: 16px; font-weight: 600; margin: 0 0 4px; color: var(--ink-2); }
.addon-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    gap: 16px;
    transition: border-color .15s;
}
.addon-card:hover { border-color: var(--teal-400); }
.addon-icon { font-size: 28px; flex-shrink: 0; }
.addon-name { font-size: 16px; font-weight: 600; margin: 0 0 6px; }
.addon-desc { font-size: 13.5px; color: var(--ink-2); line-height: 1.55; margin: 0 0 8px; }
.addon-price { font-size: 14px; font-weight: 600; color: var(--teal-700); }
.addon-tease { font-size: 12.5px; color: var(--mute); padding: 0 4px; line-height: 1.5; }

.faq-section { padding: 64px 0; background: var(--bg-2); }
.wrap-narrow { max-width: 720px; margin: 0 auto; padding: 0 24px; }
.center { text-align: center; }
.faq-item { margin: 24px 0; padding-bottom: 24px; border-bottom: 1px solid var(--line); }
.faq-item:last-child { border-bottom: 0; }
.faq-item h3 { font-size: 17px; font-weight: 600; margin: 0 0 8px; color: var(--ink); }
.faq-item p { font-size: 14.5px; color: var(--ink-2); line-height: 1.65; margin: 0; }

.cta-block { padding: 80px 0; background: #fff; }

@media (max-width: 600px) {
    .fc-title { font-size: 24px; }
    .fc-card { padding: 22px 18px; }
    .plan-card { padding: 22px; }
    .plan-price { font-size: 36px; }
}
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
