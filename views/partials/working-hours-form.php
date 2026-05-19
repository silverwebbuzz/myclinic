<?php
$days = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
$workingHours = $workingHours ?? [];
?>
<div class="space-y-3">
    <?php foreach ($days as $key => $label):
        $day = $workingHours[$key] ?? ['enabled' => false, 'sessions' => []];
        $sessions = $day['sessions'] ?? [];
        $morning = $sessions[0] ?? [];
        $evening = $sessions[1] ?? [];
    ?>
    <div class="rounded-lg border border-slate-200 p-3">
        <label class="flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="<?= $key ?>_enabled" value="1" <?= !empty($day['enabled']) ? 'checked' : '' ?>>
            <?= htmlspecialchars($label) ?>
        </label>
        <div class="mt-2 grid gap-2 sm:grid-cols-2 text-xs">
            <div>
                <span class="text-slate-500">Morning</span>
                <div class="mt-1 flex gap-1">
                    <input type="time" name="<?= $key ?>_morning_start" value="<?= htmlspecialchars(substr($morning['start'] ?? '09:00', 0, 5)) ?>" class="rounded border px-2 py-1">
                    <input type="time" name="<?= $key ?>_morning_end" value="<?= htmlspecialchars(substr($morning['end'] ?? '13:00', 0, 5)) ?>" class="rounded border px-2 py-1">
                </div>
            </div>
            <div>
                <span class="text-slate-500">Evening</span>
                <div class="mt-1 flex gap-1">
                    <input type="time" name="<?= $key ?>_evening_start" value="<?= htmlspecialchars(substr($evening['start'] ?? '16:00', 0, 5)) ?>" class="rounded border px-2 py-1">
                    <input type="time" name="<?= $key ?>_evening_end" value="<?= htmlspecialchars(substr($evening['end'] ?? '20:00', 0, 5)) ?>" class="rounded border px-2 py-1">
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
