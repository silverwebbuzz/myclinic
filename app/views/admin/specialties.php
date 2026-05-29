<?php
/** /admin/specialties — specialty catalog manager (super-admin). */
$modes = ['allopathic', 'homeopathic', 'dental', 'both'];
$categories = [
    'General & specialists', 'Surgeons & critical care', 'Dental',
    'Women, child, eye & ENT', 'AYUSH & alternative', 'Therapy & nutrition',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Specialties — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100" x-data="{ editing: null }">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Specialties</h1>
            <span class="text-sm text-slate-500"><?= count($specialties) ?> total</span>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            The <code>specialty_master</code> table doesn't exist yet. Run
            <code>document/seeds/specialty_master.sql</code> first.
        </div>
        <?php endif; ?>

        <p class="text-sm text-slate-500">
            The single source of truth for specialties across the doctor portal, the public
            directory, and SEO pages. Specialties marked <strong>Custom form</strong> have
            bespoke clinical screens in code (homeopathy, dental, etc.) — toggling that flag
            without matching code only affects the label.
        </p>

        <!-- ===== Add / Edit form ===== -->
        <form method="post" action="/admin/specialties"
              class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="id" :value="editing ? editing.id : ''">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold" x-text="editing ? ('Edit: ' + editing.label) : 'Add a specialty'"></h2>
                <button type="button" x-show="editing" @click="editing = null"
                        class="text-xs text-slate-500 hover:underline">Cancel edit</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-slate-600">Slug <span class="text-slate-400">(immutable on edit)</span></span>
                    <input type="text" name="slug" :value="editing ? editing.slug : ''" :readonly="!!editing"
                           placeholder="cardiologist"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm read-only:bg-slate-100">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Label</span>
                    <input type="text" name="label" :value="editing ? editing.label : ''"
                           placeholder="Cardiologist"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Plural label</span>
                    <input type="text" name="plural_label" :value="editing ? editing.plural_label : ''"
                           placeholder="Cardiologists"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Category</span>
                    <select name="category" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                            :value="editing ? editing.category : ''">
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Icon (emoji)</span>
                    <input type="text" name="icon" :value="editing ? editing.icon : ''" maxlength="8"
                           placeholder="❤️" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Prescription mode</span>
                    <select name="prescription_mode" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                        <?php foreach ($modes as $m): ?>
                        <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Sort order</span>
                    <input type="number" name="sort_order" :value="editing ? editing.sort_order : 100"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" :checked="editing ? editing.is_active == 1 : true">
                    Active
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="seo_safe" value="1" :checked="editing ? editing.seo_safe == 1 : true">
                    Show on marketing/SEO
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="has_custom_form" value="1" :checked="editing ? editing.has_custom_form == 1 : false">
                    Has custom clinical form (code)
                </label>
            </div>
            <button type="submit" class="ui-btn ui-btn-primary">
                <span x-text="editing ? 'Update specialty' : 'Add specialty'"></span>
            </button>
        </form>

        <!-- ===== List ===== -->
        <div class="overflow-x-auto ui-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Specialty</th>
                        <th class="px-4 py-2">Slug</th>
                        <th class="px-4 py-2">Category</th>
                        <th class="px-4 py-2">Mode</th>
                        <th class="px-4 py-2">Custom</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($specialties as $s): ?>
                    <tr class="<?= empty($s['is_active']) ? 'opacity-50' : '' ?>">
                        <td class="px-4 py-2 font-medium"><?= htmlspecialchars($s['icon'] ?? '') ?> <?= htmlspecialchars($s['label']) ?></td>
                        <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($s['slug']) ?></td>
                        <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($s['category'] ?? '') ?></td>
                        <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($s['prescription_mode'] ?? '') ?></td>
                        <td class="px-4 py-2"><?= !empty($s['has_custom_form']) ? '✓' : '—' ?></td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= !empty($s['is_active']) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                <?= !empty($s['is_active']) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button type="button"
                                    @click='editing = <?= htmlspecialchars(json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>; window.scrollTo({top:0,behavior:"smooth"})'
                                    class="text-emerald-700 hover:underline">Edit</button>
                            <form method="post" action="/admin/specialties/<?= (int) $s['id'] ?>/toggle" class="ml-3 inline">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <button type="submit" class="text-slate-500 hover:underline">
                                    <?= !empty($s['is_active']) ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</body>
</html>
