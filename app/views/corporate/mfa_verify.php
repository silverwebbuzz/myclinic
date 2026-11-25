<?php
/** /company/mfa/verify — second step of sign-in. */
$pageTitle = 'Two-step sign-in';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm" x-data="{ backup: false }">
    <h1 class="text-xl font-semibold">Enter your sign-in code</h1>
    <p class="mt-1 text-sm text-tx2" x-show="!backup">Open your authenticator app and enter the 6-digit code for <strong><?= $e($email) ?></strong>.</p>
    <p class="mt-1 text-sm text-tx2" x-show="backup" x-cloak>Enter one of the backup codes you saved when you set up two-step sign-in. Each code works once.</p>
    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-erb px-3 py-2 text-sm text-er"><?= $e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/company/mfa/verify" class="mt-5 space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="next" value="<?= $e($next ?? '') ?>">
        <input name="code" required autofocus autocomplete="one-time-code" maxlength="12"
               :inputmode="backup ? 'text' : 'numeric'" :placeholder="backup ? 'ABCD-EFGH' : '123 456'"
               class="w-full rounded-[7px] border border-ln bg-sf px-3 py-2.5 text-center font-mono text-lg tracking-[.25em] uppercase focus:border-ac focus:outline-none focus:ring-2 focus:ring-ac/15">
        <button class="w-full rounded-[7px] bg-ac py-2.5 text-sm font-medium text-white hover:opacity-90">Verify</button>
    </form>
    <button type="button" class="mt-4 text-sm font-medium text-act hover:underline" @click="backup = !backup"
            x-text="backup ? 'Use my authenticator app instead' : 'Lost your phone? Use a backup code'">Lost your phone? Use a backup code</button>
</div>
<form method="post" action="/company/logout" class="mt-4 text-center text-sm text-tx2">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    Wrong account? <button class="font-medium text-act hover:underline">Sign out</button>
</form>
<p class="mt-2 text-center text-xs text-tx3">No phone and no backup codes? Ask your company admin or email
    <a href="mailto:help@eclinicpro.com" class="underline">help@eclinicpro.com</a> to reset two-step sign-in.</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
