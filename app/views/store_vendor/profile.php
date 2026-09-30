<?php
/**
 * @var array<string,mixed> $vendor
 * @var bool $locked
 * @var bool $cryptoReady
 * @var array<string,string> $businessTypes
 */
$pageTitle = 'Business profile';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
// Required to submit for review (VendorService::checklist); a PAN already on file needn't be retyped.
$gstinRequired = \App\Services\Store\StoreSettings::get('store_require_gstin', '1') === '1';
$panRequired = empty($vendor['pan_last4']) && $cryptoReady;
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 text-sm focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none disabled:bg-sf2 disabled:text-tx3';
ob_start();
?>
<h1 class="text-[22px] font-semibold tracking-[-.015em]">Business profile</h1>
<?php if (isset($commissionBp)): ?>
    <?php $cBp = (int) $commissionBp; $ex = (int) round(10000 * $cBp / 10000); ?>
    <section class="mt-5 flex flex-wrap items-center justify-between gap-4 rounded-[10px] border border-ac/20 bg-acs p-5">
        <div>
            <h2 class="font-semibold">Your commission</h2>
            <p class="mt-1 text-sm text-tx2">Charged on your selling price <strong>before GST</strong>, plus 18% GST on the commission, only on items you sell.
                Example: on a ₹118 item (₹100 + ₹18 GST) the commission is ₹<?= $e(\App\Services\Store\ProductService::rupees($ex)) ?> + ₹<?= $e(\App\Services\Store\ProductService::rupees((int) round($ex * 0.18))) ?> GST.</p>
            <p class="mt-1 text-xs text-tx3">Agreed with eClinicPro. To discuss it, contact the eClinicPro seller team. Changes apply to new orders only.</p>
        </div>
        <p class="text-3xl font-semibold text-act"><?= $e(\App\Services\Store\CommissionService::pct($cBp)) ?></p>
    </section>
<?php endif; ?>
<form method="post" action="/vendor/profile" enctype="multipart/form-data" class="mt-5 space-y-6">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <h2 class="font-semibold">Store details <span class="text-sm font-normal text-tx3">(shown to customers)</span></h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Store name</span>
                <input name="display_name" required minlength="2" maxlength="160" value="<?= $e($vendor['display_name']) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">About your store</span>
                <textarea name="description" rows="4" maxlength="2000" class="<?= $input ?>" placeholder="What you sell, since when, what makes you trustworthy"><?= $e($vendor['description'] ?? '') ?></textarea>
            </label>
            <div class="text-sm sm:col-span-2">
                <span class="text-tx2">Logo</span>
                <div class="mt-1 flex items-center gap-4">
                    <?php if (!empty($vendor['logo_path'])): ?>
                        <img src="<?= $e($vendor['logo_path']) ?>" alt="" class="h-14 w-14 rounded-[10px] border object-cover">
                    <?php endif; ?>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="text-sm">
                </div>
                <span class="text-xs text-tx3">Square JPG/PNG/WEBP, max 2 MB.</span>
            </div>
            <label class="block text-sm">
                <span class="text-tx2">Dispatch time (days)</span>
                <input type="number" name="handling_days" min="1" max="10" value="<?= (int) ($vendor['handling_days'] ?? 2) ?>" class="<?= $input ?>">
                <span class="text-xs text-tx3">Days from order to handing over to the courier.</span>
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Default return window (days)</span>
                <input type="number" name="default_return_window_days" min="0" max="30" value="<?= (int) ($vendor['default_return_window_days'] ?? 7) ?>" class="<?= $input ?>">
                <span class="text-xs text-tx3">0 = no returns. Can be overridden per product.</span>
            </label>
        </div>
    </section>

    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <h2 class="font-semibold">Contact</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-tx2">Contact person</span>
                <input name="contact_name" required value="<?= $e($vendor['contact_name']) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Mobile</span>
                <input name="phone" required inputmode="numeric" data-validate="mobile" value="<?= $e(preg_replace('/^\+91/', '', (string) $vendor['phone'])) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Email</span>
                <input value="<?= $e($vendor['email']) ?>" disabled class="<?= $input ?>">
                <span class="text-xs text-tx3">Your login email. Contact support to change it.</span>
            </label>
        </div>
    </section>

    <section class="rounded-[10px] border border-ln bg-sf p-6">
        <h2 class="font-semibold">Legal &amp; tax</h2>
        <?php if ($locked): ?>
            <p class="mt-1 text-sm text-amber-700">Locked while your account is under review or approved. Email help@eclinicpro.com to change these.</p>
        <?php endif; ?>
        <?php if (!$cryptoReady && !$locked): ?>
            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Secure storage isn't configured on the server yet, so PAN can't be saved right now. You can save the other details.</p>
        <?php endif; ?>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span class="text-tx2">Legal business name <span class="text-tx3">(as on PAN / GST)</span></span>
                <input name="legal_name" maxlength="190" value="<?= $e($vendor['legal_name'] ?? '') ?>" <?= $locked ? 'disabled' : 'required' ?> class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Business type</span>
                <select name="business_type" <?= $locked ? 'disabled' : 'required' ?> class="<?= $input ?>">
                    <option value="">Select…</option>
                    <?php foreach ($businessTypes as $k => $label): ?>
                        <option value="<?= $e($k) ?>" <?= ($vendor['business_type'] ?? '') === $k ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm">
                <span class="text-tx2">GSTIN<?= $gstinRequired ? '' : ' <span class="text-tx3">(if registered)</span>' ?></span>
                <input name="gstin" maxlength="15" data-validate="gstin" value="<?= $e($vendor['gstin'] ?? '') ?>" <?= $locked ? 'disabled' : ($gstinRequired ? 'required' : '') ?> class="<?= $input ?> uppercase" placeholder="24ABCDE1234F1Z5">
            </label>
            <label class="block text-sm">
                <span class="text-tx2">PAN</span>
                <input name="pan" maxlength="10" data-validate="pan" <?= $locked ? 'disabled' : ($panRequired ? 'required' : '') ?> class="<?= $input ?> uppercase"
                       placeholder="<?= !empty($vendor['pan_last4']) ? 'On file: ••••••' . $e($vendor['pan_last4']) . ' (type to replace)' : 'ABCDE1234F' ?>">
                <span class="text-xs text-tx3">Stored encrypted. Only the last 4 characters are shown.</span>
            </label>
        </div>
    </section>

    <button class="rounded-[7px] bg-ac px-6 py-2.5 text-sm font-medium text-white hover:opacity-90">Save profile</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
