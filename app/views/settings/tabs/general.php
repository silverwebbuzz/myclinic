<?php
use App\Services\DirectoryProfileUrlService;
use App\Services\DoctorOtpService;

$slug = (string) ($clinic['slug'] ?? '');
$clinicId = (int) ($clinic['id'] ?? 0);

$bookingUrl = DirectoryProfileUrlService::publicBookingUrlForTenant($clinicId, $slug);
$hasProfile = DirectoryProfileUrlService::hasPublicProfile($clinicId);
$phoneStep = (string) ($phoneStep ?? 'none'); // none|code|verified
$pendingPhone = (string) ($pendingPhone ?? '');
$phoneDevCode = (string) ($phoneDevCode ?? '');
$effectivePhone = (string) ($clinic['phone'] ?? '');
$countries = (isset($countries) && is_array($countries)) ? $countries : [];

$formatIndianPhone = static function (string $raw): string {
    $digits = preg_replace('/\D/', '', $raw) ?? '';
    if (strlen($digits) >= 10) {
        return '+91' . substr($digits, -10);
    }
    return $raw;
};

$effectivePhoneDisplay = $formatIndianPhone(DoctorOtpService::normalizePhone($effectivePhone));
$pendingPhoneDisplay = $pendingPhone !== '' ? $formatIndianPhone(DoctorOtpService::normalizePhone($pendingPhone)) : '';
$pendingDigits = $pendingPhoneDisplay !== '' ? (preg_replace('/\D/', '', $pendingPhoneDisplay) ?? '') : '';
$pendingDigits10 = strlen($pendingDigits) >= 10 ? substr($pendingDigits, -10) : '';
$phoneCodeResendSeconds = 30;

$clinicName  = $clinic['name'] ?? 'our clinic';
$shareText   = "Book your appointment at {$clinicName} online — quick, no calls needed: {$bookingUrl}";
$qrSrc       = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=8&data=' . rawurlencode($bookingUrl);
?>
<div class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/40 shadow-sm"
     x-data="{ copied: false, shareOpen: false, copyMsg: false }">
    <div class="grid gap-6 p-6 md:grid-cols-[1fr_auto] md:items-center">
        <!-- Left: title + URL + actions -->
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </span>
                <div>
                    <h3 class="text-base font-semibold text-emerald-900">Your booking link</h3>
                    <p class="text-xs text-emerald-700">
                        <?php if ($hasProfile): ?>
                        Share your public profile — patients book from the sidebar widget.
                        <?php else: ?>
                        Direct booking link for your clinic. After you’re listed on Find a Doctor, this upgrades to your full profile page.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Big readable URL -->
            <div class="mt-4 flex items-center gap-2 rounded-xl border border-emerald-300 bg-white p-1.5 shadow-sm">
                <div class="min-w-0 flex-1 px-3 py-1.5">
                    <div class="truncate font-mono text-sm text-emerald-900" title="<?= htmlspecialchars($bookingUrl) ?>">
                        <?= htmlspecialchars(preg_replace('#^https?://#', '', $bookingUrl)) ?>
                    </div>
                </div>
                <input type="text" readonly value="<?= htmlspecialchars($bookingUrl) ?>"
                       x-ref="urlField" class="sr-only">
                <button type="button"
                        @click="navigator.clipboard.writeText($refs.urlField.value); copied = true; setTimeout(() => copied = false, 2000)"
                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                    <svg x-show="!copied" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span x-show="!copied">Copy</span>
                    <svg x-show="copied" x-cloak width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span x-show="copied" x-cloak>Copied</span>
                </button>
            </div>

            <!-- Share strip -->
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="https://wa.me/?text=<?= rawurlencode($shareText) ?>" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.6-.8-1.9-.9-.3-.1-.4-.1-.6.1-.2.3-.7.9-.8 1-.2.2-.3.2-.5.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.2-.5.1-.2 0-.4 0-.5-.1-.1-.6-1.5-.9-2-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.4-.3.3-1 1-1 2.5s1 2.9 1.1 3.1c.1.2 2 3 4.8 4.2.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.6-.7 1.9-1.3.2-.6.2-1.2.2-1.3-.1-.1-.3-.2-.6-.3z"/><path d="M21 12c0 5-4 9-9 9-1.5 0-3-.4-4.3-1.1L3 21l1.2-4.6C3.4 15 3 13.5 3 12c0-5 4-9 9-9s9 4 9 9z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                    Share on WhatsApp
                </a>
                <a href="sms:?&body=<?= rawurlencode($shareText) ?>"
                   class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    SMS
                </a>
                <a href="mailto:?subject=<?= rawurlencode('Book your appointment at ' . $clinicName) ?>&body=<?= rawurlencode($shareText) ?>"
                   class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Email
                </a>
                <button type="button" x-data
                        @click="navigator.clipboard.writeText('<?= htmlspecialchars(addslashes($shareText), ENT_QUOTES) ?>'); copyMsg = true; setTimeout(() => copyMsg = false, 2000)"
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span x-show="!copyMsg">Copy share text</span>
                    <span x-show="copyMsg" x-cloak class="text-emerald-700">✓ Copied!</span>
                </button>
                <a href="<?= htmlspecialchars($bookingUrl) ?>" target="_blank" rel="noopener"
                   class="ml-auto inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100">
                    Preview ↗
                </a>
            </div>

            <p class="mt-4 flex items-start gap-1.5 text-xs text-emerald-700/80">
                <span class="mt-0.5 shrink-0"><?= ui_icon('help', 13) ?></span>
                <span><strong>Pro tip:</strong> Add this to your WhatsApp Business bio, Google Business profile, business cards, and clinic signage. The QR code on the right works on any phone camera.</span>
            </p>
        </div>

        <!-- Right: QR code panel -->
        <div class="flex flex-col items-center gap-2 rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm">
            <img src="<?= htmlspecialchars($qrSrc) ?>"
                 alt="Booking QR code"
                 width="160" height="160"
                 class="block rounded-lg"
                 loading="lazy">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Scan to book</span>
            <a href="<?= htmlspecialchars($qrSrc) ?>" download="booking-qr-<?= htmlspecialchars($slug) ?>.png"
               class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 hover:underline">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download
            </a>
        </div>
    </div>
</div>

<div x-data="{
    phoneModal: <?= json_encode(in_array($phoneStep, ['code', 'verified'], true)) ?>,
    resendIn: <?= (int) ($phoneStep === 'code' ? $phoneCodeResendSeconds : 0) ?>,
    resendTimer: null,
    initResend() {
        if (this.resendIn > 0) {
            this.resendTimer = setInterval(() => {
                this.resendIn -= 1;
                if (this.resendIn <= 0) {
                    clearInterval(this.resendTimer);
                    this.resendTimer = null;
                }
            }, 1000);
        }
    }
}" x-init="initResend()">
<form method="post" action="/settings/general" enctype="multipart/form-data" class="space-y-4 ui-card ui-card-pad">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <label class="ui-label mb-1 block">Clinic name</label>
            <input name="clinic_name" required value="<?= htmlspecialchars($clinic['name'] ?? '') ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">Phone</label>
            <div class="flex gap-2">
                <input name="phone" value="<?= htmlspecialchars($effectivePhoneDisplay) ?>" class="ui-input"
                       readonly @click.prevent="phoneModal = true" @focus.prevent="phoneModal = true"
                       title="Click to change phone">
                <button type="button" class="ui-btn ui-btn-secondary whitespace-nowrap" @click="phoneModal = true">Change</button>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">Phone changes require WhatsApp OTP verification.</p>
            <?php if ($pendingPhone !== '' && $phoneStep === 'verified'): ?>
            <p class="mt-1 text-[11px] font-medium text-emerald-700">Verified: <?= htmlspecialchars($pendingPhoneDisplay) ?>.</p>
            <?php endif; ?>
        </div>
        <div class="sm:col-span-2">
            <label class="ui-label mb-1 block">Email</label>
            <input name="email" type="email" value="<?= htmlspecialchars($clinic['email'] ?? '') ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">GSTIN</label>
            <input name="gstin" value="<?= htmlspecialchars($clinic['gstin'] ?? '') ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">Country</label>
            <select name="country_code" class="ui-input">
                <?php foreach ($countries as $code => $name): ?>
                <option value="<?= $code ?>" <?= ($clinic['country_code'] ?? 'IN') === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="ui-label mb-1 block">Currency</label>
            <input name="currency" value="<?= htmlspecialchars($clinic['currency'] ?? 'INR') ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">Timezone</label>
            <input name="timezone" value="<?= htmlspecialchars($clinic['timezone'] ?? 'Asia/Kolkata') ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">UHID prefix</label>
            <input name="uhid_prefix" maxlength="6" value="<?= htmlspecialchars($config['uhid_prefix'] ?? 'MC') ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">Invoice prefix</label>
            <input name="invoice_prefix" value="<?= htmlspecialchars($config['invoice_prefix'] ?? 'INV') ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">Tax label</label>
            <input name="invoice_tax_label" value="<?= htmlspecialchars($config['invoice_tax_label'] ?? 'GST') ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">Tax %</label>
            <input name="invoice_tax_percent" type="number" step="0.01" value="<?= htmlspecialchars((string) ($config['invoice_tax_percent'] ?? '0')) ?>" class="ui-input">
        </div>
        <div>
            <label class="ui-label mb-1 block">Brand color</label>
            <input name="brand_color" type="color" value="<?= htmlspecialchars($clinic['brand_color'] ?? '#0F766E') ?>" class="h-[34px] w-full rounded-lg border border-slate-300 p-1">
        </div>
        <div class="sm:col-span-2">
            <label class="ui-label mb-1 block">Logo</label>
            <input name="logo" type="file" accept="image/png,image/jpeg" class="ui-input file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-0.5 file:text-xs">
        </div>
        <div>
            <label class="ui-label mb-1 block">Registration number</label>
            <input name="registration_number" value="<?= htmlspecialchars($clinic['registration_number'] ?? '') ?>"
                   placeholder="e.g. medical council reg. no." class="ui-input">
            <p class="mt-0.5 text-[11px] text-slate-400">Printed on prescriptions and invoices.</p>
        </div>
        <div class="sm:col-span-2 lg:col-span-3 grid gap-x-4 gap-y-3 sm:grid-cols-2">
            <div>
                <label class="ui-label mb-1 block">Prescription header note</label>
                <input name="rx_header_text" maxlength="255"
                       value="<?= htmlspecialchars($config['rx_header_text'] ?? '') ?>"
                       placeholder="e.g. Dr A. Sharma · MBBS, MD · Mon–Sat 9am–1pm" class="ui-input">
                <p class="mt-0.5 text-[11px] text-slate-400">Shown under the clinic name on the printed Rx pad.</p>
            </div>
            <div>
                <label class="ui-label mb-1 block">Prescription footer note</label>
                <input name="rx_footer_text" maxlength="255"
                       value="<?= htmlspecialchars($config['rx_footer_text'] ?? '') ?>"
                       placeholder="e.g. Not valid for medico-legal purposes" class="ui-input">
                <p class="mt-0.5 text-[11px] text-slate-400">Shown at the bottom of the printed Rx pad.</p>
            </div>
        </div>
    </div>
    <button type="submit" class="ui-btn ui-btn-primary">Save general</button>
</form>

<div x-show="phoneModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-900/40 p-4" @click.self="phoneModal = false">
    <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-900">Verify new phone number</h3>
            <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="phoneModal = false">✕</button>
        </div>
        <p class="mt-1 text-xs text-slate-500">Send a WhatsApp OTP to the new number and verify it. Your phone number updates automatically after successful verification.</p>

        <?php if ($phoneStep === 'code' && $pendingPhone !== ''): ?>
            <form method="post" action="/settings/general/phone/verify-otp" class="mt-4 space-y-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="new_phone" value="<?= htmlspecialchars($pendingPhoneDisplay) ?>">
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">WhatsApp OTP sent to <strong><?= htmlspecialchars($pendingPhoneDisplay) ?></strong></div>
                <input name="otp_code" maxlength="6" inputmode="numeric" placeholder="Enter 6-digit WhatsApp OTP" class="ui-input">
                <button type="submit" class="ui-btn ui-btn-primary w-full">Verify WhatsApp OTP</button>
            </form>
            <form method="post" action="/settings/general/phone/send-otp" class="mt-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="new_phone" value="<?= htmlspecialchars($pendingPhoneDisplay) ?>">
                <button type="submit" class="ui-btn ui-btn-secondary w-full" :disabled="resendIn > 0">
                    <span x-show="resendIn === 0">Resend WhatsApp OTP</span>
                    <span x-show="resendIn > 0">Resend in <span x-text="resendIn"></span>s</span>
                </button>
            </form>
            <?php if ($phoneDevCode !== ''): ?>
            <p class="mt-2 text-xs text-amber-700">DEV OTP: <strong><?= htmlspecialchars($phoneDevCode) ?></strong></p>
            <?php endif; ?>
        <?php elseif ($phoneStep === 'verified' && $pendingPhone !== ''): ?>
            <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                Verified successfully. Updating your phone number…
            </div>
            <button type="button" class="ui-btn ui-btn-primary mt-3 w-full" @click="phoneModal = false">Done</button>
        <?php else: ?>
            <form method="post" action="/settings/general/phone/send-otp" class="mt-4 space-y-3"
                  x-data="{ digits: <?= json_encode($pendingDigits10) ?> }">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:ring-2 focus-within:ring-emerald-200">
                    <span class="flex items-center bg-slate-50 px-3 text-sm font-semibold text-slate-700">+91</span>
                    <input name="new_phone"
                           x-model="digits"
                           inputmode="numeric"
                           pattern="[0-9]{10}"
                           maxlength="10"
                           placeholder="10-digit mobile number"
                           class="min-w-0 flex-1 px-3 py-2 text-sm outline-none"
                           autofocus>
                </div>
                <button type="submit" class="ui-btn ui-btn-primary w-full">Send WhatsApp OTP</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</div>

<?php /* "Services offered" moved to the "Listed on eClinicPro" page (/listing),
        where the whole public profile is edited in one place. */ ?>
