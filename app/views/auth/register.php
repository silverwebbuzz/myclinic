<?php
$title = 'Register — ManageClinic';
$phoneStep = $phoneStep ?? 'phone';
$verifiedPhone = $verifiedPhone ?? null;
$pendingPhone = $pendingPhone ?? '';
$devCode = $devCode ?? null;
$info = $info ?? null;
$error = $error ?? null;
$old = $old ?? [];
$defaultUsername = $defaultUsername ?? '';
$oldUsername = $old['username'] ?? $defaultUsername;
$prefRef = $_GET['ref'] ?? ($_COOKIE['mc_ref'] ?? '');
$prefRef = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $prefRef));
ob_start();
?>
<div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Start your clinic</h1>
    <p class="mt-1 text-sm text-slate-500">30-day free trial · No credit card required</p>

    <?php if (!empty($info)): ?>
        <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars((string) $info) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <?php
    $phoneDigits = preg_replace('/\D/', '', (string) ($verifiedPhone ?: $pendingPhone)) ?? '';
    if (str_starts_with($phoneDigits, '91') && strlen($phoneDigits) === 12) {
        $phoneDigits = substr($phoneDigits, 2);
    }
    ?>

    <div class="mt-5 flex items-center gap-1 text-[11px] text-slate-500">
        <div class="flex items-center gap-1 <?= $phoneStep !== 'phone' ? 'text-emerald-700' : 'text-emerald-700 font-medium' ?>">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white"><?= $phoneStep === 'phone' ? '1' : '✓' ?></span>
            Verify phone
        </div>
        <span class="h-px flex-1 bg-slate-200"></span>
        <div class="flex items-center gap-1 <?= $phoneStep === 'code' ? 'text-emerald-700 font-medium' : ($phoneStep === 'details' ? 'text-emerald-700' : '') ?>">
            <span class="flex h-5 w-5 items-center justify-center rounded-full <?= in_array($phoneStep, ['code', 'details'], true) ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500' ?> text-[10px] font-bold"><?= $phoneStep === 'details' ? '✓' : '2' ?></span>
            Enter code
        </div>
        <span class="h-px flex-1 bg-slate-200"></span>
        <div class="flex items-center gap-1 <?= $phoneStep === 'details' ? 'text-emerald-700 font-medium' : '' ?>">
            <span class="flex h-5 w-5 items-center justify-center rounded-full <?= $phoneStep === 'details' ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500' ?> text-[10px] font-bold">3</span>
            Your details
        </div>
    </div>

    <?php if ($phoneStep === 'phone'): ?>
        <form method="post" action="/register/send-otp" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600">Your WhatsApp mobile number</label>
                <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-slate-300 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                    <span class="border-r border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">+91</span>
                    <input name="phone" type="tel" inputmode="numeric" maxlength="10" required autofocus
                           value="<?= htmlspecialchars($phoneDigits) ?>"
                           placeholder="98XXXXXXXX"
                           class="w-full px-3 py-2 text-sm focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-400">We'll send a one-time code on WhatsApp to this number for verification.</p>
            </div>
            <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
                <div class="overflow-hidden rounded-lg border border-slate-200 p-2">
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars((string) $captchaSiteKey) ?>"></div>
                </div>
            <?php endif; ?>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                Send code
            </button>
        </form>
    <?php elseif ($phoneStep === 'code'): ?>
        <?php if (!empty($devCode)): ?>
            <div class="mt-4 flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                <span class="rounded bg-amber-300 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide">DEV</span>
                Your code: <strong class="ml-auto font-mono text-base tracking-widest"><?= htmlspecialchars((string) $devCode) ?></strong>
            </div>
        <?php endif; ?>
        <form method="post" action="/register/verify-otp" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars((string) $pendingPhone) ?>">
            <div class="rounded-lg bg-slate-50 px-3 py-2 text-center text-sm text-slate-700">
                Code sent to <strong>+91 <?= htmlspecialchars($phoneDigits) ?></strong>
                · <a href="/register" class="text-emerald-600 hover:underline">Change</a>
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
                Verify
            </button>
        </form>
        <form method="post" action="/register/send-otp" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars((string) $pendingPhone) ?>">
            <button type="submit" class="w-full rounded-lg border border-slate-200 py-2 text-sm text-slate-600 hover:bg-slate-50">
                Resend code
            </button>
        </form>
    <?php else: ?>
        <form method="post" action="/register" class="mt-6 space-y-4" x-data="registerForm()" x-init="init()">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                Verified phone <strong>+91 <?= htmlspecialchars($phoneDigits) ?></strong>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Username</label>
                <input name="username" type="text" autocomplete="username"
                       value="<?= htmlspecialchars($oldUsername) ?>"
                       placeholder="<?= htmlspecialchars($defaultUsername) ?>"
                       @input.debounce.400ms="checkUsername($event.target.value)"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                <p class="mt-1 text-xs" :class="usernameStatusClass" x-text="usernameStatusText"></p>
                <p class="mt-0.5 text-xs text-slate-400">Defaults to your mobile number. You can change it, or leave as-is.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Your name</label>
                <input name="owner_name" type="text" required value="<?= htmlspecialchars($old['ownerName'] ?? '') ?>"
                       placeholder="e.g. Dr. Mitesh Prajapati"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Clinic name</label>
                <input name="clinic_name" type="text" required value="<?= htmlspecialchars($old['clinicName'] ?? '') ?>"
                       @input="suggestSlug($event.target.value)"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>
            <input name="slug" type="hidden" x-model="slug">

            <div>
                <label class="block text-xs font-medium text-slate-600">Password</label>
                <input name="password" type="password" required minlength="8"
                       @input="checkStrength($event.target.value)"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                <div class="mt-1 h-1 rounded bg-slate-200"><div class="h-1 rounded bg-emerald-500 transition-all" :style="'width:' + strength + '%'"></div></div>
                <p class="mt-1 text-xs text-slate-400">8+ chars, 1 uppercase, 1 number</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Confirm password</label>
                <input name="password_confirm" type="password" required
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Email</label>
                <input name="email" type="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                       placeholder="For receipts &amp; updates"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Referral code <span class="text-slate-400">(optional)</span></label>
                <input name="referral_code" type="text" value="<?= htmlspecialchars($prefRef) ?>"
                       placeholder="Have a partner code? Enter it here"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase focus:border-emerald-500 focus:outline-none">
            </div>
            <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
                <div class="overflow-hidden rounded-lg border border-slate-200 p-2">
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars((string) $captchaSiteKey) ?>"></div>
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                Create clinic account
            </button>
        </form>
    <?php endif; ?>

    <p class="mt-4 text-center text-sm text-slate-500">
        Already have an account? <a href="/login" class="text-emerald-600 hover:underline">Login</a>
    </p>
    <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</div>
<script>
function registerForm() {
    return {
        slug: <?= json_encode($old['slug'] ?? '') ?>,
        strength: 0,
        usernameStatusText: '',
        usernameStatusClass: 'text-slate-400',
        defaultUsername: <?= json_encode($defaultUsername) ?>,
        init() {
            const initial = <?= json_encode($oldUsername) ?>;
            if (initial) {
                this.checkUsername(initial);
            }
        },
        async checkUsername(value) {
            const raw = (value || '').trim();
            if (raw === '') {
                this.usernameStatusText = 'Will use your mobile number if left blank.';
                this.usernameStatusClass = 'text-slate-400';
                return;
            }
            try {
                const r = await fetch('/api/check-username?username=' + encodeURIComponent(raw));
                const data = await r.json();
                if (data.reason === 'invalid') {
                    this.usernameStatusText = 'Use 3–30 chars: letters, numbers, underscore (or 10-digit mobile).';
                    this.usernameStatusClass = 'text-red-600';
                } else if (data.available) {
                    this.usernameStatusText = 'Username is available.';
                    this.usernameStatusClass = 'text-emerald-600';
                } else {
                    this.usernameStatusText = 'Username is already taken.';
                    this.usernameStatusClass = 'text-red-600';
                }
            } catch (e) {
                this.usernameStatusText = '';
            }
        },
        suggestSlug(name) {
            this.slug = name.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '')
                .slice(0, 60);
        },
        checkStrength(pw) {
            let s = 0;
            if (pw.length >= 8) s += 33;
            if (/[A-Z]/.test(pw)) s += 33;
            if (/[0-9]/.test(pw)) s += 34;
            this.strength = s;
        }
    };
}
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
