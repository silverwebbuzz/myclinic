<?php
$title = 'Partner Login — eClinicPro';
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Partner login</h1>
    <p class="mt-1 text-sm text-slate-500">Access your referrals, earnings &amp; payouts.</p>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/partner/login" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div>
            <label class="block text-xs font-medium text-slate-600">Email</label>
            <input name="email" type="email" required
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600">Password</label>
            <input name="password" type="password" required
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>
        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Log in
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        New partner? <a href="/partner/register" class="text-emerald-600 hover:underline">Apply here</a>
    </p>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
