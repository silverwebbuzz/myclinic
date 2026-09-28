<?php
/**
 * @var list<array<string,mixed>> $addresses
 */
$pageTitle = 'Addresses';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#17774f] focus:outline-none';
$typeLabel = ['pickup' => 'Pickup / warehouse', 'return' => 'Return', 'registered' => 'Registered office'];
ob_start();
?>
<h1 class="text-2xl font-semibold">Addresses</h1>
<p class="mt-1 text-sm text-slate-500">Couriers collect your orders from the <strong>pickup</strong> address. Customer returns go to the <strong>return</strong> address.</p>

<div class="mt-5 grid gap-4 md:grid-cols-2">
    <?php if (!$addresses): ?>
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500 md:col-span-2">No addresses yet. Add your pickup address below.</p>
    <?php endif; ?>
    <?php foreach ($addresses as $a): ?>
        <div class="rounded-2xl border border-[#ece8df] bg-white p-5 text-sm">
            <div class="flex items-center justify-between">
                <span class="rounded-full bg-[#edf5ef] px-2.5 py-0.5 text-xs font-semibold text-[#0e4d34]"><?= $e($typeLabel[$a['type']] ?? $a['type']) ?></span>
                <form method="post" action="/vendor/addresses/<?= (int) $a['id'] ?>/remove" onsubmit="return confirm('Remove this address?')">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="text-xs text-slate-500 hover:text-red-600 hover:underline">Remove</button>
                </form>
            </div>
            <div class="mt-3 font-medium"><?= $e($a['contact_name']) ?> · <?= $e($a['phone']) ?></div>
            <div class="mt-1 text-slate-600"><?= $e($a['line1']) ?><?= !empty($a['line2']) ? ', ' . $e($a['line2']) : '' ?><br><?= $e($a['city']) ?>, <?= $e($a['state']) ?> – <?= $e($a['pincode']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<form method="post" action="/vendor/addresses" class="mt-6 rounded-2xl border border-[#ece8df] bg-white p-6" x-data="{ type: 'pickup' }">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <h2 class="font-semibold">Add an address</h2>
    <p class="mt-1 text-xs text-slate-500">Adding a new address of a type replaces the current one for new orders. Orders already in progress keep their original address.</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="block text-sm">
            <span class="text-slate-600">Address type</span>
            <select name="type" x-model="type" class="<?= $input ?>">
                <?php foreach ($typeLabel as $k => $label): ?>
                    <option value="<?= $e($k) ?>"><?= $e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="flex items-end gap-2 pb-2 text-sm text-slate-600" x-show="type === 'pickup'">
            <input type="checkbox" name="same_for_return" value="1" checked> Also use as return address
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Contact name</span>
            <input name="contact_name" required class="<?= $input ?>">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Mobile</span>
            <input name="phone" required inputmode="numeric" class="<?= $input ?>" placeholder="10-digit mobile">
        </label>
        <label class="block text-sm sm:col-span-2">
            <span class="text-slate-600">Address line 1</span>
            <input name="line1" required maxlength="255" class="<?= $input ?>" placeholder="Shop / building, street">
        </label>
        <label class="block text-sm sm:col-span-2">
            <span class="text-slate-600">Address line 2 <span class="text-slate-400">(optional)</span></span>
            <input name="line2" maxlength="255" class="<?= $input ?>" placeholder="Area, landmark">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">City</span>
            <input name="city" required class="<?= $input ?>">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">State</span>
            <input name="state" required class="<?= $input ?>">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Pincode</span>
            <input name="pincode" required inputmode="numeric" maxlength="6" class="<?= $input ?>">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Email <span class="text-slate-400">(optional)</span></span>
            <input name="email" type="email" class="<?= $input ?>">
        </label>
    </div>
    <button class="mt-5 rounded-full bg-[#0e4d34] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#17774f]">Save address</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
