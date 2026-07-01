<?php
/** @var bool $showImmunizations */
/** @var array{overdue: list, due_today: list, due_soon: list, upcoming: list} $immunizationSummary */
/** @var list<array{visit_id: ?int, visit_number: ?int, visited_at: ?string, label: string, is_current: bool, items: list}> $immunizationsGiven */
/** @var int $visitId */
/** @var int $patientId */
/** @var bool $editable */

$fmtDate = static function (string $iso): string {
    try {
        return (new \DateTimeImmutable($iso))->format('d M Y');
    } catch (\Throwable) {
        return $iso;
    }
};

$groupByDate = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $groups[(string) $row['due_date']][] = $row;
    }
    ksort($groups);

    return $groups;
};
?>
<?php if (!empty($showImmunizations)): ?>
<?php
    $overdue = $immunizationSummary['overdue'] ?? [];
    $dueToday = $immunizationSummary['due_today'] ?? [];
    $dueSoon = $immunizationSummary['due_soon'] ?? [];
    $upcoming = $immunizationSummary['upcoming'] ?? [];
    $givenGroups = $immunizationsGiven ?? [];
    $givenOnVisit = 0;
    foreach ($givenGroups as $g) {
        if (!empty($g['is_current'])) {
            $givenOnVisit = count($g['items'] ?? []);
            break;
        }
    }
    $totalGiven = 0;
    foreach ($givenGroups as $g) {
        $totalGiven += count($g['items'] ?? []);
    }
    $dueSoonGroups = $groupByDate($dueSoon);
    $urgent = $overdue !== [] || $dueToday !== [];
    $hasAny = $urgent || $dueSoon !== [] || $upcoming !== [];
    $pendingCount = count($overdue) + count($dueToday) + count($dueSoon) + count($upcoming);
?>
<details class="rounded-lg border border-slate-200 bg-slate-50/50" <?= $urgent ? 'open' : '' ?>
         x-data="visitImmunizations(<?= (int) $visitId ?>, <?= !empty($editable) ? 'true' : 'false' ?>)">
    <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-slate-700">
        Immunizations
        <?php if ($overdue !== []): ?>
            <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900"><?= count($overdue) ?> overdue</span>
        <?php endif; ?>
        <?php if ($dueToday !== []): ?>
            <span class="ml-1 rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"><?= count($dueToday) ?> due today</span>
        <?php endif; ?>
        <?php if (!$urgent && $pendingCount > 0): ?>
            <span class="ml-1 rounded bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600"><?= $pendingCount ?> pending</span>
        <?php endif; ?>
        <?php if ($givenOnVisit > 0): ?>
            <span class="ml-1 rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"><?= $givenOnVisit ?> given here</span>
        <?php elseif ($totalGiven > 0): ?>
            <span class="ml-1 rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= $totalGiven ?> given</span>
        <?php endif; ?>
    </summary>
    <div class="border-t border-slate-100 px-4 pb-4 pt-3 text-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500">Patient immunization register</p>
            <a href="/patients/<?= (int) $patientId ?>#sec-immunizations"
               class="text-xs font-medium text-brand hover:underline">Full schedule</a>
        </div>

        <?php if ($overdue !== []): ?>
        <div class="mb-3">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-amber-800">Overdue</h4>
            <ul class="mt-1 divide-y divide-amber-100 rounded-md border border-amber-200 bg-amber-50/80">
                <?php foreach ($overdue as $row): ?>
                <li class="flex items-center justify-between gap-3 px-3 py-2">
                    <div class="min-w-0">
                        <span class="font-medium text-slate-800"><?= htmlspecialchars((string) $row['vaccine_name']) ?></span>
                        <span class="block text-xs text-slate-500">Due <?= htmlspecialchars($fmtDate((string) $row['due_date'])) ?></span>
                    </div>
                    <?php if (!empty($editable)): ?>
                    <button type="button" class="shrink-0 rounded border border-brand/30 bg-white px-2 py-1 text-xs font-medium text-brand hover:bg-brand/5"
                            @click="markGiven(<?= (int) $row['id'] ?>)">Given today</button>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($dueToday !== []): ?>
        <div class="mb-3">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Due today</h4>
            <ul class="mt-1 divide-y divide-emerald-100 rounded-md border border-emerald-200 bg-emerald-50/60">
                <?php foreach ($dueToday as $row): ?>
                <li class="flex items-center justify-between gap-3 px-3 py-2">
                    <span class="font-medium text-slate-800"><?= htmlspecialchars((string) $row['vaccine_name']) ?></span>
                    <?php if (!empty($editable)): ?>
                    <button type="button" class="shrink-0 rounded border border-brand/30 bg-white px-2 py-1 text-xs font-medium text-brand hover:bg-brand/5"
                            @click="markGiven(<?= (int) $row['id'] ?>)">Given</button>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($dueSoonGroups !== []): ?>
        <div class="mb-3" x-data="{ expanded: false }">
            <button type="button" class="flex w-full items-center justify-between text-left text-xs font-semibold uppercase tracking-wide text-slate-600"
                    @click="expanded = !expanded">
                <span>Coming up (next 30 days) · <?= count($dueSoon) ?> doses</span>
                <span class="normal-case font-medium text-brand" x-text="expanded ? 'Hide' : 'Show'"></span>
            </button>
            <div class="mt-2 space-y-2" x-show="expanded" x-collapse>
                <?php foreach ($dueSoonGroups as $dueDate => $rows): ?>
                <details class="rounded-md border border-slate-200 bg-white">
                    <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-slate-700">
                        <?= htmlspecialchars($fmtDate($dueDate)) ?>
                        <span class="text-slate-400">· <?= count($rows) ?> vaccine<?= count($rows) === 1 ? '' : 's' ?></span>
                    </summary>
                    <ul class="divide-y border-t border-slate-100">
                        <?php foreach ($rows as $row): ?>
                        <li class="flex items-center justify-between gap-3 px-3 py-1.5 text-xs">
                            <span class="text-slate-700"><?= htmlspecialchars((string) $row['vaccine_name']) ?></span>
                            <?php if (!empty($editable)): ?>
                            <button type="button" class="shrink-0 text-brand hover:underline"
                                    @click="markGiven(<?= (int) $row['id'] ?>)">Given today</button>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($upcoming !== []): ?>
        <div x-data="{ showUpcoming: false }">
            <button type="button" class="text-xs font-medium text-brand hover:underline" @click="showUpcoming = !showUpcoming">
                <span x-text="showUpcoming ? 'Hide later schedule' : 'Later schedule (<?= count($upcoming) ?> doses)'"></span>
            </button>
            <ul class="mt-2 max-h-48 space-y-0.5 overflow-y-auto rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-600"
                x-show="showUpcoming">
                <?php foreach ($upcoming as $row): ?>
                <li><?= htmlspecialchars((string) $row['vaccine_name']) ?> · <?= htmlspecialchars($fmtDate((string) $row['due_date'])) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!$hasAny): ?>
        <p class="text-sm text-slate-400">No pending doses on the register.</p>
        <?php endif; ?>

        <?php if ($givenGroups !== []): ?>
        <div class="mt-4 border-t border-slate-200 pt-3" x-data="{ showGiven: <?= $givenOnVisit > 0 ? 'true' : 'false' ?> }">
            <button type="button" class="flex w-full items-center justify-between text-left text-xs font-semibold uppercase tracking-wide text-slate-600"
                    @click="showGiven = !showGiven">
                <span>Given vaccines · <?= $totalGiven ?> dose<?= $totalGiven === 1 ? '' : 's' ?></span>
                <span class="normal-case font-medium text-brand" x-text="showGiven ? 'Hide' : 'Show'"></span>
            </button>
            <div class="mt-2 space-y-2" x-show="showGiven" x-collapse>
                <?php foreach ($givenGroups as $group): ?>
                <div class="rounded-md border <?= !empty($group['is_current']) ? 'border-emerald-200 bg-emerald-50/50' : 'border-slate-200 bg-white' ?>">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-inherit px-3 py-2">
                        <div class="min-w-0">
                            <?php if (!empty($group['is_current'])): ?>
                            <span class="text-xs font-semibold text-emerald-800">This visit</span>
                            <span class="text-xs text-slate-500"> · </span>
                            <?php endif; ?>
                            <?php if (!empty($group['visit_id']) && empty($group['is_current'])): ?>
                            <a href="/visits/<?= (int) $group['visit_id'] ?>"
                               class="text-xs font-medium text-brand hover:underline">
                                <?= htmlspecialchars((string) $group['label']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-xs font-medium text-slate-700"><?= htmlspecialchars((string) $group['label']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-slate-400"><?= count($group['items'] ?? []) ?> vaccine<?= count($group['items'] ?? []) === 1 ? '' : 's' ?></span>
                    </div>
                    <ul class="divide-y divide-slate-100 px-3 py-1">
                        <?php foreach ($group['items'] as $row): ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 py-1.5 text-xs">
                            <span class="font-medium text-slate-800"><?= htmlspecialchars((string) $row['vaccine_name']) ?></span>
                            <span class="text-slate-500">
                                Given <?= htmlspecialchars($fmtDate((string) ($row['given_date'] ?? ''))) ?>
                                <?php if (!empty($row['notes'])): ?>
                                · <?= htmlspecialchars((string) $row['notes']) ?>
                                <?php endif; ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <p class="mt-2 text-xs text-rose-600" x-show="error" x-text="error"></p>
    </div>
</details>
<script>
if (typeof window.visitImmunizations !== 'function') {
    window.visitImmunizations = function (visitId, editable) {
        return {
            visitId,
            editable,
            error: '',
            async markGiven(immunizationId) {
                if (!this.editable) return;
                this.error = '';
                try {
                    const r = await fetch('/api/v1/visits/' + this.visitId + '/immunizations/given', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ immunization_id: immunizationId }),
                    });
                    const data = await r.json();
                    if (!r.ok) throw new Error(data.error || 'Could not save');
                    location.reload();
                } catch (e) {
                    this.error = e.message || 'Save failed';
                }
            },
        };
    };
}
</script>
<?php endif; ?>
