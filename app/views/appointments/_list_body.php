<?php
// =====================================================================
// appointments/_list_body.php — the day-view results block (count cards +
// status tabs + appointment table). Rendered both inline by
// appointments/index.php and on its own by AppointmentController::listApi()
// so the appointments page can live-refresh this region via polling
// (same pattern as dashboard/_today_panel).
//
// Required vars: $appointments, $counts, $statusFilter, $date,
//   $displayDate, $doctorId, $statusFilter, $csrf.
// Self-contained closures below so the partial works in either context.
// =====================================================================
$statusFilter = $statusFilter ?? 'all';
$displayDate  = $displayDate ?? date('d M Y', strtotime($date));

$statusBadge = static fn (string $status): string => match ($status) {
    'scheduled' => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'in_progress' => 'bg-indigo-100 text-indigo-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'no_show' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-slate-200 text-slate-600 line-through',
    default => 'bg-slate-100 text-slate-700',
};
$qs = static function (array $extra) use ($date, $doctorId, $statusFilter): string {
    return http_build_query(array_filter(array_merge([
        'date' => $date,
        'doctor_id' => $doctorId,
        'status' => $statusFilter,
    ], $extra), static fn ($v) => $v !== null && $v !== ''));
};
$bookUrl = static function (?string $d = null, ?string $time = null) use ($date, $doctorId): string {
    $params = array_filter([
        'date' => $d ?? $date,
        'doctor_id' => $doctorId,
        'time' => $time,
    ], static fn ($v) => $v !== null && $v !== '');
    return '/appointments/new' . ($params ? '?' . http_build_query($params) : '');
};

$cards = [
    ['key' => 'all', 'label' => 'Total', 'color' => 'border-slate-300', 'text' => 'text-slate-800'],
    ['key' => 'scheduled', 'label' => 'Waiting', 'color' => 'border-amber-400', 'text' => 'text-amber-600'],
    ['key' => 'confirmed', 'label' => 'Confirmed', 'color' => 'border-blue-400', 'text' => 'text-blue-600'],
    ['key' => 'in_progress', 'label' => 'In Consult', 'color' => 'border-indigo-400', 'text' => 'text-indigo-600'],
    ['key' => 'completed', 'label' => 'Completed', 'color' => 'border-emerald-400', 'text' => 'text-emerald-600'],
];
?>
<!-- data-appt-total lets the poller detect when the count changed (toast). -->
<div data-appt-total="<?= (int) ($counts['all'] ?? 0) ?>">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <?php foreach ($cards as $card): ?>
        <a href="?<?= htmlspecialchars($qs(['status' => $card['key']])) ?>"
           class="rounded-xl border-2 bg-white p-4 transition hover:shadow-sm <?= $card['color'] ?> <?= $statusFilter === $card['key'] ? 'ring-2 ring-offset-1 ring-emerald-500' : '' ?>">
            <p class="text-2xl font-bold <?= $card['text'] ?>"><?= (int) ($counts[$card['key']] ?? 0) ?></p>
            <p class="text-xs uppercase tracking-wide text-slate-500"><?= htmlspecialchars($card['label']) ?></p>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="mt-3 overflow-hidden ui-card">
        <div class="flex flex-wrap gap-1 border-b px-2 py-2 text-sm">
            <?php
            $tabs = [
                'all' => 'All',
                'scheduled' => 'Waiting',
                'confirmed' => 'Confirmed',
                'in_progress' => 'In Consult',
                'completed' => 'Completed',
                'no_show' => 'Not Arrived',
                'cancelled' => 'Cancelled',
            ];
            foreach ($tabs as $key => $label):
                $active = $statusFilter === $key;
            ?>
            <a href="?<?= htmlspecialchars($qs(['status' => $key])) ?>"
               class="rounded-lg px-3 py-1.5 <?= $active ? 'bg-emerald-50 font-medium text-emerald-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                <?= htmlspecialchars($label) ?>
                <span class="ml-1 text-xs text-slate-400">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1040px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Slot</th>
                        <th class="px-4 py-3 text-center" title="Called into consult">In</th>
                        <th class="px-4 py-3 text-center" title="Consult finished">Out</th>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($appointments as $i => $a): ?>
                    <?php
                        $status = (string) ($a['status'] ?? 'scheduled');
                        $type = (string) ($a['type'] ?? 'prebooked');
                        $typeBadge = match ($type) {
                            'walkin' => 'bg-slate-100 text-slate-700',
                            'online' => 'bg-cyan-100 text-cyan-800',
                            'followup' => 'bg-purple-100 text-purple-800',
                            default => 'bg-indigo-50 text-indigo-700',
                        };
                    ?>
                    <tr class="hover:bg-slate-50 <?= in_array($status, ['completed','cancelled','no_show'], true) ? 'opacity-60' : '' ?>">
                        <td class="px-4 py-3">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold text-white <?= in_array($status, ['completed','cancelled','no_show'], true) ? 'bg-slate-400' : 'bg-emerald-600' ?>">
                                <?= $i + 1 ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/patients/<?= (int) $a['patient_id'] ?>" class="font-medium text-emerald-700 hover:underline">
                                <?= htmlspecialchars((string) ($a['patient_name'] ?? '')) ?>
                            </a>
                            <div class="font-mono text-xs text-slate-500"><?= htmlspecialchars((string) ($a['uhid'] ?? '')) ?></div>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <?php $phone = trim((string) ($a['patient_phone'] ?? '')); ?>
                            <?php if ($phone !== ''): ?>
                            <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone)) ?>"
                               class="inline-flex items-center gap-1 font-medium text-emerald-700 hover:underline whitespace-nowrap"
                               title="Call patient">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                <?= htmlspecialchars($phone) ?>
                            </a>
                            <?php else: ?><span class="text-slate-300">—</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs <?= $typeBadge ?>">
                                <?= $type === 'walkin' ? 'Walk-in' : ($type === 'online' ? 'Online' : ($type === 'followup' ? 'Follow-up' : 'Booked')) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium">
                            <?= htmlspecialchars(date('h:i A', strtotime((string) $a['scheduled_at']))) ?>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-slate-500">
                            <?= !empty($a['consult_started_at']) ? htmlspecialchars(date('h:i A', strtotime((string) $a['consult_started_at']))) : '<span class="text-slate-300">—</span>' ?>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-slate-500">
                            <?= !empty($a['completed_at']) ? htmlspecialchars(date('h:i A', strtotime((string) $a['completed_at']))) : '<span class="text-slate-300">—</span>' ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600"><?= htmlspecialchars((string) ($a['doctor_name'] ?? '')) ?></td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs font-medium <?= $statusBadge($status) ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <?php require __DIR__ . '/_payment_cell.php'; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                                $rowUser = \App\Core\RequestContext::user() ?? [];
                                $canManageRow = \App\Services\RoleAccessService::canManageAppointment($rowUser, $a);
                            ?>
                            <?php if ($canManageRow): ?>
                                <?php require __DIR__ . '/_row_actions.php'; ?>
                            <?php else: ?>
                            <span class="block text-right text-xs text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($appointments)): ?>
        <div class="p-12 text-center">
            <p class="mb-3 flex justify-center text-slate-300"><?= ui_icon('appointments', 40) ?></p>
            <p class="text-sm font-medium text-slate-700">No appointments<?= $statusFilter !== 'all' ? ' in this status' : '' ?> on <?= htmlspecialchars($displayDate) ?></p>
            <p class="mt-1 text-xs text-slate-500">Try another date or status, or book a new appointment.</p>
            <?php
                $canBookAppointments = \App\Services\RoleAccessService::canBookAppointments(\App\Core\RequestContext::user() ?? []);
            ?>
            <?php if ($canBookAppointments): ?>
            <a href="<?= htmlspecialchars($bookUrl()) ?>" class="mt-4 inline-block ui-btn ui-btn-primary">+ Book appointment</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
