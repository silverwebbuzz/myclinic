<?php
/**
 * /company/screening/programmes/{id} — one programme: checks and who it applies to.
 *
 * @var array<string,mixed> $p
 * @var list<array<string,mixed>> $items        active first, then removed
 * @var list<array{id:int,target_type:string,target_id:int,label:string}> $assignments
 * @var int $covered
 * @var list<array<string,mixed>> $examTypes
 * @var list<array<string,mixed>> $branches
 * @var list<array<string,mixed>> $departments
 * @var list<array<string,mixed>> $jobRoles
 * @var bool $canCustomise
 * @var bool $canAssign
 */
use App\Services\Corporate\ProgrammeService;

$pageTitle = (string) $p['name'];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$reqPill = ['mandatory' => 'bg-erb text-er', 'recommended' => 'bg-sf2 text-tx2 ring-1 ring-ln', 'company' => 'bg-acs text-ac'];
$active = array_values(array_filter($items, static fn ($i) => (int) $i['is_removed'] === 0));
$removed = array_values(array_filter($items, static fn ($i) => (int) $i['is_removed'] === 1));
$csrfField = '<input type="hidden" name="_csrf" value="' . $e($csrf) . '">';
ob_start();
?>
<a href="/company/screening/programmes" class="text-[13px] text-tx2 hover:text-tx">&larr; Programmes</a>
<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        <h1 class="flex flex-wrap items-center gap-2 text-[20px] font-semibold tracking-[-.015em]"><?= $e($p['name']) ?>
            <?php if ((int) ($p['is_sample'] ?? 0) === 1): ?><span class="rounded bg-wnb px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-wn">Sample</span><?php endif; ?>
            <?php if ($p['status'] === 'archived'): ?><span class="rounded bg-sf2 px-1.5 py-0.5 text-[11px] font-medium text-tx3 ring-1 ring-ln">Archived</span><?php endif; ?></h1>
        <p class="mt-0.5 text-tx3"><?= (int) $p['template_id'] > 0 ? 'Adopted from the eClinicPro template ' . $e($p['template_code']) . ' v' . (int) $p['template_version'] : 'Your own programme' ?><?= !empty($p['description']) ? ' · ' . $e($p['description']) : '' ?></p>
    </div>
    <?php if ($canCustomise): ?>
        <button type="button" x-data @click="$dispatch('toggle-settings')" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Settings</button>
    <?php endif; ?>
</div>

<?php if ((int) ($p['is_sample'] ?? 0) === 1): ?>
    <div class="mt-4 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-[13px] text-wn">This programme came from a SAMPLE template made for testing. Have the checks reviewed by an occupational health doctor before relying on them.</div>
<?php endif; ?>

<?php if (($update ?? null) !== null): ?>
    <section class="mt-4 rounded-[10px] border border-ac/30 bg-acs p-5">
        <h2 class="text-[15px] font-semibold">Update available: version <?= (int) $update['version'] ?></h2>
        <p class="mt-1 text-[13px] text-tx2">eClinicPro changed this template since you adopted v<?= (int) $p['template_version'] ?>. Nothing changes until you accept. Tick what you want to apply; Mandatory / Recommended labels always follow the new version, and a Mandatory check that is now required more often is tightened to the new frequency.</p>
        <?php
        $examNames = array_column($examTypes, 'name', 'id');
        $desc = static function (?array $i) use ($examNames): string {
            if ($i === null) {
                return '';
            }

            return ($i['frequency_months'] ? 'every ' . (int) $i['frequency_months'] . ' months · ' : '') . ucfirst((string) $i['requirement']);
        };
        ?>
        <form method="post" action="/company/screening/programmes/<?= (int) $p['id'] ?>/update" class="mt-3">
            <?= $csrfField ?>
            <input type="hidden" name="template_id" value="<?= (int) $update['template_id'] ?>">
            <ul class="space-y-2">
                <?php foreach ($update['changes'] as $c): $i = $c['new'] ?? $c['old']; $mine = $c['mine']; $mineActive = $mine !== null && (int) $mine['is_removed'] === 0;
                    $name = ($examNames[(int) $i['exam_type_id']] ?? ($i['exam_name'] ?? 'Check')) . ' · ' . strtolower(ProgrammeService::CATEGORIES[$i['category']] ?? $i['category']);
                    $selectable = match ($c['type']) { 'added' => !$mineActive, 'removed' => $mineActive, default => $mineActive };
                    ?>
                    <li class="flex items-start gap-3 rounded-[7px] bg-sf px-3 py-2 text-[13px] ring-1 ring-ln">
                        <?php if ($canCustomise && $selectable): ?>
                            <input type="checkbox" name="accept[]" value="<?= $e($c['key']) ?>" checked class="mt-0.5 rounded border-ln" aria-label="Apply">
                        <?php else: ?><span class="mt-0.5 inline-block w-[13px]"></span><?php endif; ?>
                        <div class="min-w-0">
                            <?php if ($c['type'] === 'added'): ?>
                                <span class="rounded bg-okb px-1.5 text-[11px] font-semibold text-ok">New</span> <?= $e($name) ?> <span class="text-tx3">· <?= $e($desc($c['new'])) ?></span>
                                <?php if ($mineActive): ?><div class="text-tx3">You already have this check.</div><?php endif; ?>
                            <?php elseif ($c['type'] === 'removed'): ?>
                                <span class="rounded bg-sf2 px-1.5 text-[11px] font-semibold text-tx2 ring-1 ring-ln">Dropped</span> <?= $e($name) ?>
                                <div class="text-tx3"><?= $mineActive ? 'Tick to remove it from your programme; leave unticked to keep it as your own check.' : 'Not in your programme.' ?></div>
                            <?php else: ?>
                                <span class="rounded bg-wnb px-1.5 text-[11px] font-semibold text-wn">Changed</span> <?= $e($name) ?>
                                <span class="text-tx3">· was <?= $e($desc($c['old'])) ?> → now <?= $e($desc($c['new'])) ?></span>
                                <?php if ($mineActive): ?><div class="text-tx3">Tick to use the new frequency (you have <?= $mine['frequency_months'] ? 'every ' . (int) $mine['frequency_months'] . ' months' : 'it' ?>).</div><?php else: ?><div class="text-tx3">Not active in your programme.</div><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canCustomise): ?>
                <button class="<?= $btn ?> mt-3 h-9 bg-ac px-4 text-white hover:opacity-90">Update to version <?= (int) $update['version'] ?></button>
            <?php else: ?>
                <p class="mt-3 text-[13px] text-tx3">Someone who can customise programmes needs to review this.</p>
            <?php endif; ?>
        </form>
    </section>
<?php endif; ?>

<?php if ($canCustomise): ?>
    <section x-data="{ open: false }" @toggle-settings.window="open = !open" x-show="open" x-cloak class="mt-4 rounded-[10px] border border-ln bg-sf p-5">
        <form method="post" action="/company/screening/programmes/<?= (int) $p['id'] ?>/settings" class="grid gap-4 sm:grid-cols-2">
            <?= $csrfField ?>
            <label class="block"><span class="text-tx2">Name</span><input name="name" required maxlength="150" value="<?= $e($p['name']) ?>" class="<?= $input ?>"></label>
            <label class="block"><span class="text-tx2">First round for existing staff (days)</span><input type="number" name="first_round_days" min="0" max="365" value="<?= (int) $p['first_round_days'] ?>" class="<?= $input ?>">
                <span class="mt-1 block text-[12px] text-tx3">People already working when this applies to them get their first periodic check due within this many days, so not everyone is overdue on day one.</span></label>
            <label class="block sm:col-span-2"><span class="text-tx2">Description <span class="text-tx3">(optional)</span></span><input name="description" maxlength="500" value="<?= $e($p['description'] ?? '') ?>" class="<?= $input ?>"></label>
            <label class="block"><span class="text-tx2">Status</span>
                <select name="status" class="<?= $input ?>">
                    <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="archived" <?= $p['status'] === 'archived' ? 'selected' : '' ?>>Archived (stops creating new due checks)</option>
                </select></label>
            <div class="flex items-end gap-2"><button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90">Save</button><button type="button" @click="open = false" class="<?= $btn ?> h-9 border border-ln hover:bg-sf2">Cancel</button></div>
        </form>
    </section>
<?php endif; ?>

<div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
    <section id="items" class="rounded-[10px] border border-ln bg-sf">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-4">
            <h2 class="text-[15px] font-semibold">Checks</h2>
            <div class="flex flex-wrap gap-1.5 text-[11px]">
                <span class="rounded-full px-2 py-0.5 font-medium <?= $reqPill['mandatory'] ?>">Mandatory</span>
                <span class="rounded-full px-2 py-0.5 font-medium <?= $reqPill['recommended'] ?>">Recommended</span>
                <span class="rounded-full px-2 py-0.5 font-medium <?= $reqPill['company'] ?>">Added by you</span>
            </div>
        </div>
        <?php if ($active === []): ?><p class="px-5 py-6 text-tx3">No checks yet.<?= $canCustomise ? ' Add one below.' : '' ?></p><?php endif; ?>
        <?php foreach (ProgrammeService::CATEGORIES as $cat => $catLabel): $rows = array_filter($active, static fn ($i) => $i['category'] === $cat); if ($rows === []) { continue; } ?>
            <div class="mt-3 px-5 text-[12px] font-medium uppercase tracking-wide text-tx3"><?= $e($catLabel) ?></div>
            <ul class="divide-y divide-ln2">
                <?php foreach ($rows as $it): $mand = $it['requirement'] === 'mandatory'; ?>
                    <li class="px-5 py-2.5" x-data="{ removing: false }">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <div class="min-w-0 flex-1 basis-full sm:basis-0">
                                <span class="font-medium"><?= $e($it['exam_name']) ?></span>
                                <span class="ml-1 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium <?= $reqPill[$it['requirement']] ?>"><?= $e($it['requirement'] === 'company' ? 'Added by you' : ProgrammeService::REQUIREMENTS[$it['requirement']]) ?></span>
                            </div>
                            <?php if ($cat === 'periodic'): ?>
                                <?php if ($canCustomise): ?>
                                    <form method="post" action="/company/screening/programmes/<?= (int) $p['id'] ?>/items/<?= (int) $it['id'] ?>/frequency" class="flex items-center gap-1.5 text-[13px]">
                                        <?= $csrfField ?>
                                        <span class="text-tx3">every</span>
                                        <input type="number" name="frequency_months" min="1" max="<?= $mand && $it['template_frequency'] ? (int) $it['template_frequency'] : 120 ?>" value="<?= (int) $it['frequency_months'] ?>" class="w-16 rounded-[7px] border border-ln bg-sf px-2 py-1 text-right" aria-label="Months">
                                        <span class="text-tx3">months</span>
                                        <button class="<?= $btn ?> h-7 border border-ln px-2 hover:bg-sf2">Save</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-[13px] text-tx2">every <?= (int) $it['frequency_months'] ?> months</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($canCustomise): ?>
                                <button type="button" x-show="!removing" @click="removing = true" class="text-[13px] text-tx2 hover:text-er">Remove</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($canCustomise): ?>
                            <form x-show="removing" x-cloak method="post" action="/company/screening/programmes/<?= (int) $p['id'] ?>/items/<?= (int) $it['id'] ?>/remove" class="mt-2 rounded-[7px] border <?= $mand ? 'border-er/30 bg-erb' : 'border-ln bg-sf2' ?> p-3">
                                <?= $csrfField ?>
                                <?php if ($mand): ?>
                                    <p class="text-[13px] text-er">This check is <strong>Mandatory</strong> in the template. Removing it will be recorded and shown as an exception in compliance reports.</p>
                                <?php endif; ?>
                                <label class="mt-2 block text-[13px]"><span class="text-tx2">Reason<?= $mand ? '' : ' (optional)' ?></span>
                                    <input name="reason" <?= $mand ? 'required minlength="' . ProgrammeService::MIN_REASON . '"' : '' ?> maxlength="500" class="<?= $input ?>" placeholder="<?= $mand ? 'e.g. Done by the client\'s own medical team at the project site' : '' ?>"></label>
                                <div class="mt-2 flex gap-2">
                                    <button class="<?= $btn ?> <?= $mand ? 'bg-er' : 'bg-tx2' ?> text-white hover:opacity-90">Remove check</button>
                                    <button type="button" @click="removing = false" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Cancel</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>

        <?php if ($canCustomise): ?>
            <form method="post" action="/company/screening/programmes/<?= (int) $p['id'] ?>/items" class="mt-3 grid gap-3 border-t border-ln2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_160px_120px_auto] sm:items-end" x-data="{ cat: 'periodic' }">
                <?= $csrfField ?>
                <label class="block"><span class="text-tx2">Add a check</span>
                    <select name="exam_type_id" required class="<?= $input ?>">
                        <option value="">Choose…</option>
                        <?php foreach (ProgrammeService::KINDS as $kind => $kindLabel): $list = array_filter($examTypes, static fn ($x) => $x['kind'] === $kind); if ($list === []) { continue; } ?>
                            <optgroup label="<?= $e($kindLabel) ?>">
                                <?php foreach ($list as $x): ?><option value="<?= (int) $x['id'] ?>"><?= $e($x['name']) ?></option><?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select></label>
                <label class="block"><span class="text-tx2">When</span>
                    <select name="category" x-model="cat" class="<?= $input ?>">
                        <?php foreach (ProgrammeService::CATEGORIES as $k => $l): ?><option value="<?= $e($k) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="block" x-show="cat === 'periodic'"><span class="text-tx2">Every (months)</span><input type="number" name="frequency_months" min="1" max="120" value="12" class="<?= $input ?>" :disabled="cat !== 'periodic'"></label>
                <div x-show="cat !== 'periodic'" class="hidden sm:block"></div>
                <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90">Add</button>
            </form>
        <?php endif; ?>

        <?php if ($removed !== []): ?>
            <div class="border-t border-ln2 px-5 py-4">
                <h3 class="text-[13px] font-semibold">Removed checks</h3>
                <ul class="mt-2 space-y-2">
                    <?php foreach ($removed as $it): ?>
                        <li class="flex flex-wrap items-start gap-x-3 gap-y-1 text-[13px]">
                            <div class="min-w-0 flex-1 basis-full sm:basis-0">
                                <span class="<?= $it['requirement'] === 'mandatory' ? 'text-er' : 'text-tx2' ?>"><?= $e($it['exam_name']) ?> · <?= $e(strtolower(ProgrammeService::CATEGORIES[$it['category']])) ?><?= $it['requirement'] === 'mandatory' ? ' · Mandatory (exception)' : '' ?></span>
                                <div class="text-tx3"><?= !empty($it['removed_reason']) ? '“' . $e($it['removed_reason']) . '” · ' : '' ?><?= $e($it['removed_by_name'] ?? '') ?><?= !empty($it['removed_at']) ? ', ' . $e($loc->date(substr((string) $it['removed_at'], 0, 10))) : '' ?></div>
                            </div>
                            <?php if ($canCustomise): ?>
                                <form method="post" action="/company/screening/programmes/<?= (int) $p['id'] ?>/items">
                                    <?= $csrfField ?>
                                    <input type="hidden" name="exam_type_id" value="<?= (int) $it['exam_type_id'] ?>">
                                    <input type="hidden" name="category" value="<?= $e($it['category']) ?>">
                                    <input type="hidden" name="frequency_months" value="<?= (int) ($it['frequency_months'] ?? 0) ?: '' ?>">
                                    <button class="text-ac hover:underline">Restore</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <aside id="who" class="h-fit rounded-[10px] border border-ln bg-sf p-5">
        <h2 class="text-[15px] font-semibold">Applies to</h2>
        <p class="mt-1 text-tx3"><?= number_format($covered) ?> current employee<?= $covered === 1 ? '' : 's' ?>. Someone in two programmes with the same check gets it once, at the more frequent interval.</p>
        <?php if ($assignments === []): ?>
            <p class="mt-3 rounded-[7px] bg-wnb px-3 py-2 text-[13px] text-wn">Not applied to anyone yet.</p>
        <?php else: ?>
            <ul class="mt-3 flex flex-wrap gap-2">
                <?php foreach ($assignments as $a): ?>
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-sf2 py-1 pl-3 pr-1.5 text-[13px] ring-1 ring-ln">
                        <span class="text-tx3"><?= $e(ProgrammeService::TARGETS[$a['target_type']]) ?><?= $a['target_type'] === 'all' ? '' : ':' ?></span><?= $a['target_type'] === 'all' ? '' : ' ' . $e($a['label']) ?>
                        <?php if ($canAssign): ?>
                            <form method="post" action="/company/screening/programmes/<?= (int) $p['id'] ?>/assignments/<?= (int) $a['id'] ?>/remove"><?= $csrfField ?><button class="flex h-5 w-5 items-center justify-center rounded-full text-tx3 hover:bg-erb hover:text-er" aria-label="Remove">×</button></form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canAssign): ?>
            <form method="post" action="/company/screening/programmes/<?= (int) $p['id'] ?>/assignments" class="mt-4 space-y-2 border-t border-ln2 pt-4" x-data="{ type: 'job_role' }">
                <?= $csrfField ?>
                <label class="block"><span class="text-tx2">Add</span>
                    <select name="target_type" x-model="type" class="<?= $input ?>">
                        <option value="job_role">A job role</option>
                        <option value="department">A department</option>
                        <option value="branch">A branch</option>
                        <option value="employee">One employee</option>
                        <option value="all">Everyone in the company</option>
                    </select></label>
                <select name="target_job_role" x-show="type === 'job_role'" class="<?= $input ?>" :disabled="type !== 'job_role'">
                    <?php foreach ($jobRoles as $j): ?><option value="<?= (int) $j['id'] ?>"><?= $e($j['title']) ?> (<?= $e($j['risk_level']) ?> risk)</option><?php endforeach; ?>
                </select>
                <select name="target_department" x-show="type === 'department'" x-cloak class="<?= $input ?>" :disabled="type !== 'department'">
                    <?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>"><?= $e($d['name']) ?></option><?php endforeach; ?>
                </select>
                <select name="target_branch" x-show="type === 'branch'" x-cloak class="<?= $input ?>" :disabled="type !== 'branch'">
                    <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>"><?= $e($b['name']) ?></option><?php endforeach; ?>
                </select>
                <input name="target_employee_no" x-show="type === 'employee'" x-cloak placeholder="Employee no." class="<?= $input ?> font-mono uppercase" :disabled="type !== 'employee'">
                <button class="<?= $btn ?> h-9 w-full bg-ac text-white hover:opacity-90">Apply</button>
            </form>
        <?php endif; ?>
    </aside>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
