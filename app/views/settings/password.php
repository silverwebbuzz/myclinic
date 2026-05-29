<div class="mx-auto max-w-lg ui-card ui-card-pad">
    <h1 class="ui-section-title">Change password</h1>
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
            <input name="current_password" type="password" required class="ui-input">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600">New password</label>
            <input name="password" type="password" required minlength="8" class="ui-input">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600">Confirm new password</label>
            <input name="password_confirm" type="password" required class="ui-input">
        </div>
        <button type="submit" class="ui-btn ui-btn-primary">Save password</button>
    </form>
    <p class="mt-4 text-sm"><a href="/settings" class="text-emerald-600 hover:underline">← Back to settings</a></p>
</div>
