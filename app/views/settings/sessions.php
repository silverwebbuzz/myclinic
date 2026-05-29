<div class="mx-auto max-w-lg ui-card ui-card-pad">
    <div class="flex items-center justify-between">
        <h1 class="ui-section-title">Active sessions</h1>
        <?php if (count($sessions) > 1): ?>
        <form method="post" action="/settings/sessions/revoke-all">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="text-xs text-red-600 hover:underline">Sign out other devices</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if (!empty($message)): ?>
        <p class="mt-3 text-sm text-emerald-600">
            <?= $message === 'others_revoked' ? 'Other devices signed out.' : 'Session revoked.' ?>
        </p>
    <?php endif; ?>
    <ul class="mt-6 divide-y">
        <?php foreach ($sessions as $session): ?>
        <li class="flex items-center justify-between py-3 text-sm">
            <div>
                <p class="font-medium"><?= htmlspecialchars($session['device_label'] ?? 'Device') ?></p>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($session['ip_address'] ?? '') ?></p>
            </div>
            <?php if (!empty($session['is_current'])): ?>
                <span class="text-xs text-emerald-600">This device</span>
            <?php else: ?>
                <form method="post" action="/settings/sessions/revoke/<?= (int) $session['id'] ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-xs text-slate-500 hover:text-red-600">Revoke</button>
                </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
        <?php if ($sessions === []): ?>
        <li class="py-4 text-sm text-slate-500">No tracked sessions. Use “Remember me” when signing in.</li>
        <?php endif; ?>
    </ul>
    <p class="mt-4 text-sm"><a href="/settings" class="text-emerald-600 hover:underline">← Back to settings</a></p>
</div>
