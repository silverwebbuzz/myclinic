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

        <!-- Left: value proposition + feature list -->
        <div class="pt-hero-copy">
          <span class="pt-eyebrow">Your free patient account</span>
          <h1>Everything about your health, in one place</h1>
          <p class="pt-hero-lede">
            Sign in with just your mobile number — no password to remember — and get:
          </p>
          <ul class="pt-feat-list">
            <li><span class="pt-feat-ic">✅</span><div><b>Prescriptions</b><span>Every e-prescription saved & searchable</span></div></li>
            <li><span class="pt-feat-ic">✅</span><div><b>Reports</b><span>Lab reports and results in one vault</span></div></li>
            <li><span class="pt-feat-ic">✅</span><div><b>Family Profiles</b><span>Manage health for up to 6 members</span></div></li>
            <li><span class="pt-feat-ic">✅</span><div><b>Bookings</b><span>Track upcoming & past appointments</span></div></li>
            <li><span class="pt-feat-ic">✅</span><div><b>Favourite Doctors</b><span>Shortlist doctors for quick access</span></div></li>
            <li><span class="pt-feat-ic">✅</span><div><b>Follow-up Reminders</b><span>Never miss a follow-up visit</span></div></li>
            <li><span class="pt-feat-ic">✅</span><div><b>Medicine Reminders</b><span>Timely nudges to take your meds</span></div></li>
            <li><span class="pt-feat-ic">✅</span><div><b>Appointment Reminders</b><span>Alerts before every appointment</span></div></li>
          </ul>
        </div>

        <!-- Right: sign-in card -->
        <div class="pt-card pt-card-signin">
          <div class="pt-card-head">
            <h2>My Health</h2>
            <p class="lede">Free forever. Set up in under a minute.</p>
          </div>
          <button type="button" class="btn btn-primary pt-cta-signin"
                  @click="window.ecpAuth.open('default')">
            Sign in with mobile number
          </button>
          <p class="pt-hint" style="text-align:center; margin-top:14px;">
            One-time code via WhatsApp message. No password to remember.
          </p>
          <div class="pt-trust">
            <span>🔒 Private & secure</span>
            <span>🇮🇳 ABHA-ready</span>
          </div>
        </div>

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
                      <option value="">—</option><option value="M">Male</option>
                      <option value="F">Female</option><option value="Other">Other</option>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Blood group</span>
                    <select x-model="family.form.blood_group">
                      <option value="">—</option>
                      <template x-for="b in family.bloods" :key="b"><option :value="b" x-text="b"></option></template>
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
                      <option value="">—</option><option value="M">Male</option>
                      <option value="F">Female</option><option value="Other">Other</option>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Blood group</span>
                    <select x-model="profile.form.blood_group">
                      <option value="">—</option>
                      <template x-for="b in profile.bloods" :key="b"><option :value="b" x-text="b"></option></template>
                    </select>
                  </label>
                  <label class="pt-fld"><span>Food preference</span>
                    <select x-model="profile.form.veg_type">
                      <option value="">—</option><option value="veg">Vegetarian</option>
                      <option value="nonveg">Non-vegetarian</option><option value="eggetarian">Eggetarian</option>
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
                <span class="ic">🧪</span>
                <div><b>Lab reports</b><span>All your results in one place</span></div>
              </li>
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
.pt-hero .wrap, .pt-main .wrap { max-width: 980px; margin: 0 auto; padding: 0 24px; }

/* -------- Logged-out (features showcase + signin card) -------- */
.pt-hero .wrap { max-width: 1040px; padding-top: 24px; }
.pt-hero-split {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
  gap: 40px;
  align-items: center;
}

/* Left column: copy + feature list */
.pt-hero-copy { min-width: 0; }
.pt-eyebrow {
  display: inline-block;
  font-size: 11px; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase;
  color: var(--teal-700);
  background: var(--teal-50);
  padding: 5px 12px; border-radius: 999px;
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
  font-size: 15.5px; line-height: 1.5;
  margin: 0 0 22px;
  max-width: 460px;
}
.pt-feat-list {
  list-style: none; padding: 0; margin: 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 24px;
}
.pt-feat-list li { display: flex; gap: 11px; align-items: flex-start; }
.pt-feat-ic { font-size: 15px; line-height: 1.3; flex-shrink: 0; }
.pt-feat-list li b { display: block; font-weight: 600; font-size: 14.5px; color: var(--ink); }
.pt-feat-list li span { display: block; font-size: 12.5px; color: var(--mute); margin-top: 2px; line-height: 1.4; }

.pt-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 36px 36px 32px;
  box-shadow: 0 18px 48px rgba(0,0,0,0.05);
}
.pt-card-signin { position: sticky; top: 100px; }
.pt-card-head h1, .pt-card-head h2 {
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
.pt-trust {
  display: flex; justify-content: center; gap: 18px;
  margin-top: 20px; padding-top: 18px;
  border-top: 1px solid var(--line);
}
.pt-trust span { font-size: 12px; font-weight: 600; color: var(--mute); }

/* Stack the split on narrower screens */
@media (max-width: 860px) {
  .pt-hero-split { grid-template-columns: 1fr; gap: 28px; }
  .pt-card-signin { position: static; order: -1; }
  .pt-hero-copy h1 { font-size: clamp(24px, 6vw, 32px); }
}
@media (max-width: 480px) {
  .pt-feat-list { grid-template-columns: 1fr; gap: 13px; }
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
  overflow: hidden;
}
.pt-bigavatar-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block; }
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

/* -------- 2-column grid: main content + nav sidebar -------- */
.pt-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 260px;
  gap: 16px;
  align-items: start;
}

.pt-section, .pt-soon {
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
  display: flex; align-items: center; gap: 11px;
  width: 100%;
  background: none; border: 0;
  padding: 11px 12px;
  border-radius: 11px;
  font: inherit; font-size: 14px; font-weight: 600;
  color: var(--ink-2); cursor: pointer;
  text-align: left;
  transition: background .15s, color .15s;
}
.pt-navmenu button:hover { background: var(--bg-2); color: var(--ink); }
.pt-navmenu button.is-active {
  background: var(--teal-50);
  color: var(--teal-800);
}
.pt-nav-ic { font-size: 16px; line-height: 1; width: 20px; text-align: center; flex-shrink: 0; }
.pt-nav-label { flex: 1 1 auto; }
.pt-tab-count {
  font-size: 11px; font-weight: 700;
  background: var(--bg-2); color: var(--ink-2);
  padding: 2px 7px; border-radius: 999px;
  min-width: 18px; text-align: center;
  flex-shrink: 0;
}
.pt-navmenu button.is-active .pt-tab-count {
  background: var(--teal-100, #cceee0); color: var(--teal-800);
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
.pt-fam-card { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: #fff; }
.pt-fam-head {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px;
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

.pt-fam-actions { display: flex; gap: 8px; margin-left: auto; flex-shrink: 0; }

/* Add/edit member form */
.pt-fam-edit { border: 1px solid var(--teal-400); border-radius: 14px; padding: 16px; margin: 14px 0; background: var(--teal-50, #f0faf6); }
.pt-fam-edit-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.pt-fld { display: flex; flex-direction: column; gap: 4px; flex: 1 1 150px; min-width: 0; }
.pt-fld-wide { flex-basis: 100%; }
.pt-fld > span { font-size: 11px; font-weight: 600; color: var(--mute); }
.pt-fld > span em { font-style: normal; color: var(--teal-700); font-weight: 500; }
.pt-fld input, .pt-fld select { border: 1px solid var(--line); border-radius: 9px; padding: 9px 11px; font: inherit; font-size: 14px; background: #fff; outline: none; width: 100%; }
.pt-fld input:focus, .pt-fld select:focus { border-color: var(--teal-400); }
.pt-fam-err { color: #c0392b; font-size: 13px; margin: 8px 0 0; }
.pt-fam-limit { color: #b45309; font-size: 13px; margin: 0 0 12px; }
.pt-fam-edit-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }

/* -------- My Profile -------- */
.pt-fld textarea { border: 1px solid var(--line); border-radius: 9px; padding: 9px 11px; font: inherit; font-size: 14px; background: #fff; outline: none; width: 100%; resize: vertical; }
.pt-fld textarea:focus { border-color: var(--teal-400); }
.pt-fld input:disabled { background: var(--bg-3, #f5f5f5); color: var(--mute); }
.pt-fld-note { font-size: 11px; color: var(--teal-700); font-style: normal; }
.pt-clickable-input { cursor: pointer; }
.pt-inline-actions { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.pt-otp-input { max-width: 150px; }
.pt-otp-box { display: grid; gap: 10px; margin-top: 6px; }
.pt-otp-sent { margin: 0; font-size: 12px; color: var(--ink-2); background: var(--bg-2); border-radius: 8px; padding: 8px 10px; }
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
.pt-modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,30,.45); display: grid; place-items: center; z-index: 1200; padding: 16px; }
.pt-modal-card { width: 100%; max-width: 520px; background: #fff; border-radius: 14px; padding: 16px; border: 1px solid var(--line); box-shadow: 0 20px 48px rgba(0,0,0,.22); position: relative; }
.pt-modal-close { position: absolute; top: 8px; right: 10px; border: 0; background: transparent; font-size: 22px; line-height: 1; color: var(--mute); cursor: pointer; }
.pt-modal-title { margin: 0; font-size: 20px; font-weight: 700; }
.pt-modal-sub { margin: 6px 0 12px; font-size: 13px; color: var(--mute); }
.pt-modal-btn { width: 100%; margin-top: 4px; }
.pt-phone-row { display: flex; align-items: center; border: 1px solid var(--line); border-radius: 9px; overflow: hidden; background: #fff; }
.pt-phone-row .pt-cc { padding: 9px 10px; border-right: 1px solid var(--line); color: var(--ink-2); background: var(--bg-3, #f5f5f5); font-size: 13px; font-weight: 600; }
.pt-phone-row input { border: 0; border-radius: 0; }
.pt-fld-wide { flex-basis: 320px; }
.pt-profile-group { font-size: 13px; font-weight: 700; color: var(--ink-2, #444); margin: 22px 0 8px; padding-bottom: 6px; border-bottom: 1px solid var(--line); }
.pt-profile-group:first-of-type { margin-top: 8px; }
.pt-profile-photo { display: flex; align-items: center; gap: 16px; margin-bottom: 4px; }
.pt-avatar-img { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 1px solid var(--line); }
.pt-photo-actions { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
.pt-photo-actions label { cursor: pointer; }
.pt-photo-hint { font-size: 11px; color: var(--mute); margin: 0; }
.pt-hidden-file { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
.pt-save-hint { font-size: 12px; color: var(--teal-700); }
.pt-save-ok {
  display: flex; align-items: center; gap: 8px;
  font-size: 14px; font-weight: 600; color: var(--teal-800);
  background: var(--teal-50); border: 1px solid var(--teal-100, #cceee0);
  border-radius: 10px; padding: 11px 14px; margin: 8px 0 0;
}

/* -------- Responsive -------- */
@media (max-width: 820px) {
  .pt-grid { grid-template-columns: 1fr; }
  /* Nav sidebar stacks ABOVE the content and stops sticking. */
  .pt-side { position: static; order: -1; }
  /* Nav becomes a horizontal, scrollable row so it doesn't eat height. */
  .pt-navmenu { flex-direction: row; overflow-x: auto; gap: 4px; padding: 6px; }
  .pt-navmenu button { flex-direction: column; gap: 4px; padding: 8px 12px; font-size: 12px; white-space: nowrap; }
  .pt-nav-ic { font-size: 18px; width: auto; }
  .pt-navmenu button .pt-tab-count { position: absolute; top: 4px; right: 6px; }
  .pt-navmenu button { position: relative; }
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
    // Hero avatar photo state — seeded from the server, updated live on upload.
    heroHasPhoto: <?= !empty($me['photo_path']) ? 'true' : 'false' ?>,
    heroPhotoVer: Date.now(),
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
      maxMembers: 6,
      canAddFlag: true,
      editing: false,
      saving: false,
      formError: '',
      form: {},
      relations: [
        { v: 'self', t: 'Self' }, { v: 'spouse', t: 'Spouse' },
        { v: 'mother', t: 'Mother' }, { v: 'father', t: 'Father' },
        { v: 'son', t: 'Son' }, { v: 'daughter', t: 'Daughter' },
        { v: 'guardian', t: 'Guardian' }, { v: 'other', t: 'Other' },
      ],
      bloods: ['A+','A-','B+','B-','O+','O-','AB+','AB-'],

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
          if (j.ok && j.max_members) this.maxMembers = j.max_members;
          if (j.ok && typeof j.can_add === 'boolean') this.canAddFlag = j.can_add;
        } catch (e) { this.members = []; }
        finally { this.loading = false; this.loaded = true; }
      },

      canAdd() {
        return this.canAddFlag && this.members.length < this.maxMembers;
      },

      relLabel(v) { return (this.relations.find(r => r.v === v) || {}).t || v; },
      genderLabel(v) {
        return ({ M: 'Male', F: 'Female', Other: 'Other' })[v] || v;
      },
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
        return { member_id: 0, relation: relation || 'other', name: '', dob: '', gender: '', blood_group: '', abha_id: '' };
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
          member_id: m.id, relation: m.relation, name: m.name || '', dob: m.dob || '',
          gender: m.gender || '', blood_group: m.blood_group || '', abha_id: m.abha_id || '',
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
          else {
            this.formError = j.error === 'member_limit_reached'
              ? 'You can add up to ' + this.maxMembers + ' family members only.'
              : ('Could not save. ' + (j.error || ''));
          }
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
          const r = await fetch('/api/patient_prescriptions', { credentials: 'same-origin' });
          const j = await r.json();
          this.items = j.ok ? (j.items || []) : [];
        } catch (e) { this.items = []; }
        finally { this.loading = false; this.loaded = true; }
      },

      blankForm() {
        return { family_member_id: '', label: '', doctor_name: '', issued_on: '', notes: '' };
      },
      startAdd() {
        this.formError = '';
        this.file = null;
        this.form = this.blankForm();
        this.adding = true;
        // Family members feed the "For" dropdown — make sure they're loaded.
        this.$data.family.loadOnce();
      },
      cancelAdd() { this.adding = false; this.formError = ''; this.file = null; },
      pickFile(e) { this.file = (e.target.files && e.target.files[0]) || null; },

      async save() {
        if (!this.form.label.trim()) { this.formError = 'Please enter a title.'; return; }
        this.saving = true; this.formError = '';
        try {
          const fd = new FormData();
          fd.append('label', this.form.label);
          if (this.form.family_member_id) fd.append('family_member_id', this.form.family_member_id);
          if (this.form.doctor_name) fd.append('doctor_name', this.form.doctor_name);
          if (this.form.issued_on) fd.append('issued_on', this.form.issued_on);
          if (this.form.notes) fd.append('notes', this.form.notes);
          if (this.file) fd.append('file', this.file);

          const r = await fetch('/api/patient_prescriptions?action=add', {
            method: 'POST', credentials: 'same-origin', body: fd,
          });
          const j = await r.json();
          if (j.ok) { this.adding = false; this.file = null; await this.load(); }
          else {
            this.formError = ({
              label_required: 'Please enter a title.',
              file_too_large: 'That file is too large (max ' + (j.max_mb || 10) + ' MB).',
              file_type_not_allowed: 'Only images or PDF files are allowed.',
              member_not_found: 'That family member could not be found.',
            })[j.error] || ('Could not save. ' + (j.error || ''));
          }
        } catch (e) { this.formError = 'Network error — please try again.'; }
        finally { this.saving = false; }
      },
      async remove(p) {
        if (!confirm('Remove "' + p.label + '" from your prescriptions?')) return;
        try {
          await fetch('/api/patient_prescriptions?action=remove', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: p.id }),
          });
          await this.load();
        } catch (e) {}
      },
      fmtDate(d) {
        try {
          return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) { return d; }
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
      photoVer: Date.now(),   // cache-buster for the avatar <img>
      photoBusted: false,     // set true if a stored photo fails to load
      bloods: ['A+','A-','B+','B-','O+','O-','AB+','AB-'],

      async loadOnce() {
        if (this.loaded) return;
        await this.load();
      },
      async load() {
        this.loading = true;
        try {
          const r = await fetch('/api/patient_profile', { credentials: 'same-origin' });
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
        if (!(this.form.name || '').trim()) { this.formError = 'Please enter your name.'; return; }
        this.saving = true;
        this.formError = '';
        this.saveSuccess = false;
        // Send only editable fields — never the read-only/derived ones.
        const f = this.form;
        const payload = {
          name: f.name, preferred_name: f.preferred_name,
          dob: f.dob, gender: f.gender, blood_group: f.blood_group, veg_type: f.veg_type,
          phone_alt: f.phone_alt, email: f.email,
          address_line1: f.address_line1, address_line2: f.address_line2,
          address_city: f.address_city, address_state: f.address_state,
          address_postal_code: f.address_postal_code, address_country: f.address_country,
          emergency_contact_name: f.emergency_contact_name,
          emergency_contact_phone: f.emergency_contact_phone,
          emergency_contact_relation: f.emergency_contact_relation,
          allergies: f.allergies, chronic_conditions: f.chronic_conditions,
          abha_id: f.abha_id, health_policy_number: f.health_policy_number,
        };
        try {
          const r = await fetch('/api/patient_profile?action=save', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
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
          this.savedAt = new Date().toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
          this.saveSuccess = true;
          // Auto-dismiss the banner after a few seconds.
          setTimeout(() => { this.saveSuccess = false; }, 4000);
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
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: '+91' + this.phoneChange.phoneDigits }),
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
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: '+91' + this.phoneChange.phoneDigits, code: this.phoneChange.code }),
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
          setTimeout(() => { this.saveSuccess = false; }, 4000);
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
            method: 'POST', credentials: 'same-origin', body: fd,
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
