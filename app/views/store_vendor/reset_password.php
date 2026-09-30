<?php
/** /vendor/reset-password?token=… — choose a new password from the emailed link. */
$pageTitle = 'Choose a new password';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <?php if (!empty($done)): ?>
        <h1 class="text-xl font-semibold">Password updated</h1>
        <p class="mt-3 text-sm text-tx2">Your new password is set. You can log in with it now.</p>
        <a href="/vendor/login" class="mt-5 block w-full rounded-[7px] bg-ac py-2.5 text-center text-sm font-medium text-white hover:opacity-90">Log in</a>
    <?php elseif (empty($valid)): ?>
        <h1 class="text-xl font-semibold">This link has expired</h1>
        <p class="mt-3 text-sm text-tx2">Reset links work once and expire after 60 minutes. Please ask for a new one.</p>
        <a href="/vendor/forgot-password" class="mt-5 block w-full rounded-[7px] bg-ac py-2.5 text-center text-sm font-medium text-white hover:opacity-90">Send a new link</a>
    <?php else: ?>
        <h1 class="text-xl font-semibold">Choose a new password</h1>
        <?php if (!empty($error)): ?>
            <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= $e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/vendor/reset-password" class="mt-5 space-y-4">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <label class="block text-sm">
                <span class="text-tx2">New password <span class="text-tx3">(at least 8 characters)</span></span>
                <input name="password" type="password" required minlength="8" autocomplete="new-password" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-tx2">Confirm new password</span>
                <input name="password_confirm" type="password" required minlength="8" data-match="password" autocomplete="new-password" class="<?= $input ?>">
            </label>
            <button class="w-full rounded-[7px] bg-ac py-2.5 text-sm font-medium text-white hover:opacity-90">Save new password</button>
        </form>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
