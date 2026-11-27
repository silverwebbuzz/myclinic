<?php
/**
 * /creator/login — phone + WhatsApp OTP. Uses the LIVE patient login API on the
 * main site (eclinicpro.com/api/patient_auth.php, CORS-allowed for this origin
 * only), so it's the same account, lockout rules and cookie as /patient.
 *
 * @var string $otpApi
 * @var string $captchaSiteKey
 * @var string $mode signin|signup
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sign in · Creator Studio · eClinicPro Wellness Hub</title>
    <?php require dirname(__DIR__) . '/store_vendor/_pg_head.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <?php if ($captchaSiteKey !== ''): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-bg font-sans text-tx">
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="grid w-full max-w-4xl gap-6 md:grid-cols-[1fr_400px] md:items-center">
        <section class="order-2 md:order-1">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="grid h-10 w-10 place-items-center rounded-[10px] bg-ac text-lg font-bold text-white">e</div>
                <div class="leading-tight">
                    <div class="text-[20px] font-semibold tracking-[-.015em]">Wellness Hub</div>
                    <div class="text-[13px] text-tx3">Creator Studio</div>
                </div>
            </div>
            <h1 class="text-[22px] font-semibold leading-tight tracking-[-.015em] md:text-[26px]">Share healthy recipes, exercises, tips and health camps with people across India.</h1>
            <ul class="mt-4 space-y-2 text-[13.5px] text-tx2">
                <li class="flex gap-2"><span class="text-ac">✓</span> For fitness coaches, dietitians, yoga teachers, doctors, hospitals, NGOs, government bodies and event organisers.</li>
                <li class="flex gap-2"><span class="text-ac">✓</span> Your own public profile with your Instagram, YouTube and website.</li>
                <li class="flex gap-2"><span class="text-ac">✓</span> Listing free health camps is always free.</li>
                <li class="flex gap-2"><span class="text-ac">✓</span> Doctors and organisations can get a Verified badge after we check their registration.</li>
            </ul>
            <p class="mt-4 text-[12.5px] text-tx3">Already have an eClinicPro patient account? Sign in with the same mobile number — it’s one account for both.</p>
        </section>

        <section class="order-1 rounded-[12px] border border-ln bg-sf p-5 shadow-sm md:order-2 md:p-6"
                 x-data="creatorLogin(<?= $e(json_encode(['api' => $otpApi, 'captcha' => $captchaSiteKey !== ''])) ?>)">
            <h2 class="text-[16px] font-semibold">Sign in or join as a creator</h2>
            <p class="mt-1 text-[12.5px] text-tx3">We’ll send a 6-digit code to your WhatsApp.</p>

            <form class="mt-4 space-y-3" @submit.prevent="step === 'phone' ? send() : verify()">
                <label class="block">
                    <span class="text-[12.5px] font-medium text-tx2">Mobile number</span>
                    <div class="mt-1 flex rounded-[8px] border border-ln bg-sf focus-within:border-ac">
                        <span class="grid place-items-center border-r border-ln px-3 text-[13px] text-tx3">+91</span>
                        <input type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10" x-model="phone" :disabled="step === 'code'"
                               class="h-10 w-full rounded-r-[8px] bg-transparent px-3 text-[14px] outline-none disabled:text-tx3" placeholder="98765 43210" required>
                    </div>
                </label>

                <template x-if="step === 'code'">
                    <div class="space-y-3">
                        <label class="block" x-show="needName">
                            <span class="text-[12.5px] font-medium text-tx2">Your name</span>
                            <input type="text" x-model="name" maxlength="120" autocomplete="name"
                                   class="mt-1 h-10 w-full rounded-[8px] border border-ln px-3 text-[14px] outline-none focus:border-ac" placeholder="Full name">
                        </label>
                        <label class="block">
                            <span class="text-[12.5px] font-medium text-tx2">Code from WhatsApp</span>
                            <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" x-model="code" x-ref="code"
                                   class="mt-1 h-10 w-full rounded-[8px] border border-ln px-3 text-center font-mono text-[18px] tracking-[.4em] outline-none focus:border-ac" placeholder="••••••" required>
                        </label>
                        <p x-show="devCode" class="rounded bg-wnb px-3 py-2 text-[12px] text-wn">Test mode code: <strong x-text="devCode"></strong></p>
                    </div>
                </template>

                <?php if ($captchaSiteKey !== ''): ?>
                    <div class="g-recaptcha" data-sitekey="<?= $e($captchaSiteKey) ?>"></div>
                <?php endif; ?>

                <p x-show="error" x-text="error" class="rounded-[8px] bg-erb px-3 py-2 text-[12.5px] text-er"></p>

                <button type="submit" :disabled="busy"
                        class="h-10 w-full rounded-[8px] bg-ac text-[14px] font-semibold text-white hover:bg-act disabled:opacity-60"
                        x-text="busy ? 'Please wait…' : (step === 'phone' ? 'Send code' : 'Verify & continue')"></button>

                <div x-show="step === 'code'" class="flex items-center justify-between text-[12.5px]">
                    <button type="button" class="text-tx3 underline" @click="reset()">Change number</button>
                    <button type="button" class="text-ac underline disabled:text-tx3 disabled:no-underline" :disabled="wait > 0" @click="send()"
                            x-text="wait > 0 ? 'Resend in ' + wait + 's' : 'Resend code'"></button>
                </div>
            </form>
            <p class="mt-4 text-[11.5px] leading-relaxed text-tx3">By continuing you agree to eClinicPro’s <a class="underline" href="https://eclinicpro.com/terms" target="_blank" rel="noopener">terms</a> and <a class="underline" href="https://eclinicpro.com/privacy-policy" target="_blank" rel="noopener">privacy policy</a>.</p>
        </section>
    </div>
</div>
<script>
function creatorLogin(cfg) {
    const MESSAGES = {
        invalid_phone: 'Please enter a valid 10-digit mobile number.',
        phone_required: 'Please enter your mobile number.',
        captcha_failed: 'Please tick “I’m not a robot” and try again.',
        otp_locked: 'Too many attempts. Please try again later.',
        resend_too_soon: 'Please wait a few seconds before asking for a new code.',
        invalid_code: 'That code is not right. Please check and try again.',
        expired: 'This code has expired. Tap “Resend code”.',
        no_code_issued: 'Please ask for a new code.',
        too_many_attempts: 'Too many wrong tries. Tap “Resend code” for a new one.',
    };
    return {
        step: 'phone', phone: '', code: '', name: '', needName: false,
        busy: false, error: '', devCode: '', wait: 0, timer: null,
        captchaToken() {
            if (!cfg.captcha || typeof grecaptcha === 'undefined') return '';
            return grecaptcha.getResponse() || '';
        },
        captchaReset() {
            if (cfg.captcha && typeof grecaptcha !== 'undefined') { try { grecaptcha.reset(); } catch (e) {} }
        },
        async call(action, body) {
            const res = await fetch(cfg.api + '?action=' + action, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            let data = {};
            try { data = await res.json(); } catch (e) {}
            return data;
        },
        tick(seconds) {
            this.wait = seconds;
            clearInterval(this.timer);
            this.timer = setInterval(() => { if (--this.wait <= 0) clearInterval(this.timer); }, 1000);
        },
        async send() {
            this.error = '';
            const digits = this.phone.replace(/\D/g, '');
            if (digits.length !== 10) { this.error = MESSAGES.invalid_phone; return; }
            if (cfg.captcha && !this.captchaToken()) { this.error = MESSAGES.captcha_failed; return; }
            this.busy = true;
            try {
                const d = await this.call('send_otp', { phone: digits, captcha_token: this.captchaToken() });
                if (!d.ok) {
                    this.error = MESSAGES[d.error] || 'We could not send the code. Please try again in a minute.';
                    if (d.retry_after) this.tick(d.retry_after);
                    return;
                }
                this.needName = !d.exists;
                this.devCode = d.dev_code || '';
                this.step = 'code';
                this.tick(30);
                this.$nextTick(() => this.$refs.code && this.$refs.code.focus());
            } catch (e) {
                this.error = 'Network problem. Please check your connection and try again.';
            } finally {
                this.busy = false;
                this.captchaReset();
            }
        },
        async verify() {
            this.error = '';
            if (this.code.replace(/\D/g, '').length !== 6) { this.error = 'Enter the 6-digit code.'; return; }
            if (this.needName && this.name.trim().length < 2) { this.error = 'Please enter your name.'; return; }
            if (cfg.captcha && !this.captchaToken()) { this.error = MESSAGES.captcha_failed; return; }
            this.busy = true;
            try {
                const d = await this.call('verify_otp', {
                    phone: this.phone.replace(/\D/g, ''), code: this.code.replace(/\D/g, ''),
                    name: this.needName ? this.name.trim() : undefined, captcha_token: this.captchaToken(),
                });
                if (!d.ok) { this.error = MESSAGES[d.error] || 'Could not verify. Please try again.'; return; }
                window.location.href = '/creator/dashboard';
            } catch (e) {
                this.error = 'Network problem. Please check your connection and try again.';
            } finally {
                this.busy = false;
                this.captchaReset();
            }
        },
        reset() { this.step = 'phone'; this.code = ''; this.error = ''; this.devCode = ''; },
    };
}
</script>
</body>
</html>
