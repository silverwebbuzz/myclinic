<?php
// =====================================================================
// contact.php — public "Contact us" page with a common enquiry form.
// Posts to /api/contact?action=submit which emails hello@eclinicpro.com
// (sent from noreply@eclinicpro.com, Reply-To = the visitor).
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';   // ecp_recaptcha_config()

$pageTitle  = 'Contact us — eClinicPro';
$metaDesc   = 'Get in touch with the eClinicPro team. Questions about the patient app, doctor onboarding, sales or support — we usually reply within one business day.';
$activePage = 'contact';

$contactCaptcha        = ecp_recaptcha_config();
$contactCaptchaEnabled = !empty($contactCaptcha['enabled']);
$contactCaptchaSiteKey = (string) ($contactCaptcha['site_key'] ?? '');

require __DIR__ . '/partials/header.php';
?>

<section class="ct-page">
  <div class="wrap">

    <div class="ct-grid" x-data="contactForm(<?= $contactCaptchaEnabled ? 'true' : 'false' ?>)">

      <!-- Left: intro + direct contact details -->
      <div class="ct-intro">
        <span class="ct-eyebrow">We’d love to hear from you</span>
        <h1>Contact us</h1>
        <p class="ct-lede">
          Whether you’re a patient with a question, a doctor exploring eClinicPro,
          or just want to say hello — drop us a message and we’ll get back to you,
          usually within one business day.
        </p>

        <ul class="ct-channels">
          <li>
            <span class="ct-ch-ic">
              <img src="assets/img/icon/email.png" alt="Email" width="24" height="24">
            </span>
            <div>
              <b>Email</b>
              <a href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a>
            </div>
          </li>
          <li>
            <span class="ct-ch-ic">
              <img src="assets/img/icon/viber.png" alt="Phone" width="24" height="24">
            </span>
            <div>
              <b>Phone</b>
              <a href="tel:+919998010029">+91 99980 10029</a>
            </div>
          </li>
          <li>
            <span class="ct-ch-ic">
              <img src="assets/img/icon/internet.png" alt="Website" width="24" height="24">
            </span>
            <div>
              <b>Website</b>
              <a href="https://eclinicpro.com">eclinicpro.com</a>
            </div>
          </li>
        </ul>
      </div>

      <!-- Right: the enquiry form -->
      <div class="ct-card">
        <form class="ct-form" @submit.prevent="submit()" x-show="!sent">
          <div class="ct-row">
            <label class="ct-fld">
              <span>Your name *</span>
              <input type="text" x-model="form.name" :disabled="busy" maxlength="120" placeholder="e.g. Riya Mehta" required @focus="loadCaptcha()">
            </label>
            <label class="ct-fld">
              <span>Email *</span>
              <input type="email" x-model="form.email" :disabled="busy" placeholder="you@example.com" required>
            </label>
          </div>
          <div class="ct-row">
            <label class="ct-fld">
              <span>Phone <em>(optional)</em></span>
              <input type="tel" x-model="form.phone" :disabled="busy" placeholder="+91 98XXXXXXXX" inputmode="tel">
            </label>
            <label class="ct-fld">
              <span>I’m contacting about</span>
              <select x-model="form.subject" :disabled="busy">
                <option>General enquiry</option>
                <option>Patient app support</option>
                <option>Doctor / clinic onboarding</option>
                <option>Sales &amp; pricing</option>
                <option>Partnership</option>
                <option>Feedback</option>
              </select>
            </label>
          </div>
          <label class="ct-fld">
            <span>Message *</span>
            <textarea x-model="form.message" :disabled="busy" rows="5" maxlength="5000"
              placeholder="How can we help?" required></textarea>
          </label>

          <!-- Honeypot: hidden from humans, tempting to bots -->
          <div class="ct-hp" aria-hidden="true">
            <label>Company <input type="text" x-model="form.company" tabindex="-1" autocomplete="off"></label>
          </div>

          <?php if ($contactCaptchaEnabled && $contactCaptchaSiteKey !== ''): ?>
            <div class="ct-captcha">
              <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($contactCaptchaSiteKey) ?>"></div>
            </div>
          <?php endif; ?>

          <p class="ct-err" x-show="errorMsg" x-text="errorMsg"></p>

          <button type="submit" class="btn btn-primary ct-submit" :disabled="busy">
            <span x-show="!busy">Send message</span>
            <span x-show="busy">Sending…</span>
          </button>
          <p class="ct-note">
            We’ll only use your details to reply to this enquiry. See our
            <a href="/privacy-policy">privacy policy</a>.
          </p>
        </form>

        <!-- Success state -->
        <div class="ct-success" x-show="sent" x-cloak>
          <div class="ct-success-ic">✅</div>
          <h2>Message sent</h2>
          <p x-text="successMsg"></p>
          <a href="/" class="btn btn-outline">Back to home</a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- reCAPTCHA is loaded lazily on first form interaction via the shared
     window.ecpLoadRecaptcha() (defined in partials/auth-modal.php), so it
     doesn't cost page-load time. See contactForm().loadCaptcha(). -->

<style>
  .ct-page {
    background: var(--bg-3, #fafafa);
    padding: 120px 0 90px;
    min-height: calc(100vh - 80px);
  }

  .ct-page .wrap {
    max-width: 1040px;
    margin: 0 auto;
    padding: 0 24px;
  }

  .ct-grid {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: 48px;
    align-items: start;
  }

  /* Left intro */
  .ct-eyebrow {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--teal-700);
    background: var(--teal-50);
    padding: 5px 12px;
    border-radius: 999px;
    margin-bottom: 16px;
  }

  .ct-intro h1 {
    font-size: clamp(28px, 3.6vw, 40px);
    font-weight: 600;
    letter-spacing: -0.8px;
    line-height: 1.12;
    margin: 0 0 14px;
  }

  .ct-lede {
    font-size: 15.5px;
    line-height: 1.6;
    color: var(--ink-2);
    margin: 0 0 28px;
    max-width: 420px;
  }

  .ct-channels {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .ct-channels li {
    display: flex;
    gap: 13px;
    align-items: center;
  }

  .ct-ch-ic {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    flex-shrink: 0;
    background: #fff;
    border: 1px solid var(--line);
    display: grid;
    place-items: center;
    font-size: 19px;
  }

  .ct-ch-ic img {
    filter: brightness(0) saturate(100%) invert(28%) sepia(63%) saturate(1045%) hue-rotate(134deg) brightness(92%) contrast(93%);
  }

  .ct-channels b {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--mute);
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .ct-channels a {
    font-size: 15px;
    font-weight: 600;
    color: var(--ink);
    text-decoration: none;
  }

  .ct-channels a:hover {
    color: var(--teal-700);
  }

  /* Right card */
  .ct-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 18px 48px rgba(0, 0, 0, 0.05);
  }

  .ct-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .ct-row {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
  }

  .ct-fld {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1 1 200px;
    min-width: 0;
  }

  .ct-fld>span {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--mute);
  }

  .ct-fld>span em {
    font-style: normal;
    text-transform: none;
    letter-spacing: normal;
    color: var(--teal-700);
    font-weight: 500;
  }

  .ct-fld input,
  .ct-fld select,
  .ct-fld textarea {
    border: 1px solid var(--line);
    border-radius: 11px;
    padding: 12px 14px;
    font: inherit;
    font-size: 15px;
    outline: none;
    width: 100%;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
  }

  .ct-fld textarea {
    resize: vertical;
  }

  .ct-fld input:focus,
  .ct-fld select:focus,
  .ct-fld textarea:focus {
    border-color: var(--teal-400);
    box-shadow: 0 0 0 3px rgba(15, 155, 110, 0.14);
  }

  .ct-fld input:disabled,
  .ct-fld textarea:disabled,
  .ct-fld select:disabled {
    background: var(--bg-2);
    opacity: 0.7;
  }

  .ct-captcha {
    display: flex;
  }

  .ct-hp {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    overflow: hidden;
  }

  .ct-err {
    font-size: 13.5px;
    color: #c0392b;
    background: rgba(192, 57, 43, 0.06);
    border: 1px solid rgba(192, 57, 43, 0.15);
    border-radius: 8px;
    padding: 10px 12px;
    margin: 0;
  }

  .ct-submit {
    padding: 14px 22px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 12px;
  }

  .ct-note {
    font-size: 12.5px;
    color: var(--mute);
    margin: 0;
    line-height: 1.5;
    text-align: center;
  }

  .ct-note a {
    color: var(--teal-700);
    text-decoration: underline;
  }

  /* Success */
  .ct-success {
    text-align: center;
    padding: 24px 8px;
  }

  .ct-success-ic {
    font-size: 44px;
    margin-bottom: 10px;
  }

  .ct-success h2 {
    font-size: 22px;
    font-weight: 600;
    margin: 0 0 8px;
    letter-spacing: -0.4px;
  }

  .ct-success p {
    font-size: 15px;
    color: var(--ink-2);
    line-height: 1.6;
    margin: 0 auto 20px;
    max-width: 360px;
  }

  .btn-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 20px;
    border-radius: 11px;
    border: 1px solid var(--line);
    background: #fff;
    color: var(--ink-2);
    font: inherit;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
  }

  .btn-outline:hover {
    border-color: var(--ink);
    color: var(--ink);
  }

  @media (max-width: 820px) {
    .ct-page {
      padding: 90px 0 60px;
    }

    .ct-grid {
      grid-template-columns: 1fr;
      gap: 32px;
    }

    .ct-card {
      padding: 24px;
    }
  }
</style>

<script>
  function contactForm(captchaEnabled) {
    return {
      captchaEnabled: !!captchaEnabled,
      busy: false,
      sent: false,
      errorMsg: '',
      successMsg: '',
      form: {
        name: '',
        email: '',
        phone: '',
        subject: 'General enquiry',
        message: '',
        company: ''
      },

      // Load reCAPTCHA on first interaction (not page load) via the shared loader.
      loadCaptcha() {
        if (this.captchaEnabled && window.ecpLoadRecaptcha) window.ecpLoadRecaptcha();
      },

      captchaToken() {
        if (!this.captchaEnabled || typeof grecaptcha === 'undefined') return '';
        try {
          return grecaptcha.getResponse() || '';
        } catch (e) {
          return '';
        }
      },
      resetCaptcha() {
        if (!this.captchaEnabled || typeof grecaptcha === 'undefined') return;
        try {
          grecaptcha.reset();
        } catch (e) {}
      },

      async submit() {
        this.errorMsg = '';
        if (!this.form.name.trim()) {
          this.errorMsg = 'Please enter your name.';
          return;
        }
        if (!this.form.email.trim()) {
          this.errorMsg = 'Please enter your email.';
          return;
        }
        if (this.form.message.trim().length < 10) {
          this.errorMsg = 'Please write a bit more so we can help.';
          return;
        }
        const captcha = this.captchaToken();
        if (this.captchaEnabled && !captcha) {
          this.errorMsg = 'Please complete the captcha.';
          return;
        }

        this.busy = true;
        try {
          const r = await fetch('/api/contact?action=submit', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              ...this.form,
              'g-recaptcha-response': captcha || undefined
            }),
          });
          const text = await r.text();
          let j;
          try {
            j = JSON.parse(text);
          } catch (e) {
            this.errorMsg = 'Server returned an unexpected response (HTTP ' + r.status + ').';
            return;
          }
          if (!j.ok) {
            this.resetCaptcha();
            this.errorMsg = this.errorText(j.error);
            return;
          }
          this.successMsg = j.message || "Thanks — we've received your message and will reply soon.";
          this.sent = true;
        } catch (e) {
          this.errorMsg = "Couldn't reach the server. Please try again, or email hello@eclinicpro.com directly.";
          this.resetCaptcha();
        } finally {
          this.busy = false;
        }
      },

      errorText(code) {
        switch (code) {
          case 'name_required':
            return 'Please enter your name.';
          case 'invalid_email':
            return 'That email address doesn’t look right.';
          case 'message_too_short':
            return 'Please write a bit more so we can help.';
          case 'message_too_long':
            return 'That message is a little too long — please shorten it.';
          case 'captcha_failed':
            return 'Captcha check failed. Please try again.';
          case 'send_failed':
            return "We couldn't send your message right now. Please email hello@eclinicpro.com directly.";
          case 'method_not_allowed':
          case 'unknown_action':
            return 'Something went wrong submitting the form. Please refresh and try again.';
          default:
            return 'Something went wrong. Please try again.';
        }
      },
    };
  }
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>