<?php /** Flash banner shared by /admin/store/* pages. */ ?>
<?php if (!empty($flashOk)): ?>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"><?= htmlspecialchars((string) $flashOk) ?></div>
<?php endif; ?>
<?php if (!empty($flashErr)): ?>
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"><?= htmlspecialchars((string) $flashErr) ?></div>
<?php endif; ?>
