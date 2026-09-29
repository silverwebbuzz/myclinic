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
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 text-sm focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
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
$initialCommission = $defaultCommission ?? ['type' => 'percent', 'rate_bp' => 1000, 'fixed_paise' => 0];
foreach ($tree as $dept) {
    foreach ($dept['subs'] as $sub) {
        if ((int) $sub['id'] === $selectedCategory && isset($sub['commission'])) {
            $initialCommission = $sub['commission'];
        }
    }
}
$calcInit = [
    'fees' => $feeConfig ?? null,
    'comm' => ['type' => $initialCommission['type'], 'bp' => (int) $initialCommission['rate_bp'], 'fixed' => (int) $initialCommission['fixed_paise']],
];
$secondaryIds = array_map('intval', $hasOld ? (array) ($old['secondary_ids'] ?? []) : ($product['secondary_ids'] ?? []));
$concernIds = array_map('intval', $hasOld ? (array) ($old['concern_ids'] ?? []) : ($product['concern_ids'] ?? []));
$returnable = $hasOld ? !empty($old['is_returnable']) : (bool) ($product['is_returnable'] ?? true);
$hasExpiry = $hasOld ? !empty($old['has_expiry']) : (bool) ($product['has_expiry'] ?? false);
$status = (string) ($product['status'] ?? 'draft');
$badge = [
    'draft' => ['Draft: not visible to customers', 'bg-ntb text-nt'],
    'pending_review' => ['In review: hidden until approved', 'bg-wnb text-wn'],
    'live' => ['Live on the store', 'bg-okb text-ok'],
    'rejected' => ['Needs changes', 'bg-erb text-er'],
    'disabled' => ['Disabled by eClinicPro', 'bg-erb text-er'],
    'archived' => ['Archived: hidden', 'bg-slate-200 text-tx2'],
][$status] ?? [$status, 'bg-sf2'];
$locked = $status === 'disabled';
ob_start();
?>
<a href="/vendor/products" class="text-sm text-act hover:underline">← Products</a>
<div class="mt-2 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-[22px] font-semibold tracking-[-.015em]"><?= $isNew ? 'Add a product' : $e($product['name']) ?></h1>
    <?php if (!$isNew): ?><span class="inline-flex h-[22px] items-center whitespace-nowrap rounded-md px-2 text-xs font-medium <?= $badge[1] ?>"><?= $e($badge[0]) ?></span><?php endif; ?>
</div>

<?php if (!$isNew && in_array($status, ['rejected', 'disabled'], true) && !empty($product['review_note'])): ?>
    <div class="mt-4 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>From the eClinicPro team:</strong> <?= $e($product['review_note']) ?></div>
<?php endif; ?>
<?php if ($formError): ?>
    <div class="mt-4 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= $e($formError) ?></div>
<?php endif; ?>

<?php if (!$isNew && in_array($status, ['draft', 'rejected'], true)): ?>
    <section class="mt-4 rounded-[10px] border border-ac/20 bg-acs p-5">
        <?php if ($problems): ?>
            <h2 class="font-semibold text-act">Before you can submit</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-tx2">
                <?php foreach ($problems as $pr): ?><li><?= $e($pr) ?></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-tx2"><strong>Ready.</strong> Submit to send this product for review.</p>
                <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/submit">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="rounded-[7px] bg-ac px-4 py-1.5 text-[13px] font-medium text-white hover:opacity-90">Submit for review</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<form method="post" action="<?= $isNew ? '/vendor/products' : '/vendor/products/' . (int) $product['id'] ?>" class="mt-5 space-y-6"
      x-data='productForm(<?= $e(json_encode(['variants' => $variantRows, 'specs' => $specRows, 'calc' => $calcInit], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)'
      @category-picked.window="setCommission($event.detail)">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <fieldset <?= $locked ? 'disabled' : '' ?> class="space-y-6">

    <!-- Basics -->
    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <h2 class="font-semibold">Basics</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Product name</span>
                <input name="name" required maxlength="255" value="<?= $e($val('name')) ?>" class="<?= $input ?>" placeholder="e.g. Omron HEM-7120 Automatic BP Monitor">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Category</span>
                <select name="category_id" required class="<?= $input ?>" @change="const o = $event.target.selectedOptions[0]; $dispatch('category-picked', { hsn: o?.dataset.hsn || '', commType: o?.dataset.commType || '', commBp: +(o?.dataset.commBp || 0), commFixed: +(o?.dataset.commFixed || 0) })">
                    <option value="">Choose the best-fitting subcategory…</option>
                    <?php foreach ($tree as $dept): ?>
                        <optgroup label="<?= $e($dept['name']) ?>">
                            <?php foreach ($dept['subs'] as $sub): ?>
                                <?php $blocked = $sub['block_reason'] !== null && (int) $sub['id'] !== $selectedCategory; ?>
                                <option value="<?= (int) $sub['id'] ?>" data-hsn="<?= $e($sub['default_hsn'] ?? '') ?>" data-comm-type="<?= $e($sub['commission']['type'] ?? 'percent') ?>" data-comm-bp="<?= (int) ($sub['commission']['rate_bp'] ?? 0) ?>" data-comm-fixed="<?= (int) ($sub['commission']['fixed_paise'] ?? 0) ?>" <?= (int) $sub['id'] === $selectedCategory ? 'selected' : '' ?> <?= $blocked ? 'disabled' : '' ?>>
                                    <?= $e($sub['name']) ?><?= $blocked ? ' 🔒' : (($sub['listing_mode'] ?? '') === 'review' ? ' (extra review)' : '') ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <span class="text-xs text-tx3">🔒 = needs a licence you haven't uploaded yet, or not sold on the store. Your approved licences:
                    <?= $licences ? $e(implode(', ', array_map(static fn ($k) => \App\Services\Store\VendorService::DOC_TYPES[$k] ?? $k, array_keys($licences)))) : 'none yet' ?>.
                    <a href="/vendor/documents" class="text-act underline">Upload licences</a></span>
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Brand</span>
                <select name="brand_id" class="<?= $input ?>">
                    <option value="">No brand / own brand</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= (int) $val('brand_id', 0) === (int) $b['id'] ? 'selected' : '' ?>><?= $e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm">
                <span class="text-tx2">…or a brand not in the list</span>
                <input name="brand_new" maxlength="160" value="<?= $e($hasOld ? ($old['brand_new'] ?? '') : '') ?>" class="<?= $input ?>" placeholder="Type the brand name">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Short description <span class="text-tx3">(shown under the name, max 500)</span></span>
                <textarea name="short_desc" rows="2" maxlength="500" class="<?= $input ?>"><?= $e($val('short_desc')) ?></textarea>
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Full description</span>
                <textarea name="description" rows="7" class="<?= $input ?>" placeholder="What it is, key benefits, how to use, ingredients / what's in the box, warnings"><?= $e($val('description')) ?></textarea>
                <span class="text-xs text-tx3">Plain text; line breaks are kept. Don't claim to diagnose, treat or cure any condition.</span>
            </label>
        </div>
    </section>

    <!-- Variants -->
    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Variants, price &amp; stock</h2>
            <button type="button" @click="addVariant()" class="rounded-[7px] border border-ac px-3 py-1 text-xs font-medium text-act hover:bg-acs">+ Add variant</button>
        </div>
        <p class="mt-1 text-xs text-tx3">One row per pack size / flavour / size. Prices include GST. Weight and box size are the <em>packed</em> parcel, used for courier charges.</p>
        <template x-for="(v, i) in variants" :key="i">
            <div class="mt-4 rounded-[10px] border border-ln p-4" :class="v.remove ? 'opacity-50' : ''">
                <input type="hidden" :name="`variants[${i}][id]`" :value="v.id">
                <input type="hidden" :name="`variants[${i}][remove]`" :value="v.remove ? 1 : ''">
                <div class="grid gap-3 sm:grid-cols-4">
                    <label class="block text-xs sm:col-span-2"><span class="text-tx2">Variant name</span>
                        <input :name="`variants[${i}][title]`" x-model="v.title" maxlength="190" class="<?= $input ?>" placeholder="e.g. 60 tablets / Large / Chocolate"></label>
                    <label class="block text-xs sm:col-span-2"><span class="text-tx2">SKU (your code)</span>
                        <input :name="`variants[${i}][sku]`" x-model="v.sku" required maxlength="80" class="<?= $input ?> uppercase"></label>
                    <label class="block text-xs"><span class="text-tx2">MRP ₹</span>
                        <input :name="`variants[${i}][mrp]`" x-model="v.mrp" required inputmode="decimal" class="<?= $input ?>"></label>
                    <label class="block text-xs"><span class="text-tx2">Selling price ₹</span>
                        <input :name="`variants[${i}][price]`" x-model="v.price" required inputmode="decimal" class="<?= $input ?>">
                        <span class="text-[11px] text-emerald-700" x-show="discount(v) > 0" x-text="discount(v) + '% off MRP'"></span></label>
                    <label class="block text-xs"><span class="text-tx2">Stock</span>
                        <input :name="`variants[${i}][stock_qty]`" x-model="v.stock_qty" inputmode="numeric" class="<?= $input ?>"></label>
                    <label class="block text-xs"><span class="text-tx2">Low-stock alert at</span>
                        <input :name="`variants[${i}][low_stock_threshold]`" x-model="v.low_stock_threshold" inputmode="numeric" class="<?= $input ?>"></label>
                    <label class="block text-xs"><span class="text-tx2">Packed weight (g)</span>
                        <input :name="`variants[${i}][weight_g]`" x-model="v.weight_g" inputmode="numeric" class="<?= $input ?>"></label>
                    <label class="block text-xs"><span class="text-tx2">Box L × B × H (cm)</span>
                        <span class="mt-1 flex gap-1">
                            <input :name="`variants[${i}][length_cm]`" x-model="v.length_cm" inputmode="decimal" class="w-full rounded-[7px] border border-ln bg-sf px-2 py-2 text-sm" placeholder="L">
                            <input :name="`variants[${i}][breadth_cm]`" x-model="v.breadth_cm" inputmode="decimal" class="w-full rounded-[7px] border border-ln bg-sf px-2 py-2 text-sm" placeholder="B">
                            <input :name="`variants[${i}][height_cm]`" x-model="v.height_cm" inputmode="decimal" class="w-full rounded-[7px] border border-ln bg-sf px-2 py-2 text-sm" placeholder="H">
                        </span></label>
                    <label class="block text-xs sm:col-span-2"><span class="text-tx2">Barcode / EAN <span class="text-tx3">(optional)</span></span>
                        <input :name="`variants[${i}][barcode]`" x-model="v.barcode" maxlength="40" class="<?= $input ?>"></label>
                </div>
                <div class="mt-3 rounded-[8px] border border-ln bg-sf2 p-3 text-xs" x-show="!v.remove && calc.fees">
                    <template x-if="!est(v)">
                        <p class="text-tx3"><strong class="text-tx2">What you'll earn:</strong> enter the selling price to see an estimate.</p>
                    </template>
                    <template x-if="est(v)">
                        <div>
                            <p class="font-semibold text-tx">What you'll earn per unit (estimate)</p>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <template x-for="sc in est(v).cases" :key="sc.label">
                                    <dl class="grid grid-cols-[1fr_auto] gap-x-3 gap-y-0.5">
                                        <dt class="col-span-2 mb-1 font-medium text-tx2" x-text="sc.label"></dt>
                                        <dt class="text-tx3">Selling price</dt><dd class="text-right" x-text="rs(sc.price)"></dd>
                                        <dt class="text-tx3" x-text="'Commission (' + est(v).commLabel + ')'"></dt><dd class="text-right" x-text="'−' + rs(sc.commission)"></dd>
                                        <dt class="text-tx3">GST on commission (18%)</dt><dd class="text-right" x-text="'−' + rs(sc.commissionGst)"></dd>
                                        <dt class="text-tx3" x-text="'Courier (' + est(v).billedLabel + ' billed)'"></dt><dd class="text-right" x-text="'−' + rs(sc.courier)"></dd>
                                        <template x-if="sc.credit > 0"><dt class="text-tx3">Customer's delivery fee</dt></template>
                                        <template x-if="sc.credit > 0"><dd class="text-right text-emerald-700" x-text="'+' + rs(sc.credit)"></dd></template>
                                        <dt class="border-t border-ln pt-1 font-semibold">You get about</dt>
                                        <dd class="border-t border-ln pt-1 text-right font-semibold" :class="sc.net <= 0 ? 'text-red-700' : (sc.net < sc.price / 2 ? 'text-amber-700' : 'text-emerald-700')"
                                            x-text="rs(sc.net) + ' (' + Math.round(sc.net / sc.price * 100) + '%)'"></dd>
                                    </dl>
                                </template>
                            </div>
                            <p class="mt-2 text-amber-800" x-show="est(v).worst < est(v).price / 2">
                                Courier is a big part of this price. Consider selling it as a pack of 2 or more, or checking the price.</p>
                            <p class="mt-2 text-tx3" x-show="!(+v.weight_g > 0)">No packed weight yet, so 500 g is assumed. Add the weight and box size for a better estimate.</p>
                            <p class="mt-1 text-tx3">Courier estimate: <span x-text="rs(calc.fees.courier_base)"></span> for the first 500 g + <span x-text="rs(calc.fees.courier_addl)"></span> per extra 500 g; the actual Shiprocket charge applies. When several items ship together, one courier charge covers the whole parcel.</p>
                        </div>
                    </template>
                </div>
                <button type="button" x-show="activeCount() > 1 || v.remove" @click="v.remove = !v.remove"
                        class="mt-2 text-xs text-tx3 hover:text-red-600 hover:underline" x-text="v.remove ? 'Undo remove' : 'Remove this variant'"></button>
            </div>
        </template>
    </section>

    <!-- Placement -->
    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <h2 class="font-semibold">Where customers find it</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-tx2">Also show under <span class="text-tx3">(up to 3, optional)</span></span>
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
                <span class="text-xs text-tx3">Hold Ctrl / ⌘ to pick several. E.g. a probiotic under both Gut Health and Senior Wellness.</span>
            </label>
            <div class="text-sm">
                <span class="text-tx2">Health goals it supports</span>
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
    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Specifications <span class="text-sm font-normal text-tx3">(optional)</span></h2>
            <button type="button" @click="specs.push({label:'', value:''})" class="rounded-[7px] border border-ac px-3 py-1 text-xs font-medium text-act hover:bg-acs">+ Add row</button>
        </div>
        <template x-for="(s, i) in specs" :key="i">
            <div class="mt-3 flex gap-2">
                <input :name="`specs[${i}][label]`" x-model="s.label" maxlength="80" class="w-1/3 rounded-[7px] border border-ln bg-sf px-3 py-2 text-sm" placeholder="e.g. Pack size">
                <input :name="`specs[${i}][value]`" x-model="s.value" maxlength="300" class="flex-1 rounded-[7px] border border-ln bg-sf px-3 py-2 text-sm" placeholder="e.g. 60 tablets">
                <button type="button" @click="specs.splice(i, 1)" class="px-2 text-tx3 hover:text-red-600" aria-label="Remove">✕</button>
            </div>
        </template>
    </section>

    <!-- Compliance, tax, returns -->
    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <h2 class="font-semibold">Compliance, tax &amp; returns</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <label class="block text-sm">
                <span class="text-tx2">Licence no. on label</span>
                <input name="license_number" maxlength="80" value="<?= $e($val('license_number')) ?>" class="<?= $input ?>" placeholder="FSSAI / device reg. no.">
                <span class="text-xs text-tx3">Required for food, supplement, AYUSH and device categories.</span>
            </label>
            <?php
            $gstCur = (string) $val('gst_bp', '');
            $gstKnown = $gstCur !== '' && array_key_exists((int) $gstCur, CatalogService::GST_RATES_BP);
            ?>
            <?php if (!empty($hsnList)): ?>
            <!-- HSN from eClinicPro's list; the GST rate follows from it (admin-controlled). -->
            <div class="contents" x-data='hsnPicker(<?= $e(json_encode(['list' => $hsnList, 'code' => (string) $val('hsn_code'), 'rate' => $gstCur], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)'
                 @category-picked.window="if (!code && $event.detail.hsn) setCode($event.detail.hsn)">
                <label class="block text-sm">
                    <span class="text-tx2">HSN code <span class="text-red-600">*</span></span>
                    <input name="hsn_code" maxlength="8" inputmode="numeric" pattern="\d{4,8}" required list="hsn-list" x-model="code" @input="sync()" class="<?= $input ?>" placeholder="Start typing, e.g. 3004">
                    <datalist id="hsn-list">
                        <?php foreach ($hsnList as $h): ?><option value="<?= $e($h['code']) ?>"><?= $e($h['description']) ?></option><?php endforeach; ?>
                    </datalist>
                    <span class="text-xs text-tx3" x-show="match" x-text="match ? match.description : ''"></span>
                    <span class="text-xs text-red-600" x-show="code.length >= 4 && !match" x-cloak>Not in eClinicPro's HSN list. Pick a code from the list, or contact support to add it.</span>
                </label>
                <label class="block text-sm">
                    <span class="text-tx2">GST rate</span>
                    <template x-if="rates.length === 1">
                        <div>
                            <input type="hidden" name="gst_bp" :value="rates[0]">
                            <p class="mt-1 rounded-lg border border-ln bg-sf2 px-3 py-2" x-text="(rates[0] / 100) + '% (set by HSN ' + match.code + ')'"></p>
                        </div>
                    </template>
                    <template x-if="rates.length > 1">
                        <select name="gst_bp" required x-model="rate" class="<?= $input ?>">
                            <option value="">Choose the rate for this product…</option>
                            <template x-for="r in rates" :key="r"><option :value="String(r)" x-text="(r / 100) + '%'" :selected="String(r) === String(rate)"></option></template>
                        </select>
                    </template>
                    <p x-show="rates.length === 0" class="mt-1 rounded-lg border border-dashed border-ln px-3 py-2 text-tx3">Pick an HSN code first</p>
                    <span class="text-xs text-tx3" x-show="rates.length > 1">This HSN has more than one rate; choose the one for this exact product. eClinicPro checks it at review.</span>
                    <span class="text-xs text-tx3" x-show="rates.length <= 1">Prices include GST. The rate is fixed by eClinicPro's HSN list.</span>
                </label>
            </div>
            <?php else: ?>
            <label class="block text-sm">
                <span class="text-tx2">HSN code <span class="text-red-600">*</span></span>
                <input name="hsn_code" maxlength="8" inputmode="numeric" pattern="\d{4,8}" required value="<?= $e($val('hsn_code')) ?>" class="<?= $input ?>">
                <span class="text-xs text-tx3">Printed on the customer's GST invoice.</span>
            </label>
            <label class="block text-sm">
                <span class="text-tx2">GST rate</span>
                <select name="gst_bp" required class="<?= $input ?>">
                    <option value="" <?= $gstKnown ? '' : 'selected' ?>>Choose GST rate…</option>
                    <?php foreach (CatalogService::GST_RATES_BP as $bp => $label): ?>
                        <option value="<?= $bp ?>" <?= $gstKnown && (int) $gstCur === $bp ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="text-xs text-tx3">Prices include GST. Your CA can confirm the rate for this HSN code.</span>
            </label>
            <?php endif; ?>
            <label class="block text-sm">
                <span class="text-tx2">Manufacturer</span>
                <input name="manufacturer" maxlength="190" value="<?= $e($val('manufacturer')) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Country of origin</span>
                <input name="country_of_origin" maxlength="60" value="<?= $e($val('country_of_origin', 'India')) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Return window (days)</span>
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
    <details class="rounded-[10px] border border-ln bg-sf p-6">
        <summary class="cursor-pointer font-semibold">Search engine listing <span class="text-sm font-normal text-tx3">(optional)</span></summary>
        <div class="mt-4 grid gap-4">
            <label class="block text-sm"><span class="text-tx2">SEO title</span>
                <input name="seo_title" maxlength="190" value="<?= $e($val('seo_title')) ?>" class="<?= $input ?>"></label>
            <label class="block text-sm"><span class="text-tx2">SEO description</span>
                <textarea name="seo_description" rows="2" maxlength="300" class="<?= $input ?>"><?= $e($val('seo_description')) ?></textarea></label>
        </div>
    </details>

    <div class="flex flex-wrap items-center gap-3">
        <button class="rounded-[7px] bg-ac px-6 py-2.5 text-sm font-medium text-white hover:opacity-90"><?= $isNew ? 'Save draft & add photos' : 'Save changes' ?></button>
        <?php if (!$isNew && $status === 'live'): ?>
            <span class="text-xs text-tx3">Price and stock changes go live immediately. Changing the name, category, brand or description sends the product back for review.</span>
        <?php endif; ?>
    </div>
    </fieldset>
</form>

<?php if (!$isNew): ?>
<!-- Photos (separate forms: file uploads can't nest in the main form) -->
<section id="photos" class="mt-6 scroll-mt-6 rounded-[10px] border border-ln bg-sf p-6">
    <h2 class="font-semibold">Photos <span class="text-sm font-normal text-tx3">(<?= count($product['images']) ?>/<?= ProductService::MAX_IMAGES ?>)</span></h2>
    <p class="mt-1 text-xs text-tx3">Square photos on a plain background work best. The first photo is the cover. JPG/PNG/WEBP, max 3 MB each.</p>
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <?php foreach ($product['images'] as $idx => $img): ?>
            <div class="relative overflow-hidden rounded-[10px] border border-ln">
                <img src="<?= $e($img['path']) ?>" alt="" class="aspect-square w-full object-cover">
                <?php if ($idx === 0): ?><span class="absolute left-2 top-2 rounded-[7px] bg-ac px-2 py-0.5 text-[10px] font-semibold text-white">Cover</span><?php endif; ?>
                <div class="flex justify-between gap-1 border-t bg-white px-2 py-1.5 text-xs">
                    <?php if ($idx !== 0 && !$locked): ?>
                        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/images/<?= (int) $img['id'] ?>/cover">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-act hover:underline">Make cover</button>
                        </form>
                    <?php else: ?><span></span><?php endif; ?>
                    <?php if (!$locked): ?>
                        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/images/<?= (int) $img['id'] ?>/delete" onsubmit="return confirm('Remove this photo?')">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-tx3 hover:text-red-600 hover:underline">Remove</button>
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
            <button class="rounded-[7px] border border-ac px-4 py-1.5 text-sm font-medium text-act hover:bg-acs">Upload photos</button>
        </form>
    <?php endif; ?>
</section>

<section class="mt-6 flex flex-wrap items-center gap-4 text-sm">
    <?php if ($status === 'live'): ?>
        <a href="https://eclinicpro.com/store/p/<?= $e($product['slug']) ?>" target="_blank" rel="noopener" class="text-act hover:underline">View on store ↗</a>
    <?php endif; ?>
    <?php if (!in_array($status, ['archived', 'disabled'], true)): ?>
        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/archive" onsubmit="return confirm('Archive this product? It will be hidden from customers.')">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="archive">
            <button class="text-tx3 hover:text-red-600 hover:underline">Archive product</button>
        </form>
    <?php elseif ($status === 'archived'): ?>
        <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/archive">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="restore">
            <button class="text-act hover:underline">Restore as draft</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<script>
function hsnPicker(init) {
    return {
        list: init.list || [], code: String(init.code || ''), rate: String(init.rate || ''),
        get match() {
            let best = null;
            for (const r of this.list) {
                if (this.code.startsWith(r.code) && (!best || r.code.length > best.code.length)) best = r;
            }
            return best;
        },
        get rates() { return this.match ? this.match.rates : []; },
        setCode(c) { this.code = String(c); this.sync(); },
        sync() {
            this.code = this.code.replace(/\D/g, '').slice(0, 8);
            const r = this.rates.map(String);
            if (r.length === 1) this.rate = r[0];
            else if (!r.includes(String(this.rate))) this.rate = '';
        },
    };
}
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
        // ---- Earnings estimate (mirrors SellerFeeService::estimate) ----
        calc: init.calc || { fees: null, comm: { type: 'percent', bp: 1000, fixed: 0 } },
        setCommission(d) {
            if (d && d.commType) this.calc.comm = { type: d.commType, bp: d.commBp || 0, fixed: d.commFixed || 0 };
        },
        rs(p) {
            const r = Math.round(p) / 100;
            return '₹' + (Number.isInteger(r) ? r.toLocaleString('en-IN') : r.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        },
        est(v) {
            const f = this.calc.fees, price = Math.round(parseFloat(v.price) * 100);
            if (!f || !(price > 0)) return null;
            const c = this.calc.comm;
            const commission = c.type === 'fixed' ? Math.min(c.fixed, price) : Math.min(Math.round(price * c.bp / 10000), price);
            const commissionGst = Math.round(commission * f.commission_gst_bp / 10000);
            const w = parseInt(v.weight_g, 10) || 0;
            const vol = Math.ceil((parseFloat(v.length_cm) || 0) * (parseFloat(v.breadth_cm) || 0) * (parseFloat(v.height_cm) || 0) / f.vol_divisor * 1000);
            const billed = Math.max(w, vol, 1);
            const slabs = Math.max(1, Math.ceil(billed / f.slab_g));
            const courier = f.courier_base + (slabs - 1) * f.courier_addl;
            const mk = (label, credit) => ({ label, price, commission, commissionGst, courier, credit,
                net: price - commission - commissionGst - (courier - credit) });
            const cases = [];
            if (f.free_above === null || price < f.free_above) {
                cases.push(mk('Bought on its own (customer pays ' + this.rs(f.delivery_fee) + ' delivery)', Math.min(f.delivery_fee, courier)));
            }
            if (f.free_above !== null) {
                cases.push(mk('In an order of ' + this.rs(f.free_above) + '+ (free delivery for the customer)', 0));
            }
            return {
                price, cases, worst: Math.min(...cases.map(x => x.net)),
                commLabel: c.type === 'fixed' ? this.rs(c.fixed) + ' per unit' : (c.bp / 100) + '%',
                billedLabel: (slabs * f.slab_g / 1000) + ' kg',
            };
        },
    };
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
