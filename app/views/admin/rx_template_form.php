<?php
/** /admin/rx-templates/new + /{id} — create/edit a master template. */
$isEdit = $template !== null;
$presets = ['', '1-0-0', '0-0-1', '1-0-1', '1-1-1', '1-1-1-1', '0-1-0', 'SOS'];
$units = ['', 'tablet', 'capsule', 'ml', 'drops', 'sachet', 'puff', 'unit'];
$foods = ['any', 'before', 'after', 'with', 'empty', 'bedtime'];

// Seed Alpine state with existing items (or one blank row).
$rowsJson = [];
foreach ($items as $it) {
    $rowsJson[] = [
        'drug_id' => (int) ($it['drug_id'] ?? 0),
        'name' => $it['drug_name'] ?? ($it['match_name'] ?? ''),
        'dose_unit' => $it['dose_unit'] ?? '',
        'dose_amount' => $it['dose_amount'] ?? '',
        'freq' => $it['frequency_preset'] ?? '',
        'days' => $it['duration_days'] ?? '',
        'food' => $it['food_timing'] ?? 'any',
        'instructions' => $it['instructions'] ?? '',
        'suggestions' => [],
    ];
}
if ($rowsJson === []) {
    $rowsJson[] = ['drug_id' => 0, 'name' => '', 'dose_unit' => '', 'dose_amount' => '', 'freq' => '', 'days' => '', 'food' => 'any', 'instructions' => '', 'suggestions' => []];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $isEdit ? 'Edit' : 'New' ?> Rx Template — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6"
          x-data="rxForm(<?= htmlspecialchars(json_encode($rowsJson), ENT_QUOTES) ?>)">

        <div class="mb-5 flex items-center justify-between">
            <h1 class="text-xl font-semibold"><?= $isEdit ? 'Edit' : 'New' ?> master template</h1>
            <a href="/admin/rx-templates" class="text-sm text-slate-500 hover:underline">← Back</a>
        </div>

        <form method="post" action="/admin/rx-templates/save" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $template['id'] ?>"><?php endif; ?>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-slate-600">Specialty</span>
                    <select name="specialty" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select…</option>
                        <?php foreach ($allSpecialties as $s): ?>
                        <option value="<?= htmlspecialchars($s['slug']) ?>" <?= ($isEdit && $template['specialty'] === $s['slug']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['label']) ?> (<?= htmlspecialchars($s['slug']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Mode</span>
                    <select name="mode" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="allopathic" <?= ($isEdit && $template['mode'] === 'allopathic') ? 'selected' : '' ?>>Allopathic</option>
                        <option value="homeopathic" <?= ($isEdit && $template['mode'] === 'homeopathic') ? 'selected' : '' ?>>Homeopathic</option>
                    </select>
                </label>
            </div>

            <label class="block text-sm">
                <span class="text-slate-600">Template name (condition)</span>
                <input type="text" name="name" required value="<?= $isEdit ? htmlspecialchars($template['name']) : '' ?>"
                       placeholder="e.g. Acute URI / Common Cold" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Description (optional)</span>
                <input type="text" name="description" value="<?= $isEdit ? htmlspecialchars((string) $template['description']) : '' ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" <?= (!$isEdit || (int) $template['is_active'] === 1) ? 'checked' : '' ?>>
                Active (visible to doctors)
            </label>

            <!-- Medicines -->
            <div class="border-t border-slate-100 pt-4">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Medicines</h2>
                    <button type="button" @click="addRow()" class="rounded-lg border border-slate-300 px-3 py-1 text-xs hover:bg-slate-50">+ Add medicine</button>
                </div>

                <div class="space-y-3">
                    <template x-for="(row, idx) in rows" :key="idx">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="grid items-end gap-2 sm:grid-cols-12">
                                <!-- Drug search -->
                                <div class="sm:col-span-5 relative">
                                    <label class="text-xs text-slate-500">Medicine</label>
                                    <input type="hidden" :name="'item_drug_id[]'" :value="row.drug_id">
                                    <input type="hidden" :name="'item_name[]'" :value="row.name">
                                    <input type="text" x-model="row.name"
                                           @input.debounce.250ms="search(idx)" @focus="search(idx)"
                                           placeholder="Type to search drug…"
                                           class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm"
                                           :class="row.drug_id ? 'border-emerald-400 bg-emerald-50' : ''">
                                    <ul x-show="row.suggestions.length" @click.outside="row.suggestions = []"
                                        class="absolute z-10 mt-1 w-full max-h-44 overflow-y-auto rounded-lg border bg-white shadow-lg">
                                        <template x-for="d in row.suggestions" :key="d.id">
                                            <li><button type="button" @click="pick(idx, d)"
                                                class="block w-full px-2 py-1 text-left text-xs hover:bg-emerald-50">
                                                <span x-text="d.name"></span>
                                                <span class="text-slate-400" x-show="d.strength" x-text="' ' + d.strength"></span>
                                            </button></li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-xs text-slate-500">Frequency</label>
                                    <select :name="'item_freq[]'" x-model="row.freq" class="mt-1 w-full rounded border border-slate-300 px-1 py-1 text-xs">
                                        <?php foreach ($presets as $f): ?><option value="<?= $f ?>"><?= $f === '' ? '—' : $f ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="text-xs text-slate-500">Days</label>
                                    <input type="number" min="1" :name="'item_days[]'" x-model="row.days" class="mt-1 w-full rounded border border-slate-300 px-1 py-1 text-xs">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-xs text-slate-500">Dose unit</label>
                                    <select :name="'item_dose_unit[]'" x-model="row.dose_unit" class="mt-1 w-full rounded border border-slate-300 px-1 py-1 text-xs">
                                        <?php foreach ($units as $u): ?><option value="<?= $u ?>"><?= $u === '' ? '—' : $u ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="text-xs text-slate-500">Amt</label>
                                    <input type="number" step="0.01" :name="'item_dose_amount[]'" x-model="row.dose_amount" class="mt-1 w-full rounded border border-slate-300 px-1 py-1 text-xs">
                                </div>
                                <div class="sm:col-span-1 flex justify-end">
                                    <button type="button" @click="rows.splice(idx,1)" class="text-rose-600 hover:underline text-sm" title="Remove">×</button>
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="text-xs text-slate-500">Food</label>
                                    <select :name="'item_food[]'" x-model="row.food" class="mt-1 w-full rounded border border-slate-300 px-1 py-1 text-xs">
                                        <?php foreach ($foods as $f): ?><option value="<?= $f ?>"><?= ucfirst($f) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sm:col-span-9">
                                    <label class="text-xs text-slate-500">Instructions (optional)</label>
                                    <input type="text" :name="'item_instructions[]'" x-model="row.instructions" class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                </div>
                            </div>
                            <p x-show="!row.drug_id && row.name" class="mt-1 text-xs text-amber-600">Pick a medicine from the dropdown so it links to the catalog.</p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex gap-2 border-t border-slate-100 pt-4">
                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-700">Save template</button>
                <a href="/admin/rx-templates" class="rounded-lg border border-slate-300 px-5 py-2 text-sm hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </main>

    <script>
    function rxForm(initialRows) {
        return {
            rows: initialRows,
            addRow() {
                this.rows.push({ drug_id: 0, name: '', dose_unit: '', dose_amount: '', freq: '', days: '', food: 'any', instructions: '', suggestions: [] });
            },
            async search(idx) {
                const row = this.rows[idx];
                row.drug_id = 0; // typing invalidates a previous pick
                const q = (row.name || '').trim();
                if (q.length < 2) { row.suggestions = []; return; }
                try {
                    const r = await fetch('/api/v1/drugs/search?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                    const data = await r.json();
                    row.suggestions = (data.drugs || []).slice(0, 8);
                } catch (e) { row.suggestions = []; }
            },
            pick(idx, d) {
                const row = this.rows[idx];
                row.drug_id = d.id;
                row.name = d.name + (d.strength ? ' ' + d.strength : '');
                row.suggestions = [];
            },
        };
    }
    </script>
</body>
</html>
