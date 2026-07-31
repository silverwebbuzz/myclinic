<?php
$title = 'Forgot password — ManageClinic';
$step = $step ?? 'phone';
$pendingPhone = $pendingPhone ?? '';
$devCode = $devCode ?? null;
$error = $error ?? null;
$info = $info ?? null;
$phoneDigits = preg_replace('/\D/', '', $pendingPhone) ?? '';
if (str_starts_with($phoneDigits, '91') && strlen($phoneDigits) === 12) {
    $phoneDigits = substr($phoneDigits, 2);
}
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Reset your password</h1>
    <p class="mt-1 text-sm text-slate-500">Verify your mobile number, then choose a new password.</p>

    <?php if (!empty($info)): ?>
        <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars((string) $info) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'phone'): ?>
        <form method="post" action="/forgot-password/send-otp" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div>
                <label class="block text-xs font-medium text-slate-600">Mobile number</label>
                <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-slate-300 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                    <span class="border-r border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">+91</span>
                    <input name="phone" type="tel" inputmode="numeric" maxlength="10" required autofocus
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
                Send reset code
            </button>
        </form>
    <?php elseif ($step === 'reset'): ?>
        <?php if (!empty($devCode)): ?>
            <div class="mt-4 flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                <span class="rounded bg-amber-300 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide">DEV</span>
                Your code: <strong class="ml-auto font-mono text-base tracking-widest"><?= htmlspecialchars((string) $devCode) ?></strong>
            </div>
        <?php endif; ?>
        <form method="post" action="/forgot-password/verify-otp" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars($pendingPhone) ?>">
            <div class="rounded-lg bg-slate-50 px-3 py-2 text-center text-sm text-slate-700">
                Code sent to <strong>+91 <?= htmlspecialchars($phoneDigits) ?></strong>
                · <a href="/forgot-password" class="text-emerald-600 hover:underline">Change</a>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">6-digit code</label>
                <input name="code" type="text" inputmode="numeric" maxlength="6" required autofocus
                       placeholder="••••••"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-[0.4em] focus:border-emerald-500 focus:outline-none">
            </div>
            <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
                <div class="overflow-hidden rounded-lg border border-slate-200 p-2">
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars((string) $captchaSiteKey) ?>"></div>
                </div>
            <?php endif; ?>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                Verify code
            </button>
        </form>
    <?php else: ?>
        <form method="post" action="/forgot-password/reset" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div>
                <label class="block text-xs font-medium text-slate-600">New password</label>
                <input name="password" type="password" required minlength="8" autofocus
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                <p class="mt-1 text-xs text-slate-400">8+ chars, 1 uppercase, 1 number</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Confirm password</label>
                <input name="password_confirm" type="password" required
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            </div>
            <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
                <div class="overflow-hidden rounded-lg border border-slate-200 p-2">
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars((string) $captchaSiteKey) ?>"></div>
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                Update password
            </button>
        </form>
    <?php endif; ?>

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
