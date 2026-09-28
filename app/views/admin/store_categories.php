<?php
/**
 * /admin/store/categories — taxonomy + compliance rules per subcategory.
 *
 * @var list<array<string,mixed>> $tree
 * @var array<int,int> $counts live products per category
 * @var string $dept selected department slug
 * @var array<string,string> $classes
 * @var array<string,string> $docTypes
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if ($dept === '' && $tree) {
    $dept = (string) $tree[0]['slug'];
}
$current = null;
foreach ($tree as $d) {
    if ($d['slug'] === $dept) {
        $current = $d;
    }
}
$modeCls = ['open' => 'bg-emerald-100 text-emerald-800', 'review' => 'bg-amber-100 text-amber-800', 'blocked' => 'bg-red-100 text-red-700'];
$licenceDocs = array_intersect_key($docTypes, array_flip(['fssai_license', 'ayush_license', 'medical_device_registration', 'drug_license', 'trade_license']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store categories — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-7xl space-y-5 p-6">
    <div>
        <h1 class="text-xl font-semibold">Store categories &amp; compliance</h1>
        <p class="text-sm text-slate-500">Seeded from the category workbook. <strong>open</strong> = normal approval · <strong>review</strong> = always needs admin approval · <strong>blocked</strong> = can't be listed. "Licence needed" = the approved seller document required to list here.</p>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php if (!$tree): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">No categories. Import <code>2026_09_28_store_taxonomy.sql</code> and <code>2026_09_28_store_taxonomy_seed.sql</code>.</div>
    <?php endif; ?>

    <div class="flex flex-col gap-5 lg:flex-row">
        <nav class="w-full shrink-0 space-y-0.5 text-sm lg:w-60">
            <?php foreach ($tree as $d): ?>
                <a href="?dept=<?= $e($d['slug']) ?>" class="flex justify-between rounded px-3 py-1.5 <?= $d['slug'] === $dept ? 'bg-slate-800 text-white' : 'hover:bg-white' ?> <?= empty($d['is_active']) ? 'opacity-50' : '' ?>">
                    <span><?= $e($d['name']) ?></span><span class="opacity-60"><?= count($d['subs']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($current !== null): ?>
        <div class="min-w-0 flex-1 space-y-3">
            <form method="post" action="/admin/store/categories/<?= (int) $current['id'] ?>" class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4 shadow-sm">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <label class="text-sm"><span class="text-xs text-slate-500">Department name</span>
                    <input name="name" value="<?= $e($current['name']) ?>" class="mt-1 block w-64 rounded border px-2 py-1.5 text-sm"></label>
                <label class="text-sm"><span class="text-xs text-slate-500">Sort</span>
                    <input name="sort_order" type="number" value="<?= (int) $current['sort_order'] ?>" class="mt-1 block w-20 rounded border px-2 py-1.5 text-sm"></label>
                <label class="flex items-center gap-1.5 pb-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !empty($current['is_active']) ? 'checked' : '' ?>> Active</label>
                <label class="flex-1 text-sm"><span class="text-xs text-slate-500">Description (category page intro)</span>
                    <input name="description" value="<?= $e($current['description'] ?? '') ?>" class="mt-1 block w-full rounded border px-2 py-1.5 text-sm"></label>
                <button class="rounded bg-slate-800 px-3 py-1.5 text-sm text-white">Save</button>
                <p class="w-full text-xs text-slate-500">Sheet note: <?= $e($current['compliance_note'] ?? '—') ?></p>
            </form>

            <?php foreach ($current['subs'] as $s): ?>
                <form id="cat-<?= (int) $s['id'] ?>" method="post" action="/admin/store/categories/<?= (int) $s['id'] ?>"
                      class="grid scroll-mt-4 gap-2 rounded-xl border bg-white p-3 text-sm shadow-sm md:grid-cols-12 md:items-end <?= empty($s['is_active']) ? 'opacity-60' : '' ?>">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <label class="md:col-span-3"><span class="text-xs text-slate-500">Subcategory <?= $s['group_key'] ? '· ' . $e($s['group_key']) : '' ?> · <?= (int) ($counts[(int) $s['id']] ?? 0) ?> live</span>
                        <input name="name" value="<?= $e($s['name']) ?>" class="mt-1 w-full rounded border px-2 py-1"></label>
                    <label class="md:col-span-2"><span class="text-xs text-slate-500">Class</span>
                        <select name="regulatory_class" class="mt-1 w-full rounded border px-1 py-1">
                            <?php foreach ($classes as $k => $label): ?><option value="<?= $e($k) ?>" <?= $s['regulatory_class'] === $k ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?>
                        </select></label>
                    <label class="md:col-span-2"><span class="text-xs text-slate-500">Licence needed</span>
                        <select name="required_vendor_doc" class="mt-1 w-full rounded border px-1 py-1">
                            <option value="">None</option>
                            <?php foreach ($licenceDocs as $k => $label): ?><option value="<?= $e($k) ?>" <?= $s['required_vendor_doc'] === $k ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?>
                        </select></label>
                    <label class="md:col-span-1"><span class="text-xs text-slate-500">Mode</span>
                        <select name="listing_mode" class="mt-1 w-full rounded border px-1 py-1 <?= $modeCls[$s['listing_mode']] ?? '' ?>">
                            <?php foreach (['open', 'review', 'blocked'] as $m): ?><option value="<?= $m ?>" <?= $s['listing_mode'] === $m ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?>
                        </select></label>
                    <label class="md:col-span-1"><span class="text-xs text-slate-500">Default HSN</span>
                        <input name="default_hsn" maxlength="8" inputmode="numeric" list="hsn-codes" value="<?= $e($s['default_hsn'] ?? '') ?>" class="mt-1 w-full rounded border px-2 py-1 font-mono"></label>
                    <label class="md:col-span-1"><span class="text-xs text-slate-500">Reason</span>
                        <input name="review_reason" value="<?= $e($s['review_reason'] ?? '') ?>" class="mt-1 w-full rounded border px-2 py-1"></label>
                    <div class="flex items-center gap-2 md:col-span-2">
                        <label class="flex items-center gap-1 text-xs" title="IMS Act: never discount/promote"><input type="checkbox" name="no_promotion" value="1" <?= !empty($s['no_promotion']) ? 'checked' : '' ?>> No promo</label>
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" <?= !empty($s['is_active']) ? 'checked' : '' ?>> Active</label>
                        <input type="hidden" name="sort_order" value="<?= (int) $s['sort_order'] ?>">
                        <button class="ml-auto rounded bg-slate-800 px-2.5 py-1 text-xs text-white">Save</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
<datalist id="hsn-codes">
    <?php foreach (\App\Services\Store\HsnService::forForm() as $h): ?><option value="<?= $e($h['code']) ?>"><?= $e($h['description']) ?></option><?php endforeach; ?>
</datalist>
</main>
</body>
</html>
