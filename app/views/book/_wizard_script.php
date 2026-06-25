<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('bookPatient', {
        loggedIn: <?= $patientLoggedIn ? 'true' : 'false' ?>,
        name: <?= json_encode((string) $patientName) ?>,
        firstName() {
            const n = (this.name || '').trim();
            if (!n) return 'Patient';
            return n.split(/\s+/)[0] || 'Patient';
        },
        initial() {
            const n = this.firstName();
            return n ? n.charAt(0).toUpperCase() : 'P';
        },
        setPatient(p) {
            this.loggedIn = true;
            this.name = p.name || p.first_name || '';
        },
        clear() {
            this.loggedIn = false;
            this.name = '';
        },
    });
});

function bookingWizard() {
    const bookApi = <?= json_encode($bookConfig, JSON_UNESCAPED_SLASHES) ?>;
    return {
        step: 1,
        doctorId: '<?= (int) ($doctorId ?? 0) ?>',
        selectedDate: '<?= htmlspecialchars($days[0]['date'] ?? date('Y-m-d')) ?>',
        selectedSlot: '',
        selectedSlotLabel: '',
        allSlots: [],
        morningSlots: [],
        eveningSlots: [],
        loadingSlots: false,
        slotMeta: null,
        loggedIn: <?= $patientLoggedIn ? 'true' : 'false' ?>,
        phone: <?= json_encode($patientPhoneDisplay) ?>,
        phoneRaw: <?= json_encode((string) $patientPhone) ?>,
        name: <?= json_encode((string) $patientName) ?>,
        submitting: false,
        // Inline patient auth (step 2)
        authStep: 'phone',
        authIntent: 'signin',
        authPhoneDigits: '',
        authCode: '',
        authName: '',
        authNameHint: '',
        authExists: false,
        authError: '',
        authDevCode: '',
        authBusy: false,

        init() {
            this.loadSlots();
            this.refreshSession();
        },

        displayPhoneFromE164(e164) {
            const d = String(e164 || '').replace(/\D/g, '');
            if (d.length >= 10) return d.slice(-10);
            return d;
        },

        async refreshSession() {
            try {
                const r = await fetch(bookApi.authMe, { credentials: 'same-origin' });
                const data = await r.json();
                if (data.ok && data.patient) {
                    this.applyPatient(data.patient);
                }
            } catch (e) { /* guest */ }
        },

        applyPatient(p) {
            this.loggedIn = true;
            this.name = p.name || '';
            this.phoneRaw = p.phone || '';
            this.phone = this.displayPhoneFromE164(p.phone);
            this.authStep = 'phone';
            this.authError = '';
            if (typeof Alpine !== 'undefined' && Alpine.store('bookPatient')) {
                Alpine.store('bookPatient').setPatient(p);
            }
        },

        async sendAuthOtp() {
            if (this.authPhoneDigits.length < 10) return;
            this.authBusy = true;
            this.authError = '';
            this.authDevCode = '';
            try {
                const r = await fetch(bookApi.authSendOtp, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        phone: this.authPhoneDigits,
                        intent: this.authIntent,
                    }),
                });
                const data = await r.json();
                if (!r.ok || !data.ok) {
                    if (data.error === 'account_not_found') {
                        this.authError = 'No account on this number. Switch to Create account.';
                    } else if (data.error === 'account_exists') {
                        this.authError = 'Account already exists. Switch to Sign in.';
                    } else {
                        this.authError = data.error || 'Could not send code';
                    }
                    return;
                }
                this.authExists = !!data.exists;
                this.authNameHint = data.name_hint || '';
                if (!this.authExists && this.authName) { /* keep typed name */ }
                this.authDevCode = data.dev_code || '';
                this.authStep = 'code';
            } catch (e) {
                this.authError = 'Network error — try again';
            } finally {
                this.authBusy = false;
            }
        },

        async verifyAuthOtp() {
            if (this.authCode.length < 6) return;
            this.authBusy = true;
            this.authError = '';
            try {
                const r = await fetch(bookApi.authVerifyOtp, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        phone: this.authPhoneDigits,
                        code: this.authCode,
                        name: this.authExists ? undefined : this.authName,
                    }),
                });
                const data = await r.json();
                if (!r.ok || !data.ok || !data.patient) {
                    this.authError = data.error === 'invalid_code' ? 'Wrong code — try again' : (data.error || 'Verification failed');
                    return;
                }
                this.applyPatient(data.patient);
            } catch (e) {
                this.authError = 'Network error — try again';
            } finally {
                this.authBusy = false;
            }
        },

        async logoutPatient() {
            try {
                await fetch(bookApi.authLogout, { method: 'POST', credentials: 'same-origin' });
            } catch (e) { /* ignore */ }
            this.loggedIn = false;
            this.name = '';
            this.phone = '';
            this.phoneRaw = '';
            this.authStep = 'phone';
            this.authPhoneDigits = '';
            this.authCode = '';
            this.authName = '';
            this.authDevCode = '';
            if (typeof Alpine !== 'undefined' && Alpine.store('bookPatient')) {
                Alpine.store('bookPatient').clear();
            }
        },

        selectDate(d) {
            this.selectedDate = d;
            this.selectedSlot = '';
            this.loadSlots();
        },

        goNext() {
            if (!this.selectedSlot) return;
            this.step = 2;
            this.refreshSession();
            if (window.innerWidth < 1024) {
                this.$el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },

        async loadSlots() {
            if (!this.doctorId || !this.selectedDate) return;
            this.loadingSlots = true;
            try {
                const r = await fetch(bookApi.slotsUrl + '?doctor_id=' + this.doctorId + '&date=' + this.selectedDate);
                const data = await r.json();
                const all = (data.slots || []).map(s => ({
                    ...s,
                    label: this._formatTime(s.time),
                    hour: parseInt(s.time.split(':')[0], 10),
                }));
                this.allSlots = all;
                this.morningSlots = all.filter(s => s.hour < 13);
                this.eveningSlots = all.filter(s => s.hour >= 13);
                this.slotMeta = data.meta || null;
                if (data.meta) {
                    console.debug('[book slots]', data.meta);
                }
            } catch (e) {
                this.allSlots = [];
                this.morningSlots = [];
                this.eveningSlots = [];
                this.slotMeta = null;
            } finally {
                this.loadingSlots = false;
            }
        },

        async lookupPatient() { /* legacy — booking now uses OTP auth */ },

        _formatTime(hhmm) {
            const [h, m] = hhmm.split(':').map(n => parseInt(n, 10));
            const period = h >= 12 ? 'PM' : 'AM';
            const h12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
            return h12 + ':' + String(m).padStart(2, '0') + ' ' + period;
        },

        formatDate(dStr) {
            try {
                const d = new Date(dStr + 'T00:00');
                return d.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' });
            } catch (e) { return dStr; }
        },
    };
}
</script>
