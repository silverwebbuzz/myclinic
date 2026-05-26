<?php
// =====================================================================
// patient.php — patient panel.
// Reads the logged-in identity server-side (from the ecp_pid cookie).
// Wishlist is fetched from /api/wishlist after page load so we keep
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

      <!-- 2-column layout: tabbed main + coming-soon sidebar -->
      <div class="pt-grid">

        <div class="pt-section pt-section-tabbed">

          <!-- Tab switcher -->
          <div class="pt-tabbar" role="tablist">
            <button type="button" role="tab"
                    :class="tab === 'bookings' ? 'is-active' : ''"
                    @click="tab = 'bookings'">
              📅 My bookings
              <span class="pt-tab-count" x-show="bookings.upcoming.length + bookings.pending.length > 0"
                    x-text="bookings.upcoming.length + bookings.pending.length"></span>
            </button>
            <button type="button" role="tab"
                    :class="tab === 'shortlist' ? 'is-active' : ''"
                    @click="tab = 'shortlist'">
              ❤️ Shortlist
              <span class="pt-tab-count" x-show="wishlist.length > 0" x-text="wishlist.length + '/5'"></span>
            </button>
          </div>

          <!-- ============ BOOKINGS TAB ============ -->
          <div x-show="tab === 'bookings'" class="pt-tab-pane">

            <!-- Loading -->
            <div x-show="bookings.loading" class="pt-loading">Loading your bookings…</div>

            <!-- Upcoming -->
            <template x-if="!bookings.loading && bookings.upcoming.length > 0">
              <div>
                <div class="pt-section-head"><h3>Upcoming</h3></div>
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
                <div class="pt-section-head"><h3>Pending requests</h3></div>
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
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                    </button>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- Coming soon sidebar -->
        <aside class="pt-soon">
          <h3>Coming soon</h3>
          <ul>
            <li>
              <span class="ic">💊</span>
              <div><b>E-prescriptions</b><span>From any visited clinic</span></div>
            </li>
            <li>
              <span class="ic">🧪</span>
              <div><b>Lab reports</b><span>All your results in one place</span></div>
            </li>
            <li>
              <span class="ic">👨‍👩‍👧</span>
              <div><b>Family profiles</b><span>Manage kids &amp; parents</span></div>
            </li>
            <li>
              <span class="ic">🩺</span>
              <div><b>Video consult</b><span>Talk to a doctor from home</span></div>
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

/* -------- Tabs (Bookings / Shortlist) -------- */
.pt-section-tabbed { padding: 0; }
.pt-tabbar {
  display: flex;
  border-bottom: 1px solid var(--line);
  padding: 6px 12px 0;
  gap: 4px;
}
.pt-tabbar button {
  display: inline-flex; align-items: center; gap: 6px;
  background: none; border: 0;
  padding: 12px 16px;
  font: inherit; font-size: 14px; font-weight: 600;
  color: var(--mute); cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: color .15s, border-color .15s;
}
.pt-tabbar button:hover { color: var(--ink-2); }
.pt-tabbar button.is-active {
  color: var(--ink);
  border-bottom-color: var(--teal-600);
}
.pt-tab-count {
  font-size: 11px; font-weight: 700;
  background: var(--bg-2); color: var(--ink-2);
  padding: 2px 7px; border-radius: 999px;
  min-width: 18px; text-align: center;
}
.pt-tabbar button.is-active .pt-tab-count {
  background: var(--teal-50); color: var(--teal-800);
}
.pt-tab-pane { padding: 22px 24px 24px; }

.pt-section-head h3 {
  font-size: 14px; font-weight: 700;
  color: var(--mute);
  letter-spacing: 0.04em; text-transform: uppercase;
  margin: 0;
}
.pt-section-note {
  font-size: 12.5px; color: var(--mute);
  margin: 0 0 10px;
}
.pt-link-btn {
  background: none; border: 0;
  font: inherit; font-size: 12.5px; font-weight: 600;
  color: var(--teal-700); cursor: pointer;
}
.pt-link-btn:hover { text-decoration: underline; }

.pt-loading {
  text-align: center; padding: 28px 16px;
  color: var(--mute); font-size: 13.5px;
}

/* -------- Booking rows -------- */
.pt-booking {
  display: flex; align-items: center; gap: 14px;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px;
  transition: border-color .15s, box-shadow .15s;
}
.pt-booking:hover {
  border-color: var(--teal-300, #a3d9c4);
  box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.pt-booking-confirmed { border-left: 4px solid var(--teal-600); }
.pt-booking-pending   { border-left: 4px solid #f59e0b; }
.pt-booking-past      { opacity: 0.85; }

.pt-booking-date {
  display: flex; flex-direction: column; align-items: center;
  width: 64px; flex-shrink: 0;
  text-align: center; line-height: 1;
}
.pt-date-day { font-size: 22px; font-weight: 700; color: var(--ink); letter-spacing: -0.5px; }
.pt-date-mon { font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--mute); margin-top: 2px; }
.pt-date-time { font-size: 11.5px; font-weight: 600; color: var(--ink-2); margin-top: 6px; white-space: nowrap; }

.pt-booking-body { flex: 1; min-width: 0; }
.pt-booking-doctor { font-size: 14.5px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pt-booking-clinic { font-size: 12.5px; color: var(--mute); margin-top: 2px; }
.pt-booking-reason { font-size: 12px; color: var(--ink-2); margin-top: 4px; font-style: italic; }
.pt-booking-token {
  display: inline-block;
  margin-top: 4px;
  background: var(--teal-50); color: var(--teal-800);
  padding: 2px 8px; border-radius: 6px;
  font-size: 11px; font-weight: 600;
}
.pt-booking-token strong { font-size: 13px; }

.pt-booking-actions {
  display: flex; flex-direction: column; align-items: flex-end; gap: 6px;
  flex-shrink: 0;
}
.pt-status {
  font-size: 10.5px; font-weight: 700;
  letter-spacing: 0.04em; text-transform: uppercase;
  padding: 4px 9px; border-radius: 999px;
  white-space: nowrap;
}
.pt-status-confirmed { background: var(--teal-50); color: var(--teal-800); }
.pt-status-pending   { background: #fff7e0;       color: #875c00; }
.pt-status-past      { background: var(--bg-2);   color: var(--mute); }

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
  .pt-section-tabbed { padding: 0; }
  .pt-tab-pane { padding: 16px; }
  .pt-tabbar { padding: 4px 8px 0; }
  .pt-tabbar button { padding: 12px 10px; font-size: 13px; }
  .pt-row { padding: 12px; }
  .pt-row-actions .btn-mini { padding: 8px 10px; }
  .pt-booking { gap: 10px; padding: 12px; }
  .pt-booking-date { width: 52px; }
  .pt-date-day { font-size: 20px; }
  .pt-booking-actions { flex-direction: row; }
}
</style>

<script>
function patientPanel(isLoggedIn) {
  return {
    loggedIn: !!isLoggedIn,
    tab: 'bookings',       // 'bookings' | 'shortlist'
    wishlist: [],
    loading: false,
    bookings: {
      upcoming: [],
      pending:  [],
      past:     [],
      loading:  true,
      pastOpen: false,
    },

    async init() {
      if (!this.loggedIn) return;
      // Load both in parallel — they're independent.
      await Promise.all([this.loadWishlist(), this.loadBookings()]);
    },

    async loadWishlist() {
      this.loading = true;
      try {
        const r = await fetch('/api/wishlist', { credentials: 'same-origin' });
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
        const r = await fetch('/api/patient_bookings', { credentials: 'same-origin' });
        const j = await r.json();
        if (j.ok) {
          this.bookings.upcoming = j.upcoming      || [];
          this.bookings.pending  = j.pending_leads || [];
          this.bookings.past     = j.past          || [];
        }
      } catch (e) { /* keep empty */ }
      finally { this.bookings.loading = false; }
    },

    // ---- date / status formatting helpers ----
    formatDay(iso) {
      try { return new Date(iso).getDate(); } catch (e) { return '—'; }
    },
    formatMon(iso) {
      try { return new Date(iso).toLocaleDateString('en-IN', { month: 'short' }); }
      catch (e) { return ''; }
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
        scheduled:   'Scheduled',
        confirmed:   'Confirmed',
        in_progress: 'In progress',
        completed:   'Completed',
        cancelled:   'Cancelled',
        no_show:     'No-show',
      })[s] || s;
    },
    prettyLeadStatus(s) {
      return ({
        awaiting_clinic: 'Awaiting clinic',
        clinic_viewed:   'Clinic saw your request',
        delivery_failed: 'Could not reach clinic',
      })[s] || 'Pending';
    },

    async removeFromWishlist(id) {
      // Optimistic: drop locally first, then call API.
      const prev = this.wishlist;
      this.wishlist = this.wishlist.filter(d => d.id !== id);
      try {
        await fetch('/api/wishlist?action=remove', {
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
        await fetch('/api/patient_auth?action=logout', {
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
