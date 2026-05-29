<?php
$specialty = $clinic['specialty'] ?? 'gp';
$options = $options ?? [];

// Group the catalog by its 'group' key, preserving config order.
$grouped = [];
foreach ($specialties as $key => $spec) {
    $grouped[$spec['group'] ?? 'Other'][$key] = $spec;
}
$currentLabel = $specialties[$specialty]['label'] ?? ucfirst((string) $specialty);
?>
<div class="ui-card ui-card-pad" x-data="{ specialtyModal: false }">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="ui-section-title">Specialty</h2>
            <p class="ui-section-sub mt-0.5">Current: <span class="font-medium text-slate-700"><?= htmlspecialchars($currentLabel) ?></span></p>
        </div>
        <button type="button" @click="specialtyModal = true" class="ui-btn ui-btn-secondary ui-btn-sm">Change specialty</button>
    </div>

    <!-- Slot duration + ICD stays inline (lightweight, frequently tuned) -->
    <form method="post" action="/settings/specialty" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div>
            <label class="ui-label mb-1 block">Slot duration (minutes)</label>
            <select name="slot_duration" class="ui-input">
                <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?>
                <option value="<?= $m ?>" <?= (int)($options['slot_duration'] ?? 15) === $m ? 'selected' : '' ?>><?= $m ?> min</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($specialty === 'gp'): ?>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input class="ui-checkbox" type="checkbox" name="icd10_enabled" value="1" <?= !empty($options['icd10_enabled']) ? 'checked' : '' ?>>
            Enable ICD-10 codes
        </label>
        <?php endif; ?>
        <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm">Save</button>
    </form>

    <!-- Change-specialty modal -->
    <div x-show="specialtyModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="specialtyModal = false">
        <div class="w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-xl" @click.outside="specialtyModal = false">
            <div class="ui-card-header">
                <div>
                    <h3 class="ui-section-title">Change specialty</h3>
                    <p class="ui-section-sub mt-0.5">This resets specialty-specific patient fields.</p>
                </div>
                <button type="button" @click="specialtyModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100"><?= ui_icon('plus', 18, 'rotate-45') ?></button>
            </div>
            <form method="post" action="/settings/specialty">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="change_specialty" value="1">
                <div class="max-h-[60vh] space-y-4 overflow-y-auto px-4 py-4">
                    <?php foreach ($grouped as $groupLabel => $items): ?>
                    <div>
                        <h4 class="ui-group-label mb-2"><?= htmlspecialchars($groupLabel) ?></h4>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <?php foreach ($items as $key => $spec): ?>
                            <label class="flex cursor-pointer items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-center text-sm text-slate-700 transition hover:border-slate-300 has-[:checked]:border-brand has-[:checked]:bg-brand-light has-[:checked]:font-medium has-[:checked]:text-brand">
                                <input type="radio" name="specialty" value="<?= htmlspecialchars($key) ?>" class="sr-only" <?= $specialty === $key ? 'checked' : '' ?>>
                                <?= htmlspecialchars($spec['label']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
                    <button type="button" @click="specialtyModal = false" class="ui-btn ui-btn-secondary ui-btn-sm">Cancel</button>
                    <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm">Save specialty</button>
                </div>
            </form>
        </div>
    </div>
</div>
