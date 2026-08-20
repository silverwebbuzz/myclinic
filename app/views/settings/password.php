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
            <label class="block text-xs font-medium text-slate-600">New password</label>
            <input name="password" type="password" required minlength="8" autocomplete="new-password" class="ui-input">
            <p class="mt-1 text-xs text-slate-400">8+ chars, 1 uppercase, 1 number</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600">Confirm new password</label>
            <input name="password_confirm" type="password" required autocomplete="new-password" class="ui-input">
        </div>
        <button type="submit" class="ui-btn ui-btn-primary">Save password</button>
    </form>
    <p class="mt-4 text-sm"><a href="/settings" class="text-emerald-600 hover:underline">← Back to settings</a></p>
</div>
