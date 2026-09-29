<?php
/** /vendor/forgot-password — ask for a reset link. Same message whether or not the email exists. */
$pageTitle = 'Forgot password';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <?php if (!empty($sent)): ?>
        <h1 class="text-xl font-semibold">Check your email</h1>
        <p class="mt-3 text-sm leading-relaxed text-tx2">
            If <strong><?= $e($email) ?></strong> belongs to a seller account, we've sent a link to reset the password.
            It works once and expires in 60 minutes.
        </p>
        <p class="mt-3 text-sm leading-relaxed text-tx3">Didn't get it? Check your spam folder, or wait a minute and try again.
            Still stuck? Email <a class="underline" href="mailto:help@eclinicpro.com">help@eclinicpro.com</a>.</p>
        <a href="/vendor/login" class="mt-5 block w-full rounded-[7px] bg-ac py-2.5 text-center text-sm font-medium text-white hover:opacity-90">Back to log in</a>
    <?php else: ?>
        <h1 class="text-xl font-semibold">Forgot your password?</h1>
        <p class="mt-2 text-sm text-tx2">Enter the email you use to log in. We'll send you a link to choose a new password.</p>
        <form method="post" action="/vendor/forgot-password" class="mt-5 space-y-4">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <label class="block text-sm">
                <span class="text-tx2">Email</span>
                <input name="email" type="email" required autocomplete="email" value="<?= $e($email ?? '') ?>"
                       class="mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none">
            </label>
            <button class="w-full rounded-[7px] bg-ac py-2.5 text-sm font-medium text-white hover:opacity-90">Send reset link</button>
        </form>
    <?php endif; ?>
</div>
<p class="mt-4 text-center text-sm text-tx2">Remembered it? <a href="/vendor/login" class="font-medium text-act hover:underline">Log in</a></p>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
