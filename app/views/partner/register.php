<?php
$title = 'Become a Partner — eClinicPro';
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Join the Partner Program</h1>
    <p class="mt-1 text-sm text-slate-500">Refer clinics, earn recurring commission on every subscription &amp; renewal.</p>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/partner/register" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div>
            <label class="block text-xs font-medium text-slate-600">Full name</label>
            <input name="name" type="text" required value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600">Email</label>
            <input name="email" type="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-600">Phone</label>
                <input name="phone" type="text" value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Country</label>
                <input name="country_code" type="text" maxlength="2" value="<?= htmlspecialchars($old['country'] ?? 'IN') ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase focus:border-emerald-500 focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-600">City</label>
                <input name="city" type="text" value="<?= htmlspecialchars($old['city'] ?? '') ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">State</label>
                <input name="state" type="text" value="<?= htmlspecialchars($old['state'] ?? '') ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600">Password</label>
            <input name="password" type="password" required
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            <p class="mt-1 text-xs text-slate-400">8+ characters</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600">Confirm password</label>
            <input name="password_confirm" type="password" required
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Create partner account
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        Already a partner? <a href="/partner/login" class="text-emerald-600 hover:underline">Log in</a>
    </p>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
