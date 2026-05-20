<div class="mx-auto max-w-md rounded-xl border bg-white p-8 text-center">
    <p class="text-4xl">🔒</p>
    <h2 class="mt-2 text-lg font-semibold"><?= htmlspecialchars($label ?? 'Module') ?> not available</h2>
    <p class="mt-2 text-sm text-slate-600">Upgrade your plan or enable the <code><?= htmlspecialchars($module ?? '') ?></code> module to use this feature.</p>
    <a href="/settings?tab=subscription" class="mt-6 inline-block rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">View subscription</a>
</div>
