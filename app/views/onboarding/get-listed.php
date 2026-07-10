<?php
$title = 'Listed on eClinicPro';
$clinic = $clinic ?? [];
$latest = $latest ?? null;
$specialties = $specialties ?? [];
$msg = $message ?? null;
$status = $latest['status'] ?? null;
$listingStatus = $listingStatus ?? ['state' => 'none', 'reason' => null];
$row = $listing ?? null;            // directory_doctors row when approved
$state = $listingStatus['state'] ?? 'none';
$isApproved = $state === 'approved';
$languages = [];
if (!empty($row['languages'])) {
    $decoded = json_decode((string) $row['languages'], true);
    if (is_array($decoded)) {
        $languages = array_values(array_filter(array_map(
            static fn ($l): string => trim((string) $l),
            $decoded
        ), static fn (string $l): bool => $l !== ''));
    }
}
ob_start();
?>

<div class="mx-auto max-w-2xl space-y-6">

    <header>
        <a href="/dashboard" class="text-sm text-slate-500 hover:text-slate-900">← Back to dashboard</a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Listed on eClinicPro</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?php if ($isApproved): ?>
                Your clinic is live at
                <a href="https://eclinicpro.com/find-a-doctor" target="_blank" class="font-medium text-emerald-700 hover:underline">eclinicpro.com/find-a-doctor</a>.
                Edit your public profile below — changes go live immediately.
            <?php else: ?>
                Show up in patient searches at
                <a href="https://eclinicpro.com/find-a-doctor" target="_blank" class="font-medium text-emerald-700 hover:underline">eclinicpro.com/find-a-doctor</a>
                and start receiving booking requests.
            <?php endif; ?>
        </p>
    </header>

    <?php if ($msg === 'saved'): ?>
    <div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-800">
        Your public profile was updated.
    </div>
    <?php endif; ?>

    <?php if ($isApproved): ?>
    <!-- ============ APPROVED → edit the live public profile ============ -->
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">✓ Listed &amp; live</span>
    </div>

    <?php if ($row === null): ?>
    <div class="rounded-xl border bg-white p-5 text-sm text-slate-600 shadow-sm">
        Your clinic is listed. The editable public profile will appear here shortly.
    </div>
    <?php else: ?>
    <form method="post" action="/listing/save" class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Doctor name (shown publicly)</span>
            <input type="text" name="doctor_name" maxlength="160"
                   value="<?= htmlspecialchars((string) ($row['doctor_name'] ?? '')) ?>"
                   placeholder="e.g. Dr. Mitesh Prajapati"
                   class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
        </label>

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">About / bio</span>
            <textarea name="bio" rows="4" maxlength="2000"
                      placeholder="Tell patients about your practice, experience, and approach."
                      class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none"><?= htmlspecialchars((string) ($row['bio'] ?? '')) ?></textarea>
        </label>

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Address</span>
            <textarea name="address" rows="2" maxlength="500"
                      class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none"><?= htmlspecialchars((string) ($row['address'] ?? '')) ?></textarea>
        </label>

        <?php
        $picker = $locationPicker ?? ['states' => [], 'citiesByState' => []];
        $pickerJson = htmlspecialchars(json_encode($picker, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $selectedLocJson = htmlspecialchars(json_encode([
            'state' => (string) ($row['state'] ?? ''),
            'city'  => (string) ($row['city'] ?? ''),
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="grid gap-4 sm:grid-cols-2" x-data="ecpStateCityPicker(<?= $pickerJson ?>, <?= $selectedLocJson ?>)">
            <input type="hidden" name="state" :value="stateName" required>
            <input type="hidden" name="city" :value="cityName" required>

            <div class="block relative" @click.outside="stateOpen = false">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">State *</span>
                <button type="button" @click="stateOpen = !stateOpen; cityOpen = false; $nextTick(() => $refs.stateQ && $refs.stateQ.focus())"
                        class="mt-1.5 flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-left text-sm focus:border-emerald-500 focus:outline-none">
                    <span :class="stateName ? 'text-slate-900' : 'text-slate-400'" x-text="stateName || 'Select state…'"></span>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="stateOpen" x-cloak
                     class="absolute z-20 mt-1 max-h-56 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                    <div class="border-b border-slate-100 p-2">
                        <input type="search" x-ref="stateQ" x-model="stateQuery" placeholder="Search state…"
                               class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm focus:border-emerald-500 focus:outline-none">
                    </div>
                    <ul class="max-h-44 overflow-y-auto py-1">
                        <template x-for="s in filteredStates" :key="s.id">
                            <li>
                                <button type="button" @click="pickState(s)"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
                                        :class="Number(stateId) === Number(s.id) ? 'bg-emerald-50 font-medium text-emerald-800' : 'text-slate-700'"
                                        x-text="s.name"></button>
                            </li>
                        </template>
                        <li x-show="filteredStates.length === 0" class="px-3 py-2 text-sm text-slate-400">No states found</li>
                    </ul>
                </div>
            </div>

            <div class="block relative" @click.outside="cityOpen = false">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">City *</span>
                <button type="button"
                        @click="if (stateId) { cityOpen = !cityOpen; stateOpen = false; $nextTick(() => $refs.cityQ && $refs.cityQ.focus()) }"
                        :disabled="!stateId"
                        class="mt-1.5 flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-left text-sm focus:border-emerald-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400">
                    <span :class="cityName ? 'text-slate-900' : 'text-slate-400'"
                          x-text="cityName || (stateId ? 'Select city…' : 'Select state first')"></span>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="cityOpen" x-cloak
                     class="absolute z-20 mt-1 max-h-56 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                    <div class="border-b border-slate-100 p-2">
                        <input type="search" x-ref="cityQ" x-model="cityQuery" placeholder="Search city…"
                               class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm focus:border-emerald-500 focus:outline-none">
                    </div>
                    <ul class="max-h-44 overflow-y-auto py-1">
                        <template x-for="c in filteredCities" :key="c.id">
                            <li>
                                <button type="button" @click="pickCity(c)"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
                                        :class="Number(cityId) === Number(c.id) ? 'bg-emerald-50 font-medium text-emerald-800' : 'text-slate-700'"
                                        x-text="c.name"></button>
                            </li>
                        </template>
                        <li x-show="filteredCities.length === 0" class="px-3 py-2 text-sm text-slate-400">No cities for this state</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Consultation fee (<?= htmlspecialchars((string) ($row['consultation_fee_currency'] ?? 'INR')) ?>)</span>
                <input type="number" name="consultation_fee" min="0" step="1"
                       value="<?= htmlspecialchars((string) ($row['consultation_fee'] ?? '')) ?>"
                       placeholder="e.g. 500"
                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Website</span>
                <input type="url" name="website" maxlength="500"
                       value="<?= htmlspecialchars((string) ($row['website'] ?? '')) ?>"
                       placeholder="https://"
                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            </label>
        </div>

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Services offered</span>
            <textarea name="services_text" rows="5"
                      placeholder="One service per line, e.g.&#10;Diabetes management&#10;Thyroid disorders&#10;Diet &amp; lifestyle counselling"
                      class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm focus:border-emerald-500 focus:outline-none"><?= htmlspecialchars(implode("\n", $services ?? [])) ?></textarea>
            <span class="mt-1 block text-xs text-slate-500">Up to 24. Each line becomes one item under “Treatments &amp; services” on your public profile. Leave blank to show common treatments for your specialty.</span>
        </label>

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Languages spoken</span>
            <textarea name="languages_text" rows="3"
                      placeholder="One language per line, e.g.&#10;English&#10;Hindi&#10;Gujarati"
                      class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm focus:border-emerald-500 focus:outline-none"><?= htmlspecialchars(implode("\n", $languages)) ?></textarea>
            <span class="mt-1 block text-xs text-slate-500">Up to 12 languages. These appear on your public listing profile.</span>
        </label>

        <div class="flex items-center justify-between">
            <a href="https://eclinicpro.com/find-a-doctor" target="_blank" class="text-sm text-emerald-700 hover:underline">View public page →</a>
            <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                Save public profile
            </button>
        </div>
    </form>
    <?php endif; ?>

    <?php else: /* not approved → application / status flow below */ ?>

    <!-- Flash messages -->
    <?php if ($msg === 'submitted'): ?>
    <div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 p-5">
        <h3 class="flex items-center gap-2 font-semibold text-emerald-900">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Request submitted
        </h3>
        <p class="mt-2 text-sm text-emerald-800">
            Our team will review within 1–2 business days. We'll text you on
            <strong><?= htmlspecialchars((string) ($clinic['phone'] ?? '')) ?></strong>
            when your clinic goes live.
        </p>
    </div>
    <?php elseif ($msg === 'failed'): ?>
    <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800">
        Something went wrong. Please try again or contact support.
    </div>
    <?php endif; ?>

    <!-- Status of existing request (if any) -->
    <?php if ($latest && in_array($status, ['pending','phone_verified'], true)): ?>
    <div class="rounded-xl border bg-white p-5 shadow-sm">
        <h3 class="flex items-center gap-2 font-semibold text-slate-900">
            <span class="inline-flex h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
            Application under review
        </h3>
        <p class="mt-2 text-sm text-slate-600">
            We received your application on
            <strong><?= htmlspecialchars(date('M j, Y', strtotime((string) $latest['created_at']))) ?></strong>.
            Our team is reviewing — most decisions land within 1–2 business days.
        </p>
        <p class="mt-2 text-xs text-slate-500">
            Reference #<?= (int) $latest['id'] ?>
        </p>
    </div>
    <?php elseif ($latest && $status === 'rejected'): ?>
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-5">
        <h3 class="font-semibold text-rose-900">Previous application rejected</h3>
        <?php if (!empty($latest['reviewer_notes'])): ?>
        <p class="mt-2 text-sm text-rose-800">
            <strong>Reason:</strong> <?= nl2br(htmlspecialchars((string) $latest['reviewer_notes'])) ?>
        </p>
        <?php endif; ?>
        <p class="mt-2 text-sm text-rose-800">
            You can re-apply below with corrected details.
        </p>
    </div>
    <?php endif; ?>

    <!-- The form. Show even if rejected (so they can re-apply). Hide if a
         pending request exists. -->
    <?php if (!$latest || !in_array($status, ['pending','phone_verified'], true)): ?>

    <!-- What patients will see preview -->
    <section class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Preview</p>
        <p class="mt-1 text-sm text-slate-600">Here's a rough idea of what patients will see. Edit the fields below to refine.</p>
        <div class="mt-3 flex items-start gap-4 rounded-xl border bg-white p-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-lg font-bold text-emerald-700">
                <?= htmlspecialchars(strtoupper(mb_substr($clinic['name'] ?? 'C', 0, 1))) ?>
            </div>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($clinic['name'] ?? 'Your clinic')) ?></div>
                <div class="text-xs text-slate-500" id="preview-location">
                    <?= htmlspecialchars((string) ($clinic['address'] ?? '—')) ?>
                </div>
                <div class="mt-1 text-xs">
                    <span class="rounded bg-emerald-100 px-1.5 py-0.5 font-semibold text-emerald-800">✓ Verified by doctor</span>
                </div>
            </div>
        </div>
    </section>

    <form method="post" action="/listing/apply" class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Locked: we trust the tenant's own phone/email/clinic name -->
        <div class="rounded-lg bg-slate-50 p-4">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Account details (verified)</h3>
            <dl class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Clinic name</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($clinic['name'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Phone</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($clinic['phone'] ?? '—')) ?></dd>
                </div>
                <?php if (!empty($clinic['email'])): ?>
                <div>
                    <dt class="text-slate-500">Email</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars((string) $clinic['email']) ?></dd>
                </div>
                <?php endif; ?>
            </dl>
            <p class="mt-3 text-xs text-slate-500">
                To change any of these, update them under <a href="/settings?tab=general" class="text-emerald-700 hover:underline">Settings → General</a> first.
            </p>
        </div>

        <!-- Doctor name -->
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Doctor's full name *</span>
            <input type="text" name="full_name" required maxlength="120"
                   value="<?= htmlspecialchars((string) ($ownerName ?? '')) ?>"
                   class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            <span class="mt-1 block text-xs text-slate-500">e.g. Dr. Riya Mehta — appears as the doctor in your public listing.</span>
        </label>

        <!-- State then City (searchable, cascading) -->
        <?php
        $picker = $locationPicker ?? ['states' => [], 'citiesByState' => []];
        $pickerJson = htmlspecialchars(json_encode($picker, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="grid gap-4 sm:grid-cols-2" x-data="ecpStateCityPicker(<?= $pickerJson ?>)">
            <input type="hidden" name="state" :value="stateName" required>
            <input type="hidden" name="city" :value="cityName" required>

            <div class="block relative" @click.outside="stateOpen = false">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">State *</span>
                <button type="button" @click="stateOpen = !stateOpen; cityOpen = false; $nextTick(() => $refs.stateQ && $refs.stateQ.focus())"
                        class="mt-1.5 flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-left text-sm focus:border-emerald-500 focus:outline-none">
                    <span :class="stateName ? 'text-slate-900' : 'text-slate-400'" x-text="stateName || 'Select state…'"></span>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="stateOpen" x-cloak
                     class="absolute z-20 mt-1 max-h-56 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                    <div class="border-b border-slate-100 p-2">
                        <input type="search" x-ref="stateQ" x-model="stateQuery" placeholder="Search state…"
                               class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm focus:border-emerald-500 focus:outline-none">
                    </div>
                    <ul class="max-h-44 overflow-y-auto py-1">
                        <template x-for="s in filteredStates" :key="s.id">
                            <li>
                                <button type="button" @click="pickState(s)"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
                                        :class="Number(stateId) === Number(s.id) ? 'bg-emerald-50 font-medium text-emerald-800' : 'text-slate-700'"
                                        x-text="s.name"></button>
                            </li>
                        </template>
                        <li x-show="filteredStates.length === 0" class="px-3 py-2 text-sm text-slate-400">No states found</li>
                    </ul>
                </div>
                <p class="mt-1 text-xs text-rose-600" x-show="triedSubmit && !stateName">Please select a state.</p>
            </div>

            <div class="block relative" @click.outside="cityOpen = false">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">City *</span>
                <button type="button"
                        @click="if (stateId) { cityOpen = !cityOpen; stateOpen = false; $nextTick(() => $refs.cityQ && $refs.cityQ.focus()) }"
                        :disabled="!stateId"
                        class="mt-1.5 flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-left text-sm focus:border-emerald-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400">
                    <span :class="cityName ? 'text-slate-900' : 'text-slate-400'"
                          x-text="cityName || (stateId ? 'Select city…' : 'Select state first')"></span>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="cityOpen" x-cloak
                     class="absolute z-20 mt-1 max-h-56 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                    <div class="border-b border-slate-100 p-2">
                        <input type="search" x-ref="cityQ" x-model="cityQuery" placeholder="Search city…"
                               class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm focus:border-emerald-500 focus:outline-none">
                    </div>
                    <ul class="max-h-44 overflow-y-auto py-1">
                        <template x-for="c in filteredCities" :key="c.id">
                            <li>
                                <button type="button" @click="pickCity(c)"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
                                        :class="Number(cityId) === Number(c.id) ? 'bg-emerald-50 font-medium text-emerald-800' : 'text-slate-700'"
                                        x-text="c.name"></button>
                            </li>
                        </template>
                        <li x-show="filteredCities.length === 0" class="px-3 py-2 text-sm text-slate-400">No cities for this state</li>
                    </ul>
                </div>
                <p class="mt-1 text-xs text-rose-600" x-show="triedSubmit && !cityName">Please select a city.</p>
            </div>
        </div>

        <!-- Specialty -->
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Specialty *</span>
            <select name="specialty" required
                    class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
                <option value="">Choose your primary specialty…</option>
                <?php foreach ($specialties as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>"
                        <?= (($clinic['specialty'] ?? '') === $key) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <span class="mt-1 block text-xs text-slate-500">
                Patients will find you when they search for this specialty.
            </span>
        </label>

        <!-- Optional registration -->
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Registration number <span class="font-normal normal-case text-slate-400">(optional)</span></span>
                <input type="text" name="reg_number" maxlength="60"
                       placeholder="e.g. G-12345"
                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Issued by <span class="font-normal normal-case text-slate-400">(optional)</span></span>
                <input type="text" name="reg_council" maxlength="80"
                       placeholder="Gujarat Medical Council"
                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            </label>
        </div>

        <!-- Optional message -->
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Anything we should know? <span class="font-normal normal-case text-slate-400">(optional)</span></span>
            <textarea name="message" rows="2" maxlength="2000"
                      placeholder="e.g. I run two clinics in Ahmedabad — both should appear."
                      class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none"></textarea>
        </label>

        <!-- Trust note -->
        <p class="text-xs text-slate-500">
            🔒 Our team reviews every application within 1–2 business days to keep eClinicPro trusted.
            We may text you at <strong><?= htmlspecialchars((string) ($clinic['phone'] ?? '')) ?></strong> for verification.
        </p>

        <div class="flex items-center justify-between">
            <a href="/dashboard" class="text-sm text-slate-500 hover:text-slate-900">Cancel</a>
            <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                Submit for review
            </button>
        </div>
    </form>

    <?php endif; /* apply-form visibility */ ?>

    <?php endif; /* approved vs not-approved */ ?>
</div>

<script>
function ecpStateCityPicker(payload, selected) {
  const states = Array.isArray(payload?.states) ? payload.states : [];
  const citiesByState = payload?.citiesByState && typeof payload.citiesByState === 'object'
    ? payload.citiesByState : {};
  const initialState = String(selected?.state || '').trim();
  const initialCity = String(selected?.city || '').trim();

  let stateId = '';
  let stateName = '';
  let cityId = '';
  let cityName = '';

  if (initialState !== '') {
    const matchState = states.find((s) => String(s.name || '').toLowerCase() === initialState.toLowerCase());
    if (matchState) {
      stateId = String(matchState.id);
      stateName = String(matchState.name || '');
      const cities = citiesByState[stateId] || [];
      if (initialCity !== '') {
        const matchCity = cities.find((c) => String(c.name || '').toLowerCase() === initialCity.toLowerCase());
        if (matchCity) {
          cityId = String(matchCity.id);
          cityName = String(matchCity.name || '');
        } else {
          cityName = initialCity;
        }
      }
    } else {
      stateName = initialState;
      cityName = initialCity;
    }
  }

  return {
    states,
    citiesByState,
    stateId,
    stateName,
    cityId,
    cityName,
    stateOpen: false,
    cityOpen: false,
    stateQuery: '',
    cityQuery: '',
    triedSubmit: false,
    get filteredStates() {
      const q = this.stateQuery.trim().toLowerCase();
      if (!q) return this.states;
      return this.states.filter(s => String(s.name || '').toLowerCase().includes(q));
    },
    get filteredCities() {
      const list = this.citiesByState[String(this.stateId)] || [];
      const q = this.cityQuery.trim().toLowerCase();
      if (!q) return list;
      return list.filter(c => String(c.name || '').toLowerCase().includes(q));
    },
    pickState(s) {
      this.stateId = String(s.id);
      this.stateName = s.name || '';
      this.stateOpen = false;
      this.stateQuery = '';
      this.cityId = '';
      this.cityName = '';
      this.cityQuery = '';
    },
    pickCity(c) {
      this.cityId = String(c.id);
      this.cityName = c.name || '';
      this.cityOpen = false;
      this.cityQuery = '';
    },
  };
}

// Block submit until state + city are chosen (hidden required inputs alone
// are unreliable across browsers when empty).
document.addEventListener('submit', (e) => {
  const form = e.target;
  if (!(form instanceof HTMLFormElement)) return;
  const action = form.getAttribute('action') || '';
  if (action !== '/listing/apply' && action !== '/listing/save') return;
  const root = form.querySelector('[x-data*="ecpStateCityPicker"]');
  if (!root || typeof Alpine === 'undefined' || typeof Alpine.$data !== 'function') return;
  const data = Alpine.$data(root);
  if (!data) return;
  data.triedSubmit = true;
  if (!data.stateName || !data.cityName) {
    e.preventDefault();
  }
}, true);
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
