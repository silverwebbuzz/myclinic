<div class="mx-auto max-w-lg rounded-xl border bg-white p-6">
    <h1 class="text-lg font-semibold">Change password</h1>
    <?php if (!empty($success)): ?>
        <p class="mt-3 text-sm text-emerald-600">Password updated successfully.</p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="mt-3 text-sm text-red-600"><?= htmlspecialchars((string) $error) ?></p>
    <?php endif; ?>
    <form method="post" action="/settings/password" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div>
            <label class="block text-xs font-medium text-slate-600">Current password</label>
            <input name="current_password" type="password" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600">New password</label>
            <input name="password" type="password" required minlength="8" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600">Confirm new password</label>
            <input name="password_confirm" type="password" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Save password</button>
    </form>
    <p class="mt-4 text-sm"><a href="/settings" class="text-emerald-600 hover:underline">← Back to settings</a></p>
</div>
