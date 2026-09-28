<?php
$pageTitle = 'Seller login';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<div class="rounded-2xl border border-[#ece8df] bg-white p-7 shadow-sm">
    <h1 class="text-xl font-semibold">Log in to sell</h1>
    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= $e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/vendor/login" class="mt-5 space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <label class="block text-sm">
            <span class="text-slate-600">Email</span>
            <input name="email" type="email" required autocomplete="email" value="<?= $e($email ?? '') ?>"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#17774f] focus:outline-none">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Password</span>
            <input name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#17774f] focus:outline-none">
        </label>
        <button class="w-full rounded-full bg-[#0e4d34] py-2.5 text-sm font-medium text-white hover:bg-[#17774f]">Log in</button>
    </form>
    <p class="mt-4 text-center text-xs text-slate-500">Forgot your password? Email <a class="underline" href="mailto:help@eclinicpro.com">help@eclinicpro.com</a> and we'll reset it.</p>
</div>
<p class="mt-4 text-center text-sm text-slate-600">New seller? <a href="/vendor/register" class="font-medium text-[#17774f] hover:underline">Create a seller account</a></p>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
