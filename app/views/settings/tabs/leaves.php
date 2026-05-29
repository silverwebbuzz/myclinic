<?php
$leaveMap = [];
foreach ($leaves as $lv) {
    $leaveMap[$lv['leave_date']][] = $lv;
}
?>
<div class="ui-card ui-card-pad space-y-4">
    <div>
        <h2 class="ui-section-title">Doctor leaves</h2>
        <p class="ui-section-sub mt-0.5">Mark full or half-day leave. Conflicting appointments are warned before save.</p>
    </div>

    <?php if (!empty($warning)): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"><?= htmlspecialchars($warning) ?></p>
    <?php endif; ?>

    <!-- Doctor selector -->
    <form method="get" action="/settings" class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="tab" value="leaves">
        <label class="block">
            <span class="ui-label mb-1 block">Doctor</span>
            <select name="doctor_id" class="ui-input" onchange="this.form.submit()">
                <?php foreach ($doctors as $doc): ?>
                <option value="<?= (int) $doc['id'] ?>" <?= (int) ($doctorId ?? 0) === (int) $doc['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($doc['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <!-- Add leave (datepicker — no full calendar) -->
    <form method="post" action="/settings/leaves" class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="doctor_id" value="<?= (int) ($doctorId ?? 0) ?>">
        <label class="block">
            <span class="ui-label mb-1 block">Date</span>
            <input type="date" name="leave_date" required class="ui-input" min="<?= date('Y-m-d') ?>">
        </label>
        <label class="block">
            <span class="ui-label mb-1 block">Session</span>
            <select name="session" class="ui-input">
                <option value="full">Full day</option>
                <option value="morning">Morning</option>
                <option value="evening">Evening</option>
            </select>
        </label>
        <label class="block sm:col-span-2">
            <span class="ui-label mb-1 block">Reason (optional)</span>
            <input type="text" name="reason" class="ui-input" placeholder="e.g. Conference, personal">
        </label>
        <div class="sm:col-span-2">
            <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm">Add leave</button>
        </div>
    </form>

    <!-- Upcoming / existing leaves -->
    <?php if ($leaves !== []): ?>
    <div class="border-t border-slate-100 pt-4">
        <h3 class="ui-group-label mb-2">Scheduled leaves</h3>
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 text-sm">
            <?php foreach ($leaves as $lv): ?>
            <li class="flex items-center justify-between px-3 py-2">
                <span class="text-slate-700"><?= htmlspecialchars(date('d M Y', strtotime((string) $lv['leave_date']))) ?>
                    <span class="ml-1 capitalize text-slate-400">· <?= htmlspecialchars($lv['session']) ?></span></span>
                <form method="post" action="/settings/leaves/<?= (int) $lv['id'] ?>/remove?doctor_id=<?= (int) ($doctorId ?? 0) ?>" onsubmit="return confirm('Remove this leave?')">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Remove</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
    <p class="border-t border-slate-100 pt-4 text-sm text-slate-400">No leaves scheduled for this doctor.</p>
    <?php endif; ?>
</div>
