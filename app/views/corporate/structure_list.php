<?php
/**
 * /company/departments and /company/job-roles — simple lists with an inline editor.
 *
 * @var string $slug 'departments' | 'job-roles'
 * @var list<array<string,mixed>> $rows  + employee_count
 * @var array<string,string> $risk
 * @var array<string,mixed>|null $old
 */
$isJob = $slug === 'job-roles';
$pageTitle = $isJob ? 'Job roles' : 'Departments';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$blank = $isJob
    ? ['id' => 0, 'title' => '', 'code' => '', 'description' => '', 'risk_level' => 'low', 'isco_code' => '']
    : ['id' => 0, 'name' => '', 'code' => '', 'cost_centre' => '', 'head_name' => ''];
$editing = is_array($old ?? null) ? array_merge($blank, array_intersect_key($old, $blank)) : null;
$riskPill = ['low' => 'bg-okb text-ok', 'medium' => 'bg-wnb text-wn', 'high' => 'bg-erb text-er'];
$noun = $isJob ? 'job role' : 'department';
ob_start();
?>
<div x-data="{ form: <?= $e(json_encode($editing ?? $blank, JSON_UNESCAPED_UNICODE)) ?>, open: <?= $editing !== null ? 'true' : 'false' ?>, blank: <?= $e(json_encode($blank)) ?>,
               edit(row) { this.form = Object.assign({}, this.blank, row); this.open = true; this.$nextTick(() => this.$refs.formTop.scrollIntoView({ behavior: 'smooth', block: 'start' })); },
               add() { this.form = Object.assign({}, this.blank); this.open = true; } }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-[20px] font-semibold tracking-[-.015em]"><?= $e($pageTitle) ?></h1>
            <p class="mt-0.5 text-tx3"><?= $isJob
                ? 'Job titles in your company. Screening programmes are assigned by job role, so keep them accurate. Risk level is a first hint until hazards are added.'
                : 'Teams or cost centres. Used for filtering and reports.' ?></p>
        </div>
        <button type="button" @click="add()" class="<?= $btn ?> bg-ac text-white hover:opacity-90">Add <?= $e($noun) ?></button>
    </div>

    <section x-ref="formTop" x-show="open" x-cloak class="mt-4 rounded-[10px] border border-ac/30 bg-sf p-5">
        <form method="post" action="/company/<?= $e($slug) ?>" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="id" :value="form.id">
            <?php if ($isJob): ?>
                <label class="block sm:col-span-2"><span class="text-tx2">Job title</span><input name="title" required maxlength="150" x-model="form.title" class="<?= $input ?>" placeholder="Welder"></label>
                <label class="block"><span class="text-tx2">Risk level</span>
                    <select name="risk_level" x-model="form.risk_level" class="<?= $input ?>">
                        <?php foreach ($risk as $k => $l): ?><option value="<?= $e($k) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="block"><span class="text-tx2">Code <span class="text-tx3">(optional)</span></span><input name="code" maxlength="40" x-model="form.code" class="<?= $input ?>"></label>
                <label class="block sm:col-span-3"><span class="text-tx2">Description <span class="text-tx3">(optional)</span></span><input name="description" maxlength="500" x-model="form.description" class="<?= $input ?>" placeholder="Main tasks, tools and exposures"></label>
                <label class="block"><span class="text-tx2">ISCO-08 code <span class="text-tx3">(optional)</span></span><input name="isco_code" maxlength="4" inputmode="numeric" x-model="form.isco_code" class="<?= $input ?>" placeholder="7212"></label>
            <?php else: ?>
                <label class="block sm:col-span-2"><span class="text-tx2">Department name</span><input name="name" required maxlength="150" x-model="form.name" class="<?= $input ?>" placeholder="Operations"></label>
                <label class="block"><span class="text-tx2">Code <span class="text-tx3">(optional)</span></span><input name="code" maxlength="40" x-model="form.code" class="<?= $input ?>"></label>
                <label class="block"><span class="text-tx2">Cost centre <span class="text-tx3">(optional)</span></span><input name="cost_centre" maxlength="60" x-model="form.cost_centre" class="<?= $input ?>"></label>
                <label class="block sm:col-span-2"><span class="text-tx2">Head of department <span class="text-tx3">(optional)</span></span><input name="head_name" maxlength="120" x-model="form.head_name" class="<?= $input ?>"></label>
            <?php endif; ?>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90">Save</button>
                <button type="button" @click="open = false" class="<?= $btn ?> h-9 border border-ln hover:bg-sf2">Cancel</button>
            </div>
        </form>
    </section>

    <section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
        <?php if ($rows === []): ?>
            <div class="px-5 py-10 text-center">
                <div class="font-medium">No <?= $e($noun) ?>s yet</div>
                <button type="button" @click="add()" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Add the first one</button>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-ln2">
                <?php foreach ($rows as $r): ?>
                    <?php $plain = array_intersect_key($r, $blank); ?>
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 <?= (int) $r['is_active'] === 1 ? '' : 'opacity-60' ?>">
                        <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                            <div class="font-medium">
                                <?= $e($isJob ? $r['title'] : $r['name']) ?>
                                <?php if ($isJob): ?><span class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-medium <?= $riskPill[$r['risk_level']] ?? '' ?>"><?= $e($risk[$r['risk_level']] ?? '') ?> risk</span><?php endif; ?>
                                <?php if ((int) $r['is_active'] !== 1): ?><span class="ml-1 text-[12px] text-tx3">inactive</span><?php endif; ?>
                            </div>
                            <div class="text-tx3"><?= $e(implode(' · ', array_filter($isJob
                                ? [$r['code'] ?? '', $r['isco_code'] ? 'ISCO ' . $r['isco_code'] : '', $r['description'] ?? '']
                                : [$r['code'] ?? '', $r['cost_centre'] ? 'Cost centre ' . $r['cost_centre'] : '', $r['head_name'] ? 'Head: ' . $r['head_name'] : '']))) ?: '&nbsp;' ?></div>
                        </div>
                        <div class="text-tx2"><?= (int) $r['employee_count'] ?> employee<?= (int) $r['employee_count'] === 1 ? '' : 's' ?></div>
                        <button type="button" @click="edit(<?= $e(json_encode($plain, JSON_UNESCAPED_UNICODE)) ?>)" class="<?= $btn ?> border border-ln hover:bg-sf2">Edit</button>
                        <form method="post" action="/company/<?= $e($slug) ?>/<?= (int) $r['id'] ?>/toggle">
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
