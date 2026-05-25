<?php
// =====================================================================
// auth-modal.php — shared patient login modal.
// Included once from header.php. Available on every page.
//
// Open it from JS:
//     window.ecpAuth.require('save_doctor', () => { /* do thing */ });
// If already logged in, the callback runs immediately.
// If logged out, the modal opens; callback fires after successful OTP.
//
// Reasons (used only for the headline copy):
//     save_doctor  — "Save this doctor"
//     book         — "Book an appointment"
//     default      — "Sign in to continue"
// =====================================================================
?>

<div id="ecp-auth-modal" x-data="ecpAuthModal()" x-show="open" x-cloak
     @keydown.escape.window="close()"
     class="auth-overlay" @click.self="close()">
  <div class="auth-card" role="dialog" aria-modal="true" aria-labelledby="ecp-auth-title">
    <button type="button" class="auth-close" @click="close()" aria-label="Close">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    <div class="auth-head">
      <div class="auth-logo">e<em>ClinicPro</em></div>
      <h2 id="ecp-auth-title" x-text="headline()"></h2>
      <p class="auth-sub" x-text="subline()"></p>
    </div>

    <!-- Sign in / Create account tabs (visible only on STEP 1) -->
    <div class="auth-tabs" role="tablist" x-show="step === 'phone'">
      <button type="button" role="tab"
              :class="intent === 'signin' ? 'is-active' : ''"
              @click="intent = 'signin'; errorMsg = ''">
        Sign in
      </button>
      <button type="button" role="tab"
              :class="intent === 'signup' ? 'is-active' : ''"
              @click="intent = 'signup'; errorMsg = ''">
        Create account
      </button>
    </div>

    <!-- STEP 1: phone -->
    <form class="auth-form" x-show="step === 'phone'" @submit.prevent="sendOtp()">
      <label>
        <span class="lbl">Mobile number</span>
        <div class="auth-phone-field">
          <span class="auth-cc">+91</span>
          <input type="tel" inputmode="numeric" autocomplete="tel-national"
                 maxlength="10" x-model="phoneDigits"
                 @input="phoneDigits = phoneDigits.replace(/\D/g, '').slice(0,10)"
                 :disabled="busy" placeholder="98XXXXXXXX" required>
        </div>
      </label>
      <p class="auth-hint" x-text="step1Hint()"></p>
      <p class="auth-error" x-show="errorMsg" x-html="errorMsg"></p>
      <button type="submit" class="auth-btn primary" :disabled="busy || phoneDigits.length < 10">
        <span x-show="!busy" x-text="intent === 'signup' ? 'Create my account' : 'Send code'"></span>
        <span x-show="busy">Sending…</span>
      </button>
      <p class="auth-tos">
        By continuing you agree to our <a href="/security" target="_blank">privacy &amp; terms</a>.
      </p>
    </form>

    <!-- STEP 2: code (+ name for new signups) -->
    <form class="auth-form" x-show="step === 'code'" @submit.prevent="verifyOtp()">
      <div class="auth-back" @click="step = 'phone'; errorMsg = ''">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        <span>Change number</span>
      </div>

      <!-- Returning user → friendly greeting -->
      <template x-if="phoneExists && nameHint">
        <div class="auth-welcome">
          Welcome back, <strong x-text="nameHint"></strong> 👋
          <span>Enter the code we just sent to <strong x-text="'+91 ' + phoneDigits"></strong>.</span>
        </div>
      </template>

      <!-- New signup → confirmation -->
      <template x-if="!phoneExists">
        <div class="auth-welcome new">
          Setting up your new account
          <span>We sent a verification code to <strong x-text="'+91 ' + phoneDigits"></strong>.</span>
        </div>
      </template>

      <template x-if="devCode">
        <div class="auth-devcode">
          <span class="tag">DEV MODE</span>
          Your code: <strong x-text="devCode"></strong>
        </div>
      </template>

      <label>
        <span class="lbl">6-digit code</span>
        <input type="text" inputmode="numeric" autocomplete="one-time-code"
               maxlength="6" x-model="code" x-ref="codeInput"
               @input="code = code.replace(/\D/g, '').slice(0,6)"
               :disabled="busy" placeholder="••••••" required>
      </label>

      <!-- Name field: ONLY shown for new accounts -->
      <template x-if="!phoneExists">
        <label>
          <span class="lbl">Your full name</span>
          <input type="text" x-model="name" :disabled="busy"
                 placeholder="e.g. Riya Mehta" maxlength="120" required>
        </label>
      </template>

      <p class="auth-error" x-show="errorMsg" x-html="errorMsg"></p>

      <button type="submit" class="auth-btn primary"
              :disabled="busy || code.length !== 6 || (!phoneExists && !name.trim())">
        <span x-show="!busy" x-text="phoneExists ? 'Sign in' : 'Create account'"></span>
        <span x-show="busy">Verifying…</span>
      </button>

      <button type="button" class="auth-btn ghost" :disabled="busy || resendCountdown > 0"
              @click="resendOtp()">
        <span x-show="resendCountdown === 0">Resend code</span>
        <span x-show="resendCountdown > 0">Resend in <span x-text="resendCountdown"></span>s</span>
      </button>
    </form>
  </div>
</div>

<style>
.auth-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 30, 0.55);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000; padding: 16px;
  animation: ecpFadeIn .15s ease;
}
@keyframes ecpFadeIn { from { opacity: 0; } to { opacity: 1; } }

.auth-card {
  background: #fff;
  border-radius: 22px;
  width: 100%; max-width: 420px;
  padding: 32px 32px 28px;
  box-shadow: 0 30px 80px rgba(0,0,0,0.25);
  position: relative;
  animation: ecpPopIn .2s ease;
}
@keyframes ecpPopIn {
  from { transform: translateY(8px) scale(0.98); opacity: 0; }
  to   { transform: translateY(0) scale(1);     opacity: 1; }
}

.auth-close {
  position: absolute; top: 14px; right: 14px;
  width: 34px; height: 34px;
  border-radius: 50%;
  border: 0; background: transparent;
  color: var(--mute, #888);
  cursor: pointer;
  display: grid; place-items: center;
  transition: background .15s, color .15s;
}
.auth-close:hover { background: var(--bg-2, #f3f3f3); color: var(--ink, #000); }

.auth-head { text-align: center; margin-bottom: 22px; }
.auth-logo {
  font-family: inherit;
  font-size: 18px; font-weight: 500;
  color: var(--ink); letter-spacing: -0.4px;
  margin-bottom: 14px;
}
.auth-logo em {
  font-style: normal; font-weight: 600;
  background: linear-gradient(135deg, var(--teal-700), var(--teal-400));
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.auth-head h2 {
  font-size: 22px; font-weight: 600;
  letter-spacing: -0.5px;
  margin-bottom: 6px;
  line-height: 1.25;
}
.auth-sub { font-size: 14px; color: var(--mute); line-height: 1.5; }

.auth-form { display: flex; flex-direction: column; gap: 14px; }
.auth-form label { display: flex; flex-direction: column; gap: 6px; }
.auth-form .lbl {
  font-size: 11px; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--mute);
}
.auth-form .lbl em {
  font-style: normal; text-transform: none;
  letter-spacing: normal; color: var(--teal-700); font-weight: 500;
}
.auth-form input {
  border: 1px solid var(--line);
  border-radius: 11px;
  padding: 13px 14px;
  font: inherit; font-size: 16px;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
  width: 100%;
}
.auth-form input:focus {
  border-color: var(--teal-400);
  box-shadow: 0 0 0 3px rgba(15,155,110,0.14);
}
.auth-form input:disabled { background: var(--bg-2); opacity: 0.7; }

.auth-phone-field {
  display: flex; align-items: stretch;
  border: 1px solid var(--line);
  border-radius: 11px;
  overflow: hidden;
  transition: border-color .15s, box-shadow .15s;
}
.auth-phone-field:focus-within {
  border-color: var(--teal-400);
  box-shadow: 0 0 0 3px rgba(15,155,110,0.14);
}
.auth-cc {
  background: var(--bg-2);
  padding: 13px 14px;
  font-weight: 600; font-size: 15px;
  color: var(--ink-2);
  border-right: 1px solid var(--line);
}
.auth-phone-field input {
  border: 0; border-radius: 0;
  flex: 1;
}
.auth-phone-field input:focus { box-shadow: none; }

.auth-hint, .auth-tos {
  font-size: 12.5px; color: var(--mute);
  line-height: 1.5; margin: -4px 0 0;
}
.auth-tos { text-align: center; margin-top: 4px; }
.auth-tos a { color: var(--teal-700); text-decoration: underline; }

.auth-error {
  font-size: 13px; color: #c0392b;
  background: rgba(192,57,43,0.06);
  border: 1px solid rgba(192,57,43,0.15);
  border-radius: 8px;
  padding: 9px 11px;
  margin: 0;
}

.auth-btn {
  display: inline-flex; align-items: center; justify-content: center;
  border: 1px solid var(--line); background: #fff;
  padding: 13px 18px;
  border-radius: 11px;
  font: inherit; font-size: 14.5px; font-weight: 600;
  color: var(--ink-2);
  cursor: pointer;
  transition: all .15s;
}
.auth-btn:hover:not(:disabled) { border-color: var(--ink); color: var(--ink); }
.auth-btn.primary {
  background: var(--teal-600); color: #fff; border-color: var(--teal-600);
}
.auth-btn.primary:hover:not(:disabled) {
  background: var(--teal-700); border-color: var(--teal-700);
  box-shadow: 0 4px 14px rgba(15,155,110,0.28);
}
.auth-btn.ghost { background: transparent; border: 0; color: var(--ink-2); }
.auth-btn.ghost:hover:not(:disabled) { color: var(--teal-700); }
.auth-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.auth-back {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12.5px; font-weight: 600;
  color: var(--ink-2); cursor: pointer;
  align-self: flex-start;
  padding: 4px 0;
}
.auth-back:hover { color: var(--teal-700); }

.auth-sent-to {
  font-size: 13.5px; color: var(--ink-2);
  background: var(--bg-2);
  padding: 10px 12px;
  border-radius: 9px;
  text-align: center;
}
.auth-sent-to strong { color: var(--ink); font-weight: 600; }

/* Sign in / Create account tabs */
.auth-tabs {
  display: flex;
  background: var(--bg-2);
  border-radius: 12px;
  padding: 4px;
  gap: 4px;
  margin-bottom: 18px;
}
.auth-tabs button {
  flex: 1;
  background: transparent;
  border: 0;
  padding: 10px 12px;
  border-radius: 9px;
  font: inherit; font-size: 13.5px; font-weight: 600;
  color: var(--mute);
  cursor: pointer;
  transition: all .15s;
}
.auth-tabs button:hover { color: var(--ink); }
.auth-tabs button.is-active {
  background: #fff;
  color: var(--ink);
  box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 0 0 1px rgba(0,0,0,0.04);
}

/* Step-2 greeting card */
.auth-welcome {
  background: var(--teal-50);
  color: var(--teal-800);
  border: 1px solid rgba(15,155,110,0.15);
  padding: 14px 16px;
  border-radius: 12px;
  font-size: 14.5px;
  font-weight: 600;
  display: flex; flex-direction: column; gap: 4px;
}
.auth-welcome strong { font-weight: 700; }
.auth-welcome span {
  font-size: 13px; font-weight: 400;
  color: var(--ink-2);
}
.auth-welcome.new {
  background: #f0f4ff;
  color: #1e3a8a;
  border-color: rgba(30,58,138,0.15);
}
.auth-welcome.new span { color: var(--ink-2); }

/* Inline link inside an error message */
.auth-error a {
  color: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.auth-devcode {
  font-size: 13px;
  background: #fff7e0;
  border: 1px solid #f5d97e;
  color: #6b4f00;
  padding: 10px 12px;
  border-radius: 9px;
  display: flex; align-items: center; gap: 10px;
}
.auth-devcode .tag {
  font-size: 10px; font-weight: 700;
  letter-spacing: 0.08em;
  background: #f5d97e; color: #6b4f00;
  padding: 2px 6px; border-radius: 4px;
}
.auth-devcode strong {
  font-family: 'JetBrains Mono', ui-monospace, Menlo, monospace;
  font-size: 16px; letter-spacing: 2px;
  color: #6b4f00;
  margin-left: auto;
}

@media (max-width: 480px) {
  .auth-card { padding: 26px 22px 22px; border-radius: 18px; }
  .auth-head h2 { font-size: 19px; }
}
</style>

<script>
function ecpAuthModal() {
  return {
    open: false,
    step: 'phone',           // 'phone' | 'code'
    intent: 'signin',        // 'signin' | 'signup' — user's tab choice
    phoneDigits: '',
    code: '',
    name: '',
    phoneExists: false,      // set by server response after sendOtp
    nameHint: null,          // first name of returning user (for greeting)
    devCode: null,
    busy: false,
    errorMsg: '',
    resendCountdown: 0,
    _resendTimer: null,
    _reason: 'default',
    _afterLogin: null,

    init() {
      // Expose a tiny global API the rest of the site uses.
      window.ecpAuth = {
        require: (reason, cb) => this.require(reason, cb),
        open:    (reason)     => this.openModal(reason || 'default'),
        close:   ()           => this.close(),
        logout:  async ()     => {
          try {
            await fetch('/api/patient_auth?action=logout', { method: 'POST', credentials: 'same-origin' });
          } catch (e) {}
          try { window.dispatchEvent(new StorageEvent('storage', { key: 'ecp_patient' })); } catch (e) {}
          location.reload();
        },
        me: async () => {
          try {
            const r = await fetch('/api/patient_auth?action=me', { credentials: 'same-origin' });
            const j = await r.json();
            return j.patient || null;
          } catch (e) { return null; }
        },
      };
      window.addEventListener('ecp:open-auth', e => this.openModal(e.detail?.reason || 'default'));
    },

    async require(reason, cb) {
      const me = await window.ecpAuth.me();
      if (me) { cb && cb(me); return; }
      this._afterLogin = cb;
      this.openModal(reason);
    },

    openModal(reason) {
      this._reason = reason || 'default';
      this.intent = 'signin';   // default to sign in; user can flip
      this.step = 'phone';
      this.errorMsg = '';
      this.devCode = null;
      this.code = '';
      this.name = '';
      this.phoneExists = false;
      this.nameHint = null;
      this.open = true;
      document.body.style.overflow = 'hidden';
    },

    close() {
      this.open = false;
      this._afterLogin = null;
      document.body.style.overflow = '';
      if (this._resendTimer) { clearInterval(this._resendTimer); this._resendTimer = null; }
    },

    headline() {
      // On step 2 we always say "Verify your number" — the welcome card
      // covers the rest of the context.
      if (this.step === 'code') return 'Verify your number';
      // Step 1 — depends on intent + reason
      if (this.intent === 'signup') {
        return this._reason === 'save_doctor' ? 'Create account to save'
             : this._reason === 'book'        ? 'Create account to book'
             :                                  'Create your account';
      }
      return {
        save_doctor: 'Sign in to save this doctor',
        book:        'Sign in to book',
        default:     'Sign in to continue',
      }[this._reason] || 'Sign in to continue';
    },
    subline() {
      if (this.step === 'code') {
        return this.phoneExists
          ? "Almost there — enter the code we texted you."
          : "Last step before your account is ready.";
      }
      return this.intent === 'signup'
        ? "New to eClinicPro? Enter your mobile number — we'll text you a code."
        : "Enter your mobile number — we'll text you a code. No password needed.";
    },
    step1Hint() {
      return this.intent === 'signup'
        ? "We'll create your account once you confirm the code."
        : "We'll send a 6-digit code via SMS. No password to remember.";
    },

    async sendOtp() {
      if (this.phoneDigits.length < 10) return;
      this.busy = true; this.errorMsg = ''; this.devCode = null;
      try {
        const r = await fetch('/api/patient_auth?action=send_otp', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            phone:  '+91' + this.phoneDigits,
            intent: this.intent,
          }),
        });
        const text = await r.text();
        let j;
        try { j = JSON.parse(text); }
        catch (e) {
          this.errorMsg = 'Server returned an unexpected response (HTTP ' + r.status + '). ' +
                          (text.slice(0, 160) || 'Empty body');
          console.error('[ecpAuth] non-JSON response:', text);
          return;
        }
        if (!j.ok) {
          // Smart messages: if user picked the wrong tab, offer to flip it.
          if (j.error === 'account_not_found') {
            this.errorMsg = "We don't see an account with this number. " +
              '<a onclick="document.querySelector(\'#ecp-auth-modal\').__x.$data.flipTo(\'signup\')">Create one instead?</a>';
          } else if (j.error === 'account_exists') {
            this.errorMsg = 'This number is already registered. ' +
              '<a onclick="document.querySelector(\'#ecp-auth-modal\').__x.$data.flipTo(\'signin\')">Sign in instead?</a>';
          } else {
            this.errorMsg = this.errorText(j.error, j.retry_after) + (j.hint ? ' — ' + j.hint : '');
          }
          return;
        }
        // Capture whether this is a returning user, so step 2 can greet them.
        this.phoneExists = !!j.exists;
        this.nameHint    = j.name_hint || null;
        if (j.dev_code)  this.devCode = j.dev_code;
        this.step = 'code';
        this.startResendCountdown(30);
        this.$nextTick(() => this.$refs.codeInput && this.$refs.codeInput.focus());
      } catch (e) {
        this.errorMsg = "Couldn't reach server: " + (e.message || e);
      } finally {
        this.busy = false;
      }
    },

    // Called from the inline link in the error messages above.
    flipTo(newIntent) {
      this.intent = newIntent;
      this.errorMsg = '';
    },

    async resendOtp() {
      this.code = '';
      await this.sendOtp();
    },

    startResendCountdown(secs) {
      this.resendCountdown = secs;
      if (this._resendTimer) clearInterval(this._resendTimer);
      this._resendTimer = setInterval(() => {
        this.resendCountdown -= 1;
        if (this.resendCountdown <= 0) { clearInterval(this._resendTimer); this._resendTimer = null; }
      }, 1000);
    },

    async verifyOtp() {
      if (this.code.length !== 6) return;
      this.busy = true; this.errorMsg = '';
      try {
        const r = await fetch('/api/patient_auth?action=verify_otp', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            phone: '+91' + this.phoneDigits,
            code:  this.code,
            name:  this.name || undefined,
          }),
        });
        const text = await r.text();
        let j;
        try { j = JSON.parse(text); }
        catch (e) {
          this.errorMsg = 'Server returned an unexpected response (HTTP ' + r.status + '). ' +
                          (text.slice(0, 160) || 'Empty body');
          console.error('[ecpAuth] non-JSON response:', text);
          return;
        }
        if (!j.ok) {
          this.errorMsg = this.errorText(j.error) + (j.hint ? ' — ' + j.hint : '');
          return;
        }

        // Success. Stash a minimal copy in localStorage so any other
        // open tabs pick up the new session via the 'storage' event.
        try {
          localStorage.setItem('ecp_patient', JSON.stringify({
            id:         j.patient.id,
            name:       j.patient.name || j.patient.first_name || 'Patient',
            first_name: j.patient.first_name || null,
            handle:     j.patient.phone,
          }));
        } catch (e) {}

        // If the caller wanted us to do something after login (save a
        // doctor, start a booking), do it BEFORE reloading.
        const cb = this._afterLogin;
        if (cb) {
          try { await cb(j.patient); } catch (e) { console.error(e); }
        }

        // Reload so the server-rendered header pill, patient page, etc.
        // reflect the new session immediately. Costs one round-trip but
        // removes a whole class of "why isn't the UI updating?" bugs.
        location.reload();
      } catch (e) {
        this.errorMsg = "Couldn't reach server. Check your connection.";
      } finally {
        this.busy = false;
      }
    },

    errorText(code, retryAfter) {
      switch (code) {
        case 'invalid_phone':     return 'That number doesn\'t look right.';
        case 'phone_required':    return 'Enter your mobile number.';
        case 'resend_too_soon':   return retryAfter
                                    ? `Please wait ${retryAfter}s before requesting another code.`
                                    : 'Please wait a moment before requesting another code.';
        case 'invalid_code':      return 'That code is incorrect. Try again.';
        case 'expired':           return 'Code expired. Tap Resend.';
        case 'too_many_attempts': return 'Too many attempts. Request a new code.';
        case 'no_code_issued':    return 'No active code. Tap Resend.';
        case 'db_unavailable':    return 'Database is unreachable. Check /app/.env credentials.';
        case 'sms_not_configured': return 'SMS isn\'t configured yet. Contact support.';
        case 'sms_send_failed':   return 'We couldn\'t send the SMS. Try again or use a different number.';
        case 'server_error':      return 'Server error.';
        default:                  return 'Something went wrong. Please try again.';
      }
    },
  };
}
</script>
