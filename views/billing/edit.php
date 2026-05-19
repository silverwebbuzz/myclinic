<?php
$items = $invoice['items'] ?? [];
$patient = $invoice['patient'] ?? [];
$due = (float) $invoice['total'] - (float) ($invoice['advance_paid'] ?? 0);
?>
<div class="mx-auto max-w-3xl space-y-4" x-data="billingEditor(<?= (int) $invoice['id'] ?>)">
    <?php if (!empty($message)): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
        <?= $message === 'paid' ? 'Payment recorded.' : 'Invoice saved.' ?>
    </p>
    <?php endif; ?>

    <div class="rounded-xl border bg-white p-6">
        <div class="flex flex-wrap justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold"><?= htmlspecialchars($invoice['invoice_number']) ?></h2>
                <p class="text-sm text-slate-500"><?= htmlspecialchars($patient['patient_name'] ?? $patient['name'] ?? '') ?> · <?= htmlspecialchars($patient['uhid'] ?? '') ?></p>
                <?php if ((float) ($patient['advance_balance'] ?? 0) > 0): ?>
                <p class="mt-1 text-xs text-emerald-700">Advance balance: ₹<?= number_format((float) $patient['advance_balance'], 2) ?></p>
                <?php endif; ?>
            </div>
            <p class="text-2xl font-bold">₹<span id="live-total"><?= number_format((float) $invoice['total'], 2) ?></span></p>
        </div>

        <form method="post" action="/billing/<?= (int) $invoice['id'] ?>" class="mt-6 space-y-4" id="invoice-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <h3 class="font-medium">Line items</h3>
            <div id="line-items" class="space-y-2">
                <?php foreach ($items as $i => $item): ?>
                <div class="grid grid-cols-12 gap-2">
                    <input name="item_description[]" value="<?= htmlspecialchars($item['description']) ?>" class="col-span-6 rounded border px-2 py-1 text-sm" placeholder="Description">
                    <input name="item_qty[]" type="number" value="<?= (int) $item['qty'] ?>" class="col-span-2 rounded border px-2 py-1 text-sm line-qty">
                    <input name="item_price[]" type="number" step="0.01" value="<?= (float) $item['unit_price'] ?>" class="col-span-3 rounded border px-2 py-1 text-sm line-price">
                </div>
                <?php endforeach; ?>
                <?php if ($items === []): ?>
                <div class="grid grid-cols-12 gap-2">
                    <input name="item_description[]" class="col-span-6 rounded border px-2 py-1 text-sm" placeholder="Description">
                    <input name="item_qty[]" type="number" value="1" class="col-span-2 rounded border px-2 py-1 text-sm line-qty">
                    <input name="item_price[]" type="number" step="0.01" class="col-span-3 rounded border px-2 py-1 text-sm line-price">
                </div>
                <?php endif; ?>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-sm">Discount %
                    <input name="discount_percent" type="number" step="0.1" value="0" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" id="discount-pct">
                </label>
                <label class="text-sm">Tax % (<?= htmlspecialchars($invoice['tax_label'] ?? 'GST') ?>)
                    <input name="tax_percent" type="number" step="0.01" value="<?= htmlspecialchars((string) ($invoice['tax_percent'] ?? $taxPercent)) ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" id="tax-pct">
                </label>
                <label class="text-sm flex items-end gap-2 pb-2">
                    <input type="checkbox" name="apply_advance" value="1" <?= (float) ($patient['advance_balance'] ?? 0) > 0 ? '' : 'disabled' ?>>
                    Apply patient advance
                </label>
            </div>

            <label class="block text-sm">Notes
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
            </label>

            <button type="submit" class="rounded-lg border px-4 py-2 text-sm">Save invoice</button>
        </form>
    </div>

    <?php if (($invoice['status'] ?? '') !== 'paid'): ?>
    <div class="rounded-xl border bg-white p-6 space-y-4">
        <h3 class="font-semibold">Collect payment · Due ₹<?= number_format($due, 2) ?></h3>
        <form method="post" action="/billing/<?= (int) $invoice['id'] ?>/pay-cash" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white">Cash payment</button>
        </form>
        <div class="border-t pt-4">
            <p class="text-sm font-medium">UPI (Razorpay)</p>
            <button type="button" @click="createUpi()" class="mt-2 rounded-lg border px-4 py-2 text-sm">Generate UPI QR</button>
            <p x-show="qrData" class="mt-2 break-all text-xs text-slate-600" x-text="qrData"></p>
            <button type="button" @click="checkPayment()" class="mt-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Check payment</button>
            <button type="button" @click="simulatePay()" class="mt-2 ml-2 rounded-lg border px-3 py-2 text-xs text-slate-600">Simulate pay (dev)</button>
            <p x-show="payStatus" class="mt-2 text-sm" x-text="payStatus"></p>
        </div>
        <p class="text-xs text-slate-400">Stripe PaymentElement can be wired when STRIPE_PUBLISHABLE_KEY is set.</p>
    </div>
    <?php else: ?>
    <p class="text-sm text-emerald-700">Paid <?= !empty($invoice['paid_at']) ? 'on ' . htmlspecialchars($invoice['paid_at']) : '' ?>
        <?php if (!empty($invoice['pdf_path'])): ?>
        · <a href="<?= htmlspecialchars($invoice['pdf_path']) ?>" target="_blank" class="underline">Download PDF</a>
        <?php endif; ?>
    </p>
    <?php endif; ?>
</div>

<script>
function billingEditor(invoiceId) {
    return {
        qrData: '',
        payStatus: '',
        async createUpi() {
            const r = await fetch('/api/v1/billing/' + invoiceId + '/razorpay-order', { headers: { Accept: 'application/json' } });
            const d = await r.json();
            this.qrData = d.qr_data || d.message || JSON.stringify(d);
        },
        async checkPayment() {
            const r = await fetch('/api/v1/billing/' + invoiceId + '/check-payment');
            const d = await r.json();
            this.payStatus = d.paid ? 'Payment received!' : 'Not paid yet';
            if (d.paid) location.reload();
        },
        async simulatePay() {
            await fetch('/api/v1/billing/' + invoiceId + '/simulate-pay', { method: 'POST' });
            location.reload();
        },
    };
}
</script>
