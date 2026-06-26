<?php
/**
 * Standalone wrapper for a settings section promoted to its own page
 * (Leaves, Billing). Renders the section's existing tab partial inside a
 * page header — no logic duplicated, the partial is reused as-is.
 *
 * Requires: $pageHeading, $pageSub, $section (pre-rendered HTML).
 */
?>
<?php if (!empty($message)): ?>
<div class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Saved.</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars((string) $_GET['error']) ?></div>
<?php endif; ?>

<?= ui_page_header($pageHeading ?? 'Settings', $pageSub ?? '') ?>

<div class="mx-auto max-w-4xl">
    <?= $section ?>
</div>
