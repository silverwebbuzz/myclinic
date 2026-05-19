<?php if ($queue === []): ?>
<p class="p-6 text-center text-sm text-slate-500">No appointments scheduled for today.</p>
<?php else: ?>
<?php foreach ($queue as $row): ?>
<div class="flex items-center justify-between px-4 py-3 text-sm">
    <div>
        <p class="font-medium"><?= htmlspecialchars($row['patient_name'] ?? '') ?></p>
        <p class="text-xs text-slate-500"><?= htmlspecialchars($row['uhid'] ?? '') ?> · <?= htmlspecialchars($row['doctor_name'] ?? '') ?></p>
    </div>
    <div class="text-right">
        <p class="font-mono text-xs"><?= date('H:i', strtotime($row['scheduled_at'])) ?></p>
        <span class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs capitalize"><?= htmlspecialchars($row['status'] ?? '') ?></span>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
