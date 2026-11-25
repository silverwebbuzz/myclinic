<?php
/**
 * /company/bookings/group — book many people into one doctor's free slots (Phase 2C-1).
 *
 * @var array{branch:int, due:string} $f
 * @var list<array<string,mixed>> $people
 * @var list<array<string,mixed>> $clinics  clinic_id, name, address, doctors[], prices[]
 * @var list<array<string,mixed>> $branches
 * @var array{booked: list<array{name:string, at:string}>, skipped: list<array{name:string, why:string}>}|null $result
 * @var \App\Support\Locale\Locale $loc
 */
use App\Services\Corporate\BookingService;

$pageTitle = 'Group booking';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sel = 'rounded-[7px] border border-ln bg-sf px-3 py-1.5 text-[13px]';
$input = 'w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 text-[13px] text-tx focus:border-ac focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$today = $loc->today();
$jsClinics = array_map(static fn ($c) => ['id' => $c['clinic_id'], 'doctors' => $c['doctors']], $clinics);
ob_start();
?>
<a href="/company/bookings" class="text-[13px] text-tx2 hover:text-tx">← Bookings</a>
<h1 class="mt-2 text-[20px] font-semibold tracking-[-.015em]">Group booking</h1>
<p class="mt-0.5 text-tx3">Book several people at once: each gets the next free slot with the doctor you choose, for all their open checks. Up to <?= BookingService::GROUP_MAX ?> people at a time.</p>

<?php if (is_array($result ?? null)): ?>
    <section class="mt-4 rounded-[10px] border border-ln bg-sf p-4 text-[13px]">
        <div class="font-semibold"><?= count($result['booked']) ?> booked<?= $result['skipped'] !== [] ? ', ' . count($result['skipped']) . ' not booked' : '' ?></div>
        <?php if ($result['booked'] !== []): ?>
            <ul class="mt-2 grid gap-x-6 gap-y-0.5 sm:grid-cols-2">
                <?php foreach ($result['booked'] as $b): ?><li><?= $e($b['name']) ?> <span class="text-tx3">· <?= $e(date('d M Y, h:i A', strtotime($b['at']))) ?></span></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($result['skipped'] !== []): ?>
            <div class="mt-3 font-medium text-er">Not booked</div>
            <ul class="mt-1 space-y-0.5"><?php foreach ($result['skipped'] as $s): ?><li><?= $e($s['name']) ?> <span class="text-tx3">· <?= $e($s['why']) ?></span></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <p class="mt-2 text-tx3">Each person got an email if we have their work email. See them all in <a href="/company/bookings" class="text-ac hover:underline">Bookings</a>.</p>
    </section>
<?php endif; ?>

<form method="get" class="mt-4 flex flex-wrap gap-2">
    <select name="branch" class="<?= $sel ?>" onchange="this.form.submit()">
        <option value="">All branches</option>
        <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === $f['branch'] ? 'selected' : '' ?>><?= $e($b['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="due" class="<?= $sel ?>" onchange="this.form.submit()">
        <?php foreach (['overdue' => 'Overdue checks', '30' => 'Due in the next 30 days', 'all' => 'Any open check'] as $k => $l): ?>
            <option value="<?= $e($k) ?>" <?= $f['due'] === $k ? 'selected' : '' ?>><?= $e($l) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($people === []): ?>
    <section class="mt-3 rounded-[10px] border border-ln bg-sf px-5 py-10 text-center text-tx3">Nobody to book with these filters.</section>
<?php else: ?>
<form method="post" action="/company/bookings/group" class="mt-3 grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
      x-data="groupBooking(<?= $e(json_encode(['clinics' => $jsClinics, 'today' => $today, 'max' => BookingService::GROUP_MAX])) ?>)">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="branch" value="<?= (int) $f['branch'] ?>">
    <input type="hidden" name="due" value="<?= $e($f['due']) ?>">
    <input type="hidden" name="clinic_id" :value="clinic ? clinic.id : ''">
    <input type="hidden" name="slot" :value="slot">

    <section class="min-w-0 overflow-hidden rounded-[10px] border border-ln bg-sf">
        <div class="flex items-center justify-between border-b border-ln2 px-4 py-2 text-[13px]">
            <label class="inline-flex items-center gap-2"><input type="checkbox" @change="toggleAll($event.target.checked)"> Select up to <?= BookingService::GROUP_MAX ?></label>
            <span class="text-tx3"><span x-text="count"></span> selected of <?= count($people) ?></span>
        </div>
        <ul class="max-h-[560px] divide-y divide-ln2 overflow-y-auto">
            <?php foreach ($people as $p): $late = (string) $p['earliest_due'] < $today; ?>
                <li><label class="flex cursor-pointer items-start gap-3 px-4 py-2 text-[13px] hover:bg-sf2">
                    <input type="checkbox" name="employment_ids[]" value="<?= (int) $p['id'] ?>" class="person mt-0.5" @change="recount()">
                    <span class="min-w-0 flex-1">
                        <span class="font-medium"><?= $e($p['full_name']) ?></span> <span class="text-tx3"><?= $e($p['employee_no']) ?></span>
                        <span class="block truncate text-tx3"><?= $e(implode(' · ', array_filter([$p['job_title'] ?? '', $p['branch_name'] ?? '']))) ?></span>
                        <span class="block truncate text-[12px] text-tx2"><?= (int) $p['checks'] ?> check<?= (int) $p['checks'] === 1 ? '' : 's' ?>: <?= $e($p['check_names']) ?></span>
                    </span>
                    <span class="whitespace-nowrap text-[12px] <?= $late ? 'text-er' : 'text-tx3' ?>"><?= $late ? 'overdue' : 'due' ?> <?= $e($loc->date((string) $p['earliest_due'])) ?></span>
                </label></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="h-fit rounded-[10px] border border-ln bg-sf p-4">
        <?php if ($clinics === []): ?>
            <p class="text-[13px] text-tx3"><?= $f['branch'] > 0 ? 'No clinic serves this branch yet.' : 'Choose a branch to see the clinics that serve it, or add a clinic that serves all branches.' ?></p>
        <?php else: ?>
            <label class="block text-[12px] text-tx3">Clinic
                <select @change="pick($event.target.value)" class="<?= $input ?> mt-1"><option value="">Choose…</option>
                    <?php foreach ($clinics as $i => $c): ?><option value="<?= $i ?>" <?= $c['doctors'] === [] ? 'disabled' : '' ?>><?= $e($c['name']) ?><?= $c['doctors'] === [] ? ' (no verified doctor yet)' : '' ?></option><?php endforeach; ?>
                </select></label>
            <div x-show="clinic" x-cloak class="mt-3 grid gap-3">
                <label class="text-[12px] text-tx3">Doctor
                    <select name="doctor_id" x-model.number="doctor" @change="load()" class="<?= $input ?> mt-1">
                        <template x-for="d in (clinic ? clinic.doctors : [])" :key="d.id"><option :value="d.id" x-text="d.name"></option></template>
                    </select></label>
                <label class="text-[12px] text-tx3">Date<input type="date" x-model="date" :min="today" @change="load()" class="<?= $input ?> mt-1"></label>
                <div class="text-[12px] text-tx3">Start from <span x-show="slots.length" class="text-tx2">(<span x-text="slots.length"></span> free slots that day)</span></div>
                <p x-show="loading" class="text-[13px] text-tx3">Looking for free times…</p>
                <p x-show="error" x-text="error" class="text-[13px] text-er"></p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="s in slots" :key="s.datetime">
                        <button type="button" @click="slot = s.datetime" x-text="s.label" class="rounded-[7px] px-2 py-1 text-[12px] ring-1" :class="slot === s.datetime ? 'bg-ac text-white ring-ac' : 'bg-sf ring-ln hover:bg-sf2'"></button>
                    </template>
                </div>
                <p x-show="slot && count > slotsFrom()" x-cloak class="text-[12px] text-wn">Only <span x-text="slotsFrom()"></span> free slots from that time: the rest will be listed as not booked.</p>
            </div>
            <button class="<?= $btn ?> mt-4 h-9 w-full bg-ac text-white hover:opacity-90 disabled:opacity-40" :disabled="!slot || count === 0">Book <span x-text="count" class="mx-1"></span> people</button>
            <p class="mt-2 text-[12px] text-tx3">Each person gets their own appointment, one after another, and an email if we have their work email.</p>
        <?php endif; ?>
    </section>
</form>
<script>
function groupBooking(cfg) {
    return {
        clinics: cfg.clinics, today: cfg.today, max: cfg.max,
        clinic: null, doctor: null, date: cfg.today, slots: [], slot: '', loading: false, error: '', count: 0,
        recount() { this.count = document.querySelectorAll('input.person:checked').length; },
        toggleAll(on) { let n = 0; document.querySelectorAll('input.person').forEach(b => { b.checked = on && n < this.max; if (b.checked) { n++; } }); this.recount(); },
        pick(i) { this.clinic = i === '' ? null : this.clinics[i]; this.doctor = this.clinic && this.clinic.doctors.length ? this.clinic.doctors[0].id : null; this.load(); },
        slotsFrom() { return this.slots.filter(s => s.datetime >= this.slot).length; },
        async load() {
            this.slots = []; this.slot = ''; this.error = '';
            if (!this.clinic || !this.doctor || !this.date) { return; }
            this.loading = true;
            try {
                const r = await fetch('/company/bookings/slots?' + new URLSearchParams({ clinic: this.clinic.id, doctor: this.doctor, date: this.date }), { headers: { 'Accept': 'application/json' } });
                const j = await r.json();
                if (!j.ok) { this.error = j.error || 'Could not load times.'; return; }
                this.slots = (j.slots || []).map(s => ({ datetime: s.datetime, label: new Date(s.datetime.replace(' ', 'T')).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }) }));
            } catch (e) { this.error = 'Could not load times.'; } finally { this.loading = false; }
        },
    };
}
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
