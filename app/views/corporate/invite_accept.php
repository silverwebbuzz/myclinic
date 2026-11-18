<?php
/**
 * /company/invite?token=… — accept an invitation.
 *
 * @var array<string,mixed>|null $invite  + company
 * @var string $roleName
 */
$pageTitle = 'Accept invitation';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$companyName = $invite ? (string) (($invite['company']['trade_name'] ?? '') ?: ($invite['company']['legal_name'] ?? '')) : '';
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <?php if ($invite === null): ?>
        <h1 class="text-xl font-semibold">This invitation can't be used</h1>
        <p class="mt-3 text-sm text-tx2"><?= $e($error ?? 'It has expired, was cancelled, or was already accepted. Ask your company admin to send a new one.') ?></p>
        <a href="/company/login" class="mt-5 block w-full rounded-[7px] bg-ac py-2.5 text-center text-sm font-medium text-white hover:opacity-90">Go to sign in</a>
    <?php else: ?>
        <h1 class="text-xl font-semibold">Join <?= $e($companyName) ?></h1>
        <p class="mt-1 text-sm text-tx2">You've been invited as <strong><?= $e($roleName) ?></strong>. Choose a password for
            <strong><?= $e($invite['email']) ?></strong>. Next you'll set up two-step sign-in with an app on your phone.</p>
        <?php if (!empty($error)): ?>
            <div class="mt-4 rounded-lg bg-erb px-3 py-2 text-sm text-er"><?= $e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/company/invite" class="mt-5 space-y-4">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <input type="email" name="username" value="<?= $e($invite['email']) ?>" autocomplete="username" class="hidden" tabindex="-1" aria-hidden="true">
            <label class="block text-sm">
                <span class="text-tx2">Your full name</span>
                <input name="name" required minlength="2" maxlength="160" autocomplete="name" value="<?= $e($name) ?>" class="<?= $input ?>">
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-tx2">Password</span>
                    <input name="password" type="password" required minlength="10" maxlength="128" autocomplete="new-password" class="<?= $input ?>">
                    <span class="text-xs text-tx3">10+ characters</span>
                </label>
                <label class="block text-sm">
                    <span class="text-tx2">Confirm password</span>
                    <input name="password_confirm" type="password" required minlength="10" maxlength="128" data-match="password" autocomplete="new-password" class="<?= $input ?>">
                </label>
            </div>
            <button class="w-full rounded-[7px] bg-ac py-2.5 text-sm font-medium text-white hover:opacity-90">Create my login</button>
        </form>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$maxWidth = 'max-w-lg';
require __DIR__ . '/_auth_layout.php';
