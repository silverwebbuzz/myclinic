<?php
/** /admin/lab/products/{id} — edit price/flags, view composition. */
$cur = $current ?? [];
// Group parameters by their group_name for a clean panel display.
$grouped = [];
foreach ($parameters as $p) {
    $grouped[$p['group_name'] ?: 'Other'][] = $p['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['name']) ?> — Lab Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl p-6 space-y-6">

        <a href="/admin/lab/products" class="text-sm text-slate-500 hover:underline">&larr; Back to catalog</a>

        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-xl font-semibold"><?= htmlspecialchars($product['name']) ?></h1>
                <p class="text-sm text-slate-500">
                    <?= htmlspecialchars($product['product_type']) ?> ·
                    code <code><?= htmlspecialchars($product['code']) ?></code> ·
                    <?= (int) $product['test_count'] ?> tests
                    <?php if (!empty($categories)): ?> · <?= htmlspecialchars(implode(', ', $categories)) ?><?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <!-- ===== Edit form ===== -->
        <form method="post" action="/admin/lab/products/<?= (int) $product['id'] ?>"
              class="rounded-xl border bg-white p-5 shadow-sm space-y-5">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <h2 class="font-semibold">Pricing</h2>
            <p class="text-xs text-slate-500">
                Patients see <strong>MRP struck through + Offer price</strong> (same as thyrocare.com).
                A logged-in coupon gives an extra discount, capped at the <strong>coupon cap %</strong> below.
                Saving a changed price adds a new dated pricing row (history is kept).
            </p>
            <div class="grid gap-4 sm:grid-cols-4">
                <label class="block text-sm">
                    <span class="text-slate-600">MRP (₹)</span>
                    <input type="number" step="0.01" min="0" name="mrp" value="<?= htmlspecialchars((string) ($cur['mrp'] ?? '')) ?>"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Offer price (₹)</span>
                    <input type="number" step="0.01" min="0" name="offer_rate" value="<?= htmlspecialchars((string) ($cur['offer_rate'] ?? '')) ?>"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Coupon cap (%)</span>
                    <input type="number" min="0" max="100" name="max_discount_pct" value="<?= (int) ($cur['max_discount_pct'] ?? 0) ?>"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Incentive (%)</span>
                    <input type="number" step="0.01" min="0" name="incentive_pct" value="<?= htmlspecialchars((string) ($cur['incentive_pct'] ?? '')) ?>"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
            </div>

            <h2 class="font-semibold pt-2">Display &amp; identity</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-slate-600">Test Code (PROJ…)</span>
                    <input type="text" name="thyrocare_code" value="<?= htmlspecialchars($product['thyrocare_code'] ?? '') ?>"
                           placeholder="PROJ1035272" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Sort order</span>
                    <input type="number" name="sort_order" value="<?= (int) $product['sort_order'] ?>"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" <?= !empty($product['is_active']) ? 'checked' : '' ?>> Active
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?>> Featured
                    </label>
                </div>
            </div>

            <button type="submit" class="rounded bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-600">Save changes</button>
        </form>

        <!-- ===== Composition ===== -->
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold mb-3">Included tests (<?= count($parameters) ?>)</h2>
            <?php if (!$parameters): ?>
            <p class="text-sm text-slate-400">No parameters linked (this is a single test).</p>
            <?php else: ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach ($grouped as $group => $names): ?>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1"><?= htmlspecialchars($group) ?></h3>
                    <ul class="text-sm text-slate-700 space-y-0.5">
                        <?php foreach ($names as $n): ?>
                        <li><?= htmlspecialchars($n) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ===== Price history ===== -->
        <?php if (count($priceHistory) > 1): ?>
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold mb-3">Price history</h2>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr><th class="py-1">Effective</th><th class="py-1 text-right">MRP</th><th class="py-1 text-right">Offer</th><th class="py-1 text-right">Cap</th></tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($priceHistory as $h): ?>
                    <tr>
                        <td class="py-1"><?= htmlspecialchars($h['effective_from']) ?></td>
                        <td class="py-1 text-right">₹<?= number_format((float) $h['mrp']) ?></td>
                        <td class="py-1 text-right">₹<?= number_format((float) $h['offer_rate']) ?></td>
                        <td class="py-1 text-right"><?= (int) $h['max_discount_pct'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
