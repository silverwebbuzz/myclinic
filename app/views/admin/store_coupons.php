<?php
/**
 * /admin/store/coupons
 *
 * @var list<array<string,mixed>> $rows
 * @var list<array<string,mixed>> $vendors
 * @var list<array<string,mixed>> $categories
 */
use App\Services\Store\CouponService;
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$in = 'mt-1 w-full rounded border px-2 py-1.5 text-sm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store coupons — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100" x-data="{ edit: null }">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">Store coupons</h1>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <p class="text-sm text-slate-500">Coupons never apply to "no promotion" items (infant food, feeding bottles). <strong>Platform-funded</strong> discounts come out of your margin and sellers still earn the full price; <strong>seller-funded</strong> coupons (limited to one seller) reduce that seller's earnings. Usage counts only on paid orders.</p>

    <form method="post" action="/admin/store/coupons" class="grid gap-3 rounded-xl border bg-white p-5 shadow-sm sm:grid-cols-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="id" :value="edit ? edit.id : ''">
        <h2 class="font-semibold sm:col-span-4" x-text="edit ? 'Edit ' + edit.code : 'New coupon'"></h2>
        <label class="text-sm">Code<input name="code" required :value="edit ? edit.code : ''" class="<?= $in ?> uppercase" placeholder="WELCOME10"></label>
        <label class="text-sm">Type<select name="type" class="<?= $in ?>">
            <option value="percent" :selected="edit && edit.type==='percent'">% off</option>
            <option value="fixed" :selected="edit && edit.type==='fixed'">₹ off</option>
            <option value="free_shipping" :selected="edit && edit.type==='free_shipping'">Free shipping</option></select></label>
        <label class="text-sm">Value (% or ₹)<input name="value" :value="edit ? (edit.type==='percent' ? edit.value/100 : (edit.type==='fixed' ? edit.value/100 : '')) : ''" class="<?= $in ?>"></label>
        <label class="text-sm">Max discount ₹ <span class="text-slate-400">(opt.)</span><input name="max_discount" :value="edit && edit.max_discount_paise ? edit.max_discount_paise/100 : ''" class="<?= $in ?>"></label>
        <label class="text-sm">Min eligible order ₹<input name="min_order" :value="edit ? edit.min_order_paise/100 : ''" class="<?= $in ?>"></label>
        <label class="text-sm">Funded by<select name="funded_by" class="<?= $in ?>">
            <option value="platform" :selected="!edit || edit.funded_by==='platform'">eClinicPro (platform)</option>
            <option value="vendor" :selected="edit && edit.funded_by==='vendor'">Seller</option></select></label>
        <label class="text-sm">Only this seller <span class="text-slate-400">(opt.)</span><select name="vendor_id" class="<?= $in ?>">
            <option value="">All sellers</option>
            <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" :selected="edit && edit.vendor_id == <?= (int) $v['id'] ?>"><?= $e($v['display_name']) ?></option><?php endforeach; ?></select></label>
        <label class="text-sm">Only this category <span class="text-slate-400">(opt.)</span><select name="category_id" class="<?= $in ?>">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" :selected="edit && edit.category_id == <?= (int) $c['id'] ?>"><?= (int) $c['parent_id'] === 0 ? '▸ ' : '   ' ?><?= $e($c['name']) ?></option><?php endforeach; ?></select></label>
        <label class="text-sm">Starts<input type="date" name="starts_at" :value="edit && edit.starts_at ? edit.starts_at.substring(0,10) : ''" class="<?= $in ?>"></label>
        <label class="text-sm">Ends<input type="date" name="ends_at" :value="edit && edit.ends_at ? edit.ends_at.substring(0,10) : ''" class="<?= $in ?>"></label>
        <label class="text-sm">Total uses <span class="text-slate-400">(blank = unlimited)</span><input name="limit_total" type="number" min="1" :value="edit ? edit.limit_total : ''" class="<?= $in ?>"></label>
        <label class="text-sm">Uses per customer<input name="limit_per_customer" type="number" min="1" :value="edit ? edit.limit_per_customer : 1" class="<?= $in ?>"></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" :checked="!edit || edit.is_active == 1"> Active</label>
        <div class="flex gap-2 sm:col-span-3 sm:justify-end">
            <button type="button" x-show="edit" @click="edit = null" class="rounded border px-3 py-1.5 text-sm">Cancel edit</button>
            <button class="rounded bg-slate-800 px-4 py-1.5 text-sm text-white">Save coupon</button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Code</th><th class="px-4 py-2">Offer</th><th class="px-4 py-2">Scope</th><th class="px-4 py-2">Funded</th><th class="px-4 py-2">Valid</th><th class="px-4 py-2 text-right">Used</th><th class="px-4 py-2"></th></tr></thead>
            <tbody class="divide-y">
            <?php if (!$rows): ?><tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No coupons yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $c): ?>
                <tr class="<?= (int) $c['is_active'] === 1 ? '' : 'opacity-50' ?>">
                    <td class="px-4 py-2 font-mono font-semibold"><?= $e($c['code']) ?></td>
                    <td class="px-4 py-2"><?= $e(CouponService::describe($c)) ?></td>
                    <td class="px-4 py-2 text-slate-600"><?= $e($c['vendor_name'] ?? 'All sellers') ?> · <?= $e($c['category_name'] ?? 'all categories') ?></td>
                    <td class="px-4 py-2"><?= $c['funded_by'] === 'vendor' ? 'Seller' : 'Platform' ?></td>
                    <td class="px-4 py-2 text-xs text-slate-500"><?= $e(substr((string) ($c['starts_at'] ?? ''), 0, 10) ?: 'now') ?> → <?= $e(substr((string) ($c['ends_at'] ?? ''), 0, 10) ?: 'no end') ?></td>
                    <td class="px-4 py-2 text-right"><?= (int) $c['used_count'] ?><?= $c['limit_total'] !== null ? ' / ' . (int) $c['limit_total'] : '' ?></td>
                    <td class="px-4 py-2 text-right"><button type="button" class="text-sky-700 hover:underline" @click='edit = <?= $e(json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>; window.scrollTo({top:0,behavior:"smooth"})'>Edit</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
