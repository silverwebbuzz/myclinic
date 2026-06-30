<div class="mx-auto max-w-lg ui-card ui-card-pad">
    <h1 class="ui-section-title">My profile</h1>
    <?php if (!empty($success)): ?>
        <p class="mt-3 text-sm text-emerald-600">Profile updated successfully.</p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="mt-3 text-sm text-red-600"><?= htmlspecialchars((string) $error) ?></p>
    <?php endif; ?>
    <form method="post" action="/settings/profile" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div>
            <label class="block text-xs font-medium text-slate-600">Your name</label>
            <input name="name" type="text" required maxlength="120"
                   value="<?= htmlspecialchars((string) ($userName ?? '')) ?>"
                   placeholder="e.g. Dr. Riya Mehta"
                   class="ui-input">
        </div>
        <button type="submit" class="ui-btn ui-btn-primary">Save profile</button>
    </form>
    <p class="mt-4 text-sm"><a href="/dashboard" class="text-emerald-600 hover:underline">← Back to dashboard</a></p>
</div>
