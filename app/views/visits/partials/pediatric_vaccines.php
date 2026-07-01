<?php
/** @var bool $showPediatricVaccines */
/** @var list<array{key: string, age: string, vaccine: string}> $pediatricVaccineSchedule */
?>
<?php if (!empty($showPediatricVaccines)): ?>
<details class="rounded-lg border border-slate-200 bg-slate-50/50" open>
    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">
        Immunization checklist
        <span class="ml-1 font-normal text-slate-500">(IAP schedule)</span>
    </summary>
    <div class="border-t border-slate-100 px-4 pb-4 pt-3">
        <p class="mb-3 text-xs text-slate-500">
            Tick vaccines discussed, due, or administered during this visit.
        </p>
        <div class="max-h-80 space-y-1 overflow-y-auto pr-1">
            <?php foreach ($pediatricVaccineSchedule as $row): ?>
                <label class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-white">
                    <input type="checkbox"
                           class="mt-0.5 rounded border-slate-300 text-brand focus:ring-brand"
                           :disabled="!editable"
                           :checked="pediatricVaccines.includes(<?= json_encode($row['key'], JSON_THROW_ON_ERROR) ?>)"
                           @change="togglePediatricVaccine(<?= json_encode($row['key'], JSON_THROW_ON_ERROR) ?>, $event.target.checked)">
                    <span class="min-w-0 flex-1">
                        <span class="font-medium text-slate-800"><?= htmlspecialchars($row['vaccine']) ?></span>
                        <span class="text-slate-500"> · <?= htmlspecialchars($row['age']) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</details>
<?php endif; ?>
