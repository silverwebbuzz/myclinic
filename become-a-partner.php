<?php
// =====================================================================
// become-a-partner.php — public landing for the affiliate/partner program.
// Linked from the footer. CTAs point at the app's /partner/register.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Become a Partner — eClinicPro';
$metaDesc = 'Refer clinics to eClinicPro and earn recurring commission on every subscription and renewal. Join from any city or country.';
$activePage = '';

$steps = [
    ['1', 'Apply &amp; verify', 'Sign up as a partner and upload a few KYC documents. We review and approve — usually within a couple of days.'],
    ['2', 'Share your link', 'Get a unique referral link and code. Share it with clinics and doctors in your city, country, or network.'],
    ['3', 'Earn on every renewal', 'When a clinic you referred buys a yearly subscription — and every year they renew — you earn your commission.'],
    ['4', 'Request payouts', 'Track your referred clinics and earnings in your dashboard. Request a payout anytime; we process within 7 days.'],
];

$perks = [
    ['💸', 'Recurring commission', 'You earn a percentage on the initial subscription and on every yearly renewal — not just a one-time payout.'],
    ['🌍', 'Open to everyone', 'Join from any city or country. No fixed targets, no upfront cost.'],
    ['📊', 'Full transparency', 'A real dashboard: which clinics you brought, their subscription status, and exactly what you have earned.'],
    ['⚡', 'Fast payouts', 'Request a payout to UPI or bank. We process within 7 days. You always see the status.'],
];

require __DIR__ . '/partials/header.php';
?>

<section style="padding: 140px 0 60px; text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(15,155,110,0.06) 0%, transparent 60%); pointer-events: none;"></div>
    <div class="wrap" style="position: relative; max-width: 820px;">
        <span class="eyebrow" style="display: block; margin-bottom: 16px;">Partner program</span>
        <h1 class="h-display" style="font-size: clamp(40px, 5.5vw, 60px); letter-spacing: -1.3px;">Refer clinics. Earn every year they stay.</h1>
        <p class="lede" style="font-size: 19px; margin-top: 22px; max-width: 640px; margin-left: auto; margin-right: auto;">
            Join the eClinicPro Partner Program from anywhere. Bring a clinic onto a yearly subscription and earn recurring commission — on the first sale and every renewal after.
        </p>
        <div class="hero-ctas" style="margin-top: 28px; justify-content: center;">
            <a href="<?= e(ecp_portal_url('/partner/register')) ?>" class="btn btn-primary btn-lg">Become a partner</a>
            <a href="<?= e(ecp_portal_url('/partner/login')) ?>" class="btn btn-ghost-dark btn-lg">Partner login →</a>
        </div>
    </div>
</section>

<section style="padding: 60px 0; border-top: 0.5px solid var(--line); background: var(--bg-2);">
    <div class="wrap">
        <h2 style="text-align:center; margin-bottom: 8px;">How it works</h2>
        <p class="lede" style="text-align:center; margin-bottom: 36px;">Four simple steps from signup to payout.</p>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px;">
            <?php foreach ($steps as $s): ?>
            <div style="background:#fff; border:0.5px solid var(--line); border-radius:16px; padding:24px;">
                <div style="width:36px;height:36px;border-radius:50%;background:rgba(15,155,110,0.12);color:#0F9B6E;display:flex;align-items:center;justify-content:center;font-weight:700;"><?= $s[0] ?></div>
                <h3 style="margin:14px 0 8px;font-size:17px;"><?= $s[1] ?></h3>
                <p style="color:var(--ink-2);font-size:14px;line-height:1.6;"><?= $s[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section style="padding: 60px 0;">
    <div class="wrap">
        <h2 style="text-align:center; margin-bottom: 36px;">Why partner with us</h2>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px;">
            <?php foreach ($perks as $p): ?>
            <div style="padding:20px;">
                <div style="font-size:28px;"><?= $p[0] ?></div>
                <h3 style="margin:12px 0 8px;font-size:17px;"><?= $p[1] ?></h3>
                <p style="color:var(--ink-2);font-size:14px;line-height:1.6;"><?= $p[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cta-block" id="cta">
    <div class="wrap reveal">
        <h2>Ready to start earning?</h2>
        <p class="lede">Apply in 2 minutes. No upfront cost. Open to partners worldwide.</p>
        <div class="hero-ctas">
            <a href="<?= e(ecp_portal_url('/partner/register')) ?>" class="btn btn-primary btn-lg">Become a partner</a>
        </div>
    </div>
</section>

<?php
$hideFinalCta = true; // we already have a CTA above
require __DIR__ . '/partials/footer.php';
