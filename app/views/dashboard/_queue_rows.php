<?php
$statusStyles = [
    'scheduled' => 'bg-slate-100 text-slate-700',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'in_progress' => 'bg-amber-100 text-amber-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
];
?>
<?php if ($queue === []): ?>
<div class="p-8 text-center">
    <p class="text-sm font-medium text-slate-600">No appointments yet today</p>
    <p class="mt-1 text-xs text-slate-400">New bookings and walk-ins will show up here as they come in.</p>
    <a href="/appointments/new" class="ui-btn ui-btn-primary mt-4 inline-flex">+ Book appointment</a>
</div>
<?php else: ?>
<?php foreach ($queue as $row): ?>
<div class="flex items-center justify-between px-4 py-3 text-sm <?= ($row['status'] ?? '') === 'in_progress' ? 'bg-amber-50/60' : '' ?>">
    <div>
        <p class="font-medium">
            <?php if (!empty($row['token_number'])): ?>
            <span class="mr-2 rounded bg-emerald-100 px-2 py-0.5 font-mono text-xs text-emerald-800">#<?= (int) $row['token_number'] ?></span>
            <?php endif; ?>
            <?= htmlspecialchars($row['patient_name'] ?? '') ?>
        </p>
        <p class="text-xs text-slate-500"><?= htmlspecialchars($row['uhid'] ?? '') ?> · <?= htmlspecialchars($row['doctor_name'] ?? '') ?></p>
    </div>
    <div class="text-right">
        <p class="font-mono text-xs"><?= date('H:i', strtotime($row['scheduled_at'])) ?></p>
        <span class="inline-block rounded-full px-2 py-0.5 text-xs capitalize <?= $statusStyles[$row['status'] ?? ''] ?? 'bg-slate-100 text-slate-700' ?>">
            <?= htmlspecialchars(str_replace('_', ' ', $row['status'] ?? '')) ?>
        </span>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
