<?php
$statusStyles = [
    'scheduled' => 'bg-slate-100 text-slate-700',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'in_progress' => 'bg-amber-100 text-amber-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'no_show' => 'bg-rose-100 text-rose-700',
    'cancelled' => 'bg-slate-100 text-slate-400 line-through',
];
?>
<?php if ($queue === []): ?>
<div class="p-8 text-center">
    <p class="text-sm font-medium text-slate-600">No appointments in today's queue</p>
    <p class="mt-1 text-xs text-slate-400">Walk-ins and bookings for today will appear here automatically.</p>
    <a href="/appointments/new" class="ui-btn ui-btn-primary mt-4 inline-flex">+ Book appointment</a>
</div>
<?php else: ?>
<?php foreach ($queue as $row): ?>
<div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm <?= ($row['status'] ?? '') === 'in_progress' ? 'bg-amber-50/60' : '' ?>">
    <div>
        <p class="font-medium">
            <?php if (!empty($row['token_number'])): ?>
            <span class="mr-2 rounded bg-emerald-100 px-2 py-0.5 font-mono text-xs text-emerald-800">#<?= (int) $row['token_number'] ?></span>
            <?php endif; ?>
            <?= htmlspecialchars($row['patient_name'] ?? '') ?>
        </p>
        <p class="text-xs text-slate-500"><?= htmlspecialchars($row['uhid'] ?? '') ?> · <?= htmlspecialchars($row['doctor_name'] ?? '') ?></p>
        <?php
        $fu = ($followUpFlags ?? [])[(int) ($row['patient_id'] ?? 0)] ?? null;
        if ($fu): ?>
        <p class="mt-0.5 text-xs <?= !empty($fu['overdue']) ? 'text-rose-700' : 'text-amber-700' ?>">
            ⏰ Follow-up
            <?= !empty($fu['overdue']) ? 'overdue' : 'due ' . htmlspecialchars(date('d M', strtotime($fu['due_date']))) ?>
            <?php if (!empty($fu['reason'])): ?>· <?= htmlspecialchars(str_replace('_', ' ', $fu['reason'])) ?><?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <span class="font-mono text-xs"><?= date('H:i', strtotime($row['scheduled_at'])) ?></span>
        <span class="rounded-full px-2 py-0.5 text-xs capitalize <?= $statusStyles[$row['status'] ?? ''] ?? 'bg-slate-100 text-slate-700' ?>">
            <?= htmlspecialchars(str_replace('_', ' ', $row['status'] ?? '')) ?>
        </span>
        <form method="post" action="/queue/<?= (int) $row['id'] ?>/status" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <input type="hidden" name="doctor_id" value="<?= (int) ($doctorId ?? 0) ?: '' ?>">
            <select name="status" class="rounded border px-2 py-1 text-sm" aria-label="Change status"
                    data-current="<?= htmlspecialchars($row['status'] ?? '') ?>"
                    onchange="if (['cancelled','no_show'].includes(this.value) && !confirm('Mark this appointment as ' + this.options[this.selectedIndex].text.toLowerCase() + '? The patient will leave the queue.')) { this.value = this.dataset.current; return; } this.classList.add('opacity-50','pointer-events-none'); this.form.submit();">
                <?php foreach (['scheduled','confirmed','in_progress','completed','no_show','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= ($row['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="/appointments/<?= (int) $row['id'] ?>/edit" class="text-xs text-slate-600 hover:underline">Edit</a>
        <?php if (in_array($row['status'] ?? '', ['scheduled', 'confirmed', 'in_progress'], true)): ?>
        <a href="/visits/new?appointment_id=<?= (int) $row['id'] ?>" class="rounded-lg bg-brand px-2 py-1 text-xs font-medium text-white hover:opacity-90">Start consultation</a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
