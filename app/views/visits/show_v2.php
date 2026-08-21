<?php
/**
 * visits/show_v2.php — Phase 2 single-screen visit layout.
 *
 * No tabs. The 4 fundamentals (Symptoms / Diagnosis / Prescription / Notes)
 * are always visible. Optional sections (Vitals, Diet, Case form) render
 * based on $visibleModules. Hidden sections live as ghost-link chips at
 * the bottom — tap to reveal for this visit only.
 *
 * Ships behind ?new=1 until the default is flipped in VisitController::show().
 */
use App\Services\PrescriptionService;
use App\Support\RxFormHelper;

$sd = $visit['specialty_data'] ?? [];
$case = $caseTaking ?? ($sd['case_taking'] ?? []);

// $visibleModules comes from VisitView::visibleModules() in the controller.
$visibleModules = $visibleModules ?? ['vitals', 'case_specialty'];
$has = static fn (string $key) => in_array($key, $visibleModules, true);

// Symptoms / Diagnosis / Prescription / Notes are always rendered.
// case_specialty depends on specialty config AND on the partial existing.
$casePartialPath = __DIR__ . '/partials/' . $casePartial . '.php';
$caseAvailable = is_file($casePartialPath);

$visitId = (int) $visit['id'];
$visibleCount = count($visibleModules);
$canUnlock = !empty($canUnlock);

// Ghost-link list: every optional section NOT in visible_modules (see VisitView::OPTIONAL).
$optionalModules = ['vitals', 'diet', 'case_specialty'];
$ghostModules = array_values(array_filter($optionalModules, static fn ($m) => !in_array($m, $visibleModules, true)));
?>

<div class="mx-auto max-w-7xl space-y-4"
     x-data="visitScreenV2(<?= htmlspecialchars(json_encode([
        'visitId' => $visitId,
        'patientId' => (int) $patient['id'],
        'editable' => $editable,
        'chief_complaint' => $visit['chief_complaint'] ?? '',
        'history' => $visit['history'] ?? '',
        'examination' => $visit['examination'] ?? '',
        'diagnosis' => $visit['diagnosis'] ?? '',
        'icd10_code' => $visit['icd10_code'] ?? '',
        'clinical_notes' => $visit['clinical_notes'] ?? '',
        'reports_notes' => $visit['reports_notes'] ?? '',
        'condition_score' => $visit['condition_score'] ?? 5,
        'follow_up_date' => $visit['follow_up_date'] ?? '',
        'follow_up_reason' => $pendingFollowUp['reason'] ?? '',
        'voiceLang' => $voiceLang ?? 'en-IN',
        'follow_up_notes' => $visit['follow_up_notes'] ?? '',
        // datetime-local wants "YYYY-MM-DDTHH:MM"
        'visited_at' => !empty($visit['visited_at']) ? date('Y-m-d\TH:i', strtotime((string) $visit['visited_at'])) : '',
        'vitals' => $vitals,
        'prescriptions' => array_values(array_map(static function (array $r, int $idx) {
            $name = $r['drug']['name'] ?? $r['remedy']['name'] ?? trim((string) ($r['dosage'] ?? ''));
            $catalogForm = isset($r['drug']['form']) ? (string) $r['drug']['form'] : null;
            $doseUnit = isset($r['dose_unit']) ? (string) $r['dose_unit'] : null;
            $taperRaw = $r['tapering_steps'] ?? null;
            $tapering = null;
            if (is_string($taperRaw) && $taperRaw !== '') {
                $decoded = json_decode($taperRaw, true);
                $tapering = is_array($decoded) ? $decoded : null;
            } elseif (is_array($taperRaw)) {
                $tapering = $taperRaw;
            }
            if (is_array($tapering)) {
                $lineDose = isset($r['dose_amount']) && $r['dose_amount'] !== ''
                    ? (float) $r['dose_amount'] : null;
                $tapering = PrescriptionService::hydrateTaperingSteps($tapering, $lineDose);
            }

            $drugForm = RxFormHelper::inferForm($catalogForm, $doseUnit, $name);
            $preset = trim((string) ($r['frequency_preset'] ?? ''));
            if ($preset === '') {
                $legacy = trim((string) ($r['frequency'] ?? ''));
                $preset = $legacy !== ''
                    ? RxFormHelper::presetFromLegacy($legacy, $drugForm)
                    : (RxFormHelper::defaultLineDefaults($drugForm)['frequency_preset'] ?? '1-0-1');
            }

            return [
                '_rxKey' => 'rx' . ((int) ($r['id'] ?? 0) ?: ('t' . $idx)),
                'drug_id' => $r['drug_id'] ?? null,
                'remedy_id' => $r['remedy_id'] ?? null,
                'drug_name' => $name,
                'potency' => $r['potency'] ?? '',
                'dosage' => $r['dosage'] ?? '',
                'dose_unit' => $doseUnit ?? '',
                'dose_amount' => $r['dose_amount'] ?? '',
                'mix_with' => $r['mix_with'] ?? '',
                'drug_form' => $drugForm,
                'frequency_preset' => $preset,
                'frequency' => $r['frequency'] ?? RxFormHelper::legacyFrequency(null, $preset),
                'duration_days' => $r['duration_days'] ?? '',
                'food_timing' => $r['food_timing'] ?? 'any',
                'instructions' => $r['instructions'] ?? '',
                'tapering_steps' => $tapering,
            ];
        }, $prescriptions, array_keys($prescriptions))),
        'specialty_data' => $sd,
        'case_taking' => $case,
        'useHomeo' => $useHomeo,
        'visibleModules' => $visibleModules,
        'ghostRevealed' => [],   // sections the doctor revealed this visit
        'symptoms' => $visitSymptoms ?? [],   // hydrated by symptomPicker on mount
        'charges' => $charges ?? [],   // existing invoice line items {description, amount}
        'chargesPrefilled' => !empty($chargesPrefilled),
        'payment' => $payment ?? ['amount' => 0, 'gst' => false, 'tax_percent' => 18, 'type' => 'cash', 'status' => 'due', 'paid_amount' => 0, 'due' => 0],
        'invoiceId' => !empty($visitInvoice) ? (int) $visitInvoice['id'] : null,
        'invoiceNumber' => !empty($visitInvoice['invoice_number']) ? (string) $visitInvoice['invoice_number'] : null,
        'invoiceDate' => !empty($visitInvoice['created_at']) ? date('d M Y', strtotime((string) $visitInvoice['created_at'])) : null,
    ], JSON_THROW_ON_ERROR), ENT_QUOTES) ?>)"
     x-init="initVisitScreen()">

    <!-- ====== Patient header (sticky on scroll) ====== -->
    <div class="sticky top-0 z-30 -mx-4 bg-slate-50/95 px-4 pb-2 pt-2 backdrop-blur md:mx-0 md:rounded-xl md:bg-transparent md:px-0">
        <?php
        $visitCount = is_array($recentVisits ?? null) ? count($recentVisits) + 1 : null;
        require __DIR__ . '/../patients/_patient_header.php';
        ?>
    </div>

    <?php
    // Post-completion action bar: print / WhatsApp / follow-up, right where
    // the doctor lands after "Complete visit". wa.me opens a prefilled chat
    // (works with WhatsApp Web/app, no Meta template approval needed).
    $rxSummaryLines = [];
    foreach ($prescriptions as $rxLine) {
        $rxName = $rxLine['drug']['name'] ?? $rxLine['remedy']['name'] ?? '';
        if ($rxName === '') {
            continue;
        }
        $bits = [$rxName];
        if (!empty($rxLine['frequency_preset'])) {
            $bits[] = (string) $rxLine['frequency_preset'];
        } elseif (!empty($rxLine['frequency'])) {
            $bits[] = (string) $rxLine['frequency'];
        }
        if (!empty($rxLine['duration_days'])) {
            $bits[] = ((int) $rxLine['duration_days']) . ' days';
        }
        $rxSummaryLines[] = count($rxSummaryLines) + 1 . '. ' . implode(' — ', $bits);
    }
    $waPhone = preg_replace('/[^0-9]/', '', (string) ($patient['phone'] ?? '')) ?? '';
    if (strlen($waPhone) === 10) {
        $waPhone = '91' . $waPhone; // bare Indian mobile — add country code
    }
    $waText = 'Prescription for ' . ($patient['name'] ?? '')
        . ' (' . date('d M Y', strtotime((string) ($visit['visited_at'] ?? 'now'))) . ")\n"
        . implode("\n", $rxSummaryLines)
        . "\n— " . ($visit['doctor_name'] ?? '');
    $waHref = 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($waText);
    ?>
    <?php if (!empty($_GET['complete_save_error'])): ?>
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        Could not save your notes before completing the visit. The visit was <strong>not</strong> completed — please click <strong>Save now</strong>, fix any errors, then try again.
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['completed']) && !$editable): ?>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
        <p class="text-sm font-medium text-emerald-800">✓ Visit completed.</p>
        <div class="flex flex-wrap gap-2">
            <?php if ($rxSummaryLines !== []): ?>
            <a href="/prescriptions/<?= $visitId ?>/pdf" target="_blank" class="ui-btn ui-btn-primary ui-btn-sm">Print prescription (A5)</a>
            <a href="/prescriptions/<?= $visitId ?>/pdf?paper=a4" target="_blank" class="ui-btn ui-btn-secondary ui-btn-sm">A4</a>
            <?php if ($waPhone !== ''): ?>
            <a href="<?= htmlspecialchars($waHref) ?>" target="_blank" rel="noopener" class="ui-btn ui-btn-secondary ui-btn-sm">Share on WhatsApp</a>
            <?php endif; ?>
            <?php endif; ?>
            <a href="/appointments/new?patient_id=<?= (int) $patient['id'] ?>" class="ui-btn ui-btn-secondary ui-btn-sm">Book follow-up</a>
            <?php if (!empty($visitInvoice)): ?>
            <a href="/billing/<?= (int) $visitInvoice['id'] ?>" class="text-sm font-medium text-brand hover:underline">
                <?= htmlspecialchars((string) $visitInvoice['invoice_number']) ?> · <?= htmlspecialchars(date('d M Y', strtotime((string) ($visitInvoice['created_at'] ?? 'now')))) ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ====== Two-column: visit form (left) + history (right) ====== -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">

    <!-- ====== TODAY'S VISIT ====== -->
    <section class="ui-card overflow-visible shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5" x-data="{ editDate: false }">
            <div>
                <div class="flex items-baseline gap-3">
                    <h2 class="ui-section-title">Today's visit</h2>
                    <span class="flex items-center gap-2 text-xs text-slate-400">
                        Visit #<?= (int) $visit['visit_number'] ?> ·
                        <!-- Editable visit date/time (for late catch-up entry) -->
                        <template x-if="!editDate">
                            <button type="button" :disabled="!editable" @click="editDate = true"
                                    class="text-slate-500 hover:text-brand disabled:cursor-default disabled:hover:text-slate-500"
                                    x-text="visited_at ? new Date(visited_at).toLocaleString() : 'Set date'"></button>
                        </template>
                        <template x-if="editDate">
                            <input type="datetime-local" x-model="visited_at" :disabled="!editable"
                                   @change="markDirty()" @blur="editDate = false"
                                   class="rounded border border-slate-300 px-2 py-0.5 text-xs">
                        </template>
                    </span>
                </div>
                <p class="mt-0.5 text-sm" x-show="invoiceId" x-cloak>
                    <a :href="'/billing/' + invoiceId" class="font-medium text-brand hover:underline" x-text="invoiceLinkLabel()"></a>
                </p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span
                    :class="saveStatus === 'saved' ? 'text-emerald-600' : (saveStatus === 'saving' ? 'text-amber-600' : (saveStatus === 'error' ? 'text-rose-600' : 'text-slate-400'))"
                    x-text="saveLabel"></span>
                <?php if ($editable): ?>
                    <button type="button" @click="dirty = true; save()" class="ui-btn ui-btn-primary ui-btn-sm">Save now</button>
                <?php endif; ?>
                <?php if (!$editable): ?>
                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">Read-only</span>
                    <?php if ($canUnlock): ?>
                        <form method="post" action="/visits/<?= $visitId ?>/unlock" class="inline">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="submit" class="rounded-full border border-brand px-2.5 py-0.5 text-xs font-medium text-brand hover:bg-brand-light">Edit this visit</button>
                        </form>
                    <?php else: ?>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">Only clinic admin can edit a completed visit</span>
                    <?php endif; ?>
                    <?php if (!empty($_GET['unlock_error'])): ?>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-700">Unlock denied. Contact clinic admin.</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-3 bg-slate-50/50 p-3">

            <!-- ---- CHIEF COMPLAINT / CASE NOTES — first thing in the visit.
                 Carries forward from the appointment/last visit; the doctor
                 edits it only when the story has changed. ---- -->
            <div class="rounded-lg border border-slate-200 bg-white p-3">
                <label class="ui-group-label" for="chief-complaint">Chief complaint / Case notes</label>
                <p class="ui-help mt-0.5">In the patient's own words — update if it has changed.</p>
                <textarea id="chief-complaint" x-model="chief_complaint" :disabled="!editable" rows="5"
                          @input="markDirty()"
                          placeholder="e.g. Fever with sore throat for 3 days, worse at night. No cough."
                          class="ui-input mt-1.5"></textarea>
            </div>

            <!-- ---- SYMPTOMS — chip picker with autocomplete (Phase 3) ---- -->
            <div x-data="symptomPicker()" class="rounded-lg border border-slate-200 bg-white p-3">
                <label class="ui-group-label">Symptoms</label>

                <!-- Selected chips -->
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2 py-1.5
                            focus-within:border-brand focus-within:ring-1 focus-within:ring-brand">
                    <template x-for="(s, idx) in symptoms" :key="idx">
                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-light px-2.5 py-1 text-sm text-brand">
                            <span x-text="s.label"></span>
                            <button type="button" :disabled="!editable" @click="removeSymptom(idx); persistSymptoms()"
                                    class="text-brand hover:text-rose-600 disabled:opacity-50"
                                    title="Remove">×</button>
                        </span>
                    </template>

                    <input type="text" x-model="query" :disabled="!editable"
                           @input.debounce.250ms="search()"
                           @focus="search()"
                           @keydown.enter.prevent="addCurrentOrFirst()"
                           @keydown.backspace="if (!query) removeSymptom(symptoms.length - 1)"
                           placeholder="Type a symptom and press Enter"
                           class="flex-1 min-w-[180px] border-0 bg-transparent p-0.5 text-sm focus:outline-none focus:ring-0">
                </div>

                <!-- Suggestion dropdown — always shows while typing, searching
                     across ALL systems (independent of the browse panel). -->
                <div x-show="(suggestions.length || catMatches.length) && showSuggestions" x-cloak
                     @click.outside="showSuggestions = false"
                     class="relative">
                    <ul class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-lg border bg-white shadow-lg">
                        <!-- Matching systems (open the browse pills) -->
                        <template x-for="c in catMatches" :key="'sys-' + c.key">
                            <li>
                                <button type="button" @click="pickSystem(c)"
                                        class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-brand-light">
                                    <span class="inline-flex items-center gap-1.5"><span class="text-slate-400"><?= ui_icon('emr', 14) ?></span><span x-text="c.label"></span></span>
                                    <span class="text-xs uppercase tracking-wider text-brand" x-text="'system · ' + c.count"></span>
                                </button>
                            </li>
                        </template>
                        <template x-for="(sug, i) in suggestions" :key="sug.label + i">
                            <li>
                                <button type="button" @click="addSymptom(sug); persistSymptoms()"
                                        class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-brand-light">
                                    <span x-text="sug.label"></span>
                                    <span class="text-xs uppercase tracking-wider"
                                          :class="sug.source === 'personal' ? 'text-brand' : 'text-slate-400'"
                                          x-text="sug.source"></span>
                                </button>
                            </li>
                        </template>
                        <template x-if="query.trim().length >= 2 && !exactMatch(query)">
                            <li class="border-t">
                                <button type="button" @click="addCustom(query); persistSymptoms()"
                                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-brand-light">
                                    + Add <strong x-text="query"></strong> as custom
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                <!-- Browse by system (Review-of-Systems quick picker) -->
                <button type="button" :disabled="!editable" @click="toggleBrowse()"
                        class="mt-2 text-sm font-medium text-brand hover:underline disabled:opacity-50">
                    <span x-text="browseOpen ? '− Hide systems' : '+ Browse by system'"></span>
                </button>
                <div x-show="browseOpen" x-cloak x-collapse class="mt-2 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <!-- Category pills -->
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="c in categories" :key="c.key">
                            <button type="button" @click="openCategory(c.key)"
                                    class="rounded-full border px-2.5 py-1 text-xs transition"
                                    :class="activeCat === c.key ? 'border-brand bg-brand text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-brand'">
                                <span x-text="c.label"></span>
                                <span class="opacity-60" x-text="'· ' + c.count"></span>
                            </button>
                        </template>
                    </div>
                    <!-- Symptom pills for the open category (click to add/remove) -->
                    <div x-show="activeCat" class="mt-3 border-t border-slate-200 pt-3">
                        <p x-show="catLoading" class="text-xs text-slate-400">Loading…</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="s in catSymptoms" :key="s.master_id">
                                <button type="button" :disabled="!editable" @click="toggleSymptom(s)"
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition disabled:opacity-50"
                                        :class="isSelected(s.label) ? 'border-brand bg-brand-light text-brand' : 'border-slate-300 bg-white text-slate-700 hover:border-brand'">
                                    <span x-text="isSelected(s.label) ? '✓' : '+'"></span>
                                    <span x-text="s.label"></span>
                                </button>
                            </template>
                            <p x-show="!catLoading && catSymptoms.length === 0" class="text-xs text-slate-400">No symptoms in this system.</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ---- PRESCRIPTION ---- -->
            <!-- Prescription state/methods now live in the parent visitScreenV2
                 scope (no nested x-data) so medicine inputs bind to one scope. -->
            <div x-init="loadTemplates()" class="overflow-visible rounded-lg border border-slate-200 bg-white p-3">
                <div class="flex items-baseline justify-between">
                    <label class="ui-group-label">Prescription</label>
                    <button type="button" :disabled="!editable" @click="cloneLastVisit()"
                            class="text-sm font-medium text-brand hover:underline disabled:opacity-50">
                        ↻ Same as last visit
                    </button>
                </div>

                <template x-if="lastVisitNote">
                    <p class="mt-1 rounded bg-brand-light px-2 py-1 text-xs text-brand"
                       x-text="lastVisitNote"></p>
                </template>

                <!-- Template chips -->
                <div x-show="templates.length || suggestions.length" class="mt-2 flex flex-wrap items-center gap-1.5">
                    <span class="text-xs text-slate-500">Apply:</span>
                    <template x-for="tpl in templates.slice(0, 5)" :key="tpl.id">
                        <button type="button" :disabled="!editable" @click="applyTemplate(tpl.id)"
                                class="rounded-full border border-slate-300 bg-white px-3 py-1 text-sm hover:border-brand hover:text-brand disabled:opacity-50">
                            <span x-text="tpl.name"></span>
                        </button>
                    </template>
                    <template x-if="templates.length > 5">
                        <details class="relative">
                            <summary class="cursor-pointer rounded-full border border-slate-300 bg-white px-2.5 py-0.5 text-xs hover:border-brand">More…</summary>
                            <div class="absolute z-10 mt-1 w-56 max-h-64 overflow-y-auto rounded-lg border bg-white shadow-lg p-1">
                                <template x-for="tpl in templates.slice(5)" :key="tpl.id">
                                    <button type="button" :disabled="!editable" @click="applyTemplate(tpl.id)"
                                            class="block w-full rounded px-2 py-1 text-left text-xs hover:bg-brand-light disabled:opacity-50">
                                        <span x-text="tpl.name"></span>
                                        <span class="text-slate-400" x-show="tpl.use_count > 0" x-text="' · ' + tpl.use_count + ' uses'"></span>
                                    </button>
                                </template>
                            </div>
                        </details>
                    </template>
                </div>

                <!-- Auto-discovered suggestions: "you often prescribe these — save as template?" -->
                <template x-for="sug in suggestions" :key="sug.id">
                    <div class="mt-2 flex items-center justify-between gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50/50 p-2 text-xs">
                        <span class="text-amber-900" x-text="sug.description + ' (' + sug.name + ')'"></span>
                        <div class="flex gap-2">
                            <button type="button" @click="activateSuggestion(sug)" class="font-semibold text-brand hover:underline">Save as template</button>
                            <button type="button" @click="dismissSuggestion(sug.id)" class="text-slate-500 hover:underline">Dismiss</button>
                        </div>
                    </div>
                </template>

                <div class="mt-2 space-y-2 overflow-visible">
                    <template x-for="(line, idx) in prescriptions" :key="line._rxKey || ('rx-fallback-' + idx)">
                        <div class="overflow-visible rounded-lg border border-slate-200 bg-white">
                            <!-- Main row -->
                            <div class="grid items-center gap-2 overflow-visible p-2 sm:grid-cols-12">
                                    <div class="relative isolate min-w-0 sm:col-span-4"
                                     :class="line._dropdown ? 'z-50' : 'z-0'"
                                     @click.outside="line._dropdown = false">
                                    <input type="text" :disabled="!editable"
                                           x-model="line.drug_name"
                                           @input.debounce.250ms="searchDrugFor(idx, line.drug_name)"
                                           @blur="syncRxFormFromName(idx)"
                                           @focus="onDrugFocus(idx)"
                                           placeholder="Medicine (type 2+ letters)"
                                           class="relative z-10 w-full rounded border bg-white px-2 py-1 text-sm"
                                           autocomplete="off"
                                           spellcheck="false">
                                    <div x-show="line._dropdown" x-cloak
                                         class="absolute left-0 right-0 top-full z-20 mt-2 min-w-[min(100%,320px)]">
                                        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl ring-1 ring-slate-200/80">
                                            <p x-show="line._searchHint" x-cloak
                                               class="border-b border-slate-100 bg-slate-50 px-2.5 py-2 text-xs text-slate-600"
                                               x-text="line._searchHint"></p>
                                            <ul x-show="(line._suggestions || []).length"
                                                class="max-h-48 overflow-y-auto overscroll-contain">
                                                <template x-for="(d, sIdx) in (line._suggestions || [])" :key="'rxs-' + idx + '-' + sIdx">
                                                    <li class="border-b border-slate-50 last:border-0">
                                                        <button type="button"
                                                                @mousedown.prevent
                                                                @click="pickDrugFor(idx, d)"
                                                                class="block w-full px-2.5 py-2 text-left text-xs hover:bg-brand-light">
                                                            <span x-text="d.name"></span>
                                                            <span class="text-slate-400" x-show="d.strength" x-text="' ' + d.strength"></span>
                                                        </button>
                                                    </li>
                                                </template>
                                            </ul>
                                            <p x-show="!line._searchHint && line._searchError" x-cloak
                                               class="border-t border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-800"
                                               x-text="line._searchError"></p>
                                        </div>
                                    </div>
                                </div>

                                <select :disabled="!editable || !!line.tapering_steps"
                                        x-init="initFrequencySelect($el, line)"
                                        class="sm:col-span-2 rounded border px-2 py-1 text-sm">
                                </select>
                                <input type="number" min="1" max="90"
                                       :disabled="!editable || !!line.tapering_steps"
                                       x-model="line.duration_days"
                                       @change="markDirty()"
                                       placeholder="Days"
                                       class="sm:col-span-2 rounded border px-2 py-1 text-sm">
                                <select :disabled="!editable" x-model="line.food_timing"
                                        @change="markDirty()"
                                        class="sm:col-span-2 rounded border px-2 py-1 text-sm">
                                    <option value="any">Any time</option>
                                    <option value="before">Before food</option>
                                    <option value="after">After food</option>
                                    <option value="empty">Empty stomach</option>
                                    <option value="bedtime">At bedtime</option>
                                </select>
                                <div class="sm:col-span-2 flex items-center justify-end gap-2 text-xs">
                                    <button type="button" :disabled="!editable" @click="line._drawer = !line._drawer"
                                            class="rounded border border-slate-300 px-1.5 py-0.5 text-slate-600 hover:border-brand hover:text-brand disabled:opacity-50"
                                            title="Advanced">⋮</button>
                                    <button type="button" :disabled="!editable" @click="removeRxLine(idx)"
                                            class="text-rose-600 hover:underline"
                                            title="Remove">×</button>
                                </div>

                                <!-- Tapering summary chip — replaces preset+duration when tapering active -->
                                <template x-if="line.tapering_steps && line.tapering_steps.length">
                                    <div class="sm:col-span-12 mt-1 rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                        Tapering schedule — <span x-text="line.tapering_steps.length + ' step' + (line.tapering_steps.length === 1 ? '' : 's')"></span>,
                                        <span x-text="taperingTotalDays(line.tapering_steps) + ' days total'"></span>
                                    </div>
                                </template>

                                <input type="text" :disabled="!editable" x-model="line.instructions"
                                       placeholder="Optional instructions"
                                       class="sm:col-span-12 rounded border border-slate-100 px-2 py-1 text-xs">
                            </div>

                            <!-- [⋮] Drawer — per-row advanced options -->
                            <div x-show="line._drawer" x-collapse class="border-t border-slate-100 bg-slate-50/60 p-3 text-xs space-y-3">
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <label class="block">
                                        <span class="text-slate-600">Dose unit</span>
                                        <select :disabled="!editable" x-model="line.dose_unit"
                                                class="mt-1 w-full rounded border px-2 py-1">
                                            <option value="">—</option>
                                            <option value="tablet">Tablet</option>
                                            <option value="capsule">Capsule</option>
                                            <option value="ml">ml</option>
                                            <option value="drops">Drops</option>
                                            <option value="sachet">Sachet</option>
                                            <option value="puff">Puff</option>
                                            <option value="unit">Unit</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-slate-600">Dose amount</span>
                                        <input type="number" step="0.01" :disabled="!editable" x-model="line.dose_amount"
                                               class="mt-1 w-full rounded border px-2 py-1">
                                    </label>
                                    <label class="block">
                                        <span class="text-slate-600">Mix with</span>
                                        <select :disabled="!editable" x-model="line.mix_with"
                                                class="mt-1 w-full rounded border px-2 py-1">
                                            <option value="">—</option>
                                            <option value="water">Water</option>
                                            <option value="milk">Milk</option>
                                            <option value="warm water">Warm water</option>
                                            <option value="nothing">Nothing</option>
                                        </select>
                                    </label>
                                </div>

                                <!-- Tapering step list -->
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-slate-700">Tapering schedule</span>
                                        <button type="button" :disabled="!editable" @click="addTaperingStep(line)"
                                                class="rounded bg-slate-800 px-2 py-0.5 text-xs text-white hover:bg-slate-900 disabled:opacity-50">
                                            + Add step
                                        </button>
                                    </div>
                                    <template x-if="!line.tapering_steps || !line.tapering_steps.length">
                                        <p class="mt-1 text-slate-500">No tapering — uses Frequency + Days above.</p>
                                    </template>
                                    <template x-if="line.tapering_steps && line.tapering_steps.length">
                                        <ol class="mt-2 space-y-1.5">
                                            <template x-for="(step, sIdx) in line.tapering_steps" :key="sIdx">
                                                <li class="flex flex-wrap items-center gap-2">
                                                    <span class="w-6 text-slate-500" x-text="(sIdx + 1) + '.'"></span>
                                                    <span class="text-slate-600">For</span>
                                                    <input type="number" min="1" :disabled="!editable" x-model.number="step.days"
                                                           @change="markDirty()"
                                                           class="w-16 rounded border px-1.5 py-0.5">
                                                    <span class="text-slate-600">days,</span>
                                                    <input type="number" step="0.01" min="0.01" :disabled="!editable"
                                                           x-model.number="step.dose_amount"
                                                           @change="markDirty()"
                                                           class="w-16 rounded border px-1.5 py-0.5"
                                                           :placeholder="line.dose_amount || '1'">
                                                    <span class="text-slate-500 text-[10px] uppercase"
                                                          x-text="line.dose_unit || 'dose'"></span>
                                                    <select :disabled="!editable"
                                                            x-init="initFrequencySelect($el, line, step)"
                                                            class="rounded border px-1.5 py-0.5">
                                                    </select>
                                                    <select :disabled="!editable" x-model="step.food"
                                                            @change="markDirty()"
                                                            class="rounded border px-1.5 py-0.5">
                                                        <option value="any">Any</option>
                                                        <option value="before">Before food</option>
                                                        <option value="after">After food</option>
                                                    </select>
                                                    <button type="button" :disabled="!editable" @click="line.tapering_steps.splice(sIdx, 1)"
                                                            class="ml-auto text-rose-600 hover:underline">×</button>
                                                </li>
                                            </template>
                                        </ol>
                                    </template>
                                </div>

                                <div class="text-right">
                                    <button type="button" @click="line._drawer = false"
                                            class="text-slate-500 hover:underline">Close</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button type="button" :disabled="!editable" @click="addRxLine()"
                                class="text-sm font-medium text-brand hover:underline disabled:opacity-50">
                            + Add medicine
                        </button>
                        <button type="button" :disabled="!editable" @click="openSaveTemplate()"
                                x-show="hasRx"
                                class="text-xs font-medium text-slate-600 hover:text-brand hover:underline disabled:opacity-50">
                            Save as template…
                        </button>
                    </div>
                </div>

                <!-- Save-as-template modal -->
                <div x-show="saveTplModal.open" x-cloak
                     @keydown.escape.window="saveTplModal.open = false"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div @click.outside="saveTplModal.open = false" class="w-full max-w-md rounded-xl bg-white shadow-xl">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h3 class="ui-section-title">Save as template</h3>
                            <p class="ui-section-sub mt-0.5">Reuse this set of medicines on future visits.</p>
                        </div>
                        <div class="space-y-3 px-4 py-4">
                            <label class="block">
                                <span class="ui-label mb-1 block">Template name</span>
                                <input type="text" x-model="saveTplModal.name"
                                       placeholder="e.g. Common Cold — adult"
                                       class="ui-input"
                                       @keydown.enter.prevent="confirmSaveTemplate()">
                            </label>
                            <label class="block">
                                <span class="ui-label mb-1 block">Description (optional)</span>
                                <input type="text" x-model="saveTplModal.description"
                                       placeholder="When to use this"
                                       class="ui-input">
                            </label>
                            <div>
                                <span class="ui-label mb-1 block">Visible to</span>
                                <div class="flex gap-2">
                                    <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-light has-[:checked]:text-brand">
                                        <input type="radio" name="tpl_scope" value="mine" x-model="saveTplModal.scope" class="ui-radio">
                                        Only me
                                    </label>
                                    <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-light has-[:checked]:text-brand">
                                        <input type="radio" name="tpl_scope" value="clinic" x-model="saveTplModal.scope" class="ui-radio">
                                        Whole clinic
                                    </label>
                                </div>
                            </div>
                            <p x-show="saveTplModal.error" x-text="saveTplModal.error" class="text-xs text-red-600"></p>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
                            <button type="button" @click="saveTplModal.open = false" class="ui-btn ui-btn-secondary ui-btn-sm">Cancel</button>
                            <button type="button" @click="confirmSaveTemplate()" :disabled="saveTplModal.saving"
                                    class="ui-btn ui-btn-primary ui-btn-sm">
                                <span x-text="saveTplModal.saving ? 'Saving…' : 'Save template'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ---- NOTES + REPORTS NOTES (side by side) ---- -->
            <div class="rounded-lg border border-slate-200 bg-white p-3">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="ui-group-label">Notes <span class="font-normal normal-case tracking-normal text-slate-400">— follow-up, observations</span></label>
                            <button type="button" :disabled="!editable" x-show="voiceSupported"
                                    @click="dictateInto('clinical_notes')"
                                    :class="listening === 'clinical_notes' ? 'text-rose-600 animate-pulse' : 'text-slate-500'"
                                    class="text-xs hover:text-brand disabled:opacity-50"
                                    title="Dictate notes"><span class="inline-flex items-center gap-1"><?= ui_icon('bell', 13) ?><span x-text="listening === 'clinical_notes' ? 'Listening…' : 'Voice'"></span></span></button>
                        </div>
                        <textarea x-model="clinical_notes" :disabled="!editable" rows="4"
                                  @input="markDirty()"
                                  placeholder="e.g. Follow-up in 2 weeks, improvement noted…"
                                  class="ui-input mt-1.5"></textarea>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="ui-group-label">Reports - notes <span class="font-normal normal-case tracking-normal text-slate-400">— lab / investigation findings</span></label>
                            <button type="button" :disabled="!editable" x-show="voiceSupported"
                                    @click="dictateInto('reports_notes')"
                                    :class="listening === 'reports_notes' ? 'text-rose-600 animate-pulse' : 'text-slate-500'"
                                    class="text-xs hover:text-brand disabled:opacity-50"
                                    title="Dictate report notes"><span class="inline-flex items-center gap-1"><?= ui_icon('bell', 13) ?><span x-text="listening === 'reports_notes' ? 'Listening…' : 'Voice'"></span></span></button>
                        </div>
                        <textarea x-model="reports_notes" :disabled="!editable" rows="4"
                                  @input="markDirty()"
                                  placeholder="e.g. CBC normal, Vit-D low, X-ray clear…"
                                  class="ui-input mt-1.5"></textarea>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">Next visit:</span>
                    <button type="button" :disabled="!editable" @click="setFollowUp(3)"
                            class="rounded-full border border-slate-300 px-2 py-0.5 hover:bg-slate-50">+3d</button>
                    <button type="button" :disabled="!editable" @click="setFollowUp(5)"
                            class="rounded-full border border-slate-300 px-2 py-0.5 hover:bg-slate-50">+5d</button>
                    <button type="button" :disabled="!editable" @click="setFollowUp(7)"
                            class="rounded-full border border-slate-300 px-2 py-0.5 hover:bg-slate-50">+1w</button>
                    <button type="button" :disabled="!editable" @click="setFollowUp(14)"
                            class="rounded-full border border-slate-300 px-2 py-0.5 hover:bg-slate-50">+2w</button>
                    <input type="date" :disabled="!editable" x-model="follow_up_date"
                           class="rounded border border-slate-300 px-2 py-0.5 text-xs">
                </div>

                <!-- Follow-up reason (only when a date is set) -->
                <div x-show="follow_up_date" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">Reason:</span>
                    <select x-model="follow_up_reason" :disabled="!editable"
                            class="rounded border border-slate-300 px-2 py-0.5 text-xs">
                        <option value="">—</option>
                        <?php foreach (($followUpReasons ?? []) as $r): ?>
                        <option value="<?= htmlspecialchars($r['reason_key']) ?>"><?= htmlspecialchars($r['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <textarea x-show="follow_up_date" x-model="follow_up_notes" :disabled="!editable"
                          rows="1" placeholder="Follow-up note (optional)"
                          class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs"></textarea>
            </div>

            <!-- ---- CHARGES (line items → visit invoice) ---- -->
            <div class="rounded-lg border border-slate-200 bg-white p-3">
                <div class="flex items-baseline justify-between">
                    <label class="ui-group-label">Charges</label>
                    <span class="text-xs text-slate-500">Total: <span class="font-semibold text-slate-800" x-text="'₹' + chargesTotal()"></span></span>
                </div>
                <div class="mt-2 space-y-2">
                    <template x-for="(c, idx) in charges" :key="c._k">
                        <div class="flex items-center gap-2">
                            <input type="text" :disabled="!editable" x-model="c.description" @input="markChargesDirty()"
                                   placeholder="e.g. Consultation, Procedure, Medicines"
                                   class="ui-input flex-1">
                            <div class="flex items-center rounded border border-slate-300">
                                <span class="px-2 text-sm text-slate-400">₹</span>
                                <input type="number" min="0" step="1" :disabled="!editable" x-model.number="c.amount"
                                       @input="markChargesDirty(); syncPaymentAmount()"
                                       placeholder="0" class="w-24 border-0 px-1 py-1.5 text-sm focus:outline-none focus:ring-0">
                            </div>
                            <button type="button" :disabled="!editable" @click="removeCharge(idx)" class="text-rose-600 hover:underline disabled:opacity-50" title="Remove">×</button>
                        </div>
                    </template>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button type="button" :disabled="!editable" @click="addCharge()"
                            class="text-sm font-medium text-brand hover:underline disabled:opacity-50">+ Add charge</button>

                    <!-- Save is a real button. It's required (highlighted) when there
                         are unsaved charge rows; disabled/neutral when nothing changed. -->
                    <button type="button" :disabled="!editable || !chargesDirty || charges.length === 0"
                            @click="saveCharges()"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed"
                            :class="(chargesDirty && charges.length) ? 'bg-brand text-white hover:bg-brand-dark' : 'bg-slate-100 text-slate-400'">
                        <span x-text="(chargesDirty && charges.length) ? 'Save charges *' : 'Charges saved'"></span>
                    </button>

                    <span class="text-xs" :class="chargesStatus === 'error' ? 'text-rose-600' : (chargesStatus === 'saved' ? 'text-emerald-600' : 'text-amber-600')"
                          x-show="chargesLabel" x-text="chargesLabel"></span>
                    <span class="text-xs text-amber-600" x-show="chargesDirty && charges.length && chargesStatus !== 'saving'">Unsaved charges</span>
                </div>
            </div>

            <!-- ====== OPTIONAL SECTIONS — collapsed by default for a fast form ====== -->

            <!-- ---- DIAGNOSIS + ICD-10 (collapsible; most visits skip it) ---- -->
            <details class="rounded-lg border border-slate-200 bg-slate-50/50">
                <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">
                    Diagnosis <span class="font-normal text-slate-400">— optional</span>
                </summary>
                <div class="px-4 pb-4 pt-2">
                    <input type="text" x-model="diagnosis" :disabled="!editable"
                           placeholder="e.g. Viral fever"
                           class="ui-input">
                    <div class="mt-2 flex items-center gap-2">
                        <input type="search" x-model="icd10_code" :disabled="!editable"
                               @input.debounce.300ms="searchIcd($event.target.value)"
                               placeholder="ICD-10 code (optional)"
                               class="w-40 rounded border border-slate-200 px-2 py-1 text-xs">
                        <ul x-show="icdResults.length" class="ml-2 inline-flex max-h-32 flex-wrap gap-1 overflow-y-auto">
                            <template x-for="c in icdResults" :key="c.code">
                                <li>
                                    <!-- Code alone is unreadable — show the label, and use it as
                                         the diagnosis text when the doctor hasn't typed one. -->
                                    <button type="button"
                                            @click="icd10_code = c.code; if (!diagnosis) diagnosis = c.label; icdResults = []"
                                            class="rounded bg-slate-100 px-2 py-0.5 text-xs hover:bg-brand-light"
                                            x-text="c.code + ' · ' + c.label"></button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </details>

            <?php if ($has('diet') && !empty($hasDiet)): ?>
                <details class="rounded-lg border border-slate-200 bg-slate-50/50"
                         @toggle="recordSection('diet', $event.target.open)">
                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Diet plan</summary>
                    <div class="px-4 pb-4 pt-2">
                        <?php require __DIR__ . '/partials/diet.php'; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php
            $visitId = (int) $visit['id'];
            $patientId = (int) $patient['id'];
            require __DIR__ . '/partials/immunizations_summary.php';
            ?>

            <!-- ---- Ghost-link strip: reveal hidden sections for this visit ---- -->
            <?php if (!empty($ghostModules)): ?>
                <div class="flex flex-wrap items-center gap-2 border-t border-dashed border-slate-200 pt-3 text-xs text-slate-500">
                    <span>+ Add:</span>
                    <?php foreach ($ghostModules as $g):
                        $label = match ($g) {
                            'vitals' => 'Vitals',
                            'diet' => 'Diet plan',
                            'case_specialty' => 'Case taking',
                            default => ucfirst($g),
                        };
                    ?>
                        <button type="button" @click="revealGhost('<?= $g ?>')"
                                class="rounded-full border border-slate-300 px-2.5 py-1 hover:border-brand hover:text-brand"
                                x-show="!ghostRevealed.includes('<?= $g ?>')">
                            <?= htmlspecialchars($label) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Ghost sections rendered dynamically (Phase 2 keeps these
                     minimal — Phase 3+ will move per-section UIs into Alpine
                     components shared with the always-visible ones above). -->
                <template x-for="g in ghostRevealed" :key="g">
                    <div class="rounded-lg border border-brand bg-brand-light/30 p-3 text-xs text-slate-600">
                        <span class="font-semibold" x-text="g"></span>
                        — this section was hidden by default for your specialty.
                        Reveal in <a href="/settings?tab=specialty" class="text-brand underline">clinic settings</a>
                        to always show it.
                    </div>
                </template>
            <?php endif; ?>

            <!-- ---- PAYMENT (last block — money side of the visit invoice) ---- -->
            <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="ui-group-label flex items-center gap-1.5"><span class="text-brand">₹</span> Payment</label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" class="ui-checkbox" :disabled="!editable"
                               x-model="payment.gst" @change="markChargesDirty()">
                        <span>Add GST (<span x-text="payment.tax_percent"></span>%)</span>
                    </label>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <label class="block">
                        <span class="ui-group-label">Amount (₹) <span class="font-normal normal-case tracking-normal text-slate-400">— auto total, editable</span></span>
                        <input type="number" min="0" step="1" :disabled="!editable"
                               x-model.number="payment.amount" @input="onPaymentAmountInput()"
                               placeholder="0" class="ui-input mt-1">
                    </label>
                    <label class="block">
                        <span class="ui-group-label">Payment type</span>
                        <select x-model="payment.type" :disabled="!editable" @change="markChargesDirty()" class="ui-input mt-1">
                            <option value="cash">Cash</option>
                            <option value="online">Online</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="ui-group-label">Payment status</span>
                        <select x-model="payment.status" :disabled="!editable" @change="markChargesDirty()" class="ui-input mt-1">
                            <option value="paid">Paid</option>
                            <option value="due">Due</option>
                        </select>
                    </label>
                </div>

                <dl class="mt-3 space-y-1 border-t border-dashed border-slate-300 pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt>
                        <dd class="text-slate-700" x-text="'₹' + payableBase().toFixed(0)"></dd></div>
                    <div class="flex justify-between" x-show="payment.gst"><dt class="text-slate-500">GST (<span x-text="payment.tax_percent"></span>%)</dt>
                        <dd class="text-slate-700" x-text="'₹' + gstAmount().toFixed(0)"></dd></div>
                    <div class="flex justify-between font-semibold"><dt>Total payable</dt>
                        <dd x-text="'₹' + totalPayable().toFixed(0)"></dd></div>
                    <div class="flex justify-between text-amber-700" x-show="payment.status === 'due' && totalPayable() > 0">
                        <dt>Balance due</dt><dd x-text="'₹' + totalPayable().toFixed(0)"></dd></div>
                </dl>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <span class="text-xs" x-show="chargesLabel"
                          :class="chargesStatus === 'error' ? 'text-rose-600' : (chargesStatus === 'saved' ? 'text-emerald-600' : 'text-amber-600')"
                          x-text="chargesLabel"></span>
                    <span class="text-xs text-slate-500" x-show="payment.paid_amount > 0"
                          x-text="'Already received ₹' + payment.paid_amount"></span>
                    <a :href="'/billing/' + invoiceId" x-show="invoiceId" class="text-xs font-medium text-brand hover:underline">Open invoice</a>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    Saved together with the visit when you click <strong>Complete visit</strong>.
                    Marked <strong>Due</strong>? Reception can see the balance and settle it from
                    <a href="/billing" class="text-brand hover:underline">Patient Bills</a>.
                </p>
            </div>

            <!-- ---- Save / Complete actions ---- -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                <div class="flex flex-wrap gap-2">
                    <button type="button" :disabled="!editable" @click="save()"
                            class="ui-btn ui-btn-secondary">
                        Save draft
                    </button>
                    <button type="button" @click="printRx()"
                            x-show="(prescriptions || []).some(p => p.drug_id || p.remedy_id || p.drug_name)"
                            class="ui-btn ui-btn-secondary"
                            title="Saves the draft first, then opens the A5 print preview">
                        Print preview
                    </button>
                </div>
                <?php if ($editable): ?>
                    <form method="post" action="/visits/<?= $visitId ?>/complete" @submit="confirmComplete($event)"
                          class="flex flex-col items-end gap-2">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="_visit_payload" value="">
                        <?php if (!empty($patient['identity_id'])): ?>
                            <!-- Patient has an eClinicPro account → offer to push the Rx to their panel. -->
                            <label class="flex items-center gap-2 text-xs text-slate-600"
                                   x-show="(prescriptions || []).some(p => p.drug_id || p.remedy_id || p.drug_name)">
                                <input type="checkbox" name="share_to_patient_app" value="1" checked
                                       class="rounded border-slate-300 text-brand focus:ring-brand">
                                <span>Share this prescription to the patient's eClinicPro app</span>
                            </label>
                        <?php endif; ?>
                        <button type="submit" class="ui-btn ui-btn-primary">
                            Complete visit
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    </div><!-- /left column -->

    <!-- ====== RIGHT SIDEBAR — vitals, standing history, case taking,
         reports and the visit list, in the order a doctor scans them ====== -->
    <aside class="space-y-4 lg:col-span-1">

    <?php if ($has('vitals')): ?>
        <details class="ui-card" open
                 @toggle="recordSection('vitals', $event.target.open)">
            <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Vitals</summary>
            <div class="px-4 pb-4 pt-2">
                <div class="grid gap-3 sm:grid-cols-2">
                    <?php foreach ($vitalsFields as $f): ?>
                        <label class="text-xs">
                            <span class="text-slate-500"><?= htmlspecialchars($f['label']) ?><?= !empty($f['unit']) ? ' (' . htmlspecialchars($f['unit']) . ')' : '' ?></span>
                            <?php if (($f['type'] ?? '') === 'select'): ?>
                                <select :disabled="!editable" x-model="vitals.<?= htmlspecialchars($f['key']) ?>"
                                        class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                                    <option value="">—</option>
                                    <?php foreach ($f['options'] ?? [] as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (!empty($f['extra'])): ?>
                                <input type="<?= $f['type'] === 'text' ? 'text' : 'number' ?>" step="any"
                                       :disabled="!editable"
                                       x-model="vitals.extra.<?= htmlspecialchars(substr($f['key'], 6)) ?>"
                                       class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                            <?php else: ?>
                                <input type="number" step="any" :disabled="!editable"
                                       x-model="vitals.<?= htmlspecialchars($f['key']) ?>"
                                       class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <template x-if="vitalsWarnings.length">
                    <div class="mt-2 space-y-1">
                        <template x-for="w in vitalsWarnings" :key="w.message">
                            <p class="rounded bg-amber-50 px-2 py-1 text-xs text-amber-900" x-text="w.message"></p>
                        </template>
                    </div>
                </template>
            </div>
        </details>
    <?php endif; ?>

    <!-- ---- HISTORY SUMMARY (read-only; edited on the patient record) ---- -->
    <?php
    $hs = $historySummary ?? [];
    $hasHistory = !empty($hs['chronic']) || !empty($hs['allergies']) || !empty($hs['surgeries'])
        || !empty($hs['family_history']) || !empty($hs['medications']);
    $hasLastVisit = !empty($hs['last_visit_at']) && (!empty($hs['last_complaint']) || !empty($hs['last_diagnosis']));
    ?>
    <section class="ui-card p-4">
        <div class="flex items-center justify-between">
            <h3 class="ui-section-title text-base">History summary</h3>
            <a href="/patients/<?= (int) $patient['id'] ?>" class="text-xs font-medium text-brand hover:underline">Edit</a>
        </div>
        <?php if (!empty($hs['last_vitals'])): ?>
        <!-- Vitals as last recorded — today's readings are entered above. -->
        <div class="mt-3">
            <p class="ui-group-label">Vital signs
                <?php if (!empty($hs['last_vitals_at'])): ?>
                <span class="font-normal normal-case tracking-normal text-slate-400">— <?= htmlspecialchars(date('d M Y', strtotime((string) $hs['last_vitals_at']))) ?></span>
                <?php endif; ?>
            </p>
            <div class="mt-1 flex flex-wrap gap-1.5">
                <?php foreach ($hs['last_vitals'] as $vs): ?>
                <span class="rounded-lg border border-slate-200 px-2 py-1 text-xs text-slate-700">
                    <?= htmlspecialchars($vs['label']) ?>: <span class="font-medium"><?= htmlspecialchars($vs['value']) ?></span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($hasLastVisit): ?>
        <!-- What brought the patient in last time — the returning-visit context. -->
        <div class="mt-3 rounded-lg bg-slate-50 p-2.5">
            <p class="ui-group-label">Last visit
                <span class="font-normal normal-case tracking-normal text-slate-400">— <?= htmlspecialchars(date('d M Y', strtotime((string) $hs['last_visit_at']))) ?></span>
            </p>
            <?php if (!empty($hs['last_complaint'])): ?>
            <p class="mt-1 whitespace-pre-line text-sm text-slate-700"><?= htmlspecialchars((string) $hs['last_complaint']) ?></p>
            <?php endif; ?>
            <?php if (!empty($hs['last_diagnosis'])): ?>
            <p class="mt-1 text-sm text-slate-600"><span class="text-slate-400">Dx:</span> <?= htmlspecialchars((string) $hs['last_diagnosis']) ?></p>
            <?php endif; ?>
            <?php if (!empty($hs['last_complaint'])): ?>
            <button type="button" :disabled="!editable"
                    @click="chief_complaint = chief_complaint || <?= htmlspecialchars(json_encode((string) $hs['last_complaint']), ENT_QUOTES) ?>; markDirty()"
                    class="mt-1.5 text-xs font-medium text-brand hover:underline disabled:opacity-50">
                ↻ Copy into today's complaint
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!$hasHistory): ?>
            <?php if (!$hasLastVisit && empty($hs['last_vitals'])): ?>
            <p class="mt-2 text-sm text-slate-400">Nothing recorded yet.</p>
            <?php endif; ?>
        <?php else: ?>
        <dl class="mt-3 space-y-2.5 text-sm">
            <?php if (!empty($hs['chronic'])): ?>
            <div>
                <dt class="ui-group-label">Chronic</dt>
                <dd class="text-slate-700"><?= htmlspecialchars(implode(', ', $hs['chronic'])) ?></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($hs['allergies'])): ?>
            <div>
                <dt class="ui-group-label text-rose-600">Allergies</dt>
                <dd class="text-rose-700"><?= htmlspecialchars(implode(', ', $hs['allergies'])) ?></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($hs['surgeries'])): ?>
            <div>
                <dt class="ui-group-label">Past surgeries</dt>
                <dd class="whitespace-pre-line text-slate-700"><?= htmlspecialchars((string) $hs['surgeries']) ?></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($hs['family_history'])): ?>
            <div>
                <dt class="ui-group-label">Family history</dt>
                <dd class="whitespace-pre-line text-slate-700"><?= htmlspecialchars((string) $hs['family_history']) ?></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($hs['medications'])): ?>
            <div>
                <dt class="ui-group-label">Medications
                    <?php if (!empty($hs['medications_date'])): ?>
                    <span class="font-normal normal-case tracking-normal text-slate-400">— last visit <?= htmlspecialchars(date('d M Y', strtotime((string) $hs['medications_date']))) ?></span>
                    <?php endif; ?>
                </dt>
                <dd class="text-slate-700"><?= htmlspecialchars((string) $hs['medications']) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
        <?php endif; ?>
        <?php if (!empty($hs['last_vitals_at']) && empty($hs['last_vitals'])): ?>
        <p class="mt-3 border-t border-slate-100 pt-2 text-xs text-slate-500">
            Last vitals: <?= htmlspecialchars(date('d M Y', strtotime((string) $hs['last_vitals_at']))) ?>
        </p>
        <?php endif; ?>
    </section>

    <!-- ---- REPORTS SUMMARY — this visit's lab/investigation notes plus what
         earlier visits recorded, so findings read as one thread ---- -->
    <?php
    $priorReports = [];
    foreach (($recentVisits ?? []) as $rv) {
        $rn = trim((string) ($rv['reports_notes'] ?? ''));
        if ($rn !== '') {
            $priorReports[] = ['date' => (string) ($rv['visited_at'] ?? ''), 'notes' => $rn];
        }
    }
    ?>
    <section class="ui-card p-4">
        <h3 class="ui-section-title text-base">Reports summary</h3>
        <div class="mt-2">
            <p class="ui-group-label">This visit</p>
            <p class="mt-0.5 whitespace-pre-line text-sm text-slate-700" x-show="reports_notes" x-text="reports_notes"></p>
            <p class="mt-0.5 text-sm text-slate-400" x-show="!reports_notes">No findings recorded yet.</p>
        </div>
        <?php if ($priorReports !== []): ?>
        <ul class="mt-3 space-y-2 border-t border-slate-100 pt-2">
            <?php foreach ($priorReports as $pr): ?>
            <li>
                <p class="ui-group-label"><?= htmlspecialchars(date('d M Y', strtotime($pr['date']))) ?></p>
                <p class="mt-0.5 whitespace-pre-line text-sm text-slate-600"><?= htmlspecialchars($pr['notes']) ?></p>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <?php if ($has('case_specialty') && $caseAvailable): ?>
        <details class="ui-card"
                 @toggle="recordSection('case_specialty', $event.target.open)">
            <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Case taking</summary>
            <div class="px-4 pb-4 pt-2 space-y-3">
                <?php require $casePartialPath; ?>
            </div>
        </details>
    <?php endif; ?>

    <section class="ui-card shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <h2 class="ui-section-title">Visit history</h2>
            <span class="text-xs text-slate-400"><?= count($recentVisits ?? []) ?> recent</span>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
        <ul class="divide-y text-sm">
            <?php if (empty($recentVisits)): ?>
                <li class="px-5 py-6 text-center text-sm text-slate-500">No prior visits.</li>
            <?php else: ?>
                <?php foreach ($recentVisits as $rv):
                    $inv = $rv['invoice'] ?? null;
                    $meds = trim((string) ($rv['medicines_summary'] ?? ''));
                    $isPaid = $inv && in_array($inv['status'] ?? '', ['paid', 'partial'], true);
                ?>
                    <li class="group px-5 py-3 hover:bg-slate-50">
                        <div class="flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-brand">
                                <?= ui_icon('appointments', 13) ?><?= htmlspecialchars(date('d M Y', strtotime((string) $rv['visited_at']))) ?>
                            </span>
                            <span class="text-[11px] text-slate-400">#<?= (int) $rv['visit_number'] ?></span>
                        </div>

                        <?php if ($meds !== ''): ?>
                            <div class="mt-1 line-clamp-2 text-sm font-medium text-slate-800"><?= htmlspecialchars($meds) ?></div>
                        <?php elseif (!empty($rv['diagnosis'])): ?>
                            <div class="mt-1 line-clamp-2 text-sm text-slate-700"><?= htmlspecialchars((string) $rv['diagnosis']) ?></div>
                        <?php elseif (!empty($rv['chief_complaint'])): ?>
                            <div class="mt-1 line-clamp-2 text-sm text-slate-700"><?= htmlspecialchars((string) $rv['chief_complaint']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($rv['follow_up_notes'])): ?>
                            <div class="mt-1 inline-block rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-800">↳ <?= htmlspecialchars((string) $rv['follow_up_notes']) ?></div>
                        <?php endif; ?>

                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            <?php if ($inv): ?>
                                <span class="inline-flex items-center gap-1.5 text-xs">
                                    <span class="font-semibold text-slate-700">₹<?= number_format((float) $inv['total'], 0) ?></span>
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-medium <?= $isPaid ? 'bg-brand-light text-brand' : 'bg-slate-100 text-slate-500' ?>">
                                        <?= $isPaid ? '✓ Paid' : 'Unpaid' ?>
                                    </span>
                                </span>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <span class="flex gap-3 text-[11px]">
                                <?php if ($inv): ?>
                                    <a href="/billing/<?= (int) $inv['id'] ?>" class="font-medium text-brand hover:underline">
                                        <?= htmlspecialchars((string) ($inv['invoice_number'] ?? 'Invoice')) ?><?= !empty($inv['created_at']) ? ' · ' . htmlspecialchars(date('d M Y', strtotime((string) $inv['created_at']))) : '' ?>
                                    </a>
                                <?php endif; ?>
                                <button type="button" @click="togglePeek(<?= (int) $rv['id'] ?>)" class="font-medium text-brand hover:underline">
                                    <span x-text="peekId === <?= (int) $rv['id'] ?> ? '▲ Hide' : '▼ View'"></span>
                                </button>
                                <a href="/visits/<?= (int) $rv['id'] ?>" class="text-slate-500 hover:text-slate-800 hover:underline">Open</a>
                            </span>
                        </div>

                        <!-- Accordion detail: expands inline, read-only -->
                        <div x-show="peekId === <?= (int) $rv['id'] ?>" x-collapse x-cloak class="mt-3 rounded-lg border border-slate-200 bg-slate-50/70 p-3 text-sm">
                            <template x-if="peekId === <?= (int) $rv['id'] ?> && peek">
                                <div>
                                    <template x-if="peek.symptoms && peek.symptoms.length">
                                        <div class="mb-2">
                                            <div class="text-[11px] font-semibold uppercase text-slate-400">Symptoms</div>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <template x-for="s in peek.symptoms" :key="s">
                                                    <span class="rounded-full bg-brand-light px-2.5 py-1 text-sm text-brand" x-text="s"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="peek.diagnosis">
                                        <div class="mb-2"><div class="text-[11px] font-semibold uppercase text-slate-400">Diagnosis</div><div class="mt-0.5 text-slate-700" x-text="peek.diagnosis"></div></div>
                                    </template>
                                    <template x-if="peek.prescriptions && peek.prescriptions.length">
                                        <div class="mb-2">
                                            <div class="text-[11px] font-semibold uppercase text-slate-400">Medicines</div>
                                            <ul class="mt-1 space-y-1">
                                                <template x-for="(m, i) in peek.prescriptions" :key="i">
                                                    <li class="text-slate-700"><span class="font-medium" x-text="m.name"></span><span class="text-xs text-slate-400" x-show="m.detail" x-text="' — ' + m.detail"></span></li>
                                                </template>
                                            </ul>
                                        </div>
                                    </template>
                                    <template x-if="peek.clinical_notes">
                                        <div class="mb-2"><div class="text-[11px] font-semibold uppercase text-slate-400">Notes</div><div class="mt-0.5 whitespace-pre-line text-slate-700" x-text="peek.clinical_notes"></div></div>
                                    </template>
                                    <template x-if="!peek.symptoms?.length && !peek.diagnosis && !peek.prescriptions?.length && !peek.clinical_notes">
                                        <p class="text-slate-400">No clinical details recorded.</p>
                                    </template>
                                    <a :href="'/visits/' + peek.id" class="mt-2 inline-block text-sm font-medium text-brand hover:underline">Open full visit to edit →</a>
                                </div>
                            </template>
                            <p x-show="peekLoading" class="text-xs text-slate-400">Loading…</p>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        </div><!-- /scroll wrapper -->
    </section>
    </aside><!-- /right column -->

    </div><!-- /two-column grid -->
</div>

<script>
window.__RX_FORM_PRESETS = <?= json_encode(RxFormHelper::presetsByForm(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
window.__RX_FORM_DEFAULTS = <?= json_encode(array_combine(
    RxFormHelper::FORMS,
    array_map(static fn (string $f) => RxFormHelper::defaultLineDefaults($f), RxFormHelper::FORMS),
), JSON_THROW_ON_ERROR) ?>;

/** Map legacy saved keys to canonical case_taking field names for Alpine binding. */
function normalizeCaseTaking(raw) {
    const ct = (raw && typeof raw === 'object') ? { ...raw } : {};
    if (typeof raw === 'string') {
        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                Object.assign(ct, parsed);
            }
        } catch (e) { /* ignore */ }
    }
    if (ct.past_history && !ct.past_medical_history) {
        ct.past_medical_history = ct.past_history;
    }
    if (ct.systemic_history && !ct.systemic_review) {
        ct.systemic_review = ct.systemic_history;
    }
    if (ct.family_medical_history && !ct.family_history) {
        ct.family_history = ct.family_medical_history;
    }
    return ct;
}

function visitScreenV2(cfg) {
    const caseTaking = normalizeCaseTaking(cfg.case_taking || cfg.specialty_data?.case_taking || {});
    const specialtyData = {
        ...(cfg.specialty_data || {}),
        case_taking: caseTaking,
    };

    // Normalize vitals.extra into an object — same handling as legacy view.
    const vitals = cfg.vitals || {};
    if (!vitals.extra) vitals.extra = {};
    if (typeof vitals.extra_vitals === 'string') vitals.extra = JSON.parse(vitals.extra_vitals || '{}');
    else if (vitals.extra_vitals) vitals.extra = vitals.extra_vitals;

    // Give each pre-loaded charge a stable key for x-for reactivity.
    const charges = (Array.isArray(cfg.charges) ? cfg.charges : []).map((c, i) => ({
        _k: 'c0' + i, description: c.description || '', amount: c.amount ?? null,
    }));

    const screen = {
        ...cfg,
        specialty_data: specialtyData,
        case_taking: caseTaking,
        vitals,
        charges,
        saveStatus: 'idle',
        saveLabel: 'Click Save to keep changes',
        icdResults: [],
        vitalsWarnings: [],
        lastVisitNote: '',
        autosaveTimer: null,
        dirty: false,
        peek: null,         // loaded summary of the currently-expanded past visit
        peekId: null,       // which history row is expanded (accordion)
        peekLoading: false,
        chargesStatus: 'idle',
        chargesLabel: '',
        _completing: false,           // re-entrancy guard for confirmComplete
        prescriptionsCleared: false,  // set when doctor deliberately removes all meds

        // Call on any user edit so manual save / complete knows there's something to persist.
        markDirty() { this.dirty = true; },

        scheduleAutosave() {
            if (!this.editable) return;
            clearTimeout(this._saveDebounce);
            this._saveDebounce = setTimeout(() => {
                if (this.dirty) this.save();
            }, 800);
        },

        // ---- Charges (visit invoice line items) ----
        _chargeKey: 0,
        chargesDirty: false,
        invoiceLinkLabel() {
            if (this.invoiceNumber) {
                return this.invoiceNumber + (this.invoiceDate ? ' · ' + this.invoiceDate : '');
            }
            return 'Invoice';
        },
        markChargesDirty() { this.dirty = true; this.chargesDirty = true; },
        // Editing the amount by hand unlinks it from the charge total.
        onPaymentAmountInput() { this.paymentAmountTouched = true; this.markChargesDirty(); },

        addCharge() {
            this.markChargesDirty();
            this.charges.push({ _k: 'c' + (++this._chargeKey), description: '', amount: null });
        },
        removeCharge(idx) { this.markChargesDirty(); this.charges.splice(idx, 1); this.syncPaymentAmount(); },
        // ---- Payment card ----
        // The amount field starts as the charges total and stays linked to it
        // until the doctor types their own figure (paymentAmountTouched).
        paymentAmountTouched: false,
        payableBase() {
            const typed = parseFloat(this.payment.amount);
            return isNaN(typed) ? 0 : typed;
        },
        gstAmount() {
            if (!this.payment.gst) return 0;
            return Math.round(this.payableBase() * (parseFloat(this.payment.tax_percent) || 0)) / 100;
        },
        totalPayable() {
            return Math.round((this.payableBase() + this.gstAmount()) * 100) / 100;
        },
        syncPaymentAmount() {
            if (this.paymentAmountTouched) return;
            this.payment.amount = this.chargesTotal();
        },
        chargesTotal() {
            return (this.charges || []).reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);
        },
        // Persist symptoms to the visit_symptoms table NOW (synchronously
        // awaited). Used before navigating/completing so an in-flight
        // fire-and-forget save isn't cancelled by the page leaving.
        async persistSymptomsNow() {
            if (!this.editable) return true;
            try {
                const r = await fetch('/api/v1/visits/' + this.visitId + '/symptoms', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ symptoms: this.symptoms || [] }),
                });
                return r.ok;
            } catch (e) {
                return false;
            }
        },

        // Block completing a visit while charges are unsaved; flush symptoms
        // + draft save first so nothing entered just before is lost.
        async confirmComplete(ev) {
            ev.preventDefault();
            // Grab the form NOW: currentTarget is only valid during dispatch,
            // and the confirm dialog below awaits.
            const form = ev.currentTarget || ev.target;
            if (this._completing) return;             // guard against double-submit
            if (!await uiConfirm('The visit will be locked read-only once completed.', {
                title: 'Complete this visit?', confirmLabel: 'Complete visit',
            })) return;

            this._completing = true;
            try {
                // Flush symptoms, charges + payment, then force-save the main
                // form BEFORE the visit is locked. If a save fails, abort —
                // completing now would lock the visit read-only and permanently
                // lose the unsaved data.
                await this.persistSymptomsNow();
                if (this.charges.length || this.payableBase() > 0) {
                    await this.saveCharges(true);
                    if (this.chargesStatus === 'error') {
                        await uiAlert('Could not save charges / payment — ' + (this.chargesLabel || 'please try again') +
                              '.\n\nThe visit was NOT completed so your data is safe.',
                              { title: 'Visit not completed', danger: true });
                        this._completing = false;
                        return;
                    }
                }
                const ok = await this.save(true);
                if (!ok) {
                    await uiAlert('Could not save the visit before completing — ' +
                          (this.saveLabel || 'please try again') +
                          '.\n\nThe visit was NOT completed so your data is safe. ' +
                          'Fix the issue and try again.', { title: 'Visit not completed', danger: true });
                    this._completing = false;
                    return;
                }
                // Attach a full snapshot for the server-side complete handler
                // (backup in case the fetch autosave was skipped).
                const payloadInput = form.querySelector('[name="_visit_payload"]');
                if (payloadInput) {
                    payloadInput.value = JSON.stringify(this.payload());
                }
                // Save confirmed persisted — now it is safe to lock the visit.
                form.submit();
            } catch (e) {
                this._completing = false;
                uiAlert('Unexpected error before completing the visit. It was NOT completed — your data is safe. Please try again.', { title: 'Visit not completed', danger: true });
            }
        },
        // withPayment=true also settles amount / GST / mode / paid-due; the
        // Payment card uses that, the charge rows save on their own.
        async saveCharges(withPayment = false) {
            if (!this.editable) return;
            if (this.charges.length === 0 && !withPayment) return;
            this.chargesStatus = 'saving';
            this.chargesLabel = 'Saving…';
            try {
                // Strip the UI-only _k key before sending.
                const items = this.charges.map(c => ({ description: c.description, amount: c.amount }));
                const body = { items: items };
                if (withPayment) {
                    body.payment = {
                        amount: this.payableBase(),
                        gst: !!this.payment.gst,
                        type: this.payment.type,
                        status: this.payment.status,
                    };
                }
                const r = await fetch('/api/v1/visits/' + this.visitId + '/charges', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await r.json();
                if (data.ok) {
                    this.chargesStatus = 'saved';
                    this.chargesLabel = withPayment
                        ? 'Saved · ₹' + (data.total || 0) + (data.due > 0 ? ' · ₹' + data.due + ' due' : ' · paid')
                        : 'Saved · ₹' + (data.total || 0);
                    this.chargesDirty = false;   // clears the "unsaved" / required state
                    if (data.status) this.payment.status = data.status === 'paid' ? 'paid' : 'due';
                    if (data.due != null) this.payment.due = data.due;
                    if (data.invoice_id) {
                        this.invoiceId = data.invoice_id;
                        if (data.invoice_number) this.invoiceNumber = data.invoice_number;
                        if (data.invoice_date) this.invoiceDate = data.invoice_date;
                    }
                } else throw new Error(data.error || 'Save failed');
            } catch (e) {
                this.chargesStatus = 'error';
                this.chargesLabel = e.message;
            }
        },

        // Accordion: expand a past visit inline (read-only). Click again = close.
        async togglePeek(id) {
            if (this.peekId === id) { this.peekId = null; return; }
            this.peekId = id;
            this.peek = null;
            this.peekLoading = true;
            try {
                const r = await fetch('/api/v1/visits/' + id + '/summary', {
                    credentials: 'same-origin', headers: { 'Accept': 'application/json' },
                });
                if (r.ok && this.peekId === id) this.peek = await r.json();
            } catch (e) { /* user can Open full visit */ }
            this.peekLoading = false;
        },

        formatPeekDate(d) {
            if (!d) return '';
            try { return new Date(d.replace(' ', 'T')).toLocaleString(); } catch (e) { return d; }
        },

        _saveDebounce: null,
        _rxKeyCounter: 0,
        initVisitScreen() {
            (this.prescriptions || []).forEach(line => {
                this.ensureRxLineKey(line);
                this.hydrateRxLine(line);
            });
            if (this.chargesPrefilled && this.charges.length) {
                this.chargesDirty = true;
                this.chargesLabel = 'Review and save charges';
            }
            // An amount already billed wins; otherwise the field tracks the
            // charge lines until someone types over it.
            this.paymentAmountTouched = (parseFloat(this.payment.amount) || 0) > 0;
            this.syncPaymentAmount();
            this.$nextTick(() => this.refreshAllFrequencySelects());
            if (!this.editable) return;
            this.$el.addEventListener('input', () => this.onFormEdit());
            this.$el.addEventListener('change', () => this.onFormEdit());
        },

        ensureRxLineKey(line) {
            if (line._rxKey) return;
            line._rxKey = 'rxn' + (++this._rxKeyCounter);
        },

        hydrateRxLine(line) {
            if (!line.drug_form) {
                line.drug_form = this.inferRxForm(line.drug_name, line.dose_unit, null);
            }
            if (!line.frequency_preset) {
                this.applyRxFormDefaults(line, line.drug_form, true);
            }
            if (line.frequency_preset) {
                line.frequency = this.presetToLegacyFreq(line.frequency_preset);
            }
        },

        presetToLegacyFreq(preset) {
            const p = String(preset || '').toUpperCase();
            if (p === '1-1-1-1' || p.includes('QID')) return 'QID';
            if (p === '1-1-1' || p.includes('TDS')) return 'TDS';
            if (p === '1-0-1' || p.includes(' BD')) return 'BD';
            if (p === '1-0-0' || p === '0-0-1' || p === '0-1-0' || p === 'OD' || p.includes(' OD')) return 'OD';
            if (p === 'SOS' || p.includes('SOS')) return 'SOS';
            if (p === 'PRN') return 'PRN';
            if (p === 'WEEKLY' || p.includes('WEEKLY')) return 'weekly';
            if (p === 'MONTHLY' || p.includes('MONTHLY')) return 'monthly';
            if (['OD', 'BD', 'TDS', 'QID', 'weekly', 'monthly', 'SOS', 'PRN'].includes(preset)) return preset;
            return 'BD';
        },

        // Alpine x-for inside <select> is unreliable — build options imperatively.
        initFrequencySelect(el, line, stepObj) {
            if (el._rxFreqBound) return;
            el._rxFreqBound = true;

            const render = () => {
                if (!el.isConnected) return;
                const cur = String(stepObj ? (stepObj.preset || '') : (line.frequency_preset || ''));
                const opts = this.frequencyOptionsFor(line);
                el.innerHTML = '';
                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = 'Frequency…';
                el.appendChild(blank);
                opts.forEach(o => {
                    const opt = document.createElement('option');
                    opt.value = o.value;
                    opt.textContent = o.label;
                    el.appendChild(opt);
                });
                if (cur && !opts.some(o => o.value === cur)) {
                    const opt = document.createElement('option');
                    opt.value = cur;
                    opt.textContent = cur;
                    el.appendChild(opt);
                }
                el.value = cur;
            };

            if (stepObj) {
                if (!stepObj._renderFreqSelect) stepObj._renderFreqSelect = render;
            } else {
                line._renderFreqSelect = render;
            }

            render();

            el.addEventListener('change', () => {
                const val = el.value;
                if (stepObj) {
                    stepObj.preset = val;
                } else {
                    line.frequency_preset = val;
                    line.frequency = this.presetToLegacyFreq(val);
                }
                this.markDirty();
            });
        },

        refreshAllFrequencySelects() {
            (this.prescriptions || []).forEach(line => {
                line._renderFreqSelect?.();
                (line.tapering_steps || []).forEach(step => step._renderFreqSelect?.());
            });
        },

        onFormEdit() {
            this.markDirty();
            this.scheduleAutosave();
        },

        // ---- Voice dictation (Web Speech API, browser-native) ----
        listening: null,            // which field is currently being dictated
        _recognition: null,
        get voiceSupported() {
            return typeof window !== 'undefined' &&
                ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window);
        },

        dictateInto(field) {
            if (!this.voiceSupported || !this.editable) return;
            // Toggle off if already listening for this field.
            if (this.listening === field && this._recognition) {
                this._recognition.stop();
                return;
            }
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            const rec = new SR();
            rec.lang = this.voiceLang || 'en-IN';
            rec.interimResults = false;
            rec.continuous = false;
            this._recognition = rec;
            this.listening = field;

            rec.onresult = (e) => {
                const text = Array.from(e.results).map(r => r[0].transcript).join(' ').trim();
                if (!text) return;
                const existing = (this[field] || '').trim();
                this[field] = existing ? (existing + ' ' + text) : text;
            };
            rec.onerror = () => { this.listening = null; };
            rec.onend = () => { this.listening = null; this._recognition = null; this.markDirty(); };
            try { rec.start(); } catch (e) { this.listening = null; }
        },

        rxLineHasContent(p) {
            if (!p) return false;
            const name = (p.drug_name || '').trim();
            const taper = Array.isArray(p.tapering_steps) ? p.tapering_steps : [];
            return !!(p.drug_id || p.remedy_id || name || p.frequency_preset || p.dose_amount
                || p.duration_days || taper.length);
        },

        countRxLines() {
            return (this.prescriptions || []).filter(p => this.rxLineHasContent(p)).length;
        },

        payload() {
            const cleanTapering = (steps) => {
                if (!Array.isArray(steps) || !steps.length) return null;
                return steps.map(s => ({
                    days: s.days,
                    preset: s.preset || null,
                    food: s.food || 'any',
                    dose_amount: s.dose_amount !== '' && s.dose_amount != null ? s.dose_amount : null,
                }));
            };
            // Strip UI-only flags from each rx line before serializing.
            const cleanRx = (this.prescriptions || []).map(p => ({
                drug_id: p.drug_id || null,
                remedy_id: p.remedy_id || null,
                drug_name: p.drug_name || '',
                potency: p.potency || null,
                dosage: p.dosage || null,
                dose_unit: p.dose_unit || null,
                dose_amount: p.dose_amount !== '' && p.dose_amount != null ? p.dose_amount : null,
                frequency_preset: p.frequency_preset || null,
                frequency: p.frequency || this.presetToLegacyFreq(p.frequency_preset) || 'BD',
                duration_days: p.duration_days !== '' && p.duration_days != null ? p.duration_days : null,
                food_timing: p.food_timing || 'any',
                mix_with: p.mix_with || null,
                tapering_steps: cleanTapering(p.tapering_steps),
                instructions: p.instructions || null,
            }));

            return {
                chief_complaint: this.chief_complaint,
                history: this.history,
                examination: this.examination,
                diagnosis: this.diagnosis,
                icd10_code: this.icd10_code,
                clinical_notes: this.clinical_notes,
                reports_notes: this.reports_notes,
                condition_score: this.condition_score,
                follow_up_date: this.follow_up_date,
                follow_up_reason: this.follow_up_reason,
                follow_up_notes: this.follow_up_notes,
                visited_at: this.visited_at,
                vitals: this.vitals,
                prescriptions: cleanRx,
                prescriptions_cleared: this.prescriptionsCleared && cleanRx.every(p => !p.drug_id && !p.remedy_id && !p.drug_name && !p.frequency_preset && !p.dose_amount),
                case_taking: this.case_taking,
                specialty_data: {
                    ...this.specialty_data,
                    case_taking: this.case_taking,
                },
                _form_blob: {
                    chief_complaint: this.chief_complaint,
                    diagnosis: this.diagnosis,
                    clinical_notes: this.clinical_notes,
                    prescriptions: cleanRx,
                    symptoms: this.symptoms || [],
                    ghost_revealed: this.ghostRevealed,
                },
            };
        },

        // Persist the current form. Returns true on confirmed success, false on
        // failure. Pass force=true (used before completing) to save even when the
        // dirty flag is stale — guarantees nothing entered is lost on completion.
        async save(force = false) {
            if (!this.editable) return true;          // read-only: nothing to persist
            if (!this.dirty && !force) return true;   // nothing changed
            this.saveStatus = 'saving';
            this.saveLabel = 'Saving…';
            try {
                await this.persistSymptomsNow();
                const r = await fetch('/api/v1/visits/' + this.visitId + '/autosave', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.payload()),
                });
                let data = {};
                try { data = await r.json(); } catch (e) { /* non-JSON (likely 500 HTML) */ }
                if (r.ok && data.ok) {
                    const expectedRx = force ? this.countRxLines() : 0;
                    if (force && expectedRx > 0) {
                        if (data.prescriptions_skipped) {
                            throw new Error('Medicines were not saved (empty sync). Click Save now, then try completing again.');
                        }
                        if (data.prescriptions_synced != null && data.prescriptions_synced < expectedRx) {
                            throw new Error('Only ' + data.prescriptions_synced + ' of ' + expectedRx + ' medicines saved.');
                        }
                    }
                    this.saveStatus = 'saved';
                    this.saveLabel = '✓ Saved ' + new Date().toLocaleTimeString();
                    this.dirty = false;
                    this.prescriptionsCleared = false;   // consumed; reset for next edits
                    this.vitalsWarnings = data.warnings || [];
                    return true;
                }
                throw new Error(data.error || ('Save failed (HTTP ' + r.status + ')'));
            } catch (e) {
                this.saveStatus = 'error';
                this.saveLabel = '⚠ ' + e.message;
                return false;
            }
        },

        async searchIcd(q) {
            if (!q || q.length < 2) { this.icdResults = []; return; }
            try {
                const r = await fetch('/api/v1/icd10/search?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
                const data = await r.json();
                this.icdResults = data.codes || [];
            } catch (e) {
                this.icdResults = [];
            }
        },

        // One-click print preview: flush the draft so the PDF reflects what's
        // on screen, then open the A5 preview in a new tab.
        async printRx(paper) {
            if (this.editable && this.dirty) {
                try { await this.save(); } catch (e) { /* preview still opens */ }
            }
            window.open('/prescriptions/' + this.visitId + '/pdf' + (paper === 'a4' ? '?paper=a4' : ''), '_blank', 'noopener');
        },

        addRxLine() {
            this.dirty = true;
            this.prescriptionsCleared = false;
            this.prescriptions.push({
                _rxKey: 'rxn' + (++this._rxKeyCounter),
                drug_id: null, remedy_id: null, drug_name: '',
                potency: '', dosage: '',
                drug_form: 'tablet',
                dose_unit: '', dose_amount: '', mix_with: '',
                frequency_preset: '', frequency: 'BD',
                duration_days: '', food_timing: 'any', instructions: '',
                tapering_steps: null,
            });
        },

        removeRxLine(idx) {
            this.dirty = true;
            this.prescriptions.splice(idx, 1);
            // If the doctor has now removed every line, mark this as a deliberate
            // clear so the next save is allowed to wipe the saved medicines.
            // (A blank autosave without this flag is treated as a no-op server-side.)
            if (!(this.prescriptions || []).some(p => p.drug_id || p.remedy_id || p.drug_name || p.frequency_preset || p.dose_amount)) {
                this.prescriptionsCleared = true;
            }
        },

        setFollowUp(days) {
            const d = new Date();
            d.setDate(d.getDate() + days);
            this.follow_up_date = d.toISOString().slice(0, 10);
            this.markDirty();
        },

        async cloneLastVisit() {
            if (!this.editable) return;
            // Confirm overwrite if doctor already filled stuff.
            const hasData = this.diagnosis || this.chief_complaint || (this.prescriptions || []).some(p => p.drug_name);
            if (hasData && !await uiConfirm('Existing entries in this form will be replaced.', {
                title: 'Copy last visit?', confirmLabel: 'Overwrite', danger: true,
            })) return;
            try {
                const r = await fetch('/api/v1/visits/' + this.visitId + '/clone-last', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                });
                const data = await r.json();
                if (data.ok) {
                    this.lastVisitNote = 'Cloned from visit on ' + (data.visited_at || 'last visit');
                    // Reload the page so server-rendered fields reflect the merge.
                    location.reload();
                } else {
                    uiAlert(data.error || 'No previous visit found.');
                }
            } catch (e) {
                uiAlert('Network error — please try again.');
            }
        },

        async revealGhost(section) {
            if (this.ghostRevealed.includes(section)) return;
            this.ghostRevealed.push(section);
            // Tell the server — recordSectionExpand may auto-promote into visible_modules.
            try {
                await fetch('/api/v1/clinic-settings/section-state', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ section: section, state: 'expanded' }),
                });
            } catch (e) { /* ignore — UI still reveals */ }
        },

        async recordSection(section, isOpen) {
            // Fired by <details> @toggle; tracks expand/collapse counts.
            try {
                await fetch('/api/v1/clinic-settings/section-state', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ section: section, state: isOpen ? 'expanded' : 'collapsed' }),
                });
            } catch (e) { /* ignore */ }
        },

        taperingTotalDays(steps) {
            if (!Array.isArray(steps)) return 0;
            return steps.reduce((sum, s) => sum + (parseInt(s.days, 10) || 0), 0);
        },

        inferRxForm(drugName, doseUnit, catalogForm) {
            if (catalogForm && (window.__RX_FORM_PRESETS || {})[catalogForm]) return catalogForm;
            const unit = (doseUnit || '').toLowerCase();
            const unitMap = { tablet: 'tablet', capsule: 'capsule', ml: 'syrup', drops: 'drops', puff: 'inhaler', sachet: 'syrup', unit: 'injection' };
            if (unit && unitMap[unit]) return unitMap[unit];
            const n = (drugName || '').toLowerCase();
            if (!n) return 'tablet';
            if (n.includes('syrup') || n.includes('suspension')) return 'syrup';
            if (n.includes('injection') || /\binj\b/.test(n) || n.includes('vial')) return 'injection';
            if (n.includes('cream') || n.includes('ointment') || n.includes(' gel') || n.includes('lotion')) return 'cream';
            if (n.includes('drop')) return 'drops';
            if (n.includes('inhaler') || n.includes('rotacap') || n.includes('respule')) return 'inhaler';
            if (n.includes('patch')) return 'patch';
            if (n.includes('capsule') || /\bcap\b/.test(n)) return 'capsule';
            if (n.includes('suppository') || n.includes('supp')) return 'other';
            if (n.includes('tablet') || /\btab\b/.test(n)) return 'tablet';
            return 'tablet';
        },

        frequencyOptionsFor(line) {
            const form = line.drug_form || this.inferRxForm(line.drug_name, line.dose_unit, null);
            const presets = (window.__RX_FORM_PRESETS || {})[form]
                || (window.__RX_FORM_PRESETS || {}).tablet
                || [];
            const opts = [...presets];
            const cur = line.frequency_preset;
            if (cur && !opts.some(o => o.value === cur)) {
                opts.unshift({ value: cur, label: cur });
            }
            return opts;
        },

        syncFrequencyForForm(line) {
            // When medicine form changes, pick the default for the new form if the
            // current preset doesn't belong to that form's option list.
            const opts = this.frequencyOptionsFor(line);
            if (line.frequency_preset && !opts.some(o => o.value === line.frequency_preset)) {
                const defs = (window.__RX_FORM_DEFAULTS || {})[line.drug_form || 'tablet'] || {};
                line.frequency_preset = defs.frequency_preset || '';
                line.frequency = this.presetToLegacyFreq(line.frequency_preset);
            }
            this.$nextTick(() => line._renderFreqSelect?.());
        },

        syncRxFormFromName(idx) {
            const line = this.prescriptions[idx];
            if (!line) return;
            const prev = line.drug_form;
            line.drug_form = this.inferRxForm(line.drug_name, line.dose_unit, null);
            if (prev !== line.drug_form) {
                this.syncFrequencyForForm(line);
            }
            this.markDirty();
        },

        applyRxFormDefaults(line, form, skipIfSet = true) {
            const defs = (window.__RX_FORM_DEFAULTS || {})[form] || {};
            if (!skipIfSet || !line.dose_unit) {
                if (defs.dose_unit) line.dose_unit = defs.dose_unit;
            }
            if ((!line.dose_amount && line.dose_amount !== 0) && defs.dose_amount != null) {
                line.dose_amount = defs.dose_amount;
            }
            if (!line.frequency_preset && defs.frequency_preset) {
                line.frequency_preset = defs.frequency_preset;
                line.frequency = this.presetToLegacyFreq(line.frequency_preset);
            }
            this.$nextTick(() => line._renderFreqSelect?.());
        },

        // ── Prescription panel (merged in — single coherent scope) ──────────
        // Previously a separate prescriptionPanel() x-data that reached into the
        // parent via window.__visit. Now it lives here so prescriptions/editable
        // and the medicine inputs bind to ONE scope — no cross-component hops.
        templates: [],
        suggestions: [],
        saveTplModal: {
            open: false,
            name: '',
            description: '',
            scope: 'mine',     // 'mine' (this doctor) | 'clinic' (whole clinic)
            saving: false,
            error: '',
        },

        // True when at least one line carries a real medicine — drives the
        // "Save as template" button visibility.
        get hasRx() {
            return (this.prescriptions || []).some(p => p.drug_id || p.drug_name);
        },

        openSaveTemplate() {
            if (!this.editable) return;
            if (!this.hasRx) {
                uiAlert('Add at least one medicine before saving as template.');
                return;
            }
            this.saveTplModal.name = (this.chief_complaint || '').trim().slice(0, 80);
            this.saveTplModal.description = '';
            this.saveTplModal.scope = 'mine';
            this.saveTplModal.error = '';
            this.saveTplModal.saving = false;
            this.saveTplModal.open = true;
        },

        async confirmSaveTemplate() {
            if (this.saveTplModal.saving) return;
            const m = this.saveTplModal;
            const name = (m.name || '').trim();
            if (name === '') { m.error = 'Please enter a name.'; return; }

            // Map prescription rows -> template item shape the API expects.
            const items = (this.prescriptions || [])
                .filter(p => p.drug_id || p.drug_name)
                .map(p => ({
                    drug_id: p.drug_id || null,
                    remedy_id: p.remedy_id || null,
                    potency: p.potency || null,
                    dose_unit: p.dose_unit || null,
                    dose_amount: p.dose_amount || null,
                    frequency_preset: p.frequency_preset || null,
                    duration_days: p.duration_days || null,
                    food_timing: p.food_timing || 'any',
                    mix_with: p.mix_with || null,
                    tapering_steps: cleanTapering(p.tapering_steps),
                    instructions: p.instructions || null,
                }));
            if (items.length === 0) { m.error = 'No medicines to save.'; return; }

            m.saving = true;
            m.error = '';
            try {
                const r = await fetch('/api/v1/prescriptions/templates', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        name: name,
                        description: (m.description || '').trim() || null,
                        scope: m.scope === 'clinic' ? 'clinic' : 'mine',
                        mode: this.useHomeo ? 'homeopathic' : 'allopathic',
                        items: items,
                    }),
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.ok) {
                    m.error = data.error || ('Save failed (HTTP ' + r.status + ').');
                    m.saving = false;
                    return;
                }
                m.open = false;
                m.saving = false;
                // Refresh the "Apply:" chips so the new template shows immediately.
                await this.loadTemplates();
            } catch (e) {
                m.error = 'Network error.';
                m.saving = false;
            }
        },

        async loadTemplates() {
            try {
                const r = await fetch('/api/v1/prescriptions/templates?scope=all', {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await r.json();
                this.templates = data.templates || [];
                this.suggestions = data.suggestions || [];
            } catch (e) { /* skip */ }
        },

        async applyTemplate(templateId) {
            if (!this.editable) return;
            if (this.hasRx && !await uiConfirm('Template medicines will be appended to the current prescription.', {
                title: 'Apply template?', confirmLabel: 'Append',
            })) return;
            try {
                const r = await fetch('/api/v1/prescriptions/templates/' + templateId + '/apply/' + this.visitId, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                });
                const data = await r.json();
                if (data.ok) {
                    // Reload to pick up the newly inserted prescriptions.
                    location.reload();
                } else {
                    uiAlert(data.error || 'Could not apply template.');
                }
            } catch (e) {
                uiAlert('Network error.');
            }
        },

        async activateSuggestion(sug) {
            const name = prompt('Save this combination as a template. Name it:', sug.name.replace(/^Suggested:\s*/, ''));
            if (!name) return;
            try {
                await fetch('/api/v1/prescriptions/templates/' + sug.id + '/activate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ name: name }),
                });
                this.loadTemplates();
            } catch (e) {
                uiAlert('Could not save template.');
            }
        },

        async dismissSuggestion(suggestionId) {
            try {
                await fetch('/api/v1/prescriptions/templates/' + suggestionId + '/delete', {
                    method: 'POST', headers: { 'Accept': 'application/json' },
                });
                this.suggestions = this.suggestions.filter(s => s.id !== suggestionId);
            } catch (e) { /* skip */ }
        },

        isGenericDrugQuery(query) {
            // Only skip the catalog API for standalone broad form words (e.g.
            // "tablet", "syrup"). Short abbrevs like "syr" / "tab" are brand
            // prefixes doctors type every day — always search the catalog.
            const blockApi = new Set([
                'tablet', 'tablets', 'syrup', 'capsule', 'capsules',
                'injection', 'cream', 'drops', 'suspension', 'ointment',
                'gel', 'lotion', 'powder', 'solution', 'mg', 'ml', 'mcg', 'iu',
            ]);
            const tokens = (query || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
            if (!tokens.length) return false;
            return tokens.every(t => blockApi.has(t));
        },

        drugNameMatchesQuery(name, query) {
            const hay = (name || '').toLowerCase();
            // Full form words only — abbrevs like "syr" / "tab" are search terms.
            const generic = new Set([
                'tablet', 'tablets', 'syrup', 'capsule', 'capsules',
                'cream', 'injection', 'drops', 'suspension', 'ointment',
                'gel', 'lotion', 'powder', 'solution', 'mg', 'ml', 'mcg', 'iu',
            ]);
            const abbrevs = { syr: 'syrup', syp: 'syrup', tab: 'tablet', tabs: 'tablet', cap: 'capsule', caps: 'capsule', inj: 'injection', crm: 'cream' };
            const tokens = (query || '').toLowerCase().trim().split(/\s+/).filter(t => t && !generic.has(t));
            if (!tokens.length) return false;
            const words = hay.split(/\s+/);
            return tokens.every(t => {
                if (hay.includes(t)) return true;
                const alt = abbrevs[t];
                if (alt && hay.includes(alt)) return true;
                return words.some(w => w.startsWith(t) || (alt && w.startsWith(alt)));
            });
        },

        localDrugSuggestions(idx, query) {
            const seen = new Set();
            const out = [];
            const genericOnly = this.isGenericDrugQuery(query);
            (this.prescriptions || []).forEach((p, i) => {
                if (i === idx) return;
                const name = (p.drug_name || '').trim();
                if (!name || seen.has(name.toLowerCase())) return;
                const matches = genericOnly
                    ? this.drugNameContainsToken(name, query)
                    : this.drugNameMatchesQuery(name, query);
                if (!matches) return;
                seen.add(name.toLowerCase());
                out.push({
                    id: p.drug_id || null,
                    name,
                    strength: '',
                    form: p.drug_form || this.inferRxForm(name, p.dose_unit, null),
                    source: 'this_visit',
                });
            });
            return out;
        },

        drugNameContainsToken(name, query) {
            const hay = (name || '').toLowerCase();
            const tokens = (query || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
            return tokens.some(t => hay.includes(t) || hay.split(/\s+/).some(w => w.startsWith(t)));
        },

        mergeDrugSuggestions(local, remote) {
            const out = [];
            const seenIds = new Set();
            const seenNames = new Set();
            [...local, ...(remote || [])].forEach(d => {
                const nameKey = (d.name || '').trim().toLowerCase();
                if (!nameKey) return;
                if (d.id) {
                    if (seenIds.has(d.id)) return;
                    seenIds.add(d.id);
                }
                if (seenNames.has(nameKey)) return;
                seenNames.add(nameKey);
                out.push(d);
            });
            return out;
        },

        onDrugFocus(idx) {
            if (!Array.isArray(this.prescriptions)) return;
            const line = this.prescriptions[idx];
            if (!line) return;
            const query = (line.drug_name || '').trim();
            if (query.length >= 2) {
                line._dropdown = true;
                this.searchDrugFor(idx, query);
                return;
            }
            if (query.length === 1) {
                line._dropdown = true;
                line._searchHint = 'Keep typing — search starts at 2 letters.';
                line._searchError = '';
                line._suggestions = [];
            }
        },

        async searchDrugFor(idx, q) {
            if (!Array.isArray(this.prescriptions)) return;
            const line = this.prescriptions[idx];
            if (!line) return;
            const query = (q || '').trim();
            const minLen = 2;
            if (query.length < minLen) {
                line._suggestions = [];
                line._searchError = '';
                line._searchHint = query.length === 1
                    ? 'Keep typing — search starts at 2 letters.'
                    : '';
                line._dropdown = query.length > 0;
                return;
            }
            line._searchHint = '';

            const genericOnly = this.isGenericDrugQuery(query);
            const local = this.localDrugSuggestions(idx, query);

            if (genericOnly) {
                line._suggestions = local;
                line._dropdown = true;
                line._searchError = local.length === 0
                    ? 'Too broad — type a brand name (e.g. Althrocin), not just the dosage form.'
                    : '';
                return;
            }

            if (local.length) {
                line._suggestions = local;
                line._dropdown = true;
                line._searchError = '';
            }

            const url = this.useHomeo
                ? '/api/v1/remedies/search?q=' + encodeURIComponent(query)
                : '/api/v1/drugs/search?q=' + encodeURIComponent(query);
            try {
                const r = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!r.ok) {
                    if (!local.length) {
                        line._suggestions = [];
                        line._dropdown = true;
                        line._searchError = r.status === 402
                            ? 'Prescription module not active for this clinic — contact admin.'
                            : 'Drug search failed (HTTP ' + r.status + ').';
                    }
                    return;
                }
                const data = await r.json();
                line._suggestions = this.mergeDrugSuggestions(local, data.drugs || data.remedies || []);
                line._dropdown = true;
                line._searchError = line._suggestions.length === 0
                    ? 'Not in catalog yet — you can still use "' + query + '" as typed.'
                    : '';
            } catch (e) {
                if (!local.length) {
                    line._suggestions = [];
                    line._dropdown = true;
                    line._searchError = 'Network error fetching medicines.';
                }
            }
        },

        pickDrugFor(idx, drug) {
            if (!Array.isArray(this.prescriptions)) return;
            const line = this.prescriptions[idx];
            if (!line) return;
            this.prescriptionsCleared = false;
            if (this.useHomeo) {
                line.remedy_id = drug.id || null;
                line.drug_id = null;
            } else {
                line.drug_id = drug.id || null;
                line.remedy_id = null;
            }
            line.drug_name = drug.name + (drug.strength && !String(drug.name).includes(drug.strength) ? ' ' + drug.strength : '');

            const form = drug.form || this.inferRxForm(line.drug_name, line.dose_unit, null);
            const formChanged = line.drug_form !== form;
            line.drug_form = form;

            // Smart defaults: pre-fill from the clinic's last prescription of
            // this drug — but never overwrite what the doctor already entered.
            const def = drug.defaults || {};
            if (!line.frequency_preset && def.frequency_preset) {
                line.frequency_preset = def.frequency_preset;
                line.frequency = def.frequency || this.presetToLegacyFreq(def.frequency_preset);
            }
            if (!line.duration_days && def.duration_days) line.duration_days = def.duration_days;
            if ((!line.food_timing || line.food_timing === 'any') && def.food_timing) line.food_timing = def.food_timing;
            if (!line.dose_unit && def.dose_unit) line.dose_unit = def.dose_unit;
            if (!line.dose_amount && def.dose_amount) line.dose_amount = def.dose_amount;

            if (formChanged || !line.frequency_preset) {
                this.applyRxFormDefaults(line, form, true);
            }
            this.syncFrequencyForForm(line);

            line._suggestions = [];
            line._dropdown = false;
            line._searchHint = '';
            line._searchError = '';

            // Picking a medicine is the primary way doctors add an Rx line — it
            // MUST mark the visit dirty so the pre-complete save actually fires.
            this.markDirty();
        },

        addTaperingStep(line) {
            if (!Array.isArray(line.tapering_steps)) line.tapering_steps = [];
            // Seed sensible defaults — last step's frequency, 3 days.
            const last = line.tapering_steps[line.tapering_steps.length - 1];
            const formDef = ((window.__RX_FORM_DEFAULTS || {})[line.drug_form || 'tablet'] || {}).frequency_preset || '1-0-1';
            line.tapering_steps.push({
                days: 3,
                dose_amount: last && last.dose_amount != null && last.dose_amount !== ''
                    ? last.dose_amount
                    : (line.dose_amount !== '' && line.dose_amount != null ? line.dose_amount : 1),
                preset: last ? last.preset : (line.frequency_preset || formDef),
                food: last ? last.food : (line.food_timing || 'after'),
            });
            this.markDirty();
            this.$nextTick(() => this.refreshAllFrequencySelects());
        },
    };
    window.__visit = screen;
    return screen;
}

// ─────────────────────────────────────────────────────────────
// symptomPicker() — chip-style autocomplete (3-layer search)
// ─────────────────────────────────────────────────────────────
function symptomPicker() {
    return {
        // The parent visitScreenV2 owns the canonical symptoms list. We MUST
        // read it from window.__visit (set in visitScreenV2.initAutosave) —
        // Alpine's `this.$root` resolves to appShell on <html>, which is the
        // WRONG scope and would silently create a parallel empty array there.
        // That's exactly why symptoms never appeared after save+reload: the UI
        // was reading from appShell while the data lived on visitScreenV2.
        get symptoms() {
            const v = window.__visit;
            if (!v) return [];
            if (!Array.isArray(v.symptoms)) v.symptoms = [];
            return v.symptoms;
        },
        set symptoms(val) { if (window.__visit) window.__visit.symptoms = val; },
        get editable() { return !!(window.__visit && window.__visit.editable); },
        get chief_complaint() { return window.__visit ? (window.__visit.chief_complaint || '') : ''; },
        set chief_complaint(v) { if (window.__visit) window.__visit.chief_complaint = v; },

        query: '',
        suggestions: [],
        showSuggestions: false,

        // Browse-by-system (Review-of-Systems style category picker)
        browseOpen: false,
        categories: [],
        catMatches: [],     // systems matching the current type-search query
        activeCat: null,
        catSymptoms: [],
        catLoading: false,

        init() {
            // Symptoms are server-rendered into $root.symptoms (no flash).
            // Nothing to fetch on mount — saves an API round-trip.
        },

        // Load the category index once (shared by browse + type-search).
        async loadCategories() {
            if (this.categories.length) return;
            try {
                const r = await fetch('/api/v1/symptoms/by-category', {
                    credentials: 'same-origin', headers: { 'Accept': 'application/json' },
                });
                if (!r.ok) {
                    this.categories = [];
                    return;
                }
                const data = await r.json();
                this.categories = data.categories || [];
            } catch (e) { this.categories = []; }
        },

        async toggleBrowse() {
            this.browseOpen = !this.browseOpen;
            if (this.browseOpen) {
                await this.loadCategories();
            }
        },

        async openCategory(key) {
            if (this.activeCat === key) { this.activeCat = null; this.catSymptoms = []; return; }
            this.activeCat = key;
            this.catLoading = true;
            this.catSymptoms = [];
            try {
                const r = await fetch('/api/v1/symptoms/by-category?cat=' + encodeURIComponent(key), {
                    credentials: 'same-origin', headers: { 'Accept': 'application/json' },
                });
                if (r.ok) {
                    const data = await r.json();
                    this.catSymptoms = data.symptoms || [];
                } else {
                    this.catSymptoms = [];
                }
            } catch (e) { this.catSymptoms = []; }
            this.catLoading = false;
        },

        isSelected(label) {
            const l = (label || '').toLowerCase();
            return this.symptoms.some(s => s.label.toLowerCase() === l);
        },

        // Quick-toggle a pill: add if absent, remove if present.
        toggleSymptom(sug) {
            const label = (sug.label || '').trim();
            if (!label) return;
            const i = this.symptoms.findIndex(s => s.label.toLowerCase() === label.toLowerCase());
            if (i >= 0) { this.symptoms.splice(i, 1); }
            else {
                this.symptoms.push({ label: label, master_id: sug.master_id || null, source: sug.source || 'master' });
            }
            if (window.__visit) window.__visit.markDirty();
            this.persistSymptoms();
        },

        // Short aliases so "gi", "cvs", "msk" etc. surface the right system.
        catAliases: {
            gi: 'gi', git: 'gi', cvs: 'cardio', cardiac: 'cardio', heart: 'cardio',
            resp: 'respiratory', lungs: 'respiratory', msk: 'ortho', bones: 'ortho',
            ent: 'ent', neuro: 'neuro', brain: 'neuro', skin: 'derma', derm: 'derma',
            gu: 'gu', urinary: 'gu', gyn: 'gyn', endo: 'endo', thyroid: 'endo',
            psych: 'psych', mental: 'psych', allergy: 'allergy', peds: 'peds', kids: 'peds',
        },

        async search() {
            this.showSuggestions = true;
            const q = (this.query || '').trim();
            const ql = q.toLowerCase();

            // Match systems by label, key, or alias → show as "… · system" rows.
            this.catMatches = [];
            if (ql.length >= 2) {
                await this.loadCategories();
                const aliasKey = this.catAliases[ql] || null;
                this.catMatches = this.categories.filter(c =>
                    c.label.toLowerCase().includes(ql)
                    || c.key.toLowerCase().includes(ql)
                    || (aliasKey && c.key === aliasKey)
                ).slice(0, 3);
            }

            try {
                const url = '/api/v1/symptoms/search?q=' + encodeURIComponent(q);
                const r = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                // Filter out anything already selected (case-insensitive label match)
                const taken = new Set(this.symptoms.map(s => s.label.toLowerCase()));
                this.suggestions = (data.symptoms || []).filter(s => !taken.has(s.label.toLowerCase()));
            } catch (e) {
                this.suggestions = [];
            }
        },

        // Clicking a "… · system" row in the dropdown opens browse + that category.
        async pickSystem(cat) {
            this.query = '';
            this.suggestions = [];
            this.catMatches = [];
            this.showSuggestions = false;
            this.browseOpen = true;
            await this.loadCategories();
            await this.openCategory(cat.key);
        },

        exactMatch(q) {
            const norm = (q || '').trim().toLowerCase();
            return this.suggestions.some(s => s.label.toLowerCase() === norm);
        },

        addSymptom(sug) {
            const label = (sug.label || '').trim();
            if (!label) return;
            const taken = this.symptoms.some(s => s.label.toLowerCase() === label.toLowerCase());
            if (taken) return;
            this.symptoms.push({
                label: label,
                master_id: sug.master_id || null,
                source: sug.source || 'custom',
            });
            this.query = '';
            this.suggestions = [];
            this.showSuggestions = false;
            if (window.__visit) window.__visit.markDirty();
        },

        addCustom(rawLabel) {
            const label = (rawLabel || '').trim();
            if (!label) return;
            this.addSymptom({ label: label, source: 'custom', master_id: null });
        },

        addCurrentOrFirst() {
            if (this.suggestions.length > 0 && this.suggestions[0]) {
                this.addSymptom(this.suggestions[0]);
            } else if (this.query.trim()) {
                this.addCustom(this.query);
            }
            this.persistSymptoms();
        },

        removeSymptom(idx) {
            if (idx < 0 || idx >= this.symptoms.length) return;
            this.symptoms.splice(idx, 1);
            if (window.__visit) window.__visit.markDirty();
        },

        async persistSymptoms() {
            const v = window.__visit;
            if (!v || !v.editable) return;
            try {
                const r = await fetch('/api/v1/visits/' + v.visitId + '/symptoms', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ symptoms: this.symptoms }),
                });
                if (!r.ok) {
                    console.warn('Symptom save failed', r.status);
                }
            } catch (e) { /* manual Save will retry */ }
        },
    };
}
</script>
