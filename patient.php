<?php
// =====================================================================
// patient.php — patient panel stub
// Login / register (name + phone OR email), wishlist of up to 5 doctors.
// Persistence is browser-only (localStorage) for now — DB-backed auth and
// OTP verification land in a follow-up.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'Patient panel — eClinicPro';
$metaDesc   = 'Save your shortlist of doctors and book faster next time.';
$activePage = '';

require __DIR__ . '/partials/header.php';
?>

<div x-data="patientPanel()" x-init="init()" x-cloak class="patient-page">

  <!-- LOGGED-OUT VIEW -->
  <section class="pt-hero" x-show="!user">
    <div class="wrap">
      <div class="pt-card">
        <div class="pt-card-head">
          <h1>Patient panel</h1>
          <p class="lede">Save up to <strong>5 doctors</strong> to your shortlist. Sign up with just your phone or email — no password needed.</p>
        </div>

        <!-- Tabs -->
        <div class="pt-tabs" role="tablist">
          <button type="button" :class="tab === 'login' ? 'is-active' : ''" @click="tab = 'login'">Sign in</button>
          <button type="button" :class="tab === 'register' ? 'is-active' : ''" @click="tab = 'register'">Create account</button>
        </div>

        <!-- Sign in -->
        <form class="pt-form" x-show="tab === 'login'" @submit.prevent="signIn()">
          <label>
            <span class="lbl">Phone or email</span>
            <input type="text" x-model="form.handle" required placeholder="e.g. 9824012345 or you@example.com">
          </label>
          <p class="pt-hint">We'll send you a one-time code (coming soon — for now any handle works).</p>
          <button type="submit" class="btn btn-primary">Continue</button>
        </form>

        <!-- Register -->
        <form class="pt-form" x-show="tab === 'register'" @submit.prevent="register()">
          <label>
            <span class="lbl">Your name</span>
            <input type="text" x-model="form.name" required placeholder="Riya Mehta">
          </label>
          <label>
            <span class="lbl">Phone <em>or</em> email — one is enough</span>
            <input type="text" x-model="form.handle" required placeholder="9824012345 or you@example.com">
          </label>
          <p class="pt-hint">By creating an account you agree to our terms. Verification by OTP is coming soon.</p>
          <button type="submit" class="btn btn-primary">Create account</button>
        </form>
      </div>
    </div>
  </section>

  <!-- LOGGED-IN VIEW -->
  <section class="pt-main" x-show="user">
    <div class="wrap">
      <div class="pt-top">
        <div>
          <div class="pt-greet">Welcome back,</div>
          <h1 x-text="user && user.name ? user.name : 'Patient'"></h1>
          <p class="lede" x-text="user && user.handle ? user.handle : ''"></p>
        </div>
        <button type="button" class="pt-signout" @click="signOut()">Sign out</button>
      </div>

      <div class="pt-section">
        <div class="pt-section-head">
          <h2>Your shortlist</h2>
          <span class="pt-counter">
            <span x-text="wishlist.length"></span> / 5
          </span>
        </div>

        <template x-if="wishlist.length === 0">
          <div class="pt-empty">
            <div class="glyph">🤍</div>
            <h3>No doctors saved yet</h3>
            <p>Tap the heart on any doctor in <a href="/find-a-doctor">Find a doctor</a> to add them here.</p>
            <a href="/find-a-doctor" class="btn btn-primary">Browse doctors</a>
          </div>
        </template>

        <div class="pt-list" x-show="wishlist.length > 0">
          <template x-for="d in wishlist" :key="d.id">
            <div class="pt-row">
              <div class="pt-row-id">
                <div class="pt-avatar" x-text="(d.firstInitial || '') + (d.lastInitial || '')"></div>
                <div>
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
                <button type="button" class="btn-mini" @click="removeFromWishlist(d.id)">Remove</button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div class="pt-soon">
        <h3>Coming soon</h3>
        <ul>
          <li>OTP verification (phone &amp; email)</li>
          <li>Appointment history &amp; upcoming bookings</li>
          <li>Health records &amp; e-prescriptions</li>
          <li>Family member profiles</li>
        </ul>
      </div>
    </div>
  </section>
</div>

<style>
.patient-page { background: var(--bg-3, #fafafa); min-height: calc(100vh - 80px); padding: 80px 0 90px; }
.pt-hero .wrap, .pt-main .wrap { max-width: 760px; margin: 0 auto; padding: 0 24px; }

.pt-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 36px 36px 32px;
  box-shadow: 0 18px 48px rgba(0,0,0,0.05);
}
.pt-card-head h1 {
  font-size: clamp(26px, 3.2vw, 34px);
  font-weight: 300;
  letter-spacing: -0.6px;
  margin-bottom: 8px;
}
.pt-card-head .lede { color: var(--ink-2); font-size: 15px; margin-bottom: 24px; }

.pt-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--line); margin-bottom: 22px; }
.pt-tabs button {
  background: none; border: 0;
  padding: 12px 4px; margin-right: 24px;
  font: inherit; font-size: 14px; font-weight: 600;
  color: var(--mute); cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}
.pt-tabs button.is-active { color: var(--ink); border-bottom-color: var(--teal-600); }

.pt-form { display: flex; flex-direction: column; gap: 16px; }
.pt-form label { display: flex; flex-direction: column; gap: 6px; }
.pt-form .lbl { font-size: 12px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: var(--mute); }
.pt-form .lbl em { font-style: normal; color: var(--teal-700); text-transform: none; letter-spacing: normal; font-weight: 500; }
.pt-form input {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 12px 14px;
  font: inherit; font-size: 15px;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.pt-form input:focus { border-color: var(--teal-400); box-shadow: 0 0 0 3px rgba(15,155,110,0.12); }
.pt-form .pt-hint { font-size: 12.5px; color: var(--mute); margin: -4px 0 4px; }
.pt-form .btn { padding: 13px 18px; font-size: 14.5px; font-weight: 600; border-radius: 11px; }

/* Logged-in */
.pt-top {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 16px; margin-bottom: 28px; flex-wrap: wrap;
}
.pt-top h1 { font-size: clamp(24px, 3vw, 32px); font-weight: 400; letter-spacing: -0.5px; margin: 4px 0 6px; }
.pt-greet { font-size: 13px; color: var(--mute); letter-spacing: 0.04em; text-transform: uppercase; }
.pt-top .lede { color: var(--ink-2); font-size: 14px; }
.pt-signout {
  border: 1px solid var(--line); background: #fff;
  padding: 8px 14px; border-radius: 10px;
  font: inherit; font-size: 13px; font-weight: 500;
  color: var(--ink-2); cursor: pointer;
}
.pt-signout:hover { border-color: var(--ink); }

.pt-section {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 22px 24px;
  margin-bottom: 18px;
}
.pt-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.pt-section-head h2 { font-size: 17px; font-weight: 600; letter-spacing: -0.3px; }
.pt-counter {
  font-size: 12.5px; font-weight: 600;
  background: var(--teal-50); color: var(--teal-800);
  padding: 4px 10px; border-radius: 999px;
}

.pt-empty { text-align: center; padding: 36px 16px; }
.pt-empty .glyph { font-size: 38px; margin-bottom: 12px; }
.pt-empty h3 { font-size: 16px; font-weight: 600; margin-bottom: 6px; }
.pt-empty p { font-size: 13.5px; color: var(--mute); margin-bottom: 18px; }
.pt-empty a { color: var(--teal-700); text-decoration: underline; }
.pt-empty .btn { display: inline-block; padding: 10px 18px; font-size: 14px; border-radius: 10px; font-weight: 600; }

.pt-list { display: flex; flex-direction: column; gap: 10px; }
.pt-row {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px 16px;
}
.pt-row-id { display: flex; align-items: center; gap: 14px; min-width: 0; flex: 1; }
.pt-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  background: linear-gradient(135deg, var(--teal-100), var(--teal-400));
  color: #fff; display: grid; place-items: center;
  font-weight: 600; font-size: 15px;
  flex-shrink: 0;
}
.pt-name { font-weight: 600; font-size: 14.5px; color: var(--ink); }
.pt-sub { font-size: 12.5px; color: var(--mute); margin-top: 2px; }
.pt-row-actions { display: flex; gap: 6px; flex-shrink: 0; }
.btn-mini {
  border: 1px solid var(--line); background: #fff;
  padding: 7px 12px; border-radius: 8px;
  font: inherit; font-size: 12.5px; font-weight: 600;
  color: var(--ink-2); cursor: pointer; text-decoration: none;
  display: inline-flex; align-items: center; gap: 4px;
}
.btn-mini:hover { border-color: var(--ink); color: var(--ink); }
.btn-mini.primary { background: var(--teal-600); color: #fff; border-color: var(--teal-600); }
.btn-mini.primary:hover { background: var(--teal-700); border-color: var(--teal-700); }

.pt-soon {
  background: #fff;
  border: 1px dashed var(--line);
  border-radius: 16px;
  padding: 22px 24px;
}
.pt-soon h3 { font-size: 14px; font-weight: 600; margin-bottom: 10px; }
.pt-soon ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
.pt-soon li { font-size: 13px; color: var(--mute); padding-left: 18px; position: relative; }
.pt-soon li::before { content: '○'; position: absolute; left: 0; color: var(--teal-400); }

@media (max-width: 600px) {
  .pt-card { padding: 24px 20px; }
  .pt-row { flex-direction: column; align-items: stretch; }
  .pt-row-actions { width: 100%; }
  .pt-row-actions .btn-mini { flex: 1; justify-content: center; }
}
</style>

<script>
function patientPanel() {
  return {
    tab: 'login',
    user: null,
    wishlist: [],
    form: { name: '', handle: '' },

    init() {
      try {
        const u = localStorage.getItem('ecp_patient');
        if (u) this.user = JSON.parse(u);
        const w = localStorage.getItem('ecp_wishlist');
        if (w) this.wishlist = JSON.parse(w) || [];
      } catch (e) { /* corrupt storage — ignore */ }
    },

    register() {
      const handle = (this.form.handle || '').trim();
      const name = (this.form.name || '').trim();
      if (!name || !handle) return;
      this.user = { name, handle, joinedAt: Date.now() };
      localStorage.setItem('ecp_patient', JSON.stringify(this.user));
      this.form = { name: '', handle: '' };
    },

    signIn() {
      const handle = (this.form.handle || '').trim();
      if (!handle) return;
      // Try to load an existing profile by handle (matches register output);
      // otherwise create a thin one so the user can start saving doctors.
      let existing = null;
      try {
        const saved = localStorage.getItem('ecp_patient');
        if (saved) {
          const u = JSON.parse(saved);
          if (u && u.handle === handle) existing = u;
        }
      } catch (e) {}
      this.user = existing || { name: handle.split('@')[0] || 'Patient', handle, joinedAt: Date.now() };
      localStorage.setItem('ecp_patient', JSON.stringify(this.user));
      this.form = { name: '', handle: '' };
    },

    signOut() {
      this.user = null;
      localStorage.removeItem('ecp_patient');
    },

    removeFromWishlist(id) {
      this.wishlist = this.wishlist.filter(d => d.id !== id);
      localStorage.setItem('ecp_wishlist', JSON.stringify(this.wishlist));
    },
  };
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
