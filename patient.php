<?php
// =====================================================================
// patient.php — patient panel.
// Reads the logged-in identity server-side (from the ecp_pid cookie).
// Wishlist is fetched from /api/wishlist after page load so we keep
// the initial HTML cacheable and small.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';

// Private page — never cache; cookie/session state must be fresh.
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie');

$pageTitle  = 'My Health — eClinicPro';
$metaDesc   = 'Save your shortlist of doctors and book faster next time.';
$activePage = '';
$noindex    = true;                  // private — don't index logged-in/empty state

$me = ecp_patient_current();   // null when logged out

require __DIR__ . '/partials/header.php';
?>

<div x-data="patientPanel(<?= $me ? '1' : '0' ?>)" x-init="init()" x-cloak class="patient-page">

  <?php if (!$me): ?>
    <!-- LOGGED-OUT VIEW: features showcase + sign-in card -->
    <section class="pt-hero">
      <div class="wrap">
        <div class="pt-hero-split">

          <!-- Left: value proposition + compact feature chips -->
          <div class="pt-hero-copy">
            <span class="pt-eyebrow">Your free patient account</span>
            <h1>Quality Healthcare, Whenever You Need It</h1>
            <p class="pt-hero-lede">
            Book clinic visits or online consultations, access prescriptions and medical records, and manage your healthcare securely through the eClinicPro patient portal.
            </p>
            <ul class="pt-feat-chips">
              <li>✅ Online Consultations</li>
              <li>✅ Clinic Visits</li>
              <li>✅ Appointment Booking</li>
              <li>✅ Health Records</li>
              <li>✅ Prescriptions</li>
              <li>✅ Test Reports</li>
              <li>✅ Follow-up Care</li>
              <li>✅ Appointment Reminders</li>
            </ul>

            <div class="ct-list-card">
              <div class="ct-card">
                <div class="ct-card-icon">
                  <img src="assets/img/icon/eclinicpro-doctor-fil.png" alt="Doctor" width="70" height="50">
                </div>
                <h3>Find Trusted Doctors</h3>
                <p>Connect with verified healthcare experts.</p>
              </div>
              <div class="ct-card">
                <div class="ct-card-icon">
                  <img src="assets/img/icon/eclinicpro-online-booking.png" alt="Doctor" width="70" height="50">
                </div>
                <h3>Book in Seconds</h3>
                <p>Schedule appointments instantly.</p>
              </div>
              <div class="ct-card">
                <div class="ct-card-icon">
                  <img src="assets/img/icon/eclinicpro-medical-aid.png" alt="Doctor" width="70" height="50">
                </div>
                <h3>Health Records</h3>
                <p>Keep records safe and accessible.</p>
              </div>
            </div>
          </div>
          <?php
          $ptCaptcha        = ecp_recaptcha_config();
          $ptCaptchaEnabled = !empty($ptCaptcha['enabled']);
          $ptCaptchaSiteKey = (string) ($ptCaptcha['site_key'] ?? '');
          ?>
          <div class="pt-card pt-card-signin" x-data="patientInlineAuth(<?= $ptCaptchaEnabled ? 'true' : 'false' ?>)">
            <div class="pt-card-head">
              <h2 x-text="step === 'code' ? 'Verify your number' : 'My Health'"></h2>
              <p class="lede" x-text="subline()"></p>
            </div>

            <!-- Sign in / Create account toggle (step 1 only) -->
            <div class="pt-auth-tabs" x-show="step === 'phone'">
              <button type="button" :class="intent === 'signin' ? 'is-active' : ''"
                @click="intent = 'signin'; errorMsg = ''">Sign in</button>
              <button type="button" :class="intent === 'signup' ? 'is-active' : ''"
                @click="intent = 'signup'; errorMsg = ''">Create account</button>
            </div>

            <!-- STEP 1: phone -->
            <form class="pt-auth-form" x-show="step === 'phone'" @submit.prevent="sendOtp()">
              <label>
                <span class="pt-auth-lbl">Mobile number</span>
                <div class="pt-phone-field">
                  <span class="pt-phone-cc">+91</span>
                  <input type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10"
                    x-model="phoneDigits"
                    @input="phoneDigits = phoneDigits.replace(/\D/g, '').slice(0,10)"
                    @focus="loadCaptcha()"
                    :disabled="busy" placeholder="98XXXXXXXX" required>
                </div>
              </label>
              <p class="pt-hint" x-text="intent === 'signup' ? 'We\'ll create your account once you confirm the code.' : 'We\'ll send a 6-digit code via WhatsApp. No password to remember.'"></p>
              <p class="pt-auth-err" x-show="errorMsg" x-html="errorMsg"></p>
              <?php if ($ptCaptchaEnabled && $ptCaptchaSiteKey !== ''): ?>
                <div class="pt-auth-captcha">
                  <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($ptCaptchaSiteKey) ?>"></div>
                </div>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary pt-cta-signin" :disabled="busy || phoneDigits.length < 10">
                <span x-show="!busy" x-text="intent === 'signup' ? 'Create my account' : 'Send code'"></span>
                <span x-show="busy">Sending…</span>
              </button>
              <p class="pt-auth-tos">By continuing you agree to our <a href="/security" target="_blank">privacy &amp; terms</a>.</p>
            </form>

            <!-- STEP 2: code -->
            <form class="pt-auth-form" x-show="step === 'code'" @submit.prevent="verifyOtp()">
              <div class="pt-auth-back" @click="step = 'phone'; errorMsg = ''">‹ Change number</div>

              <template x-if="phoneExists && nameHint">
                <div class="pt-auth-welcome">Welcome back, <strong x-text="nameHint"></strong> 👋
                  <span>Enter the code we sent to <strong x-text="'+91 ' + phoneDigits"></strong>.</span>
                </div>
              </template>
              <template x-if="!phoneExists">
                <div class="pt-auth-welcome new">Setting up your account
                  <span>We sent a code to <strong x-text="'+91 ' + phoneDigits"></strong>.</span>
                </div>
              </template>
              <template x-if="devCode">
                <div class="pt-auth-dev">DEV code: <strong x-text="devCode"></strong></div>
              </template>

              <label>
                <span class="pt-auth-lbl">6-digit code</span>
                <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                  x-model="code" x-ref="codeInput"
                  @input="code = code.replace(/\D/g, '').slice(0,6)"
                  :disabled="busy" placeholder="••••••" required>
              </label>
              <template x-if="!phoneExists">
                <label>
                  <span class="pt-auth-lbl">Your full name</span>
                  <input type="text" x-model="name" :disabled="busy" placeholder="e.g. Riya Mehta" maxlength="120" required>
                </label>
              </template>
              <p class="pt-auth-err" x-show="errorMsg" x-html="errorMsg"></p>
              <?php if ($ptCaptchaEnabled && $ptCaptchaSiteKey !== ''): ?>
                <div class="pt-auth-captcha">
                  <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($ptCaptchaSiteKey) ?>"></div>
                </div>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary pt-cta-signin"
                :disabled="busy || code.length !== 6 || (!phoneExists && !name.trim())">
                <span x-show="!busy" x-text="phoneExists ? 'Sign in' : 'Create account'"></span>
                <span x-show="busy">Verifying…</span>
              </button>
              <button type="button" class="pt-auth-resend" :disabled="busy || resendCountdown > 0" @click="resendOtp()">
                <span x-show="resendCountdown === 0">Resend code</span>
                <span x-show="resendCountdown > 0">Resend in <span x-text="resendCountdown"></span>s</span>
              </button>
            </form>

            <div class="pt-trust" x-show="step === 'phone'">
              <span>🔒 Private & secure</span>
              <span>🇮🇳 ABHA-ready</span>
            </div>
          </div>
        </div>



        <!-- Right: INLINE sign-in / sign-up form (same OTP flow as the modal) -->


      </div>
</div>
</section>

<!-- ============ FEATURE SHOWCASE (alternating image + text) ============ -->
<?php
    // Each slide is a self-contained graphic; the copy here is a short lead-in only.
    // Each point mirrors a bullet baked into the slide, surfaced as real text
    // for SEO and for readability on small phone screens. 'd' = optional detail.
    $ptShowcase = [
      ['img' => 1,  'kicker' => 'All in one app',      'title' => 'Your personal health companion',      'text' => 'Whether you\'re booking a doctor\'s appointment, tracking prescriptions, or storing medical reports, eClinicPro keeps your healthcare organized in one place.', 'points' => [
        ['t' => 'Find trusted doctors'],
        ['t' => 'Easy appointment booking'],
        ['t' => 'Secure health records'],
        ['t' => 'Family health management'],
      ]],
      ['img' => 2,  'kicker' => 'Find doctors',         'title' => 'Find the Right Doctor, Faster',              'text' => 'Browse verified doctors across multiple specialties. Compare experience, availability, patient reviews, and consultation fees before booking.', 'points' => [
        ['t' => 'Verified doctors'],
        ['t' => 'Ratings & Reviews'],
        ['t' => 'Multiple Specialties'],
        ['t' => 'Online & Clinic Visits'],
      ]],
      ['img' => 3,  'kicker' => 'Book instantly',       'title' => 'Book Appointments in Seconds',               'text' => 'Choose your preferred doctor, pick an available time slot, and confirm your appointment within seconds.', 'points' => [
        ['t' => 'Live availability'],
        ['t' => 'Instant confirmation'],
        ['t' => 'Reschedule anytime'],
        ['t' => 'Calendar sync'],
      ]],
      ['img' => 4,  'kicker' => 'For everyone',         'title' => 'Care for your whole family',            'text' => 'Manage healthcare for parents, spouse, children, and loved ones using a single account.', 'points' => [
        ['t' => 'Multiple profiles'],
        ['t' => 'Shared prescriptions'],
        ['t' => 'Appointment history'],
        ['t' => 'Family reminders'],
      ]],
      ['img' => 5,  'kicker' => 'Prescriptions',        'title' => 'Your Prescriptions Always With You',   'text' => 'Access all prescriptions digitally. Never lose important medications or doctor\'s advice again.', 'points' => [
        // ['t' => 'Upload & save prescriptions', 'd' => 'Keep every prescription instantly'],
        ['t' => 'Prescription history'],
        ['t' => 'Medicine reminders'],
        ['t' => 'Download PDF'],
        ['t' => 'Share with pharmacy'],
      ]],
      ['img' => 6,  'kicker' => 'Health history',       'title' => 'Your health organized',     'text' => 'Securely store lab reports, scans, prescriptions, vaccination records, and consultation history.', 'points' => [
        ['t' => 'Reports'],
        ['t' => 'Lab Results'],
        ['t' => 'X-rays'],
        ['t' => 'Health Timeline'],
      ]],
      ['img' => 7,  'kicker' => 'Favourites',           'title' => 'Stay Connected with Doctors', 'text' => 'Message your doctor, receive follow-up advice, and stay connected between appointments.', 'points' => [
        ['t' => 'Secure chat'],
        ['t' => 'Follow-up care'],
        ['t' => 'Video consultation'],
        ['t' => 'Medical advice'],
      ]],
      ['img' => 8,  'kicker' => 'Reminders',            'title' => 'Never Miss an Appointment',             'text' => 'Receive timely reminders for doctor visits, medications, vaccinations, and health checkups.', 'points' => [
        ['t' => 'Push notifications'],
        ['t' => 'SMS reminders'],
        ['t' => 'Calendar sync'],
        ['t' => 'Medication alerts'],
      ]],
      ['img' => 9,  'kicker' => 'Privacy first',        'title' => 'Your Health, Protected & Secure',       'text' => 'Your personal health information is protected using industry-standard encryption and secure cloud infrastructure.', 'points' => [
        // ['t' => 'Secure data', 'd' => 'End-to-end encryption'],
        ['t' => 'End-to-end encryption'],
        ['t' => 'Secure cloud backup'],
        ['t' => 'Privacy controls'],
        ['t' => 'HIPAA-ready architecture'],
      ]],
      ['img' => 10, 'kicker' => 'Better healthcare',    'title' => 'For You As Your Loved Ones',             'text' => 'From finding doctors to managing prescriptions and protecting your medical history, ClinicPro helps you stay healthier every day.', 'cta' => true, 'points' => [
        ['t' => 'Find doctors'],
        ['t' => 'Book Appointments'],
        ['t' => 'Manage Family'],
        ['t' => 'Store Reports'],
        ['t' => 'Digital Prescriptions'],
      ]],
    ];
?>
<section class="pt-showcase">
  <div class="wrap">
    <div class="pt-showcase-head">
      <span class="pt-eyebrow">A quick tour</span>
      <h2>See what your free account can do</h2>
    </div>

    <?php foreach ($ptShowcase as $i => $s): ?>
      <div class="pt-show-row<?= $i % 2 ? ' is-reverse' : '' ?>">
        <div class="pt-show-media">
          <img src="/assets/img/patient_img/eClinicpro-patient<?= (int) $s['img'] ?>.jpeg"
            alt="<?= e($s['title']) ?> — eClinicPro patient app"
            loading="lazy" width="1080" height="1350">
        </div>
        <div class="pt-show-copy">
          <span class="pt-show-kicker"><?= e($s['kicker']) ?></span>
          <h3><?= e($s['title']) ?></h3>
          <p><?= e($s['text']) ?></p>
          <?php if (!empty($s['points'])): ?>
            <ul class="pt-show-points">
              <?php foreach ($s['points'] as $pt): ?>
                <li>
                  <svg class="pt-show-tick" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  <span><strong><?= e($pt['t']) ?></strong><?php if (!empty($pt['d'])): ?> — <?= e($pt['d']) ?><?php endif; ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if (!empty($s['cta'])): ?>
            <button type="button" class="btn btn-primary pt-show-cta"
              @click="window.ecpAuth ? window.ecpAuth.open('default') : (window.location.hash = '')">
              Create your free account
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php else: ?>
  <!-- LOGGED-IN VIEW -->
  <section class="pt-main">
    <div class="wrap">

      <!-- Hero / profile strip -->
      <div class="pt-hero-strip">
        <div class="pt-hero-id">
          <div class="pt-bigavatar">
            <template x-if="heroHasPhoto">
              <img :src="'/api/patient_profile?action=photo&t=' + heroPhotoVer" alt="Profile photo" class="pt-bigavatar-img">
            </template>
            <template x-if="!heroHasPhoto">
              <span><?= e(ecp_patient_initials($me)) ?></span>
            </template>
          </div>
          <div>
            <div class="pt-greet">Welcome back</div>
            <h1><?= e($me['name'] ?: 'there') ?></h1>
            <div class="pt-handle"><?= e($me['phone']) ?></div>
          </div>
        </div>
        <div class="pt-hero-actions">
          <a href="/find-a-doctor" class="btn btn-ghost">Find a doctor</a>
          <button type="button" class="btn btn-outline" @click="signOut()">Sign out</button>
        </div>
      </div>

      <!-- 2-column layout: tabbed main + coming-soon sidebar -->
      <div class="pt-grid">

        <div class="pt-section pt-section-tabbed">

          <!-- ============ BOOKINGS TAB ============ -->
          <div x-show="tab === 'bookings'" class="pt-tab-pane">

            <!-- Loading -->
            <div x-show="bookings.loading" class="pt-loading">Loading your bookings…</div>

            <!-- Upcoming -->
            <template x-if="!bookings.loading && bookings.upcoming.length > 0">
              <div>
                <div class="pt-section-head">
                  <h3>Upcoming</h3>
                </div>
                <div class="pt-list">
                  <template x-for="b in bookings.upcoming" :key="'apt-' + b.id">
                    <div class="pt-booking pt-booking-confirmed">
                      <div class="pt-booking-date">
                        <span class="pt-date-day" x-text="formatDay(b.when_iso)"></span>
                        <span class="pt-date-mon" x-text="formatMon(b.when_iso)"></span>
                        <span class="pt-date-time" x-text="b.when_time"></span>
                      </div>
                      <div class="pt-booking-body">
                        <div class="pt-booking-doctor" x-text="b.doctor_name || 'Doctor'"></div>
                        <div class="pt-booking-clinic" x-text="b.clinic_name"></div>
                        <template x-if="b.token_number">
                          <div class="pt-booking-token">Token <strong x-text="b.token_number"></strong></div>
                        </template>
                        <template x-if="b.reason">
                          <div class="pt-booking-reason" x-text="'For: ' + b.reason"></div>
                        </template>
                      </div>
                      <div class="pt-booking-actions">
                        <template x-if="b.clinic_phone">
                          <a :href="'tel:' + b.clinic_phone" class="btn-mini primary">📞 Call</a>
                        </template>
                        <span class="pt-status pt-status-confirmed" x-text="prettyStatus(b.status)"></span>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </template>

            <!-- Pending (lead requests to unclaimed clinics) -->
            <template x-if="!bookings.loading && bookings.pending.length > 0">
              <div style="margin-top: 18px;">
                <div class="pt-section-head">
                  <h3>Pending requests</h3>
                </div>
                <p class="pt-section-note">
                  We've notified these clinics. They'll call you to confirm.
                </p>
                <div class="pt-list">
                  <template x-for="b in bookings.pending" :key="'lead-' + b.id">
                    <div class="pt-booking pt-booking-pending">
                      <div class="pt-booking-date">
                        <span class="pt-date-day" x-text="formatDayFromDate(b.when_iso)"></span>
                        <span class="pt-date-mon" x-text="formatMonFromDate(b.when_iso)"></span>
                        <span class="pt-date-time" x-text="b.when_time || ''"></span>
                      </div>
                      <div class="pt-booking-body">
                        <div class="pt-booking-doctor" x-text="b.doctor_name || b.clinic_name"></div>
                        <div class="pt-booking-clinic">
                          <span x-text="b.clinic_name"></span>
                          <template x-if="b.clinic_address">
                            <span x-text="' · ' + b.clinic_address"></span>
                          </template>
                        </div>
                        <template x-if="b.reason">
                          <div class="pt-booking-reason" x-text="'For: ' + b.reason"></div>
                        </template>
                      </div>
                      <div class="pt-booking-actions">
                        <template x-if="b.clinic_phone">
                          <a :href="'tel:' + b.clinic_phone" class="btn-mini">📞 Call</a>
                        </template>
                        <span class="pt-status pt-status-pending" x-text="prettyLeadStatus(b.status)"></span>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </template>

            <!-- Past -->
            <template x-if="!bookings.loading && bookings.past.length > 0">
              <div style="margin-top: 18px;">
                <div class="pt-section-head">
                  <h3>Past</h3>
                  <button type="button" class="pt-link-btn"
                    @click="bookings.pastOpen = !bookings.pastOpen"
                    x-text="bookings.pastOpen ? 'Hide' : 'Show ' + bookings.past.length"></button>
                </div>
                <div class="pt-list" x-show="bookings.pastOpen" x-cloak>
                  <template x-for="b in bookings.past" :key="'past-' + b.id">
                    <div class="pt-booking pt-booking-past">
                      <div class="pt-booking-date">
                        <span class="pt-date-day" x-text="formatDay(b.when_iso)"></span>
                        <span class="pt-date-mon" x-text="formatMon(b.when_iso)"></span>
                        <span class="pt-date-time" x-text="b.when_time"></span>
                      </div>
                      <div class="pt-booking-body">
                        <div class="pt-booking-doctor" x-text="b.doctor_name || 'Doctor'"></div>
                        <div class="pt-booking-clinic" x-text="b.clinic_name"></div>
                      </div>
                      <span class="pt-status pt-status-past" x-text="prettyStatus(b.status)"></span>
                    </div>
                  </template>
                </div>
              </div>
            </template>

            <!-- Empty state (no bookings at all) -->
            <template x-if="!bookings.loading && bookings.upcoming.length === 0 && bookings.pending.length === 0 && bookings.past.length === 0">
              <div class="pt-empty">
                <div class="glyph">📅</div>
                <h3>No bookings yet</h3>
                <p>Find a doctor and tap Book to schedule your first appointment.</p>
                <a href="/find-a-doctor" class="btn btn-primary">Browse doctors</a>
              </div>
            </template>
          </div>

          <!-- ============ SHORTLIST TAB ============ -->
          <div x-show="tab === 'shortlist'" class="pt-tab-pane">
            <div class="pt-section-head">
              <h3>Your shortlist</h3>
              <span class="pt-counter"><span x-text="wishlist.length"></span> / 5</span>
            </div>

            <template x-if="wishlist.length === 0">
              <div class="pt-empty">
                <div class="glyph">🤍</div>
                <h3>No doctors saved yet</h3>
                <p>Tap the heart on any doctor in Find a doctor to save them here for quick access.</p>
                <a href="/find-a-doctor" class="btn btn-primary">Browse doctors</a>
              </div>
            </template>

            <div class="pt-list" x-show="wishlist.length > 0">
              <template x-for="d in wishlist" :key="d.id">
                <div class="pt-row">
                  <div class="pt-row-id">
                    <div class="pt-avatar" x-text="(d.firstInitial || '') + (d.lastInitial || '')"></div>
                    <div class="pt-row-text">
                      <div class="pt-name" x-text="d.name"></div>
                      <div class="pt-sub">
                        <span x-text="d.specLabel"></span>
                        <template x-if="d.area || d.city">
                          <span x-text="' · ' + [d.area, d.city].filter(Boolean).join(', ')"></span>
                        </template>
                      </div>
                    </div>
                  </div>
                  <div class="pt-row-actions">
                    <template x-if="d.phone">
                      <a :href="'tel:' + d.phone" class="btn-mini primary">📞 Call</a>
                    </template>
                    <button type="button" class="btn-mini" @click="removeFromWishlist(d.id)" aria-label="Remove">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6" />
                      </svg>
                    </button>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- ============ FAMILY TAB ============ -->
          <div x-show="tab === 'family'" class="pt-tab-pane">
            <div class="pt-section-head">
              <h3>Family profiles</h3>
              <button type="button" class="btn-mini primary" x-show="family.canAdd()" @click="family.startAdd()">+ Add member</button>
            </div>
            <p class="pt-section-note">
              Add up to 6 family members (including yourself) with relation, name, date of birth, gender, blood group, and ABHA number.
              <span x-show="family.members.length > 0" x-text="' · ' + family.members.length + '/' + family.maxMembers + ' added'"></span>
            </p>
            <p class="pt-fam-limit" x-show="!family.loading && !family.canAdd()">You’ve reached the maximum of 6 family members. Remove someone to add another.</p>

            <div x-show="family.loading" class="pt-loading">Loading your family…</div>

            <!-- Add / edit member form (shared) -->
            <template x-if="family.editing">
              <div class="pt-fam-edit">
                <div class="pt-fam-edit-grid">
                  <label class="pt-fld"><span>Relation</span>
                    <select x-model="family.form.relation" :disabled="family.form.relation === 'self'">
                      <template x-for="r in family.relations" :key="r.v">
                        <option :value="r.v" x-text="r.t"></option>
                      </template>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Full name *</span>
                    <input type="text" x-model="family.form.name" placeholder="Member's name">
                  </label>
                  <label class="pt-fld"><span>Date of birth</span>
                    <input type="date" x-model="family.form.dob">
                  </label>
                  <label class="pt-fld"><span>Gender</span>
                    <select x-model="family.form.gender">
                      <option value="">—</option>
                      <option value="M">Male</option>
                      <option value="F">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Blood group</span>
                    <select x-model="family.form.blood_group">
                      <option value="">—</option>
                      <template x-for="b in family.bloods" :key="b">
                        <option :value="b" x-text="b"></option>
                      </template>
                    </select>
                  </label>
                  <label class="pt-fld"><span>ABHA number <em>(optional)</em></span>
                    <input type="text" x-model="family.form.abha_id" placeholder="14-digit ABHA number" inputmode="numeric">
                  </label>
                </div>

                <p class="pt-fam-err" x-show="family.formError" x-text="family.formError"></p>
                <div class="pt-fam-edit-actions">
                  <button type="button" class="btn-mini" @click="family.cancelEdit()">Cancel</button>
                  <button type="button" class="btn-mini primary" :disabled="family.saving"
                    @click="family.saveMember()" x-text="family.saving ? 'Saving…' : 'Save member'"></button>
                </div>
              </div>
            </template>

            <!-- Accordion of members -->
            <div class="pt-fam-list" x-show="!family.loading && family.members.length > 0">
              <template x-for="m in family.members" :key="m.id">
                <div class="pt-fam-card">
                  <div class="pt-fam-head">
                    <span class="pt-fam-avatar" x-text="family.initials(m.name)"></span>
                    <span class="pt-fam-id">
                      <span class="pt-fam-name" x-text="m.name"></span>
                      <span class="pt-fam-rel">
                        <span x-text="family.relLabel(m.relation)"></span>
                        <template x-if="m.dob"><span x-text="' · ' + family.age(m.dob) + ' yrs'"></span></template>
                        <template x-if="m.gender"><span x-text="' · ' + family.genderLabel(m.gender)"></span></template>
                        <template x-if="m.blood_group"><span class="pt-fam-blood" x-text="m.blood_group"></span></template>
                        <template x-if="m.abha_id"><span x-text="' · ABHA ' + m.abha_id"></span></template>
                      </span>
                    </span>
                    <div class="pt-fam-actions">
                      <button type="button" class="btn-mini" @click="family.startEdit(m)">✎ Edit</button>
                      <template x-if="!m.is_self">
                        <button type="button" class="btn-mini" @click="family.removeMember(m)">Remove</button>
                      </template>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <template x-if="!family.loading && family.members.length === 0 && !family.editing">
              <div class="pt-empty">
                <div class="glyph">👨‍👩‍👧</div>
                <h3>No family members yet</h3>
                <p>Add yourself, your spouse, parents or children to keep everyone's health details in one place.</p>
                <button type="button" class="btn btn-primary" x-show="family.canAdd()" @click="family.startAdd()">+ Add a member</button>
              </div>
            </template>
          </div>

          <!-- ============ E-PRESCRIPTIONS TAB ============ -->
          <div x-show="tab === 'rx'" class="pt-tab-pane">
            <div class="pt-section-head">
              <h3>E-prescriptions</h3>
              <button type="button" class="btn-mini primary" @click="rx.startAdd()" x-show="!rx.adding">+ Add prescription</button>
            </div>
            <p class="pt-section-note">
              Keep every prescription in one place — upload a photo or PDF of one you already have,
              and doctors on eClinicPro can share new ones straight to your panel.
            </p>

            <div x-show="rx.loading" class="pt-loading">Loading your prescriptions…</div>

            <!-- Add form (patient self-upload) -->
            <template x-if="rx.adding">
              <div class="pt-fam-edit">
                <div class="pt-fam-edit-grid">
                  <label class="pt-fld"><span>For</span>
                    <select x-model="rx.form.family_member_id">
                      <option value="">Myself</option>
                      <template x-for="m in family.members" :key="'rxm-' + m.id">
                        <template x-if="!m.is_self">
                          <option :value="m.id" x-text="m.name"></option>
                        </template>
                      </template>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Title *</span>
                    <input type="text" x-model="rx.form.label" placeholder="e.g. Dr. Jayesh — May 2026 — BP">
                  </label>
                  <label class="pt-fld"><span>Doctor <em>(optional)</em></span>
                    <input type="text" x-model="rx.form.doctor_name" placeholder="Doctor's name">
                  </label>
                  <label class="pt-fld"><span>Date <em>(optional)</em></span>
                    <input type="date" x-model="rx.form.issued_on">
                  </label>
                  <label class="pt-fld"><span>Reason / notes <em>(optional)</em></span>
                    <input type="text" x-model="rx.form.notes" placeholder="e.g. Blood pressure">
                  </label>
                  <label class="pt-fld"><span>Prescription file <em>(photo or PDF)</em></span>
                    <input type="file" accept="image/*,application/pdf" @change="rx.pickFile($event)">
                  </label>
                </div>

                <p class="pt-fam-err" x-show="rx.formError" x-text="rx.formError"></p>
                <div class="pt-fam-edit-actions">
                  <button type="button" class="btn-mini" @click="rx.cancelAdd()">Cancel</button>
                  <button type="button" class="btn-mini primary" :disabled="rx.saving"
                    @click="rx.save()" x-text="rx.saving ? 'Saving…' : 'Save prescription'"></button>
                </div>
              </div>
            </template>

            <!-- List (both sources) -->
            <div class="pt-fam-list" x-show="!rx.loading && rx.items.length > 0">
              <template x-for="p in rx.items" :key="'rx-' + p.id">
                <div class="pt-fam-card">
                  <div class="pt-fam-head">
                    <span class="pt-fam-avatar">💊</span>
                    <span class="pt-fam-id">
                      <span class="pt-fam-name" x-text="p.label"></span>
                      <span class="pt-fam-rel">
                        <template x-if="p.is_clinic"><span class="pt-fam-blood">From clinic</span></template>
                        <template x-if="p.doctor_name"><span x-text="' · ' + p.doctor_name"></span></template>
                        <template x-if="p.clinic_name"><span x-text="' · ' + p.clinic_name"></span></template>
                        <template x-if="p.issued_on"><span x-text="' · ' + rx.fmtDate(p.issued_on)"></span></template>
                        <template x-if="p.notes"><span x-text="' · ' + p.notes"></span></template>
                      </span>
                    </span>
                    <div class="pt-fam-actions">
                      <template x-if="p.has_file">
                        <a class="btn-mini" :href="'/api/patient_prescriptions?action=file&id=' + p.id" target="_blank" rel="noopener">View</a>
                      </template>
                      <button type="button" class="btn-mini" @click="rx.remove(p)">Remove</button>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <template x-if="!rx.loading && rx.items.length === 0 && !rx.adding">
              <div class="pt-empty">
                <div class="glyph">💊</div>
                <h3>No prescriptions yet</h3>
                <p>Upload a photo of a prescription you already have, or ask your eClinicPro doctor to share one during your next visit.</p>
                <button type="button" class="btn btn-primary" @click="rx.startAdd()">+ Add a prescription</button>
              </div>
            </template>
          </div>

          <!-- ============ LAB REPORTS TAB ============ -->
          <div x-show="tab === 'labs'" class="pt-tab-pane">
            <div class="pt-section-head">
              <h3>Lab reports</h3>
              <button type="button" class="btn-mini primary" @click="labs.startAdd()" x-show="!labs.adding">+ Add report</button>
            </div>
            <p class="pt-section-note">
              Keep every test result in one place — blood work, scans, X-rays. Upload a photo or PDF
              of a report you already have and it stays with you, for any doctor you visit.
            </p>

            <div x-show="labs.loading" class="pt-loading">Loading your lab reports…</div>

            <!-- Add form (patient self-upload) -->
            <template x-if="labs.adding">
              <div class="pt-fam-edit">
                <div class="pt-fam-edit-grid">
                  <label class="pt-fld"><span>For</span>
                    <select x-model="labs.form.family_member_id">
                      <option value="">Myself</option>
                      <template x-for="m in family.members" :key="'labm-' + m.id">
                        <template x-if="!m.is_self">
                          <option :value="m.id" x-text="m.name"></option>
                        </template>
                      </template>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Title *</span>
                    <input type="text" x-model="labs.form.label" placeholder="e.g. Complete Blood Count — Jul 2026">
                  </label>
                  <label class="pt-fld"><span>Test type <em>(optional)</em></span>
                    <input type="text" x-model="labs.form.test_type" list="ecp-lab-types" placeholder="e.g. Blood test">
                    <datalist id="ecp-lab-types">
                      <option value="Blood test"></option>
                      <option value="Urine test"></option>
                      <option value="X-Ray"></option>
                      <option value="Ultrasound"></option>
                      <option value="CT scan"></option>
                      <option value="MRI"></option>
                      <option value="ECG"></option>
                      <option value="Biopsy / pathology"></option>
                      <option value="Other"></option>
                    </datalist>
                  </label>
                  <label class="pt-fld"><span>Lab / diagnostic centre <em>(optional)</em></span>
                    <input type="text" x-model="labs.form.lab_name" placeholder="e.g. Thyrocare">
                  </label>
                  <label class="pt-fld"><span>Referred by <em>(optional)</em></span>
                    <input type="text" x-model="labs.form.doctor_name" placeholder="Doctor's name">
                  </label>
                  <label class="pt-fld"><span>Report date <em>(optional)</em></span>
                    <input type="date" x-model="labs.form.reported_on">
                  </label>
                  <label class="pt-fld"><span>Notes <em>(optional)</em></span>
                    <input type="text" x-model="labs.form.notes" placeholder="e.g. Fasting sample">
                  </label>
                  <label class="pt-fld"><span>Report file <em>(photo or PDF)</em></span>
                    <input type="file" accept="image/*,application/pdf" @change="labs.pickFile($event)">
                  </label>
                </div>

                <p class="pt-fam-err" x-show="labs.formError" x-text="labs.formError"></p>
                <div class="pt-fam-edit-actions">
                  <button type="button" class="btn-mini" @click="labs.cancelAdd()">Cancel</button>
                  <button type="button" class="btn-mini primary" :disabled="labs.saving"
                    @click="labs.save()" x-text="labs.saving ? 'Saving…' : 'Save report'"></button>
                </div>
              </div>
            </template>

            <!-- List -->
            <div class="pt-fam-list" x-show="!labs.loading && labs.items.length > 0">
              <template x-for="r in labs.items" :key="'lab-' + r.id">
                <div class="pt-fam-card">
                  <div class="pt-fam-head">
                    <span class="pt-fam-avatar">🧪</span>
                    <span class="pt-fam-id">
                      <span class="pt-fam-name" x-text="r.label"></span>
                      <span class="pt-fam-rel">
                        <template x-if="r.test_type"><span class="pt-fam-blood" x-text="r.test_type"></span></template>
                        <template x-if="r.lab_name"><span x-text="' · ' + r.lab_name"></span></template>
                        <template x-if="r.doctor_name"><span x-text="' · ' + r.doctor_name"></span></template>
                        <template x-if="r.reported_on"><span x-text="' · ' + labs.fmtDate(r.reported_on)"></span></template>
                        <template x-if="r.notes"><span x-text="' · ' + r.notes"></span></template>
                      </span>
                    </span>
                    <div class="pt-fam-actions">
                      <template x-if="r.has_file">
                        <a class="btn-mini" :href="'/api/patient_lab_reports?action=file&id=' + r.id" target="_blank" rel="noopener">View</a>
                      </template>
                      <button type="button" class="btn-mini" @click="labs.remove(r)">Remove</button>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <template x-if="!labs.loading && labs.items.length === 0 && !labs.adding">
              <div class="pt-empty">
                <div class="glyph">🧪</div>
                <h3>No lab reports yet</h3>
                <p>Add your past test results — blood work, scans, X-rays — so you always have them with you at your next appointment.</p>
                <button type="button" class="btn btn-primary" @click="labs.startAdd()">+ Add a report</button>
              </div>
            </template>
          </div>

          <!-- ============ MY PROFILE TAB ============ -->
          <div x-show="tab === 'profile'" class="pt-tab-pane">
            <div class="pt-section-head">
              <h3>My profile</h3>
              <span class="pt-save-hint" x-show="profile.savedAt" x-text="'Saved ' + profile.savedAt"></span>
            </div>
            <p class="pt-section-note">
              Everything here is optional — add whatever you like and we’ll keep it safe.
              These are your own details; family members are managed in the Family tab.
            </p>

            <div x-show="profile.loading" class="pt-loading">Loading your profile…</div>

            <template x-if="!profile.loading && profile.form">
              <div class="pt-profile">

                <!-- Photo -->
                <div class="pt-profile-photo">
                  <template x-if="profile.form.has_photo && !profile.photoBusted">
                    <img :src="'/api/patient_profile?action=photo&t=' + profile.photoVer" alt="Profile photo" class="pt-avatar-img">
                  </template>
                  <template x-if="!profile.form.has_photo || profile.photoBusted">
                    <span class="pt-bigavatar" x-text="profile.initials()"></span>
                  </template>
                  <div class="pt-photo-actions">
                    <label class="btn-mini primary">
                      <span x-text="profile.uploadingPhoto ? 'Uploading…' : (profile.form.has_photo ? 'Change photo' : 'Add photo')"></span>
                      <input type="file" accept="image/jpeg,image/png,image/webp" class="pt-hidden-file"
                        @change="profile.uploadPhoto($event)" :disabled="profile.uploadingPhoto">
                    </label>
                    <p class="pt-photo-hint">JPG, PNG or WebP · up to 4&nbsp;MB</p>
                  </div>
                </div>

                <!-- Personal -->
                <h4 class="pt-profile-group">Personal</h4>
                <div class="pt-fam-edit-grid">
                  <label class="pt-fld"><span>Full name *</span>
                    <input type="text" x-model="profile.form.name" placeholder="Your name">
                  </label>
                  <label class="pt-fld"><span>Preferred name</span>
                    <input type="text" x-model="profile.form.preferred_name" placeholder="What we should call you">
                  </label>
                  <label class="pt-fld"><span>Date of birth</span>
                    <input type="date" x-model="profile.form.dob">
                  </label>
                  <label class="pt-fld"><span>Gender</span>
                    <select x-model="profile.form.gender">
                      <option value="">—</option>
                      <option value="M">Male</option>
                      <option value="F">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Blood group</span>
                    <select x-model="profile.form.blood_group">
                      <option value="">—</option>
                      <template x-for="b in profile.bloods" :key="b">
                        <option :value="b" x-text="b"></option>
                      </template>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Food preference</span>
                    <select x-model="profile.form.veg_type">
                      <option value="">—</option>
                      <option value="veg">Vegetarian</option>
                      <option value="nonveg">Non-vegetarian</option>
                      <option value="eggetarian">Eggetarian</option>
                      <option value="vegan">Vegan</option>
                    </select>
                  </label>
                </div>

                <!-- Contact -->
                <h4 class="pt-profile-group">Contact</h4>
                <div class="pt-fam-edit-grid">
                  <label class="pt-fld"><span>Primary phone <em class="pt-fld-note" x-show="profile.form.phone_verified">✓ Verified</em></span>
                    <input type="text" :value="profile.form.phone || ''" readonly
                      @click="profile.openPhoneModal()"
                      class="pt-clickable-input"
                      placeholder="+91XXXXXXXXXX">

                  </label>
                  <label class="pt-fld"><span>Alternate phone</span>
                    <input type="tel" x-model="profile.form.phone_alt" placeholder="Another number" inputmode="tel">
                  </label>
                  <label class="pt-fld"><span>Email</span>
                    <input type="email" x-model="profile.form.email" placeholder="you@example.com">
                    <em class="pt-fld-note" x-show="profile.form.email && profile.form.email_verified">✓ Verified</em>
                  </label>
                </div>

                <!-- Address -->
                <h4 class="pt-profile-group">Address</h4>
                <div class="pt-fam-edit-grid">
                  <label class="pt-fld pt-fld-wide"><span>Address line 1</span>
                    <input type="text" x-model="profile.form.address_line1" placeholder="House / flat, street">
                  </label>
                  <label class="pt-fld pt-fld-wide"><span>Address line 2</span>
                    <input type="text" x-model="profile.form.address_line2" placeholder="Area, landmark">
                  </label>
                  <label class="pt-fld"><span>City</span>
                    <input type="text" x-model="profile.form.address_city">
                  </label>
                  <label class="pt-fld"><span>State</span>
                    <input type="text" x-model="profile.form.address_state">
                  </label>
                  <label class="pt-fld"><span>PIN / postal code</span>
                    <input type="text" x-model="profile.form.address_postal_code" inputmode="numeric">
                  </label>
                  <label class="pt-fld"><span>Country</span>
                    <input type="text" x-model="profile.form.address_country" maxlength="2" placeholder="IN" style="text-transform:uppercase">
                  </label>
                </div>

                <!-- Emergency contact -->
                <h4 class="pt-profile-group">Emergency contact</h4>
                <div class="pt-fam-edit-grid">
                  <label class="pt-fld"><span>Name</span>
                    <input type="text" x-model="profile.form.emergency_contact_name" placeholder="Who to call in an emergency">
                  </label>
                  <label class="pt-fld"><span>Phone</span>
                    <input type="tel" x-model="profile.form.emergency_contact_phone" inputmode="tel">
                  </label>
                  <label class="pt-fld"><span>Relation</span>
                    <input type="text" x-model="profile.form.emergency_contact_relation" placeholder="e.g. Spouse, Parent">
                  </label>
                </div>

                <!-- Medical -->
                <h4 class="pt-profile-group">Medical</h4>
                <div class="pt-fam-edit-grid">
                  <label class="pt-fld pt-fld-wide"><span>Allergies</span>
                    <textarea x-model="profile.form.allergies" rows="2" placeholder="e.g. Penicillin, peanuts"></textarea>
                  </label>
                  <label class="pt-fld pt-fld-wide"><span>Chronic conditions</span>
                    <textarea x-model="profile.form.chronic_conditions" rows="2" placeholder="e.g. Diabetes, hypertension"></textarea>
                  </label>
                  <label class="pt-fld"><span>ABHA number</span>
                    <input type="text" x-model="profile.form.abha_id" placeholder="14-digit ABHA" inputmode="numeric">
                  </label>
                  <label class="pt-fld"><span>Health Policy Number</span>
                    <input type="text" x-model="profile.form.health_policy_number" maxlength="40" placeholder="Insurance / health policy no." style="text-transform:uppercase">
                  </label>
                </div>

                <p class="pt-fam-err" x-show="profile.formError" x-text="profile.formError"></p>
                <p class="pt-save-ok" x-show="profile.saveSuccess" x-transition>✓ Your profile was saved successfully.</p>
                <div class="pt-fam-edit-actions">
                  <button type="button" class="btn btn-primary" :disabled="profile.saving"
                    @click="profile.save()" x-text="profile.saving ? 'Saving…' : 'Save profile'"></button>
                </div>

                <!-- Primary phone change modal -->
                <div class="pt-modal-backdrop" x-show="profile.phoneChange.open" x-transition.opacity @click.self="profile.closePhoneModal()">
                  <div class="pt-modal-card" x-transition>
                    <button type="button" class="pt-modal-close" @click="profile.closePhoneModal()">×</button>
                    <h4 class="pt-modal-title">Verify new phone number</h4>
                    <p class="pt-modal-sub">Send a WhatsApp OTP to the new number and verify it. Your primary phone updates only after successful verification.</p>

                    <label class="pt-fld" x-show="!profile.phoneChange.awaitingOtp">
                      <span>New primary phone</span>
                      <div class="pt-phone-row">
                        <span class="pt-cc">+91</span>
                        <input type="text" x-model="profile.phoneChange.phoneDigits" inputmode="numeric" maxlength="10"
                          @input="profile.phoneChange.phoneDigits = profile.phoneChange.phoneDigits.replace(/\D/g, '').slice(0,10)"
                          @input.debounce.400ms="profile.checkPhoneAvailability()"
                          placeholder="10-digit mobile number">
                      </div>
                    </label>
                    <p class="pt-fam-err" x-show="profile.phoneChange.availabilityText" x-text="profile.phoneChange.availabilityText"></p>
                    <p class="pt-fam-err" x-show="profile.phoneChange.error" x-text="profile.phoneChange.error"></p>

                    <button type="button" class="btn btn-primary pt-modal-btn" x-show="!profile.phoneChange.awaitingOtp" @click="profile.sendPhoneOtp()"
                      :disabled="profile.phoneChange.sending || profile.phoneChange.phoneDigits.length !== 10">
                      <span x-show="!profile.phoneChange.sending">Send WhatsApp OTP</span>
                      <span x-show="profile.phoneChange.sending">Sending…</span>
                    </button>

                    <div class="pt-otp-box" x-show="profile.phoneChange.awaitingOtp">
                      <p class="pt-otp-sent">WhatsApp OTP sent to <strong x-text="'+91' + profile.phoneChange.phoneDigits"></strong></p>
                      <input type="text" class="pt-otp-input pt-otp-full" x-model="profile.phoneChange.code" inputmode="numeric" maxlength="6"
                        @input="profile.phoneChange.code = profile.phoneChange.code.replace(/\D/g, '').slice(0,6)"
                        placeholder="Enter 6-digit WhatsApp OTP">
                      <button type="button" class="btn btn-primary pt-modal-btn" @click="profile.verifyPhoneOtp()"
                        :disabled="profile.phoneChange.verifying || profile.phoneChange.code.length !== 6">
                        <span x-show="!profile.phoneChange.verifying">Verify WhatsApp OTP</span>
                        <span x-show="profile.phoneChange.verifying">Verifying…</span>
                      </button>
                      <button type="button" class="btn pt-modal-btn pt-resend-btn" @click="profile.sendPhoneOtp()"
                        :disabled="profile.phoneChange.sending || profile.phoneChange.resendCountdown > 0">
                        <span x-show="!profile.phoneChange.sending && profile.phoneChange.resendCountdown === 0">Resend WhatsApp OTP</span>
                        <span x-show="!profile.phoneChange.sending && profile.phoneChange.resendCountdown > 0">Resend in <span x-text="profile.phoneChange.resendCountdown"></span>s</span>
                        <span x-show="profile.phoneChange.sending">Sending…</span>
                      </button>
                    </div>
                    <em class="pt-fld-note" x-show="profile.phoneChange.devCode">DEV OTP: <span x-text="profile.phoneChange.devCode"></span></em>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Right sidebar: vertical nav + coming-soon -->
        <aside class="pt-side">

          <!-- Vertical tab nav -->
          <nav class="pt-navmenu" role="tablist" aria-label="Patient panel sections">
            <button type="button" role="tab"
              :class="tab === 'bookings' ? 'is-active' : ''"
              @click="tab = 'bookings'">
              <span class="pt-nav-ic">📅</span>
              <span class="pt-nav-label">My bookings</span>
              <span class="pt-tab-count" x-show="bookings.upcoming.length + bookings.pending.length > 0"
                x-text="bookings.upcoming.length + bookings.pending.length"></span>
            </button>
            <button type="button" role="tab"
              :class="tab === 'shortlist' ? 'is-active' : ''"
              @click="tab = 'shortlist'">
              <span class="pt-nav-ic">❤️</span>
              <span class="pt-nav-label">Shortlist</span>
              <span class="pt-tab-count" x-show="wishlist.length > 0" x-text="wishlist.length + '/5'"></span>
            </button>
            <button type="button" role="tab"
              :class="tab === 'family' ? 'is-active' : ''"
              @click="tab = 'family'; family.loadOnce()">
              <span class="pt-nav-ic">👨‍👩‍👧</span>
              <span class="pt-nav-label">Family</span>
              <span class="pt-tab-count" x-show="family.members.length > 0" x-text="family.members.length"></span>
            </button>
            <button type="button" role="tab"
              :class="tab === 'rx' ? 'is-active' : ''"
              @click="tab = 'rx'; rx.loadOnce()">
              <span class="pt-nav-ic">💊</span>
              <span class="pt-nav-label">E-prescriptions</span>
              <span class="pt-tab-count" x-show="rx.items.length > 0" x-text="rx.items.length"></span>
            </button>
            <button type="button" role="tab"
              :class="tab === 'labs' ? 'is-active' : ''"
              @click="tab = 'labs'; labs.loadOnce()">
              <span class="pt-nav-ic">🧪</span>
              <span class="pt-nav-label">Lab reports</span>
              <span class="pt-tab-count" x-show="labs.items.length > 0" x-text="labs.items.length"></span>
            </button>
            <button type="button" role="tab"
              :class="tab === 'profile' ? 'is-active' : ''"
              @click="tab = 'profile'; profile.loadOnce()">
              <span class="pt-nav-ic">👤</span>
              <span class="pt-nav-label">My Profile</span>
            </button>
          </nav>

          <!-- Coming soon -->
          <div class="pt-soon">
            <h3>Coming soon</h3>
            <ul>
              <li>
                <span class="ic">🩺</span>
                <div><b>Video consult</b><span>Talk to a doctor from home</span></div>
              </li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </section>
<?php endif; ?>
</div>

<style>
  /* ===================================================================
   Patient panel
   =================================================================== */
  .patient-page {
    background: var(--bg-3, #fafafa);
    min-height: calc(100vh - 80px);
    padding: 120px 0 80px;
  }

  .pt-hero .wrap,
  .pt-main .wrap {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 20px;
  }

  /* -------- Logged-out (features showcase + signin card) -------- */
  .pt-hero .wrap {
    max-width: 1280px;
    padding-top: 24px;
  }

  .pt-hero-split {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
    gap: 40px;
    /* align-items: center; */
  }

  /* Left column: copy + feature list */
  .pt-hero-copy {
    min-width: 0;
  }

  .pt-eyebrow {
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

  .pt-hero-copy h1 {
    font-size: clamp(26px, 3.6vw, 40px);
    font-weight: 600;
    letter-spacing: -0.8px;
    line-height: 1.12;
    margin: 0 0 12px;
  }

  .pt-hero-lede {
    color: var(--ink-2);
    font-size: 15.5px;
    line-height: 1.5;
    margin: 0 0 22px;
    max-width: 460px;
  }

  .pt-feat-chips {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .pt-feat-chips li {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--ink-2);
    background: var(--bg-2);
    border: 1px solid var(--line);
    padding: 6px 12px;
    border-radius: 999px;
  }

  .pt-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 36px 36px 32px;
    box-shadow: 0 18px 48px rgba(0, 0, 0, 0.05);
  }

  .pt-card-signin {
    position: sticky;
    top: 100px;
  }

  .pt-card-head h1,
  .pt-card-head h2 {
    font-size: clamp(24px, 3vw, 30px);
    font-weight: 500;
    letter-spacing: -0.5px;
    margin-bottom: 8px;
  }

  .pt-card-head .lede {
    color: var(--ink-2);
    font-size: 14.5px;
    margin-bottom: 22px;
    line-height: 1.5;
  }

  .pt-card-head .lede strong {
    color: var(--ink);
    font-weight: 600;
  }

  .pt-cta-signin {
    width: 100%;
    padding: 14px 18px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 12px;
  }

  .pt-trust {
    display: flex;
    justify-content: center;
    gap: 18px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
  }

  .pt-trust span {
    font-size: 12px;
    font-weight: 600;
    color: var(--mute);
  }

  /* -------- Inline auth form (on the signin card) -------- */
  .pt-auth-tabs {
    display: flex;
    background: var(--bg-2);
    border-radius: 12px;
    padding: 4px;
    gap: 4px;
    margin-bottom: 18px;
  }

  .pt-auth-tabs button {
    flex: 1;
    background: transparent;
    border: 0;
    padding: 10px 12px;
    border-radius: 9px;
    font: inherit;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--mute);
    cursor: pointer;
    transition: all .15s;
  }

  .pt-auth-tabs button:hover {
    color: var(--ink);
  }

  .pt-auth-tabs button.is-active {
    background: var(--teal-600, #0F9B6E);
    color: #fff;
    box-shadow: 0 1px 3px rgba(15, 155, 110, 0.25), 0 0 0 1px rgba(15, 155, 110, 0.35);
  }

  .pt-auth-tabs button.is-active:hover {
    color: #fff;
  }

  .pt-auth-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .pt-auth-form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .pt-auth-lbl {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--mute);
  }

  .pt-auth-form input {
    border: 1px solid var(--line);
    border-radius: 11px;
    padding: 13px 14px;
    font: inherit;
    font-size: 16px;
    outline: none;
    width: 100%;
    transition: border-color .15s, box-shadow .15s;
  }

  .pt-auth-form input:focus {
    border-color: var(--teal-400);
    box-shadow: 0 0 0 3px rgba(15, 155, 110, 0.14);
  }

  .pt-auth-form input:disabled {
    background: var(--bg-2);
    opacity: 0.7;
  }

  .pt-phone-field {
    display: flex;
    align-items: stretch;
    border: 1px solid var(--line);
    border-radius: 11px;
    overflow: hidden;
    transition: border-color .15s, box-shadow .15s;
  }

  .pt-phone-field:focus-within {
    border-color: var(--teal-400);
    box-shadow: 0 0 0 3px rgba(15, 155, 110, 0.14);
  }

  .pt-phone-cc {
    background: var(--bg-2);
    padding: 13px 14px;
    font-weight: 600;
    font-size: 15px;
    color: var(--ink-2);
    border-right: 1px solid var(--line);
  }

  .pt-phone-field input {
    border: 0;
    border-radius: 0;
    flex: 1;
  }

  .pt-phone-field input:focus {
    box-shadow: none;
  }

  .pt-auth-captcha {
    display: flex;
    justify-content: center;
  }

  .pt-auth-err {
    font-size: 13px;
    color: #c0392b;
    background: rgba(192, 57, 43, 0.06);
    border: 1px solid rgba(192, 57, 43, 0.15);
    border-radius: 8px;
    padding: 9px 11px;
    margin: 0;
  }

  .pt-auth-err a {
    color: inherit;
    font-weight: 600;
    text-decoration: underline;
    cursor: pointer;
  }

  .pt-auth-tos {
    text-align: center;
    font-size: 12.5px;
    color: var(--mute);
    margin: 4px 0 0;
  }

  .pt-auth-tos a {
    color: var(--teal-700);
    text-decoration: underline;
  }

  .pt-auth-back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--ink-2);
    cursor: pointer;
    align-self: flex-start;
    padding: 2px 0;
  }

  .pt-auth-back:hover {
    color: var(--teal-700);
  }

  .pt-auth-welcome {
    background: var(--teal-50);
    color: var(--teal-800);
    border: 1px solid rgba(15, 155, 110, 0.15);
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 14.5px;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .pt-auth-welcome span {
    font-size: 13px;
    font-weight: 400;
    color: var(--ink-2);
  }

  .pt-auth-welcome.new {
    background: #f0f4ff;
    color: #1e3a8a;
    border-color: rgba(30, 58, 138, 0.15);
  }

  .pt-auth-dev {
    font-size: 13px;
    background: #fff7e0;
    border: 1px solid #f5d97e;
    color: #6b4f00;
    padding: 10px 12px;
    border-radius: 9px;
  }

  .pt-auth-dev strong {
    font-family: ui-monospace, Menlo, monospace;
    letter-spacing: 2px;
    margin-left: 6px;
  }

  .pt-auth-resend {
    background: transparent;
    border: 0;
    font: inherit;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--ink-2);
    cursor: pointer;
    padding: 4px;
  }

  .pt-auth-resend:hover:not(:disabled) {
    color: var(--teal-700);
  }

  .pt-auth-resend:disabled {
    opacity: 0.55;
    cursor: not-allowed;
  }

  /* Stack the split on narrower screens */
  @media (max-width: 860px) {
    .pt-hero-split {
      grid-template-columns: 1fr;
      gap: 28px;
    }

    .pt-card-signin {
      position: static;
      order: -1;
    }

    .pt-hero-copy h1 {
      font-size: clamp(24px, 6vw, 32px);
    }
  }

  /* -------- Feature showcase (alternating image + text) -------- */
  .pt-showcase {
    border-top: 1px solid var(--line);
    margin-top: 64px;
    padding-top: 56px;
  }

  .pt-showcase .wrap {
    max-width: 1040px;
    margin: 0 auto;
    padding: 0 24px;
  }

  .pt-showcase-head {
    text-align: center;
    margin-bottom: 48px;
  }

  .pt-showcase-head h2 {
    font-size: clamp(24px, 3.4vw, 34px);
    font-weight: 600;
    letter-spacing: -0.6px;
    margin: 6px 0 0;
  }

  .pt-show-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 48px;
    align-items: center;
    margin-bottom: 72px;
  }

  .pt-show-row.is-reverse .pt-show-media {
    order: 2;
  }

  .pt-show-media {
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--line);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.07);
    background: #fff;
  }

  .pt-show-media img {
    display: block;
    width: 100%;
    height: auto;
  }

  .pt-show-kicker {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--teal-700);
    background: var(--teal-50);
    padding: 5px 12px;
    border-radius: 999px;
    margin-bottom: 14px;
  }

  .pt-show-copy h3 {
    font-size: clamp(21px, 2.6vw, 28px);
    font-weight: 600;
    letter-spacing: -0.5px;
    line-height: 1.2;
    margin: 0 0 12px;
  }

  .pt-show-copy p {
    font-size: 15.5px;
    line-height: 1.6;
    color: var(--ink-2);
    margin: 0;
    max-width: 440px;
  }

  .pt-show-points {
    list-style: none;
    padding: 0;
    margin: 18px 0 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 460px;
  }

  .pt-show-points li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 14.5px;
    line-height: 1.45;
    color: var(--ink-2);
  }

  .pt-show-tick {
    color: var(--teal-600);
    flex-shrink: 0;
    margin-top: 1px;
  }

  .pt-show-points strong {
    font-weight: 600;
    color: var(--ink);
  }

  .pt-show-cta {
    margin-top: 22px;
    padding: 13px 24px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 12px;
  }

  @media (max-width: 820px) {
    .pt-showcase {
      margin-top: 44px;
      padding-top: 40px;
    }

    .pt-showcase-head {
      margin-bottom: 36px;
    }

    .pt-show-row {
      grid-template-columns: 1fr;
      gap: 22px;
      margin-bottom: 52px;
    }

    /* Image always sits above the text on mobile, regardless of desktop side. */
    .pt-show-row.is-reverse .pt-show-media {
      order: 0;
    }

    .pt-show-copy {
      text-align: center;
    }

    .pt-show-copy p {
      margin-left: auto;
      margin-right: auto;
    }

    /* Keep the checklist left-aligned (readable) but centre the block. */
    .pt-show-points {
      text-align: left;
      margin-left: auto;
      margin-right: auto;
    }

    .pt-show-media {
      max-width: 460px;
      margin: 0 auto;
    }
  }

  @media (max-width: 480px) {
    .pt-feat-chips {
      gap: 6px;
    }
  }

  .pt-tabs {
    display: flex;
    border-bottom: 1px solid var(--line);
    margin-bottom: 22px;
  }

  .pt-tabs button {
    background: none;
    border: 0;
    padding: 12px 4px;
    margin-right: 24px;
    font: inherit;
    font-size: 14px;
    font-weight: 600;
    color: var(--mute);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
  }

  .pt-tabs button.is-active {
    color: var(--ink);
    border-bottom-color: var(--teal-600);
  }

  .pt-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .pt-form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .pt-form .lbl {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--mute);
  }

  .pt-form .lbl em {
    font-style: normal;
    color: var(--teal-700);
    text-transform: none;
    letter-spacing: normal;
    font-weight: 500;
  }

  .pt-form input {
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 12px 14px;
    font: inherit;
    font-size: 15px;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
  }

  .pt-form input:focus {
    border-color: var(--teal-400);
    box-shadow: 0 0 0 3px rgba(15, 155, 110, 0.12);
  }

  .pt-form .pt-hint {
    font-size: 12.5px;
    color: var(--mute);
    margin: -2px 0 6px;
    line-height: 1.5;
  }

  .pt-form .btn {
    padding: 13px 18px;
    font-size: 14.5px;
    font-weight: 600;
    border-radius: 11px;
    margin-top: 4px;
  }

  /* -------- Logged-in: hero strip -------- */
  .pt-hero-strip {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 24px 28px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.03);
  }

  .pt-hero-id {
    display: flex;
    align-items: center;
    gap: 18px;
    min-width: 0;
  }

  .pt-bigavatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--teal-400), var(--teal-700));
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 24px;
    letter-spacing: -0.5px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(15, 155, 110, 0.20);
    overflow: hidden;
  }

  .pt-bigavatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    display: block;
  }

  .pt-greet {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--mute);
    margin-bottom: 2px;
  }

  .pt-hero-id h1 {
    font-size: clamp(20px, 2.6vw, 26px);
    font-weight: 600;
    letter-spacing: -0.4px;
    margin: 0 0 4px;
    line-height: 1.2;
  }

  .pt-handle {
    font-size: 13.5px;
    color: var(--mute);
  }

  .pt-hero-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
  }

  .btn-outline,
  .btn-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 16px;
    border-radius: 10px;
    font: inherit;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
  }

  .btn-outline {
    background: #fff;
    border: 1px solid var(--line);
    color: var(--ink-2);
  }

  .btn-outline:hover {
    border-color: var(--ink);
    color: var(--ink);
  }

  .btn-ghost {
    background: var(--bg-2);
    border: 1px solid transparent;
    color: var(--ink-2);
  }

  .btn-ghost:hover {
    background: var(--teal-50);
    color: var(--teal-700);
  }

  /* -------- 2-column grid: main content + nav sidebar -------- */
  .pt-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 280px;
    gap: 24px;
    align-items: start;
  }

  .pt-section,
  .pt-soon {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 22px 24px;
  }

  /* Right sidebar wrapper — nav + coming-soon, sticks while scrolling. */
  .pt-side {
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: sticky;
    top: 88px;
  }

  .pt-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
  }

  .pt-section-head h2 {
    font-size: 16px;
    font-weight: 600;
    letter-spacing: -0.3px;
    margin: 0;
  }

  .pt-counter {
    font-size: 12px;
    font-weight: 700;
    background: var(--teal-50);
    color: var(--teal-800);
    padding: 4px 11px;
    border-radius: 999px;
    letter-spacing: 0.02em;
  }

  /* Empty state */
  .pt-empty {
    text-align: center;
    padding: 36px 16px 28px;
  }

  .pt-empty .glyph {
    font-size: 36px;
    margin-bottom: 10px;
    filter: grayscale(0.3);
  }

  .pt-empty h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 6px;
  }

  .pt-empty p {
    font-size: 13.5px;
    color: var(--mute);
    margin: 0 auto 18px;
    max-width: 320px;
    line-height: 1.5;
  }

  .pt-empty .btn {
    display: inline-block;
    padding: 10px 22px;
    font-size: 13.5px;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
  }

  /* Wishlist rows */
  .pt-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .pt-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 12px 14px;
    transition: border-color .15s, box-shadow .15s;
  }

  .pt-row:hover {
    border-color: var(--teal-400);
    box-shadow: 0 4px 14px rgba(15, 155, 110, 0.06);
  }

  .pt-row-id {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    flex: 1;
  }

  .pt-row-text {
    min-width: 0;
  }

  .pt-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--teal-100), var(--teal-400));
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
  }

  .pt-name {
    font-weight: 600;
    font-size: 14.5px;
    color: var(--ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .pt-sub {
    font-size: 12.5px;
    color: var(--mute);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .pt-row-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
  }

  .btn-mini {
    border: 1px solid var(--line);
    background: #fff;
    padding: 7px 12px;
    border-radius: 8px;
    font: inherit;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--ink-2);
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all .15s;
  }

  .btn-mini:hover {
    border-color: var(--ink);
    color: var(--ink);
  }

  .btn-mini.primary {
    background: var(--teal-600);
    color: #fff;
    border-color: var(--teal-600);
  }

  .btn-mini.primary:hover {
    background: var(--teal-700);
    border-color: var(--teal-700);
  }

  /* Coming-soon sidebar */
  .pt-soon h3 {
    font-size: 13px;
    font-weight: 600;
    color: var(--mute);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin: 0 0 14px;
  }

  .pt-soon ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .pt-soon li {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    font-size: 13px;
  }

  .pt-soon li .ic {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: var(--bg-2);
    display: grid;
    place-items: center;
    font-size: 16px;
    flex-shrink: 0;
  }

  .pt-soon li b {
    display: block;
    font-weight: 600;
    color: var(--ink);
    font-size: 13.5px;
  }

  .pt-soon li span {
    display: block;
    color: var(--mute);
    font-size: 12.5px;
    margin-top: 1px;
  }

  /* -------- Tabs (Bookings / Shortlist) -------- */
  .pt-section-tabbed {
    padding: 0;
  }

  /* Vertical nav menu in the right sidebar. */
  .pt-navmenu {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .pt-navmenu button {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    background: none;
    border: 0;
    padding: 11px 12px;
    border-radius: 11px;
    font: inherit;
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-2);
    cursor: pointer;
    text-align: left;
    transition: background .15s, color .15s;
  }

  .pt-navmenu button:hover {
    background: var(--bg-2);
    color: var(--ink);
  }

  .pt-navmenu button.is-active {
    background: var(--teal-50);
    color: var(--teal-800);
  }

  .pt-nav-ic {
    font-size: 16px;
    line-height: 1;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
  }

  .pt-nav-label {
    flex: 1 1 auto;
  }

  .pt-tab-count {
    font-size: 11px;
    font-weight: 700;
    background: var(--bg-2);
    color: var(--ink-2);
    padding: 2px 7px;
    border-radius: 999px;
    min-width: 18px;
    text-align: center;
    flex-shrink: 0;
  }

  .pt-navmenu button.is-active .pt-tab-count {
    background: var(--teal-100, #cceee0);
    color: var(--teal-800);
  }

  .pt-tab-pane {
    padding: 22px 24px 24px;
  }

  .pt-section-head h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--mute);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin: 0;
  }

  .pt-section-note {
    font-size: 12.5px;
    color: var(--mute);
    margin: 0 0 10px;
  }

  .pt-link-btn {
    background: none;
    border: 0;
    font: inherit;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--teal-700);
    cursor: pointer;
  }

  .pt-link-btn:hover {
    text-decoration: underline;
  }

  .pt-loading {
    text-align: center;
    padding: 28px 16px;
    color: var(--mute);
    font-size: 13.5px;
  }

  /* -------- Booking rows -------- */
  .pt-booking {
    display: flex;
    align-items: center;
    gap: 14px;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 14px;
    transition: border-color .15s, box-shadow .15s;
  }

  .pt-booking:hover {
    border-color: var(--teal-300, #a3d9c4);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
  }

  .pt-booking-confirmed {
    border-left: 4px solid var(--teal-600);
  }

  .pt-booking-pending {
    border-left: 4px solid #f59e0b;
  }

  .pt-booking-past {
    opacity: 0.85;
  }

  .pt-booking-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 64px;
    flex-shrink: 0;
    text-align: center;
    line-height: 1;
  }

  .pt-date-day {
    font-size: 22px;
    font-weight: 700;
    color: var(--ink);
    letter-spacing: -0.5px;
  }

  .pt-date-mon {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--mute);
    margin-top: 2px;
  }

  .pt-date-time {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--ink-2);
    margin-top: 6px;
    white-space: nowrap;
  }

  .pt-booking-body {
    flex: 1;
    min-width: 0;
  }

  .pt-booking-doctor {
    font-size: 14.5px;
    font-weight: 600;
    color: var(--ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .pt-booking-clinic {
    font-size: 12.5px;
    color: var(--mute);
    margin-top: 2px;
  }

  .pt-booking-reason {
    font-size: 12px;
    color: var(--ink-2);
    margin-top: 4px;
    font-style: italic;
  }

  .pt-booking-token {
    display: inline-block;
    margin-top: 4px;
    background: var(--teal-50);
    color: var(--teal-800);
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
  }

  .pt-booking-token strong {
    font-size: 13px;
  }

  .pt-booking-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
  }

  .pt-status {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 4px 9px;
    border-radius: 999px;
    white-space: nowrap;
  }

  .pt-status-confirmed {
    background: var(--teal-50);
    color: var(--teal-800);
  }

  .pt-status-pending {
    background: #fff7e0;
    color: #875c00;
  }

  .pt-status-past {
    background: var(--bg-2);
    color: var(--mute);
  }

  /* -------- Family profiles -------- */
  .pt-fam-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 14px;
  }

  .pt-fam-card {
    border: 1px solid var(--line);
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
  }

  .pt-fam-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
  }

  .pt-fam-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--teal-100), var(--teal-400));
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 13px;
  }

  .pt-fam-id {
    flex: 1;
    min-width: 0;
  }

  .pt-fam-name {
    display: block;
    font-weight: 600;
    font-size: 14.5px;
    color: var(--ink);
  }

  .pt-fam-rel {
    display: block;
    font-size: 12.5px;
    color: var(--mute);
    margin-top: 2px;
  }

  .pt-fam-blood {
    background: var(--teal-50);
    color: var(--teal-800);
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    margin-left: 6px;
  }

  .pt-fam-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
    flex-shrink: 0;
  }

  /* Add/edit member form */
  .pt-fam-edit {
    border: 1px solid var(--teal-400);
    border-radius: 14px;
    padding: 16px;
    margin: 14px 0;
    background: var(--teal-50, #f0faf6);
  }

  .pt-fam-edit-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .pt-fld {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1 1 150px;
    min-width: 0;
  }

  .pt-fld-wide {
    flex-basis: 100%;
  }

  .pt-fld>span {
    font-size: 11px;
    font-weight: 600;
    color: var(--mute);
  }

  .pt-fld>span em {
    font-style: normal;
    color: var(--teal-700);
    font-weight: 500;
  }

  .pt-fld input,
  .pt-fld select {
    border: 1px solid var(--line);
    border-radius: 9px;
    padding: 9px 11px;
    font: inherit;
    font-size: 14px;
    background: #fff;
    outline: none;
    width: 100%;
  }

  .pt-fld input:focus,
  .pt-fld select:focus {
    border-color: var(--teal-400);
  }

  .pt-fam-err {
    color: #c0392b;
    font-size: 13px;
    margin: 8px 0 0;
  }

  .pt-fam-limit {
    color: #b45309;
    font-size: 13px;
    margin: 0 0 12px;
  }

  .pt-fam-edit-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 14px;
  }

  /* -------- My Profile -------- */
  .pt-fld textarea {
    border: 1px solid var(--line);
    border-radius: 9px;
    padding: 9px 11px;
    font: inherit;
    font-size: 14px;
    background: #fff;
    outline: none;
    width: 100%;
    resize: vertical;
  }

  .pt-fld textarea:focus {
    border-color: var(--teal-400);
  }

  .pt-fld input:disabled {
    background: var(--bg-3, #f5f5f5);
    color: var(--mute);
  }

  .pt-fld-note {
    font-size: 11px;
    color: var(--teal-700);
    font-style: normal;
  }

  .pt-clickable-input {
    cursor: pointer;
  }

  .pt-inline-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
  }

  .pt-otp-input {
    max-width: 150px;
  }

  .pt-otp-box {
    display: grid;
    gap: 10px;
    margin-top: 6px;
  }

  .pt-otp-sent {
    margin: 0;
    font-size: 12px;
    color: var(--ink-2);
    background: var(--bg-2);
    border-radius: 8px;
    padding: 8px 10px;
  }

  .pt-otp-full {
    max-width: 100%;
    width: 100%;
    min-height: 32px;
    padding: 7px 12px;
    border: 1px solid #e5e7eb !important;
    border-radius: 9px;
    background: #fff;
    color: #111827;
    font-size: 13px;
    line-height: 1.2;
    box-shadow: none !important;
    outline: none !important;
    -webkit-appearance: none;
    appearance: none;
  }

  .pt-otp-full:focus {
    border-color: #d1d5db !important;
    box-shadow: none !important;
    outline: none !important;
  }

  .pt-resend-btn {
    width: 100%;
    min-height: 32px;
    padding: 7px 12px;
    border: 1px solid #e5e7eb !important;
    border-radius: 9px;
    background: #fff;
    color: #6b7280;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
    box-shadow: none !important;
  }

  .pt-resend-btn:hover:not(:disabled) {
    border-color: #d1d5db !important;
    background: #fff;
    color: #4b5563;
  }

  .pt-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 30, .45);
    display: grid;
    place-items: center;
    z-index: 1200;
    padding: 16px;
  }

  .pt-modal-card {
    width: 100%;
    max-width: 520px;
    background: #fff;
    border-radius: 14px;
    padding: 16px;
    border: 1px solid var(--line);
    box-shadow: 0 20px 48px rgba(0, 0, 0, .22);
    position: relative;
  }

  .pt-modal-close {
    position: absolute;
    top: 8px;
    right: 10px;
    border: 0;
    background: transparent;
    font-size: 22px;
    line-height: 1;
    color: var(--mute);
    cursor: pointer;
  }

  .pt-modal-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
  }

  .pt-modal-sub {
    margin: 6px 0 12px;
    font-size: 13px;
    color: var(--mute);
  }

  .pt-modal-btn {
    width: 100%;
    margin-top: 4px;
  }

  .pt-phone-row {
    display: flex;
    align-items: center;
    border: 1px solid var(--line);
    border-radius: 9px;
    overflow: hidden;
    background: #fff;
  }

  .pt-phone-row .pt-cc {
    padding: 9px 10px;
    border-right: 1px solid var(--line);
    color: var(--ink-2);
    background: var(--bg-3, #f5f5f5);
    font-size: 13px;
    font-weight: 600;
  }

  .pt-phone-row input {
    border: 0;
    border-radius: 0;
  }

  .pt-fld-wide {
    flex-basis: 320px;
  }

  .pt-profile-group {
    font-size: 13px;
    font-weight: 700;
    color: var(--ink-2, #444);
    margin: 22px 0 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--line);
  }

  .pt-profile-group:first-of-type {
    margin-top: 8px;
  }

  .pt-profile-photo {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 4px;
  }

  .pt-avatar-img {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--line);
  }

  .pt-photo-actions {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
  }

  .pt-photo-actions label {
    cursor: pointer;
  }

  .pt-photo-hint {
    font-size: 11px;
    color: var(--mute);
    margin: 0;
  }

  .pt-hidden-file {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
  }

  .pt-save-hint {
    font-size: 12px;
    color: var(--teal-700);
  }

  .pt-save-ok {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--teal-800);
    background: var(--teal-50);
    border: 1px solid var(--teal-100, #cceee0);
    border-radius: 10px;
    padding: 11px 14px;
    margin: 8px 0 0;
  }

  /* -------- Responsive -------- */
  @media (max-width: 820px) {
    .pt-grid {
      grid-template-columns: 1fr;
    }

    /* Nav sidebar stacks ABOVE the content and stops sticking. */
    .pt-side {
      position: static;
      order: -1;
    }

    /* Nav becomes a horizontal, scrollable row so it doesn't eat height. */
    .pt-navmenu {
      flex-direction: row;
      overflow-x: auto;
      gap: 4px;
      padding: 6px;
    }

    .pt-navmenu button {
      flex-direction: column;
      gap: 4px;
      padding: 8px 12px;
      font-size: 12px;
      white-space: nowrap;
    }

    .pt-nav-ic {
      font-size: 18px;
      width: auto;
    }

    .pt-navmenu button .pt-tab-count {
      position: absolute;
      top: 4px;
      right: 6px;
    }

    .pt-navmenu button {
      position: relative;
    }
  }

  @media (max-width: 600px) {
    .patient-page {
      padding: 24px 0 60px;
    }

    .pt-card {
      padding: 28px 22px 24px;
    }

    .pt-hero-strip {
      padding: 20px;
      gap: 14px;
    }

    .pt-hero-id {
      gap: 14px;
      width: 100%;
    }

    .pt-bigavatar {
      width: 54px;
      height: 54px;
      font-size: 20px;
    }

    .pt-hero-actions {
      width: 100%;
    }

    .pt-hero-actions .btn-outline,
    .pt-hero-actions .btn-ghost {
      flex: 1;
    }

    .pt-section,
    .pt-soon {
      padding: 18px 16px;
    }

    .pt-section-tabbed {
      padding: 0;
    }

    .pt-tab-pane {
      padding: 16px;
    }

    .pt-row {
      padding: 12px;
    }

    .pt-row-actions .btn-mini {
      padding: 8px 10px;
    }

    .pt-booking {
      gap: 10px;
      padding: 12px;
    }

    .pt-booking-date {
      width: 52px;
    }

    .pt-date-day {
      font-size: 20px;
    }

    .pt-booking-actions {
      flex-direction: row;
    }

    .pt-fam-facts {
      grid-template-columns: 1fr;
    }

    .pt-hero-split,
    .ct-grid {
      display: flex;
      flex-direction: column;
    }

    .pt-card,
    .ct-card {
      width: 100%;
    }
  }

  .ct-list-card {
    display: flex;
    gap: 15px;
    margin-top: 30px;
  }

  .ct-list-card .ct-card {
    display: flex;
    align-items: center;
    width: 33.33%;
    /* justify-content: center; */
    flex-direction: column;
    padding: 16px 6px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 18px 48px rgba(0, 0, 0, 0.05);
  }

  .ct-list-card .ct-card .ct-card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    margin-bottom: 10px;
    border-radius: 50%;
    /* background: var(--teal-50);
    border: 1px solid var(--teal-100); */
  }

  .ct-list-card .ct-card h3 {
    font-size: 16px;
  }

  .ct-list-card .ct-card p {
    font-size: 14px;
  }

  .ct-list-card .ct-card .ct-card-icon img {
    filter: brightness(0) saturate(100%) invert(28%) sepia(63%) saturate(1045%) hue-rotate(134deg) brightness(92%) contrast(93%);
  }
</style>

<script>
  /* Inline sign-in/up on the logged-out patient page.
   Reuses the same /api/patient_auth endpoints as the shared modal. */
  function patientInlineAuth(captchaEnabled) {
    return {
      step: 'phone', // 'phone' | 'code'
      intent: 'signup', // 'signin' | 'signup' — default to Create account for new-visitor conversion
      phoneDigits: '',
      code: '',
      name: '',
      phoneExists: false,
      nameHint: null,
      devCode: null,
      busy: false,
      errorMsg: '',
      resendCountdown: 0,
      captchaEnabled: !!captchaEnabled,
      _resendTimer: null,

      // Load reCAPTCHA on first interaction with the form (not on page load),
      // so browsing visitors never pay its ~370 KB / ~2s cost.
      loadCaptcha() {
        if (this.captchaEnabled && window.ecpLoadRecaptcha) window.ecpLoadRecaptcha();
      },

      subline() {
        if (this.step === 'code') {
          return this.phoneExists ? 'Almost there — enter the code we sent you.' :
            'Last step before your account is ready.';
        }
        return this.intent === 'signup' ?
          "New here? Enter your WhatsApp number — we'll send a code." :
          "Sign in with your mobile number. Free forever.";
      },

      captchaToken() {
        if (!this.captchaEnabled || typeof grecaptcha === 'undefined') return '';
        try {
          for (let i = 0; i < 4; i++) {
            try {
              const t = grecaptcha.getResponse(i);
              if (t) return t;
            } catch (e) {
              break;
            }
          }
          return grecaptcha.getResponse() || '';
        } catch (e) {
          return '';
        }
      },
      resetCaptcha() {
        if (!this.captchaEnabled || typeof grecaptcha === 'undefined') return;
        try {
          for (let i = 0; i < 4; i++) {
            try {
              grecaptcha.reset(i);
            } catch (e) {
              break;
            }
          }
        } catch (e) {}
      },

      flipTo(newIntent) {
        this.intent = newIntent;
        this.errorMsg = '';
      },

      async sendOtp() {
        if (this.phoneDigits.length < 10) return;
        const captcha = this.captchaToken();
        if (this.captchaEnabled && !captcha) {
          this.errorMsg = 'Please complete the captcha.';
          return;
        }
        this.busy = true;
        this.errorMsg = '';
        this.devCode = null;
        try {
          const r = await fetch('/api/patient_auth?action=send_otp', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              phone: '+91' + this.phoneDigits,
              intent: this.intent,
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
            if (j.error === 'account_not_found') {
              this.errorMsg = "We don't see an account with this number. " +
                '<a onclick="Alpine.$data(document.querySelector(\'.pt-card-signin\')).flipTo(\'signup\')">Create one instead?</a>';
            } else if (j.error === 'account_exists') {
              this.errorMsg = 'This number is already registered. ' +
                '<a onclick="Alpine.$data(document.querySelector(\'.pt-card-signin\')).flipTo(\'signin\')">Sign in instead?</a>';
            } else {
              this.errorMsg = this.errorText(j.error, j.retry_after) + (j.hint ? ' — ' + j.hint : '');
            }
            return;
          }
          this.phoneExists = !!j.exists;
          this.nameHint = j.name_hint || null;
          if (j.dev_code) this.devCode = j.dev_code;
          this.step = 'code';
          this.resetCaptcha();
          this.startResendCountdown(30);
          this.$nextTick(() => this.$refs.codeInput && this.$refs.codeInput.focus());
        } catch (e) {
          this.errorMsg = "Couldn't reach server: " + (e.message || e);
          this.resetCaptcha();
        } finally {
          this.busy = false;
        }
      },

      async resendOtp() {
        this.code = '';
        if (this.captchaEnabled && !this.captchaToken()) {
          this.errorMsg = 'Complete the captcha, then tap Resend again.';
          return;
        }
        await this.sendOtp();
      },

      startResendCountdown(secs) {
        this.resendCountdown = secs;
        if (this._resendTimer) clearInterval(this._resendTimer);
        this._resendTimer = setInterval(() => {
          this.resendCountdown -= 1;
          if (this.resendCountdown <= 0) {
            clearInterval(this._resendTimer);
            this._resendTimer = null;
          }
        }, 1000);
      },

      async verifyOtp() {
        if (this.code.length !== 6) return;
        const captcha = this.captchaToken();
        if (this.captchaEnabled && !captcha) {
          this.errorMsg = 'Please complete the captcha.';
          return;
        }
        this.busy = true;
        this.errorMsg = '';
        try {
          const r = await fetch('/api/patient_auth?action=verify_otp', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              phone: '+91' + this.phoneDigits,
              code: this.code,
              name: this.name || undefined,
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
            this.errorMsg = this.errorText(j.error) + (j.hint ? ' — ' + j.hint : '');
            return;
          }
          // Success — reload into the logged-in panel.
          if (typeof ecpSetPatientSession === 'function') ecpSetPatientSession(j.patient);
          location.reload();
        } catch (e) {
          this.errorMsg = "Couldn't reach server. Check your connection.";
          this.resetCaptcha();
        } finally {
          this.busy = false;
        }
      },

      errorText(code, retryAfter) {
        switch (code) {
          case 'invalid_phone':
            return "That number doesn't look right.";
          case 'phone_required':
            return 'Enter your mobile number.';
          case 'resend_too_soon':
            return retryAfter ? `Please wait ${retryAfter}s before requesting another code.` : 'Please wait a moment before requesting another code.';
          case 'otp_locked':
            return retryAfter ? `Too many OTP requests. Try again in ${Math.ceil(retryAfter / 60)} minute(s).` : 'Too many OTP requests. Please try again later.';
          case 'invalid_code':
            return 'That code is incorrect. Try again.';
          case 'expired':
            return 'Code expired. Tap Resend.';
          case 'too_many_attempts':
            return 'Too many attempts. Request a new code.';
          case 'no_code_issued':
            return 'No active code. Tap Resend.';
          case 'whatsapp_not_configured':
            return 'WhatsApp OTP is not configured yet. Contact support.';
          case 'not_whatsapp':
            return 'This number does not appear to have WhatsApp active.';
          case 'wa_send_failed':
            return "We couldn't send the WhatsApp OTP. Try again later.";
          case 'captcha_failed':
            return 'Please complete the captcha and try again.';
          default:
            return 'Something went wrong. Please try again.';
        }
      },
    };
  }

  function patientPanel(isLoggedIn) {
    return {
      loggedIn: !!isLoggedIn,
      tab: 'bookings', // 'bookings' | 'shortlist'
      wishlist: [],
      loading: false,
      // Hero avatar photo state — seeded from the server, updated live on upload.
      heroHasPhoto: <?= !empty($me['photo_path']) ? 'true' : 'false' ?>,
      heroPhotoVer: Date.now(),
      bookings: {
        upcoming: [],
        pending: [],
        past: [],
        loading: true,
        pastOpen: false,
      },

      // ---- Family profiles (Family tab) ----
      family: {
        loaded: false,
        loading: false,
        members: [],
        maxMembers: 6,
        canAddFlag: true,
        editing: false,
        saving: false,
        formError: '',
        form: {},
        relations: [{
            v: 'self',
            t: 'Self'
          }, {
            v: 'spouse',
            t: 'Spouse'
          },
          {
            v: 'mother',
            t: 'Mother'
          }, {
            v: 'father',
            t: 'Father'
          },
          {
            v: 'son',
            t: 'Son'
          }, {
            v: 'daughter',
            t: 'Daughter'
          },
          {
            v: 'guardian',
            t: 'Guardian'
          }, {
            v: 'other',
            t: 'Other'
          },
        ],
        bloods: ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'],

        async loadOnce() {
          if (this.loaded) return;
          await this.load();
        },
        async load() {
          this.loading = true;
          try {
            const r = await fetch('/api/family', {
              credentials: 'same-origin'
            });
            const j = await r.json();
            this.members = j.ok ? (j.members || []) : [];
            if (j.ok && j.max_members) this.maxMembers = j.max_members;
            if (j.ok && typeof j.can_add === 'boolean') this.canAddFlag = j.can_add;
          } catch (e) {
            this.members = [];
          } finally {
            this.loading = false;
            this.loaded = true;
          }
        },

        canAdd() {
          return this.canAddFlag && this.members.length < this.maxMembers;
        },

        relLabel(v) {
          return (this.relations.find(r => r.v === v) || {}).t || v;
        },
        genderLabel(v) {
          return ({
            M: 'Male',
            F: 'Female',
            Other: 'Other'
          })[v] || v;
        },
        initials(name) {
          const p = (name || '').trim().split(/\s+/);
          return ((p[0] || '')[0] || '?').toUpperCase() + (p.length > 1 ? (p[p.length - 1][0] || '').toUpperCase() : '');
        },
        age(dob) {
          try {
            const d = new Date(dob),
              n = new Date();
            let a = n.getFullYear() - d.getFullYear();
            if (n.getMonth() < d.getMonth() || (n.getMonth() === d.getMonth() && n.getDate() < d.getDate())) a--;
            return a;
          } catch (e) {
            return '';
          }
        },

        // ---- add / edit member ----
        blankForm(relation) {
          return {
            member_id: 0,
            relation: relation || 'other',
            name: '',
            dob: '',
            gender: '',
            blood_group: '',
            abha_id: ''
          };
        },
        startAdd() {
          if (!this.canAdd()) {
            this.formError = 'You can add up to ' + this.maxMembers + ' family members only.';
            return;
          }
          this.formError = '';
          this.form = this.blankForm('spouse');
          this.editing = true;
        },
        startEdit(m) {
          this.formError = '';
          this.form = {
            member_id: m.id,
            relation: m.relation,
            name: m.name || '',
            dob: m.dob || '',
            gender: m.gender || '',
            blood_group: m.blood_group || '',
            abha_id: m.abha_id || '',
          };
          this.editing = true;
        },
        cancelEdit() {
          this.editing = false;
          this.formError = '';
        },
        async saveMember() {
          if (!this.form.name.trim()) {
            this.formError = 'Please enter a name.';
            return;
          }
          this.saving = true;
          this.formError = '';
          try {
            const r = await fetch('/api/family?action=save_member', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify(this.form),
            });
            const j = await r.json();
            if (j.ok) {
              this.editing = false;
              await this.load();
            } else {
              this.formError = j.error === 'member_limit_reached' ?
                'You can add up to ' + this.maxMembers + ' family members only.' :
                ('Could not save. ' + (j.error || ''));
            }
          } catch (e) {
            this.formError = 'Network error — please try again.';
          } finally {
            this.saving = false;
          }
        },
        async removeMember(m) {
          if (!confirm('Remove ' + m.name + ' from your family list?')) return;
          try {
            await fetch('/api/family?action=remove_member', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                member_id: m.id
              }),
            });
            await this.load();
          } catch (e) {}
        },
      },

      // ---- E-prescriptions (Rx tab) ----
      rx: {
        loaded: false,
        loading: false,
        items: [],
        adding: false,
        saving: false,
        formError: '',
        form: {},
        file: null,

        async loadOnce() {
          if (this.loaded) return;
          await this.load();
        },
        async load() {
          this.loading = true;
          try {
            const r = await fetch('/api/patient_prescriptions', {
              credentials: 'same-origin'
            });
            const j = await r.json();
            this.items = j.ok ? (j.items || []) : [];
          } catch (e) {
            this.items = [];
          } finally {
            this.loading = false;
            this.loaded = true;
          }
        },

        blankForm() {
          return {
            family_member_id: '',
            label: '',
            doctor_name: '',
            issued_on: '',
            notes: ''
          };
        },
        startAdd() {
          this.formError = '';
          this.file = null;
          this.form = this.blankForm();
          this.adding = true;
          // Family members feed the "For" dropdown — make sure they're loaded.
          this.$data.family.loadOnce();
        },
        cancelAdd() {
          this.adding = false;
          this.formError = '';
          this.file = null;
        },
        pickFile(e) {
          this.file = (e.target.files && e.target.files[0]) || null;
        },

        async save() {
          if (!this.form.label.trim()) {
            this.formError = 'Please enter a title.';
            return;
          }
          this.saving = true;
          this.formError = '';
          try {
            const fd = new FormData();
            fd.append('label', this.form.label);
            if (this.form.family_member_id) fd.append('family_member_id', this.form.family_member_id);
            if (this.form.doctor_name) fd.append('doctor_name', this.form.doctor_name);
            if (this.form.issued_on) fd.append('issued_on', this.form.issued_on);
            if (this.form.notes) fd.append('notes', this.form.notes);
            if (this.file) fd.append('file', this.file);

            const r = await fetch('/api/patient_prescriptions?action=add', {
              method: 'POST',
              credentials: 'same-origin',
              body: fd,
            });
            const j = await r.json();
            if (j.ok) {
              this.adding = false;
              this.file = null;
              await this.load();
            } else {
              this.formError = ({
                label_required: 'Please enter a title.',
                file_too_large: 'That file is too large (max ' + (j.max_mb || 10) + ' MB).',
                file_type_not_allowed: 'Only images or PDF files are allowed.',
                member_not_found: 'That family member could not be found.',
              })[j.error] || ('Could not save. ' + (j.error || ''));
            }
          } catch (e) {
            this.formError = 'Network error — please try again.';
          } finally {
            this.saving = false;
          }
        },
        async remove(p) {
          if (!confirm('Remove "' + p.label + '" from your prescriptions?')) return;
          try {
            await fetch('/api/patient_prescriptions?action=remove', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                id: p.id
              }),
            });
            await this.load();
          } catch (e) {}
        },
        fmtDate(d) {
          try {
            return new Date(d).toLocaleDateString('en-IN', {
              day: 'numeric',
              month: 'short',
              year: 'numeric'
            });
          } catch (e) {
            return d;
          }
        },
      },

      // ---- Lab reports (Labs tab) ----
      labs: {
        loaded: false,
        loading: false,
        items: [],
        adding: false,
        saving: false,
        formError: '',
        form: {},
        file: null,

        async loadOnce() {
          if (this.loaded) return;
          await this.load();
        },
        async load() {
          this.loading = true;
          try {
            const r = await fetch('/api/patient_lab_reports', {
              credentials: 'same-origin'
            });
            const j = await r.json();
            this.items = j.ok ? (j.items || []) : [];
          } catch (e) {
            this.items = [];
          } finally {
            this.loading = false;
            this.loaded = true;
          }
        },

        blankForm() {
          return {
            family_member_id: '',
            label: '',
            test_type: '',
            lab_name: '',
            doctor_name: '',
            reported_on: '',
            notes: ''
          };
        },
        startAdd() {
          this.formError = '';
          this.file = null;
          this.form = this.blankForm();
          this.adding = true;
          // Family members feed the "For" dropdown — make sure they're loaded.
          this.$data.family.loadOnce();
        },
        cancelAdd() {
          this.adding = false;
          this.formError = '';
          this.file = null;
        },
        pickFile(e) {
          this.file = (e.target.files && e.target.files[0]) || null;
        },

        async save() {
          if (!this.form.label.trim()) {
            this.formError = 'Please enter a title.';
            return;
          }
          this.saving = true;
          this.formError = '';
          try {
            const fd = new FormData();
            fd.append('label', this.form.label);
            if (this.form.family_member_id) fd.append('family_member_id', this.form.family_member_id);
            if (this.form.test_type) fd.append('test_type', this.form.test_type);
            if (this.form.lab_name) fd.append('lab_name', this.form.lab_name);
            if (this.form.doctor_name) fd.append('doctor_name', this.form.doctor_name);
            if (this.form.reported_on) fd.append('reported_on', this.form.reported_on);
            if (this.form.notes) fd.append('notes', this.form.notes);
            if (this.file) fd.append('file', this.file);

            const r = await fetch('/api/patient_lab_reports?action=add', {
              method: 'POST',
              credentials: 'same-origin',
              body: fd,
            });
            const j = await r.json();
            if (j.ok) {
              this.adding = false;
              this.file = null;
              await this.load();
            } else {
              this.formError = ({
                label_required: 'Please enter a title.',
                file_too_large: 'That file is too large (max ' + (j.max_mb || 10) + ' MB).',
                file_type_not_allowed: 'Only images or PDF files are allowed.',
                member_not_found: 'That family member could not be found.',
              })[j.error] || ('Could not save. ' + (j.error || ''));
            }
          } catch (e) {
            this.formError = 'Network error — please try again.';
          } finally {
            this.saving = false;
          }
        },
        async remove(r) {
          if (!confirm('Remove "' + r.label + '" from your lab reports?')) return;
          try {
            await fetch('/api/patient_lab_reports?action=remove', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                id: r.id
              }),
            });
            await this.load();
          } catch (e) {}
        },
        fmtDate(d) {
          try {
            return new Date(d).toLocaleDateString('en-IN', {
              day: 'numeric',
              month: 'short',
              year: 'numeric'
            });
          } catch (e) {
            return d;
          }
        },
      },

      // ---- My Profile (Profile tab) ----
      profile: {
        loaded: false,
        loading: false,
        saving: false,
        uploadingPhoto: false,
        formError: '',
        savedAt: '',
        saveSuccess: false,
        form: null,
        phoneChange: {
          open: false,
          phoneDigits: '',
          code: '',
          sending: false,
          verifying: false,
          awaitingOtp: false,
          availabilityText: '',
          error: '',
          devCode: '',
          resendCountdown: 0,
          resendTimer: null,
        },
        photoVer: Date.now(), // cache-buster for the avatar <img>
        photoBusted: false, // set true if a stored photo fails to load
        bloods: ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'],

        async loadOnce() {
          if (this.loaded) return;
          await this.load();
        },
        async load() {
          this.loading = true;
          try {
            const r = await fetch('/api/patient_profile', {
              credentials: 'same-origin'
            });
            const j = await r.json();
            this.form = (j.ok && j.profile) ? j.profile : {};
            const digits = ((this.form.phone || '').replace(/\D/g, '') || '');
            this.phoneChange.phoneDigits = digits.startsWith('91') && digits.length === 12 ? digits.slice(2) : digits.slice(-10);
            this.loaded = true;
          } catch (e) {
            this.formError = 'Could not load your profile. Please try again.';
          } finally {
            this.loading = false;
          }
        },
        initials() {
          const n = (this.form && this.form.name || '').trim();
          if (!n) return '🙂';
          const p = n.split(/\s+/);
          return ((p[0][0] || '') + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
        },
        async save() {
          if (!this.form) return;
          if (!(this.form.name || '').trim()) {
            this.formError = 'Please enter your name.';
            return;
          }
          this.saving = true;
          this.formError = '';
          this.saveSuccess = false;
          // Send only editable fields — never the read-only/derived ones.
          const f = this.form;
          const payload = {
            name: f.name,
            preferred_name: f.preferred_name,
            dob: f.dob,
            gender: f.gender,
            blood_group: f.blood_group,
            veg_type: f.veg_type,
            phone_alt: f.phone_alt,
            email: f.email,
            address_line1: f.address_line1,
            address_line2: f.address_line2,
            address_city: f.address_city,
            address_state: f.address_state,
            address_postal_code: f.address_postal_code,
            address_country: f.address_country,
            emergency_contact_name: f.emergency_contact_name,
            emergency_contact_phone: f.emergency_contact_phone,
            emergency_contact_relation: f.emergency_contact_relation,
            allergies: f.allergies,
            chronic_conditions: f.chronic_conditions,
            abha_id: f.abha_id,
            health_policy_number: f.health_policy_number,
          };
          try {
            const r = await fetch('/api/patient_profile?action=save', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify(payload),
            });
            const j = await r.json();
            if (!j.ok) {
              this.formError = ({
                email_in_use: 'That email is already used by another account.',
                name_required: 'Please enter your name.',
              })[j.error] || 'Could not save. Please try again.';
              return;
            }
            if (j.profile) this.form = j.profile;
            this.savedAt = new Date().toLocaleTimeString('en-IN', {
              hour: 'numeric',
              minute: '2-digit'
            });
            this.saveSuccess = true;
            // Auto-dismiss the banner after a few seconds.
            setTimeout(() => {
              this.saveSuccess = false;
            }, 4000);
          } catch (e) {
            this.formError = 'Network error. Please try again.';
          } finally {
            this.saving = false;
          }
        },
        async checkPhoneAvailability() {
          const digits = (this.phoneChange.phoneDigits || '').replace(/\D/g, '');
          if (digits.length !== 10) {
            this.phoneChange.availabilityText = '';
            return true;
          }
          const current = ((this.form.phone || '').replace(/\D/g, '') || '').slice(-10);
          if (digits === current) {
            this.phoneChange.availabilityText = 'This is your current verified number.';
            return true;
          }
          try {
            const r = await fetch('/api/patient_profile?action=check_phone&phone=' + encodeURIComponent('+91' + digits), {
              credentials: 'same-origin',
            });
            const j = await r.json();
            if (j && j.ok && !j.available) {
              this.phoneChange.availabilityText = 'This number is already in use.';
              return false;
            }
            this.phoneChange.availabilityText = '';
            return true;
          } catch (e) {
            this.phoneChange.availabilityText = '';
            return true;
          }
        },
        openPhoneModal() {
          this.phoneChange.phoneDigits = '';
          this.phoneChange.code = '';
          this.phoneChange.awaitingOtp = false;
          this.phoneChange.availabilityText = '';
          this.phoneChange.error = '';
          this.phoneChange.devCode = '';
          this.phoneChange.open = true;
        },
        closePhoneModal() {
          this.phoneChange.open = false;
          this.phoneChange.error = '';
          if (this.phoneChange.resendTimer) {
            clearInterval(this.phoneChange.resendTimer);
            this.phoneChange.resendTimer = null;
          }
          this.phoneChange.resendCountdown = 0;
        },
        startResendCountdown(secs = 30) {
          if (this.phoneChange.resendTimer) {
            clearInterval(this.phoneChange.resendTimer);
            this.phoneChange.resendTimer = null;
          }
          this.phoneChange.resendCountdown = secs;
          this.phoneChange.resendTimer = setInterval(() => {
            this.phoneChange.resendCountdown -= 1;
            if (this.phoneChange.resendCountdown <= 0) {
              clearInterval(this.phoneChange.resendTimer);
              this.phoneChange.resendTimer = null;
              this.phoneChange.resendCountdown = 0;
            }
          }, 1000);
        },
        async sendPhoneOtp() {
          const okAvailable = await this.checkPhoneAvailability();
          if (!okAvailable) {
            this.phoneChange.error = 'This number is already in use. Please use another number.';
            return;
          }
          this.phoneChange.sending = true;
          this.phoneChange.error = '';
          this.formError = '';
          this.phoneChange.devCode = '';
          try {
            const r = await fetch('/api/patient_profile?action=send_phone_otp', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                phone: '+91' + this.phoneChange.phoneDigits
              }),
            });
            const j = await r.json();
            if (!j.ok) {
              this.phoneChange.error = ({
                invalid_phone: 'Please enter a valid phone number.',
                phone_in_use: 'This number is already in use.',
                otp_locked: j.retry_after ? `Too many OTP requests. Try again in ${Math.ceil(j.retry_after / 60)} minute(s).` : 'Too many OTP requests. Try again later.',
                resend_too_soon: j.retry_after ? `Please wait ${j.retry_after}s before requesting another OTP.` : 'Please wait before requesting another OTP.',
                whatsapp_not_configured: 'WhatsApp OTP is not configured.',
                wa_template_missing: 'WhatsApp OTP template is missing.',
                wa_template_unapproved: 'WhatsApp OTP template is not approved.',
                not_whatsapp: 'This number does not appear to have WhatsApp active.',
                wa_send_failed: 'Could not send WhatsApp OTP. Please try again.',
              })[j.error] || 'Could not send OTP. Please try again.';
              return;
            }
            this.phoneChange.awaitingOtp = true;
            this.phoneChange.code = '';
            this.phoneChange.devCode = j.dev_code || '';
            this.startResendCountdown(30);
          } catch (e) {
            this.formError = 'Network error. Please try again.';
          } finally {
            this.phoneChange.sending = false;
          }
        },
        async verifyPhoneOtp() {
          this.phoneChange.verifying = true;
          this.phoneChange.error = '';
          this.formError = '';
          try {
            const r = await fetch('/api/patient_profile?action=verify_phone_otp', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                phone: '+91' + this.phoneChange.phoneDigits,
                code: this.phoneChange.code
              }),
            });
            const j = await r.json();
            if (!j.ok) {
              this.phoneChange.error = ({
                invalid_input: 'Please enter a valid OTP.',
                invalid_code: 'That OTP is incorrect.',
                expired: 'OTP expired. Please send again.',
                no_code_issued: 'No active OTP. Please send again.',
                too_many_attempts: 'Too many attempts. Please request a new OTP.',
                phone_in_use: 'This number is already in use.',
              })[j.error] || 'Could not verify OTP.';
              return;
            }
            if (j.profile) {
              this.form = j.profile;
            }
            this.phoneChange.awaitingOtp = false;
            this.phoneChange.code = '';
            this.phoneChange.devCode = '';
            this.phoneChange.availabilityText = 'Primary phone updated and verified.';
            this.phoneChange.open = false;
            this.saveSuccess = true;
            setTimeout(() => {
              this.saveSuccess = false;
            }, 4000);
          } catch (e) {
            this.formError = 'Network error. Please try again.';
          } finally {
            this.phoneChange.verifying = false;
          }
        },
        async uploadPhoto(ev) {
          const file = ev.target.files && ev.target.files[0];
          ev.target.value = '';
          if (!file) return;
          this.uploadingPhoto = true;
          this.formError = '';
          try {
            const fd = new FormData();
            fd.append('photo', file);
            const r = await fetch('/api/patient_profile?action=photo', {
              method: 'POST',
              credentials: 'same-origin',
              body: fd,
            });
            const j = await r.json();
            if (!j.ok) {
              this.formError = ({
                file_too_large: 'That image is too large (max 4 MB).',
                file_type_not_allowed: 'Please upload a JPG, PNG or WebP image.',
              })[j.error] || 'Could not upload photo. Please try again.';
              return;
            }
            this.form.has_photo = true;
            this.photoBusted = false;
            this.photoVer = Date.now(); // force the profile-tab <img> to refetch
            // Update the hero avatar live too (header updates on next page load).
            this.$root.heroHasPhoto = true;
            this.$root.heroPhotoVer = Date.now();
          } catch (e) {
            this.formError = 'Network error while uploading. Please try again.';
          } finally {
            this.uploadingPhoto = false;
          }
        },
      },

      async init() {
        if (!this.loggedIn) {
          return;
        }
        await Promise.all([this.loadWishlist(), this.loadBookings()]);
      },

      async loadWishlist() {
        this.loading = true;
        try {
          const r = await fetch('/api/wishlist', {
            credentials: 'same-origin'
          });
          const j = await r.json();
          this.wishlist = j.ok ? (j.items || []) : [];
        } catch (e) {
          this.wishlist = [];
        } finally {
          this.loading = false;
        }
      },

      async loadBookings() {
        this.bookings.loading = true;
        try {
          const r = await fetch('/api/patient_bookings', {
            credentials: 'same-origin'
          });
          const j = await r.json();
          if (j.ok) {
            this.bookings.upcoming = j.upcoming || [];
            this.bookings.pending = j.pending_leads || [];
            this.bookings.past = j.past || [];
          }
        } catch (e) {
          /* keep empty */
        } finally {
          this.bookings.loading = false;
        }
      },

      // ---- date / status formatting helpers ----
      formatDay(iso) {
        try {
          return new Date(iso).getDate();
        } catch (e) {
          return '—';
        }
      },
      formatMon(iso) {
        try {
          return new Date(iso).toLocaleDateString('en-IN', {
            month: 'short'
          });
        } catch (e) {
          return '';
        }
      },
      // when_iso for pending leads is just a YYYY-MM-DD; parse it safely.
      formatDayFromDate(d) {
        if (!d) return '—';
        if (d.length === 10) d = d + 'T00:00';
        return this.formatDay(d);
      },
      formatMonFromDate(d) {
        if (!d) return '';
        if (d.length === 10) d = d + 'T00:00';
        return this.formatMon(d);
      },
      prettyStatus(s) {
        return ({
          scheduled: 'Scheduled',
          confirmed: 'Confirmed',
          in_progress: 'In progress',
          completed: 'Completed',
          cancelled: 'Cancelled',
          no_show: 'No-show',
        })[s] || s;
      },
      prettyLeadStatus(s) {
        return ({
          awaiting_clinic: 'Awaiting clinic',
          clinic_viewed: 'Clinic saw your request',
          delivery_failed: 'Could not reach clinic',
        })[s] || 'Pending';
      },

      async removeFromWishlist(id) {
        // Optimistic: drop locally first, then call API.
        const prev = this.wishlist;
        this.wishlist = this.wishlist.filter(d => d.id !== id);
        try {
          await fetch('/api/wishlist?action=remove', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              doctor_id: id
            }),
          });
        } catch (e) {
          // Rollback on network failure.
          this.wishlist = prev;
        }
      },

      async signOut() {
        try {
          await fetch('/api/patient_auth?action=logout', {
            method: 'POST',
            credentials: 'same-origin',
          });
        } catch (e) {}
        try {
          localStorage.removeItem('ecp_patient');
        } catch (e) {}
        try {
          window.dispatchEvent(new StorageEvent('storage', {
            key: 'ecp_patient'
          }));
        } catch (e) {}
        location.reload();
      },
    };
  }
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>