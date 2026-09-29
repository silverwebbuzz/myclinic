<?php
/**
 * @var array{pending:int,available:int,in_payout:int,paid:int} $balances
 * @var list<array<string,mixed>> $ledger
 * @var list<array<string,mixed>> $payouts
 * @var array<string,mixed>|null $bank
 */
use App\Services\Store\ProductService;

$pageTitle = 'Payouts';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
$typeLabel = ['sale_credit' => 'Sale', 'commission_debit' => 'Commission', 'commission_gst_debit' => 'GST on commission',
    'tcs_debit' => 'TCS', 'tds_debit' => 'TDS', 'shipping_debit' => 'Shipping', 'refund_reversal' => 'Refund', 'rto_charge' => 'Return-to-origin',
    'penalty' => 'Penalty', 'adjustment' => 'Adjustment', 'payout' => 'Payout'];
$statusLabel = ['pending' => 'In return window', 'available' => 'Next payout', 'in_payout' => 'Being paid', 'paid' => 'Paid', 'on_hold' => 'On hold'];
ob_start();
?>
<h1 class="text-[22px] font-semibold tracking-[-.015em]">Payouts</h1>
<p class="mt-1 text-sm text-tx3">You earn on every <strong>delivered</strong> package. Earnings unlock after the return window. Once they're ready, request a payout and we'll transfer it to your bank.</p>

<div class="mt-5 grid gap-3 sm:grid-cols-4">
    <?php foreach ([['pending', 'In return window', 'Unlocks after the return window'], ['available', 'Ready to request', 'Request a payout below'],
                     ['in_payout', 'Being paid', 'Transfer in progress'], ['paid', 'Paid to you', 'Lifetime']] as [$k, $label, $hint]): ?>
        <div class="rounded-[10px] border border-ln bg-sf p-4">
            <div class="text-xs uppercase tracking-wide text-tx3"><?= $e($label) ?></div>
            <div class="mt-1 text-xl font-semibold <?= $balances[$k] < 0 ? 'text-red-600' : '' ?>"><?= $r($balances[$k]) ?></div>
            <div class="text-xs text-tx3"><?= $e($hint) ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($bank === null || $bank['status'] !== 'verified'): ?>
    <div class="mt-4 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
        Payouts need a <strong>verified</strong> bank account. <a href="/vendor/bank" class="underline">Check your bank details</a>.
    </div>
<?php endif; ?>

<?php
$hasOpenPayout = (bool) array_filter($payouts, static fn ($p) => in_array($p['status'], ['draft', 'approved', 'processing'], true));
$bankOk = $bank !== null && $bank['status'] === 'verified';
$canRequest = $bankOk && !$hasOpenPayout && $balances['available'] >= $minPayout;
?>
<section class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-[10px] border border-ln bg-sf p-5">
    <div class="text-sm">
        <div class="font-semibold">Request a payout</div>
        <div class="mt-0.5 text-tx3">
            <?php if ($hasOpenPayout): ?>
                A payout is already being processed. You can request again once it's paid.
            <?php elseif (!$bankOk): ?>
                Available once your bank account is verified.
            <?php elseif ($balances['available'] < $minPayout): ?>
                Minimum payout is <?= $r($minPayout) ?>. You have <?= $r(max(0, $balances['available'])) ?> ready.
            <?php else: ?>
                <?= $r($balances['available']) ?> is ready. We usually transfer within 2 working days and email you the bank reference (UTR).
            <?php endif; ?>
        </div>
    </div>
    <?php if ($canRequest): ?>
        <form method="post" action="/vendor/payouts/request" class="flex flex-wrap items-center gap-2"
              onsubmit="return confirm('Request a payout of <?= $e($r($balances['available'])) ?> to your bank account ending <?= $e($bank['account_last4'] ?? '') ?>?')">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input name="note" maxlength="500" placeholder="Note for our team (optional)" class="w-64 rounded-[7px] border border-ln bg-sf px-3 py-2 text-sm">
            <button class="rounded-[7px] bg-ac px-4 py-2 text-sm font-medium text-white hover:opacity-90">Request payout of <?= $r($balances['available']) ?></button>
        </form>
    <?php endif; ?>
</section>

<section class="mt-6 rounded-[10px] border border-ln bg-sf p-6">
    <h2 class="font-semibold">Payouts</h2>
    <?php if (!$payouts): ?>
        <p class="mt-2 text-sm text-tx3">No payouts yet.</p>
    <?php else: ?>
        <table class="mt-3 w-full text-sm">
            <thead class="text-left text-xs uppercase text-tx3"><tr><th class="py-1">Payout</th><th>Period</th><th class="text-right">Amount</th><th>Status</th><th>UTR</th><th></th></tr></thead>
            <tbody class="divide-y">
            <?php foreach ($payouts as $p): ?>
                <tr><td class="py-2 font-mono"><?= $e($p['payout_no']) ?></td>
                    <td class="text-tx3"><?= $e($p['period_from']) ?> → <?= $e($p['period_to']) ?></td>
                    <td class="text-right font-semibold"><?= $r($p['net_paise']) ?></td>
                    <td><?= $e($p['status'] === 'draft' || $p['status'] === 'approved' ? 'Processing' : ($p['status'] === 'cancelled' ? 'Declined' : ucfirst((string) $p['status']))) ?>
                        <?php if (in_array($p['status'], ['cancelled', 'failed'], true) && !empty($p['failure_reason'] ?? $p['decline_reason'] ?? '')): ?>
                            <div class="text-xs text-tx3"><?= $e($p['decline_reason'] ?? $p['failure_reason']) ?></div>
                        <?php endif; ?></td>
                    <td class="font-mono text-xs"><?= $e($p['reference'] ?? '') ?></td>
                    <td class="text-right"><a href="/vendor/payouts/<?= (int) $p['id'] ?>/statement" class="text-act hover:underline">Statement</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="mt-6 rounded-[10px] border border-ln bg-sf p-6">
    <h2 class="font-semibold">Earnings history</h2>
    <?php if (!$ledger): ?>
        <p class="mt-2 text-sm text-tx3">Nothing yet. Entries appear when your packages are delivered.</p>
    <?php else: ?>
        <table class="mt-3 w-full text-sm">
            <thead class="text-left text-xs uppercase text-tx3"><tr><th class="py-1">Date</th><th>Order</th><th>Type</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
            <tbody class="divide-y">
            <?php foreach ($ledger as $l): ?>
                <tr><td class="py-1.5 text-tx3"><?= $e(substr((string) $l['created_at'], 0, 10)) ?></td>
                    <td class="font-mono text-xs"><?= $e($l['sub_order_no'] ?? '—') ?></td>
                    <td><?= $e($typeLabel[$l['entry_type']] ?? $l['entry_type']) ?><?= in_array($l['entry_type'], ['penalty', 'adjustment'], true) && !empty($l['memo']) ? ' <span class="text-xs text-tx3">(' . $e($l['memo']) . ')</span>' : '' ?></td>
                    <td class="text-xs text-tx3"><?= $e($statusLabel[$l['status']] ?? $l['status']) ?><?= $l['status'] === 'pending' && $l['available_at'] ? ' until ' . $e(substr((string) $l['available_at'], 0, 10)) : '' ?></td>
                    <td class="text-right <?= (int) $l['amount_paise'] < 0 ? 'text-red-600' : '' ?>"><?= $r($l['amount_paise']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
