<?php
// =====================================================================
// doctor-claim-modal.php — shared modal for the two doctor-side flows:
//
//   1) Claim an existing directory listing
//      window.ecpClaim.open('claim', { id: 123, name: 'Vasupujya Dental', ... })
//
//   2) Request to be listed
//      window.ecpClaim.open('new_listing')
//
// 3-step flow:
//   step 1 → phone + Send OTP
//   step 2 → 6-digit code
//   step 3 → full form (name, clinic, city, specialty, optional doc)
//
// Phone is verified before they fill the rest, so we never collect doctor
// details from a fake number.
// =====================================================================
?>

<div id="ecp-claim-modal" x-data="ecpClaimModal()" x-show="open" x-cloak
     @keydown.escape.window="close()"
     class="auth-overlay" @click.self="close()">
  <div class="auth-card" style="max-width: 480px;" role="dialog" aria-modal="true">
    <button type="button" class="auth-close" @click="close()" aria-label="Close">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    <div class="auth-head">
      <div class="auth-logo">e<em>ClinicPro</em></div>
      <h2 x-text="headline()"></h2>
      <p class="auth-sub" x-text="subline()"></p>
    </div>

    <!-- Step progress -->
    <div class="claim-steps" x-show="!done">
      <div class="claim-step" :class="step >= 1 ? 'is-active' : ''"><span>1</span> Verify phone</div>
      <div class="claim-sep"></div>
      <div class="claim-step" :class="step >= 2 ? 'is-active' : ''"><span>2</span> Enter code</div>
      <div class="claim-sep"></div>
      <div class="claim-step" :class="step >= 3 ? 'is-active' : ''"><span>3</span> Your details</div>
    </div>

    <!-- STEP 1: phone -->
    <form class="auth-form" x-show="step === 1 && !done" @submit.prevent="sendOtp()">
      <template x-if="context && context.name">
        <div class="claim-listing">
          <strong x-text="context.name"></strong>
          <span x-text="[context.area, context.city].filter(Boolean).join(', ')"></span>
        </div>
      </template>
      <label>
        <span class="lbl">Your mobile number</span>
        <div class="auth-phone-field">
          <span class="auth-cc">+91</span>
          <input type="tel" inputmode="numeric" maxlength="10" required
                 x-model="phoneDigits"
                 @input="phoneDigits = phoneDigits.replace(/\D/g, '').slice(0,10)"
                 :disabled="busy" placeholder="98XXXXXXXX">
        </div>
      </label>
      <p class="auth-hint">We'll send a one-time code via SMS to verify it's really you.</p>
      <p class="auth-error" x-show="errorMsg" x-text="errorMsg"></p>
      <button type="submit" class="auth-btn primary" :disabled="busy || phoneDigits.length < 10">
        <span x-show="!busy">Send code</span>
        <span x-show="busy">Sending…</span>
      </button>
    </form>

    <!-- STEP 2: code -->
    <form class="auth-form" x-show="step === 2 && !done" @submit.prevent="verifyOtp()">
      <div class="auth-back" @click="step = 1; errorMsg = ''">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        <span>Change number</span>
      </div>
      <div class="auth-sent-to">
        Code sent to <strong x-text="'+91 ' + phoneDigits"></strong>
      </div>
      <template x-if="devCode">
        <div class="auth-devcode">
          <span class="tag">DEV MODE</span>
          Your code: <strong x-text="devCode"></strong>
        </div>
      </template>
      <label>
        <span class="lbl">6-digit code</span>
        <input type="text" inputmode="numeric" maxlength="6" required
               x-model="code" x-ref="codeInput"
               @input="code = code.replace(/\D/g, '').slice(0,6)"
               :disabled="busy" placeholder="••••••">
      </label>
      <p class="auth-error" x-show="errorMsg" x-text="errorMsg"></p>
      <button type="submit" class="auth-btn primary" :disabled="busy || code.length !== 6">
        <span x-show="!busy">Verify</span>
        <span x-show="busy">Verifying…</span>
      </button>
      <button type="button" class="auth-btn ghost"
              :disabled="busy || resendCountdown > 0" @click="resendOtp()">
        <span x-show="resendCountdown === 0">Resend code</span>
        <span x-show="resendCountdown > 0">Resend in <span x-text="resendCountdown"></span>s</span>
      </button>
    </form>

    <!-- STEP 3: doctor details + optional document upload -->
    <form class="auth-form" x-show="step === 3 && !done" @submit.prevent="submitForm()"
          enctype="multipart/form-data">
      <label>
        <span class="lbl">Full name *</span>
        <input type="text" x-model="form.full_name" required maxlength="120"
               :disabled="busy" placeholder="Dr. Riya Mehta">
      </label>

      <label>
        <span class="lbl">Clinic / Hospital name *</span>
        <input type="text" x-model="form.clinic_name" required maxlength="180"
               :disabled="busy" placeholder="Mehta Eye Care">
      </label>

      <div class="auth-form-row">
        <label style="flex: 1;">
          <span class="lbl">Specialty *</span>
          <select x-model="form.specialty" required :disabled="busy">
            <option value="">Choose…</option>
            <template x-for="s in specialties" :key="s.id">
              <option :value="s.id" x-text="s.label"></option>
            </template>
          </select>
        </label>
        <label style="flex: 1;">
          <span class="lbl">City *</span>
          <input type="text" x-model="form.city" required maxlength="80"
                 :disabled="busy" placeholder="Ahmedabad">
        </label>
      </div>

      <label>
        <span class="lbl">Email <em>(optional, for backup contact)</em></span>
        <input type="email" x-model="form.email" maxlength="160"
               :disabled="busy" placeholder="you@clinic.com">
      </label>

      <div class="auth-form-row">
        <label style="flex: 1;">
          <span class="lbl">Reg. number <em>(optional)</em></span>
          <input type="text" x-model="form.reg_number" maxlength="60"
                 :disabled="busy" placeholder="e.g. G-12345">
        </label>
        <label style="flex: 1;">
          <span class="lbl">Issued by <em>(optional)</em></span>
          <input type="text" x-model="form.reg_council" maxlength="80"
                 :disabled="busy" placeholder="Gujarat Medical Council">
        </label>
      </div>

      <label>
        <span class="lbl">Registration certificate <em>(JPG/PNG/PDF, &le;5MB)</em></span>
        <input type="file" x-ref="documentInput" accept="image/jpeg,image/png,image/webp,application/pdf"
               :disabled="busy">
        <span class="auth-hint" style="margin-top: 4px;">
          Speeds up review. You can also upload later from your dashboard.
        </span>
      </label>

      <label>
        <span class="lbl">Anything we should know? <em>(optional)</em></span>
        <textarea x-model="form.message" rows="2" maxlength="2000"
                  :disabled="busy" placeholder="e.g. I run two clinics in Ahmedabad — both should be linked."></textarea>
      </label>

      <p class="auth-error" x-show="errorMsg" x-text="errorMsg"></p>

      <button type="submit" class="auth-btn primary"
              :disabled="busy || !form.full_name.trim() || !form.clinic_name.trim() || !form.specialty || !form.city.trim()">
        <span x-show="!busy">Submit for review</span>
        <span x-show="busy">Submitting…</span>
      </button>
    </form>

    <!-- DONE state -->
    <div class="claim-done" x-show="done">
      <div class="claim-done-icon">✓</div>
      <h3>Request submitted</h3>
      <p>
        Thanks! Our team will review your request within 1–2 business days.
        We'll text you on <strong x-text="'+91 ' + phoneDigits"></strong> when it's approved.
      </p>
      <p class="claim-ref">Reference #<span x-text="resultId"></span></p>
      <button type="button" class="auth-btn primary" @click="close()">Close</button>
    </div>
  </div>
</div>

<style>
/* Reuses .auth-* styles from auth-modal.php; just adds claim-specific bits */
.claim-steps {
  display: flex; align-items: center;
  margin-bottom: 18px;
  font-size: 12px; color: var(--mute);
}
.claim-step {
  display: inline-flex; align-items: center; gap: 6px;
  font-weight: 500;
}
.claim-step span {
  width: 22px; height: 22px;
  border-radius: 50%;
  background: var(--bg-2);
  display: inline-grid; place-items: center;
  font-weight: 700; font-size: 12px;
  color: var(--mute);
}
.claim-step.is-active { color: var(--ink); }
.claim-step.is-active span { background: var(--teal-600); color: #fff; }
.claim-sep { flex: 1; height: 1px; background: var(--line); margin: 0 8px; }

.claim-listing {
  background: var(--teal-50);
  border: 1px solid rgba(15,155,110,0.18);
  padding: 12px 14px;
  border-radius: 10px;
  display: flex; flex-direction: column;
  margin-bottom: 4px;
}
.claim-listing strong { color: var(--ink); font-size: 14px; font-weight: 600; }
.claim-listing span { font-size: 12px; color: var(--ink-2); margin-top: 2px; }

.auth-form-row { display: flex; gap: 10px; }
.auth-form select,
.auth-form textarea {
  border: 1px solid var(--line);
  border-radius: 11px;
  padding: 13px 14px;
  font: inherit; font-size: 15px;
  outline: none; width: 100%;
  transition: border-color .15s, box-shadow .15s;
  background: #fff;
}
.auth-form select:focus,
.auth-form textarea:focus {
  border-color: var(--teal-400);
  box-shadow: 0 0 0 3px rgba(15,155,110,0.14);
}
.auth-form textarea { resize: vertical; font-family: inherit; }
.auth-form input[type="file"] {
  font-size: 13.5px; color: var(--ink-2);
  padding: 8px 0;
}

.claim-done { text-align: center; padding: 14px 4px; }
.claim-done-icon {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: var(--teal-50);
  color: var(--teal-700);
  font-size: 30px; font-weight: 700;
  display: grid; place-items: center;
  margin: 0 auto 16px;
}
.claim-done h3 { font-size: 20px; font-weight: 600; margin-bottom: 8px; letter-spacing: -0.3px; }
.claim-done p { font-size: 14px; color: var(--ink-2); margin-bottom: 12px; line-height: 1.5; }
.claim-done .claim-ref {
  font-size: 12px; color: var(--mute);
  font-family: 'JetBrains Mono', ui-monospace, Menlo, monospace;
  margin-bottom: 18px;
}
.claim-done .auth-btn { width: 100%; }

@media (max-width: 480px) {
  .auth-form-row { flex-direction: column; gap: 12px; }
  .claim-steps { font-size: 11px; }
  .claim-step span { width: 20px; height: 20px; font-size: 11px; }
}
</style>

<script>
function ecpClaimModal() {
  return {
    open: false,
    step: 1,                  // 1 phone → 2 code → 3 details
    done: false,              // success screen
    busy: false,
    errorMsg: '',
    devCode: null,
    resendCountdown: 0,
    _resendTimer: null,
    _type: 'claim',
    context: null,            // {id, name, area, city} when claiming
    resultId: null,
    phoneDigits: '',
    code: '',
    form: {
      full_name: '',
      clinic_name: '',
      city: '',
      specialty: '',
      email: '',
      reg_number: '',
      reg_council: '',
      message: '',
    },
    specialties: [],

    init() {
      // Expose a tiny global API the find-a-doctor page calls.
      window.ecpClaim = {
        open:  (type, ctx) => this.openModal(type, ctx),
        close: ()          => this.close(),
      };
      // Reuse the specialty list from FD_DATA if find-a-doctor is on this page.
      if (window.FD_DATA && Array.isArray(window.FD_DATA.specialties)) {
        this.specialties = window.FD_DATA.specialties;
      }
    },

    openModal(type, ctx) {
      this._type   = type || 'claim';
      this.context = ctx || null;
      this.step    = 1;
      this.done    = false;
      this.busy    = false;
      this.errorMsg = '';
      this.devCode = null;
      this.resultId = null;
      this.code = '';
      // Pre-fill from the context where possible.
      if (this.context) {
        this.form.clinic_name = this.context.clinicName || this.context.name || '';
        this.form.city        = this.context.city  || '';
        this.form.specialty   = this.context.spec  || '';
        this.form.full_name   = this.context.doctorName || '';
      } else {
        this.form = { full_name: '', clinic_name: '', city: '', specialty: '',
                      email: '', reg_number: '', reg_council: '', message: '' };
      }
      this.open = true;
      document.body.style.overflow = 'hidden';
    },

    close() {
      this.open = false;
      document.body.style.overflow = '';
      if (this._resendTimer) { clearInterval(this._resendTimer); this._resendTimer = null; }
    },

    headline() {
      if (this.done) return 'Submitted ✓';
      if (this._type === 'new_listing') return 'List your clinic';
      return 'Claim this listing';
    },
    subline() {
      if (this.done) return '';
      if (this._type === 'new_listing')
        return "Not in our directory yet? Tell us and we'll add you.";
      return 'Verify your number, then confirm a few details. We review claims within 1–2 days.';
    },

    async sendOtp() {
      if (this.phoneDigits.length < 10) return;
      this.busy = true; this.errorMsg = ''; this.devCode = null;
      try {
        const r = await fetch('/api/doctor_claim?action=send_otp', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ phone: '+91' + this.phoneDigits }),
        });
        const text = await r.text();
        let j; try { j = JSON.parse(text); } catch (e) {
          this.errorMsg = 'Server returned an unexpected response (HTTP ' + r.status + ').';
          return;
        }
        if (!j.ok) { this.errorMsg = this.errorText(j.error, j.retry_after); return; }
        if (j.dev_code) this.devCode = j.dev_code;
        this.step = 2;
        this.startResendCountdown(30);
        this.$nextTick(() => this.$refs.codeInput && this.$refs.codeInput.focus());
      } catch (e) {
        this.errorMsg = "Couldn't reach server. Try again.";
      } finally { this.busy = false; }
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
        const r = await fetch('/api/doctor_claim?action=verify_otp', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ phone: '+91' + this.phoneDigits, code: this.code }),
        });
        const j = await r.json();
        if (!j.ok) { this.errorMsg = this.errorText(j.error); return; }
        this.step = 3;
      } catch (e) {
        this.errorMsg = "Couldn't reach server. Try again.";
      } finally { this.busy = false; }
    },

    async submitForm() {
      this.busy = true; this.errorMsg = '';
      try {
        const fd = new FormData();
        fd.append('type', this._type);
        if (this.context && this.context.id) fd.append('directory_doctor_id', this.context.id);
        fd.append('phone', '+91' + this.phoneDigits);
        for (const [k, v] of Object.entries(this.form)) {
          fd.append(k, v || '');
        }
        const fileInput = this.$refs.documentInput;
        if (fileInput && fileInput.files && fileInput.files[0]) {
          fd.append('document', fileInput.files[0]);
        }

        const r = await fetch('/api/doctor_claim?action=submit', {
          method: 'POST', body: fd,
        });
        const j = await r.json();
        if (!j.ok) { this.errorMsg = this.errorText(j.error) + (j.hint ? ' — ' + j.hint : ''); return; }
        this.resultId = j.request_id;
        this.done = true;
      } catch (e) {
        this.errorMsg = "Couldn't reach server. Try again.";
      } finally { this.busy = false; }
    },

    errorText(code, retryAfter) {
      switch (code) {
        case 'invalid_phone':           return "That number doesn't look right.";
        case 'resend_too_soon':         return retryAfter
                                          ? `Please wait ${retryAfter}s before requesting another code.`
                                          : 'Please wait a moment before requesting another code.';
        case 'invalid_code':            return 'That code is incorrect. Try again.';
        case 'expired':                 return 'Code expired. Tap Resend.';
        case 'too_many_attempts':       return 'Too many attempts. Request a new code.';
        case 'no_code_issued':          return 'No active code. Tap Resend.';
        case 'phone_not_verified':      return 'Your verification expired. Please start over.';
        case 'missing_required_fields': return 'Please fill in all required (*) fields.';
        case 'file_too_large':          return 'File too large (max 5 MB).';
        case 'file_type_not_allowed':   return 'Only JPG, PNG, WebP or PDF allowed.';
        case 'directory_doctor_id_required': return 'Internal error — couldn\'t identify the listing.';
        case 'db_unavailable':          return 'Database unreachable. Please try again shortly.';
        default:                        return 'Something went wrong. Please try again.';
      }
    },
  };
}
</script>
