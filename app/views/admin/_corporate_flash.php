<?php
/** Flash messages for the Workforce Health admin pages ($flashOk / $flashErr from CorporateAdminController::render). */
$hf = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<?php if (!empty($flashOk)): ?>
    <div class="mt-3 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"><?= $hf($flashOk) ?></div>
<?php endif; ?>
<?php if (!empty($flashErr)): ?>
    <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $hf($flashErr) ?></div>
<?php endif; ?>
