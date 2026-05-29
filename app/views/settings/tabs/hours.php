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

$timeField = static function (string $name, string $label, string $value): string {
    return '<label class="block"><span class="ui-label mb-1 block">' . htmlspecialchars($label) . '</span>'
        . '<input type="time" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars(substr($value, 0, 5)) . '" class="ui-input"></label>';
};
?>
<form method="post" action="/settings/hours" class="ui-card">
    <div class="ui-card-header">
        <div>
            <h2 class="ui-section-title">Working hours</h2>
            <p class="ui-section-sub mt-0.5">Set consultation sessions, slot length and how far ahead patients can book.</p>
        </div>
    </div>
    <div class="ui-card-pad">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Slot duration -->
        <div class="ui-section">
            <h3 class="ui-label">Slot duration</h3>
            <p class="ui-help mt-0.5">Each visible booking slot will be this long.</p>
            <div class="mt-3 flex flex-wrap gap-5 text-sm">
                <?php foreach ([15 => '15 minutes', 30 => '30 minutes'] as $v => $l): ?>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" class="ui-radio" name="slot_duration_min" value="<?= $v ?>" <?= $slotDuration === $v ? 'checked' : '' ?>>
                    <span class="text-slate-700"><?= $l ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Mon–Sat -->
        <div class="ui-section">
            <h3 class="ui-label">Monday – Saturday sessions</h3>
            <div class="mt-3 space-y-5">
                <div class="rounded-xl border border-slate-200 p-4">
                    <?= ui_toggle('weekday_morning_enabled', '1', $morningEnabled, ['label' => 'Morning session', 'sub' => 'Daytime consultation hours']) ?>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <?= $timeField('weekday_morning_start', 'Start', (string) ($morning['start'] ?? '09:30')) ?>
                        <?= $timeField('weekday_morning_end', 'End', (string) ($morning['end'] ?? '13:00')) ?>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 p-4">
                    <?= ui_toggle('weekday_evening_enabled', '1', $eveningEnabled, ['label' => 'Evening session', 'sub' => 'Evening consultation hours']) ?>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <?= $timeField('weekday_evening_start', 'Start', (string) ($evening['start'] ?? '16:30')) ?>
                        <?= $timeField('weekday_evening_end', 'End', (string) ($evening['end'] ?? '20:30')) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sunday -->
        <div class="ui-section">
            <h3 class="ui-label">Sunday</h3>
            <div class="mt-3 rounded-xl border border-slate-200 p-4">
                <?= ui_toggle('sunday_open', '1', !empty($sundayDay['enabled']), ['label' => 'Open on Sunday', 'sub' => 'Allow Sunday bookings']) ?>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <?= $timeField('sunday_start', 'Start', (string) ($sundaySession['start'] ?? '10:00')) ?>
                    <?= $timeField('sunday_end', 'End', (string) ($sundaySession['end'] ?? '13:00')) ?>
                </div>
            </div>
        </div>

        <!-- Extended hours -->
        <div class="ui-section">
            <h3 class="ui-label">Extended hours <span class="ml-1 text-xs font-normal text-amber-600">walk-in admin only</span></h3>
            <p class="ui-help mt-0.5">Visible only in the staff <a href="/appointments/new" class="text-brand hover:underline">walk-in form</a> — never on the public booking page.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <?= $timeField('weekday_morning_extended_end', 'Morning extended end', (string) ($morning['extended_end'] ?? '')) ?>
                <?= $timeField('weekday_evening_extended_end', 'Evening extended end', (string) ($evening['extended_end'] ?? '')) ?>
                <div class="sm:col-span-2"><?= $timeField('sunday_extended_end', 'Sunday extended end', (string) ($sundaySession['extended_end'] ?? '')) ?></div>
            </div>
        </div>

        <!-- Booking window -->
        <div class="ui-section">
            <h3 class="ui-label">Online booking window</h3>
            <p class="ui-help mt-0.5">How many days ahead can patients book through the public booking page?</p>
            <div class="mt-3 flex flex-wrap gap-5 text-sm">
                <?php foreach ([7, 15, 30, 60, 90] as $d): ?>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" class="ui-radio" name="booking_window_days" value="<?= $d ?>" <?= $bookingWindow === $d ? 'checked' : '' ?>>
                    <span class="text-slate-700"><?= $d ?> days</span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
            <p class="ui-help">Saving regenerates doctor schedule slots.</p>
            <button type="submit" class="ui-btn ui-btn-primary">Save hours</button>
        </div>
    </div>
</form>
