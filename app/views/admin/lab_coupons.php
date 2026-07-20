<?php /** /admin/lab/coupons — login-bonus discount coupons (super-admin). */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab Coupons — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100" x-data="{ editing: null }">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Discount Coupons</h1>
            <span class="text-sm text-slate-500"><?= count($coupons) ?> total</span>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            The <code>lab_coupons</code> table doesn't exist yet. Run the lab catalog SQL patches first.
        </div>
        <?php endif; ?>

        <p class="text-sm text-slate-500">
            The extra discount a <strong>logged-in patient</strong> gets on top of the offer price.
            The actual discount applied is <code>LEAST(coupon %, product's coupon cap %)</code>, so no
            product is ever discounted past the cap you set on it.
        </p>

        <!-- Add / edit -->
        <form method="post" action="/admin/lab/coupons" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="id" :value="editing ? editing.id : ''">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold" x-text="editing ? ('Edit: ' + editing.code) : 'Add a coupon'"></h2>
                <button type="button" x-show="editing" @click="editing = null" class="text-xs text-slate-500 hover:underline">Cancel edit</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-slate-600">Code <span class="text-slate-400">(new only)</span></span>
                    <input type="text" name="code" :value="editing ? editing.code : ''" :readonly="!!editing" placeholder="WELCOME10"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm uppercase read-only:bg-slate-100">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Discount %</span>
                    <input type="number" min="1" max="100" name="discount_pct" :value="editing ? editing.discount_pct : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm sm:col-span-1">
                    <span class="text-slate-600">Description</span>
                    <input type="text" name="description" :value="editing ? editing.description : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Starts</span>
                    <input type="date" name="starts_at" :value="editing ? editing.starts_at : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Ends</span>
                    <input type="date" name="ends_at" :value="editing ? editing.ends_at : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="requires_login" value="1" :checked="editing ? editing.requires_login == 1 : true"> Requires login
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" :checked="editing ? editing.is_active == 1 : true"> Active
                    </label>
                </div>
            </div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                <span x-text="editing ? 'Update coupon' : 'Add coupon'"></span>
            </button>
        </form>

        <!-- List -->
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2">Code</th><th class="px-4 py-2 text-center">%</th><th class="px-4 py-2">Description</th><th class="px-4 py-2">Login</th><th class="px-4 py-2">Window</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($coupons as $c): ?>
                    <tr class="<?= empty($c['is_active']) ? 'opacity-50' : '' ?>">
                        <td class="px-4 py-2 font-mono font-medium"><?= htmlspecialchars($c['code']) ?></td>
                        <td class="px-4 py-2 text-center font-semibold"><?= (int) $c['discount_pct'] ?>%</td>
                        <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($c['description'] ?? '') ?></td>
                        <td class="px-4 py-2"><?= !empty($c['requires_login']) ? '✓' : '—' ?></td>
                        <td class="px-4 py-2 text-slate-500 text-xs"><?= htmlspecialchars(($c['starts_at'] ?? '—') . ' → ' . ($c['ends_at'] ?? '—')) ?></td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= !empty($c['is_active']) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                <?= !empty($c['is_active']) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button type="button" @click='editing = <?= htmlspecialchars(json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>; window.scrollTo({top:0,behavior:"smooth"})' class="text-sky-700 hover:underline">Edit</button>
                            <form method="post" action="/admin/lab/coupons/<?= (int) $c['id'] ?>/toggle" class="ml-3 inline">
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
