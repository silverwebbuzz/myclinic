<?php
/**
 * Add / edit an AFFILIATE product (seller with vendor_type = 'affiliate').
 * Customers see the photo, title and price on the store and buy on the
 * partner's site through /store/go/{id}. No variants, stock, GST or courier.
 *
 * @var array<string,mixed>|null $product  ProductService::load() result, null when new
 * @var array<string,mixed> $old           POST to re-populate after an error
 * @var string|null $formError
 * @var list<array<string,mixed>> $tree    departments with subs (+ block_reason)
 * @var list<array<string,mixed>> $brands
 * @var list<string> $problems
 * @var array{days30:int,total:int}|null $clicks
 */
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isNew = $product === null;
$pageTitle = $isNew ? 'Add affiliate product' : 'Edit affiliate product';
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 text-sm focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$hasOld = $old !== [];
$val = static function (string $key, $default = '') use ($old, $hasOld, $product) {
    if ($hasOld) {
        return $old[$key] ?? $default;
    }

    return $product[$key] ?? $default;
};

// Price / MRP / availability live on the one hidden variant.
$variant = null;
foreach (($product['variants'] ?? []) as $v) {
    if ((int) $v['is_active'] === 1) {
        $variant = $v;
        break;
    }
}
if ($hasOld) {
    $price = (string) ($old['price'] ?? '');
    $mrp = (string) ($old['mrp'] ?? '');
    $available = !empty($old['available']);
} else {
    $price = $variant ? str_replace(',', '', ProductService::rupees((int) $variant['price_paise'])) : '';
    $mrp = $variant && (int) $variant['mrp_paise'] > (int) $variant['price_paise']
        ? str_replace(',', '', ProductService::rupees((int) $variant['mrp_paise'])) : '';
    $available = $variant ? (int) $variant['stock_qty'] > 0 : true;
}

$selectedCategory = (int) $val('category_id', 0);
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
    <h1 class="text-[22px] font-semibold tracking-[-.015em]"><?= $isNew ? 'Add an affiliate product' : $e($product['name']) ?></h1>
    <?php if (!$isNew): ?><span class="inline-flex h-[22px] items-center whitespace-nowrap rounded-md px-2 text-xs font-medium <?= $badge[1] ?>"><?= $e($badge[0]) ?></span><?php endif; ?>
</div>
<p class="mt-1 text-sm text-tx3">Customers see the photo, title and price, and buy on the partner's website (opens in a new tab). No stock, GST, shipping or returns to manage here.</p>

<?php if (!$isNew && in_array($status, ['rejected', 'disabled'], true) && !empty($product['review_note'])): ?>
    <div class="mt-4 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>From the eClinicPro team:</strong> <?= $e($product['review_note']) ?></div>
<?php endif; ?>
<?php if ($formError): ?>
    <div class="mt-4 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= $e($formError) ?></div>
<?php endif; ?>

<?php if (!$isNew && in_array($status, ['draft', 'rejected'], true)): ?>
    <section class="mt-4 rounded-[10px] border border-ac/20 bg-acs p-5">
        <?php if ($problems): ?>
            <h2 class="font-semibold text-act">Before you can publish</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-tx2">
                <?php foreach ($problems as $pr): ?><li><?= $e($pr) ?></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-tx2"><strong>Ready.</strong> Publishing puts it on the store straight away.</p>
                <form method="post" action="/vendor/products/<?= (int) $product['id'] ?>/submit">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="rounded-[7px] bg-ac px-4 py-1.5 text-[13px] font-medium text-white hover:opacity-90">Publish</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<form method="post" action="<?= $isNew ? '/vendor/products' : '/vendor/products/' . (int) $product['id'] ?>" class="mt-5 space-y-6">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <fieldset <?= $locked ? 'disabled' : '' ?> class="space-y-6">

    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <h2 class="font-semibold">Product</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Product title</span>
                <input name="name" required minlength="3" maxlength="255" value="<?= $e($val('name')) ?>" class="<?= $input ?>" placeholder="e.g. Himalaya Ashvagandha 60 tablets">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Category</span>
                <select name="category_id" required class="<?= $input ?>">
                    <option value="">Choose the best-fitting subcategory…</option>
                    <?php foreach ($tree as $dept): ?>
                        <optgroup label="<?= $e($dept['name']) ?>">
                            <?php foreach ($dept['subs'] as $sub): ?>
                                <?php $blocked = $sub['block_reason'] !== null && (int) $sub['id'] !== $selectedCategory; ?>
                                <option value="<?= (int) $sub['id'] ?>" <?= (int) $sub['id'] === $selectedCategory ? 'selected' : '' ?> <?= $blocked ? 'disabled' : '' ?>>
                                    <?= $e($sub['name']) ?><?= $blocked ? ' 🔒' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <span class="text-xs text-tx3">🔒 = not sold on the store (e.g. medicines).</span>
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Brand</span>
                <select name="brand_id" class="<?= $input ?>">
                    <option value="">Choose the brand…</option>
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
                <span class="text-tx2">Short description <span class="text-tx3">(optional, helps search)</span></span>
                <textarea name="short_desc" rows="2" maxlength="500" class="<?= $input ?>"><?= $e($val('short_desc')) ?></textarea>
            </label>
        </div>
    </section>

    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <h2 class="font-semibold">Price &amp; link</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-tx2">Price ₹ <span class="text-tx3">(as on the partner site)</span></span>
                <input name="price" required inputmode="decimal" value="<?= $e($price) ?>" class="<?= $input ?>" placeholder="e.g. 249">
            </label>
            <label class="block text-sm">
                <span class="text-tx2">MRP ₹ <span class="text-tx3">(optional, shows "% off")</span></span>
                <input name="mrp" inputmode="decimal" value="<?= $e($mrp) ?>" class="<?= $input ?>" placeholder="e.g. 299">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Affiliate link</span>
                <input name="affiliate_url" type="url" required maxlength="1000" value="<?= $e($val('affiliate_url')) ?>" class="<?= $input ?>" placeholder="https://…">
                <span class="text-xs text-tx3">Paste the full tracking link from the affiliate programme. It opens in a new tab.</span>
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Website name on the button <span class="text-tx3">(optional)</span></span>
                <input name="affiliate_merchant" maxlength="80" value="<?= $e($val('affiliate_merchant')) ?>" class="<?= $input ?>" placeholder="e.g. Amazon, Himalaya">
                <span class="text-xs text-tx3">Shown as "Buy on Amazon ↗". Blank = "Buy now ↗".</span>
            </label>
            <label class="flex items-center gap-2 self-center text-sm">
                <input type="checkbox" name="available" value="1" <?= $available ? 'checked' : '' ?>> Available on the partner site
                <span class="text-xs text-tx3">(untick to show "Out of stock")</span>
            </label>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <button class="rounded-[7px] bg-ac px-6 py-2.5 text-sm font-medium text-white hover:opacity-90"><?= $isNew ? 'Save draft & add photo' : 'Save changes' ?></button>
        <?php if (!$isNew && $status === 'live'): ?>
            <span class="text-xs text-tx3">Changes go live immediately. Check the price on the partner site regularly.</span>
        <?php endif; ?>
    </div>
    </fieldset>
</form>

<?php if (!$isNew): ?>
<section id="photos" class="mt-6 scroll-mt-6 rounded-[10px] border border-ln bg-sf p-6">
    <h2 class="font-semibold">Photos <span class="text-sm font-normal text-tx3">(<?= count($product['images']) ?>/<?= ProductService::MAX_IMAGES ?>)</span></h2>
    <p class="mt-1 text-xs text-tx3">Use a photo you are allowed to use (from the brand or the affiliate programme's banner/creative kit). The first photo is the cover. JPG/PNG/WEBP, max 3 MB.</p>
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

<?php if ($clicks !== null): ?>
<section class="mt-6 rounded-[10px] border border-ln bg-sf p-6 text-sm">
    <h2 class="font-semibold">Clicks to the partner site</h2>
    <p class="mt-2 text-tx2"><strong class="text-lg"><?= (int) $clicks['days30'] ?></strong> in the last 30 days · <?= (int) $clicks['total'] ?> in total</p>
    <p class="mt-1 text-xs text-tx3">Counted when a customer taps "Buy" on the store or in the app. Compare with the affiliate programme's own report (some clicks are bots).</p>
</section>
<?php endif; ?>

<section class="mt-6 flex flex-wrap items-center gap-4 text-sm">
    <?php if (!empty($product['affiliate_url'])): ?>
        <a href="<?= $e($product['affiliate_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-act hover:underline">Test the affiliate link ↗</a>
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
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
