<?php
// =====================================================================
// demo-modal.php — "Schedule A Free Demo" lead modal for specialty pages.
// Open via: data-open-demo-modal on a link/button, or window.ecpDemo.open()
// =====================================================================
$demoDefaultSpecialty = $demoDefaultSpecialty ?? ($spec['label'] ?? 'General practice');
// Page specialty key (e.g. 'homeopathy') — used server-side to build a stable
// email subject like "homeopathy book a demo", independent of the editable field.
$demoSpecKey = $specKey ?? ($demoSpecKey ?? '');
?>
<div id="ecp-demo-modal" class="demo-modal-overlay" x-data="ecpDemoModal()" x-show="open" x-cloak
     @open-demo-modal.window="openModal()"
     @keydown.escape.window="close()"
     @click.self="close()">
    <div class="demo-modal-card" role="dialog" aria-modal="true" aria-labelledby="ecp-demo-title">
        <button type="button" class="demo-modal-close" @click="close()" aria-label="Close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <template x-if="success">
            <div class="demo-modal-success">
                <div class="demo-modal-success-ico" aria-hidden="true">✓</div>
                <h2 id="ecp-demo-title">Request received</h2>
                <p>Our sales team will call you within the next 12 hours.</p>
                <button type="button" class="demo-modal-submit" @click="close()">Close</button>
            </div>
        </template>

        <template x-if="!success">
            <div>
                <h2 id="ecp-demo-title" class="demo-modal-title">Schedule A Free Demo</h2>
                <p class="demo-modal-sub">Our Sales Team will Call you in next 12 hours.</p>

                <form class="demo-modal-form" @submit.prevent="submit()">
                    <label class="demo-modal-field">
                        <span class="demo-modal-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <circle cx="12" cy="8" r="4"/><path d="M4 20c0-3.3 3.6-6 8-6s8 2.7 8 6"/>
                            </svg>
                        </span>
                        <input type="text" name="name" x-model="name" required autocomplete="name"
                               placeholder="Doctor name" :disabled="busy">
                    </label>

                    <label class="demo-modal-field">
                        <span class="demo-modal-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                        </span>
                        <input type="tel" name="phone" x-model="phone" required inputmode="numeric"
                               autocomplete="tel-national" maxlength="10"
                               @input="phone = phone.replace(/\D/g, '').replace(/^0+/, '').slice(0, 10)"
                               placeholder="Contact no. without 0 and +91" :disabled="busy">
                    </label>

                    <label class="demo-modal-field">
                        <span class="demo-modal-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/>
                            </svg>
                        </span>
                        <input type="text" name="specialty" x-model="specialty" required
                               placeholder="Your Speciality" :disabled="busy">
                    </label>

                    <p class="demo-modal-error" x-show="errorMsg" x-text="errorMsg"></p>

                    <button type="submit" class="demo-modal-submit" :disabled="busy">
                        <span x-show="!busy">Request For Demo</span>
                        <span x-show="busy">Sending…</span>
                    </button>
                </form>
            </div>
        </template>
    </div>
</div>

<style>
.demo-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 10050;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(10, 10, 10, 0.45);
    backdrop-filter: blur(4px);
}
.demo-modal-card {
    position: relative;
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 16px;
    padding: 40px 36px 36px;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.18);
    text-align: center;
}
.demo-modal-close {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: var(--mute);
    transition: background .15s, color .15s;
}
.demo-modal-close:hover {
    background: var(--bg-2);
    color: var(--ink);
}
.demo-modal-title {
    font-size: clamp(22px, 4vw, 28px);
    font-weight: 600;
    color: #3d3d3d;
    letter-spacing: -0.3px;
    line-height: 1.25;
}
.demo-modal-sub {
    margin-top: 10px;
    font-size: 14px;
    line-height: 1.5;
    color: #888;
}
.demo-modal-form {
    margin-top: 28px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    text-align: left;
}
.demo-modal-field {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 16px;
    height: 52px;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
}
.demo-modal-field:focus-within {
    border-color: #1a6fc4;
    box-shadow: 0 0 0 3px rgba(26, 111, 196, 0.12);
}
.demo-modal-ico {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    color: #aaa;
    display: grid;
    place-items: center;
}
.demo-modal-ico svg { width: 18px; height: 18px; }
.demo-modal-field input {
    flex: 1;
    min-width: 0;
    border: none;
    background: transparent;
    font: inherit;
    font-size: 14px;
    color: var(--ink);
    outline: none;
}
.demo-modal-field input::placeholder { color: #b0b0b0; }
.demo-modal-error {
    font-size: 13px;
    color: #c62828;
    text-align: center;
    margin: 0;
}
.demo-modal-submit {
    margin-top: 8px;
    align-self: center;
    min-width: 200px;
    padding: 14px 32px;
    border-radius: 12px;
    border: 2px solid #1a6fc4;
    background: #fff;
    color: #1a6fc4;
    font-size: 16px;
    font-weight: 700;
    transition: background .15s, color .15s, transform .15s;
}
.demo-modal-submit:hover:not(:disabled) {
    background: #1a6fc4;
    color: #fff;
    transform: translateY(-1px);
}
.demo-modal-submit:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
.demo-modal-success-ico {
    width: 56px;
    height: 56px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: var(--teal-50);
    color: var(--teal-700);
    display: grid;
    place-items: center;
    font-size: 24px;
    font-weight: 700;
}
.demo-modal-success h2 {
    font-size: 22px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
}
.demo-modal-success p {
    font-size: 14px;
    color: var(--mute);
    line-height: 1.55;
    margin-bottom: 20px;
}
@media (max-width: 480px) {
    .demo-modal-card { padding: 32px 22px 28px; }
    .demo-modal-submit { width: 100%; }
}
</style>

<script>
function ecpDemoModal() {
    return {
        open: false,
        success: false,
        busy: false,
        errorMsg: '',
        name: '',
        phone: '',
        specialty: <?= json_encode($demoDefaultSpecialty, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        defaultSpecialty: <?= json_encode($demoDefaultSpecialty, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        specKey: <?= json_encode($demoSpecKey, JSON_HEX_TAG | JSON_HEX_AMP) ?>,

        openModal() {
            this.open = true;
            this.success = false;
            this.errorMsg = '';
            this.busy = false;
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.open = false;
            this.busy = false;
            this.errorMsg = '';
            document.body.style.overflow = '';
            if (this.success) {
                this.name = '';
                this.phone = '';
                this.specialty = this.defaultSpecialty;
                this.success = false;
            }
        },

        async submit() {
            this.errorMsg = '';
            if (!this.name.trim() || !this.phone.trim() || !this.specialty.trim()) {
                this.errorMsg = 'Please fill in all fields.';
                return;
            }
            if (this.phone.length < 10) {
                this.errorMsg = 'Please enter a valid 10-digit contact number.';
                return;
            }
            this.busy = true;
            try {
                const body = new FormData();
                body.append('modal', '1');
                body.append('name', this.name.trim());
                body.append('phone', this.phone.trim());
                body.append('specialty', this.specialty.trim());
                body.append('spec_key', this.specKey);
                body.append('message', 'Demo request via specialty page modal');

                const res = await fetch('/book-a-demo', {
                    method: 'POST',
                    body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.ok) {
                    this.success = true;
                } else {
                    this.errorMsg = data.error || 'Something went wrong. Please try again.';
                }
            } catch (e) {
                this.errorMsg = 'Network error — please try again or email eclinicpro.com@gmail.com.';
            } finally {
                this.busy = false;
            }
        },
    };
}

window.ecpDemo = {
    open() { window.dispatchEvent(new CustomEvent('open-demo-modal')); },
};

document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-open-demo-modal]');
    if (!trigger) return;
    e.preventDefault();
    window.ecpDemo.open();
});
</script>
