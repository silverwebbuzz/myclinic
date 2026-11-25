<?php
/** /company/verify-email?token=… result. */
$pageTitle = 'Confirm email';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <?php if (!empty($ok)): ?>
        <h1 class="text-xl font-semibold">Email confirmed</h1>
        <p class="mt-3 text-sm text-tx2">Thanks, your email address is confirmed.</p>
    <?php else: ?>
        <h1 class="text-xl font-semibold">We couldn't confirm your email</h1>
        <p class="mt-3 text-sm text-tx2"><?= $e($error ?? 'This link is not valid.') ?></p>
    <?php endif; ?>
    <a href="<?= !empty($signedIn) ? '/company/dashboard' : '/company/login' ?>" class="mt-5 block w-full rounded-[7px] bg-ac py-2.5 text-center text-sm font-medium text-white hover:opacity-90">
        <?= !empty($signedIn) ? 'Go to dashboard' : 'Sign in' ?>
    </a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
