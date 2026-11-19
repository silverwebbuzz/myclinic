<?php
/**
 * /company/screening — due checks.
 *
 * @var array<string,string> $filters
 * @var string $today
 * @var array<string,int|null> $counts
 * @var array{rows: list<array<string,mixed>>, total: int, page: int, pages: int} $result
 * @var list<array<string,mixed>> $programmes
 * @var list<array<string,mixed>> $branches
 * @var list<array<string,mixed>> $jobRoles
 * @var list<array<string,mixed>> $examTypes
 * @var bool $canManage
 * @var bool $canExport
 * @var bool $scoped
 */
use App\Services\Corporate\ScreeningCaseService as S;

$pageTitle = 'Screening';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'w-full rounded-[7px] border border-ln bg-sf px-2.5 py-1.5 text-[13px] focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$qs = static function (array $over) use ($filters): string {
    $q = array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== null);

    return $q === [] ? '' : '?' . http_build_query($q);
};
$here = '/company/screening' . $qs([]);
$days = static fn (string $from, string $to): int => (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->format('%r%a');
$opt = static fn (string $v, string $l, string $cur): string => '<option value="' . htmlspecialchars($v, ENT_QUOTES) . '"' . ($v === $cur ? ' selected' : '') . '>' . htmlspecialchars($l, ENT_QUOTES) . '</option>';
$bucket = $filters['bucket'];
$closed = in_array($bucket, ['done', 'waived'], true);
$bucketCount = ['overdue' => $counts['overdue'], 'soon' => $counts['soon'], 'later' => $counts['later'], 'done' => $counts['done_12m'], 'waived' => $counts['waived']];
$hasFilter = array_filter(array_diff_key($filters, ['bucket' => 1])) !== [];
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Screening</h1>
        <p class="mt-0.5 text-tx3">Health checks due for your people<?= $scoped ? ' in your branches' : '' ?>. Dates only: results stay with the doctor.</p>
    </div>
    <nav class="flex gap-1 rounded-[9px] bg-sf2 p-1 ring-1 ring-ln">
        <a href="/company/screening" class="rounded-[7px] bg-sf px-3 py-1 text-[13px] font-medium shadow-sm">Due checks</a>
        <a href="/company/screening/programmes" class="rounded-[7px] px-3 py-1 text-[13px] text-tx2 hover:text-tx">Programmes</a>
    </nav>
</div>

<div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <a href="/company/screening?bucket=overdue" class="rounded-[10px] border border-ln bg-sf px-4 py-3 hover:bg-sf2"><div class="text-[12px] text-tx3">Overdue</div><div class="mt-0.5 text-[22px] font-semibold <?= $counts['overdue'] > 0 ? 'text-er' : 'text-tx2' ?>"><?= number_format((int) $counts['overdue']) ?></div></a>
    <a href="/company/screening?bucket=soon" class="rounded-[10px] border border-ln bg-sf px-4 py-3 hover:bg-sf2"><div class="text-[12px] text-tx3">Due in 30 days</div><div class="mt-0.5 text-[22px] font-semibold text-wn"><?= number_format((int) $counts['soon']) ?></div></a>
    <a href="/company/screening?bucket=done" class="rounded-[10px] border border-ln bg-sf px-4 py-3 hover:bg-sf2"><div class="text-[12px] text-tx3">Done in last 12 months</div><div class="mt-0.5 text-[22px] font-semibold text-ok"><?= number_format((int) $counts['done_12m']) ?></div></a>
    <div class="rounded-[10px] border border-ln bg-sf px-4 py-3"><div class="text-[12px] text-tx3">People up to date</div>
        <div class="mt-0.5 text-[22px] font-semibold"><?= $counts['up_to_date_pct'] === null ? '—' : (int) $counts['up_to_date_pct'] . '%' ?></div>
        <div class="text-[12px] text-tx3"><?= number_format((int) $counts['people_ok']) ?> of <?= number_format((int) $counts['people']) ?> with no overdue check</div></div>
</div>

<?php if ($programmes === []): ?>
    <div class="mt-4 rounded-[10px] border border-ln bg-sf px-5 py-10 text-center">
        <div class="font-medium">No screening programmes yet</div>
        <p class="mt-1 text-tx3">Adopt an eClinicPro template, apply it to job roles, and due dates appear here automatically.</p>
        <?php if (\App\Services\Corporate\CorpAuth::can('rules.customise')): ?><a href="/company/screening/library" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Browse templates</a><?php endif; ?>
    </div>
<?php else: ?>
    <div class="mt-4 flex flex-wrap gap-1 border-b border-ln">
        <?php foreach (S::BUCKETS as $k => $l): ?>
            <a href="/company/screening<?= $e($qs(['bucket' => $k, 'page' => ''])) ?>" class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-[13px] <?= $k === $bucket ? 'border-ac font-semibold text-tx' : 'border-transparent text-tx2 hover:text-tx' ?>">
                <?= $e($l) ?> <span class="ml-0.5 rounded-full bg-sf2 px-1.5 text-[11px] text-tx3 ring-1 ring-ln"><?= number_format((int) $bucketCount[$k]) ?></span></a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="/company/screening" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        <input type="hidden" name="bucket" value="<?= $e($bucket) ?>">
        <input type="search" name="q" value="<?= $e($filters['q']) ?>" placeholder="Name or employee no." class="<?= $input ?> col-span-2 sm:col-span-3 lg:col-span-1" aria-label="Search">
        <select name="programme" class="<?= $input ?>" aria-label="Programme"><option value="">All programmes</option><?php foreach ($programmes as $p): ?><?= $opt((string) $p['id'], (string) $p['name'], $filters['programme']) ?><?php endforeach; ?></select>
        <select name="exam" class="<?= $input ?>" aria-label="Check"><option value="">All checks</option><?php foreach ($examTypes as $x): ?><?= $opt((string) $x['id'], (string) $x['name'], $filters['exam']) ?><?php endforeach; ?></select>
        <select name="branch" class="<?= $input ?>" aria-label="Branch"><option value="">All branches</option><?php foreach ($branches as $b): ?><?= $opt((string) $b['id'], (string) $b['name'], $filters['branch']) ?><?php endforeach; ?></select>
        <select name="job_role" class="<?= $input ?>" aria-label="Job role"><option value="">All job roles</option><?php foreach ($jobRoles as $j): ?><?= $opt((string) $j['id'], (string) $j['title'], $filters['job_role']) ?><?php endforeach; ?></select>
        <div class="flex items-center gap-2"><button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Filter</button><?php if ($hasFilter): ?><a href="/company/screening?bucket=<?= $e($bucket) ?>" class="text-[13px] text-tx2 hover:text-tx">Clear</a><?php endif; ?></div>
    </form>

    <section class="mt-3 overflow-hidden rounded-[10px] border border-ln bg-sf">
        <div class="flex items-center justify-between border-b border-ln px-5 py-2 text-[13px] text-tx3">
            <span><?= number_format($result['total']) ?> check<?= $result['total'] === 1 ? '' : 's' ?></span>
            <?php if ($canExport && $result['total'] > 0): ?><a href="/company/screening/export<?= $e($qs(['page' => ''])) ?>" class="text-ac hover:underline">Export CSV</a><?php endif; ?>
        </div>
        <?php if ($result['rows'] === []): ?>
            <p class="px-5 py-10 text-center text-tx3"><?= $bucket === 'overdue' && !$hasFilter ? 'Nothing overdue. 🎉' : 'Nothing here.' ?></p>
        <?php else: ?>
            <ul class="divide-y divide-ln2">
                <?php foreach ($result['rows'] as $r): $id = (int) $r['id']; $d = $days($today, (string) $r['due_on']); ?>
                    <li class="px-5 py-3" x-data="{ mode: '' }">
                        <div class="grid gap-x-4 gap-y-1 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1.6fr)_minmax(0,1fr)_auto] md:items-center">
                            <div class="min-w-0">
                                <a href="/company/employees/<?= (int) $r['employment_id'] ?>#screening" class="truncate font-medium hover:underline"><?= $e($r['full_name']) ?></a>
                                <div class="truncate text-[13px] text-tx3"><span class="font-mono"><?= $e($r['employee_no']) ?></span> · <?= $e(implode(' · ', array_filter([$r['job_title'] ?? '', $r['branch_name'] ?? '']))) ?></div>
                            </div>
                            <div class="min-w-0 text-[13px]">
                                <div class="truncate"><?= $e($r['exam_name']) ?></div>
                                <div class="truncate text-tx3"><?= $e(S::CATEGORIES[$r['category']] ?? $r['category']) ?><?= !empty($r['programme_name']) ? ' · ' . $e($r['programme_name']) : '' ?></div>
                            </div>
                            <div class="text-[13px]">
                                <?php if ($closed): ?>
                                    <?php if ($r['status'] === 'completed'): ?>
                                        <div>Done <?= $e($loc->date($r['completed_on'])) ?></div><div class="text-tx3"><?= $r['completion_source'] === 'elsewhere' ? 'outside eClinicPro' : 'via eClinicPro' ?></div>
                                    <?php else: ?>
                                        <div>Waived · was due <?= $e($loc->date($r['due_on'])) ?></div><div class="truncate text-tx3" title="<?= $e($r['closed_reason']) ?>"><?= $e($r['closed_reason']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="<?= $d < 0 ? 'font-medium text-er' : ($d <= S::SOON_DAYS ? 'text-wn' : '') ?>">Due <?= $e($loc->date($r['due_on'])) ?></div>
                                    <div class="text-tx3"><?= $d < 0 ? (-$d) . ' day' . (-$d === 1 ? '' : 's') . ' overdue' : ($d === 0 ? 'today' : 'in ' . $d . ' day' . ($d === 1 ? '' : 's')) ?><?= $r['status'] === 'scheduled' ? ' · booked' : '' ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2 md:justify-end">
                                <?php if ($canManage && !$closed): ?>
                                    <button type="button" @click="mode = mode === 'done' ? '' : 'done'" class="<?= $btn ?> border border-ln hover:bg-sf2">Done elsewhere</button>
                                    <button type="button" @click="mode = mode === 'waive' ? '' : 'waive'" class="<?= $btn ?> border border-ln text-tx2 hover:bg-sf2">Waive</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($canManage && !$closed): ?>
                            <form x-show="mode === 'done'" x-cloak method="post" action="/company/screening/cases/<?= $id ?>/done" class="mt-3 grid gap-2 rounded-[7px] border border-ln bg-sf2 p-3 sm:grid-cols-[170px_minmax(0,1fr)_auto] sm:items-end">
                                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="<?= $e($here) ?>">
                                <label class="block text-[13px]"><span class="text-tx2">Date done</span><input type="date" name="date" required max="<?= $e($today) ?>" value="<?= $e($today) ?>" class="<?= $input ?> mt-1"></label>
                                <label class="block text-[13px]"><span class="text-tx2">Where / note <span class="text-tx3">(optional, no results)</span></span><input name="note" maxlength="500" class="<?= $input ?> mt-1" placeholder="e.g. City Hospital, certificate on file"></label>
                                <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Save</button>
                            </form>
                            <form x-show="mode === 'waive'" x-cloak method="post" action="/company/screening/cases/<?= $id ?>/waive" class="mt-3 grid gap-2 rounded-[7px] border border-ln bg-sf2 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="<?= $e($here) ?>">
                                <label class="block text-[13px]"><span class="text-tx2">Why is this check not needed this time?</span><input name="reason" required minlength="<?= S::MIN_REASON ?>" maxlength="500" class="<?= $input ?> mt-1" placeholder="e.g. On long-term leave abroad until March"></label>
                                <button class="<?= $btn ?> bg-tx2 text-white hover:opacity-90">Waive</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($result['pages'] > 1): ?>
                <div class="flex items-center justify-between border-t border-ln px-5 py-3 text-[13px]">
                    <span class="text-tx3">Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
                    <div class="flex gap-2">
                        <?php if ($result['page'] > 1): ?><a href="/company/screening<?= $e($qs(['page' => (string) ($result['page'] - 1)])) ?>" class="<?= $btn ?> border border-ln hover:bg-sf2">Previous</a><?php endif; ?>
                        <?php if ($result['page'] < $result['pages']): ?><a href="/company/screening<?= $e($qs(['page' => (string) ($result['page'] + 1)])) ?>" class="<?= $btn ?> border border-ln hover:bg-sf2">Next</a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
