<?php
// ----- Aggregate the flat $report into per-staff summary + today's roll-call -----
$today = $today ?? null;
$report = $report ?? [];

$todayStr = date('Y-m-d');
$summary = []; // staff_name => [present, absent, leave, half, late, lastIn, lastOut, todayStatus]
$todayRows = [];
foreach ($report as $row) {
    $name = (string) ($row['staff_name'] ?? '—');
    if (!isset($summary[$name])) {
        $summary[$name] = ['present' => 0, 'absent' => 0, 'leave' => 0, 'half' => 0, 'late' => 0, 'totalHours' => 0, 'todayStatus' => null, 'todayIn' => null, 'todayOut' => null];
    }
    $s = (string) ($row['status'] ?? '');
    if ($s === 'present') $summary[$name]['present']++;
    elseif ($s === 'absent') $summary[$name]['absent']++;
    elseif ($s === 'leave') $summary[$name]['leave']++;
    elseif ($s === 'half_day' || $s === 'half') $summary[$name]['half']++;
    elseif ($s === 'late') $summary[$name]['late']++;

    if (!empty($row['clock_in']) && !empty($row['clock_out'])) {
        $in = strtotime((string) $row['clock_in']);
        $out = strtotime((string) $row['clock_out']);
        if ($in && $out && $out > $in) {
            $summary[$name]['totalHours'] += ($out - $in) / 3600;
        }
    }

    if (($row['date'] ?? '') === $todayStr) {
        $summary[$name]['todayStatus'] = $s;
        $summary[$name]['todayIn'] = $row['clock_in'] ?? null;
        $summary[$name]['todayOut'] = $row['clock_out'] ?? null;
        $todayRows[] = $row;
    }
}

$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$clockedIn = !empty($today['clock_in']) && empty($today['clock_out']);
$clockedOut = !empty($today['clock_in']) && !empty($today['clock_out']);

$statusBadge = static function (string $s): array {
    return match ($s) {
        'present' => ['bg-emerald-100 text-emerald-800', 'Present'],
        'absent' => ['bg-red-100 text-red-700', 'Absent'],
        'leave' => ['bg-blue-100 text-blue-700', 'Leave'],
        'late' => ['bg-amber-100 text-amber-800', 'Late'],
        'half_day', 'half' => ['bg-purple-100 text-purple-700', 'Half day'],
        default => ['bg-slate-100 text-slate-600', $s ?: '—'],
    };
};
?>
<div class="space-y-5" x-data="{ showLog: false }">
    <!-- ===== Header ===== -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-2 text-lg font-semibold">⏰ Staff attendance</h2>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($monthLabel) ?></p>
        </div>
        <div class="flex gap-2">
            <a href="/staff/leaves" class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50">📋 Leave requests</a>
        </div>
    </div>

    <?php if (!empty($_GET['clocked_in'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">✓ Clocked in.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['clocked_out'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">✓ Clocked out.</p>
    <?php endif; ?>

    <!-- ===== Your status today (big card) ===== -->
    <div class="rounded-xl border bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Your status today</p>
                <p class="mt-1 text-base font-semibold">
                    <?php if ($clockedOut): ?>
                        <span class="text-slate-700">🌙 Day complete</span>
                    <?php elseif ($clockedIn): ?>
                        <span class="text-emerald-700">🟢 Clocked in</span>
                    <?php else: ?>
                        <span class="text-slate-500">⚪ Not clocked in yet</span>
                    <?php endif; ?>
                </p>
                <?php if ($today): ?>
                <p class="mt-1 text-xs text-slate-500">
                    In: <span class="font-mono"><?= !empty($today['clock_in']) ? htmlspecialchars(substr((string) $today['clock_in'], 11, 5)) : '—' ?></span>
                    · Out: <span class="font-mono"><?= !empty($today['clock_out']) ? htmlspecialchars(substr((string) $today['clock_out'], 11, 5)) : '—' ?></span>
                </p>
                <?php endif; ?>
            </div>
            <div class="flex gap-2">
                <form method="post" action="/staff/attendance/clock-in">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" <?= $clockedIn || $clockedOut ? 'disabled' : '' ?>
                            class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">
                        Clock in
                    </button>
                </form>
                <form method="post" action="/staff/attendance/clock-out">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" <?= !$clockedIn ? 'disabled' : '' ?>
                            class="rounded-lg border px-5 py-2 text-sm font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                        Clock out
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== Today's roll-call ===== -->
    <div class="rounded-xl border bg-white">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold">Today — <?= htmlspecialchars(date('D, j M')) ?></h3>
            <p class="text-xs text-slate-500"><?= count($todayRows) ?> entries</p>
        </div>
        <?php if ($todayRows === []): ?>
        <p class="px-5 py-8 text-center text-sm text-slate-500">No attendance recorded today yet.</p>
        <?php else: ?>
        <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($todayRows as $row):
                [$badgeClass, $badgeText] = $statusBadge((string) ($row['status'] ?? ''));
            ?>
            <div class="rounded-lg border bg-slate-50 p-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="truncate text-sm font-medium"><?= htmlspecialchars((string) ($row['staff_name'] ?? '')) ?></p>
                    <span class="shrink-0 rounded px-2 py-0.5 text-xs font-medium <?= $badgeClass ?>"><?= htmlspecialchars($badgeText) ?></span>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-slate-500">In</span>
                        <p class="font-mono"><?= !empty($row['clock_in']) ? htmlspecialchars(substr((string) $row['clock_in'], 11, 5)) : '—' ?></p>
                    </div>
                    <div>
                        <span class="text-slate-500">Out</span>
                        <p class="font-mono"><?= !empty($row['clock_out']) ? htmlspecialchars(substr((string) $row['clock_out'], 11, 5)) : '—' ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== Month picker ===== -->
    <form method="get" class="flex flex-wrap items-center gap-2 rounded-xl border bg-white p-4 text-sm">
        <span class="text-slate-600">Viewing:</span>
        <select name="month" class="rounded-lg border px-3 py-2">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" name="year" value="<?= (int) $year ?>" min="2020" max="2100" class="w-24 rounded-lg border px-3 py-2">
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-white">Apply</button>
    </form>

    <!-- ===== Monthly per-staff summary ===== -->
    <div class="overflow-hidden rounded-xl border bg-white">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold">Monthly summary — <?= htmlspecialchars($monthLabel) ?></h3>
            <p class="text-xs text-slate-500"><?= count($summary) ?> staff</p>
        </div>
        <?php if ($summary === []): ?>
        <p class="px-5 py-12 text-center text-sm text-slate-500">No attendance records for this month.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3 text-center text-emerald-700">Present</th>
                        <th class="px-4 py-3 text-center text-red-600">Absent</th>
                        <th class="px-4 py-3 text-center text-blue-700">Leave</th>
                        <th class="px-4 py-3 text-center text-amber-700">Late</th>
                        <th class="px-4 py-3 text-center text-purple-700">Half-day</th>
                        <th class="px-4 py-3 text-right">Hours</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($summary as $name => $s): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($name) ?></td>
                        <td class="px-4 py-3 text-center font-semibold text-emerald-700"><?= (int) $s['present'] ?></td>
                        <td class="px-4 py-3 text-center font-semibold text-red-600"><?= (int) $s['absent'] ?></td>
                        <td class="px-4 py-3 text-center text-blue-700"><?= (int) $s['leave'] ?></td>
                        <td class="px-4 py-3 text-center text-amber-700"><?= (int) $s['late'] ?></td>
                        <td class="px-4 py-3 text-center text-purple-700"><?= (int) $s['half'] ?></td>
                        <td class="px-4 py-3 text-right font-mono text-xs"><?= number_format((float) $s['totalHours'], 1) ?>h</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== Detailed log (collapsible) ===== -->
    <div class="rounded-xl border bg-white">
        <button type="button" @click="showLog = !showLog"
                class="flex w-full items-center justify-between px-5 py-3 text-sm font-medium hover:bg-slate-50">
            <span>Detailed daily log (<?= count($report) ?> entries)</span>
            <span x-text="showLog ? '▾' : '▸'"></span>
        </button>
        <div x-show="showLog" x-collapse class="overflow-x-auto border-t">
            <table class="w-full min-w-[600px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">In</th>
                        <th class="px-4 py-3">Out</th>
                        <th class="px-4 py-3">Hours</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($report as $row):
                        [$badgeClass, $badgeText] = $statusBadge((string) ($row['status'] ?? ''));
                        $hours = '';
                        if (!empty($row['clock_in']) && !empty($row['clock_out'])) {
                            $in = strtotime((string) $row['clock_in']);
                            $out = strtotime((string) $row['clock_out']);
                            if ($in && $out && $out > $in) {
                                $hours = number_format(($out - $in) / 3600, 1) . 'h';
                            }
                        }
                    ?>
                    <tr>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($row['staff_name'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-xs"><?= htmlspecialchars((string) ($row['date'] ?? '')) ?></td>
                        <td class="px-4 py-3 font-mono text-xs"><?= !empty($row['clock_in']) ? htmlspecialchars(substr((string) $row['clock_in'], 11, 5)) : '—' ?></td>
                        <td class="px-4 py-3 font-mono text-xs"><?= !empty($row['clock_out']) ? htmlspecialchars(substr((string) $row['clock_out'], 11, 5)) : '—' ?></td>
                        <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($hours ?: '—') ?></td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs font-medium <?= $badgeClass ?>"><?= htmlspecialchars($badgeText) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
