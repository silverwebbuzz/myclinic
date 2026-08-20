<?php
/**
 * Today's Appointments panel — status tiles + filter tabs + table + row actions,
 * scoped to a single date. Extracted from appointments/index.php so the dashboard
 * can embed the same facilities (rename: "Today's Appointments").
 *
 * Tab/tile filtering is done client-side (Alpine `apptPanel`) so the dashboard
 * doesn't reload the whole page when a status is clicked. The table renders ALL
 * statuses once; rows are shown/hidden by the active filter.
 *
 * Required vars:
 *   $appointments  — array of appointment rows (ALL statuses for the date)
 *   $counts        — ['all'=>n,'scheduled'=>n,...] status tallies
 *   $date          — 'Y-m-d'
 *   $csrf          — token for the cancel action
 * Optional:
 *   $statusFilter  — initial active filter (default 'all')
 *   $panelTitle    — heading text (default 'Today's Appointments')
 */
$canBookAppointments = \App\Services\RoleAccessService::canBookAppointments(\App\Core\RequestContext::user() ?? []);
$statusFilter = $statusFilter ?? 'all';
$panelTitle   = $panelTitle ?? "Today's Appointments";
$displayDate  = date('d M Y', strtotime($date));

$statusBadge = static fn (string $status): string => match ($status) {
    'scheduled' => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'in_progress' => 'bg-indigo-100 text-indigo-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'no_show' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-slate-200 text-slate-600 line-through',
    default => 'bg-slate-100 text-slate-700',
};

$cards = [
    ['key' => 'all', 'label' => 'Total', 'color' => 'border-slate-300', 'text' => 'text-slate-800'],
    ['key' => 'scheduled', 'label' => 'Waiting', 'color' => 'border-amber-400', 'text' => 'text-amber-600'],
    ['key' => 'confirmed', 'label' => 'Confirmed', 'color' => 'border-blue-400', 'text' => 'text-blue-600'],
    ['key' => 'in_progress', 'label' => 'In Consult', 'color' => 'border-indigo-400', 'text' => 'text-indigo-600'],
    ['key' => 'completed', 'label' => 'Completed', 'color' => 'border-emerald-400', 'text' => 'text-emerald-600'],
];
$tabs = [
    'all' => 'All',
    'scheduled' => 'Waiting',
    'confirmed' => 'Confirmed',
    'in_progress' => 'In Consult',
    'completed' => 'Completed',
    'no_show' => 'Not Arrived',
    'cancelled' => 'Cancelled',
];
?>
<div class="space-y-4" x-data="apptPanel(<?= htmlspecialchars(json_encode(['filter' => $statusFilter], JSON_THROW_ON_ERROR), ENT_QUOTES) ?>)">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-2 ui-section-title">
                <span class="text-brand"><?= ui_icon('appointments', 18) ?></span> <?= htmlspecialchars($panelTitle) ?>
            </h2>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($displayDate) ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/appointments/calendar" class="ui-btn ui-btn-secondary ui-btn-sm">Calendar</a>
            <?php if ($canBookAppointments): ?>
            <a href="/appointments/new?date=<?= htmlspecialchars($date) ?>" class="ui-btn ui-btn-primary ui-btn-sm">+ Book</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status tiles — clicking filters the table client-side -->
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <?php foreach ($cards as $card): ?>
        <button type="button" @click="filter = '<?= $card['key'] ?>'"
           class="rounded-xl border-2 bg-white p-4 text-left transition hover:shadow-sm <?= $card['color'] ?>"
           :class="filter === '<?= $card['key'] ?>' ? 'ring-2 ring-offset-1 ring-emerald-500' : ''">
            <p class="text-2xl font-bold <?= $card['text'] ?>"><?= (int) ($counts[$card['key']] ?? 0) ?></p>
            <p class="text-xs uppercase tracking-wide text-slate-500"><?= htmlspecialchars($card['label']) ?></p>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="overflow-hidden ui-card">
        <div class="flex flex-wrap gap-1 border-b px-2 py-2 text-sm">
            <?php foreach ($tabs as $key => $label): ?>
            <button type="button" @click="filter = '<?= $key ?>'"
               class="rounded-lg px-3 py-1.5"
               :class="filter === '<?= $key ?>' ? 'bg-emerald-50 font-medium text-emerald-700' : 'text-slate-600 hover:bg-slate-50'">
                <?= htmlspecialchars($label) ?>
                <span class="ml-1 text-xs text-slate-400">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1080px] text-sm">
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
                        <th class="px-4 py-3">Complaint</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php $rowNum = 0; foreach ($appointments as $a): ?>
                    <?php
                        $status = (string) ($a['status'] ?? 'scheduled');
                        $type = (string) ($a['type'] ?? 'prebooked');
                        $typeBadge = match ($type) {
                            'walkin' => 'bg-slate-100 text-slate-700',
                            'online' => 'bg-cyan-100 text-cyan-800',
                            'followup' => 'bg-purple-100 text-purple-800',
                            default => 'bg-indigo-50 text-indigo-700',
                        };
                        $rowNum++;
                    ?>
                    <tr class="hover:bg-slate-50 <?= in_array($status, ['completed','cancelled','no_show'], true) ? 'opacity-60' : '' ?>"
                        x-show="filter === 'all' || filter === '<?= $status ?>'">
                        <td class="px-4 py-3">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold text-white <?= in_array($status, ['completed','cancelled','no_show'], true) ? 'bg-slate-400' : 'bg-emerald-600' ?>">
                                <?= $rowNum ?>
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
                        <td class="px-4 py-3 text-xs text-slate-600 max-w-[200px] truncate" title="<?= htmlspecialchars((string) ($a['chief_complaint'] ?? '')) ?>">
                            <?= htmlspecialchars((string) ($a['chief_complaint'] ?? '—')) ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs font-medium <?= $statusBadge($status) ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                            </span>
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
            <p class="text-sm font-medium text-slate-700">No appointments on <?= htmlspecialchars($displayDate) ?></p>
            <p class="mt-1 text-xs text-slate-500">New bookings and walk-ins will show up here as they come in.</p>
            <?php if ($canBookAppointments): ?>
            <a href="/appointments/new?date=<?= htmlspecialchars($date) ?>" class="mt-4 inline-block ui-btn ui-btn-primary">+ Book appointment</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Empty-for-this-filter notice (all rows hidden by the active tab) -->
        <div x-show="!hasVisibleRows()" x-cloak class="p-8 text-center text-sm text-slate-500">
            No appointments in this status on <?= htmlspecialchars($displayDate) ?>.
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function apptPanel(cfg) {
    return {
        filter: cfg.filter || 'all',
        hasVisibleRows() {
            // Any row matching the current filter? Used to show the empty-filter notice.
            return this.$root.querySelectorAll('tbody tr').length > 0
                && Array.from(this.$root.querySelectorAll('tbody tr'))
                    .some(tr => tr.style.display !== 'none');
        },
    };
}
</script>
