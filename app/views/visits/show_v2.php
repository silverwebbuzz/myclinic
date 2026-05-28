<?php
/**
 * visits/show_v2.php — Phase 2 single-screen visit layout.
 *
 * No tabs. The 4 fundamentals (Symptoms / Diagnosis / Prescription / Notes)
 * are always visible. Optional sections (Vitals, Labs, Photos, Diet, Consent,
 * Case form) render based on $visibleModules. Hidden sections live as
 * ghost-link chips at the bottom — tap to reveal for this visit only.
 *
 * Ships behind ?new=1 until the default is flipped in VisitController::show().
 */
$sd = $visit['specialty_data'] ?? [];
$case = $sd['case_taking'] ?? [];

// $visibleModules comes from VisitView::visibleModules() in the controller.
$visibleModules = $visibleModules ?? ['vitals', 'case_specialty'];
$has = static fn (string $key) => in_array($key, $visibleModules, true);

// Symptoms / Diagnosis / Prescription / Notes are always rendered.
// case_specialty depends on specialty config AND on the partial existing.
$casePartialPath = __DIR__ . '/partials/' . $casePartial . '.php';
$caseAvailable = is_file($casePartialPath);

$visitId = (int) $visit['id'];
$visibleCount = count($visibleModules);

// Ghost-link list: every optional section NOT in visible_modules.
$optionalModules = ['vitals', 'labs', 'photos', 'diet', 'consent', 'case_specialty'];
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
        'condition_score' => $visit['condition_score'] ?? 5,
        'follow_up_date' => $visit['follow_up_date'] ?? '',
        'follow_up_reason' => $pendingFollowUp['reason'] ?? '',
        'voiceLang' => $voiceLang ?? 'en-IN',
        'follow_up_notes' => $visit['follow_up_notes'] ?? '',
        // datetime-local wants "YYYY-MM-DDTHH:MM"
        'visited_at' => !empty($visit['visited_at']) ? date('Y-m-d\TH:i', strtotime((string) $visit['visited_at'])) : '',
        'vitals' => $vitals,
        'prescriptions' => array_map(static fn ($r) => [
            'drug_id' => $r['drug_id'] ?? null,
            'remedy_id' => $r['remedy_id'] ?? null,
            'drug_name' => $r['drug']['name'] ?? $r['remedy']['name'] ?? '',
            'potency' => $r['potency'] ?? '',
            'dosage' => $r['dosage'] ?? '',
            'frequency_preset' => $r['frequency_preset'] ?? '',
            'frequency' => $r['frequency'] ?? 'BD',
            'duration_days' => $r['duration_days'] ?? '',
            'food_timing' => $r['food_timing'] ?? 'any',
            'instructions' => $r['instructions'] ?? '',
        ], $prescriptions),
        'specialty_data' => $sd,
        'case_taking' => $case,
        'useHomeo' => $useHomeo,
        'visibleModules' => $visibleModules,
        'ghostRevealed' => [],   // sections the doctor revealed this visit
        'symptoms' => $visitSymptoms ?? [],   // hydrated by symptomPicker on mount
        'charges' => $charges ?? [],   // existing invoice line items {description, amount}
    ], JSON_THROW_ON_ERROR), ENT_QUOTES) ?>)"
     x-init="initAutosave()">

    <!-- ====== Patient header (sticky on scroll) ====== -->
    <div class="sticky top-0 z-30 -mx-4 bg-slate-50/95 px-4 pb-2 pt-2 backdrop-blur md:mx-0 md:rounded-xl md:bg-transparent md:px-0">
        <?php
        $visitCount = is_array($recentVisits ?? null) ? count($recentVisits) + 1 : null;
        require __DIR__ . '/../patients/_patient_header.php';
        ?>
    </div>

    <!-- ====== Two-column: visit form (left) + history (right) ====== -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">

    <!-- ====== TODAY'S VISIT ====== -->
    <section class="rounded-xl border bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3" x-data="{ editDate: false }">
            <div class="flex items-baseline gap-3">
                <h2 class="text-base font-semibold text-slate-900">Today's visit</h2>
                <span class="flex items-center gap-2 text-xs text-slate-400">
                    Visit #<?= (int) $visit['visit_number'] ?> ·
                    <!-- Editable visit date/time (for late catch-up entry) -->
                    <template x-if="!editDate">
                        <button type="button" :disabled="!editable" @click="editDate = true"
                                class="text-slate-500 hover:text-emerald-700 disabled:cursor-default disabled:hover:text-slate-500"
                                x-text="visited_at ? new Date(visited_at).toLocaleString() : 'Set date'"></button>
                    </template>
                    <template x-if="editDate">
                        <input type="datetime-local" x-model="visited_at" :disabled="!editable"
                               @change="markDirty()" @blur="editDate = false"
                               class="rounded border border-slate-300 px-2 py-0.5 text-xs">
                    </template>
                </span>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span
                    :class="saveStatus === 'saved' ? 'text-emerald-600' : (saveStatus === 'saving' ? 'text-amber-600' : 'text-slate-400')"
                    x-text="saveLabel"></span>
                <?php if (!$editable): ?>
                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">Read-only</span>
                    <form method="post" action="/visits/<?= $visitId ?>/unlock" class="inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="rounded-full border border-emerald-300 px-2.5 py-0.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Edit this visit</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-5 px-5 py-5">

            <!-- ---- SYMPTOMS — chip picker with autocomplete (Phase 3) ---- -->
            <div x-data="symptomPicker()">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Symptoms</label>

                <!-- Selected chips -->
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2 py-1.5
                            focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                    <template x-for="(s, idx) in symptoms" :key="idx">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-800">
                            <span x-text="s.label"></span>
                            <button type="button" :disabled="!editable" @click="removeSymptom(idx); persistSymptoms()"
                                    class="text-emerald-700 hover:text-rose-600 disabled:opacity-50"
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
                                        class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-emerald-50">
                                    <span><span class="mr-1">📂</span><span x-text="c.label"></span></span>
                                    <span class="text-xs uppercase tracking-wider text-emerald-700" x-text="'system · ' + c.count"></span>
                                </button>
                            </li>
                        </template>
                        <template x-for="(sug, i) in suggestions" :key="sug.label + i">
                            <li>
                                <button type="button" @click="addSymptom(sug); persistSymptoms()"
                                        class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-emerald-50">
                                    <span x-text="sug.label"></span>
                                    <span class="text-xs uppercase tracking-wider"
                                          :class="sug.source === 'personal' ? 'text-emerald-700' : 'text-slate-400'"
                                          x-text="sug.source"></span>
                                </button>
                            </li>
                        </template>
                        <template x-if="query.trim().length >= 2 && !exactMatch(query)">
                            <li class="border-t">
                                <button type="button" @click="addCustom(query); persistSymptoms()"
                                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs text-slate-600 hover:bg-emerald-50">
                                    + Add <strong x-text="query"></strong> as custom
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                <!-- Browse by system (Review-of-Systems quick picker) -->
                <button type="button" :disabled="!editable" @click="toggleBrowse()"
                        class="mt-2 text-xs font-medium text-emerald-700 hover:underline disabled:opacity-50">
                    <span x-text="browseOpen ? '− Hide systems' : '+ Browse by system'"></span>
                </button>
                <div x-show="browseOpen" x-cloak x-collapse class="mt-2 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <!-- Category pills -->
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="c in categories" :key="c.key">
                            <button type="button" @click="openCategory(c.key)"
                                    class="rounded-full border px-2.5 py-1 text-xs transition"
                                    :class="activeCat === c.key ? 'border-emerald-500 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-emerald-400'">
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
                                        :class="isSelected(s.label) ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-700 hover:border-emerald-400'">
                                    <span x-text="isSelected(s.label) ? '✓' : '+'"></span>
                                    <span x-text="s.label"></span>
                                </button>
                            </template>
                            <p x-show="!catLoading && catSymptoms.length === 0" class="text-xs text-slate-400">No symptoms in this system.</p>
                        </div>
                    </div>
                </div>

                <!-- Free-text complaint fallback (kept for migration parity + voice notes) -->
                <details class="mt-2">
                    <summary class="cursor-pointer text-xs text-slate-500 hover:text-slate-700">+ Narrative complaint (optional)</summary>
                    <textarea x-model="chief_complaint" :disabled="!editable" rows="2"
                              placeholder="Free-text complaint, in patient's own words"
                              class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>
                </details>
            </div>

            <!-- ---- PRESCRIPTION ---- -->
            <div x-data="prescriptionPanel()" x-init="loadTemplates()">
                <div class="flex items-baseline justify-between">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prescription</label>
                    <button type="button" :disabled="!editable" @click="cloneLastVisit()"
                            class="text-xs font-medium text-emerald-700 hover:underline disabled:opacity-50">
                        ↻ Same as last visit
                    </button>
                </div>

                <template x-if="lastVisitNote">
                    <p class="mt-1 rounded bg-emerald-50 px-2 py-1 text-xs text-emerald-800"
                       x-text="lastVisitNote"></p>
                </template>

                <!-- Template chips -->
                <div x-show="templates.length || suggestions.length" class="mt-2 flex flex-wrap items-center gap-1.5">
                    <span class="text-xs text-slate-500">Apply:</span>
                    <template x-for="tpl in templates.slice(0, 5)" :key="tpl.id">
                        <button type="button" :disabled="!editable" @click="applyTemplate(tpl.id)"
                                class="rounded-full border border-slate-300 bg-white px-2.5 py-0.5 text-xs hover:border-emerald-500 hover:text-emerald-700 disabled:opacity-50">
                            <span x-text="tpl.name"></span>
                        </button>
                    </template>
                    <template x-if="templates.length > 5">
                        <details class="relative">
                            <summary class="cursor-pointer rounded-full border border-slate-300 bg-white px-2.5 py-0.5 text-xs hover:border-emerald-500">More…</summary>
                            <div class="absolute z-10 mt-1 w-56 max-h-64 overflow-y-auto rounded-lg border bg-white shadow-lg p-1">
                                <template x-for="tpl in templates.slice(5)" :key="tpl.id">
                                    <button type="button" :disabled="!editable" @click="applyTemplate(tpl.id)"
                                            class="block w-full rounded px-2 py-1 text-left text-xs hover:bg-emerald-50 disabled:opacity-50">
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
                            <button type="button" @click="activateSuggestion(sug)" class="font-semibold text-emerald-700 hover:underline">Save as template</button>
                            <button type="button" @click="dismissSuggestion(sug.id)" class="text-slate-500 hover:underline">Dismiss</button>
                        </div>
                    </div>
                </template>

                <div class="mt-2 space-y-2">
                    <template x-for="(line, idx) in prescriptions" :key="idx">
                        <div class="rounded-lg border border-slate-200 bg-white">
                            <!-- Main row -->
                            <div class="grid items-center gap-2 p-2 sm:grid-cols-12">
                                <div class="sm:col-span-4 relative">
                                    <input type="text" :disabled="!editable"
                                           x-model="line.drug_name"
                                           @input.debounce.250ms="searchDrugFor(idx, line.drug_name)"
                                           @focus="line._dropdown = true"
                                           @click.outside="line._dropdown = false"
                                           placeholder="Medicine"
                                           class="w-full rounded border px-2 py-1 text-sm">
                                    <ul x-show="line._dropdown && (line._suggestions || []).length"
                                        class="absolute z-10 mt-1 w-full max-h-44 overflow-y-auto rounded-lg border bg-white shadow-lg">
                                        <template x-for="d in (line._suggestions || [])" :key="d.id">
                                            <li>
                                                <button type="button"
                                                        @click="pickDrugFor(idx, d)"
                                                        class="block w-full px-2 py-1 text-left text-xs hover:bg-emerald-50">
                                                    <span x-text="d.name"></span>
                                                    <span class="text-slate-400" x-show="d.strength" x-text="' ' + d.strength"></span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <select :disabled="!editable || !!line.tapering_steps" x-model="line.frequency_preset"
                                        class="sm:col-span-2 rounded border px-2 py-1 text-xs">
                                    <option value="">Frequency…</option>
                                    <option value="1-0-0">1-0-0</option>
                                    <option value="0-0-1">0-0-1</option>
                                    <option value="1-0-1">1-0-1</option>
                                    <option value="1-1-1">1-1-1</option>
                                    <option value="1-1-1-1">1-1-1-1</option>
                                    <option value="0-1-0">0-1-0</option>
                                    <option value="SOS">SOS</option>
                                </select>
                                <input type="number" min="1" max="90"
                                       :disabled="!editable || !!line.tapering_steps"
                                       x-model="line.duration_days"
                                       placeholder="Days"
                                       class="sm:col-span-2 rounded border px-2 py-1 text-xs">
                                <select :disabled="!editable" x-model="line.food_timing"
                                        class="sm:col-span-2 rounded border px-2 py-1 text-xs">
                                    <option value="any">Any time</option>
                                    <option value="before">Before food</option>
                                    <option value="after">After food</option>
                                    <option value="empty">Empty stomach</option>
                                    <option value="bedtime">At bedtime</option>
                                </select>
                                <div class="sm:col-span-2 flex items-center justify-end gap-2 text-xs">
                                    <button type="button" :disabled="!editable" @click="line._drawer = !line._drawer"
                                            class="rounded border border-slate-300 px-1.5 py-0.5 text-slate-600 hover:border-emerald-500 hover:text-emerald-700 disabled:opacity-50"
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
                                                <li class="flex items-center gap-2">
                                                    <span class="w-6 text-slate-500" x-text="(sIdx + 1) + '.'"></span>
                                                    <span class="text-slate-600">For</span>
                                                    <input type="number" min="1" :disabled="!editable" x-model.number="step.days"
                                                           class="w-16 rounded border px-1.5 py-0.5">
                                                    <span class="text-slate-600">days,</span>
                                                    <select :disabled="!editable" x-model="step.preset"
                                                            class="rounded border px-1.5 py-0.5">
                                                        <option value="1-0-0">1-0-0</option>
                                                        <option value="0-0-1">0-0-1</option>
                                                        <option value="1-0-1">1-0-1</option>
                                                        <option value="1-1-1">1-1-1</option>
                                                        <option value="0-1-0">0-1-0</option>
                                                    </select>
                                                    <select :disabled="!editable" x-model="step.food"
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

                    <button type="button" :disabled="!editable" @click="addRxLine()"
                            class="text-xs font-medium text-emerald-700 hover:underline disabled:opacity-50">
                        + Add medicine
                    </button>
                </div>
            </div>

            <!-- ---- NOTES (always visible) ---- -->
            <div>
                <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes &amp; next visit</label>
                    <button type="button" :disabled="!editable" x-show="voiceSupported"
                            @click="dictateInto('clinical_notes')"
                            :class="listening === 'clinical_notes' ? 'text-rose-600 animate-pulse' : 'text-slate-500'"
                            class="text-xs hover:text-emerald-700 disabled:opacity-50"
                            title="Dictate notes">🎙 <span x-text="listening === 'clinical_notes' ? 'Listening…' : 'Voice'"></span></button>
                </div>
                <textarea x-model="clinical_notes" :disabled="!editable" rows="2"
                          placeholder="Observations, advice, what changed"
                          class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>

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
            <div>
                <div class="flex items-baseline justify-between">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Charges</label>
                    <span class="text-xs text-slate-500">Total: <span class="font-semibold text-slate-800" x-text="'₹' + chargesTotal()"></span></span>
                </div>
                <div class="mt-2 space-y-2">
                    <template x-for="(c, idx) in charges" :key="c._k">
                        <div class="flex items-center gap-2">
                            <input type="text" :disabled="!editable" x-model="c.description" @change="markDirty()"
                                   placeholder="e.g. Consultation, Procedure, Medicines"
                                   class="flex-1 rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <div class="flex items-center rounded border border-slate-300">
                                <span class="px-2 text-sm text-slate-400">₹</span>
                                <input type="number" min="0" step="1" :disabled="!editable" x-model.number="c.amount" @change="markDirty()"
                                       placeholder="0" class="w-24 border-0 px-1 py-1.5 text-sm focus:outline-none focus:ring-0">
                            </div>
                            <button type="button" :disabled="!editable" @click="removeCharge(idx)" class="text-rose-600 hover:underline disabled:opacity-50" title="Remove">×</button>
                        </div>
                    </template>
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <button type="button" :disabled="!editable" @click="addCharge()" class="text-xs font-medium text-emerald-700 hover:underline disabled:opacity-50">+ Add charge</button>
                    <button type="button" :disabled="!editable" @click="saveCharges()" class="text-xs font-medium text-emerald-700 hover:underline disabled:opacity-50">Save charges</button>
                    <span class="text-xs" :class="chargesStatus === 'saved' ? 'text-emerald-600' : 'text-slate-400'" x-text="chargesLabel"></span>
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
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <div class="mt-2 flex items-center gap-2">
                        <input type="search" x-model="icd10_code" :disabled="!editable"
                               @input.debounce.300ms="searchIcd($event.target.value)"
                               placeholder="ICD-10 code (optional)"
                               class="w-40 rounded border border-slate-200 px-2 py-1 text-xs">
                        <ul x-show="icdResults.length" class="ml-2 inline-flex max-h-32 flex-wrap gap-1">
                            <template x-for="c in icdResults" :key="c.code">
                                <li>
                                    <button type="button" @click="icd10_code = c.code; icdResults = []"
                                            class="rounded bg-slate-100 px-2 py-0.5 text-xs hover:bg-emerald-50"
                                            x-text="c.code"></button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </details>

            <?php if ($has('vitals')): ?>
                <details class="rounded-lg border border-slate-200 bg-slate-50/50"
                         @toggle="recordSection('vitals', $event.target.open)">
                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Vitals</summary>
                    <div class="px-4 pb-4 pt-2">
                        <div class="grid gap-3 sm:grid-cols-3">
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

            <?php if ($has('case_specialty') && $caseAvailable): ?>
                <details class="rounded-lg border border-slate-200 bg-slate-50/50"
                         @toggle="recordSection('case_specialty', $event.target.open)">
                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Case taking</summary>
                    <div class="px-4 pb-4 pt-2 space-y-3">
                        <?php require $casePartialPath; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($has('labs') && !empty($hasLab)): ?>
                <details class="rounded-lg border border-slate-200 bg-slate-50/50"
                         @toggle="recordSection('labs', $event.target.open)">
                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Lab orders</summary>
                    <div class="px-4 pb-4 pt-2">
                        <?php require __DIR__ . '/partials/lab.php'; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($has('photos') && !empty($hasPhotos)): ?>
                <details class="rounded-lg border border-slate-200 bg-slate-50/50"
                         @toggle="recordSection('photos', $event.target.open)">
                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Photos</summary>
                    <div class="px-4 pb-4 pt-2">
                        <?php require __DIR__ . '/partials/photos.php'; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($has('diet') && !empty($hasDiet)): ?>
                <details class="rounded-lg border border-slate-200 bg-slate-50/50"
                         @toggle="recordSection('diet', $event.target.open)">
                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Diet plan</summary>
                    <div class="px-4 pb-4 pt-2">
                        <?php require __DIR__ . '/partials/diet.php'; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($has('consent') && !empty($hasConsent)): ?>
                <details class="rounded-lg border border-slate-200 bg-slate-50/50"
                         @toggle="recordSection('consent', $event.target.open)">
                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Consent</summary>
                    <div class="px-4 pb-4 pt-2">
                        <?php require __DIR__ . '/partials/consent.php'; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (!empty($hasDischarge)): ?>
                <details class="rounded-lg border border-slate-200 bg-slate-50/50">
                    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">Discharge summary</summary>
                    <div class="px-4 pb-4 pt-2">
                        <?php require __DIR__ . '/partials/discharge.php'; ?>
                    </div>
                </details>
            <?php endif; ?>

            <!-- ---- Ghost-link strip: reveal hidden sections for this visit ---- -->
            <?php if (!empty($ghostModules)): ?>
                <div class="flex flex-wrap items-center gap-2 border-t border-dashed border-slate-200 pt-3 text-xs text-slate-500">
                    <span>+ Add:</span>
                    <?php foreach ($ghostModules as $g):
                        $label = match ($g) {
                            'vitals' => 'Vitals',
                            'labs' => 'Labs',
                            'photos' => 'Photos',
                            'diet' => 'Diet plan',
                            'consent' => 'Consent',
                            'case_specialty' => 'Case taking',
                            default => ucfirst($g),
                        };
                    ?>
                        <button type="button" @click="revealGhost('<?= $g ?>')"
                                class="rounded-full border border-slate-300 px-2.5 py-1 hover:border-emerald-400 hover:text-emerald-700"
                                x-show="!ghostRevealed.includes('<?= $g ?>')">
                            <?= htmlspecialchars($label) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Ghost sections rendered dynamically (Phase 2 keeps these
                     minimal — Phase 3+ will move per-section UIs into Alpine
                     components shared with the always-visible ones above). -->
                <template x-for="g in ghostRevealed" :key="g">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/30 p-3 text-xs text-slate-600">
                        <span class="font-semibold" x-text="g"></span>
                        — this section was hidden by default for your specialty.
                        Reveal in <a href="/settings?tab=specialty" class="text-emerald-700 underline">clinic settings</a>
                        to always show it.
                    </div>
                </template>
            <?php endif; ?>

            <!-- ---- Save / Complete actions ---- -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                <button type="button" :disabled="!editable" @click="save()"
                        class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50">
                    Save draft
                </button>
                <?php if ($editable): ?>
                    <form method="post" action="/visits/<?= $visitId ?>/complete"
                          onsubmit="return confirm('Complete this visit?')">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Complete visit
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    </div><!-- /left column -->

    <!-- ====== VISIT HISTORY (right column, sticky on desktop) ====== -->
    <aside class="lg:col-span-1">
    <section class="rounded-xl border bg-white shadow-sm lg:sticky lg:top-20">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h2 class="text-base font-semibold text-slate-900">Visit history</h2>
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
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                📅 <?= htmlspecialchars(date('d M Y', strtotime((string) $rv['visited_at']))) ?>
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
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-medium <?= $isPaid ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                        <?= $isPaid ? '✓ Paid' : 'Unpaid' ?>
                                    </span>
                                </span>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <span class="flex gap-3 text-[11px]">
                                <?php if ($inv): ?>
                                    <a href="/billing/<?= (int) $inv['id'] ?>" class="text-emerald-700 hover:underline">Invoice</a>
                                <?php endif; ?>
                                <button type="button" @click="togglePeek(<?= (int) $rv['id'] ?>)" class="font-medium text-emerald-700 hover:underline">
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
                                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-800" x-text="s"></span>
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
                                    <a :href="'/visits/' + peek.id" class="mt-2 inline-block text-xs font-medium text-emerald-700 hover:underline">Open full visit to edit →</a>
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
function visitScreenV2(cfg) {
    // Normalize vitals.extra into an object — same handling as legacy view.
    const vitals = cfg.vitals || {};
    if (!vitals.extra) vitals.extra = {};
    if (typeof vitals.extra_vitals === 'string') vitals.extra = JSON.parse(vitals.extra_vitals || '{}');
    else if (vitals.extra_vitals) vitals.extra = vitals.extra_vitals;

    // Give each pre-loaded charge a stable key for x-for reactivity.
    const charges = (Array.isArray(cfg.charges) ? cfg.charges : []).map((c, i) => ({
        _k: 'c0' + i, description: c.description || '', amount: c.amount ?? null,
    }));

    return {
        ...cfg,
        vitals,
        charges,
        saveStatus: 'idle',
        saveLabel: 'Auto-save on',
        icdResults: [],
        vitalsWarnings: [],
        lastVisitNote: '',
        autosaveTimer: null,
        dirty: false,   // becomes true only when the doctor actually edits
        peek: null,         // loaded summary of the currently-expanded past visit
        peekId: null,       // which history row is expanded (accordion)
        peekLoading: false,
        chargesStatus: 'idle',
        chargesLabel: '',

        // Call on any user edit so autosave knows there's something to save.
        markDirty() { this.dirty = true; },

        // ---- Charges (visit invoice line items) ----
        _chargeKey: 0,
        addCharge() {
            this.dirty = true;
            this.charges.push({ _k: 'c' + (++this._chargeKey), description: '', amount: null });
        },
        removeCharge(idx) { this.dirty = true; this.charges.splice(idx, 1); },
        chargesTotal() {
            return (this.charges || []).reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);
        },
        async saveCharges() {
            if (!this.editable) return;
            this.chargesStatus = 'saving';
            this.chargesLabel = 'Saving…';
            try {
                // Strip the UI-only _k key before sending.
                const items = this.charges.map(c => ({ description: c.description, amount: c.amount }));
                const r = await fetch('/api/v1/visits/' + this.visitId + '/charges', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ items: items }),
                });
                const data = await r.json();
                if (data.ok) {
                    this.chargesStatus = 'saved';
                    this.chargesLabel = 'Saved · ₹' + (data.total || 0);
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

        initAutosave() {
            if (!this.editable) return;
            this.autosaveTimer = setInterval(() => this.save(), 30000);
            // Save when the tab loses focus too — captures last edit. Guarded by
            // `dirty` so merely viewing a visit never re-saves it.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') this.save();
            });
            // Mark dirty on any input/change within the visit form.
            this.$nextTick(() => {
                const root = this.$root || document;
                root.addEventListener && root.addEventListener('input', () => this.markDirty());
                root.addEventListener && root.addEventListener('change', () => this.markDirty());
            });
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
            rec.onend = () => { this.listening = null; this._recognition = null; this.save(); };
            try { rec.start(); } catch (e) { this.listening = null; }
        },

        payload() {
            // Strip UI-only flags from each rx line before serializing.
            const cleanRx = (this.prescriptions || []).map(p => ({
                drug_id: p.drug_id || null,
                remedy_id: p.remedy_id || null,
                drug_name: p.drug_name || '',
                potency: p.potency || null,
                dose_unit: p.dose_unit || null,
                dose_amount: p.dose_amount || null,
                frequency_preset: p.frequency_preset || null,
                frequency: p.frequency || null,
                duration_days: p.duration_days || null,
                food_timing: p.food_timing || 'any',
                mix_with: p.mix_with || null,
                tapering_steps: Array.isArray(p.tapering_steps) && p.tapering_steps.length ? p.tapering_steps : null,
                instructions: p.instructions || null,
            }));

            return {
                chief_complaint: this.chief_complaint,
                history: this.history,
                examination: this.examination,
                diagnosis: this.diagnosis,
                icd10_code: this.icd10_code,
                clinical_notes: this.clinical_notes,
                condition_score: this.condition_score,
                follow_up_date: this.follow_up_date,
                follow_up_reason: this.follow_up_reason,
                follow_up_notes: this.follow_up_notes,
                visited_at: this.visited_at,
                vitals: this.vitals,
                prescriptions: cleanRx,
                specialty_data: { case_taking: this.case_taking, ...this.specialty_data },
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

        async save() {
            // Never autosave a read-only visit, and never re-save one the
            // doctor only viewed (no edits) — prevents touching old visits.
            if (!this.editable || !this.dirty) return;
            this.saveStatus = 'saving';
            this.saveLabel = 'Saving…';
            try {
                const r = await fetch('/api/v1/visits/' + this.visitId + '/autosave', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.payload()),
                });
                const data = await r.json();
                if (data.ok) {
                    this.saveStatus = 'saved';
                    this.saveLabel = 'Saved ' + new Date().toLocaleTimeString();
                    this.vitalsWarnings = data.warnings || [];
                } else throw new Error(data.error || 'Save failed');
            } catch (e) {
                this.saveStatus = 'error';
                this.saveLabel = e.message;
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

        async searchDrug(idx, q) {
            if (!q || q.length < 2) return;
            const url = this.useHomeo ? '/api/v1/remedies/search?q=' : '/api/v1/drugs/search?q=';
            try {
                const r = await fetch(url + encodeURIComponent(q), { credentials: 'same-origin' });
                const data = await r.json();
                const list = data.remedies || data.drugs || [];
                if (list[0]) {
                    const item = list[0];
                    this.prescriptions[idx].drug_id = this.useHomeo ? null : item.id;
                    this.prescriptions[idx].remedy_id = this.useHomeo ? item.id : null;
                    this.prescriptions[idx].drug_name = item.name;
                }
            } catch (e) { /* ignore */ }
        },

        addRxLine() {
            this.dirty = true;
            this.prescriptions.push({
                drug_id: null, remedy_id: null, drug_name: '',
                potency: '', dosage: '',
                frequency_preset: '', frequency: 'BD',
                duration_days: '', food_timing: 'any', instructions: '',
            });
        },

        removeRxLine(idx) {
            this.dirty = true;
            this.prescriptions.splice(idx, 1);
        },

        setFollowUp(days) {
            const d = new Date();
            d.setDate(d.getDate() + days);
            this.follow_up_date = d.toISOString().slice(0, 10);
        },

        async cloneLastVisit() {
            if (!this.editable) return;
            // Confirm overwrite if doctor already filled stuff.
            const hasData = this.diagnosis || this.chief_complaint || (this.prescriptions || []).some(p => p.drug_name);
            if (hasData && !confirm('Overwrite current form with last visit? Existing entries will be replaced.')) return;
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
                    alert(data.error || 'No previous visit found.');
                }
            } catch (e) {
                alert('Network error — please try again.');
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
    };
}

// ─────────────────────────────────────────────────────────────
// symptomPicker() — chip-style autocomplete (3-layer search)
// ─────────────────────────────────────────────────────────────
function symptomPicker() {
    return {
        // The parent visitScreenV2 owns the canonical symptoms list; this
        // component reads/writes via $root so reloads + autosave still see it.
        get symptoms() { return this.$root.symptoms = this.$root.symptoms || []; },
        set symptoms(v) { this.$root.symptoms = v; },
        get chief_complaint() { return this.$root.chief_complaint; },
        set chief_complaint(v) { this.$root.chief_complaint = v; },

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
                const data = await r.json();
                this.catSymptoms = data.symptoms || [];
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
        },

        async persistSymptoms() {
            if (!this.$root.editable) return;
            try {
                await fetch('/api/v1/visits/' + this.$root.visitId + '/symptoms', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ symptoms: this.symptoms }),
                });
            } catch (e) { /* autosave will retry on next save */ }
        },
    };
}

// ─────────────────────────────────────────────────────────────
// prescriptionPanel() — templates + auto-discovery + drug autocomplete
// ─────────────────────────────────────────────────────────────
function prescriptionPanel() {
    return {
        templates: [],
        suggestions: [],

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
            if (!this.$root.editable) return;
            const hasItems = (this.$root.prescriptions || []).some(p => p.drug_name);
            if (hasItems && !confirm('Append template medicines to current prescription?')) return;
            try {
                const r = await fetch('/api/v1/prescriptions/templates/' + templateId + '/apply/' + this.$root.visitId, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                });
                const data = await r.json();
                if (data.ok) {
                    // Reload to pick up the newly inserted prescriptions.
                    location.reload();
                } else {
                    alert(data.error || 'Could not apply template.');
                }
            } catch (e) {
                alert('Network error.');
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
                alert('Could not save template.');
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

        async searchDrugFor(idx, q) {
            const line = this.$root.prescriptions[idx];
            if (!line) return;
            const query = (q || '').trim();
            if (query.length < 2) { line._suggestions = []; line._dropdown = false; return; }
            const url = this.$root.useHomeo
                ? '/api/v1/remedies/search?q=' + encodeURIComponent(query)
                : '/api/v1/drugs/search?q=' + encodeURIComponent(query);
            try {
                const r = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                line._suggestions = data.drugs || data.remedies || [];
                line._dropdown = line._suggestions.length > 0;
            } catch (e) {
                line._suggestions = [];
            }
        },

        pickDrugFor(idx, drug) {
            const line = this.$root.prescriptions[idx];
            if (!line) return;
            if (this.$root.useHomeo) {
                line.remedy_id = drug.id;
                line.drug_id = null;
            } else {
                line.drug_id = drug.id;
                line.remedy_id = null;
            }
            line.drug_name = drug.name + (drug.strength ? ' ' + drug.strength : '');
            line._suggestions = [];
            line._dropdown = false;
        },

        addTaperingStep(line) {
            if (!Array.isArray(line.tapering_steps)) line.tapering_steps = [];
            // Seed sensible defaults — last step's frequency, 3 days.
            const last = line.tapering_steps[line.tapering_steps.length - 1];
            line.tapering_steps.push({
                days: 3,
                preset: last ? last.preset : (line.frequency_preset || '1-0-1'),
                food: last ? last.food : (line.food_timing || 'after'),
            });
        },
    };
}
</script>
