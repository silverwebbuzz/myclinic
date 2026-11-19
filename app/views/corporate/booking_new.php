<?php
/**
 * /company/bookings/new?employee={id}[&replace={bookingId}] — book due checks at a network clinic.
 *
 * @var array<string,mixed> $emp
 * @var list<array<string,mixed>> $cases     open checks (+ the moving booking's checks first)
 * @var list<int> $preselect
 * @var list<array<string,mixed>> $clinics   clinic_id, name, address, phone, doctors[], prices[exam_type_id => price]
 * @var array<string,mixed>|null $replace
 * @var string $currency
 * @var \App\Support\Locale\Locale $loc
 */
use App\Services\Corporate\ProgrammeService;

$pageTitle = $replace ? 'Move booking' : 'Book checks';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$today = $loc->today();
$jsCases = array_map(static fn ($c) => ['id' => (int) $c['id'], 'exam' => (int) $c['exam_type_id']], $cases);
$jsClinics = array_map(static fn ($c) => ['id' => $c['clinic_id'], 'doctors' => $c['doctors'], 'prices' => (object) $c['prices']], $clinics);
ob_start();
?>
<a href="/company/employees/<?= (int) $emp['id'] ?>#screening" class="text-[13px] text-tx2 hover:text-tx">← <?= $e($emp['full_name']) ?></a>
<h1 class="mt-2 text-[20px] font-semibold tracking-[-.015em]"><?= $replace ? 'Move booking' : 'Book health checks' ?></h1>
<p class="mt-0.5 text-tx3"><?= $e($emp['full_name']) ?> · <?= $e($emp['employee_no'] ?? '') ?> · <?= $e($emp['job_title'] ?? '') ?><?= !empty($emp['branch_name']) ? ' · ' . $e($emp['branch_name']) : '' ?></p>
<?php if ($replace): ?>
    <p class="mt-3 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-[13px] text-wn">Now booked at <?= $e($replace['clinic_name']) ?> on <?= $e(date('d M Y, h:i A', strtotime((string) $replace['scheduled_at']))) ?>. Pick the new time below; the old appointment is cancelled when you confirm.</p>
<?php endif; ?>

<?php if ($cases === []): ?>
    <section class="mt-4 rounded-[10px] border border-ln bg-sf px-5 py-10 text-center">
        <div class="font-medium">Nothing to book</div>
        <p class="mt-1 text-tx3">All of this person's checks are already booked or done.</p>
    </section>
<?php elseif ($clinics === []): ?>
    <section class="mt-4 rounded-[10px] border border-ln bg-sf px-5 py-10 text-center">
        <div class="font-medium">No clinic serves <?= $e($emp['branch_name'] ?? 'this branch') ?> yet</div>
        <p class="mt-1 text-tx3">Add a clinic to your network first and tell it which branches it serves.</p>
        <?php if (\App\Services\Corporate\CorpAuth::can('providers.manage')): ?><a href="/company/providers" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Clinics</a><?php endif; ?>
    </section>
<?php else: ?>
<form method="post" action="/company/bookings" class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]"
      x-data="booking(<?= $e(json_encode(['cases' => $jsCases, 'clinics' => $jsClinics, 'selected' => $preselect, 'today' => $today])) ?>)">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="employment_id" value="<?= (int) $emp['id'] ?>">
    <input type="hidden" name="replace_id" value="<?= (int) ($replace['id'] ?? 0) ?>">
    <input type="hidden" name="clinic_id" :value="clinic ? clinic.id : ''">
    <input type="hidden" name="slot" :value="slot">

    <section class="min-w-0 rounded-[10px] border border-ln bg-sf p-5">
        <h2 class="font-semibold">1. Checks</h2>
        <ul class="mt-2 space-y-1.5 text-[13px]">
            <?php foreach ($cases as $c): $overdue = (string) $c['due_on'] < $today; ?>
                <li><label class="flex items-start gap-2">
                    <input type="checkbox" name="case_ids[]" value="<?= (int) $c['id'] ?>" x-model.number="selected" class="mt-0.5">
                    <span class="min-w-0"><?= $e($c['exam_name']) ?> <span class="text-tx3">· <?= $e(ProgrammeService::CATEGORIES[$c['category']] ?? $c['category']) ?></span>
                        <span class="block text-[12px] <?= $overdue ? 'text-er' : 'text-tx3' ?>"><?= $overdue ? 'Overdue since ' : 'Due ' ?><?= $e($loc->date((string) $c['due_on'])) ?></span></span>
                </label></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="min-w-0 rounded-[10px] border border-ln bg-sf p-5">
        <h2 class="font-semibold">2. Clinic</h2>
        <div class="mt-2 grid gap-2">
            <?php foreach ($clinics as $i => $c): ?>
                <label class="flex min-w-0 cursor-pointer items-start gap-3 rounded-[9px] p-3 ring-1" :class="ci === <?= $i ?> ? 'bg-acs ring-ac/40' : 'ring-ln hover:bg-sf2'">
                    <input type="radio" name="_clinic" value="<?= $i ?>" x-model.number="ci" @change="pickClinic()" class="mt-1">
                    <span class="min-w-0 flex-1">
                        <span class="font-medium"><?= $e($c['name']) ?></span>
                        <span class="block truncate text-[13px] text-tx3"><?= $e($c['address'] ?? '') ?></span>
                        <?php if ($c['doctors'] === []): ?><span class="block text-[12px] text-wn">No doctor with a verified registration yet: can't book here for now.</span><?php endif; ?>
                    </span>
                    <span class="text-right text-[13px] text-tx2" x-text="priceText(<?= $i ?>)"></span>
                </label>
            <?php endforeach; ?>
        </div>

        <div x-show="clinic && clinic.doctors.length" x-cloak class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="text-[12px] text-tx3">Doctor
                <select name="doctor_id" x-model.number="doctor" @change="load()" class="<?= $input ?> mt-1 text-[13px] text-tx">
                    <template x-for="d in (clinic ? clinic.doctors : [])" :key="d.id"><option :value="d.id" x-text="d.name + (d.qualification ? ' · ' + d.qualification : '')"></option></template>
                </select></label>
            <label class="text-[12px] text-tx3">Date
                <input type="date" x-model="date" :min="today" @change="load()" class="<?= $input ?> mt-1 text-[13px] text-tx"></label>
        </div>
        <div x-show="clinic && clinic.doctors.length" x-cloak class="mt-3">
            <p x-show="loading" class="text-[13px] text-tx3">Looking for free times…</p>
            <p x-show="error" x-text="error" class="text-[13px] text-er"></p>
            <p x-show="!loading && !error && date && slots.length === 0" class="text-[13px] text-tx3">No free time that day. Try another date or doctor.</p>
            <div class="flex flex-wrap gap-1.5">
                <template x-for="s in slots" :key="s.datetime">
                    <button type="button" @click="slot = s.datetime" x-text="s.label"
                            class="rounded-[7px] px-2.5 py-1 text-[13px] ring-1" :class="slot === s.datetime ? 'bg-ac text-white ring-ac' : 'bg-sf ring-ln hover:bg-sf2'"></button>
                </template>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-ln2 pt-4">
            <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90 disabled:opacity-40" :disabled="!slot || selected.length === 0"><?= $replace ? 'Move booking' : 'Book' ?></button>
            <span class="text-[13px] text-tx3" x-show="!slot">Pick the checks, a clinic and a time.</span>
        </div>
        <p class="mt-2 text-[12px] text-tx3">The clinic gets a normal appointment. The employee gets an email if we have their work email. Only the fitness outcome comes back to you.</p>
    </section>
</form>
<script>
function booking(cfg) {
    return {
        cases: cfg.cases, clinics: cfg.clinics, selected: cfg.selected, today: cfg.today,
        ci: null, clinic: null, doctor: null, date: '', slots: [], slot: '', loading: false, error: '',
        currency: <?= json_encode($currency) ?>,
        pickClinic() {
            this.clinic = this.clinics[this.ci] || null;
            this.doctor = this.clinic && this.clinic.doctors.length ? this.clinic.doctors[0].id : null;
            this.slots = []; this.slot = ''; this.error = '';
            if (!this.date) { this.date = this.today; }
            this.load();
        },
        priceText(i) {
            const p = this.clinics[i].prices; let sum = 0, missing = 0;
            this.cases.filter(c => this.selected.includes(c.id)).forEach(c => { if (p[c.exam] !== undefined) { sum += Number(p[c.exam]); } else { missing++; } });
            if (sum === 0) { return missing ? 'No agreed price' : ''; }
            return new Intl.NumberFormat('en-IN', { style: 'currency', currency: this.currency }).format(sum) + (missing ? ' + ' + missing + ' unpriced' : '');
        },
        async load() {
            this.slots = []; this.slot = ''; this.error = '';
            if (!this.clinic || !this.doctor || !this.date) { return; }
            this.loading = true;
            try {
                const q = new URLSearchParams({ clinic: this.clinic.id, doctor: this.doctor, date: this.date });
                const r = await fetch('/company/bookings/slots?' + q.toString(), { headers: { 'Accept': 'application/json' } });
                const j = await r.json();
                if (!j.ok) { this.error = j.error || 'Could not load times.'; return; }
                this.slots = (j.slots || []).map(s => ({ datetime: s.datetime, label: new Date(s.datetime.replace(' ', 'T')).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }) }));
            } catch (e) { this.error = 'Could not load times. Please try again.'; }
            finally { this.loading = false; }
        },
    };
}
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
