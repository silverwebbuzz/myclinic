<?php
/**
 * /company/branches — work locations. A branch can be in another country.
 *
 * @var list<array<string,mixed>> $rows        + employee_count
 * @var list<array<string,mixed>> $countries
 * @var array<string, list<array{id:int,name:string}>> $regions
 * @var array<string,string> $types
 * @var list<string> $timezones
 * @var array<string,mixed>|null $old
 */
$pageTitle = 'Branches';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$countryNames = array_column($countries, 'name', 'code');
$countryJs = [];
foreach ($countries as $c) {
    $countryJs[(string) $c['code']] = ['label' => (string) $c['region_label'], 'tz' => (string) $c['default_timezone'], 'regions' => $regions[(string) $c['code']] ?? []];
}
$blank = ['id' => 0, 'name' => '', 'code' => '', 'type' => 'office', 'country_code' => (string) ($company['country_code'] ?? ''), 'region_id' => '', 'city' => '',
    'address_line1' => '', 'address_line2' => '', 'postal_code' => '', 'timezone' => ''];
$editing = is_array($old ?? null) ? array_merge($blank, $old) : null;
// Plain rows for the Alpine editor (no counts / timestamps).
$rowsJs = array_map(static fn (array $r): array => array_intersect_key($r, $blank), $rows);
ob_start();
?>
<div x-data="{
        countries: <?= $e(json_encode($countryJs, JSON_UNESCAPED_UNICODE)) ?>,
        form: <?= $e(json_encode($editing ?? $blank, JSON_UNESCAPED_UNICODE)) ?>,
        open: <?= $editing !== null ? 'true' : 'false' ?>,
        blank: <?= $e(json_encode($blank)) ?>,
        get c() { return this.countries[this.form.country_code] || { label: 'Region', tz: '', regions: [] }; },
        edit(row) { this.form = Object.assign({}, this.blank, row); this.open = true; this.$nextTick(() => this.$refs.formTop.scrollIntoView({ behavior: 'smooth', block: 'start' })); },
        add() { this.form = Object.assign({}, this.blank); this.open = true; }
     }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-[20px] font-semibold tracking-[-.015em]">Branches</h1>
            <p class="mt-0.5 text-tx3">Offices, sites, yards and other places your people work. A branch can be in a different country; its country decides which screening rules apply there.</p>
        </div>
        <button type="button" @click="add()" class="<?= $btn ?> bg-ac text-white hover:opacity-90">Add branch</button>
    </div>

    <section x-ref="formTop" x-show="open" x-cloak class="mt-4 rounded-[10px] border border-ac/30 bg-sf p-5">
        <h2 class="text-[15px] font-semibold" x-text="form.id ? 'Edit branch' : 'New branch'">New branch</h2>
        <form method="post" action="/company/branches" class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="id" :value="form.id">
            <label class="block"><span class="text-tx2">Name</span><input name="name" required maxlength="150" x-model="form.name" class="<?= $input ?>" placeholder="Jebel Ali Yard"></label>
            <label class="block"><span class="text-tx2">Code <span class="text-tx3">(optional)</span></span><input name="code" maxlength="40" x-model="form.code" class="<?= $input ?> uppercase" placeholder="JAFZ-YARD"></label>
            <label class="block"><span class="text-tx2">Type</span>
                <select name="type" x-model="form.type" class="<?= $input ?>">
                    <?php foreach ($types as $k => $l): ?><option value="<?= $e($k) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                </select></label>
            <label class="block"><span class="text-tx2">Country</span>
                <select name="country_code" required x-model="form.country_code" @change="form.region_id = ''; form.timezone = ''" class="<?= $input ?>">
                    <?php foreach ($countries as $c): ?><option value="<?= $e($c['code']) ?>"><?= $e($c['name']) ?></option><?php endforeach; ?>
                </select></label>
            <label class="block"><span class="text-tx2" x-text="c.label">Region</span>
                <select name="region_id" x-model="form.region_id" class="<?= $input ?>">
                    <option value="">Select…</option>
                    <template x-for="r in c.regions" :key="r.id"><option :value="r.id" x-text="r.name" :selected="r.id == form.region_id"></option></template>
                </select></label>
            <label class="block"><span class="text-tx2">City</span><input name="city" maxlength="100" x-model="form.city" class="<?= $input ?>"></label>
            <label class="block sm:col-span-2"><span class="text-tx2">Address</span><input name="address_line1" maxlength="200" x-model="form.address_line1" class="<?= $input ?>"></label>
            <label class="block"><span class="text-tx2">Postal code</span><input name="postal_code" maxlength="20" x-model="form.postal_code" class="<?= $input ?>"></label>
            <input type="hidden" name="address_line2" :value="form.address_line2">
            <label class="block"><span class="text-tx2">Time zone</span>
                <select name="timezone" x-model="form.timezone" class="<?= $input ?>">
                    <option value="" x-text="'Country default (' + c.tz + ')'">Country default</option>
                    <?php foreach ($timezones as $tz): ?><option value="<?= $e($tz) ?>"><?= $e($tz) ?></option><?php endforeach; ?>
                </select></label>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90">Save branch</button>
                <button type="button" @click="open = false" class="<?= $btn ?> h-9 border border-ln hover:bg-sf2">Cancel</button>
            </div>
        </form>
    </section>

    <section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
        <?php if ($rows === []): ?>
            <div class="px-5 py-10 text-center">
                <div class="font-medium">No branches yet</div>
                <p class="mt-1 text-tx3">Add at least one. Every employee belongs to a branch.</p>
                <button type="button" @click="add()" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Add your first branch</button>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-ln2">
                <?php foreach ($rows as $i => $r): ?>
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 <?= (int) $r['is_active'] === 1 ? '' : 'opacity-60' ?>">
                        <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                            <div class="font-medium"><?= $e($r['name']) ?> <span class="ml-1 rounded bg-sf2 px-1.5 py-0.5 font-mono text-[11px] text-tx3 ring-1 ring-ln"><?= $e($r['code']) ?></span>
                                <?php if ((int) $r['is_active'] !== 1): ?><span class="ml-1 text-[12px] text-tx3">inactive</span><?php endif; ?></div>
                            <div class="text-tx3"><?= $e($types[$r['type']] ?? $r['type']) ?> · <?= $e(trim(($r['city'] ?? '') . ', ' . ($countryNames[$r['country_code']] ?? $r['country_code']), ', ')) ?> · <?= $e($r['timezone']) ?></div>
                        </div>
                        <div class="text-tx2"><?= (int) $r['employee_count'] ?> employee<?= (int) $r['employee_count'] === 1 ? '' : 's' ?></div>
                        <button type="button" @click="edit(<?= $e(json_encode($rowsJs[$i], JSON_UNESCAPED_UNICODE)) ?>)" class="<?= $btn ?> border border-ln hover:bg-sf2">Edit</button>
                        <form method="post" action="/company/branches/<?= (int) $r['id'] ?>/toggle">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                            <button class="<?= $btn ?> border border-ln hover:bg-sf2"><?= (int) $r['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
