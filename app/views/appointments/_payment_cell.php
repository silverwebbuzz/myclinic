<?php
/**
 * Payment cell for the appointment day list (dashboard + Appointments page).
 *
 * Shows what the visit invoice is worth and whether it is settled; when money
 * is still due, whoever may touch billing (reception, doctor, clinic admin)
 * can record it right here instead of opening Patient Bills.
 *
 * Required vars: $a (appointment row, with 'invoice' attached), $csrf
 */
$inv = $a['invoice'] ?? null;

$payUser = \App\Core\RequestContext::user() ?? [];
$canRecordPayment = \App\Services\RoleAccessService::canAccessPath($payUser, 'POST', '/billing')
    && (\App\Gates\ModuleGate::check('invoicing_basic') || \App\Gates\ModuleGate::check('billing_pro'));
$returnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/dashboard');
?>
<?php if ($inv === null): ?>
    <span class="text-xs text-slate-300">—</span>
<?php elseif (($inv['due'] ?? 0) > 0.005): ?>
    <div class="space-y-1">
        <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800">
            ₹<?= number_format((float) $inv['due'], 0) ?> due
        </span>
        <?php if ($canRecordPayment): ?>
        <form method="post" action="/billing/<?= (int) $inv['invoice_id'] ?>/payment" class="flex items-center gap-1">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
            <select name="method" class="rounded border border-slate-300 px-1.5 py-0.5 text-[11px]">
                <option value="cash">Cash</option>
                <option value="online">Online</option>
            </select>
            <button type="submit" class="rounded bg-brand px-2 py-0.5 text-[11px] font-semibold text-white hover:bg-brand-dark">
                Mark paid
            </button>
        </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800">
        ✓ Paid ₹<?= number_format((float) $inv['total'], 0) ?>
    </span>
    <?php if (!empty($inv['payment_mode'])): ?>
    <div class="text-[10px] uppercase text-slate-400"><?= htmlspecialchars((string) $inv['payment_mode']) ?></div>
    <?php endif; ?>
<?php endif; ?>
