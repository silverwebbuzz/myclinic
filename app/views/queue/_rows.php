<?php if ($queue === []): ?>
<p class="p-6 text-center text-sm text-slate-500">No appointments scheduled for today.</p>
<?php else: ?>
<?php foreach ($queue as $row): ?>
<div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
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
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs capitalize"><?= htmlspecialchars(str_replace('_', ' ', $row['status'] ?? '')) ?></span>
        <form method="post" action="/queue/<?= (int) $row['id'] ?>/status" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <select name="status" onchange="this.form.submit()" class="rounded border px-2 py-1 text-xs">
                <?php foreach (['scheduled','confirmed','in_progress','completed','no_show','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= ($row['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="/appointments/<?= (int) $row['id'] ?>/edit" class="text-xs text-slate-600 hover:underline">Edit</a>
        <?php if (in_array($row['status'] ?? '', ['scheduled', 'confirmed', 'in_progress'], true)): ?>
        <a href="/visits/new?appointment_id=<?= (int) $row['id'] ?>" class="rounded-lg bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-700">Start consultation</a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
