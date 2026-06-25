<?php
/** @var array<string, mixed> $bookingCtx */
$doctor = $bookingCtx['leadDoctor'] ?? [];
$leadDays = is_array($bookingCtx['days'] ?? null) ? $bookingCtx['days'] : [];
if ($leadDays === [] && function_exists('ecp_book_build_week_days')) {
    $leadDays = ecp_book_build_week_days(7);
}
$doctorJson = htmlspecialchars(json_encode($doctor, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
$leadDaysJson = htmlspecialchars(json_encode($leadDays, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
?>
<div class="dp-lead-widget" x-data="ecpProfileLeadBook(<?= $doctorJson ?>, <?= $leadDaysJson ?>)" x-init="init()">
    <div class="dp-lead-widget-head">
        <h2>Book appointment</h2>
        <p><?= e($doctor['name'] ?? '') ?><?php if (!empty($doctor['area']) || !empty($doctor['city'])): ?> · <?= e(trim(($doctor['area'] ?? '') !== '' ? $doctor['area'] : ($doctor['city'] ?? ''))) ?><?php endif; ?></p>
    </div>

    <div class="dp-lead-widget-body" x-show="!done">
        <form class="auth-form" @submit.prevent="submit()" style="padding:0;">
            <label>
                <span class="lbl">Preferred date</span>
                <div class="lb-date-strip">
                    <?php foreach ($leadDays as $i => $d): ?>
                    <?php
                    $iso = (string) ($d['date'] ?? '');
                    $dowLabel = $i === 0 ? 'Today' : ($i === 1 ? 'Tom' : (string) ($d['weekday'] ?? ''));
                    ?>
                    <button type="button"
                            @click="form.preferred_date = '<?= htmlspecialchars($iso, ENT_QUOTES) ?>'"
                            :class="form.preferred_date === '<?= htmlspecialchars($iso, ENT_QUOTES) ?>' ? 'is-active' : ''"
                            class="lb-date">
                        <span class="lb-dow"><?= htmlspecialchars($dowLabel) ?></span>
                        <span class="lb-day"><?= (int) ($d['day'] ?? 0) ?></span>
                        <span class="lb-mon"><?= htmlspecialchars((string) ($d['month'] ?? '')) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </label>

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
                <p class="auth-hint" style="margin-top:6px;">The clinic will confirm the exact slot when they call you.</p>
            </label>

            <label>
                <span class="lbl">Reason for visit <em>(optional)</em></span>
                <input type="text" x-model="form.reason" maxlength="200"
                       :disabled="busy" placeholder="e.g. Routine check-up">
            </label>

            <template x-if="patient">
                <div class="lb-as-you">
                    Booking as <strong x-text="patient.first_name || patient.name"></strong>
                    · <span x-text="patient.phone || patient.handle"></span>
                </div>
            </template>
            <template x-if="!patient">
                <p class="auth-hint">Sign in to send your booking request.</p>
            </template>

            <p class="auth-error" x-show="errorMsg" x-text="errorMsg"></p>

            <button type="submit" class="auth-btn primary dp-btn-book"
                    :disabled="busy || !form.preferred_date || !form.preferred_time">
                <span x-show="!busy" x-text="patient ? 'Send booking request' : 'Sign in to book'"></span>
                <span x-show="busy">Sending…</span>
            </button>

            <p class="auth-tos">🔒 We share only your name and phone with the clinic.</p>
        </form>
    </div>

    <div class="dp-lead-widget-body lb-done" x-show="done" x-cloak>
        <div class="lb-done-icon">✓</div>
        <h3>You're all set</h3>
        <p x-text="resultMsg"></p>
        <div class="lb-done-summary" x-show="form.preferred_date">
            <div><span class="lb-done-label">Doctor</span><strong x-text="doctor ? doctor.name : ''"></strong></div>
            <div><span class="lb-done-label">When</span><strong x-text="formatDate(form.preferred_date) + ', ' + formatTime(form.preferred_time)"></strong></div>
        </div>
        <button type="button" class="auth-btn primary" @click="resetForm()">Book another</button>
    </div>
</div>

<script>
function ecpProfileLeadBook(doctor, initialDays) {
  const normalizeDays = (rows) => {
    if (!Array.isArray(rows) || rows.length === 0) {
      const monthShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const dowShort   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      const today = new Date();
      const out = [];
      for (let i = 0; i < 7; i++) {
        const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + i);
        const iso = d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');
        out.push({
          iso,
          dow: i === 0 ? 'Today' : (i === 1 ? 'Tom' : dowShort[d.getDay()]),
          day: d.getDate(),
          mon: monthShort[d.getMonth()],
        });
      }
      return out;
    }
    return rows.map((row, i) => ({
      iso: String(row.date ?? row.iso ?? ''),
      dow: i === 0 ? 'Today' : (i === 1 ? 'Tom' : String(row.weekday ?? row.dow ?? '')),
      day: Number(row.day ?? 0),
      mon: String(row.month ?? row.mon ?? ''),
    })).filter((row) => row.iso !== '');
  };

  return {
    doctor,
    patient: window.ECP_PATIENT || null,
    busy: false,
    done: false,
    errorMsg: '',
    resultMsg: '',
    days: normalizeDays(initialDays),
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
      this.patient = window.ECP_PATIENT || null;
    },

    resetForm() {
      this.done = false;
      this.errorMsg = '';
      this.form = { preferred_date: '', preferred_time: '', reason: '' };
      this.patient = window.ECP_PATIENT || null;
    },

    async submit() {
      if (!this.form.preferred_date || !this.form.preferred_time) return;

      if (!this.patient) {
        if (window.ecpAuth) {
          window.ecpAuth.require('book', () => {
            this.patient = window.ECP_PATIENT || null;
            if (this.patient) this.submit();
          });
        }
        return;
      }

      this.busy = true;
      this.errorMsg = '';
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
          if (window.ecpAuth) window.ecpAuth.require('book', () => {
            this.patient = window.ECP_PATIENT || null;
            this.submit();
          });
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
