<?php /** /admin/lab/categories — lab category manager (super-admin). */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab Categories — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100" x-data="{ editing: null }">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Lab Categories</h1>
            <span class="text-sm text-slate-500"><?= count($categories) ?> total</span>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            The <code>lab_categories</code> table doesn't exist yet. Run the lab catalog SQL patches first.
        </div>
        <?php endif; ?>

        <p class="text-sm text-slate-500">Organ / disease groups used to browse and filter the lab storefront.</p>

        <!-- Add / edit -->
        <form method="post" action="/admin/lab/categories" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="id" :value="editing ? editing.id : ''">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold" x-text="editing ? ('Edit: ' + editing.name) : 'Add a category'"></h2>
                <button type="button" x-show="editing" @click="editing = null" class="text-xs text-slate-500 hover:underline">Cancel edit</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm sm:col-span-1">
                    <span class="text-slate-600">Name</span>
                    <input type="text" name="name" :value="editing ? editing.name : ''" placeholder="Cardiac Risk Markers"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Slug <span class="text-slate-400">(new only)</span></span>
                    <input type="text" name="slug" :value="editing ? editing.slug : ''" :readonly="!!editing" placeholder="cardiac-risk-markers"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm read-only:bg-slate-100">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Sort order</span>
                    <input type="number" name="sort_order" :value="editing ? editing.sort_order : 100"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" :checked="editing ? editing.is_active == 1 : true"> Active
                </label>
            </div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                <span x-text="editing ? 'Update category' : 'Add category'"></span>
            </button>
        </form>

        <!-- List -->
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2">Name</th><th class="px-4 py-2">Slug</th><th class="px-4 py-2 text-center">Products</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($categories as $c): ?>
                    <tr class="<?= empty($c['is_active']) ? 'opacity-50' : '' ?>">
                        <td class="px-4 py-2 font-medium"><?= htmlspecialchars($c['name']) ?></td>
                        <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($c['slug']) ?></td>
                        <td class="px-4 py-2 text-center text-slate-500"><?= (int) ($c['product_count'] ?? 0) ?></td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= !empty($c['is_active']) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                <?= !empty($c['is_active']) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button type="button" @click='editing = <?= htmlspecialchars(json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>; window.scrollTo({top:0,behavior:"smooth"})' class="text-sky-700 hover:underline">Edit</button>
                            <form method="post" action="/admin/lab/categories/<?= (int) $c['id'] ?>/toggle" class="ml-3 inline">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <button type="submit" class="text-slate-500 hover:underline"><?= !empty($c['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
