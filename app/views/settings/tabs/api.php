<div class="space-y-6 ui-card ui-card-pad">
    <h2 class="ui-section-title">REST API keys</h2>
    <p class="text-sm text-slate-600">Use Bearer tokens for <code class="rounded bg-slate-100 px-1">/api/v1/rest/*</code>. Docs: <a href="/docs" class="text-emerald-600 hover:underline" target="_blank">/docs</a></p>

    <?php if (!empty($newApiKey)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
        <p class="font-medium text-amber-900">Copy your new key now — it will not be shown again:</p>
        <code class="mt-2 block break-all rounded bg-white px-2 py-1"><?= htmlspecialchars($newApiKey) ?></code>
    </div>
    <?php endif; ?>

    <form method="post" action="/settings/api/keys" class="rounded-lg border p-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <h3 class="font-medium">Create key</h3>
        <label class="mt-3 block text-sm">
            <span class="text-slate-600">Name</span>
            <input name="name" required class="ui-input" placeholder="Production integration">
        </label>
        <fieldset class="mt-3">
            <legend class="text-xs font-medium text-slate-600">Scopes</legend>
            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                <?php foreach ($apiScopes as $scope): ?>
                <label class="flex items-center gap-1">
                    <input type="checkbox" name="scopes[]" value="<?= htmlspecialchars($scope) ?>">
                    <?= htmlspecialchars($scope) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <button type="submit" class="mt-4 rounded-lg bg-brand px-4 py-2 text-sm text-white">Generate key</button>
    </form>

    <table class="w-full text-left text-sm">
        <thead class="border-b text-xs uppercase text-slate-500">
            <tr><th class="py-2">Name</th><th>Prefix</th><th>Last used</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($apiKeys as $k): ?>
            <tr class="border-b">
                <td class="py-2"><?= htmlspecialchars($k['name'] ?? '') ?></td>
                <td class="font-mono text-xs"><?= htmlspecialchars($k['key_prefix'] ?? 'mc_live_…') ?>…</td>
                <td class="text-slate-500"><?= htmlspecialchars($k['last_used'] ?? '—') ?></td>
                <td>
                    <?php if ((int) ($k['is_active'] ?? 0)): ?>
                    <form method="post" action="/settings/api/keys/<?= (int) $k['id'] ?>/revoke">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="text-red-600 hover:underline">Revoke</button>
                    </form>
                    <?php else: ?>
                    <span class="text-slate-400">Revoked</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
