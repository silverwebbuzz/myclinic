<div class="mx-auto max-w-md ui-card p-8 text-center">
    <p class="flex justify-center text-slate-300"><?= ui_icon('settings', 40) ?></p>
    <h2 class="mt-3 ui-section-title"><?= htmlspecialchars($label ?? 'Module') ?> not available</h2>
    <p class="mt-2 text-sm text-slate-600">Upgrade your plan or enable the <code><?= htmlspecialchars($module ?? '') ?></code> module to use this feature.</p>
    <a href="/settings?tab=subscription" class="ui-btn ui-btn-primary mt-6">View subscription</a>
</div>
