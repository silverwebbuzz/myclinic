<?php
// =====================================================================
// lead-book-modal.php — booking modal for UNCLAIMED doctors on
// find-a-doctor. (Claimed doctors send the user to the portal instead.)
//
// 7-day window: only today through +7 days are bookable.
//
// Opens via JS: window.ecpBook.open(doctorPayload)
//   - If logged out: relays through window.ecpAuth.require('book',cb)
//   - If logged in:  shows the date/slot picker → submits to /api/lead
// =====================================================================
?>

<div id="ecp-book-modal" x-data="ecpBookModal()" x-show="open" x-cloak
     @keydown.escape.window="close()"
     class="auth-overlay" @click.self="close()">
  <div class="auth-card" style="max-width: 460px;" role="dialog" aria-modal="true">
    <button type="button" class="auth-close" @click="close()" aria-label="Close">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    <div class="auth-head">
      <div class="auth-logo">e<em>ClinicPro</em></div>
      <h2 x-text="done ? 'Request sent ✓' : 'Book appointment'"></h2>
      <p class="auth-sub" x-show="!done"
         x-text="doctor ? (doctor.name + ' · ' + (doctor.area || doctor.city || '')) : ''"></p>
    </div>

    <!-- ============ Step 1: pick date + time + reason ============ -->
    <form class="auth-form" x-show="!done" @submit.prevent="submit()">

      <!-- Date strip — next 7 days -->
      <label>
        <span class="lbl">Preferred date</span>
        <div class="lb-date-strip">
          <template x-for="d in days" :key="d.iso">
            <button type="button"
                    @click="form.preferred_date = d.iso"
                    :class="form.preferred_date === d.iso ? 'is-active' : ''"
                    class="lb-date">
              <span class="lb-dow" x-text="d.dow"></span>
              <span class="lb-day" x-text="d.day"></span>
              <span class="lb-mon" x-text="d.mon"></span>
            </button>
          </template>
        </div>
      </label>

      <!-- Time slot grid -->
      <label>
        <span class="lbl">Preferred time</span>
        <div class="lb-time-grid">
          <template x-for="t in times" :key="t.value">
            <button type="button"
                    @click="form.preferred_time = t.value"
                    :class="form.preferred_time === t.value ? 'is-active' : ''"
                    class="lb-time" x-text="t.label"></button>
          </template>
        </div>
        <p class="auth-hint" style="margin-top:6px;">
          The clinic will confirm the exact slot when they call you.
        </p>
      </label>

      <label>
        <span class="lbl">Reason for visit <em>(optional)</em></span>
        <input type="text" x-model="form.reason" maxlength="200"
               :disabled="busy" placeholder="e.g. Routine check-up, dental cleaning">
      </label>

      <!-- Patient identity reminder -->
      <template x-if="patient">
        <div class="lb-as-you">
          Booking as <strong x-text="patient.first_name || patient.name"></strong>
          · <span x-text="patient.phone || patient.handle"></span>
        </div>
      </template>

      <p class="auth-error" x-show="errorMsg" x-text="errorMsg"></p>

      <button type="submit" class="auth-btn primary"
              :disabled="busy || !form.preferred_date || !form.preferred_time">
        <span x-show="!busy">Send booking request</span>
        <span x-show="busy">Sending…</span>
      </button>

      <p class="auth-tos">
        🔒 We share only your name and phone with the clinic.
      </p>
    </form>

    <!-- ============ Done state ============ -->
    <div x-show="done" x-cloak class="lb-done">
      <div class="lb-done-icon">✓</div>
      <h3>You're all set</h3>
      <p x-text="resultMsg"></p>
      <div class="lb-done-summary" x-show="form.preferred_date">
        <div><span class="lb-done-label">Doctor</span><strong x-text="doctor ? doctor.name : ''"></strong></div>
        <div><span class="lb-done-label">When</span><strong x-text="formatDate(form.preferred_date) + ', ' + formatTime(form.preferred_time)"></strong></div>
      </div>
      <button type="button" class="auth-btn primary" @click="close()">Close</button>
    </div>
  </div>
</div>

<style>
/* Reuses .auth-* styles from auth-modal.php; just adds booking-specific pieces */
.lb-date-strip {
  display: flex; gap: 8px; overflow-x: auto;
  padding: 4px 2px 6px;
  margin: 0 -2px;
  scrollbar-width: thin;
}
.lb-date {
  flex: 0 0 auto;
  min-width: 64px;
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 4px;
  border: 1.5px solid var(--line);
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  transition: all .15s;
}
.lb-date:hover { border-color: var(--teal-400); }
.lb-date.is-active {
  border-color: var(--teal-600);
  background: var(--teal-600);
  color: #fff;
  box-shadow: 0 4px 10px rgba(15,155,110,0.25);
}
.lb-dow { font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; opacity: 0.7; }
.lb-day { font-size: 22px; font-weight: 700; line-height: 1.1; margin-top: 2px; }
.lb-mon { font-size: 10px; opacity: 0.7; }

.lb-time-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
  margin-top: 4px;
}
.lb-time {
  background: #fff;
  border: 1.5px solid var(--line);
  border-radius: 10px;
  padding: 10px 4px;
  font: inherit; font-size: 13.5px; font-weight: 600;
  color: var(--ink-2);
  cursor: pointer;
  transition: all .15s;
}
.lb-time:hover { border-color: var(--teal-400); color: var(--teal-700); }
.lb-time.is-active {
  background: var(--teal-600); color: #fff;
  border-color: var(--teal-600);
  box-shadow: 0 4px 10px rgba(15,155,110,0.25);
}

.lb-as-you {
  background: var(--bg-2);
  padding: 9px 12px;
  border-radius: 9px;
  font-size: 12.5px;
  color: var(--ink-2);
}
.lb-as-you strong { color: var(--ink); font-weight: 600; }

.lb-done { text-align: center; padding: 12px 4px; }
.lb-done-icon {
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--teal-50);
  color: var(--teal-700);
  font-size: 30px; font-weight: 700;
  display: grid; place-items: center;
  margin: 0 auto 14px;
}
.lb-done h3 { font-size: 20px; font-weight: 600; margin-bottom: 8px; letter-spacing: -0.3px; }
.lb-done p { font-size: 14px; color: var(--ink-2); margin: 0 auto 14px; max-width: 320px; line-height: 1.5; }
.lb-done-summary {
  background: var(--bg-2);
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 16px;
  text-align: left;
}
.lb-done-summary > div {
  display: flex; justify-content: space-between;
  font-size: 13.5px;
  padding: 4px 0;
}
.lb-done-summary strong { font-weight: 600; color: var(--ink); }
.lb-done-label { color: var(--mute); }
.lb-done .auth-btn { width: 100%; }
</style>

<script>
function ecpBookModal() {
  return {
    open: false,
    busy: false,
    done: false,
    doctor: null,           // { id, name, area, city, ... }
    patient: window.ECP_PATIENT || null,
    errorMsg: '',
    resultMsg: '',
    days: [],
    times: [
      { value: '09:00', label: '9:00 AM' },
      { value: '10:00', label: '10:00 AM' },
      { value: '11:00', label: '11:00 AM' },
      { value: '12:00', label: '12:00 PM' },
      { value: '15:00', label: '3:00 PM' },
      { value: '16:00', label: '4:00 PM' },
      { value: '17:00', label: '5:00 PM' },
      { value: '18:00', label: '6:00 PM' },
      { value: '19:00', label: '7:00 PM' },
    ],
    form: { preferred_date: '', preferred_time: '', reason: '' },

    init() {
      // Build the next-7-days strip once.
      const monthShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const dowShort   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      const today = new Date();
      for (let i = 0; i < 7; i++) {
        const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + i);
        const iso = d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');
        this.days.push({
          iso,
          dow: i === 0 ? 'Today' : (i === 1 ? 'Tom' : dowShort[d.getDay()]),
          day: d.getDate(),
          mon: monthShort[d.getMonth()],
        });
      }

      // Expose tiny global API.
      window.ecpBook = {
        open: (doctor) => this.openForDoctor(doctor),
        close: () => this.close(),
      };
    },

    openForDoctor(doctor) {
      this.doctor = doctor;
      this.done = false;
      this.errorMsg = '';
      this.form = { preferred_date: '', preferred_time: '', reason: '' };
      // Refresh "current patient" snapshot from header bootstrap.
      this.patient = window.ECP_PATIENT || null;
      this.open = true;
      document.body.style.overflow = 'hidden';
    },

    close() {
      this.open = false;
      document.body.style.overflow = '';
    },

    async submit() {
      if (!this.doctor || !this.form.preferred_date || !this.form.preferred_time) return;

      this.busy = true; this.errorMsg = '';
      try {
        const r = await fetch('/api/lead?action=submit', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            doctor_id:      this.doctor.id,
            preferred_date: this.form.preferred_date,
            preferred_time: this.form.preferred_time,
            reason:         this.form.reason || '',
          }),
        });
        const j = await r.json();

        if (r.status === 401) {
          // Session expired mid-flow → reopen login.
          this.close();
          if (window.ecpAuth) window.ecpAuth.open('book');
          return;
        }
        if (!j.ok) {
          this.errorMsg = this.errorText(j.error);
          return;
        }
        this.done = true;
        this.resultMsg = j.message || 'Your booking request was sent.';
      } catch (e) {
        this.errorMsg = "Couldn't reach the server. Please try again.";
      } finally {
        this.busy = false;
      }
    },

    errorText(code) {
      return {
        date_required:       'Please pick a date.',
        time_required:       'Please pick a time.',
        date_in_past:        "That date is already past — please pick a future date.",
        date_out_of_window:  "Only the next 7 days are bookable.",
        doctor_id_required:  'Something went wrong identifying the doctor.',
        doctor_not_found:    'This clinic is no longer listed.',
        too_many_requests:   "You've sent a lot of bookings recently. Try again in an hour.",
        login_required:      'Please sign in to continue.',
      }[code] || 'Something went wrong. Please try again.';
    },

    formatDate(iso) {
      if (!iso) return '';
      const d = new Date(iso + 'T00:00');
      return d.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' });
    },
    formatTime(hhmm) {
      if (!hhmm) return '';
      const [h, m] = hhmm.split(':').map(n => parseInt(n, 10));
      const ampm = h >= 12 ? 'PM' : 'AM';
      const h12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
      return h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
    },
  };
}
</script>
