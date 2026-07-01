<?php
/** @var int $patientId */
/** @var bool $editable */
$patientId = (int) ($patientId ?? 0);
$editable = !empty($editable);
?>
<div class="ui-card p-5" x-data="patientImmunizations(<?= (int) $patientId ?>, <?= $editable ? 'true' : 'false' ?>)"
     x-init="load()">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="ui-section-title">Immunization register</h3>
        <span class="text-xs text-slate-500" x-show="saveHint" x-text="saveHint"></span>
    </div>
    <p class="mt-1 text-xs text-slate-500">
        IAP schedule from date of birth. Past doses start as <strong>Unknown</strong> — enter given dates when confirmed.
    </p>

    <div class="mt-4 overflow-x-auto" x-show="items.length">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Vaccine</th>
                    <th class="px-3 py-2">Due age</th>
                    <th class="px-3 py-2">Due date</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Given date</th>
                    <th class="px-3 py-2">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template x-for="row in items" :key="row.id">
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-medium text-slate-800" x-text="row.vaccine_name"></td>
                        <td class="px-3 py-2 text-slate-600" x-text="row.age_label"></td>
                        <td class="px-3 py-2 text-slate-600" x-text="row.due_date"></td>
                        <td class="px-3 py-2">
                            <template x-if="editable">
                                <select class="ui-input text-xs" x-model="row.status" @change="onStatusChange(row)">
                                    <option value="unknown">Unknown</option>
                                    <option value="due">Due</option>
                                    <option value="overdue">Overdue</option>
                                    <option value="given">Given</option>
                                    <option value="not_given">Not Given</option>
                                    <option value="skipped">Skipped</option>
                                </select>
                            </template>
                            <template x-if="!editable">
                                <span class="rounded px-2 py-0.5 text-xs font-medium"
                                      :class="statusClass(row.display_status)"
                                      x-text="statusLabel(row.display_status)"></span>
                            </template>
                        </td>
                        <td class="px-3 py-2">
                            <input type="date" class="ui-input text-xs" :disabled="!editable"
                                   x-model="row.given_date" @change="queueSave(row)">
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" class="ui-input text-xs" :disabled="!editable"
                                   x-model="row.notes" placeholder="Optional" @change="queueSave(row)">
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-sm text-slate-400" x-show="!loading && !items.length">No schedule yet — save the patient with a date of birth on a pediatric clinic.</p>
    <p class="mt-2 text-xs text-rose-600" x-show="error" x-text="error"></p>
</div>

<script>
function patientImmunizations(patientId, editable) {
    return {
        patientId,
        editable,
        items: [],
        loading: true,
        error: '',
        saveHint: '',
        _saveTimer: null,

        async load() {
            this.loading = true;
            this.error = '';
            try {
                const r = await fetch('/api/v1/patients/' + this.patientId + '/immunizations', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                const data = await r.json();
                if (!r.ok) throw new Error(data.error || 'Could not load immunizations');
                this.items = (data.items || []).map(row => ({
                    ...row,
                    status: row.status || row.display_status,
                }));
            } catch (e) {
                this.error = e.message || 'Load failed';
            } finally {
                this.loading = false;
            }
        },

        statusClass(s) {
            return {
                unknown: 'bg-slate-100 text-slate-700',
                due: 'bg-blue-100 text-blue-800',
                overdue: 'bg-amber-100 text-amber-900',
                given: 'bg-emerald-100 text-emerald-800',
                not_given: 'bg-rose-100 text-rose-800',
                skipped: 'bg-slate-200 text-slate-500 line-through',
            }[s] || 'bg-slate-100 text-slate-600';
        },

        statusLabel(s) {
            return ({
                unknown: 'Unknown',
                due: 'Due',
                overdue: 'Overdue',
                given: 'Given',
                not_given: 'Not Given',
                skipped: 'Skipped',
            })[s] || s;
        },

        onStatusChange(row) {
            if (row.status === 'not_given' || row.status === 'skipped') {
                row.given_date = '';
            } else if (row.status === 'given' && !row.given_date) {
                row.given_date = new Date().toISOString().slice(0, 10);
            }
            this.queueSave(row);
        },

        queueSave(row) {
            if (!this.editable) return;
            clearTimeout(this._saveTimer);
            this._saveTimer = setTimeout(() => this.saveRow(row), 500);
        },

        async saveRow(row) {
            this.saveHint = 'Saving…';
            try {
                const r = await fetch('/api/v1/patients/' + this.patientId + '/immunizations', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        items: [{
                            id: row.id,
                            given_date: row.given_date || null,
                            status: row.given_date ? 'given' : row.status,
                            notes: row.notes || '',
                        }],
                    }),
                });
                const data = await r.json();
                if (!r.ok) throw new Error(data.error || 'Save failed');
                this.items = (data.items || this.items).map(row => ({
                    ...row,
                    status: row.status || row.display_status,
                }));
                this.saveHint = 'Saved';
                setTimeout(() => { this.saveHint = ''; }, 2000);
            } catch (e) {
                this.error = e.message || 'Save failed';
                this.saveHint = '';
            }
        },
    };
}
</script>
