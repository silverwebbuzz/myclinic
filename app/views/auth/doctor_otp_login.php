<?php
$title = 'Doctor sign in — eClinicPro';
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">
        <?= $step === 'code' ? 'Enter the code' : 'Doctor sign in' ?>
    </h1>
    <p class="mt-1 text-sm text-slate-500">
        <?= $step === 'code'
            ? 'We just texted a 6-digit code to your phone.'
            : 'Enter your registered mobile number. We\'ll text you a one-time code.' ?>
    </p>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
            <?= htmlspecialchars((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 'phone'): ?>
        <form method="post" action="/doctor/login/send" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrf) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600">Mobile number</label>
                <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-slate-300 focus-within:border-emerald-500">
                    <span class="border-r border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">+91</span>
                    <input name="phone" type="tel" inputmode="numeric" maxlength="14" required autofocus
                           value="<?= htmlspecialchars((string) $phone) ?>"
                           placeholder="98XXXXXXXX"
                           class="w-full px-3 py-2 text-sm focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Only approved doctor accounts can sign in here.
                </p>
            </div>

            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                Send code
            </button>
        </form>
    <?php else: ?>
        <?php if (!empty($devCode)): ?>
            <div class="mt-4 flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                <span class="rounded bg-amber-300 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900">DEV</span>
                Your code: <strong class="ml-auto font-mono text-base tracking-widest"><?= htmlspecialchars((string) $devCode) ?></strong>
            </div>
        <?php endif; ?>

        <form method="post" action="/doctor/login/verify" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf"  value="<?= htmlspecialchars((string) $csrf) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars((string) $phone) ?>">
            <div class="rounded-lg bg-slate-50 px-3 py-2 text-center text-sm text-slate-700">
                Code sent to <strong><?= htmlspecialchars((string) $phone) ?></strong>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">6-digit code</label>
                <input name="code" type="text" inputmode="numeric" maxlength="6" required autofocus
                       placeholder="••••••"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-lg tracking-[0.4em] text-center focus:border-emerald-500 focus:outline-none">
            </div>

            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                Verify &amp; sign in
            </button>
        </form>

        <form method="post" action="/doctor/login/send" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrf) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars((string) $phone) ?>">
            <button type="submit" class="w-full rounded-lg border border-slate-200 py-2 text-sm text-slate-600 hover:bg-slate-50">
                Resend code
            </button>
        </form>
    <?php endif; ?>

    <div class="mt-6 border-t pt-4 text-center text-sm text-slate-500">
        Staff with an email + password? <a href="/login" class="text-emerald-600 hover:underline">Sign in here</a>
    </div>
    <p class="mt-2 text-center text-sm text-slate-500">
        Not listed yet?
        <a href="https://eclinicpro.com/find-a-doctor" class="text-emerald-600 hover:underline">Claim your clinic</a>
    </p>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
