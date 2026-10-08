<?php
/**
 * /admin/store/products/{id} — review one product.
 *
 * @var array<string,mixed> $product  ProductService::load()
 * @var array<string,mixed>|null $vendor
 * @var array<string,mixed>|null $category
 * @var array<string,mixed>|null $dept
 * @var array<string,mixed>|null $brand
 * @var list<string> $secondaryNames
 * @var list<string> $concernNames
 * @var array<string,string> $licences
 * @var list<string> $problems
 */
use App\Services\Store\CatalogService;
use App\Services\Store\ProductService;
use App\Services\Store\VendorService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pid = (int) $product['id'];
$status = (string) $product['status'];
$requiredDoc = (string) ($category['required_vendor_doc'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $e($product['name']) ?> — Store product</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <a href="/admin/store/products" class="text-sm text-sky-700 hover:underline">← Products</a>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold"><?= $e($product['name']) ?> <?= !empty($product['is_featured']) ? '<span class="text-amber-500">★</span>' : '' ?></h1>
            <p class="text-sm text-slate-500">
                <?= $e(str_replace('_', ' ', $status)) ?> · by
                <a href="/admin/store/vendors/<?= (int) ($vendor['id'] ?? 0) ?>" class="text-sky-700 hover:underline"><?= $e($vendor['display_name'] ?? '?') ?></a>
                <?= $vendor && $vendor['status'] !== 'approved' ? '<span class="text-red-600">(seller ' . $e($vendor['status']) . ')</span>' : '' ?>
                · /store/p/<?= $e($product['slug']) ?>
            </p>
        </div>
        <form method="post" action="/admin/store/products/<?= $pid ?>/feature">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="rounded border bg-white px-3 py-1.5 text-sm hover:bg-slate-50"><?= !empty($product['is_featured']) ? 'Unfeature' : 'Feature on store' ?></button>
        </form>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>

    <!-- Decision -->
    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Decision</h2>
        <?php if (!empty($product['review_note'])): ?><p class="mt-1 text-sm text-slate-600">Last note to seller: <?= $e($product['review_note']) ?></p><?php endif; ?>
        <?php if ($problems): ?>
            <div class="mt-3 rounded bg-amber-50 px-3 py-2 text-sm text-amber-900"><strong>Automatic checks flag:</strong>
                <ul class="list-disc pl-5"><?php foreach ($problems as $pr): ?><li><?= $e($pr) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="/admin/store/products/<?= $pid ?>/decision" class="mt-3 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="next" value="queue">
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-sm"><span class="text-slate-600">Regulatory class (on approve)</span>
                    <select name="regulatory_class" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                        <?php foreach (CatalogService::CLASS_LABELS as $k => $label): ?>
                            <?php if ($k === 'drug_restricted') { continue; } ?>
                            <option value="<?= $e($k) ?>" <?= $product['regulatory_class'] === $k ? 'selected' : '' ?>><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="text-sm sm:col-span-2"><span class="text-slate-600">Note to seller (required to send back or disable)</span>
                    <input name="note" maxlength="500" class="mt-1 w-full rounded border px-3 py-1.5 text-sm" placeholder="e.g. Remove the claim 'cures diabetes' from the description"></label>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if (in_array($status, ['pending_review', 'rejected', 'disabled'], true)): ?>
                    <button name="action" value="approve" class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"><?= $status === 'disabled' ? 'Re-enable (live)' : 'Approve & publish' ?></button>
                <?php endif; ?>
                <?php if ($status === 'pending_review'): ?>
                    <button name="action" value="reject" class="rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">Send back for changes</button>
                <?php endif; ?>
                <?php if (in_array($status, ['live', 'pending_review'], true)): ?>
                    <button name="action" value="disable" class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700" onclick="return confirm('Disable this product? The seller cannot re-enable it.')">Disable</button>
                <?php endif; ?>
            </div>
        </form>
        <p class="mt-3 text-xs text-slate-500">Check: genuine brand, no "diagnose / treat / cure" claims, licence number matches the seller's licence, sensible price vs MRP, clear photos.</p>
    </section>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="font-semibold">Photos</h2>
            <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                <?php foreach ($product['images'] as $img): ?>
                    <a href="<?= $e($img['path']) ?>" target="_blank" rel="noopener"><img src="<?= $e($img['path']) ?>" alt="" class="aspect-square w-full rounded border object-cover"></a>
                <?php endforeach; ?>
                <?php if (!$product['images']): ?><p class="col-span-4 text-sm text-slate-400">No photos.</p><?php endif; ?>
            </div>
            <h2 class="mt-5 font-semibold">Description</h2>
            <?php if (!empty($product['short_desc'])): ?><p class="mt-2 text-sm font-medium text-slate-700"><?= $e($product['short_desc']) ?></p><?php endif; ?>
            <div class="mt-2 whitespace-pre-line text-sm text-slate-600"><?= $e($product['description'] ?? '') ?: '<span class="text-slate-400">—</span>' ?></div>
            <?php if ($product['specs']): ?>
                <h2 class="mt-5 font-semibold">Specifications</h2>
                <dl class="mt-2 grid grid-cols-3 gap-y-1 text-sm">
                    <?php foreach ($product['specs'] as $s): ?><dt class="text-slate-400"><?= $e($s['label']) ?></dt><dd class="col-span-2"><?= $e($s['value']) ?></dd><?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold">Compliance</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div><dt class="text-slate-400">Category</dt><dd><?= $e($dept['name'] ?? '') ?> › <?= $e($category['name'] ?? '?') ?></dd></div>
                <div><dt class="text-slate-400">Category rule</dt><dd><?= $e(CatalogService::CLASS_LABELS[$category['regulatory_class'] ?? ''] ?? '') ?> · <?= $e($category['listing_mode'] ?? '') ?><?= !empty($category['review_reason']) ? ' · ' . $e($category['review_reason']) : '' ?></dd></div>
                <?php if ($requiredDoc !== ''): ?>
                    <div><dt class="text-slate-400">Seller licence needed</dt>
                        <dd><?= $e(VendorService::DOC_TYPES[$requiredDoc] ?? $requiredDoc) ?>:
                            <?= array_key_exists($requiredDoc, $licences) ? '<span class="text-emerald-700">on file ✓ ' . $e($licences[$requiredDoc]) . '</span>' : '<span class="text-red-600">missing ✗</span>' ?></dd></div>
                <?php endif; ?>
                <div><dt class="text-slate-400">Licence no. on label</dt><dd class="font-mono"><?= $e($product['license_number'] ?? '—') ?></dd></div>
                <?php if (!empty($category['no_promotion'])): ?><div class="rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">No-promotion category (IMS Act): never discount or promote.</div><?php endif; ?>
                <div><dt class="text-slate-400">Also listed under</dt><dd><?= $e(implode(', ', $secondaryNames) ?: '—') ?></dd></div>
                <div><dt class="text-slate-400">Health goals</dt><dd><?= $e(implode(', ', $concernNames) ?: '—') ?></dd></div>
                <div><dt class="text-slate-400">Brand</dt><dd><?= $e($brand['name'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-400">Manufacturer / origin</dt><dd><?= $e($product['manufacturer'] ?? '—') ?> · <?= $e($product['country_of_origin'] ?? '') ?></dd></div>
                <div><dt class="text-slate-400">Tax</dt><dd>HSN <?= $e($product['hsn_code'] ?? '—') ?> · GST <?= (int) $product['gst_bp'] / 100 ?>%</dd></div>
                <div><dt class="text-slate-400">Returns</dt><dd><?= !empty($product['is_returnable']) ? 'Returnable, ' . \App\Services\Store\ReturnService::windowLabel() . ' (platform rule)' : 'Not returnable' ?><?= !empty($product['has_expiry']) ? ' · has expiry' : '' ?></dd></div>
            </dl>
        </section>
    </div>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Variants</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1">Variant</th><th>SKU</th><th class="text-right">MRP</th><th class="text-right">Price</th><th class="text-right">Off</th><th class="text-right">Stock</th><th class="text-right">Weight</th><th class="text-right">Box (cm)</th></tr></thead>
                <tbody class="divide-y">
                <?php foreach ($product['variants'] as $v): ?>
                    <?php $off = (int) $v['mrp_paise'] > 0 ? (int) round((1 - (int) $v['price_paise'] / (int) $v['mrp_paise']) * 100) : 0; ?>
                    <tr class="<?= (int) $v['is_active'] === 1 ? '' : 'opacity-40' ?>">
                        <td class="py-1.5"><?= $e($v['title'] ?? '—') ?><?= (int) $v['is_active'] === 1 ? '' : ' (removed)' ?></td>
                        <td class="font-mono text-xs"><?= $e($v['sku']) ?></td>
                        <td class="text-right">₹<?= $e(ProductService::rupees((int) $v['mrp_paise'])) ?></td>
                        <td class="text-right">₹<?= $e(ProductService::rupees((int) $v['price_paise'])) ?></td>
                        <td class="text-right <?= $off >= 60 ? 'font-semibold text-red-600' : '' ?>"><?= $off ?>%</td>
                        <td class="text-right"><?= (int) $v['stock_qty'] ?></td>
                        <td class="text-right"><?= (int) $v['weight_g'] ?> g</td>
                        <td class="text-right"><?= (int) $v['length_mm'] / 10 ?>×<?= (int) $v['breadth_mm'] / 10 ?>×<?= (int) $v['height_mm'] / 10 ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
