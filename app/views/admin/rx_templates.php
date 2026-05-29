<?php
/** /admin/rx-templates — master prescription template manager (super-admin). */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rx Templates — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6 space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Master prescription templates</h1>
                <p class="text-sm text-slate-500">System starter packs shown to doctors per specialty. Inactive ones are hidden from doctors.</p>
            </div>
            <a href="/admin/rx-templates/new" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">+ New template</a>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <!-- Specialty filter -->
        <form method="get" class="flex items-center gap-2">
            <label class="text-sm text-slate-600">Specialty:</label>
            <select name="specialty" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">All</option>
                <?php foreach ($specialties as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= $filterSpecialty === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="text-sm text-slate-400"><?= count($templates) ?> templates</span>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Specialty</th>
                        <th class="px-4 py-3">Template</th>
                        <th class="px-4 py-3">Mode</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($templates as $t): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars($t['specialty']) ?></td>
                        <td class="px-4 py-3">
                            <a href="/admin/rx-templates/<?= (int) $t['id'] ?>" class="font-medium text-slate-900 hover:underline"><?= htmlspecialchars($t['name']) ?></a>
                            <?php if (!empty($t['description'])): ?><p class="text-xs text-slate-400"><?= htmlspecialchars($t['description']) ?></p><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 capitalize text-slate-600"><?= htmlspecialchars($t['mode']) ?></td>
                        <td class="px-4 py-3 text-center text-slate-600"><?= (int) $t['item_count'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ((int) $t['is_active'] === 1): ?>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                            <?php else: ?>
                            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Inactive — review</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="/admin/rx-templates/<?= (int) $t['id'] ?>" class="text-xs font-medium text-slate-600 hover:underline">Edit</a>
                                <form method="post" action="/admin/rx-templates/<?= (int) $t['id'] ?>/toggle">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <button type="submit" class="text-xs font-medium text-emerald-700 hover:underline"><?= (int) $t['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <form method="post" action="/admin/rx-templates/<?= (int) $t['id'] ?>/delete" onsubmit="return confirm('Delete this template?')">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($templates === []): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No templates<?= $filterSpecialty ? ' for this specialty' : '' ?>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
