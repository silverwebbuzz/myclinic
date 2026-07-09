<?php
$title = 'Login — ManageClinic';
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Welcome back</h1>
    <p class="mt-1 text-sm text-slate-500">Sign in with your username and password</p>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div>
            <label class="block text-xs font-medium text-slate-600">Username</label>
            <input name="username" type="text" required autofocus autocomplete="username"
                   placeholder="Your login username"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600">Password</label>
            <input name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>

        <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
        <div class="overflow-hidden rounded-lg border border-slate-200 p-2">
            <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars((string) $captchaSiteKey) ?>"></div>
        </div>
        <?php endif; ?>

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-slate-600">
                <input type="checkbox" name="remember_me" value="1" class="rounded border-slate-300">
                Remember me
            </label>
            <div class="flex flex-col items-end gap-1 text-xs">
                <a href="/forgot-username" class="text-emerald-600 hover:underline">Forgot username?</a>
                <a href="/forgot-password" class="text-emerald-600 hover:underline">Forgot password?</a>
            </div>
        </div>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Log in
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        New clinic? <a href="/register" class="text-emerald-600 hover:underline">Create account</a>
    </p>
    <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
