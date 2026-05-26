<?php
$brandColor = $clinic['brand_color'] ?? '#0F9B6E';
$clinicName = $clinic['name'] ?? 'Clinic';
$clinicAddr = trim((string) ($clinic['address'] ?? ''));
$clinicPhone = $clinic['phone'] ?? '';
$clinicCity  = $clinic['city'] ?? '';
$clinicState = $clinic['state'] ?? '';
$isConfirmed = !empty($confirmation);
$bookingError = $error ?? null;

// Try to read a fee from the first doctor or the clinic settings.
$displayFee = null;
foreach ($doctors ?? [] as $d) {
    if (!empty($d['incentive_flat_fee'])) { $displayFee = (float) $d['incentive_flat_fee']; break; }
}
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
        <div class="flex items-center gap-2 text-sm">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-white font-bold">e</span>
            <span class="font-semibold text-slate-800">ClinicPro</span>
            <span class="hidden text-slate-300 sm:inline">·</span>
            <span class="hidden text-xs text-slate-500 sm:inline">Online booking</span>
        </div>
        <?php if ($clinicPhone): ?>
        <a href="tel:<?= htmlspecialchars($clinicPhone) ?>"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <span class="hidden sm:inline"><?= htmlspecialchars($clinicPhone) ?></span>
            <span class="sm:hidden">Call clinic</span>
        </a>
        <?php endif; ?>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">

    <!-- Breadcrumb -->
    <nav class="mb-4 text-xs text-slate-500">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="https://eclinicpro.com" class="hover:text-brand">Home</a></li>
            <li>›</li>
            <li><a href="https://eclinicpro.com/find-a-doctor" class="hover:text-brand">Find a doctor</a></li>
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
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

                <?php if ($isConfirmed): ?>
                <!-- Confirmation panel -->
                <div class="bg-gradient-to-br from-emerald-50 to-white p-6 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h2 class="mt-4 text-lg font-bold text-slate-900">Appointment confirmed!</h2>
                    <p class="mt-1 text-sm text-slate-600">We've reserved your slot.</p>

                    <?php if (!empty($confirmation['token'])): ?>
                    <div class="mt-5 rounded-2xl border-2 border-dashed border-emerald-400 bg-white p-4">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Your token number</div>
                        <div class="mt-1 text-5xl font-bold text-brand"><?= (int) $confirmation['token'] ?></div>
                        <div class="mt-1 text-xs text-slate-500">Show this at reception</div>
                    </div>
                    <?php endif; ?>
                </div>
                <dl class="border-t border-slate-100 p-4 text-sm">
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <dt class="text-slate-500">Patient</dt>
                        <dd class="font-semibold uppercase text-slate-900"><?= htmlspecialchars($confirmation['patient_name']) ?></dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <dt class="text-slate-500">Date</dt>
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars($confirmation['date']) ?></dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <dt class="text-slate-500">Time</dt>
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars($confirmation['time']) ?></dd>
                    </div>
                    <div class="flex justify-between py-2">
                        <dt class="text-slate-500">Appt ID</dt>
                        <dd class="font-mono text-xs font-semibold text-slate-900">#<?= (int) $confirmation['appointment_id'] ?></dd>
                    </div>
                </dl>
                <div class="border-t border-slate-100 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                    💡 Please arrive 10 minutes early and show your token at reception.
                </div>
                <div class="p-4">
                    <a href="/book/<?= htmlspecialchars($slug) ?>"
                       class="block w-full rounded-xl bg-brand py-3 text-center text-sm font-semibold text-white hover:opacity-90">
                        Book another appointment
                    </a>
                </div>

                <?php else: ?>
                <!-- Active wizard -->
                <div class="bg-brand-50 px-5 pt-5 pb-3 sm:px-6">
                    <h2 class="text-base font-bold text-slate-900">Choose your appointment</h2>
                    <p class="mt-0.5 text-xs text-slate-600">Same-day slots available. No advance payment needed.</p>

                    <!-- Appointment type segmented control -->
                    <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl border border-brand-100 bg-white p-1">
                        <button type="button" class="rounded-lg bg-brand py-1.5 text-xs font-semibold text-white">
                            <span class="inline-flex items-center gap-1">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                Clinic visit
                            </span>
                        </button>
                        <button type="button" class="rounded-lg py-1.5 text-xs font-semibold text-slate-400" disabled title="Coming soon">
                            <span class="inline-flex items-center gap-1">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 7l-7 5 7 5z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                                Video
                            </span>
                        </button>
                    </div>
                </div>

                <?php if ($bookingError): ?>
                <div class="mx-5 mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800 sm:mx-6">
                    ⚠️ <?= htmlspecialchars($bookingError) ?>
                </div>
                <?php endif; ?>

                <div class="p-5 sm:p-6" x-data="bookingWizard()" x-init="init()">
                    <?php require __DIR__ . '/_stepper.php'; ?>

                    <form method="post" action="/book/<?= htmlspecialchars($slug) ?>" @submit="submitting = true" class="mt-5">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="doctor_id" :value="doctorId">
                        <input type="hidden" name="scheduled_at" :value="selectedSlot">

                        <!-- ============ STEP 1: Date & Slot ============ -->
                        <div x-show="step === 1" class="space-y-5">
                            <?php if (count($doctors) > 1): ?>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Doctor</span>
                                <select x-model="doctorId" @change="loadSlots()"
                                        class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand">
                                    <?php foreach ($doctors as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <?php endif; ?>

                            <!-- Date strip -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Select date</span>
                                    <span class="text-[10px] text-slate-400" x-show="!loadingSlots" x-cloak>
                                        <span x-text="(morningSlots.length + eveningSlots.length)"></span> slots
                                    </span>
                                </div>
                                <div class="mt-2 flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
                                    <?php foreach ($days as $d): ?>
                                    <button type="button"
                                            @click="selectDate('<?= htmlspecialchars($d['date']) ?>')"
                                            :class="selectedDate === '<?= htmlspecialchars($d['date']) ?>'
                                                ? 'border-brand bg-brand text-white shadow-md'
                                                : '<?= $d['within_window'] ? 'border-slate-200 bg-white hover:border-brand text-slate-700' : 'border-slate-100 bg-slate-50 text-slate-300 cursor-not-allowed' ?>'"
                                            <?= $d['within_window'] ? '' : 'disabled' ?>
                                            class="flex shrink-0 flex-col items-center rounded-xl border-2 px-3 py-2.5 transition min-w-[68px]">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider opacity-80"><?= htmlspecialchars($d['weekday']) ?></span>
                                        <span class="mt-0.5 text-xl font-bold leading-none"><?= (int) $d['day'] ?></span>
                                        <span class="mt-0.5 text-[10px] opacity-80"><?= htmlspecialchars($d['month']) ?></span>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Slots -->
                            <div x-show="loadingSlots" class="rounded-xl bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                                <div class="mx-auto h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-brand"></div>
                                <p class="mt-2">Loading slots…</p>
                            </div>
                            <div x-show="!loadingSlots && morningSlots.length === 0 && eveningSlots.length === 0" x-cloak
                                 class="rounded-xl bg-amber-50 px-3 py-4 text-center text-sm text-amber-800">
                                No slots on this day. Try another date.
                            </div>

                            <div x-show="morningSlots.length > 0" x-cloak>
                                <p class="text-xs font-semibold text-slate-600">
                                    <span class="text-amber-500">☀️</span> Morning
                                    <span class="ml-1 font-normal text-slate-400">(<span x-text="morningSlots.filter(s => s.available).length"></span> slots)</span>
                                </p>
                                <div class="mt-2 grid grid-cols-3 gap-2">
                                    <template x-for="s in morningSlots" :key="s.datetime">
                                        <button type="button"
                                                :disabled="!s.available"
                                                @click="selectedSlot = s.datetime; selectedSlotLabel = s.label"
                                                :class="selectedSlot === s.datetime
                                                    ? 'bg-brand text-white border-brand shadow-md'
                                                    : (s.available ? 'border-slate-200 hover:border-brand bg-white text-slate-700' : 'border-slate-100 bg-slate-50 text-slate-300 line-through cursor-not-allowed')"
                                                class="rounded-lg border-2 px-1 py-2 text-xs font-semibold transition"
                                                x-text="s.label"></button>
                                    </template>
                                </div>
                            </div>

                            <div x-show="eveningSlots.length > 0" x-cloak>
                                <p class="text-xs font-semibold text-slate-600">
                                    <span class="text-indigo-500">🌙</span> Evening
                                    <span class="ml-1 font-normal text-slate-400">(<span x-text="eveningSlots.filter(s => s.available).length"></span> slots)</span>
                                </p>
                                <div class="mt-2 grid grid-cols-3 gap-2">
                                    <template x-for="s in eveningSlots" :key="s.datetime">
                                        <button type="button"
                                                :disabled="!s.available"
                                                @click="selectedSlot = s.datetime; selectedSlotLabel = s.label"
                                                :class="selectedSlot === s.datetime
                                                    ? 'bg-brand text-white border-brand shadow-md'
                                                    : (s.available ? 'border-slate-200 hover:border-brand bg-white text-slate-700' : 'border-slate-100 bg-slate-50 text-slate-300 line-through cursor-not-allowed')"
                                                class="rounded-lg border-2 px-1 py-2 text-xs font-semibold transition"
                                                x-text="s.label"></button>
                                    </template>
                                </div>
                            </div>

                            <button type="button" @click="goNext()" :disabled="!selectedSlot"
                                    class="w-full rounded-xl bg-brand py-3.5 text-sm font-bold text-white shadow-sm transition hover:shadow-md disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none">
                                <span x-show="!selectedSlot">Pick a slot to continue</span>
                                <span x-show="selectedSlot" x-cloak>
                                    Continue →
                                    <span class="text-[11px] font-normal opacity-90" x-text="'(' + selectedSlotLabel + ')'"></span>
                                </span>
                            </button>
                        </div>

                        <!-- ============ STEP 2: Your Details ============ -->
                        <div x-show="step === 2" class="space-y-4" x-cloak>
                            <div class="rounded-lg bg-brand-50 px-3 py-2.5 text-xs">
                                <div class="font-semibold text-slate-900">
                                    📅 <span x-text="selectedSlotLabel"></span> · <span x-text="formatDate(selectedDate)"></span>
                                </div>
                            </div>

                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mobile number <span class="text-rose-500">*</span></span>
                                <div class="mt-1.5 flex gap-2">
                                    <input type="tel" name="phone" x-model="phone" required inputmode="numeric"
                                           placeholder="10-digit mobile"
                                           class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand">
                                    <button type="button" @click="lookupPatient()" :disabled="!phone || phone.length < 6"
                                            class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-40">
                                        Find
                                    </button>
                                </div>
                            </label>

                            <template x-if="lookupResult === 'found'">
                                <div class="rounded-lg bg-emerald-50 px-3 py-2.5 text-sm">
                                    <p class="font-semibold text-emerald-800">👋 Welcome back, <span x-text="foundName"></span></p>
                                </div>
                            </template>
                            <template x-if="lookupResult === 'not_found'">
                                <div class="rounded-lg bg-sky-50 px-3 py-2.5 text-sm text-sky-800">
                                    🆕 New patient — please enter your name below.
                                </div>
                            </template>

                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Full name <span class="text-rose-500">*</span></span>
                                <input type="text" name="name" x-model="name" required
                                       placeholder="Your full name"
                                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand">
                            </label>

                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reason for visit <span class="font-normal normal-case tracking-normal text-slate-400">(optional)</span></span>
                                <input type="text" name="chief_complaint"
                                       placeholder="e.g. Fever, back pain"
                                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand">
                            </label>

                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Visit type</span>
                                <select name="is_followup" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand">
                                    <option value="">First / Regular visit</option>
                                    <option value="1">Follow-up</option>
                                </select>
                            </label>

                            <div class="flex gap-2 pt-2">
                                <button type="button" @click="step = 1"
                                        class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    ← Back
                                </button>
                                <button type="submit" :disabled="submitting || !name || !phone"
                                        class="flex-1 rounded-lg bg-brand py-3 text-sm font-bold text-white shadow-sm transition hover:shadow-md disabled:opacity-50">
                                    <span x-show="!submitting">Confirm booking</span>
                                    <span x-show="submitting" x-cloak>Booking…</span>
                                </button>
                            </div>
                            <p class="text-center text-[11px] text-slate-500">
                                Pay at the clinic. No advance payment.
                            </p>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer trust note under widget -->
            <p class="mt-3 text-center text-[11px] text-slate-500">
                🔒 Your details stay private. eClinicPro never shares your number.
            </p>
        </aside>
    </div>
</main>

<script>
function bookingWizard() {
    return {
        step: 1,
        doctorId: '<?= (int) ($doctorId ?? 0) ?>',
        selectedDate: '<?= htmlspecialchars($days[0]['date'] ?? date('Y-m-d')) ?>',
        selectedSlot: '',
        selectedSlotLabel: '',
        morningSlots: [],
        eveningSlots: [],
        loadingSlots: false,
        phone: '',
        name: '',
        foundName: '',
        lookupResult: null,
        submitting: false,

        init() { this.loadSlots(); },

        selectDate(d) {
            this.selectedDate = d;
            this.selectedSlot = '';
            this.loadSlots();
        },

        goNext() {
            if (!this.selectedSlot) return;
            this.step = 2;
            // Scroll the widget into view on mobile (where the right column
            // sits below the left), so users see the form they just opened.
            if (window.innerWidth < 1024) {
                this.$el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },

        async loadSlots() {
            if (!this.doctorId || !this.selectedDate) return;
            this.loadingSlots = true;
            try {
                const r = await fetch('/book/<?= htmlspecialchars($slug) ?>/slots?doctor_id=' + this.doctorId + '&date=' + this.selectedDate);
                const data = await r.json();
                const all = (data.slots || []).map(s => ({
                    ...s,
                    label: this._formatTime(s.time),
                    hour: parseInt(s.time.split(':')[0], 10),
                }));
                this.morningSlots = all.filter(s => s.hour < 13);
                this.eveningSlots = all.filter(s => s.hour >= 13);
            } catch (e) {
                this.morningSlots = [];
                this.eveningSlots = [];
            } finally {
                this.loadingSlots = false;
            }
        },

        async lookupPatient() {
            if (!this.phone || this.phone.length < 6) return;
            try {
                const r = await fetch('/book/<?= htmlspecialchars($slug) ?>/lookup?phone=' + encodeURIComponent(this.phone));
                const data = await r.json();
                if (data.found) {
                    this.foundName = data.name;
                    this.name = data.name;
                    this.lookupResult = 'found';
                } else {
                    this.lookupResult = 'not_found';
                }
            } catch (e) {
                this.lookupResult = null;
            }
        },

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
</body>
</html>
