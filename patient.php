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

$pageTitle  = 'Patient panel — eClinicPro';
$metaDesc   = 'Save your shortlist of doctors and book faster next time.';
$activePage = '';
$noindex    = true;                  // private — don't index logged-in/empty state

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
            <button type="button" role="tab"
                    :class="tab === 'family' ? 'is-active' : ''"
                    @click="tab = 'family'; family.loadOnce()">
              👨‍👩‍👧 Family
              <span class="pt-tab-count" x-show="family.members.length > 0" x-text="family.members.length"></span>
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

          <!-- ============ FAMILY TAB ============ -->
          <div x-show="tab === 'family'" class="pt-tab-pane">
            <div class="pt-section-head">
              <h3>Family profiles</h3>
              <button type="button" class="btn-mini primary" @click="family.startAdd()">+ Add member</button>
            </div>
            <p class="pt-section-note">
              Keep your family's health details, emergency contacts, insurance &amp; documents in one place — handy during an emergency.
            </p>

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
                      <option value="">—</option><option value="M">Male</option>
                      <option value="F">Female</option><option value="Other">Other</option>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Blood group</span>
                    <select x-model="family.form.blood_group">
                      <option value="">—</option>
                      <template x-for="b in family.bloods" :key="b"><option x-text="b"></option></template>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Mobile <em>(optional)</em></span>
                    <input type="tel" x-model="family.form.phone" placeholder="If they have one" inputmode="numeric">
                  </label>
                </div>

                <div class="pt-fam-edit-grid">
                  <label class="pt-fld pt-fld-wide"><span>Allergies</span>
                    <input type="text" x-model="family.form.allergies" placeholder="e.g. penicillin, peanuts">
                  </label>
                  <label class="pt-fld pt-fld-wide"><span>Chronic conditions</span>
                    <input type="text" x-model="family.form.chronic_conditions" placeholder="e.g. diabetes, asthma">
                  </label>
                </div>

                <div class="pt-fam-edit-grid">
                  <label class="pt-fld"><span>Emergency contact</span>
                    <input type="text" x-model="family.form.emergency_contact_name" placeholder="Name">
                  </label>
                  <label class="pt-fld"><span>Emergency phone</span>
                    <input type="tel" x-model="family.form.emergency_contact_phone" placeholder="Mobile" inputmode="numeric">
                  </label>
                  <label class="pt-fld"><span>ABHA ID <em>(optional)</em></span>
                    <input type="text" x-model="family.form.abha_id" placeholder="14-digit ABHA number">
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
                <div class="pt-fam-card" :class="family.openId === m.id ? 'is-open' : ''">
                  <button type="button" class="pt-fam-head" @click="family.toggle(m.id)">
                    <span class="pt-fam-avatar" x-text="family.initials(m.name)"></span>
                    <span class="pt-fam-id">
                      <span class="pt-fam-name" x-text="m.name"></span>
                      <span class="pt-fam-rel">
                        <span x-text="family.relLabel(m.relation)"></span>
                        <template x-if="m.dob"><span x-text="' · ' + family.age(m.dob) + ' yrs'"></span></template>
                        <template x-if="m.blood_group"><span class="pt-fam-blood" x-text="m.blood_group"></span></template>
                      </span>
                    </span>
                    <span class="pt-fam-chevron" aria-hidden="true">▾</span>
                  </button>

                  <div class="pt-fam-body" x-show="family.openId === m.id" x-cloak>
                    <!-- summary rows -->
                    <dl class="pt-fam-facts">
                      <template x-if="m.phone"><div><dt>Mobile</dt><dd x-text="m.phone"></dd></div></template>
                      <template x-if="m.allergies"><div><dt>Allergies</dt><dd x-text="m.allergies"></dd></div></template>
                      <template x-if="m.chronic_conditions"><div><dt>Conditions</dt><dd x-text="m.chronic_conditions"></dd></div></template>
                      <template x-if="m.emergency_contact_name"><div><dt>Emergency</dt><dd x-text="m.emergency_contact_name + (m.emergency_contact_phone ? ' · ' + m.emergency_contact_phone : '')"></dd></div></template>
                      <template x-if="m.abha_id"><div><dt>ABHA</dt><dd x-text="m.abha_id"></dd></div></template>
                    </dl>

                    <!-- insurance -->
                    <div class="pt-fam-sub">
                      <div class="pt-fam-sub-head">
                        <h4>🛡️ Insurance policies</h4>
                        <button type="button" class="pt-link-btn" @click="family.addPolicy(m.id)">+ Add policy</button>
                      </div>
                      <template x-if="m.policies.length === 0">
                        <p class="pt-fam-empty">No policies added.</p>
                      </template>
                      <template x-for="p in m.policies" :key="p.id">
                        <div class="pt-fam-policy">
                          <div>
                            <strong x-text="p.insurer_name || 'Policy'"></strong>
                            <span class="pt-fam-tag" x-text="family.policyLabel(p.policy_type)"></span>
                            <div class="pt-fam-policy-sub">
                              <span x-show="p.policy_number" x-text="'No. ' + p.policy_number"></span>
                              <span x-show="p.sum_insured_inr" x-text="' · ₹' + Number(p.sum_insured_inr).toLocaleString('en-IN') + ' cover'"></span>
                              <span x-show="p.valid_till" x-text="' · valid till ' + p.valid_till"></span>
                            </div>
                          </div>
                          <button type="button" class="btn-mini" @click="family.deletePolicy(m.id, p.id)" aria-label="Remove policy">✕</button>
                        </div>
                      </template>

                      <!-- inline policy form -->
                      <template x-if="family.policyFor === m.id">
                        <div class="pt-fam-policy-form">
                          <input type="text" placeholder="Insurer" x-model="family.policyForm.insurer_name">
                          <select x-model="family.policyForm.policy_type">
                            <template x-for="t in family.policyTypes" :key="t.v"><option :value="t.v" x-text="t.t"></option></template>
                          </select>
                          <input type="text" placeholder="Policy number" x-model="family.policyForm.policy_number">
                          <input type="number" placeholder="Sum insured ₹" x-model="family.policyForm.sum_insured_inr">
                          <input type="date" x-model="family.policyForm.valid_till">
                          <button type="button" class="btn-mini primary" @click="family.savePolicy()">Save</button>
                          <button type="button" class="btn-mini" @click="family.policyFor = null">Cancel</button>
                        </div>
                      </template>
                    </div>

                    <!-- documents -->
                    <div class="pt-fam-sub">
                      <div class="pt-fam-sub-head">
                        <h4>📄 Documents</h4>
                        <label class="pt-link-btn pt-fam-upload">
                          + Upload
                          <input type="file" accept="image/*,application/pdf" hidden
                                 @change="family.uploadDoc(m.id, $event)">
                        </label>
                      </div>
                      <p class="pt-fam-hint">ABHA card, insurance card, vaccine certificate, lab report… (image or PDF, max 5 MB).</p>
                      <template x-if="m.documents.length === 0">
                        <p class="pt-fam-empty">No documents uploaded.</p>
                      </template>
                      <div class="pt-fam-docs" x-show="m.documents.length > 0">
                        <template x-for="d in m.documents" :key="d.id">
                          <div class="pt-fam-doc">
                            <a :href="'/api/family_doc?id=' + d.id" target="_blank" rel="noopener" class="pt-fam-doc-link">
                              <span class="pt-fam-doc-ic" x-text="family.docIcon(d.mime_type)"></span>
                              <span class="pt-fam-doc-meta">
                                <span class="pt-fam-doc-title" x-text="d.title"></span>
                                <span class="pt-fam-doc-type" x-text="family.docLabel(d.doc_type)"></span>
                              </span>
                            </a>
                            <button type="button" class="btn-mini" @click="family.deleteDoc(m.id, d.id)" aria-label="Delete document">✕</button>
                          </div>
                        </template>
                      </div>
                    </div>

                    <!-- member actions -->
                    <div class="pt-fam-actions">
                      <button type="button" class="btn-mini" @click="family.startEdit(m)">✎ Edit details</button>
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
                <button type="button" class="btn btn-primary" @click="family.startAdd()">+ Add a member</button>
              </div>
            </template>
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
  padding: 120px 0 80px;
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

/* -------- Family profiles -------- */
.pt-fam-list { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; }
.pt-fam-card { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; transition: border-color .15s, box-shadow .15s; }
.pt-fam-card.is-open { border-color: var(--teal-400); box-shadow: 0 4px 16px rgba(15,155,110,0.07); }
.pt-fam-head {
  width: 100%; display: flex; align-items: center; gap: 12px;
  background: #fff; border: 0; padding: 14px 16px; cursor: pointer; text-align: left;
}
.pt-fam-avatar {
  width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg, var(--teal-100), var(--teal-400));
  color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 13px;
}
.pt-fam-id { flex: 1; min-width: 0; }
.pt-fam-name { display: block; font-weight: 600; font-size: 14.5px; color: var(--ink); }
.pt-fam-rel { display: block; font-size: 12.5px; color: var(--mute); margin-top: 2px; }
.pt-fam-blood { background: var(--teal-50); color: var(--teal-800); padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 700; margin-left: 6px; }
.pt-fam-chevron { color: var(--mute); transition: transform .2s; font-size: 13px; }
.pt-fam-card.is-open .pt-fam-chevron { transform: rotate(180deg); }

.pt-fam-body { padding: 4px 16px 18px; border-top: 1px solid var(--line); }
.pt-fam-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 18px; margin: 14px 0 4px; }
.pt-fam-facts > div { min-width: 0; }
.pt-fam-facts dt { font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--mute); }
.pt-fam-facts dd { margin: 2px 0 0; font-size: 13.5px; color: var(--ink); }

.pt-fam-sub { margin-top: 16px; border-top: 1px dashed var(--line); padding-top: 12px; }
.pt-fam-sub-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.pt-fam-sub-head h4 { margin: 0; font-size: 13px; font-weight: 700; color: var(--ink); }
.pt-fam-hint { font-size: 11.5px; color: var(--mute); margin: 0 0 8px; }
.pt-fam-empty { font-size: 12.5px; color: var(--mute); margin: 4px 0; }

.pt-fam-policy { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
.pt-fam-policy strong { font-size: 13.5px; }
.pt-fam-tag { display: inline-block; background: var(--bg-2); color: var(--ink-2); font-size: 10.5px; font-weight: 700; padding: 1px 7px; border-radius: 999px; margin-left: 6px; }
.pt-fam-policy-sub { font-size: 12px; color: var(--mute); margin-top: 3px; }
.pt-fam-policy-form, .pt-fam-edit-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.pt-fam-policy-form input, .pt-fam-policy-form select {
  border: 1px solid var(--line); border-radius: 9px; padding: 8px 10px; font: inherit; font-size: 13px; flex: 1 1 130px; min-width: 0;
}

.pt-fam-docs { display: flex; flex-direction: column; gap: 6px; }
.pt-fam-doc { display: flex; align-items: center; justify-content: space-between; gap: 8px; border: 1px solid var(--line); border-radius: 10px; padding: 8px 10px; }
.pt-fam-doc-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--ink); min-width: 0; flex: 1; }
.pt-fam-doc-ic { font-size: 20px; flex-shrink: 0; }
.pt-fam-doc-meta { min-width: 0; }
.pt-fam-doc-title { display: block; font-size: 13.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pt-fam-doc-type { display: block; font-size: 11.5px; color: var(--mute); }
.pt-fam-upload { cursor: pointer; }

.pt-fam-actions { display: flex; gap: 8px; margin-top: 16px; }

/* Add/edit member form */
.pt-fam-edit { border: 1px solid var(--teal-400); border-radius: 14px; padding: 16px; margin: 14px 0; background: var(--teal-50, #f0faf6); }
.pt-fld { display: flex; flex-direction: column; gap: 4px; flex: 1 1 150px; min-width: 0; }
.pt-fld-wide { flex-basis: 100%; }
.pt-fld > span { font-size: 11px; font-weight: 600; color: var(--mute); }
.pt-fld > span em { font-style: normal; color: var(--teal-700); font-weight: 500; }
.pt-fld input, .pt-fld select { border: 1px solid var(--line); border-radius: 9px; padding: 9px 11px; font: inherit; font-size: 14px; background: #fff; outline: none; width: 100%; }
.pt-fld input:focus, .pt-fld select:focus { border-color: var(--teal-400); }
.pt-fam-err { color: #c0392b; font-size: 13px; margin: 8px 0 0; }
.pt-fam-edit-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }

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
  .pt-fam-facts { grid-template-columns: 1fr; }
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

    // ---- Family profiles (Family tab) ----
    family: {
      loaded: false,
      loading: false,
      members: [],
      openId: null,
      // add/edit member
      editing: false,
      saving: false,
      formError: '',
      form: {},
      // inline insurance form
      policyFor: null,
      policyForm: {},
      // static option lists
      relations: [
        { v: 'self', t: 'Self' }, { v: 'spouse', t: 'Spouse' },
        { v: 'mother', t: 'Mother' }, { v: 'father', t: 'Father' },
        { v: 'son', t: 'Son' }, { v: 'daughter', t: 'Daughter' },
        { v: 'guardian', t: 'Guardian' }, { v: 'other', t: 'Other' },
      ],
      bloods: ['A+','A-','B+','B-','O+','O-','AB+','AB-'],
      policyTypes: [
        { v: 'health', t: 'Health' }, { v: 'topup', t: 'Top-up' },
        { v: 'personal_accident', t: 'Personal accident' },
        { v: 'critical_illness', t: 'Critical illness' }, { v: 'other', t: 'Other' },
      ],

      async loadOnce() {
        if (this.loaded) return;
        await this.load();
      },
      async load() {
        this.loading = true;
        try {
          const r = await fetch('/api/family', { credentials: 'same-origin' });
          const j = await r.json();
          this.members = j.ok ? (j.members || []) : [];
        } catch (e) { this.members = []; }
        finally { this.loading = false; this.loaded = true; }
      },
      toggle(id) { this.openId = this.openId === id ? null : id; },

      // ---- labels / formatting ----
      relLabel(v) { return (this.relations.find(r => r.v === v) || {}).t || v; },
      policyLabel(v) { return (this.policyTypes.find(t => t.v === v) || {}).t || v; },
      docLabel(v) {
        return ({ abha: 'ABHA card', insurance_card: 'Insurance card', id_photo: 'ID photo',
          prescription: 'Prescription', lab_report: 'Lab report', vaccine_cert: 'Vaccine certificate',
          other: 'Document' })[v] || 'Document';
      },
      docIcon(mime) { return (mime || '').indexOf('pdf') >= 0 ? '📕' : '🖼️'; },
      initials(name) {
        const p = (name || '').trim().split(/\s+/);
        return ((p[0] || '')[0] || '?').toUpperCase() + (p.length > 1 ? (p[p.length-1][0] || '').toUpperCase() : '');
      },
      age(dob) {
        try {
          const d = new Date(dob), n = new Date();
          let a = n.getFullYear() - d.getFullYear();
          if (n.getMonth() < d.getMonth() || (n.getMonth() === d.getMonth() && n.getDate() < d.getDate())) a--;
          return a;
        } catch (e) { return ''; }
      },

      // ---- add / edit member ----
      blankForm(relation) {
        return { member_id: 0, relation: relation || 'other', name: '', dob: '', gender: '',
          blood_group: '', phone: '', allergies: '', chronic_conditions: '',
          emergency_contact_name: '', emergency_contact_phone: '', abha_id: '' };
      },
      startAdd() { this.formError = ''; this.form = this.blankForm('spouse'); this.editing = true; },
      startEdit(m) {
        this.formError = '';
        this.form = {
          member_id: m.id, relation: m.relation, name: m.name || '', dob: m.dob || '',
          gender: m.gender || '', blood_group: m.blood_group || '', phone: m.phone || '',
          allergies: m.allergies || '', chronic_conditions: m.chronic_conditions || '',
          emergency_contact_name: m.emergency_contact_name || '',
          emergency_contact_phone: m.emergency_contact_phone || '', abha_id: m.abha_id || '',
        };
        this.editing = true;
      },
      cancelEdit() { this.editing = false; this.formError = ''; },
      async saveMember() {
        if (!this.form.name.trim()) { this.formError = 'Please enter a name.'; return; }
        this.saving = true; this.formError = '';
        try {
          const r = await fetch('/api/family?action=save_member', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(this.form),
          });
          const j = await r.json();
          if (j.ok) { this.editing = false; await this.load(); }
          else { this.formError = 'Could not save. ' + (j.error || ''); }
        } catch (e) { this.formError = 'Network error — please try again.'; }
        finally { this.saving = false; }
      },
      async removeMember(m) {
        if (!confirm('Remove ' + m.name + ' from your family list?')) return;
        try {
          await fetch('/api/family?action=remove_member', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ member_id: m.id }),
          });
          if (this.openId === m.id) this.openId = null;
          await this.load();
        } catch (e) {}
      },

      // ---- insurance ----
      addPolicy(memberId) {
        this.policyFor = memberId;
        this.policyForm = { member_id: memberId, policy_id: 0, insurer_name: '', policy_type: 'health',
          policy_number: '', sum_insured_inr: '', valid_till: '' };
      },
      async savePolicy() {
        try {
          const r = await fetch('/api/family?action=save_policy', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(this.policyForm),
          });
          const j = await r.json();
          if (j.ok) { this.policyFor = null; await this.load(); }
        } catch (e) {}
      },
      async deletePolicy(memberId, policyId) {
        if (!confirm('Delete this policy?')) return;
        try {
          await fetch('/api/family?action=delete_policy', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ policy_id: policyId }),
          });
          await this.load();
        } catch (e) {}
      },

      // ---- documents ----
      async uploadDoc(memberId, ev) {
        const file = ev.target.files && ev.target.files[0];
        ev.target.value = '';
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) { alert('File is larger than 5 MB.'); return; }
        const fd = new FormData();
        fd.append('member_id', memberId);
        fd.append('file', file);
        fd.append('title', file.name.replace(/\.[^.]+$/, ''));
        try {
          const r = await fetch('/api/family_doc', { method: 'POST', credentials: 'same-origin', body: fd });
          const j = await r.json();
          if (j.ok) await this.load();
          else alert('Upload failed: ' + (j.error || 'unknown error'));
        } catch (e) { alert('Upload failed — please try again.'); }
      },
      async deleteDoc(memberId, docId) {
        if (!confirm('Delete this document?')) return;
        const fd = new FormData(); fd.append('id', docId);
        try {
          await fetch('/api/family_doc?action=delete', { method: 'POST', credentials: 'same-origin', body: fd });
          await this.load();
        } catch (e) {}
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
