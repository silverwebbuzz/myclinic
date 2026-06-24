<?php
$brandColor = $clinic['brand_color'] ?? '#0F9B6E';
$clinicName = $clinic['name'] ?? 'Clinic';
$clinicAddr = trim((string) ($clinic['address'] ?? ''));
$clinicPhone = $clinic['phone'] ?? '';
$clinicCity  = $clinic['city'] ?? '';
$clinicState = $clinic['state'] ?? '';
$isConfirmed = !empty($confirmation);
$bookingError = $error ?? null;

// Logged-in /patient details (set by BookController::show). When present we
// prefill + lock name/phone and skip asking for them.
$patientName     = $patientName ?? '';
$patientPhone    = $patientPhone ?? '';
$patientLoggedIn = !empty($patientLoggedIn);

// Try to read a fee from the first doctor or the clinic settings.
$displayFee = null;
foreach ($doctors ?? [] as $d) {
    if (!empty($d['incentive_flat_fee'])) { $displayFee = (float) $d['incentive_flat_fee']; break; }
}
$patientPhoneDisplay = preg_replace('/\D/', '', (string) $patientPhone);
if (strlen($patientPhoneDisplay) >= 10) {
    $patientPhoneDisplay = substr($patientPhoneDisplay, -10);
}
$patientFirstName = 'Patient';
if (trim($patientName) !== '') {
    $patientFirstName = preg_split('/\s+/', trim($patientName))[0] ?: 'Patient';
}
$patientInitial = strtoupper(mb_substr($patientFirstName, 0, 1)) ?: 'P';

$bookConfig = array_merge([
    'authMe' => '/api/patient-auth/me',
    'authSendOtp' => '/api/patient-auth/send-otp',
    'authVerifyOtp' => '/api/patient-auth/verify-otp',
    'authLogout' => '/api/patient-auth/logout',
    'slotsUrl' => '/book/' . rawurlencode((string) ($slug ?? '')) . '/slots',
    'formAction' => '/book/' . rawurlencode((string) ($slug ?? '')),
    'siteHomeUrl' => 'https://eclinicpro.com',
    'findDoctorUrl' => 'https://eclinicpro.com/find-a-doctor',
    'patientPanelUrl' => 'https://eclinicpro.com/patient',
], $bookConfig ?? []);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book — <?= htmlspecialchars($clinicName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root { --brand: <?= htmlspecialchars($brandColor) ?>; }
        html, body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .bg-brand { background: var(--brand); }
        .text-brand { color: var(--brand); }
        .border-brand { border-color: var(--brand); }
        .ring-brand:focus { --tw-ring-color: var(--brand); }
        .bg-brand-50 { background: color-mix(in srgb, var(--brand) 8%, white); }
        .bg-brand-100 { background: color-mix(in srgb, var(--brand) 14%, white); }
        .border-brand-100 { border-color: color-mix(in srgb, var(--brand) 20%, white); }
        [x-cloak] { display: none !important; }
        .auth-devcode {
            font-size: 13px;
            background: #fff7e0;
            border: 1px solid #f5d97e;
            color: #6b4f00;
            padding: 10px 12px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .auth-devcode .tag {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            background: #f5d97e;
            color: #6b4f00;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .auth-devcode strong {
            font-family: ui-monospace, Menlo, monospace;
            font-size: 16px;
            letter-spacing: 2px;
            color: #6b4f00;
            margin-left: auto;
        }
        /* sticky right column scrolls independently on desktop */
        @media (min-width: 1024px) {
            .book-stick { position: sticky; top: 24px; align-self: start; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50">

<!-- Slim top bar -->
<header class="border-b bg-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
        <a href="<?= htmlspecialchars($bookConfig['siteHomeUrl']) ?>" class="flex items-center gap-2 text-sm hover:opacity-80">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-white font-bold">e</span>
            <span class="font-semibold text-slate-800">ClinicPro</span>
            <span class="hidden text-slate-300 sm:inline">·</span>
            <span class="hidden text-xs text-slate-500 sm:inline">Online booking</span>
        </a>
        <div class="flex items-center gap-4">
        <nav class="hidden items-center gap-3 text-xs font-medium text-slate-600 sm:flex">
            <a href="<?= htmlspecialchars($bookConfig['findDoctorUrl']) ?>" class="hover:text-brand">Find a doctor</a>
            <a href="<?= htmlspecialchars($bookConfig['patientPanelUrl']) ?>" class="hover:text-brand">My panel</a>
        </nav>
        <?php if ($clinicPhone): ?>
        <a href="tel:<?= htmlspecialchars($clinicPhone) ?>"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <span class="hidden sm:inline"><?= htmlspecialchars($clinicPhone) ?></span>
            <span class="sm:hidden">Call clinic</span>
        </a>
        <?php endif; ?>
        <div class="flex items-center gap-3" x-data x-show="$store.bookPatient.loggedIn" x-cloak>
            <a href="<?= htmlspecialchars($bookConfig['patientPanelUrl']) ?>"
               class="hidden text-xs font-medium text-slate-600 hover:text-brand sm:inline">My panel</a>
            <div class="flex items-center gap-2 text-sm text-slate-700">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand"
                      x-text="$store.bookPatient.initial()"><?= htmlspecialchars($patientInitial) ?></span>
                <span class="hidden sm:inline">Hi, <strong x-text="$store.bookPatient.firstName()"><?= htmlspecialchars($patientFirstName) ?></strong></span>
            </div>
        </div>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">

    <!-- Breadcrumb -->
    <nav class="mb-4 text-xs text-slate-500">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="<?= htmlspecialchars($bookConfig['siteHomeUrl']) ?>" class="hover:text-brand">Home</a></li>
            <li>›</li>
            <li><a href="<?= htmlspecialchars($bookConfig['findDoctorUrl']) ?>" class="hover:text-brand">Find a doctor</a></li>
            <?php if ($clinicCity): ?>
            <li>›</li>
            <li><?= htmlspecialchars($clinicCity) ?></li>
            <?php endif; ?>
            <li>›</li>
            <li class="font-medium text-slate-700"><?= htmlspecialchars($clinicName) ?></li>
        </ol>
    </nav>

    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">

        <!-- ============ LEFT COLUMN: Clinic info + tabs ============ -->
        <div class="space-y-4">

            <!-- Clinic header card -->
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="p-5 sm:p-6">
                    <div class="flex items-start gap-4 sm:gap-5">
                        <!-- Logo / monogram -->
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-2xl font-bold text-brand ring-2 ring-brand-100 sm:h-20 sm:w-20 sm:text-3xl">
                            <?= htmlspecialchars(strtoupper(mb_substr($clinicName, 0, 1))) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">
                                    <?= htmlspecialchars($clinicName) ?>
                                </h1>
                                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Verified
                                </span>
                            </div>
                            <?php if (count($doctors) > 0): ?>
                            <p class="mt-1 text-sm text-slate-600">
                                <?= count($doctors) ?> <?= count($doctors) === 1 ? 'doctor' : 'doctors' ?> available
                                <?php if (!empty($doctors[0]['specialization'])): ?>
                                    · <?= htmlspecialchars($doctors[0]['specialization']) ?>
                                <?php endif; ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($clinicAddr || $clinicCity): ?>
                            <p class="mt-2 flex items-start gap-1.5 text-sm text-slate-600">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 flex-shrink-0 text-slate-400"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span><?= htmlspecialchars(trim($clinicAddr . ($clinicAddr && $clinicCity ? ', ' : '') . $clinicCity)) ?></span>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick stats row -->
                    <div class="mt-5 grid grid-cols-3 gap-3 rounded-xl bg-slate-50 p-3 text-center sm:gap-4 sm:p-4">
                        <div>
                            <div class="text-base font-bold text-slate-900 sm:text-lg">
                                <?php if ($displayFee !== null): ?>
                                    ₹<?= number_format($displayFee) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </div>
                            <div class="text-[10px] font-medium uppercase tracking-wider text-slate-500 sm:text-[11px]">Consult fee</div>
                        </div>
                        <div class="border-x border-slate-200">
                            <div class="text-base font-bold text-slate-900 sm:text-lg">Same-day</div>
                            <div class="text-[10px] font-medium uppercase tracking-wider text-slate-500 sm:text-[11px]">Available</div>
                        </div>
                        <div>
                            <div class="text-base font-bold text-slate-900 sm:text-lg">No payment</div>
                            <div class="text-[10px] font-medium uppercase tracking-wider text-slate-500 sm:text-[11px]">Pay at clinic</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tabs (only Info is functional for now) -->
            <div class="border-b border-slate-200 bg-white sm:rounded-t-2xl sm:px-2">
                <nav class="flex gap-1 overflow-x-auto px-2 sm:px-0" role="tablist">
                    <button type="button" class="border-b-2 border-brand px-4 py-3 text-sm font-semibold text-brand">
                        Info
                    </button>
                    <button type="button" class="border-b-2 border-transparent px-4 py-3 text-sm font-medium text-slate-500" disabled title="Coming soon">
                        Doctors
                    </button>
                    <button type="button" class="border-b-2 border-transparent px-4 py-3 text-sm font-medium text-slate-500" disabled title="Coming soon">
                        Reviews
                    </button>
                    <button type="button" class="border-b-2 border-transparent px-4 py-3 text-sm font-medium text-slate-500" disabled title="Coming soon">
                        Photos
                    </button>
                </nav>
            </div>

            <!-- About / address / hours card -->
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="grid gap-6 p-5 sm:grid-cols-2 sm:p-6">
                    <!-- Address column -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Address</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-700">
                            <?php if ($clinicAddr): ?>
                                <?= nl2br(htmlspecialchars($clinicAddr)) ?>
                            <?php else: ?>
                                <span class="text-slate-400">Not provided</span>
                            <?php endif; ?>
                            <?php if ($clinicCity): ?>
                                <br><?= htmlspecialchars($clinicCity) ?><?php if ($clinicState): ?>, <?= htmlspecialchars($clinicState) ?><?php endif; ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($clinicAddr || $clinicCity): ?>
                        <a href="https://www.google.com/maps/search/<?= rawurlencode($clinicName . ' ' . ($clinicAddr ?: '') . ' ' . ($clinicCity ?: '')) ?>"
                           target="_blank" rel="noopener"
                           class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
                            Get directions
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Hours column -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Clinic hours</h3>
                        <dl class="mt-2 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-600">Mon – Sat</dt>
                                <dd class="font-medium text-slate-800">11:00 AM – 8:00 PM</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-600">Sunday</dt>
                                <dd class="font-medium text-slate-800">By appointment</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-[11px] text-slate-500">
                            Pick a slot on the right — slots reflect doctor availability.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Doctors list (compact) -->
            <?php if (count($doctors) > 0): ?>
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="p-5 sm:p-6">
                    <h3 class="text-base font-semibold text-slate-900">Doctors at <?= htmlspecialchars($clinicName) ?></h3>
                    <div class="mt-4 space-y-3">
                        <?php foreach ($doctors as $d): ?>
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 sm:p-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-base font-semibold text-brand">
                                <?= htmlspecialchars(strtoupper(mb_substr($d['name'], 0, 1))) ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-slate-900"><?= htmlspecialchars($d['name']) ?></div>
                                <?php if (!empty($d['specialization']) || !empty($d['qualification'])): ?>
                                <div class="truncate text-xs text-slate-500">
                                    <?php if (!empty($d['qualification'])): ?>
                                        <?= htmlspecialchars($d['qualification']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($d['specialization'])): ?>
                                        <?= !empty($d['qualification']) ? ' · ' : '' ?>
                                        <?= htmlspecialchars($d['specialization']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Trust footer -->
            <div class="text-center text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Booking secured by eClinicPro · Pay only at the clinic
                </span>
            </div>
        </div>

        <!-- ============ RIGHT COLUMN: Booking widget ============ -->
        <aside class="book-stick">
            <?php $embedMode = false; require __DIR__ . '/_widget.php'; ?>
        </aside>
    </div>
</main>

<?php require __DIR__ . '/_wizard_script.php'; ?>
</body>
</html>
