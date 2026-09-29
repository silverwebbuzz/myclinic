<?php
$pageTitle = 'Become a seller';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <h1 class="text-xl font-semibold">Sell on eClinicPro Store</h1>
    <p class="mt-1 text-sm text-tx3">Reach customers looking for trusted health &amp; wellness products. Setup takes about 10 minutes; keep your PAN, bank details and cancelled cheque handy.</p>
    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= $e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/vendor/register" class="mt-5 space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <label class="block text-sm">
            <span class="text-tx2">Store / business name</span>
            <input name="business_name" required maxlength="160" value="<?= $e($old['business_name'] ?? '') ?>" class="<?= $input ?>" placeholder="As customers will see it">
        </label>
        <label class="block text-sm">
            <span class="text-tx2">Contact person</span>
            <input name="contact_name" required maxlength="160" value="<?= $e($old['contact_name'] ?? '') ?>" class="<?= $input ?>">
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-tx2">Email (your login)</span>
                <input name="email" type="email" required value="<?= $e($old['email'] ?? '') ?>" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Mobile</span>
                <div class="mt-1 flex rounded-[7px] border border-ln bg-sf focus-within:border-ac">
                    <span class="flex items-center border-r border-ln px-3 text-tx3">+91</span>
                    <input name="phone" type="tel" inputmode="numeric" required maxlength="14" value="<?= $e(preg_replace('/^\+91/', '', (string) ($old['phone'] ?? ''))) ?>" class="w-full rounded-r-lg px-3 py-2 focus:outline-none">
                </div>
            </label>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-tx2">Password</span>
                <input name="password" type="password" required minlength="8" autocomplete="new-password" class="<?= $input ?>">
                <span class="text-xs text-tx3">8+ characters</span>
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Confirm password</span>
                <input name="password_confirm" type="password" required minlength="8" autocomplete="new-password" class="<?= $input ?>">
            </label>
        </div>
        <label class="flex items-start gap-2 text-sm text-tx2">
            <input type="checkbox" name="accept_terms" value="1" class="mt-1" required>
            <span>I agree to list only genuine, legally saleable products, keep valid licences for regulated items, and follow the eClinicPro Store seller terms.</span>
        </label>
        <button class="w-full rounded-[7px] bg-ac py-2.5 text-sm font-medium text-white hover:opacity-90">Create seller account</button>
    </form>
</div>
<p class="mt-4 text-center text-sm text-tx2">Already selling? <a href="/vendor/login" class="font-medium text-act hover:underline">Log in</a></p>
<?php
$content = ob_get_clean();
$maxWidth = 'max-w-xl';
require __DIR__ . '/_auth_layout.php';
