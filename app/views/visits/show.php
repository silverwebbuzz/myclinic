<?php
$sd = $visit['specialty_data'] ?? [];
$case = $sd['case_taking'] ?? [];
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<div class="flex flex-col gap-4 xl:flex-row" x-data="visitEmr(<?= htmlspecialchars(json_encode([
    'visitId' => (int) $visit['id'],
    'editable' => $editable,
    'chief_complaint' => $visit['chief_complaint'] ?? '',
    'history' => $visit['history'] ?? '',
    'examination' => $visit['examination'] ?? '',
    'diagnosis' => $visit['diagnosis'] ?? '',
    'icd10_code' => $visit['icd10_code'] ?? '',
    'clinical_notes' => $visit['clinical_notes'] ?? '',
    'condition_score' => $visit['condition_score'] ?? 5,
    'follow_up_date' => $visit['follow_up_date'] ?? '',
    'follow_up_notes' => $visit['follow_up_notes'] ?? '',
    'vitals' => $vitals,
    'prescriptions' => array_map(static fn ($r) => [
        'drug_id' => $r['drug_id'] ?? null,
        'remedy_id' => $r['remedy_id'] ?? null,
        'drug_name' => $r['drug']['name'] ?? $r['remedy']['name'] ?? '',
        'potency' => $r['potency'] ?? '',
        'dosage' => $r['dosage'] ?? '',
        'frequency' => $r['frequency'] ?? 'BD',
        'duration_days' => $r['duration_days'] ?? '',
        'instructions' => $r['instructions'] ?? '',
    ], $prescriptions),
    'specialty_data' => $sd,
    'case_taking' => $case,
    'useHomeo' => $useHomeo,
    'allergies' => $allergies,
], JSON_THROW_ON_ERROR), ENT_QUOTES) ?>)">

    <div class="min-w-0 flex-1 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl border bg-white p-4">
            <div>
                <h2 class="text-lg font-semibold"><?= htmlspecialchars($patient['name']) ?></h2>
                <p class="text-sm text-slate-500"><?= htmlspecialchars($patient['uhid']) ?> · Visit #<?= (int) $visit['visit_number'] ?></p>
                <?php if ($allergies !== []): ?>
                <p class="mt-1 text-xs text-red-600">Allergies: <?= htmlspecialchars(implode(', ', $allergies)) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded-full px-2 py-0.5 text-xs capitalize"
                      :class="saveStatus === 'saved' ? 'bg-emerald-100 text-emerald-800' : (saveStatus === 'saving' ? 'bg-amber-100' : 'bg-slate-100')"
                      x-text="saveLabel"></span>
                <?php if (!$editable): ?>
                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">Read-only</span>
                <?php if (!empty($visit['rx_pdf_path'])): ?>
                <a href="<?= htmlspecialchars($visit['rx_pdf_path']) ?>" target="_blank" class="text-emerald-600 hover:underline">Rx PDF</a>
                <?php endif; ?>
                <?php if (!empty($canUnlock)): ?>
                <form method="post" action="/visits/<?= (int) $visit['id'] ?>/unlock">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-xs text-amber-700 hover:underline">Unlock for edit</button>
                </form>
                <?php endif; ?>
                <?php else: ?>
                <form method="post" action="/visits/<?= (int) $visit['id'] ?>/complete" onsubmit="return confirm('Complete this visit?')">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Complete visit</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <nav class="flex flex-wrap gap-1 border-b text-xs sm:text-sm">
            <template x-for="t in tabs" :key="t.id">
                <button type="button" @click="activeTab = t.id; loadTab(t.id)"
                        :class="activeTab === t.id ? 'border-b-2 border-emerald-600 text-emerald-800' : 'text-slate-500'"
                        class="px-3 py-2" x-text="t.label"></button>
            </template>
        </nav>

        <!-- Vitals -->
        <section x-show="activeTab === 'vitals'" class="rounded-xl border bg-white p-4 space-y-4">
            <h3 class="font-semibold">Vitals</h3>
            <template x-if="warnings.length">
                <div class="space-y-1">
                    <template x-for="w in warnings" :key="w.message">
                        <p class="rounded bg-amber-50 px-2 py-1 text-xs text-amber-900" x-text="w.message"></p>
                    </template>
                </div>
            </template>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($vitalsFields as $f): ?>
                <label class="text-sm">
                    <span class="text-slate-600"><?= htmlspecialchars($f['label']) ?><?= !empty($f['unit']) ? ' (' . htmlspecialchars($f['unit']) . ')' : '' ?></span>
                    <?php if (($f['type'] ?? '') === 'select'): ?>
                    <select :disabled="!editable" x-model="vitals.<?= htmlspecialchars($f['key']) ?>" class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm">
                        <option value="">—</option>
                        <?php foreach ($f['options'] ?? [] as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php elseif (!empty($f['extra'])): ?>
                    <input type="<?= $f['type'] === 'text' ? 'text' : 'number' ?>" step="any" :disabled="!editable"
                           x-model="vitals.extra.<?= htmlspecialchars(substr($f['key'], 6)) ?>"
                           class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm">
                    <?php else: ?>
                    <input type="number" step="any" :disabled="!editable" x-model="vitals.<?= htmlspecialchars($f['key']) ?>"
                           class="mt-1 w-full rounded-lg border px-2 py-1.5 text-sm">
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <canvas id="vitals-spark" height="80"></canvas>
        </section>

        <!-- History -->
        <section x-show="activeTab === 'history'" class="rounded-xl border bg-white p-4 space-y-3">
            <label class="block text-sm"><span class="font-medium">Chief complaint</span>
                <textarea :disabled="!editable" x-model="chief_complaint" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </label>
            <label class="block text-sm"><span class="font-medium">History</span>
                <textarea :disabled="!editable" x-model="history" rows="5" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </label>
            <label class="block text-sm"><span class="font-medium">Examination</span>
                <textarea :disabled="!editable" x-model="examination" rows="4" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </label>
        </section>

        <!-- Case taking -->
        <section x-show="activeTab === 'case'" class="rounded-xl border bg-white p-4 space-y-3">
            <?php require __DIR__ . '/partials/' . $casePartial . '.php'; ?>
        </section>

        <!-- Diagnosis -->
        <section x-show="activeTab === 'diagnosis'" class="rounded-xl border bg-white p-4 space-y-3">
            <label class="block text-sm"><span class="font-medium">Diagnosis</span>
                <textarea :disabled="!editable" x-model="diagnosis" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </label>
            <div class="text-sm">
                <span class="font-medium">ICD-10</span>
                <input type="search" :disabled="!editable" x-model="icd10_code" @input.debounce.300ms="searchIcd(icd10_code)"
                       placeholder="Search ICD-10…" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                <ul x-show="icdResults.length" class="mt-1 max-h-32 overflow-y-auto rounded border text-xs">
                    <template x-for="c in icdResults" :key="c.code">
                        <li><button type="button" @click="icd10_code = c.code; icdResults = []"
                                    class="w-full px-2 py-1 text-left hover:bg-slate-50" x-text="c.code + ' — ' + c.label"></button></li>
                    </template>
                </ul>
            </div>
            <label class="block text-sm">Condition score: <span x-text="condition_score"></span>/10
                <input type="range" min="1" max="10" :disabled="!editable" x-model="condition_score" class="mt-1 w-full">
            </label>
            <label class="block text-sm">Follow-up date
                <input type="date" :disabled="!editable" x-model="follow_up_date" class="mt-1 rounded-lg border px-3 py-2 text-sm">
            </label>
            <label class="block text-sm">Follow-up notes
                <textarea :disabled="!editable" x-model="follow_up_notes" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </label>
        </section>

        <!-- Prescription -->
        <section x-show="activeTab === 'rx'" class="rounded-xl border bg-white p-4 space-y-3">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold">Prescription</h3>
                <button type="button" @click="addRxLine()" :disabled="!editable" class="text-sm text-emerald-600">+ Add line</button>
            </div>
            <template x-if="rxWarnings.length">
                <div class="rounded-lg bg-amber-50 p-2 text-xs text-amber-900">
                    <template x-for="w in rxWarnings" :key="w"><p x-text="w"></p></template>
                </div>
            </template>
            <template x-for="(line, idx) in prescriptions" :key="idx">
                <div class="grid gap-2 rounded-lg border p-3 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <input type="search" :disabled="!editable" x-model="line.drug_name" placeholder="Search medicine…"
                               @input.debounce.300ms="searchDrug(idx, $event.target.value)" class="w-full rounded border px-2 py-1 text-sm">
                    </div>
                    <template x-if="useHomeo">
                        <input type="text" :disabled="!editable" x-model="line.potency" placeholder="Potency" class="rounded border px-2 py-1 text-sm">
                    </template>
                    <input type="text" :disabled="!editable" x-model="line.dosage" placeholder="Dose" class="rounded border px-2 py-1 text-sm">
                    <select :disabled="!editable" x-model="line.frequency" class="rounded border px-2 py-1 text-sm">
                        <option value="OD">OD</option><option value="BD">BD</option><option value="TDS">TDS</option>
                        <option value="QID">QID</option><option value="SOS">SOS</option><option value="PRN">PRN</option>
                    </select>
                    <input type="number" :disabled="!editable" x-model="line.duration_days" placeholder="Days" class="rounded border px-2 py-1 text-sm">
                    <button type="button" @click="prescriptions.splice(idx,1)" :disabled="!editable" class="text-red-600 text-xs">Remove</button>
                </div>
            </template>
        </section>

        <?php if (!empty($hasConsent)): ?>
        <?php include dirname(__DIR__) . '/visits/partials/consent.php'; ?>
        <?php else: ?>
        <section x-show="activeTab === 'consent'" class="rounded-xl border bg-white p-6 text-sm text-slate-500">
            <p>Consent module is not active for this clinic.</p>
        </section>
        <?php endif; ?>

        <!-- Notes -->
        <section x-show="activeTab === 'notes'" class="rounded-xl border bg-white p-4">
            <label class="block text-sm font-medium">Clinical notes
                <textarea :disabled="!editable" x-model="clinical_notes" rows="8" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </label>
        </section>

        <?php if (!empty($hasDischarge)): ?>
        <?php include dirname(__DIR__) . '/visits/partials/discharge.php'; ?>
        <?php else: ?>
        <section x-show="activeTab === 'discharge'" class="rounded-xl border bg-white p-6 text-sm text-slate-500">
            <p>Discharge module is not active for this clinic.</p>
        </section>
        <?php endif; ?>

        <?php if (!empty($hasLab)): ?>
        <?php include dirname(__DIR__) . '/visits/partials/lab.php'; ?>
        <?php endif; ?>

        <?php if (!empty($hasDiet)): ?>
        <?php include dirname(__DIR__) . '/visits/partials/diet.php'; ?>
        <?php endif; ?>

        <?php if (!empty($hasPhotos)): ?>
        <?php include dirname(__DIR__) . '/visits/partials/photos.php'; ?>
        <?php endif; ?>
    </div>

    <aside class="w-full shrink-0 xl:w-72">
        <div class="rounded-xl border bg-white p-4">
            <h3 class="text-sm font-semibold text-slate-600">Recent visits</h3>
            <ul class="mt-3 space-y-3 text-sm">
                <?php if ($recentVisits === []): ?>
                <li class="text-slate-500">No prior visits</li>
                <?php else: ?>
                <?php foreach ($recentVisits as $rv): ?>
                <li class="border-b pb-2">
                    <p class="font-medium"><?= htmlspecialchars(date('d M Y', strtotime($rv['visited_at']))) ?></p>
                    <p class="text-xs text-slate-500"><?= htmlspecialchars($rv['diagnosis'] ?? $rv['chief_complaint'] ?? '—') ?></p>
                </li>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <a href="/patients/<?= (int) $patient['id'] ?>" class="mt-4 block text-center text-xs text-emerald-600 hover:underline">Patient profile →</a>
        </div>
    </aside>
</div>

<script>
function visitEmr(cfg) {
    const vitals = cfg.vitals || {};
    if (!vitals.extra) vitals.extra = {};
    if (typeof vitals.extra_vitals === 'string') vitals.extra = JSON.parse(vitals.extra_vitals || '{}');
    else if (vitals.extra_vitals) vitals.extra = vitals.extra_vitals;

    return {
        ...cfg,
        vitals,
        activeTab: 'vitals',
        tabs: [
            {id:'vitals',label:'Vitals'},{id:'history',label:'Complaint & History'},{id:'case',label:'Case Taking'},
            {id:'diagnosis',label:'Diagnosis'},{id:'rx',label:'Prescription'},
            <?php if (!empty($hasLab)): ?>{id:'lab',label:'Lab'},<?php endif; ?>
            <?php if (!empty($hasDiet)): ?>{id:'diet',label:'Diet'},<?php endif; ?>
            <?php if (!empty($hasPhotos)): ?>{id:'photos',label:'Photos'},<?php endif; ?>
            {id:'consent',label:'Consent'},{id:'notes',label:'Notes'},{id:'discharge',label:'Discharge'},
        ],
        saveStatus: 'idle',
        saveLabel: 'Auto-save on',
        warnings: <?= json_encode($vitalsWarnings) ?>,
        icdResults: [],
        rxWarnings: [],
        drugSuggestions: [],
        autosaveTimer: null,
        init() {
            if (this.editable) {
                this.autosaveTimer = setInterval(() => this.save(), 30000);
            }
            this.initChart();
        },
        initChart() {
            const series = <?= json_encode($chartSeries) ?>;
            const el = document.getElementById('vitals-spark');
            if (!el || !series.labels.length) return;
            new Chart(el, {
                type: 'line',
                data: {
                    labels: series.labels,
                    datasets: [
                        { label: 'BP sys', data: series.bp_systolic, borderColor: '#0F9B6E', tension: 0.3 },
                        { label: 'Weight', data: series.weight_kg, borderColor: '#3b82f6', tension: 0.3 },
                    ],
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { display: false } } },
            });
        },
        loadTab(id) { /* lazy: content inline */ },
        payload() {
            return {
                chief_complaint: this.chief_complaint,
                history: this.history,
                examination: this.examination,
                diagnosis: this.diagnosis,
                icd10_code: this.icd10_code,
                clinical_notes: this.clinical_notes,
                condition_score: this.condition_score,
                follow_up_date: this.follow_up_date,
                follow_up_notes: this.follow_up_notes,
                vitals: this.vitals,
                prescriptions: this.prescriptions,
                specialty_data: { case_taking: this.case_taking, ...this.specialty_data },
            };
        },
        async save() {
            if (!this.editable) return;
            this.saveStatus = 'saving';
            this.saveLabel = 'Saving…';
            try {
                const r = await fetch('/api/v1/visits/<?= (int) $visit['id'] ?>/autosave', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.payload()),
                });
                const data = await r.json();
                if (data.ok) {
                    this.saveStatus = 'saved';
                    this.saveLabel = 'Saved ' + new Date().toLocaleTimeString();
                    this.warnings = data.warnings || [];
                } else throw new Error(data.error || 'Save failed');
            } catch (e) {
                this.saveStatus = 'error';
                this.saveLabel = e.message;
            }
        },
        async searchIcd(q) {
            if (q.length < 2) { this.icdResults = []; return; }
            const r = await fetch('/api/v1/icd10/search?q=' + encodeURIComponent(q));
            const data = await r.json();
            this.icdResults = data.codes || [];
        },
        async searchDrug(idx, q) {
            if (q.length < 2) return;
            const url = this.useHomeo ? '/api/v1/remedies/search?q=' : '/api/v1/drugs/search?q=';
            const r = await fetch(url + encodeURIComponent(q));
            const data = await r.json();
            const list = data.remedies || data.drugs || [];
            if (list[0]) {
                const item = list[0];
                this.prescriptions[idx].drug_id = item.id;
                this.prescriptions[idx].remedy_id = this.useHomeo ? item.id : null;
                if (!this.useHomeo) this.prescriptions[idx].drug_id = item.id;
                this.prescriptions[idx].drug_name = item.name;
                this.checkRx();
            }
        },
        addRxLine() {
            this.prescriptions.push({ drug_id: null, remedy_id: null, drug_name: '', potency: '', dosage: '', frequency: 'BD', duration_days: '', instructions: '' });
        },
        checkRx() { this.rxWarnings = []; /* server validates on save */ },
    };
}
</script>
