<?php
$pageTitle = 'Seller login';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <h1 class="text-xl font-semibold">Log in to sell</h1>
    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= $e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/vendor/login" class="mt-5 space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <label class="block text-sm">
            <span class="text-tx2">Email</span>
            <input name="email" type="email" required autocomplete="email" value="<?= $e($email ?? '') ?>"
                   class="mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none">
        </label>
        <label class="block text-sm">
            <span class="flex items-baseline justify-between"><span class="text-tx2">Password</span>
                <a href="/vendor/forgot-password" class="text-xs font-medium text-act hover:underline">Forgot password?</a></span>
            <input name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none">
        </label>
        <button class="w-full rounded-[7px] bg-ac py-2.5 text-sm font-medium text-white hover:opacity-90">Log in</button>
    </form>
</div>
<p class="mt-4 text-center text-sm text-tx2">New seller? <a href="/vendor/register" class="font-medium text-act hover:underline">Create a seller account</a></p>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
