<?php
$isConfirmed = !empty($confirmation);
$embedMode = $embedMode ?? false;
$bookingError = $bookingError ?? null;
$slotGridClass = $embedMode ? 'pb-slot-grid pb-slot-grid--2' : 'grid grid-cols-3 gap-2';
$cardClass = 'dp-book-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm' . ($embedMode ? ' dp-book-embed' : '');
?>
            <div class="<?= $cardClass ?>">

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
                    <a href="<?= htmlspecialchars((string) ($bookConfig['returnTo'] ?? $bookConfig['patientPanelUrl'] ?? '/find-a-doctor')) ?>"
                       class="block w-full rounded-xl bg-brand py-3 text-center text-sm font-semibold text-white hover:opacity-90">
                        Book another appointment
                    </a>
                </div>

                <?php else: ?>
                <!-- Active wizard -->
                <div class="<?= $embedMode ? 'bg-brand-50 px-4 pt-4 pb-3' : 'bg-brand-50 px-5 pt-5 pb-3 sm:px-6' ?>">
                    <h2 class="text-base font-bold text-slate-900">Choose your appointment</h2>
                    <p class="mt-0.5 text-xs text-slate-600">Same-day slots available. No advance payment needed.</p>

                    <?php if (empty($embedMode)): ?>
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
                    <?php endif; ?>
                </div>

                <?php if ($bookingError): ?>
                <div class="mx-5 mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800 sm:mx-6">
                    ⚠️ <?= htmlspecialchars($bookingError) ?>
                </div>
                <?php endif; ?>

                <div class="<?= $embedMode ? 'p-4' : 'p-5 sm:p-6' ?>" x-data="bookingWizard()" x-init="init()">
                    <?php require __DIR__ . '/_stepper.php'; ?>

                    <form method="post" action="<?= htmlspecialchars($bookConfig['formAction']) ?>" @submit="submitting = true" class="mt-5">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <?php if (!empty($bookConfig['returnTo'])): ?>
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars((string) $bookConfig['returnTo']) ?>">
                        <?php endif; ?>
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
                                            class="flex shrink-0 flex-col items-center rounded-xl border-2 px-2.5 py-2 transition <?= $embedMode ? 'min-w-[54px]' : 'min-w-[68px]' ?>">
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
                            <div x-show="!loadingSlots && allSlots.length === 0" x-cloak
                                 class="rounded-xl bg-amber-50 px-3 py-4 text-center text-sm text-amber-800">
                                <span x-show="slotMeta && slotMeta.in_window === false">This date is outside the online booking window.</span>
                                <span x-show="slotMeta && slotMeta.in_window !== false && slotMeta.schedule_rows === 0">No slots on this day — the clinic may be closed. Try a weekday (Mon–Sat).</span>
                                <span x-show="!slotMeta || (slotMeta.in_window !== false && slotMeta.schedule_rows !== 0)">No slots available right now. Try another date.</span>
                            </div>

                            <div x-show="!loadingSlots && allSlots.length > 0 && morningSlots.filter(s => s.available).length === 0 && eveningSlots.filter(s => s.available).length === 0" x-cloak
                                 class="rounded-xl bg-slate-50 px-3 py-4 text-center text-sm text-slate-600">
                                All slots are full or past for this day. Try another date.
                            </div>

                            <div x-show="allSlots.length > 0 && morningSlots.length > 0" x-cloak>
                                <p class="text-xs font-semibold text-slate-600">
                                    <span class="text-amber-500">☀️</span> Morning
                                    <span class="ml-1 font-normal text-slate-400">(<span x-text="morningSlots.filter(s => s.available).length"></span> slots)</span>
                                </p>
                                <div class="mt-2 <?= $slotGridClass ?>">
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

                            <div x-show="allSlots.length > 0 && eveningSlots.length > 0" x-cloak>
                                <p class="text-xs font-semibold text-slate-600">
                                    <span class="text-indigo-500">🌙</span> Evening
                                    <span class="ml-1 font-normal text-slate-400">(<span x-text="eveningSlots.filter(s => s.available).length"></span> slots)</span>
                                </p>
                                <div class="mt-2 <?= $slotGridClass ?>">
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

                            <!-- ===== Logged-in patient ===== -->
                            <template x-if="loggedIn">
                                <div class="rounded-lg bg-emerald-50 px-3 py-3 text-sm">
                                    <p class="font-semibold text-emerald-800">👋 Booking as <span x-text="name"></span></p>
                                    <p class="mt-0.5 text-xs text-emerald-700">
                                        <span x-text="phone"></span> · using your eClinicPro profile.
                                        <button type="button" @click="logoutPatient()" class="ml-1 text-emerald-900 underline">Switch account</button>
                                    </p>
                                    <input type="hidden" name="phone" :value="phoneRaw">
                                    <input type="hidden" name="name" :value="name">
                                </div>
                            </template>

                            <!-- ===== Not logged in: Sign in / Create account (same as main site) ===== -->
                            <template x-if="!loggedIn">
                                <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Sign in to continue</p>
                                        <p class="mt-0.5 text-xs text-slate-600">Use your eClinicPro patient account — we'll fill your name and mobile automatically.</p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-1 rounded-lg border border-slate-200 bg-white p-1" x-show="authStep === 'phone'">
                                        <button type="button"
                                                :class="authIntent === 'signin' ? 'bg-brand text-white' : 'text-slate-600'"
                                                @click="authIntent = 'signin'; authError = ''"
                                                class="rounded-md py-1.5 text-xs font-semibold">Sign in</button>
                                        <button type="button"
                                                :class="authIntent === 'signup' ? 'bg-brand text-white' : 'text-slate-600'"
                                                @click="authIntent = 'signup'; authError = ''"
                                                class="rounded-md py-1.5 text-xs font-semibold">Create account</button>
                                    </div>

                                    <div x-show="authStep === 'phone'" class="space-y-3">
                                        <label class="block">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mobile number <span class="text-rose-500">*</span></span>
                                            <div class="mt-1.5 flex overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-brand focus-within:ring-2 ring-brand">
                                                <span class="flex items-center border-r border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">+91</span>
                                                <input type="tel" x-model="authPhoneDigits" inputmode="numeric" maxlength="10"
                                                       @input="authPhoneDigits = authPhoneDigits.replace(/\D/g, '').slice(0, 10)"
                                                       placeholder="98XXXXXXXX"
                                                       class="w-full border-0 px-3 py-2.5 text-sm focus:outline-none focus:ring-0">
                                            </div>
                                        </label>
                                        <p class="text-xs text-rose-700" x-show="authError" x-text="authError"></p>
                                        <button type="button" @click="sendAuthOtp()" :disabled="authBusy || authPhoneDigits.length < 10"
                                                class="w-full rounded-lg bg-brand py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                                            <span x-show="!authBusy" x-text="authIntent === 'signup' ? 'Send code to create account' : 'Send sign-in code'"></span>
                                            <span x-show="authBusy" x-cloak>Sending…</span>
                                        </button>
                                    </div>

                                    <div x-show="authStep === 'code'" class="space-y-3" x-cloak>
                                        <button type="button" @click="authStep = 'phone'; authError = ''" class="text-xs text-slate-500 hover:text-brand">← Change number</button>
                                        <template x-if="authExists && authNameHint">
                                            <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                                Welcome back, <strong x-text="authNameHint"></strong> 👋 Enter the code we sent.
                                            </p>
                                        </template>
                                        <template x-if="!authExists">
                                            <label class="block">
                                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Full name <span class="text-rose-500">*</span></span>
                                                <input type="text" x-model="authName" placeholder="Your full name"
                                                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand">
                                            </label>
                                        </template>
                                        <template x-if="authDevCode">
                                            <div class="auth-devcode">
                                                <span class="tag">DEV MODE</span>
                                                Your code: <strong x-text="authDevCode"></strong>
                                            </div>
                                        </template>
                                        <label class="block">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">6-digit code</span>
                                            <input type="text" inputmode="numeric" maxlength="6" x-model="authCode"
                                                   @input="authCode = authCode.replace(/\D/g, '').slice(0, 6)"
                                                   placeholder="••••••"
                                                   class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-center text-lg tracking-widest focus:border-brand focus:outline-none focus:ring-2 ring-brand">
                                        </label>
                                        <p class="text-xs text-rose-700" x-show="authError" x-text="authError"></p>
                                        <button type="button" @click="verifyAuthOtp()" :disabled="authBusy || authCode.length < 6"
                                                class="w-full rounded-lg bg-brand py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                                            <span x-show="!authBusy">Verify &amp; continue</span>
                                            <span x-show="authBusy" x-cloak>Verifying…</span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div x-show="loggedIn" class="space-y-4">
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
                            </div>

                            <div class="flex gap-2 pt-2">
                                <button type="button" @click="step = 1"
                                        class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    ← Back
                                </button>
                                <button type="submit" :disabled="submitting || !loggedIn || !name || !phoneRaw"
                                        class="flex-1 rounded-lg bg-brand py-3 text-sm font-bold text-white shadow-sm transition hover:shadow-md disabled:opacity-50">
                                    <span x-show="!submitting && loggedIn">Confirm booking</span>
                                    <span x-show="!submitting && !loggedIn" x-cloak>Sign in to confirm</span>
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

            <?php if (empty($embedMode)): ?>
            <!-- Footer trust note under widget -->
            <p class="mt-3 text-center text-[11px] text-slate-500">
                🔒 Your details stay private. eClinicPro never shares your number.
            </p>
            <?php endif; ?>
