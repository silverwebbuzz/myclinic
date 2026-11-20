<?php
/**
 * /company/risks — risk register: 5×5 matrix + list.
 *
 * @var array{status:string, branch:int, band:string} $f
 * @var list<array<string,mixed>> $entries
 * @var array<int, array<int, int>> $matrix
 * @var list<array<string,mixed>> $branches
 * @var bool $canEdit
 * @var bool $canExport
 * @var \App\Support\Locale\Locale $loc
 */
use App\Services\Corporate\RiskService;

$pageTitle = 'Risk register';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sel = 'rounded-[7px] border border-ln bg-sf px-3 py-1.5 text-[13px]';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$cellTone = static function (int $score): string {
    return match (true) { $score <= 4 => 'bg-okb', $score <= 9 => 'bg-wnb', $score <= 16 => 'bg-erb', default => 'bg-er/80 text-white' };
};
$qs = http_build_query(array_filter(['status' => $f['status'], 'branch' => $f['branch'] ?: null]));
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Risk register</h1>
        <p class="mt-0.5 text-tx3">Workplace hazards by site and job, how serious they are, what controls them, and whether the people exposed are up to date with their health checks.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ($canExport): ?>
            <a href="/company/risks/export?format=xlsx&<?= $e($qs) ?>" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Excel</a>
            <a href="/company/risks/export?format=pdf&<?= $e($qs) ?>" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">PDF</a>
        <?php endif; ?>
        <?php if ($canEdit): ?><a href="/company/risks/new" class="<?= $btn ?> bg-ac text-white hover:opacity-90">Add a risk</a><?php endif; ?>
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2.2fr)]">
    <section class="rounded-[10px] border border-ln bg-sf p-4">
        <h2 class="text-[13px] font-semibold">Risk matrix <span class="font-normal text-tx3">(open and controlled)</span></h2>
        <div class="mx-auto mt-3 flex max-w-[300px] gap-1.5">
            <div class="flex shrink-0 items-center"><span class="rotate-180 whitespace-nowrap text-[11px] text-tx3 [writing-mode:vertical-rl]">Likelihood →</span></div>
            <div class="min-w-0 flex-1">
                <div class="grid grid-cols-5 gap-1">
                    <?php for ($l = 5; $l >= 1; $l--): for ($s = 1; $s <= 5; $s++): $n = $matrix[$l][$s] ?? 0; ?>
                        <div class="flex aspect-square items-center justify-center rounded-[5px] text-[13px] font-semibold <?= $cellTone($l * $s) ?> <?= $n === 0 ? 'opacity-40' : '' ?>" title="Likelihood <?= $l ?> × severity <?= $s ?>"><?= $n ?: '' ?></div>
                    <?php endfor; endfor; ?>
                </div>
                <div class="mt-1 text-center text-[11px] text-tx3">Severity →</div>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
            <?php foreach (['low' => [2, 'Low 1–4'], 'medium' => [6, 'Medium 5–9'], 'high' => [12, 'High 10–16'], 'very_high' => [20, 'Very high 20–25']] as $k => [$sc, $label]): ?>
                <a href="/company/risks?<?= $e(http_build_query(array_filter(['status' => $f['status'], 'branch' => $f['branch'] ?: null, 'band' => $f['band'] === $k ? null : $k]))) ?>"
                   class="rounded-full px-2 py-0.5 <?= RiskService::band($sc)[1] ?> <?= $f['band'] === $k ? 'ring-2 ring-ac' : '' ?>"><?= $e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="min-w-0">
        <form method="get" class="flex flex-wrap gap-2">
            <select name="status" class="<?= $sel ?>" onchange="this.form.submit()">
                <option value="">Open and controlled</option>
                <?php foreach (RiskService::STATUSES as $k => $l): ?><option value="<?= $e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $e($l) ?> only</option><?php endforeach; ?>
            </select>
            <select name="branch" class="<?= $sel ?>" onchange="this.form.submit()">
                <option value="">All branches</option>
                <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === $f['branch'] ? 'selected' : '' ?>><?= $e($b['name']) ?></option><?php endforeach; ?>
            </select>
            <?php if ($f['band'] !== ''): ?><input type="hidden" name="band" value="<?= $e($f['band']) ?>"><?php endif; ?>
        </form>
        <div class="mt-3 overflow-hidden rounded-[10px] border border-ln bg-sf">
            <?php if ($entries === []): ?>
                <p class="px-5 py-10 text-center text-tx3"><?= $canEdit ? 'No risks recorded yet. Add the main hazards of each site and job.' : 'No risks recorded.' ?></p>
            <?php else: ?>
                <ul class="divide-y divide-ln2">
                    <?php foreach ($entries as $r): [$bl, $bc] = RiskService::band($r['score']); [$rbl, $rbc] = RiskService::band($r['residual_score']); ?>
                        <li class="grid gap-x-4 gap-y-1 px-4 py-3 text-[13px] md:grid-cols-[minmax(0,2fr)_auto_minmax(0,1.1fr)] md:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if ($canEdit): ?><a href="/company/risks/<?= (int) $r['id'] ?>" class="font-medium hover:underline"><?= $e($r['hazard']) ?></a><?php else: ?><span class="font-medium"><?= $e($r['hazard']) ?></span><?php endif; ?>
                                    <span class="font-mono text-[11px] text-tx3"><?= $e($r['ref']) ?></span>
                                    <?php if ($r['status'] !== 'open'): ?><span class="text-[11px] text-tx3"><?= $e(RiskService::STATUSES[$r['status']]) ?></span><?php endif; ?>
                                </div>
                                <div class="truncate text-tx2"><?= $e($r['activity']) ?></div>
                                <div class="text-[12px] text-tx3"><?= $e($r['branch_name'] ?? 'All branches') ?><?= !empty($r['job_title']) ? ' · ' . $e($r['job_title']) : '' ?><?= !empty($r['owner_name']) ? ' · owner ' . $e($r['owner_name']) : '' ?></div>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $bc ?>" title="Likelihood <?= (int) $r['likelihood'] ?> × severity <?= (int) $r['severity'] ?>"><?= $e($bl) ?> <?= (int) $r['score'] ?></span>
                                <?php if ($r['residual_score'] > 0): ?><span class="text-tx3">→</span><span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $rbc ?>" title="After controls"><?= $e($rbl) ?> <?= (int) $r['residual_score'] ?></span><?php endif; ?>
                            </div>
                            <div class="text-[12px] md:text-right">
                                <?php if ($r['coverage']['people'] > 0): ?>
                                    <div><?= (int) $r['coverage']['people'] ?> people<?php if ($r['coverage']['overdue'] > 0): ?> · <span class="text-er"><?= (int) $r['coverage']['overdue'] ?> overdue a check</span><?php else: ?> · <span class="text-ok">checks up to date</span><?php endif; ?></div>
                                <?php endif; ?>
                                <?php if (!empty($r['review_on'])): ?><div class="<?= $r['review_overdue'] ? 'font-medium text-er' : 'text-tx3' ?>">Review <?= $r['review_overdue'] ? 'overdue since' : 'by' ?> <?= $e($loc->date((string) $r['review_on'])) ?></div><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
