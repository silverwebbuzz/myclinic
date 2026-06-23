<?php
$weekdaySource = $workingHours['mon'] ?? ['enabled' => false, 'sessions' => []];
$weekdaySessions = is_array($weekdaySource['sessions'] ?? null) ? $weekdaySource['sessions'] : [];
$morning = is_array($weekdaySessions[0] ?? null) ? $weekdaySessions[0] : [];
$evening = is_array($weekdaySessions[1] ?? null) ? $weekdaySessions[1] : [];
$morningEnabled = !empty($morning['start']) && !empty($morning['end']);
$eveningEnabled = !empty($evening['start']) && !empty($evening['end']);
$sundayDay = $workingHours['sun'] ?? ['enabled' => false, 'sessions' => []];
$sundaySession = is_array($sundayDay['sessions'][0] ?? null) ? $sundayDay['sessions'][0] : [];
$timeField = static function (string $name, string $label, string $value): string {
    return '<label class="flex items-center gap-2">'
        . '<span class="ui-label w-10 shrink-0">' . htmlspecialchars($label) . '</span>'
        . '<input type="time" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars(substr($value, 0, 5)) . '" class="ui-input w-32"></label>';
};
$messages = [
    'hours_saved' => 'Consultation hours saved.',
    'leave_saved' => 'Leave added.',
    'leave_removed' => 'Leave removed.',
];
?>
<?= ui_page_header('My schedule', 'Set your consultation hours and mark leave days. Slot length: ' . (int) ($slotDuration ?? 15) . ' min (clinic default).') ?>

<div class="mx-auto max-w-3xl space-y-4">
    <?php if (!empty($message) && isset($messages[$message])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($messages[$message]) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if (!empty($warning)): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"><?= htmlspecialchars($warning) ?></p>
    <?php endif; ?>

    <form method="post" action="/doctor/schedule/hours" class="ui-card ui-card-pad space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <h3 class="ui-section-title">Consultation hours</h3>
        <p class="text-xs text-slate-500">These timings control your available appointment slots only — not other doctors.</p>

        <div class="space-y-3">
            <div class="rounded-lg border border-slate-200 p-3">
                <?= ui_toggle('weekday_morning_enabled', '1', $morningEnabled, ['label' => 'Morning session (Mon–Sat)']) ?>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                    <?= $timeField('weekday_morning_start', 'Start', (string) ($morning['start'] ?? '09:30')) ?>
                    <?= $timeField('weekday_morning_end', 'End', (string) ($morning['end'] ?? '13:00')) ?>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <?= ui_toggle('weekday_evening_enabled', '1', $eveningEnabled, ['label' => 'Evening session (Mon–Sat)']) ?>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                    <?= $timeField('weekday_evening_start', 'Start', (string) ($evening['start'] ?? '16:30')) ?>
                    <?= $timeField('weekday_evening_end', 'End', (string) ($evening['end'] ?? '20:30')) ?>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <?= ui_toggle('sunday_open', '1', !empty($sundayDay['enabled']), ['label' => 'Open on Sunday']) ?>
                <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                    <?= $timeField('sunday_start', 'Start', (string) ($sundaySession['start'] ?? '10:00')) ?>
                    <?= $timeField('sunday_end', 'End', (string) ($sundaySession['end'] ?? '13:00')) ?>
                </div>
            </div>
        </div>
        <button type="submit" class="ui-btn ui-btn-primary">Save hours</button>
    </form>

    <div class="ui-card ui-card-pad space-y-4">
        <h3 class="ui-section-title">Leave days</h3>
        <form method="post" action="/doctor/schedule/leaves" class="grid gap-3 sm:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
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
                <input type="text" name="reason" class="ui-input" placeholder="e.g. Conference">
            </label>
            <div class="sm:col-span-2">
                <button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm">Add leave</button>
            </div>
        </form>

        <?php if (!empty($leaves)): ?>
        <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
            <?php foreach ($leaves as $lv): ?>
            <li class="flex items-center justify-between px-3 py-2">
                <span><?= htmlspecialchars(date('d M Y', strtotime((string) $lv['leave_date']))) ?>
                    <span class="capitalize text-slate-400">· <?= htmlspecialchars($lv['session']) ?></span></span>
                <form method="post" action="/doctor/schedule/leaves/<?= (int) $lv['id'] ?>/remove" onsubmit="return confirm('Remove this leave?')">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Remove</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-sm text-slate-400">No leave days scheduled.</p>
        <?php endif; ?>
    </div>
</div>
