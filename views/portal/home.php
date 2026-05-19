<div class="rounded-xl border border-slate-200 bg-white p-6 text-center">
    <h1 class="text-lg font-semibold text-slate-900">Welcome</h1>
    <p class="mt-2 text-sm text-slate-500">
        Patient portal for <?= htmlspecialchars($clinic['name'] ?? 'your clinic') ?>.
        <a href="/portal/login" class="mt-2 inline-block text-emerald-600 hover:underline">Sign in with your phone →</a>
    </p>
</div>
