<?php
// =====================================================================
// patient.php — patient panel.
// Reads the logged-in identity server-side (from the ecp_pid cookie).
// Wishlist is fetched from /api/wishlist.php after page load so we keep
// the initial HTML cacheable and small.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';

$pageTitle  = 'Patient panel — eClinicPro';
$metaDesc   = 'Save your shortlist of doctors and book faster next time.';
$activePage = '';

$me = ecp_patient_current();   // null when logged out

require __DIR__ . '/partials/header.php';
?>

<div x-data="patientPanel(<?= $me ? '1' : '0' ?>)" x-init="init()" x-cloak class="patient-page">

<?php if (!$me): ?>
  <!-- LOGGED-OUT VIEW: simple CTA that opens the shared auth modal -->
  <section class="pt-hero">
    <div class="wrap">
      <div class="pt-card">
        <div class="pt-card-head">
          <h1>Patient panel</h1>
          <p class="lede">Save up to <strong>5 doctors</strong> to your shortlist and access your prescriptions in one place.</p>
        </div>
        <button type="button" class="btn btn-primary pt-cta-signin"
                @click="window.ecpAuth.open('default')">
          Sign in with mobile number
        </button>
        <p class="pt-hint" style="text-align:center; margin-top:14px;">
          One-time code via SMS. No password to remember.
        </p>
      </div>
    </div>
  </section>
<?php else: ?>
  <!-- LOGGED-IN VIEW -->
  <section class="pt-main">
    <div class="wrap">

      <!-- Hero / profile strip -->
      <div class="pt-hero-strip">
        <div class="pt-hero-id">
          <div class="pt-bigavatar"><?= e(ecp_patient_initials($me)) ?></div>
          <div>
            <div class="pt-greet">Welcome back</div>
            <h1><?= e($me['name'] ?: 'Patient') ?></h1>
            <div class="pt-handle"><?= e($me['phone']) ?></div>
          </div>
        </div>
        <div class="pt-hero-actions">
          <a href="/find-a-doctor" class="btn btn-ghost">Find a doctor</a>
          <button type="button" class="btn btn-outline" @click="signOut()">Sign out</button>
        </div>
      </div>

      <!-- 2-column layout: shortlist + coming soon -->
      <div class="pt-grid">

        <!-- Shortlist -->
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
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                  </button>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Coming soon sidebar -->
        <aside class="pt-soon">
          <h3>Coming soon</h3>
          <ul>
            <li>
              <span class="ic">🔐</span>
              <div><b>OTP verification</b><span>SMS &amp; email codes</span></div>
            </li>
            <li>
              <span class="ic">📅</span>
              <div><b>Appointments</b><span>Bookings &amp; history</span></div>
            </li>
            <li>
              <span class="ic">💊</span>
              <div><b>E-prescriptions</b><span>From any visited clinic</span></div>
            </li>
            <li>
              <span class="ic">👨‍👩‍👧</span>
              <div><b>Family profiles</b><span>Manage kids &amp; parents</span></div>
            </li>
          </ul>
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
  padding: 40px 0 80px;
}
.pt-hero .wrap, .pt-main .wrap { max-width: 980px; margin: 0 auto; padding: 0 24px; }

/* -------- Logged-out (signup/signin card) -------- */
.pt-hero .wrap { max-width: 480px; padding-top: 24px; }
.pt-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 36px 36px 32px;
  box-shadow: 0 18px 48px rgba(0,0,0,0.05);
}
.pt-card-head h1 {
  font-size: clamp(24px, 3vw, 30px);
  font-weight: 500;
  letter-spacing: -0.5px;
  margin-bottom: 8px;
}
.pt-card-head .lede { color: var(--ink-2); font-size: 14.5px; margin-bottom: 22px; line-height: 1.5; }
.pt-card-head .lede strong { color: var(--ink); font-weight: 600; }
.pt-cta-signin {
  width: 100%;
  padding: 14px 18px;
  font-size: 15px; font-weight: 600;
  border-radius: 12px;
}

.pt-tabs { display: flex; border-bottom: 1px solid var(--line); margin-bottom: 22px; }
.pt-tabs button {
  background: none; border: 0;
  padding: 12px 4px; margin-right: 24px;
  font: inherit; font-size: 14px; font-weight: 600;
  color: var(--mute); cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}
.pt-tabs button.is-active { color: var(--ink); border-bottom-color: var(--teal-600); }

.pt-form { display: flex; flex-direction: column; gap: 14px; }
.pt-form label { display: flex; flex-direction: column; gap: 6px; }
.pt-form .lbl {
  font-size: 11px; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--mute);
}
.pt-form .lbl em {
  font-style: normal; color: var(--teal-700);
  text-transform: none; letter-spacing: normal; font-weight: 500;
}
.pt-form input {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 12px 14px;
  font: inherit; font-size: 15px;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.pt-form input:focus {
  border-color: var(--teal-400);
  box-shadow: 0 0 0 3px rgba(15,155,110,0.12);
}
.pt-form .pt-hint { font-size: 12.5px; color: var(--mute); margin: -2px 0 6px; line-height: 1.5; }
.pt-form .btn {
  padding: 13px 18px; font-size: 14.5px;
  font-weight: 600; border-radius: 11px;
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
  box-shadow: 0 6px 18px rgba(0,0,0,0.03);
}
.pt-hero-id { display: flex; align-items: center; gap: 18px; min-width: 0; }
.pt-bigavatar {
  width: 64px; height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--teal-400), var(--teal-700));
  color: #fff; display: grid; place-items: center;
  font-weight: 700; font-size: 24px;
  letter-spacing: -0.5px;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(15,155,110,0.20);
}
.pt-greet {
  font-size: 11px; font-weight: 600;
  letter-spacing: 0.08em; text-transform: uppercase;
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
.pt-handle { font-size: 13.5px; color: var(--mute); }

.pt-hero-actions { display: flex; gap: 8px; flex-shrink: 0; }
.btn-outline, .btn-ghost {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 9px 16px;
  border-radius: 10px;
  font: inherit; font-size: 13.5px; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: all .15s;
}
.btn-outline { background: #fff; border: 1px solid var(--line); color: var(--ink-2); }
.btn-outline:hover { border-color: var(--ink); color: var(--ink); }
.btn-ghost { background: var(--bg-2); border: 1px solid transparent; color: var(--ink-2); }
.btn-ghost:hover { background: var(--teal-50); color: var(--teal-700); }

/* -------- 2-column grid: shortlist + sidebar -------- */
.pt-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 280px;
  gap: 16px;
  align-items: start;
}

.pt-section, .pt-soon {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 22px 24px;
}
.pt-section-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 14px;
}
.pt-section-head h2 {
  font-size: 16px; font-weight: 600;
  letter-spacing: -0.3px; margin: 0;
}
.pt-counter {
  font-size: 12px; font-weight: 700;
  background: var(--teal-50); color: var(--teal-800);
  padding: 4px 11px; border-radius: 999px;
  letter-spacing: 0.02em;
}

/* Empty state */
.pt-empty { text-align: center; padding: 36px 16px 28px; }
.pt-empty .glyph {
  font-size: 36px; margin-bottom: 10px;
  filter: grayscale(0.3);
}
.pt-empty h3 { font-size: 16px; font-weight: 600; margin-bottom: 6px; }
.pt-empty p {
  font-size: 13.5px; color: var(--mute);
  margin: 0 auto 18px; max-width: 320px; line-height: 1.5;
}
.pt-empty .btn {
  display: inline-block;
  padding: 10px 22px;
  font-size: 13.5px; font-weight: 600;
  border-radius: 10px;
  text-decoration: none;
}

/* Wishlist rows */
.pt-list { display: flex; flex-direction: column; gap: 10px; }
.pt-row {
  display: flex; align-items: center; justify-content: space-between;
  gap: 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 12px 14px;
  transition: border-color .15s, box-shadow .15s;
}
.pt-row:hover {
  border-color: var(--teal-400);
  box-shadow: 0 4px 14px rgba(15,155,110,0.06);
}
.pt-row-id { display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1; }
.pt-row-text { min-width: 0; }
.pt-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, var(--teal-100), var(--teal-400));
  color: #fff; display: grid; place-items: center;
  font-weight: 700; font-size: 13px;
  flex-shrink: 0;
}
.pt-name {
  font-weight: 600; font-size: 14.5px;
  color: var(--ink);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pt-sub {
  font-size: 12.5px; color: var(--mute);
  margin-top: 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pt-row-actions { display: flex; gap: 6px; flex-shrink: 0; }
.btn-mini {
  border: 1px solid var(--line); background: #fff;
  padding: 7px 12px; border-radius: 8px;
  font: inherit; font-size: 12.5px; font-weight: 600;
  color: var(--ink-2); cursor: pointer; text-decoration: none;
  display: inline-flex; align-items: center; gap: 5px;
  transition: all .15s;
}
.btn-mini:hover { border-color: var(--ink); color: var(--ink); }
.btn-mini.primary {
  background: var(--teal-600); color: #fff;
  border-color: var(--teal-600);
}
.btn-mini.primary:hover {
  background: var(--teal-700); border-color: var(--teal-700);
}

/* Coming-soon sidebar */
.pt-soon h3 {
  font-size: 13px; font-weight: 600;
  color: var(--mute);
  letter-spacing: 0.06em; text-transform: uppercase;
  margin: 0 0 14px;
}
.pt-soon ul {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-direction: column; gap: 14px;
}
.pt-soon li {
  display: flex; gap: 12px; align-items: flex-start;
  font-size: 13px;
}
.pt-soon li .ic {
  width: 32px; height: 32px;
  border-radius: 9px;
  background: var(--bg-2);
  display: grid; place-items: center;
  font-size: 16px;
  flex-shrink: 0;
}
.pt-soon li b { display: block; font-weight: 600; color: var(--ink); font-size: 13.5px; }
.pt-soon li span { display: block; color: var(--mute); font-size: 12.5px; margin-top: 1px; }

/* -------- Responsive -------- */
@media (max-width: 820px) {
  .pt-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .patient-page { padding: 24px 0 60px; }
  .pt-card { padding: 28px 22px 24px; }
  .pt-hero-strip { padding: 20px; gap: 14px; }
  .pt-hero-id { gap: 14px; width: 100%; }
  .pt-bigavatar { width: 54px; height: 54px; font-size: 20px; }
  .pt-hero-actions { width: 100%; }
  .pt-hero-actions .btn-outline, .pt-hero-actions .btn-ghost { flex: 1; }
  .pt-section, .pt-soon { padding: 18px 16px; }
  .pt-row { padding: 12px; }
  .pt-row-actions .btn-mini { padding: 8px 10px; }
}
</style>

<script>
function patientPanel(isLoggedIn) {
  return {
    loggedIn: !!isLoggedIn,
    wishlist: [],
    loading: false,

    async init() {
      if (!this.loggedIn) return;
      await this.loadWishlist();
    },

    async loadWishlist() {
      this.loading = true;
      try {
        const r = await fetch('/api/wishlist.php', { credentials: 'same-origin' });
        const j = await r.json();
        this.wishlist = j.ok ? (j.items || []) : [];
      } catch (e) {
        this.wishlist = [];
      } finally {
        this.loading = false;
      }
    },

    async removeFromWishlist(id) {
      // Optimistic: drop locally first, then call API.
      const prev = this.wishlist;
      this.wishlist = this.wishlist.filter(d => d.id !== id);
      try {
        await fetch('/api/wishlist.php?action=remove', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ doctor_id: id }),
        });
      } catch (e) {
        // Rollback on network failure.
        this.wishlist = prev;
      }
    },

    async signOut() {
      try {
        await fetch('/api/patient_auth.php?action=logout', {
          method: 'POST', credentials: 'same-origin',
        });
      } catch (e) {}
      try { localStorage.removeItem('ecp_patient'); } catch (e) {}
      try { window.dispatchEvent(new StorageEvent('storage', { key: 'ecp_patient' })); } catch (e) {}
      location.reload();
    },
  };
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
