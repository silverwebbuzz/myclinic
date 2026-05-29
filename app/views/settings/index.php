<?php
/**
 * Settings — single page, two-column balanced layout (reference: Medicare).
 * No sub-menu. The 10 section partials are grouped into 4 logical panels
 * and explicitly placed into a left and right column so heights stay
 * balanced (a CSS-columns masonry made them lopsided).
 *
 * Each $sections[$t] is already a self-contained .ui-card, so we just
 * place them; grouping is by column assignment below.
 */

// 'general' (the booking-page hero + clinic details) spans the full row on
// top; everything else flows into two balanced columns below.
$fullOrder  = ['general'];
$leftOrder  = ['hours', 'specialty', 'branding'];
$rightOrder = ['notifications', 'leaves', 'team', 'subscription', 'api', 'consent-forms'];

$render = static function (array $order, array $tabs, array $sections): string {
    $out = '';
    foreach ($order as $t) {
        if (in_array($t, $tabs, true) && isset($sections[$t])) {
            $out .= '<div>' . $sections[$t] . '</div>';
        }
    }
    return $out;
};
?>
<?php if (!empty($message)): ?>
<div class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Settings saved.</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<?= ui_page_header('Settings', 'Personalize your clinic and manage preferences securely.') ?>

<!-- Booking page + clinic details: full width -->
<div class="mb-4 space-y-4"><?= $render($fullOrder, $tabs, $sections) ?></div>

<!-- Everything else: two balanced columns -->
<div class="grid items-start gap-4 lg:grid-cols-2">
    <div class="space-y-4"><?= $render($leftOrder, $tabs, $sections) ?></div>
    <div class="space-y-4"><?= $render($rightOrder, $tabs, $sections) ?></div>
</div>
