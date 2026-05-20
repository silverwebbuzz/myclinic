<?php
$title = 'Reset password — ManageClinic';
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Choose a new password</h1>

    <?php if (empty($valid)): ?>
        <p class="mt-4 text-sm text-red-600">This reset link is invalid or has expired.</p>
        <p class="mt-4 text-center text-sm"><a href="/forgot-password" class="text-emerald-600 hover:underline">Request a new link</a></p>
    <?php else: ?>
        <?php if (!empty($error)): ?>
            <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars((string) $error) ?></div>
        <?php endif; ?>

        <form method="post" action="/reset-password/<?= htmlspecialchars($token) ?>" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div>
                <label class="block text-xs font-medium text-slate-600">New password</label>
                <input name="password" type="password" required minlength="8"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                <p class="mt-1 text-xs text-slate-400">8+ chars, 1 uppercase, 1 number</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Confirm password</label>
                <input name="password_confirm" type="password" required
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            </div>

            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                Update password
            </button>
        </form>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
