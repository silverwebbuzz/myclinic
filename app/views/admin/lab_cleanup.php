<?php
/**
 * /admin/lab/products/cleanup — bulk-remove catalog items the merchant
 * account can't fulfil. Paste names → preview matches → delete.
 */
$matchCount = 0;
foreach ($matched as $hits) {
    $matchCount += count($hits);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clean up lab catalog — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6 space-y-6">

        <a href="/admin/lab/products" class="text-sm text-slate-500 hover:underline">&larr; Back to catalog</a>

        <div>
            <h1 class="text-xl font-semibold">Clean up catalog</h1>
            <p class="text-sm text-slate-500">
                Paste the tests / packages your Thyrocare merchant account does <strong>not</strong> offer —
                one per line. Names, master codes and PROJ… test codes all work.
            </p>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-rose-50 border border-rose-200 px-4 py-2 text-sm text-rose-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <?php if ($report !== null): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
            <h2 class="font-semibold mb-2">Deleted <?= (int) $report['products'] ?> product<?= $report['products'] === 1 ? '' : 's' ?></h2>
            <ul class="list-disc pl-5 space-y-0.5">
                <li><?= (int) $report['pricing'] ?> pricing row(s)</li>
                <li><?= (int) $report['parameters'] ?> product→parameter link(s)</li>
                <li><?= (int) $report['categories'] ?> product→category link(s)</li>
                <li><?= (int) $report['orphan_parameters'] ?> now-unused parameter(s) swept</li>
                <li><?= (int) $report['orphan_categories'] ?> now-empty category(ies) swept</li>
            </ul>
            <?php if (!empty($report['names'])): ?>
            <details class="mt-3">
                <summary class="cursor-pointer font-medium">Show removed names</summary>
                <ul class="mt-2 space-y-0.5 text-emerald-800">
                    <?php foreach ($report['names'] as $n): ?>
                    <li><?= htmlspecialchars($n) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
            <p class="mt-3 text-xs">
                Re-run <code>2026_07_21_lab_test_groups.sql</code> if you want the cached
                test-group counts recomputed — deleting products doesn't change other products' counts,
                so this is optional.
            </p>
        </div>
        <?php endif; ?>

        <form method="post" action="/admin/lab/products/cleanup" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <label class="block text-sm">
                <span class="text-slate-600 font-medium">Names / codes to remove (one per line)</span>
                <textarea name="names" rows="12"
                          placeholder="COMPLETE THYROID CHECKUP&#10;AAROGYAM BASIC 1 WITH UTSH&#10;PROJ1035272"
                          class="mt-1 w-full rounded border px-3 py-2 font-mono text-xs"><?= htmlspecialchars($raw) ?></textarea>
            </label>

            <div class="flex items-center gap-3">
                <button type="submit" name="action" value="preview"
                        class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Preview matches
                </button>
                <?php if ($previewed && $matchCount > 0): ?>
                <button type="submit" name="action" value="delete"
                        onclick="return confirm('Permanently delete <?= $matchCount ?> product(s) and all their pricing, parameters and category links? This cannot be undone.');"
                        class="rounded bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                    Delete <?= $matchCount ?> matched product<?= $matchCount === 1 ? '' : 's' ?>
                </button>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($previewed): ?>
        <div class="grid gap-6 md:grid-cols-2">

            <div class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="font-semibold mb-3 text-emerald-800">
                    Will be deleted (<?= $matchCount ?>)
                </h2>
                <?php if (!$matched): ?>
                <p class="text-sm text-slate-400">Nothing matched.</p>
                <?php else: ?>
                <ul class="space-y-1 text-sm">
                    <?php foreach ($matched as $term => $hits): ?>
                        <?php foreach ($hits as $hit): ?>
                        <li class="flex items-start gap-2">
                            <span class="text-slate-300">#<?= (int) $hit['id'] ?></span>
                            <a href="/admin/lab/products/<?= (int) $hit['id'] ?>" class="text-sky-700 hover:underline">
                                <?= htmlspecialchars($hit['name']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <div class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="font-semibold mb-3 text-amber-800">
                    No match (<?= count($unmatched) ?>)
                </h2>
                <?php if (!$unmatched): ?>
                <p class="text-sm text-slate-400">Every line matched a product.</p>
                <?php else: ?>
                <p class="mb-2 text-xs text-slate-500">
                    These lines matched nothing — already deleted, or the name differs from the catalog.
                    Search the catalog to check the exact wording.
                </p>
                <ul class="space-y-1 text-sm text-slate-700">
                    <?php foreach ($unmatched as $u): ?>
                    <li class="flex items-start justify-between gap-2">
                        <span><?= htmlspecialchars($u) ?></span>
                        <a href="/admin/lab/products?<?= htmlspecialchars(http_build_query(['q' => $u])) ?>"
                           class="shrink-0 text-xs text-sky-700 hover:underline">search</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
