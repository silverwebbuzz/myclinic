<?php
// =====================================================================
// specialty-template.php — shared body markup for all 6 specialty pages.
// Each thin wrapper (gps.php, dentists.php, etc.) sets $spec then requires this.
// =====================================================================
require_once __DIR__ . '/helpers.php';

if (!isset($spec) || !is_array($spec)) {
    http_response_code(500);
    echo 'specialty-template.php: missing $spec';
    return;
}

$pageTitle = $spec['label'] . ' — eClinicPro';
$metaDesc = $spec['heroBlurb'];
$activePage = 'specialties';

require __DIR__ . '/header.php';
?>

<!-- ============ HERO ============ -->
<section style="padding: 140px 0 60px; text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(15,155,110,0.06) 0%, transparent 60%); pointer-events: none;"></div>
    <div class="wrap" style="position: relative; max-width: 820px;">
        <div style="font-size: 56px; margin-bottom: 16px;"><?= $spec['icon'] ?></div>
        <span class="eyebrow" style="display: block; margin-bottom: 16px;">For <?= e($spec['label']) ?></span>
        <h1 class="h-display" style="font-size: clamp(38px, 5vw, 56px); letter-spacing: -1.2px;">
            <?= e($spec['headline'][0]) ?><span class="grad"><?= e($spec['headline'][1]) ?></span><?= e($spec['headline'][2]) ?>
        </h1>
        <p class="lede" style="font-size: 18px; margin-top: 22px; max-width: 640px; margin-left: auto; margin-right: auto;">
            <?= e($spec['heroBlurb']) ?>
        </p>
        <div style="display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; margin-top: 24px;">
            <?php foreach ($spec['heroProof'] as $p): ?>
            <span style="font-size: 13px; color: var(--mute); font-weight: 500;">
                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--teal-500); margin-right: 8px; vertical-align: middle;"></span>
                <?= e($p) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <div class="hero-ctas" style="margin-top: 32px; justify-content: center;">
            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">Start free</a>
            <a href="/book-a-demo" class="btn btn-ghost-dark btn-lg">See a tailored demo →</a>
        </div>
    </div>
</section>

<!-- ============ STATS ============ -->
<section style="padding: 56px 0; background: var(--bg-2); border-top: 0.5px solid var(--line); border-bottom: 0.5px solid var(--line);">
    <div class="wrap">
        <div class="stats">
            <?php foreach ($spec['stats'] as [$v, $l]): ?>
            <div class="stat reveal">
                <div class="stat-num"><?= e($v) ?></div>
                <div class="stat-label"><?= e($l) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ FEATURES ============ -->
<section style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Built for <?= e(strtolower($spec['label'])) ?></span>
            <h2 class="h-section"><?= e($spec['featsHead'][0]) ?><br><?= e($spec['featsHead'][1]) ?></h2>
        </div>
        <div class="feat-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px;">
            <?php foreach ($spec['feats'] as $i => [$icon, $t, $d]): ?>
            <div class="feat-item reveal" style="transition-delay: <?= ($i % 3) * 60 ?>ms;">
                <div class="ico" style="width: 40px; height: 40px; border-radius: 10px; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center; font-size: 18px; margin-bottom: 14px;"><?= $icon ?></div>
                <h4 style="font-size: 15px; font-weight: 500; margin-bottom: 6px;"><?= e($t) ?></h4>
                <p style="font-size: 13.5px; color: var(--mute); line-height: 1.55;"><?= e($d) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ WORKFLOW ============ -->
<section class="bg-grey" style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">A day in the clinic</span>
            <h2 class="h-section"><?= e($spec['workflowHead']) ?></h2>
        </div>
        <div style="display: flex; flex-direction: column; gap: 4px; max-width: 760px; margin: 0 auto;">
            <?php foreach ($spec['workflow'] as $i => [$t, $d]): ?>
            <div class="reveal" style="display: grid; grid-template-columns: 48px 1fr; gap: 20px; padding: 24px 0; border-top: <?= $i === 0 ? 'none' : '0.5px solid var(--line)' ?>;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--teal-600); color: #fff; display: grid; place-items: center; font-weight: 500; font-size: 15px;"><?= $i + 1 ?></div>
                <div>
                    <h4 style="font-size: 17px; font-weight: 500; margin-bottom: 4px;"><?= e($t) ?></h4>
                    <p style="font-size: 14px; color: var(--mute); line-height: 1.6;"><?= e($d) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ PRICING ============ -->
<section style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Build your plan</span>
            <h2 class="h-section"><?= e($spec['pricingHead']) ?></h2>
            <p class="lede"><?= e($spec['pricingBlurb']) ?></p>
        </div>
        <div class="reveal" style="max-width: 520px; margin: 0 auto; background: var(--bg-2); border-radius: 18px; padding: 28px;">
            <div style="font-size: 12px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500; margin-bottom: 14px;">For: <?= e($spec['pricingLabel']) ?></div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($spec['pricingItems'] as [$item, $price]): ?>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 0.5px solid var(--line);">
                    <span style="font-size: 14px;"><?= e($item) ?></span>
                    <span style="font-size: 14px; font-weight: 500;"><?= e($price) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--ink);">
                <span style="font-size: 14px; font-weight: 500;">Total per month</span>
                <span style="font-size: 32px; font-weight: 300; letter-spacing: -1px;">$<?= (int) $spec['pricingTotal'] ?><span style="font-size: 14px; color: var(--mute);">/mo</span></span>
            </div>
            <div style="margin-top: 20px; text-align: center;">
                <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary">Configure this plan</a>
            </div>
        </div>
    </div>
</section>

<!-- ============ TESTIMONIALS ============ -->
<section class="bg-grey" style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">In their words</span>
            <h2 class="h-section"><?= e($spec['testimonialsHead']) ?></h2>
        </div>
        <div class="tgrid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <?php foreach ($spec['testimonials'] as $i => [$name, $loc, $quote]):
                $initials = '';
                foreach (explode(' ', $name) as $w) {
                    if ($w !== '' && ctype_upper($w[0])) {
                        $initials .= $w[0];
                        if (strlen($initials) >= 2) break;
                    }
                }
            ?>
            <div class="tcard reveal" style="transition-delay: <?= $i * 80 ?>ms; background: #fff; border: 0.5px solid var(--line); border-radius: 16px; padding: 28px;">
                <div class="stars" style="color: var(--amber); font-size: 14px; letter-spacing: 2px;">★★★★★</div>
                <blockquote style="font-size: 15px; font-weight: 300; line-height: 1.5; margin: 16px 0; color: var(--ink);">"<?= e($quote) ?>"</blockquote>
                <div class="tperson" style="display: flex; align-items: center; gap: 12px; margin-top: 18px;">
                    <div class="tavatar" style="width: 38px; height: 38px; border-radius: 50%; background: var(--teal-600); color: #fff; display: grid; place-items: center; font-weight: 500; font-size: 13px;"><?= e($initials) ?></div>
                    <div>
                        <div class="nm" style="font-size: 14px; font-weight: 500;"><?= e($name) ?></div>
                        <div class="sp" style="font-size: 12px; color: var(--mute);"><?= e($loc) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ MIGRATION ============ -->
<section style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal" style="text-align: center;">
            <span class="eyebrow">Migration</span>
            <h2 class="h-section">We'll move you over.</h2>
            <p class="lede"><?= e($spec['migrationBlurb']) ?></p>
        </div>
        <div class="reveal" style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; max-width: 720px; margin: 0 auto;">
            <?php foreach ($spec['migrateFrom'] as $from): ?>
            <span style="font-size: 13px; padding: 8px 16px; background: var(--bg-2); border-radius: 999px; font-weight: 500; color: var(--ink-2);"><?= e($from) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ OTHER SPECIALTIES ============ -->
<?php
$allSpecs = require __DIR__ . '/specialty-data.php';
$otherSpecs = array_filter($allSpecs, fn ($s) => $s['slug'] !== $spec['slug']);
?>
<section class="bg-grey" style="padding: 80px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Other specialties</span>
            <h2 class="h-section" style="font-size: 28px;">Built for theirs too.</h2>
        </div>
        <div class="specialty-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; max-width: 900px; margin: 0 auto;">
            <?php foreach ($otherSpecs as $os): ?>
            <a href="/<?= e($os['slug']) ?>" class="specialty-card reveal" style="background: #fff; border: 0.5px solid var(--line); border-radius: 14px; padding: 20px; text-decoration: none; color: inherit; transition: border-color .15s, transform .15s;">
                <div style="font-size: 28px; margin-bottom: 8px;"><?= $os['icon'] ?></div>
                <div style="font-size: 14px; font-weight: 500;">For <?= e(strtolower($os['label'])) ?></div>
                <div style="font-size: 12px; color: var(--mute); margin-top: 2px;"><?= e($os['heroProof'][0]) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
@media (max-width: 800px) {
    .feat-grid, .tgrid { grid-template-columns: 1fr !important; }
    .specialty-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
</style>

<?php
// Use the final CTA from the specialty data instead of the default
$hideFinalCta = true;
?>
<section class="cta-block" id="cta">
    <div class="wrap reveal">
        <h2><?= e($spec['ctaTitle']) ?></h2>
        <p class="lede"><?= e($spec['ctaSub']) ?><br>
        No credit card. No phone-tag with sales. Just a clean clinic.</p>
        <div class="hero-ctas">
            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">Start free — no card needed</a>
            <a href="/book-a-demo" class="btn btn-ghost-dark btn-lg">Schedule a 15-min demo →</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
