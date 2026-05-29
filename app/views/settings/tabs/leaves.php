<?php
$monthStart = $leaveMonth . '-01';
$daysInMonth = (int) date('t', strtotime($monthStart));
$leaveMap = [];
foreach ($leaves as $lv) {
    $leaveMap[$lv['leave_date']][] = $lv;
}
?>
<div class="space-y-6 ui-card ui-card-pad">
    <h2 class="ui-section-title">Doctor leaves</h2>
    <p class="text-sm text-slate-500">Mark full or half-day leave. Conflicting appointments are warned before save.</p>

    <?php if (!empty($warning)): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"><?= htmlspecialchars($warning) ?></p>
    <?php endif; ?>

    <form method="get" action="/settings" class="flex flex-wrap gap-3">
        <input type="hidden" name="tab" value="leaves">
        <select name="doctor_id" class="ui-input" onchange="this.form.submit()">
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= (int) $doc['id'] ?>" <?= (int) ($doctorId ?? 0) === (int) $doc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($doc['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="month" name="month" value="<?= htmlspecialchars($leaveMonth) ?>" class="ui-input">
        <button type="submit" class="ui-input">Go</button>
    </form>

    <div class="grid grid-cols-7 gap-1 text-center text-xs">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
        <div class="py-1 font-semibold text-slate-500"><?= $d ?></div>
        <?php endforeach; ?>
        <?php
        $firstDow = (int) date('w', strtotime($monthStart));
        for ($i = 0; $i < $firstDow; $i++) {
            echo '<div></div>';
        }
        for ($day = 1; $day <= $daysInMonth; $day++):
            $date = sprintf('%s-%02d', $leaveMonth, $day);
            $dayLeaves = $leaveMap[$date] ?? [];
        ?>
        <div class="min-h-[4rem] rounded border p-1 text-left <?= $dayLeaves ? 'bg-amber-50 border-amber-200' : 'bg-slate-50' ?>">
            <span class="font-medium"><?= $day ?></span>
            <?php foreach ($dayLeaves as $lv): ?>
            <p class="mt-0.5 truncate text-[10px] capitalize text-amber-800"><?= htmlspecialchars($lv['session']) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endfor; ?>
    </div>

    <form method="post" action="/settings/leaves" class="grid gap-3 border-t pt-4 sm:grid-cols-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="doctor_id" value="<?= (int) ($doctorId ?? 0) ?>">
        <label class="text-sm">
            <span class="text-slate-600">Date</span>
            <input type="date" name="leave_date" required class="ui-input">
        </label>
        <label class="text-sm">
            <span class="text-slate-600">Session</span>
            <select name="session" class="ui-input">
                <option value="full">Full day</option>
                <option value="morning">Morning</option>
                <option value="evening">Evening</option>
            </select>
        </label>
        <label class="text-sm sm:col-span-2">
            <span class="text-slate-600">Reason (optional)</span>
            <input type="text" name="reason" class="ui-input">
        </label>
        <button type="submit" class="ui-btn ui-btn-primary sm:col-span-2">Add leave</button>
    </form>

    <?php if ($leaves !== []): ?>
    <ul class="divide-y border-t text-sm">
        <?php foreach ($leaves as $lv): ?>
        <li class="flex items-center justify-between py-2">
            <span><?= htmlspecialchars($lv['leave_date']) ?> — <span class="capitalize"><?= htmlspecialchars($lv['session']) ?></span></span>
            <form method="post" action="/settings/leaves/<?= (int) $lv['id'] ?>/remove?doctor_id=<?= (int) ($doctorId ?? 0) ?>" onsubmit="return confirm('Remove this leave?')">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="text-red-600 hover:underline">Remove</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
