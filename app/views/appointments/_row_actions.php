<?php
/**
 * Shared status-driven action buttons for one appointment row. Used by the
 * appointments listing (_list_body.php) and the dashboard today-panel
 * (_today_panel.php) so the doctor sees the same flow everywhere.
 *
 * Clean, compact layout — ONE labelled primary action (the obvious next step)
 * plus small icon-only secondary buttons, so rows never get cramped or wrap.
 * No dropdown (absolute menus get clipped inside the scrollable table).
 *   scheduled / confirmed → [🩺 Call]  ✓(arrive)  ✎(edit)  ⦸(no-show)  ✕(cancel)
 *   in_progress           → [▶ Resume] ✎(edit)
 *   completed             → ✓ Done  👤(patient)
 *   no_show               → [↩ Arrived late]
 *   cancelled             → ✎(edit)
 *
 * Requires: $a (appointment row), $csrf.
 * Status changes POST to /queue/{id}/status; QueueController redirects back to
 * the page we acted from (the `return` field).
 */
$aid     = (int) ($a['id'] ?? 0);
$pid     = (int) ($a['patient_id'] ?? 0);
$status  = (string) ($a['status'] ?? 'scheduled');
$canConsult = \App\Services\RoleAccessService::canConsult(\App\Core\RequestContext::user() ?? []);
$returnTo   = $_SERVER['REQUEST_URI'] ?? '/appointments';
$csrfTok    = htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES);
$retEsc     = htmlspecialchars($returnTo, ENT_QUOTES);

/**
 * A status-change submit button. $body is the inner HTML (icon or label),
 * $classes the button styling, $confirm whether to confirm first.
 */
$statusBtn = static function (string $to, string $body, string $classes, string $title = '', bool $confirm = false) use ($aid, $csrfTok, $retEsc): string {
    $onsubmit = $confirm
        ? ' onsubmit="return confirm(\'Mark this appointment as ' . htmlspecialchars(str_replace('_', ' ', $to), ENT_QUOTES) . '?\')"'
        : '';
    return '<form method="post" action="/queue/' . $aid . '/status" class="inline"' . $onsubmit . '>'
        . '<input type="hidden" name="_csrf" value="' . $csrfTok . '">'
        . '<input type="hidden" name="status" value="' . htmlspecialchars($to) . '">'
        . '<input type="hidden" name="return" value="' . $retEsc . '">'
        . '<button type="submit" class="' . $classes . '"' . ($title ? ' title="' . htmlspecialchars($title) . '"' : '') . '>'
        . $body . '</button></form>';
};

// Styles
$primary = 'inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold whitespace-nowrap';
$callBtn   = $primary . ' bg-emerald-600 text-white hover:bg-emerald-700';
$resumeBtn = $primary . ' bg-indigo-600 text-white hover:bg-indigo-700';
$lateBtn   = $primary . ' border text-slate-700 hover:bg-slate-50';
// Square icon buttons for secondary actions
$ico       = 'inline-flex h-7 w-7 items-center justify-center rounded-lg border text-xs';
$icoArrive = $ico . ' border-blue-200 text-blue-600 hover:bg-blue-50';
$icoMuted  = $ico . ' border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700';
$icoDanger = $ico . ' border-red-200 text-red-500 hover:bg-red-50';

$editIco    = '<a href="/appointments/' . $aid . '/edit" class="' . $icoMuted . '" title="Edit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>';
$cancelIco  = $statusBtn('cancelled', '✕', $icoDanger, 'Cancel appointment', true);
$noShowIco  = $statusBtn('no_show', '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="23" y2="14"/><line x1="23" y1="8" x2="17" y2="14"/></svg>', $icoMuted, 'Not arrived', true);
$arriveIco  = $statusBtn('confirmed', '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>', $icoArrive, 'Mark patient arrived / in clinic');
?>
<div class="flex items-center justify-end gap-1.5">
    <?php if ($status === 'scheduled' || $status === 'confirmed'): ?>

        <?php if ($canConsult): ?>
        <a href="/visits/new?appointment_id=<?= $aid ?>" class="<?= $callBtn ?>" title="Call patient in &amp; start consultation">🩺 Call</a>
        <?php endif; ?>
        <?php if ($status === 'scheduled'): ?><?= $arriveIco ?><?php endif; ?>
        <?= $editIco ?>
        <?= $noShowIco ?>
        <?= $cancelIco ?>

    <?php elseif ($status === 'in_progress'): ?>

        <?php if ($canConsult): ?>
        <a href="/visits/new?appointment_id=<?= $aid ?>" class="<?= $resumeBtn ?>" title="Back into the open consultation">▶ Resume</a>
        <?php else: ?>
        <span class="text-xs font-medium text-indigo-700">🩺 With doctor</span>
        <?php endif; ?>
        <?= $editIco ?>

    <?php elseif ($status === 'completed'): ?>

        <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Done
        </span>
        <?php if ($pid): ?>
        <a href="/patients/<?= $pid ?>" class="<?= $icoMuted ?>" title="View patient"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></a>
        <?php endif; ?>

    <?php elseif ($status === 'no_show'): ?>

        <?= $statusBtn('confirmed', '↩ Arrived late', $lateBtn, 'Patient came late') ?>

    <?php else: /* cancelled */ ?>
        <?= $editIco ?>
    <?php endif; ?>
</div>
