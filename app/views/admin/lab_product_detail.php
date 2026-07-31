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
        <?php $isError = in_array($message, ['confirm_name_mismatch', 'delete_failed', 'save_error'], true); ?>
        <div class="rounded border px-4 py-2 text-sm <?= $isError
            ? 'bg-rose-50 border-rose-200 text-rose-800'
            : 'bg-emerald-50 border-emerald-200 text-emerald-800' ?>">
            <?= $message === 'confirm_name_mismatch'
                ? 'The name you typed didn\'t match — nothing was deleted.'
                : htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
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

        <!-- ===== Danger zone ===== -->
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <h2 class="font-semibold text-rose-900">Delete this product</h2>
            <p class="mt-1 text-sm text-rose-800">
                Permanently removes the product plus its pricing history,
                <?= count($parameters) ?> parameter link(s), category links, images and aliases.
                Parameters and categories left referencing nothing are swept too. This cannot be undone.
            </p>
            <p class="mt-2 text-sm text-rose-800">
                If you only want it off the public site, untick <strong>Active</strong> above instead —
                that keeps the row for a future re-import.
            </p>

            <form method="post" action="/admin/lab/products/<?= (int) $product['id'] ?>/delete"
                  class="mt-4 flex flex-wrap items-end gap-3"
                  onsubmit="return confirm('Permanently delete this product? This cannot be undone.');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <label class="block text-sm">
                    <span class="text-rose-900">Type the product name to confirm</span>
                    <input type="text" name="confirm_name" required autocomplete="off"
                           placeholder="<?= htmlspecialchars($product['name']) ?>"
                           class="mt-1 w-96 max-w-full rounded border border-rose-300 px-2 py-1.5 text-sm">
                </label>
                <button type="submit"
                        class="rounded bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                    Delete permanently
                </button>
            </form>
        </div>
    </main>
</body>
</html>
