<?php
/**
 * @var array<string,mixed> $vendor
 * @var bool $locked
 * @var bool $cryptoReady
 * @var array<string,string> $businessTypes
 */
$pageTitle = 'Business profile';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#17774f] focus:outline-none disabled:bg-slate-100 disabled:text-slate-500';
ob_start();
?>
<h1 class="text-2xl font-semibold">Business profile</h1>
<form method="post" action="/vendor/profile" enctype="multipart/form-data" class="mt-5 space-y-6">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

    <section class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <h2 class="font-semibold">Store details <span class="text-sm font-normal text-slate-500">(shown to customers)</span></h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">Store name</span>
                <input name="display_name" required maxlength="160" value="<?= $e($vendor['display_name']) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">About your store</span>
                <textarea name="description" rows="4" maxlength="2000" class="<?= $input ?>" placeholder="What you sell, since when, what makes you trustworthy"><?= $e($vendor['description'] ?? '') ?></textarea>
            </label>
            <div class="text-sm sm:col-span-2">
                <span class="text-slate-600">Logo</span>
                <div class="mt-1 flex items-center gap-4">
                    <?php if (!empty($vendor['logo_path'])): ?>
                        <img src="<?= $e($vendor['logo_path']) ?>" alt="" class="h-14 w-14 rounded-xl border object-cover">
                    <?php endif; ?>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="text-sm">
                </div>
                <span class="text-xs text-slate-400">Square JPG/PNG/WEBP, max 2 MB.</span>
            </div>
            <label class="block text-sm">
                <span class="text-slate-600">Dispatch time (days)</span>
                <input type="number" name="handling_days" min="1" max="10" value="<?= (int) ($vendor['handling_days'] ?? 2) ?>" class="<?= $input ?>">
                <span class="text-xs text-slate-400">Days from order to handing over to the courier.</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Default return window (days)</span>
                <input type="number" name="default_return_window_days" min="0" max="30" value="<?= (int) ($vendor['default_return_window_days'] ?? 7) ?>" class="<?= $input ?>">
                <span class="text-xs text-slate-400">0 = no returns. Can be overridden per product.</span>
            </label>
        </div>
    </section>

    <section class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <h2 class="font-semibold">Contact</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">Contact person</span>
                <input name="contact_name" required value="<?= $e($vendor['contact_name']) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Mobile</span>
                <input name="phone" required value="<?= $e(preg_replace('/^\+91/', '', (string) $vendor['phone'])) ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">Email</span>
                <input value="<?= $e($vendor['email']) ?>" disabled class="<?= $input ?>">
                <span class="text-xs text-slate-400">Your login email. Contact support to change it.</span>
            </label>
        </div>
    </section>

    <section class="rounded-2xl border border-[#ece8df] bg-white p-6">
        <h2 class="font-semibold">Legal &amp; tax</h2>
        <?php if ($locked): ?>
            <p class="mt-1 text-sm text-amber-700">Locked while your account is under review or approved. Email help@eclinicpro.com to change these.</p>
        <?php endif; ?>
        <?php if (!$cryptoReady && !$locked): ?>
            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Secure storage isn't configured on the server yet, so PAN can't be saved right now. You can save the other details.</p>
        <?php endif; ?>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span class="text-slate-600">Legal business name <span class="text-slate-400">(as on PAN / GST)</span></span>
                <input name="legal_name" maxlength="190" value="<?= $e($vendor['legal_name'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?> class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Business type</span>
                <select name="business_type" <?= $locked ? 'disabled' : '' ?> class="<?= $input ?>">
                    <option value="">Select…</option>
                    <?php foreach ($businessTypes as $k => $label): ?>
                        <option value="<?= $e($k) ?>" <?= ($vendor['business_type'] ?? '') === $k ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">GSTIN <span class="text-slate-400">(if registered)</span></span>
                <input name="gstin" maxlength="15" value="<?= $e($vendor['gstin'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?> class="<?= $input ?> uppercase" placeholder="24ABCDE1234F1Z5">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">PAN</span>
                <input name="pan" maxlength="10" <?= $locked ? 'disabled' : '' ?> class="<?= $input ?> uppercase"
                       placeholder="<?= !empty($vendor['pan_last4']) ? 'On file: ••••••' . $e($vendor['pan_last4']) . ' (type to replace)' : 'ABCDE1234F' ?>">
                <span class="text-xs text-slate-400">Stored encrypted. Only the last 4 characters are shown.</span>
            </label>
        </div>
    </section>

    <button class="rounded-full bg-[#0e4d34] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#17774f]">Save profile</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
