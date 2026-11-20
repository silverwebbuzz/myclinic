<?php
/**
 * /company/incidents — work incidents (records, no medical detail) + post-incident checks.
 *
 * @var array{status:string, type:string, branch:int} $f
 * @var list<array<string,mixed>> $rows
 * @var list<array<string,mixed>> $branches
 * @var \App\Support\Locale\Locale $loc
 */
use App\Services\Corporate\IncidentService;

$pageTitle = 'Incidents';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 text-[13px] focus:border-ac focus:outline-none';
$sel = 'rounded-[7px] border border-ln bg-sf px-3 py-1.5 text-[13px]';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$tone = ['injury' => 'bg-erb text-er', 'near_miss' => 'bg-wnb text-wn', 'exposure' => 'bg-wnb text-wn', 'illness_at_work' => 'bg-erb text-er', 'other' => 'bg-ntb text-nt'];
$caseLabel = ['not_scheduled' => 'check due', 'scheduled' => 'check booked', 'completed' => 'check done', 'cancelled' => 'check cancelled', 'waived' => 'check waived'];
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3" x-data>
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Incidents</h1>
        <p class="mt-0.5 text-tx3">Work injuries, near misses and exposures. Write what happened, not medical details: the doctor records those.</p>
    </div>
</div>

<details class="mt-4 rounded-[10px] border border-ln bg-sf" <?= $rows === [] ? 'open' : '' ?>>
    <summary class="cursor-pointer px-5 py-3 font-semibold">Record an incident</summary>
    <form method="post" action="/company/incidents" class="grid gap-3 border-t border-ln2 p-5 sm:grid-cols-4" x-data="{ who: '' }">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <label class="text-[12px] text-tx3">Date<input type="date" name="occurred_on" required max="<?= $e($loc->today()) ?>" value="<?= $e($loc->today()) ?>" class="<?= $input ?> text-tx"></label>
        <label class="text-[12px] text-tx3">Type<select name="type" required class="<?= $input ?> text-tx">
            <?php foreach (IncidentService::TYPES as $k => $l): ?><option value="<?= $e($k) ?>"><?= $e($l) ?></option><?php endforeach; ?></select></label>
        <label class="text-[12px] text-tx3">Employee no. <span class="text-tx3">(if someone was involved)</span><input name="employee_no" x-model="who" maxlength="40" class="<?= $input ?> text-tx"></label>
        <label class="text-[12px] text-tx3">Branch <span class="text-tx3">(if nobody named)</span><select name="branch_id" class="<?= $input ?> text-tx">
            <option value="0">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>"><?= $e($b['name']) ?></option><?php endforeach; ?></select></label>
        <label class="text-[12px] text-tx3 sm:col-span-3">What happened <span class="text-tx3">(no diagnosis or medical details)</span>
            <input name="summary" required minlength="10" maxlength="1000" placeholder="e.g. Slipped on wet floor near batching plant, hurt left ankle" class="<?= $input ?> text-tx"></label>
        <label class="text-[12px] text-tx3">Days lost<input type="number" name="days_lost" min="0" max="999" value="0" class="<?= $input ?> text-tx"></label>
        <label class="flex items-center gap-2 text-[13px] sm:col-span-3" x-show="who !== ''" x-cloak>
            <input type="checkbox" name="needs_check" value="1" checked> Needs a post-incident fitness check before going back to the same work</label>
        <div class="sm:col-span-4"><button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Record incident</button></div>
    </form>
</details>

<div class="mt-4 flex flex-wrap items-center gap-2">
    <form method="get" class="flex flex-wrap gap-2">
        <select name="status" class="<?= $sel ?>" onchange="this.form.submit()"><option value="">Open and closed</option>
            <option value="open" <?= $f['status'] === 'open' ? 'selected' : '' ?>>Open</option><option value="closed" <?= $f['status'] === 'closed' ? 'selected' : '' ?>>Closed</option></select>
        <select name="type" class="<?= $sel ?>" onchange="this.form.submit()"><option value="">All types</option>
            <?php foreach (IncidentService::TYPES as $k => $l): ?><option value="<?= $e($k) ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?></select>
        <select name="branch" class="<?= $sel ?>" onchange="this.form.submit()"><option value="">All branches</option>
            <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === $f['branch'] ? 'selected' : '' ?>><?= $e($b['name']) ?></option><?php endforeach; ?></select>
    </form>
</div>

<section class="mt-3 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if ($rows === []): ?>
        <p class="px-5 py-10 text-center text-tx3">No incidents recorded.</p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($rows as $r): ?>
                <li class="grid gap-x-4 gap-y-1 px-5 py-3 md:grid-cols-[110px_minmax(0,2.4fr)_minmax(0,1.2fr)_auto] md:items-center <?= $r['status'] === 'closed' ? 'opacity-70' : '' ?>">
                    <div class="text-[13px]"><div class="font-medium"><?= $e($loc->date((string) $r['occurred_on'])) ?></div><div class="font-mono text-[11px] text-tx3"><?= $e($r['ref']) ?></div></div>
                    <div class="min-w-0 text-[13px]">
                        <span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $tone[$r['type']] ?? '' ?>"><?= $e(IncidentService::TYPES[$r['type']] ?? $r['type']) ?></span>
                        <?php if ((int) $r['days_lost'] > 0): ?><span class="ml-1 text-tx3"><?= (int) $r['days_lost'] ?> day<?= (int) $r['days_lost'] === 1 ? '' : 's' ?> lost</span><?php endif; ?>
                        <div class="mt-1 text-tx2"><?= $e($r['summary']) ?></div>
                    </div>
                    <div class="min-w-0 text-[13px]">
                        <?php if (!empty($r['full_name'])): ?><a href="/company/employees/<?= (int) $r['employment_id'] ?>#incidents" class="font-medium hover:underline"><?= $e($r['full_name']) ?></a>
                            <div class="text-tx3"><?= $e($r['employee_no']) ?><?= !empty($r['case_status']) ? ' · ' . $e($caseLabel[$r['case_status']] ?? '') : '' ?></div>
                        <?php else: ?><span class="text-tx3">Nobody named</span><?php endif; ?>
                        <div class="text-tx3"><?= $e($r['branch_name'] ?? '') ?></div>
                    </div>
                    <div class="flex gap-3 text-[13px] md:justify-end">
                        <?php foreach ($r['status'] === 'open' ? ['close' => 'Close'] : ['reopen' => 'Reopen'] as $act => $label): ?>
                            <form method="post" action="/company/incidents/<?= (int) $r['id'] ?>/<?= $act ?>"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-ac hover:underline"><?= $label ?></button></form>
                        <?php endforeach; ?>
                        <form method="post" action="/company/incidents/<?= (int) $r['id'] ?>/remove" onsubmit="return confirm('Remove this incident record? A post-incident check that is not booked yet is cancelled.')">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-er hover:underline">Remove</button></form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
