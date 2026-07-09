<?php
$title = 'Forgot username — ManageClinic';
$step = $step ?? 'phone';
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Forgot your username?</h1>
    <p class="mt-1 text-sm text-slate-500">Enter the mobile number on your account. We'll text you your username.</p>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <?php if (!empty($sent) && empty($devUsername)): ?>
        <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            If an account exists for that number, your username has been sent by SMS.
        </div>
    <?php endif; ?>

    <?php if (!empty($devUsername)): ?>
        <div class="mt-4 flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            <span class="rounded bg-amber-300 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide">DEV</span>
            Username: <strong class="ml-auto font-mono"><?= htmlspecialchars((string) $devUsername) ?></strong>
        </div>
    <?php endif; ?>

    <form method="post" action="/forgot-username" class="mt-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div>
            <label class="block text-xs font-medium text-slate-600">Mobile number</label>
            <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-slate-300 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                <span class="border-r border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">+91</span>
                <input name="phone" type="tel" inputmode="numeric" maxlength="10" required
                       placeholder="98XXXXXXXX"
                       class="w-full px-3 py-2 text-sm focus:outline-none">
            </div>
        </div>
        <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
            <div class="overflow-hidden rounded-lg border border-slate-200 p-2">
                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars((string) $captchaSiteKey) ?>"></div>
            </div>
        <?php endif; ?>

        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Send my username
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        <a href="/login" class="text-emerald-600 hover:underline">Back to login</a>
    </p>
    <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
