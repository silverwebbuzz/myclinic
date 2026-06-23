<?php
/**
 * Settings — single page, accordion layout.
 *
 * Each $sections[$t] is a self-contained .ui-card partial. Rather than tile
 * them into balanced columns (which looked busy), every section is wrapped in
 * a native <details> accordion so the page opens compact and the user clicks
 * the row they want to edit. 'general' is expanded by default.
 *
 * The per-tab title/subtitle/icon below drive the clickable summary row. The
 * partials keep their own internal heading, so we hide the card's own
 * .ui-card-header / first .ui-section-title visually is NOT needed — instead we
 * present a clean summary and let the card body show below it.
 */

// Title, subtitle and a simple inline SVG icon for each accordion row.
$meta = [
    'general'       => ['Clinic profile & booking page', 'Clinic name, contact details, and your public booking page.', 'M3 21h18M5 21V7l8-4 8 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01'],
    'hours'         => ['Working hours', 'Set consultation timings for each day of the week.', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    'specialty'     => ['Specialty', 'Choose your clinic specialty and what it tailors.', 'M11 4a4 4 0 100 8 4 4 0 000-8zm0 8v8m-4-4h8'],
    'leaves'        => ['Leaves & holidays', 'Block dates when the clinic is closed.', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    'notifications' => ['Notifications & messaging', 'WhatsApp, automated patient reminders, and payments.', 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
    'subscription'  => ['Subscription & billing', 'Your plan, seats, and billing details.', 'M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
    'branding'      => ['White-label branding', 'Custom domain, logo, and brand color (Enterprise).', 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h10a2 2 0 012 2v6M7 21h10a2 2 0 002-2v-5M7 21v-5a2 2 0 012-2h2'],
];

// 'general' opens by default; everything else starts collapsed.
$openByDefault = 'general';

// Render order — single column, general first.
// 'notifications' is intentionally omitted — not currently needed in Settings.
$order = ['general', 'hours', 'specialty', 'leaves', 'subscription', 'branding'];
?>
<?php if (!empty($message)): ?>
<div class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Settings saved.</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<?= ui_page_header('Settings', 'Personalize your clinic and manage preferences securely.') ?>

<div class="mx-auto max-w-4xl space-y-3">
    <?php foreach ($order as $t): ?>
        <?php if (!in_array($t, $tabs, true) || !isset($sections[$t])): continue; endif; ?>
        <?php [$title, $sub, $icon] = $meta[$t] ?? [ucfirst($t), '', 'M5 12h14']; ?>
        <details class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"<?= $t === $openByDefault ? ' open' : '' ?>>
            <summary class="flex cursor-pointer select-none list-none items-center gap-3 px-4 py-3.5 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= htmlspecialchars($icon) ?>" />
                    </svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-slate-800"><?= htmlspecialchars($title) ?></span>
                    <?php if ($sub !== ''): ?>
                    <span class="block truncate text-xs text-slate-500"><?= htmlspecialchars($sub) ?></span>
                    <?php endif; ?>
                </span>
                <svg class="h-5 w-5 shrink-0 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </summary>
            <div class="settings-accordion-body border-t border-slate-100 p-4 sm:p-5">
                <?= $sections[$t] ?>
            </div>
        </details>
    <?php endforeach; ?>
</div>

<style>
/* The accordion frame already supplies the border, padding and section title,
   so neutralise the chrome on the partials' own top-level cards to avoid a
   double border / double padding inside each open panel. Nested cards (e.g.
   subscription, team) keep their own styling. */
.settings-accordion-body > .ui-card,
.settings-accordion-body > form.ui-card {
    border: 0;
    box-shadow: none;
    background: transparent;
}
.settings-accordion-body > .ui-card.ui-card-pad,
.settings-accordion-body > form.ui-card.ui-card-pad,
/* hours / notifications wrap their body in an inner .ui-card-pad div */
.settings-accordion-body > form.ui-card > .ui-card-pad {
    padding: 0;
}
</style>
