<?php
/**
 * Shared status-driven action buttons for one appointment row. Used by the
 * appointments listing (_list_body.php) and the dashboard today-panel
 * (_today_panel.php) so the doctor sees the same flow everywhere.
 *
 * The buttons change with status (modelled on drfeelgoods' queue):
 *   scheduled  → Arrive (mark the patient as in clinic)
 *   confirmed  → Call   (one click: starts the visit + opens the consult page;
 *                        /visits/new sets status=in_progress for us)
 *   in_progress→ Resume consult (back into the open visit)
 *   completed  → Done · Invoice
 * Plus Edit, and a Cancel ✕ while the visit is still open.
 *
 * Requires: $a (appointment row), $csrf.
 * Status changes POST to /queue/{id}/status (QueueController::updateStatus),
 * which redirects back to the referring page.
 */
$aid     = (int) ($a['id'] ?? 0);
$pid     = (int) ($a['patient_id'] ?? 0);
$status  = (string) ($a['status'] ?? 'scheduled');
$canConsult = \App\Services\RoleAccessService::canConsult(\App\Core\RequestContext::user() ?? []);
// Return to whatever page we acted from (appointments / dashboard), not /queue.
$returnTo = $_SERVER['REQUEST_URI'] ?? '/appointments';

/** Tiny inline form that flips status, then returns to the current page. */
$statusForm = static function (string $to, string $label, string $classes, string $title = '', bool $confirm = false) use ($aid, $csrf, $returnTo): string {
    $onsubmit = $confirm
        ? ' onsubmit="return confirm(\'Mark this appointment as ' . htmlspecialchars(strtolower($label), ENT_QUOTES) . '?\')"'
        : '';
    return '<form method="post" action="/queue/' . $aid . '/status" class="inline"' . $onsubmit . '>'
        . '<input type="hidden" name="_csrf" value="' . htmlspecialchars((string) ($csrf ?? '')) . '">'
        . '<input type="hidden" name="status" value="' . htmlspecialchars($to) . '">'
        . '<input type="hidden" name="return" value="' . htmlspecialchars($returnTo) . '">'
        . '<button type="submit" class="' . $classes . '"' . ($title ? ' title="' . htmlspecialchars($title) . '"' : '') . '>'
        . $label . '</button></form>';
};

$btnBase   = 'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium';
$callBtn   = $btnBase . ' bg-emerald-600 text-white hover:bg-emerald-700';
$arriveBtn = $btnBase . ' bg-blue-600 text-white hover:bg-blue-700';
$ghostBtn  = $btnBase . ' border text-slate-600 hover:bg-slate-50';
$dangerBtn = $btnBase . ' border border-red-200 text-red-600 hover:bg-red-50';
?>
<div class="flex flex-wrap items-center justify-end gap-1.5">
    <?php if ($status === 'scheduled' || $status === 'confirmed'): ?>

        <?php if ($status === 'scheduled'): ?>
            <?= $statusForm('confirmed', '✓ Arrived', $arriveBtn, 'Mark patient as arrived / in clinic') ?>
        <?php endif; ?>

        <?php if ($canConsult): ?>
        <!-- One-click Call: starts the visit AND opens the consult page;
             /visits/new sets status=in_progress for us. (point d) -->
        <a href="/visits/new?appointment_id=<?= $aid ?>" class="<?= $callBtn ?>" title="Call patient in &amp; start consultation">
            🩺 Call
        </a>
        <?php endif; ?>

        <a href="/appointments/<?= $aid ?>/edit" class="<?= $ghostBtn ?>">Edit</a>
        <?= $statusForm('no_show', 'Not arrived', $ghostBtn, 'Patient did not arrive', true) ?>
        <?= $statusForm('cancelled', '✕', $dangerBtn, 'Cancel appointment', true) ?>

    <?php elseif ($status === 'in_progress'): ?>

        <?php if ($canConsult): ?>
        <a href="/visits/new?appointment_id=<?= $aid ?>" class="<?= $btnBase ?> bg-indigo-600 text-white hover:bg-indigo-700" title="Back into the open consultation">
            ▶ Resume consult
        </a>
        <?php else: ?>
        <span class="text-xs font-medium text-indigo-700">🩺 With doctor</span>
        <?php endif; ?>
        <a href="/appointments/<?= $aid ?>/edit" class="<?= $ghostBtn ?>">Edit</a>

    <?php elseif ($status === 'completed'): ?>

        <span class="text-xs text-slate-400">✓ Done</span>
        <?php if ($pid): ?>
        <a href="/patients/<?= $pid ?>" class="<?= $ghostBtn ?>" title="View patient">Patient</a>
        <?php endif; ?>

    <?php elseif ($status === 'no_show'): ?>

        <?= $statusForm('confirmed', '↩ Arrived late', $ghostBtn, 'Patient came late') ?>

    <?php else: /* cancelled */ ?>
        <a href="/appointments/<?= $aid ?>/edit" class="<?= $ghostBtn ?>">Edit</a>
    <?php endif; ?>
</div>
