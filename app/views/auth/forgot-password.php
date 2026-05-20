<?php
$title = 'Forgot password — ManageClinic';
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Reset your password</h1>
    <p class="mt-1 text-sm text-slate-500">We will email you a reset link if an account exists.</p>

    <?php if (!empty($sent)): ?>
        <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            If an account exists for that email, you will receive a reset link shortly.
        </div>
    <?php endif; ?>

    <form method="post" action="/forgot-password" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div>
            <label class="block text-xs font-medium text-slate-600">Email</label>
            <input name="email" type="email" required autocomplete="email"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Send reset link
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        <a href="/login" class="text-emerald-600 hover:underline">Back to login</a>
    </p>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
