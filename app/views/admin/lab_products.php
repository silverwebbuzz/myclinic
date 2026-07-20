<?php
/** /admin/lab/products — Thyrocare lab catalog list (super-admin). */
$typeBadge = static function (string $t): array {
    return match ($t) {
        'OFFER'   => ['Offer',   'bg-violet-100 text-violet-800'],
        'PROFILE' => ['Package', 'bg-sky-100 text-sky-800'],
        default   => ['Test',    'bg-slate-100 text-slate-700'],
    };
};
$qs = static function (array $over) use ($q, $type): string {
    return http_build_query(array_merge(['q' => $q, 'type' => $type], $over));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab Tests & Packages — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-7xl p-6 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Lab Tests &amp; Packages</h1>
                <p class="text-sm text-slate-500">Thyrocare catalog — <?= number_format($total) ?> items</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            The lab tables don't exist yet. Run
            <code>app/database/patches/2026_07_20_lab_catalog.sql</code> then
            <code>…_lab_catalog_seed.sql</code>.
        </div>
        <?php endif; ?>

        <!-- Search + type filter -->
        <form method="get" action="/admin/lab/products" class="flex flex-wrap items-end gap-3">
            <label class="block text-sm">
                <span class="text-slate-600">Search</span>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="name, code or PROJ… test code"
                       class="mt-1 w-72 rounded border px-3 py-1.5 text-sm">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Type</span>
                <select name="type" class="mt-1 rounded border px-3 py-1.5 text-sm">
                    <option value="">All</option>
                    <?php foreach (['TEST' => 'Tests', 'PROFILE' => 'Packages', 'OFFER' => 'Offers'] as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $type === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="rounded bg-slate-800 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Filter</button>
            <?php if ($q !== '' || $type !== ''): ?>
            <a href="/admin/lab/products" class="text-sm text-slate-500 hover:underline">Clear</a>
            <?php endif; ?>
        </form>

        <!-- List -->
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Name</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Test Code</th>
                        <th class="px-4 py-2 text-right">MRP</th>
                        <th class="px-4 py-2 text-right">Offer</th>
                        <th class="px-4 py-2 text-right">Coupon cap</th>
                        <th class="px-4 py-2 text-center"># tests</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($products as $p): ?>
                    <?php [$tLabel, $tClass] = $typeBadge($p['product_type']); ?>
                    <tr class="<?= empty($p['is_active']) ? 'opacity-50' : '' ?>">
                        <td class="px-4 py-2 font-medium">
                            <a href="/admin/lab/products/<?= (int) $p['id'] ?>" class="text-sky-700 hover:underline">
                                <?= htmlspecialchars($p['name']) ?>
                            </a>
                            <?php if (!empty($p['is_featured'])): ?>
                            <span class="ml-1 text-amber-500" title="Featured">★</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $tClass ?>"><?= $tLabel ?></span></td>
                        <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($p['thyrocare_code'] ?? '') ?: '<span class="text-slate-300">—</span>' ?></td>
                        <td class="px-4 py-2 text-right text-slate-400 line-through"><?= $p['mrp'] !== null ? '₹' . number_format((float) $p['mrp']) : '—' ?></td>
                        <td class="px-4 py-2 text-right font-semibold"><?= $p['offer_rate'] !== null ? '₹' . number_format((float) $p['offer_rate']) : '—' ?></td>
                        <td class="px-4 py-2 text-right text-slate-600"><?= (int) ($p['max_discount_pct'] ?? 0) ?>%</td>
                        <td class="px-4 py-2 text-center text-slate-500"><?= (int) $p['test_count'] ?></td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= !empty($p['is_active']) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                                <?= !empty($p['is_active']) ? 'Active' : 'Hidden' ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <a href="/admin/lab/products/<?= (int) $p['id'] ?>" class="text-sky-700 hover:underline">Edit</a>
                            <form method="post" action="/admin/lab/products/<?= (int) $p['id'] ?>/toggle" class="ml-3 inline">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <button type="submit" class="text-slate-500 hover:underline"><?= !empty($p['is_active']) ? 'Hide' : 'Show' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$products): ?>
                    <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No products match.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="flex items-center justify-between text-sm">
            <span class="text-slate-500">Page <?= $page ?> of <?= $pages ?></span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                <a href="?<?= htmlspecialchars($qs(['page' => $page - 1])) ?>" class="rounded border px-3 py-1 hover:bg-slate-50">Prev</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                <a href="?<?= htmlspecialchars($qs(['page' => $page + 1])) ?>" class="rounded border px-3 py-1 hover:bg-slate-50">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
