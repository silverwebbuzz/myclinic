<div class="space-y-6 ui-card ui-card-pad">
    <h2 class="ui-section-title">White-label branding</h2>
    <p class="text-sm text-slate-600">Enterprise: custom domain, logo, and brand color. “Powered by ManageClinic” is hidden on your plan.</p>

    <form method="post" action="/settings/branding" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label class="block text-sm">
            <span class="text-slate-600">Brand color</span>
            <input name="brand_color" type="color" value="<?= htmlspecialchars($clinic['brand_color'] ?? '#0F9B6E') ?>" class="mt-1 h-10 w-full rounded border">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Logo</span>
            <input name="logo" type="file" accept="image/png,image/jpeg" class="mt-1 w-full text-sm">
        </label>
        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm text-white">Save branding</button>
    </form>

    <hr class="my-6">

    <h3 class="font-medium">Custom domain</h3>
    <form method="post" action="/settings/branding/domain" class="mt-3 flex flex-wrap gap-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input name="custom_domain" placeholder="app.yourclinic.com" value="<?= htmlspecialchars($clinic['custom_domain'] ?? '') ?>"
               class="min-w-[200px] flex-1 ui-input">
        <button type="submit" class="ui-btn ui-btn-secondary hover:bg-slate-50">Start DNS verify</button>
    </form>

    <?php if (!empty($domainVerify)): ?>
    <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm">
        <p>Add TXT record:</p>
        <p class="mt-1 font-mono text-xs">Host: <strong><?= htmlspecialchars($domainVerify['host']) ?></strong></p>
        <p class="font-mono text-xs">Value: <strong><?= htmlspecialchars($domainVerify['token']) ?></strong></p>
        <p class="mt-2">
            Status:
            <?php if ($domainVerify['verified']): ?>
            <span class="text-emerald-700">Verified</span>
            <?php else: ?>
            <span class="text-amber-700">Pending</span>
            <?php endif; ?>
        </p>
        <?php if (!$domainVerify['verified']): ?>
        <form method="post" action="/settings/branding/domain/check" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm text-white">Check DNS</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="mt-4 text-xs text-slate-500">
        After DNS verification, terminate TLS on your reverse proxy with Let's Encrypt (certbot or Caddy).
        Point <code><?= htmlspecialchars($clinic['custom_domain'] ?? 'app.yourclinic.com') ?></code> to this app; TenantMiddleware resolves the clinic by <code>custom_domain</code>.
    </p>
</div>
