<?php
/** /company/login */
$pageTitle = 'Sign in';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <h1 class="text-xl font-semibold">Sign in to your company account</h1>
    <p class="mt-1 text-sm text-tx3">For company admins, HR and HSE teams. Employees sign in at
        <a href="https://eclinicpro.com/patient" class="text-act hover:underline">eclinicpro.com/patient</a>.</p>
    <?php if (!empty($notice)): ?>
        <div class="mt-4 rounded-lg bg-okb px-3 py-2 text-sm text-ok"><?= $e($notice) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-erb px-3 py-2 text-sm text-er"><?= $e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/company/login" class="mt-5 space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="next" value="<?= $e($next ?? '') ?>">
        <label class="block text-sm">
            <span class="text-tx2">Work email</span>
            <input name="email" type="email" required autocomplete="username" value="<?= $e($email ?? '') ?>" class="<?= $input ?>">
        </label>
        <label class="block text-sm">
            <span class="flex items-baseline justify-between"><span class="text-tx2">Password</span>
                <a href="/company/forgot-password" class="text-xs font-medium text-act hover:underline">Forgot password?</a></span>
            <input name="password" type="password" required autocomplete="current-password" class="<?= $input ?>">
        </label>
        <?php if (!empty($captchaEnabled)): ?>
            <div class="g-recaptcha" data-sitekey="<?= $e($captchaSiteKey) ?>"></div>
        <?php endif; ?>
        <button class="w-full rounded-[7px] bg-ac py-2.5 text-sm font-medium text-white hover:opacity-90">Sign in</button>
    </form>
</div>
<p class="mt-4 text-center text-sm text-tx2">New to Workforce Health? <a href="/company/register" class="font-medium text-act hover:underline">Register your company</a></p>
<?php if (!empty($captchaEnabled)): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
