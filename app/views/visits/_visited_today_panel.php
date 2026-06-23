<?php
/**
 * Today's completed visits — dashboard panel (mirrors appointments/_today_panel layout).
 *
 * Required: $visits (list), $date, $visitedTodayCount or count from visits
 * Optional: $panelTitle
 */
use App\Support\ClinicTime;

$visits = $visits ?? [];
$date = $date ?? ClinicTime::today();
$panelTitle = $panelTitle ?? "Today's Visited Patients";
$displayDate = date('d M Y', strtotime($date));
$total = (int) ($visitedTodayCount ?? count($visits));
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-2 ui-section-title">
                <span class="text-brand"><?= ui_icon('emr', 18) ?></span> <?= htmlspecialchars($panelTitle) ?>
            </h2>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($displayDate) ?> · completed consultations</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/visits" class="ui-btn ui-btn-secondary ui-btn-sm">All visits</a>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border-2 border-emerald-400 bg-white p-4 text-left ring-2 ring-offset-1 ring-emerald-500">
            <p class="text-2xl font-bold text-emerald-600"><?= $total ?></p>
            <p class="text-xs uppercase tracking-wide text-slate-500">Visited today</p>
        </div>
    </div>

    <div class="overflow-hidden ui-card">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Visited</th>
                        <th class="px-4 py-3">Complaint</th>
                        <th class="px-4 py-3 text-right">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php $rowNum = 0; foreach ($visits as $v): $rowNum++; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white">
                                <?= $rowNum ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/patients/<?= (int) $v['patient_id'] ?>" class="font-medium text-emerald-700 hover:underline">
                                <?= htmlspecialchars((string) ($v['patient_name'] ?? '')) ?>
                            </a>
                            <div class="font-mono text-xs text-slate-500"><?= htmlspecialchars((string) ($v['uhid'] ?? '')) ?></div>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <?php $phone = trim((string) ($v['patient_phone'] ?? '')); ?>
                            <?php if ($phone !== ''): ?>
                            <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone)) ?>"
                               class="inline-flex items-center gap-1 font-medium text-emerald-700 hover:underline whitespace-nowrap">
                                <?= htmlspecialchars($phone) ?>
                            </a>
                            <?php else: ?><span class="text-slate-300">—</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600"><?= htmlspecialchars((string) ($v['doctor_name'] ?? '')) ?></td>
                        <td class="px-4 py-3 font-medium">
                            <?= htmlspecialchars(date('h:i A', strtotime((string) ($v['visited_at'] ?? '')))) ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 max-w-[200px] truncate" title="<?= htmlspecialchars((string) ($v['chief_complaint'] ?? '')) ?>">
                            <?= htmlspecialchars((string) ($v['chief_complaint'] ?? '—')) ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="/visits/<?= (int) $v['id'] ?>"
                               class="rounded border px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
                                Open EMR
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($visits === []): ?>
        <div class="p-12 text-center">
            <p class="mb-3 flex justify-center text-slate-300"><?= ui_icon('emr', 40) ?></p>
            <p class="text-sm font-medium text-slate-700">No completed visits on <?= htmlspecialchars($displayDate) ?></p>
            <p class="mt-1 text-xs text-slate-500">Patients you finish consulting today will appear here.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
