<?php
$title = 'Set new password — ManageClinic';
$required = !empty($required);
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">
        <?= $required ? 'Set your password' : 'Change password' ?>
    </h1>
    <?php if ($required): ?>
    <p class="mt-1 text-sm text-slate-500">Your clinic admin gave you a temporary password. Choose a new one to continue.</p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <form method="post" action="/change-password" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div>
            <label class="block text-xs font-medium text-slate-600">New password</label>
            <input name="password" type="password" required minlength="8" autocomplete="new-password"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            <p class="mt-1 text-xs text-slate-400">8+ chars, 1 uppercase, 1 number</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600">Confirm new password</label>
            <input name="password_confirm" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            <?= $required ? 'Continue to dashboard' : 'Update password' ?>
        </button>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
