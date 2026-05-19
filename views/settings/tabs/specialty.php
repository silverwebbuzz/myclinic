<?php
$specialty = $clinic['specialty'] ?? 'gp';
$options = $options ?? [];
?>
<form method="post" action="/settings/specialty" class="space-y-6 rounded-xl border bg-white p-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <h2 class="text-lg font-semibold">Specialty settings</h2>

    <label class="flex items-center gap-2 text-sm text-amber-800">
        <input type="checkbox" name="change_specialty" value="1">
        Change specialty (resets specialty-specific patient fields)
    </label>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" x-data="{ change: false }">
        <?php foreach ($specialties as $key => $spec): ?>
        <label class="rounded-lg border p-2 text-center text-xs has-[:checked]:border-emerald-500">
            <input type="radio" name="specialty" value="<?= htmlspecialchars($key) ?>" class="sr-only" <?= $specialty === $key ? 'checked' : '' ?>>
            <?= $spec['icon'] ?> <?= htmlspecialchars($spec['label']) ?>
        </label>
        <?php endforeach; ?>
    </div>

    <div>
        <label class="text-xs font-medium">Slot duration (minutes)</label>
        <select name="slot_duration" class="mt-1 rounded-lg border px-3 py-2 text-sm">
            <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?>
            <option value="<?= $m ?>" <?= (int)($options['slot_duration'] ?? 15) === $m ? 'selected' : '' ?>><?= $m ?> min</option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($specialty === 'gp'): ?>
    <label class="flex gap-2 text-sm"><input type="checkbox" name="icd10_enabled" value="1" <?= !empty($options['icd10_enabled']) ? 'checked' : '' ?>> ICD-10 codes</label>
    <?php endif; ?>

    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Save specialty</button>
</form>
