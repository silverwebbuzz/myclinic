<?php
$weekdaySource = $workingHours['mon'] ?? ['enabled' => false, 'sessions' => []];
$weekdaySessions = is_array($weekdaySource['sessions'] ?? null) ? $weekdaySource['sessions'] : [];
$morning = is_array($weekdaySessions[0] ?? null) ? $weekdaySessions[0] : [];
$evening = is_array($weekdaySessions[1] ?? null) ? $weekdaySessions[1] : [];
$morningEnabled = !empty($morning['start']) && !empty($morning['end']);
$eveningEnabled = !empty($evening['start']) && !empty($evening['end']);

$sundayDay = $workingHours['sun'] ?? ['enabled' => false, 'sessions' => []];
$sundaySession = is_array($sundayDay['sessions'][0] ?? null) ? $sundayDay['sessions'][0] : [];

$slotDuration = (int) ($config['slot_duration_min'] ?? 15);
$bookingWindow = (int) ($config['booking_window_days'] ?? 30);
?>
<form method="post" action="/settings/hours" class="space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div class="ui-card p-5">
        <h3 class="flex items-center gap-2 text-base font-semibold">⏱️ Slot duration</h3>
        <p class="mt-1 text-xs text-slate-500">Each visible booking slot will be this long.</p>
        <div class="mt-3 flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2">
                <input type="radio" name="slot_duration_min" value="15" <?= $slotDuration === 15 ? 'checked' : '' ?>>
                15 minutes
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" name="slot_duration_min" value="30" <?= $slotDuration === 30 ? 'checked' : '' ?>>
                30 minutes
            </label>
        </div>
    </div>

    <div class="ui-card p-5">
        <h3 class="flex items-center gap-2 ui-section-title"><span class="text-brand"><?= ui_icon('appointments', 17) ?></span>Monday – Saturday sessions</h3>
        <div class="mt-3 space-y-4">
            <div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="weekday_morning_enabled" value="1" <?= $morningEnabled ? 'checked' : '' ?>>
                    Morning session
                </label>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="text-slate-500">Start</span>
                        <input type="time" name="weekday_morning_start"
                               value="<?= htmlspecialchars(substr((string) ($morning['start'] ?? '09:30'), 0, 5)) ?>"
                               class="ui-input">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-500">End</span>
                        <input type="time" name="weekday_morning_end"
                               value="<?= htmlspecialchars(substr((string) ($morning['end'] ?? '13:00'), 0, 5)) ?>"
                               class="ui-input">
                    </label>
                </div>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="weekday_evening_enabled" value="1" <?= $eveningEnabled ? 'checked' : '' ?>>
                    Evening session
                </label>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="text-slate-500">Start</span>
                        <input type="time" name="weekday_evening_start"
                               value="<?= htmlspecialchars(substr((string) ($evening['start'] ?? '16:30'), 0, 5)) ?>"
                               class="ui-input">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-500">End</span>
                        <input type="time" name="weekday_evening_end"
                               value="<?= htmlspecialchars(substr((string) ($evening['end'] ?? '20:30'), 0, 5)) ?>"
                               class="ui-input">
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-card p-5">
        <h3 class="flex items-center gap-2 ui-section-title"><span class="text-brand"><?= ui_icon('scheduling', 17) ?></span>Sunday</h3>
        <label class="mt-2 flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="sunday_open" value="1" <?= !empty($sundayDay['enabled']) ? 'checked' : '' ?>>
            Open on Sunday
        </label>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-500">Start</span>
                <input type="time" name="sunday_start"
                       value="<?= htmlspecialchars(substr((string) ($sundaySession['start'] ?? '10:00'), 0, 5)) ?>"
                       class="ui-input">
            </label>
            <label class="block text-sm">
                <span class="text-slate-500">End</span>
                <input type="time" name="sunday_end"
                       value="<?= htmlspecialchars(substr((string) ($sundaySession['end'] ?? '13:00'), 0, 5)) ?>"
                       class="ui-input">
            </label>
        </div>
    </div>

    <div class="ui-card p-5">
        <h3 class="flex items-center gap-2 ui-section-title text-red-700"><?= ui_icon('bell', 17) ?>Extended hours (walk-in admin only)</h3>
        <p class="mt-1 text-xs text-slate-500">
            These extended end times are only visible in the staff <a href="/appointments/new" class="underline">walk-in form</a> — never on the public booking page. Useful when the doctor occasionally sees patients beyond normal hours.
        </p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">Morning extended end <span class="text-xs text-slate-400">(after normal morning end)</span></span>
                <input type="time" name="weekday_morning_extended_end"
                       value="<?= htmlspecialchars(substr((string) ($morning['extended_end'] ?? ''), 0, 5)) ?>"
                       class="ui-input">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Evening extended end <span class="text-xs text-slate-400">(after normal evening end)</span></span>
                <input type="time" name="weekday_evening_extended_end"
                       value="<?= htmlspecialchars(substr((string) ($evening['extended_end'] ?? ''), 0, 5)) ?>"
                       class="ui-input">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">Sunday extended end <span class="text-xs text-slate-400">(after normal Sunday end)</span></span>
                <input type="time" name="sunday_extended_end"
                       value="<?= htmlspecialchars(substr((string) ($sundaySession['extended_end'] ?? ''), 0, 5)) ?>"
                       class="ui-input">
            </label>
        </div>
    </div>

    <div class="ui-card p-5">
        <h3 class="flex items-center gap-2 ui-section-title"><span class="text-brand"><?= ui_icon('scheduling', 17) ?></span>Online booking window</h3>
        <p class="mt-1 text-xs text-slate-500">How many days ahead can patients book through the public booking page?</p>
        <div class="mt-3 flex flex-wrap gap-4 text-sm">
            <?php foreach ([7, 15, 30, 60, 90] as $d): ?>
            <label class="flex items-center gap-2">
                <input type="radio" name="booking_window_days" value="<?= $d ?>" <?= $bookingWindow === $d ? 'checked' : '' ?>>
                <?= $d ?> days
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="ui-btn ui-btn-primary">
            Save hours
        </button>
        <p class="text-xs text-slate-500 self-center">Saving regenerates doctor schedule slots.</p>
    </div>
</form>
