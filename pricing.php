<?php
// =====================================================================
// pricing.php — eClinicPro pricing
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Pricing — eClinicPro';
$metaDesc = 'Simple pricing. Start free, forever. Add only the modules you need. Pay what your clinic actually uses — never more.';
$activePage = 'pricing';

require __DIR__ . '/partials/header.php';

$plans = [
    ['Starter', 'Solo doctors', 0, 0, 'Start free', 'btn-dark', null, false, true],
    ['Clinic', 'Single-location', 29, 278, 'Start Clinic trial', 'btn-dark', null, false, false],
    ['Practice', 'Multi-doctor', 79, 758, 'Get Practice', 'btn-primary', 'Most chosen', true, false],
    ['Hospital', 'Multi-location', 199, 1910, 'Talk to sales', 'btn-dark', null, false, false],
];

$compareGroups = [
    ['Core', [
        ['Patient records', '200 active', 'Unlimited', 'Unlimited', 'Unlimited'],
        ['Appointments & reminders', true, true, true, true],
        ['WhatsApp & SMS reminders', false, true, true, true],
        ['Digital prescriptions', 'Basic', true, true, true],
        ['QR patient cards', false, true, true, true],
        ['Users', '1', '3', '10', 'Unlimited'],
        ['Locations', '1', '1', '3', 'Unlimited'],
    ]],
    ['Clinical modules', [
        ['Modules included', '0', '5', '12', 'All 24'],
        ['Vitals & trend charts', false, '+$5/mo', true, true],
        ['Lab orders', false, '+$8/mo', true, true],
        ['Pharmacy inventory', false, '+$12/mo', true, true],
        ['Telemedicine', false, '+$14/mo', true, true],
        ['Specialty modules (dental, homeo, etc.)', false, '+$7–11/mo', true, true],
    ]],
    ['Business', [
        ['Invoicing & payments', 'Basic', true, true, true],
        ['Multi-currency', false, true, true, true],
        ['Analytics & reports', false, false, true, true],
        ['Insurance claim submission', false, false, true, true],
        ['API & webhooks', false, false, true, true],
    ]],
    ['Security & support', [
        ['HIPAA / GDPR / DPDP', true, true, true, true],
        ['Audit logs', '30 days', '90 days', '1 year', 'Forever'],
        ['SSO (SAML)', false, false, false, true],
        ['Custom roles', false, false, true, true],
        ['Support', 'Community', 'Email · 24h', 'Chat · priority', 'Dedicated CSM'],
        ['SLA', false, false, false, '99.95%'],
    ]],
];

$renderCell = function ($v) {
    if ($v === true) return '<span class="cmp-tick">✓</span>';
    if ($v === false) return '<span class="cmp-dash">—</span>';
    return '<span style="font-size: 13px; color: var(--ink-2);">' . e((string) $v) . '</span>';
};
?>

<section style="padding: 140px 0 60px; text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(15,155,110,0.06) 0%, transparent 60%); pointer-events: none;"></div>
    <div class="wrap" style="position: relative; max-width: 820px;">
        <span class="eyebrow" style="display: block; margin-bottom: 16px;">Pricing</span>
        <h1 class="h-display" style="font-size: clamp(40px, 5.5vw, 60px); letter-spacing: -1.3px;">Simple pricing. No surprises.</h1>
        <p class="lede" style="font-size: 19px; margin-top: 22px; max-width: 640px; margin-left: auto; margin-right: auto;">
            Start free, forever. Upgrade only when your clinic outgrows it — or build your own plan from the module marketplace.
        </p>

        <div x-data="{ yearly: false }" style="display: flex; justify-content: center; margin-top: 32px;">
            <div class="pricing-toggle" style="display: inline-flex; background: var(--bg-2); border-radius: 999px; padding: 4px; position: relative;">
                <button type="button" @click="yearly = false"
                        :class="!yearly ? 'active' : ''"
                        style="position: relative; z-index: 1; padding: 8px 22px; border-radius: 999px; font-size: 13px; font-weight: 500; border: 0; background: transparent; cursor: pointer;"
                        :style="!yearly ? 'background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : ''">
                    Monthly
                </button>
                <button type="button" @click="yearly = true"
                        :class="yearly ? 'active' : ''"
                        style="position: relative; z-index: 1; padding: 8px 22px; border-radius: 999px; font-size: 13px; font-weight: 500; border: 0; background: transparent; cursor: pointer; display: flex; align-items: center; gap: 8px;"
                        :style="yearly ? 'background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : ''">
                    Yearly <span class="save-pill" style="font-size: 10px; background: var(--teal-50); color: var(--teal-800); padding: 2px 6px; border-radius: 999px; font-weight: 600;">Save 20%</span>
                </button>
            </div>
        </div>
    </div>
</section>

<section style="padding-top: 20px;" x-data="{ yearly: false }">
    <div class="wrap">
        <div class="cmp-wrap" style="overflow-x: auto;">
            <table class="cmp-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 32%; text-align: left; padding: 18px 16px; vertical-align: bottom;">
                            <span style="font-size: 12px; color: var(--mute); font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase;">Compare plans</span>
                        </th>
                        <?php foreach ($plans as [$name, $sub, $price, $yPrice, $cta, $ctaCls, $tag, $featured, $isStarter]):
                            $thBg = $featured ? 'background: var(--bg-3); border-top: 3px solid var(--teal-600);' : '';
                        ?>
                        <th style="padding: 18px 16px; text-align: center; vertical-align: bottom; <?= $thBg ?>">
                            <div class="cmp-plan">
                                <div style="font-size: 18px; font-weight: 500;"><?= e($name) ?></div>
                                <div style="font-size: 11px; color: var(--mute); margin-top: 2px;"><?= e($sub) ?></div>
                                <div style="margin-top: 12px; font-size: 22px; font-weight: 300;">
                                    <?php if ($price === 0): ?>
                                        Free
                                    <?php else: ?>
                                        $<span x-text="yearly ? <?= (int) $yPrice ?> : <?= (int) $price ?>"></span><span style="font-size: 12px; color: var(--mute);" x-text="yearly ? '/yr' : '/mo'"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compareGroups as [$group, $rows]): ?>
                    <tr class="cmp-group">
                        <td colspan="5" style="padding: 22px 16px 10px; font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500;"><?= e($group) ?></td>
                    </tr>
                    <?php foreach ($rows as $row): ?>
                    <tr style="border-top: 0.5px solid var(--line);">
                        <td style="padding: 12px 16px; font-weight: 500; color: var(--ink); font-size: 13.5px;"><?= e($row[0]) ?></td>
                        <?php for ($i = 1; $i <= 4; $i++):
                            $featuredCol = $plans[$i - 1][7];
                            $cellBg = $featuredCol ? 'background: var(--bg-3);' : '';
                        ?>
                        <td style="padding: 12px 16px; text-align: center; <?= $cellBg ?>"><?= $renderCell($row[$i]) ?></td>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <tr style="border-top: 0.5px solid var(--line);">
                        <td></td>
                        <?php foreach ($plans as [$name, $sub, $price, $yPrice, $cta, $ctaCls, $tag, $featured]):
                            $featuredCol = $featured ? 'background: var(--bg-3);' : '';
                        ?>
                        <td style="padding: 24px 16px; text-align: center; <?= $featuredCol ?>">
                            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn <?= e($ctaCls) ?>" style="width: 100%; max-width: 200px;"><?= e($cta) ?></a>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
        <div style="text-align: center; margin-top: 40px; font-size: 13px; color: var(--mute);">
            All prices in USD · Local currency at checkout · 30-day money-back · Cancel anytime
        </div>
    </div>
</section>

<!-- ============ CALCULATOR ============ -->
<?php
$presets = [
    ['gp', 'Solo GP', ['Prescriptions' => 6, 'QR patient card' => 4, 'Vitals & charts' => 5, 'Billing & invoices' => 9], 24],
    ['dental', 'Single-chair dentist', ['Prescriptions' => 6, 'Dental charting' => 9, 'Billing & invoices' => 9, 'Analytics & reports' => 9], 33],
    ['homeo', 'Homeopath', ['Prescriptions' => 6, 'Remedy database' => 7, 'Billing & invoices' => 9], 22],
    ['multi', 'Multi-doctor practice', ['Prescriptions' => 6, 'QR patient card' => 4, 'Vitals & charts' => 5, 'Pharmacy' => 12, 'Lab orders' => 8, 'Billing & invoices' => 9, 'Analytics' => 9, 'Telemedicine' => 14], 73],
];
?>
<section style="background: #fff; padding: 100px 0;" x-data="{ active: 'gp' }">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Build your own plan</span>
            <h2 class="h-section">What would your clinic pay?</h2>
            <p class="lede">Pick a typical clinic profile to see what a real à-la-carte plan costs — almost always less than the all-in tiers above.</p>
        </div>
        <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 36px;">
            <?php foreach ($presets as [$id, $label]): ?>
            <button type="button" @click="active = '<?= e($id) ?>'"
                    :class="active === '<?= e($id) ?>' ? 'active' : ''"
                    class="spec-tab"><?= e($label) ?></button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($presets as [$id, $label, $mods, $total]): ?>
        <div x-show="active === '<?= e($id) ?>'" x-cloak class="reveal" style="max-width: 640px; margin: 0 auto; background: var(--bg-2); border-radius: 18px; padding: 28px;">
            <div style="font-size: 12px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500; margin-bottom: 14px;">For: <?= e($label) ?></div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 0.5px solid var(--line);">
                    <span style="font-size: 14px;">Starter plan</span>
                    <span style="font-size: 14px; font-weight: 500;">Free</span>
                </div>
                <?php foreach ($mods as $name => $price): ?>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 0.5px solid var(--line);">
                    <span style="font-size: 14px;"><?= e($name) ?></span>
                    <span style="font-size: 14px; font-weight: 500;">$<?= (int) $price ?>/mo</span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--ink);">
                <span style="font-size: 14px; font-weight: 500;">Total per month</span>
                <span style="font-size: 32px; font-weight: 300; letter-spacing: -1px;">$<?= (int) $total ?><span style="font-size: 14px; color: var(--mute);">/mo</span></span>
            </div>
            <div style="margin-top: 20px; text-align: center;">
                <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary">Configure this plan</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ PRICING FAQ ============ -->
<?php
$faqs = [
    ['Can I change plans mid-month?', 'Yes. Upgrades take effect immediately and we prorate the difference. Downgrades take effect at the next billing cycle so you don\'t lose paid-for days.'],
    ['What counts as an "active patient" on Starter?', 'A patient seen or contacted in the last 12 months. Inactive patients sit in your archive forever, free, and re-activate the moment you book them.'],
    ['Do you offer a free trial of paid plans?', 'Every paid plan has a 30-day full-feature trial, no credit card needed. If you don\'t convert, your data stays accessible on the Starter free tier.'],
    ['Is there a per-doctor fee on top?', 'No. The plan price is the plan price. User seats are included in each tier. If you outgrow seats, the next tier up usually costs less than per-seat pricing would.'],
    ['How does multi-location billing work?', 'You\'re billed once for the whole organization. Practice includes 3 locations, Hospital is unlimited. Each location has its own dashboard but rolls up to one bill.'],
    ['What payment methods do you accept?', 'Credit/debit card, ACH/SEPA bank transfer, UPI (India), Apple Pay, Google Pay. Enterprise plans support invoice + wire.'],
    ['Do you discount for non-profits or rural clinics?', 'Yes — 40% off Practice/Hospital for registered non-profits, NGOs, and verified rural single-doctor clinics. Email hello@eclinicpro.com with documentation.'],
    ['What if I just want one specific module, like dental charting?', 'Build your own plan. Start on Starter (free), then add only the modules you need à la carte from the marketplace. Many solo dentists run on $13/month total.'],
];
?>
<section style="background: var(--bg-2);" x-data="{ open: 0 }">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Pricing questions</span>
            <h2 class="h-section">What doctors ask before they sign.</h2>
        </div>
        <div class="faq-list reveal">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
            <div class="faq-item" :class="open === <?= $i ?> ? 'open' : ''">
                <button type="button" class="faq-q" @click="open = open === <?= $i ?> ? -1 : <?= $i ?>">
                    <span><?= e($q) ?></span><span class="plus"></span>
                </button>
                <div class="faq-a" x-show="open === <?= $i ?>" x-collapse><?= e($a) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
