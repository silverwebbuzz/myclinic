<?php
/**
 * Add / edit product.
 *
 * @var array<string,mixed>|null $product  ProductService::load() result, null when new
 * @var array<string,mixed> $old           POST to re-populate after an error
 * @var string|null $formError
 * @var list<array<string,mixed>> $tree    departments with subs (+ block_reason)
 * @var list<array<string,mixed>> $brands
 * @var list<array<string,mixed>> $concerns
 * @var array<string,string> $licences
 * @var list<string> $problems
 */
use App\Services\Store\CatalogService;
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isNew = $product === null;
$pageTitle = $isNew ? 'Add product' : 'Edit product';
$input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#17774f] focus:outline-none';
$hasOld = $old !== [];
// Value precedence: posted (after an error) > saved product > default.
$val = static function (string $key, $default = '') use ($old, $hasOld, $product) {
    if ($hasOld) {
        return $old[$key] ?? $default;
    }

    return $product[$key] ?? $default;
};

// Variants → form rows (rupees + cm for humans).
if ($hasOld) {
    $variantRows = array_values(array_filter((array) ($old['variants'] ?? []), 'is_array'));
} elseif (!$isNew) {
    $variantRows = [];
    foreach ($product['variants'] as $v) {
        if ((int) $v['is_active'] !== 1) {
            continue;
        }
        $variantRows[] = [
            'id' => (int) $v['id'], 'title' => (string) ($v['title'] ?? ''), 'sku' => (string) $v['sku'],
            'mrp' => ProductService::rupees((int) $v['mrp_paise']), 'price' => ProductService::rupees((int) $v['price_paise']),
            'stock_qty' => (int) $v['stock_qty'], 'low_stock_threshold' => (int) $v['low_stock_threshold'],
            'weight_g' => (int) $v['weight_g'], 'length_cm' => (int) $v['length_mm'] / 10,
            'breadth_cm' => (int) $v['breadth_mm'] / 10, 'height_cm' => (int) $v['height_mm'] / 10,
            'barcode' => (string) ($v['barcode'] ?? ''),
        ];
    }
} else {
    $variantRows = [];
}
if (!$variantRows) {
    $variantRows[] = ['id' => 0, 'title' => '', 'sku' => '', 'mrp' => '', 'price' => '', 'stock_qty' => '0',
        'low_stock_threshold' => 5, 'weight_g' => '', 'length_cm' => '', 'breadth_cm' => '', 'height_cm' => '', 'barcode' => ''];
}
foreach ($variantRows as &$vr) {
    $vr['mrp'] = str_replace(',', '', (string) ($vr['mrp'] ?? ''));
    $vr['price'] = str_replace(',', '', (string) ($vr['price'] ?? ''));
}
unset($vr);

$specRows = $hasOld ? array_values(array_filter((array) ($old['specs'] ?? []), 'is_array')) : ($product['specs'] ?? []);
if (!$specRows) {
    $specRows = [['label' => '', 'value' => '']];
}

$selectedCategory = (int) $val('category_id', 0);
$secondaryIds = array_map('intval', $hasOld ? (array) ($old['secondary_ids'] ?? []) : ($product['secondary_ids'] ?? []));
$concernIds = array_map('intval', $hasOld ? (array) ($old['concern_ids'] ?? []) : ($product['concern_ids'] ?? []));
$returnable = $hasOld ? !empty($old['is_returnable']) : (bool) ($product['is_returnable'] ?? true);
$hasExpiry = $hasOld ? !empty($old['has_expiry']) : (bool) ($product['has_expiry'] ?? false);
$status = (string) ($product['status'] ?? 'draft');
$badge = [
    'draft' => ['Draft: not visible to customers', 'bg-slate-100 text-slate-700'],
    'pending_review' => ['In review: hidden until approved', 'bg-amber-100 text-amber-800'],
    'live' => ['Live on the store', 'bg-emerald-100 text-emerald-800'],
    'rejected' => ['Needs changes', 'bg-red-100 text-red-700'],
    'disabled' => ['Disabled by eClinicPro', 'bg-red-100 text-red-700'],
    'archived' => ['Archived: hidden', 'bg-slate-200 text-slate-600'],
][$status] ?? [$status, 'bg-slate-100'];
$locked = $status === 'disabled';
ob_start();
?>
<a href="/vendor/products" class="text-sm text-[#17774f] hover:underline">← Products</a>
<div class="mt-2 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold"><?= $isNew ? 'Add a product' : $e($product['name']) ?></h1>
    <?php if (!$isNew): ?><span class="rounded-full px-3 py-1 text-xs font-semibold <?= $badge[1] ?>"><?= $e($badge[0]) ?></span><?php endif; ?>
</div>

<?php if (!$isNew && in_array($status, ['rejected', 'disabled'], true) && !empty($product['review_note'])): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>From the eClinicPro team:</strong> <?= $e($product['review_note']) ?></div>
<?php endif; ?>
<?php if ($formError): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= $e($formError) ?></div>
<?php endif; ?>

<?php if (!$isNew && in_array($status, ['draft', 'rejected'], true)): ?>
    <section class="mt-4 rounded-2xl border border-[#d5e6da] bg-[#edf5ef] p-5">
        <?php if ($problems): ?>
            <h2 class="font-semibold text-[#0e4d34]">Before you can submit</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700">
                <?php foreach ($problems as $pr): ?><li><?= $e($pr) ?></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-700"><strong>Ready.</strong> Submit to send this product for review.</p>
                <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/submit">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="rounded-full bg-[#0e4d34] px-6 py-2 text-sm font-medium text-white hover:bg-[#17774f]">Submit for review</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<form method="post" action="<?= $isNew ? '/vendor/products' : '/vendor/products/' . (int) $product['id'] ?>" class="mt-5 space-y-6"
      x-data='productForm(<?= $e(json_encode(['variants' => $variantRows, 'specs' => $specRows], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)'>
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <fieldset <?= $locked ? 'disabled' : '' ?> class="space-y-6">

    <!-- Basics -->
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <h2 class="font-semibold">Basics</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">Product name</span>
                <input name="name" required maxlength="255" value="<?= $e($val('name')) ?>" class="<?= $input ?>" placeholder="e.g. Omron HEM-7120 Automatic BP Monitor">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">Category</span>
                <select name="category_id" required class="<?= $input ?>">
                    <option value="">Choose the best-fitting subcategory…</option>
                    <?php foreach ($tree as $dept): ?>
                        <optgroup label="<?= $e($dept['name']) ?>">
                            <?php foreach ($dept['subs'] as $sub): ?>
                                <?php $blocked = $sub['block_reason'] !== null && (int) $sub['id'] !== $selectedCategory; ?>
                                <option value="<?= (int) $sub['id'] ?>" <?= (int) $sub['id'] === $selectedCategory ? 'selected' : '' ?> <?= $blocked ? 'disabled' : '' ?>>
                                    <?= $e($sub['name']) ?><?= $blocked ? ' 🔒' : (($sub['listing_mode'] ?? '') === 'review' ? ' (extra review)' : '') ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <span class="text-xs text-slate-500">🔒 = needs a licence you haven't uploaded yet, or not sold on the store. Your approved licences:
                    <?= $licences ? $e(implode(', ', array_map(static fn ($k) => \App\Services\Store\VendorService::DOC_TYPES[$k] ?? $k, array_keys($licences)))) : 'none yet' ?>.
                    <a href="/vendor/documents" class="text-[#17774f] underline">Upload licences</a></span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Brand</span>
                <select name="brand_id" class="<?= $input ?>">
                    <option value="">No brand / own brand</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= (int) $val('brand_id', 0) === (int) $b['id'] ? 'selected' : '' ?>><?= $e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">…or a brand not in the list</span>
                <input name="brand_new" maxlength="160" value="<?= $e($hasOld ? ($old['brand_new'] ?? '') : '') ?>" class="<?= $input ?>" placeholder="Type the brand name">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">Short description <span class="text-slate-400">(shown under the name, max 500)</span></span>
                <textarea name="short_desc" rows="2" maxlength="500" class="<?= $input ?>"><?= $e($val('short_desc')) ?></textarea>
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">Full description</span>
                <textarea name="description" rows="7" class="<?= $input ?>" placeholder="What it is, key benefits, how to use, ingredients / what's in the box, warnings"><?= $e($val('description')) ?></textarea>
                <span class="text-xs text-slate-400">Plain text; line breaks are kept. Don't claim to diagnose, treat or cure any condition.</span>
            </label>
        </div>
    </section>

    <!-- Variants -->
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Variants, price &amp; stock</h2>
            <button type="button" @click="addVariant()" class="rounded-full border border-[#0e4d34] px-3 py-1 text-xs font-medium text-[#0e4d34] hover:bg-[#edf5ef]">+ Add variant</button>
        </div>
        <p class="mt-1 text-xs text-slate-500">One row per pack size / flavour / size. Prices include GST. Weight and box size are the <em>packed</em> parcel, used for courier charges.</p>
        <template x-for="(v, i) in variants" :key="i">
            <div class="mt-4 rounded-xl border border-slate-200 p-4" :class="v.remove ? 'opacity-50' : ''">
                <input type="hidden" :name="`variants[${i}][id]`" :value="v.id">
                <input type="hidden" :name="`variants[${i}][remove]`" :value="v.remove ? 1 : ''">
                <div class="grid gap-3 sm:grid-cols-4">
                    <label class="block text-xs sm:col-span-2"><span class="text-slate-600">Variant name</span>
                        <input :name="`variants[${i}][title]`" x-model="v.title" maxlength="190" class="<?= $input ?>" placeholder="e.g. 60 tablets / Large / Chocolate"></label>
                    <label class="block text-xs sm:col-span-2"><span class="text-slate-600">SKU (your code)</span>
                        <input :name="`variants[${i}][sku]`" x-model="v.sku" required maxlength="80" class="<?= $input ?> uppercase"></label>
                    <label class="block text-xs"><span class="text-slate-600">MRP ₹</span>
                        <input :name="`variants[${i}][mrp]`" x-model="v.mrp" required inputmode="decimal" class="<?= $input ?>"></label>
                    <label class="block text-xs"><span class="text-slate-600">Selling price ₹</span>
                        <input :name="`variants[${i}][price]`" x-model="v.price" required inputmode="decimal" class="<?= $input ?>">
                        <span class="text-[11px] text-emerald-700" x-show="discount(v) > 0" x-text="discount(v) + '% off MRP'"></span></label>
                    <label class="block text-xs"><span class="text-slate-600">Stock</span>
                        <input :name="`variants[${i}][stock_qty]`" x-model="v.stock_qty" inputmode="numeric" class="<?= $input ?>"></label>
                    <label class="block text-xs"><span class="text-slate-600">Low-stock alert at</span>
                        <input :name="`variants[${i}][low_stock_threshold]`" x-model="v.low_stock_threshold" inputmode="numeric" class="<?= $input ?>"></label>
                    <label class="block text-xs"><span class="text-slate-600">Packed weight (g)</span>
                        <input :name="`variants[${i}][weight_g]`" x-model="v.weight_g" inputmode="numeric" class="<?= $input ?>"></label>
                    <label class="block text-xs"><span class="text-slate-600">Box L × B × H (cm)</span>
                        <span class="mt-1 flex gap-1">
                            <input :name="`variants[${i}][length_cm]`" x-model="v.length_cm" inputmode="decimal" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" placeholder="L">
                            <input :name="`variants[${i}][breadth_cm]`" x-model="v.breadth_cm" inputmode="decimal" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" placeholder="B">
                            <input :name="`variants[${i}][height_cm]`" x-model="v.height_cm" inputmode="decimal" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" placeholder="H">
                        </span></label>
                    <label class="block text-xs sm:col-span-2"><span class="text-slate-600">Barcode / EAN <span class="text-slate-400">(optional)</span></span>
                        <input :name="`variants[${i}][barcode]`" x-model="v.barcode" maxlength="40" class="<?= $input ?>"></label>
                </div>
                <button type="button" x-show="activeCount() > 1 || v.remove" @click="v.remove = !v.remove"
                        class="mt-2 text-xs text-slate-500 hover:text-red-600 hover:underline" x-text="v.remove ? 'Undo remove' : 'Remove this variant'"></button>
            </div>
        </template>
    </section>

    <!-- Placement -->
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <h2 class="font-semibold">Where customers find it</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">Also show under <span class="text-slate-400">(up to 3, optional)</span></span>
                <select name="secondary_ids[]" multiple size="7" class="<?= $input ?>">
                    <?php foreach ($tree as $dept): ?>
                        <optgroup label="<?= $e($dept['name']) ?>">
                            <?php foreach ($dept['subs'] as $sub): ?>
                                <?php if ($sub['block_reason'] === null): ?>
                                    <option value="<?= (int) $sub['id'] ?>" <?= in_array((int) $sub['id'], $secondaryIds, true) ? 'selected' : '' ?>><?= $e($sub['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <span class="text-xs text-slate-400">Hold Ctrl / ⌘ to pick several. E.g. a probiotic under both Gut Health and Senior Wellness.</span>
            </label>
            <div class="text-sm">
                <span class="text-slate-600">Health goals it supports</span>
                <div class="mt-2 grid grid-cols-2 gap-1.5">
                    <?php foreach ($concerns as $c): ?>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="concern_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $concernIds, true) ? 'checked' : '' ?>>
                            <?= $e($c['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Specs -->
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Specifications <span class="text-sm font-normal text-slate-500">(optional)</span></h2>
            <button type="button" @click="specs.push({label:'', value:''})" class="rounded-full border border-[#0e4d34] px-3 py-1 text-xs font-medium text-[#0e4d34] hover:bg-[#edf5ef]">+ Add row</button>
        </div>
        <template x-for="(s, i) in specs" :key="i">
            <div class="mt-3 flex gap-2">
                <input :name="`specs[${i}][label]`" x-model="s.label" maxlength="80" class="w-1/3 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Pack size">
                <input :name="`specs[${i}][value]`" x-model="s.value" maxlength="300" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 60 tablets">
                <button type="button" @click="specs.splice(i, 1)" class="px-2 text-slate-400 hover:text-red-600" aria-label="Remove">✕</button>
            </div>
        </template>
    </section>

    <!-- Compliance, tax, returns -->
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <h2 class="font-semibold">Compliance, tax &amp; returns</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <label class="block text-sm">
                <span class="text-slate-600">Licence no. on label</span>
                <input name="license_number" maxlength="80" value="<?= $e($val('license_number')) ?>" class="<?= $input ?>" placeholder="FSSAI / device reg. no.">
                <span class="text-xs text-slate-400">Required for food, supplement, AYUSH and device categories.</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">HSN code <span class="text-red-600">*</span></span>
                <input name="hsn_code" maxlength="8" inputmode="numeric" pattern="\d{4,8}" required value="<?= $e($val('hsn_code')) ?>" class="<?= $input ?>">
                <span class="text-xs text-slate-400">Printed on the customer's GST invoice.</span>
            </label>
            <?php
            $gstCur = (string) $val('gst_bp', '');
            $gstKnown = $gstCur !== '' && array_key_exists((int) $gstCur, CatalogService::GST_RATES_BP);
            ?>
            <label class="block text-sm">
                <span class="text-slate-600">GST rate</span>
                <select name="gst_bp" required class="<?= $input ?>">
                    <option value="" <?= $gstKnown ? '' : 'selected' ?>>Choose GST rate…</option>
                    <?php foreach (CatalogService::GST_RATES_BP as $bp => $label): ?>
                        <option value="<?= $bp ?>" <?= $gstKnown && (int) $gstCur === $bp ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$isNew && $gstCur !== '' && !$gstKnown): ?>
                    <span class="text-xs text-amber-700">Saved earlier at <?= (int) $gstCur / 100 ?>%, which is no longer a GST slab. Please choose the current rate.</span>
                <?php else: ?>
                    <span class="text-xs text-slate-400">Prices include GST. Your CA can confirm the rate for this HSN code.</span>
                <?php endif; ?>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Manufacturer</span>
                <input name="manufacturer" maxlength="190" value="<?= $e($val('manufacturer')) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Country of origin</span>
                <input name="country_of_origin" maxlength="60" value="<?= $e($val('country_of_origin', 'India')) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Return window (days)</span>
                <input name="return_window_days" type="number" min="0" max="30" value="<?= $e($val('return_window_days')) ?>" class="<?= $input ?>" placeholder="Default: <?= (int) ($vendor['default_return_window_days'] ?? 7) ?>">
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_returnable" value="1" <?= $returnable ? 'checked' : '' ?>> Returnable
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="has_expiry" value="1" <?= $hasExpiry ? 'checked' : '' ?>> Has an expiry date
            </label>
        </div>
    </section>

    <!-- SEO -->
    <details class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <summary class="cursor-pointer font-semibold">Search engine listing <span class="text-sm font-normal text-slate-500">(optional)</span></summary>
        <div class="mt-4 grid gap-4">
            <label class="block text-sm"><span class="text-slate-600">SEO title</span>
                <input name="seo_title" maxlength="190" value="<?= $e($val('seo_title')) ?>" class="<?= $input ?>"></label>
            <label class="block text-sm"><span class="text-slate-600">SEO description</span>
                <textarea name="seo_description" rows="2" maxlength="300" class="<?= $input ?>"><?= $e($val('seo_description')) ?></textarea></label>
        </div>
    </details>

    <div class="flex flex-wrap items-center gap-3">
        <button class="rounded-full bg-[#0e4d34] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#17774f]"><?= $isNew ? 'Save draft & add photos' : 'Save changes' ?></button>
        <?php if (!$isNew && $status === 'live'): ?>
            <span class="text-xs text-slate-500">Price and stock changes go live immediately. Changing the name, category, brand or description sends the product back for review.</span>
        <?php endif; ?>
    </div>
    </fieldset>
</form>

<?php if (!$isNew): ?>
<!-- Photos (separate forms: file uploads can't nest in the main form) -->
<section id="photos" class="mt-6 scroll-mt-6 rounded-2xl border border-[#ece8df] bg-white p-6">
    <h2 class="font-semibold">Photos <span class="text-sm font-normal text-slate-500">(<?= count($product['images']) ?>/<?= ProductService::MAX_IMAGES ?>)</span></h2>
    <p class="mt-1 text-xs text-slate-500">Square photos on a plain background work best. The first photo is the cover. JPG/PNG/WEBP, max 3 MB each.</p>
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <?php foreach ($product['images'] as $idx => $img): ?>
            <div class="relative overflow-hidden rounded-xl border border-slate-200">
                <img src="<?= $e($img['path']) ?>" alt="" class="aspect-square w-full object-cover">
                <?php if ($idx === 0): ?><span class="absolute left-2 top-2 rounded-full bg-[#0e4d34] px-2 py-0.5 text-[10px] font-semibold text-white">Cover</span><?php endif; ?>
                <div class="flex justify-between gap-1 border-t bg-white px-2 py-1.5 text-xs">
                    <?php if ($idx !== 0 && !$locked): ?>
                        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/images/<?= (int) $img['id'] ?>/cover">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-[#17774f] hover:underline">Make cover</button>
                        </form>
                    <?php else: ?><span></span><?php endif; ?>
                    <?php if (!$locked): ?>
                        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/images/<?= (int) $img['id'] ?>/delete" onsubmit="return confirm('Remove this photo?')">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-slate-500 hover:text-red-600 hover:underline">Remove</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (!$locked && count($product['images']) < ProductService::MAX_IMAGES): ?>
        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/images" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="file" name="images[]" multiple required accept="image/jpeg,image/png,image/webp" class="text-sm">
            <button class="rounded-full border border-[#0e4d34] px-4 py-1.5 text-sm font-medium text-[#0e4d34] hover:bg-[#edf5ef]">Upload photos</button>
        </form>
    <?php endif; ?>
</section>

<section class="mt-6 flex flex-wrap items-center gap-4 text-sm">
    <?php if ($status === 'live'): ?>
        <a href="https://eclinicpro.com/store/p/<?= $e($product['slug']) ?>" target="_blank" rel="noopener" class="text-[#17774f] hover:underline">View on store ↗</a>
    <?php endif; ?>
    <?php if (!in_array($status, ['archived', 'disabled'], true)): ?>
        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/archive" onsubmit="return confirm('Archive this product? It will be hidden from customers.')">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="archive">
            <button class="text-slate-500 hover:text-red-600 hover:underline">Archive product</button>
        </form>
    <?php elseif ($status === 'archived'): ?>
        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/archive">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="restore">
            <button class="text-[#17774f] hover:underline">Restore as draft</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<script>
function productForm(init) {
    const blank = () => ({ id: 0, title: '', sku: '', mrp: '', price: '', stock_qty: '0', low_stock_threshold: 5,
        weight_g: '', length_cm: '', breadth_cm: '', height_cm: '', barcode: '', remove: false });
    return {
        variants: (init.variants || []).map(v => Object.assign(blank(), v, { remove: !!v.remove && v.remove !== '0' })),
        specs: init.specs || [],
        addVariant() { this.variants.push(blank()); },
        activeCount() { return this.variants.filter(v => !v.remove).length; },
        discount(v) {
            const m = parseFloat(v.mrp), p = parseFloat(v.price);
            return m > 0 && p > 0 && p < m ? Math.round((1 - p / m) * 100) : 0;
        },
    };
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
