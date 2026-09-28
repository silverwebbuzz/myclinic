<?php
$pageTitle = 'Become a seller';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
$input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#17774f] focus:outline-none';
ob_start();
?>
<div class="rounded-2xl border border-[#ece8df] bg-white p-7 shadow-sm">
    <h1 class="text-xl font-semibold">Sell on eClinicPro Store</h1>
    <p class="mt-1 text-sm text-slate-500">Reach customers looking for trusted health &amp; wellness products. Setup takes about 10 minutes; keep your PAN, bank details and cancelled cheque handy.</p>
    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= $e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/vendor/register" class="mt-5 space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <label class="block text-sm">
            <span class="text-slate-600">Store / business name</span>
            <input name="business_name" required maxlength="160" value="<?= $e($old['business_name'] ?? '') ?>" class="<?= $input ?>" placeholder="As customers will see it">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Contact person</span>
            <input name="contact_name" required maxlength="160" value="<?= $e($old['contact_name'] ?? '') ?>" class="<?= $input ?>">
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">Email (your login)</span>
                <input name="email" type="email" required value="<?= $e($old['email'] ?? '') ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Mobile</span>
                <div class="mt-1 flex rounded-lg border border-slate-300 focus-within:border-[#17774f]">
                    <span class="flex items-center border-r border-slate-200 px-3 text-slate-500">+91</span>
                    <input name="phone" type="tel" inputmode="numeric" required maxlength="14" value="<?= $e(preg_replace('/^\+91/', '', (string) ($old['phone'] ?? ''))) ?>" class="w-full rounded-r-lg px-3 py-2 focus:outline-none">
                </div>
            </label>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">Password</span>
                <input name="password" type="password" required minlength="8" autocomplete="new-password" class="<?= $input ?>">
                <span class="text-xs text-slate-400">8+ characters</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Confirm password</span>
                <input name="password_confirm" type="password" required minlength="8" autocomplete="new-password" class="<?= $input ?>">
            </label>
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-600">
            <input type="checkbox" name="accept_terms" value="1" class="mt-1" required>
            <span>I agree to list only genuine, legally saleable products, keep valid licences for regulated items, and follow the eClinicPro Store seller terms.</span>
        </label>
        <button class="w-full rounded-full bg-[#0e4d34] py-2.5 text-sm font-medium text-white hover:bg-[#17774f]">Create seller account</button>
    </form>
</div>
<p class="mt-4 text-center text-sm text-slate-600">Already selling? <a href="/vendor/login" class="font-medium text-[#17774f] hover:underline">Log in</a></p>
<?php
$content = ob_get_clean();
$maxWidth = 'max-w-xl';
require __DIR__ . '/_auth_layout.php';
