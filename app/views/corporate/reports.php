<?php
/**
 * /company/reports — screening compliance by group + fitness outcome mix (suppressed under 5).
 *
 * @var string $by
 * @var array{branch:int, programme:int} $f
 * @var array{rows: list<array<string,mixed>>, total: array<string,mixed>, by: string} $report
 * @var list<array<string,mixed>> $outcomes
 * @var string $outcomesBy
 * @var list<array<string,mixed>> $branches
 * @var list<array<string,mixed>> $programmes
 * @var bool $canExport
 */
use App\Services\Corporate\ReportService;

$pageTitle = 'Reports';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'rounded-[7px] border border-ln bg-sf px-3 py-1.5 text-[13px] focus:border-ac focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$qs = static fn (array $over) => '?' . http_build_query(array_filter(array_merge(['by' => $by, 'branch' => $f['branch'] ?: null, 'programme' => $f['programme'] ?: null], $over), static fn ($v) => $v !== null && $v !== 0 && $v !== ''));
$pct = static function (?int $p): string {
    if ($p === null) {
        return '<span class="text-tx3">—</span>';
    }
    $tone = $p >= 90 ? 'text-ok' : ($p >= 70 ? 'text-wn' : 'text-er');

    return '<span class="font-semibold ' . $tone . '">' . $p . '%</span>';
};
$rowHtml = static function (array $r, bool $total = false) use ($e, $pct): string {
    return '<tr class="' . ($total ? 'bg-sf2 font-semibold' : 'hover:bg-sf2') . '">'
        . '<td class="px-4 py-2">' . $e($r['name']) . '</td>'
        . '<td class="px-3 py-2 text-right tabular-nums">' . number_format((int) $r['people']) . '</td>'
        . '<td class="px-3 py-2 text-right tabular-nums">' . number_format((int) $r['covered']) . '</td>'
        . '<td class="px-3 py-2 text-right">' . $pct($r['up_to_date_pct']) . '</td>'
        . '<td class="px-3 py-2 text-right tabular-nums ' . ((int) $r['overdue'] > 0 ? 'text-er' : 'text-tx3') . '">' . number_format((int) $r['overdue']) . '</td>'
        . '<td class="px-3 py-2 text-right tabular-nums">' . number_format((int) $r['people_overdue']) . '</td>'
        . '<td class="px-3 py-2 text-right tabular-nums">' . number_format((int) $r['soon']) . '</td>'
        . '<td class="px-4 py-2 text-right tabular-nums">' . number_format((int) $r['done_12m']) . '</td></tr>';
};
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Reports</h1>
        <p class="mt-0.5 text-tx3">Screening compliance across your workforce. Administrative data only: no medical details.</p>
    </div>
    <?php if ($canExport || \App\Services\Corporate\CorpAuth::can('billing.view')): ?>
        <div class="flex flex-wrap gap-2">
            <?php if (\App\Services\Corporate\CorpAuth::can('billing.view')): ?><a href="/company/reports/spend" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Spend</a><?php endif; ?>
            <?php if ($canExport): foreach (\App\Services\Corporate\ReportExportService::FORMATS as $fmt => $label): ?>
                <a href="/company/reports/export<?= $e($qs(['format' => $fmt])) ?>" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2"><?= $e($label) ?></a>
            <?php endforeach; endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="mt-4 flex flex-wrap items-center gap-3">
    <nav class="flex flex-wrap gap-1 rounded-[9px] bg-sf2 p-1 ring-1 ring-ln">
        <?php foreach (ReportService::GROUPS as $k => $label): ?>
            <a href="/company/reports<?= $e($qs(['by' => $k])) ?>" class="rounded-[7px] px-3 py-1 text-[13px] <?= $by === $k ? 'bg-sf font-medium shadow-sm' : 'text-tx2 hover:text-tx' ?>">By <?= $e(strtolower($label)) ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" class="flex flex-wrap gap-2">
        <input type="hidden" name="by" value="<?= $e($by) ?>">
        <select name="branch" class="<?= $input ?>" onchange="this.form.submit()">
            <option value="">All branches</option>
            <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === $f['branch'] ? 'selected' : '' ?>><?= $e($b['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="programme" class="<?= $input ?>" onchange="this.form.submit()">
            <option value="">All programmes</option>
            <?php foreach ($programmes as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $f['programme'] ? 'selected' : '' ?>><?= $e($p['name']) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="<?= $btn ?> border border-ln bg-sf">Apply</button></noscript>
    </form>
</div>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-[13px]">
            <thead class="bg-sf2 text-left text-[12px] text-tx3">
                <tr>
                    <th class="px-4 py-2 font-medium"><?= $e(ReportService::GROUPS[$by]) ?></th>
                    <th class="px-3 py-2 text-right font-medium">People</th>
                    <th class="px-3 py-2 text-right font-medium" title="People with at least one screening check">Covered</th>
                    <th class="px-3 py-2 text-right font-medium" title="Covered people with nothing overdue">Up to date</th>
                    <th class="px-3 py-2 text-right font-medium">Checks overdue</th>
                    <th class="px-3 py-2 text-right font-medium">People overdue</th>
                    <th class="px-3 py-2 text-right font-medium">Due in 30 days</th>
                    <th class="px-4 py-2 text-right font-medium">Done in 12 months</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ln2">
                <?php foreach ($report['rows'] as $r): ?><?= $rowHtml($r) ?><?php endforeach; ?>
                <?php if ($report['rows'] === []): ?><tr><td colspan="8" class="px-4 py-8 text-center text-tx3">No data yet.</td></tr><?php endif; ?>
                <?php if ($report['rows'] !== []): ?><?= $rowHtml($report['total'], true) ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<p class="mt-2 text-[12px] text-tx3"><?= $by === 'programme' ? 'People appear under every programme that applies to them; the total counts each person once.' : 'Terminated employees are not counted.' ?></p>

<section class="mt-6 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <div class="px-5 pt-4">
        <h2 class="font-semibold">Fitness outcomes by <?= $e(strtolower(ReportService::GROUPS[$outcomesBy])) ?></h2>
        <p class="text-[13px] text-tx3">Current certificates the employee agreed to share. Groups with fewer than <?= ReportService::MIN_GROUP ?> such people are hidden so no one's result can be worked out.</p>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full min-w-[640px] text-[13px]">
            <thead class="bg-sf2 text-left text-[12px] text-tx3">
                <tr><th class="px-4 py-2 font-medium"><?= $e(ReportService::GROUPS[$outcomesBy]) ?></th><th class="px-3 py-2 text-right font-medium">Fit</th>
                    <th class="px-3 py-2 text-right font-medium">Fit with restrictions</th><th class="px-3 py-2 text-right font-medium">Unfit</th>
                    <th class="px-3 py-2 text-right font-medium">Temporarily unfit / review</th><th class="px-4 py-2 text-right font-medium">Expiring in 30 days</th></tr>
            </thead>
            <tbody class="divide-y divide-ln2">
                <?php foreach ($outcomes as $o): ?>
                    <tr>
                        <td class="px-4 py-2"><?= $e($o['name']) ?></td>
                        <?php if ($o['suppressed']): ?>
                            <td colspan="5" class="px-4 py-2 text-tx3">Fewer than <?= ReportService::MIN_GROUP ?> people: hidden</td>
                        <?php else: ?>
                            <td class="px-3 py-2 text-right tabular-nums text-ok"><?= (int) $o['fit'] ?></td>
                            <td class="px-3 py-2 text-right tabular-nums text-wn"><?= (int) $o['fit_restricted'] ?></td>
                            <td class="px-3 py-2 text-right tabular-nums text-er"><?= (int) $o['unfit'] ?></td>
                            <td class="px-3 py-2 text-right tabular-nums"><?= (int) $o['review'] ?></td>
                            <td class="px-4 py-2 text-right tabular-nums"><?= (int) $o['expiring'] ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($outcomes === []): ?><tr><td colspan="6" class="px-4 py-8 text-center text-tx3">No shared certificates yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<p class="mt-2 text-[12px] text-tx3">Per-person certificates are in <a href="/company/certificates" class="text-ac hover:underline">Certificates</a>.</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
