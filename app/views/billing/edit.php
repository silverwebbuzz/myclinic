<?php
$items = $invoice['items'] ?? [];
$patient = $invoice['patient'] ?? [];
$amountPaid = (float) ($invoice['amount_paid'] ?? 0);
$advancePaid = (float) ($invoice['advance_paid'] ?? 0);
$due = max(0, round((float) $invoice['total'] - $advancePaid - $amountPaid, 2));
$status = (string) ($invoice['status'] ?? 'draft');
// The form posts discount_percent but only the amount is stored — derive it
// back so re-saving doesn't silently wipe an existing discount.
$subtotal = (float) ($invoice['subtotal'] ?? 0);
$discountPct = $subtotal > 0 ? round((float) ($invoice['discount_amount'] ?? 0) / $subtotal * 100, 1) : 0;
$editable = !in_array($status, ['paid', 'refunded'], true);
?>
<div class="mx-auto max-w-3xl space-y-4" x-data="billingEditor(<?= (int) $invoice['id'] ?>)">
    <?php if (!empty($message)): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
        <?= $message === 'paid' ? 'Payment recorded — invoice fully paid.' : ($message === 'partial' ? 'Partial payment recorded.' : 'Invoice saved.') ?>
    </p>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars((string) $_GET['error']) ?></p>
    <?php endif; ?>

    <div class="ui-card ui-card-pad">
        <div class="flex flex-wrap justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 ui-section-title">
                    <?= htmlspecialchars($invoice['invoice_number']) ?>
                    <?= ui_badge(ucfirst($status), in_array($status, ['paid'], true) ? 'success' : ($status === 'partial' ? 'warning' : 'neutral')) ?>
                </h2>
                <p class="text-sm text-slate-500"><?= htmlspecialchars($patient['patient_name'] ?? $patient['name'] ?? '') ?> · <?= htmlspecialchars($patient['uhid'] ?? '') ?></p>
                <?php if ((float) ($patient['advance_balance'] ?? 0) > 0): ?>
                <p class="mt-1 text-xs text-emerald-700">Advance balance: ₹<?= number_format((float) $patient['advance_balance'], 2) ?></p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold">₹<span x-text="liveTotal"><?= number_format((float) $invoice['total'], 2) ?></span></p>
                <?php if ($amountPaid > 0 || $advancePaid > 0): ?>
                <p class="text-xs text-slate-500">
                    Paid ₹<?= number_format($amountPaid + $advancePaid, 2) ?>
                    <?php if ($due > 0): ?> · <span class="font-medium text-amber-700">Due ₹<?= number_format($due, 2) ?></span><?php endif; ?>
                </p>
                <?php endif; ?>
                <a href="/billing/<?= (int) $invoice['id'] ?>/pdf"
                   class="ui-btn ui-btn-secondary ui-btn-sm mt-2">
                    <?= ui_icon('emr', 14) ?><span>Download PDF</span>
                </a>
            </div>
        </div>

        <form method="post" action="/billing/<?= (int) $invoice['id'] ?>" class="mt-6 space-y-4" id="invoice-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div class="flex items-center justify-between">
                <h3 class="font-medium">Line items</h3>
                <?php if ($editable): ?>
                <button type="button" @click="addRow()" class="text-xs font-medium text-brand hover:underline">+ Add item</button>
                <?php endif; ?>
            </div>
            <div id="line-items" class="space-y-2" @input="recalc()">
                <?php foreach ($items === [] ? [['description' => '', 'qty' => 1, 'unit_price' => '']] : $items as $item): ?>
                <div class="grid grid-cols-12 gap-2">
                    <input name="item_description[]" value="<?= htmlspecialchars((string) ($item['description'] ?? '')) ?>"
                           <?= $editable ? '' : 'readonly' ?>
                           class="col-span-6 rounded border px-2 py-1 text-sm" placeholder="Description">
                    <input name="item_qty[]" type="number" min="1" value="<?= (int) ($item['qty'] ?? 1) ?>"
                           <?= $editable ? '' : 'readonly' ?>
                           class="col-span-2 rounded border px-2 py-1 text-sm line-qty">
                    <input name="item_price[]" type="number" step="0.01" value="<?= htmlspecialchars((string) ($item['unit_price'] ?? '')) ?>"
                           <?= $editable ? '' : 'readonly' ?>
                           class="col-span-3 rounded border px-2 py-1 text-sm line-price" placeholder="0.00">
                    <?php if ($editable): ?>
                    <button type="button" @click="removeRow($event)" class="col-span-1 text-rose-500 hover:text-rose-700" title="Remove line">×</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid gap-3 sm:grid-cols-3" @input="recalc()">
                <label class="text-sm">Discount %
                    <input name="discount_percent" type="number" step="0.1" min="0" max="100"
                           value="<?= htmlspecialchars((string) $discountPct) ?>" <?= $editable ? '' : 'readonly' ?>
                           class="ui-input" id="discount-pct">
                </label>
                <label class="text-sm">Tax % (<?= htmlspecialchars($invoice['tax_label'] ?? 'GST') ?>)
                    <input name="tax_percent" type="number" step="0.01" min="0"
                           value="<?= htmlspecialchars((string) ($invoice['tax_percent'] ?? $taxPercent)) ?>" <?= $editable ? '' : 'readonly' ?>
                           class="ui-input" id="tax-pct">
                </label>
                <label class="text-sm flex items-end gap-2 pb-2">
                    <input class="ui-checkbox" type="checkbox" name="apply_advance" value="1"
                           <?= (float) ($patient['advance_balance'] ?? 0) > 0 && $editable ? '' : 'disabled' ?>>
                    Apply patient advance
                </label>
            </div>

            <label class="block text-sm">Notes
                <textarea name="notes" rows="2" <?= $editable ? '' : 'readonly' ?> class="ui-input"><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
            </label>

            <?php if ($editable): ?>
            <button type="submit" class="ui-btn ui-btn-secondary">Save invoice</button>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($due > 0): ?>
    <div class="ui-card ui-card-pad space-y-4">
        <h3 class="font-semibold">Collect payment · <span class="text-amber-700">Due ₹<?= number_format($due, 2) ?></span></h3>

        <form method="post" action="/billing/<?= (int) $invoice['id'] ?>/payment" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label class="text-sm">Amount
                <input type="number" name="amount" step="0.01" min="0.01" max="<?= htmlspecialchars((string) $due) ?>"
                       value="<?= htmlspecialchars((string) $due) ?>" class="ui-input w-36">
                <span class="mt-0.5 block text-[11px] text-slate-400">Lower it to record a partial payment</span>
            </label>
            <label class="text-sm">Mode
                <select name="method" class="ui-input w-36">
                    <option value="cash">Cash</option>
                    <option value="upi">UPI</option>
                    <option value="card">Card</option>
                    <option value="insurance">Insurance</option>
                </select>
            </label>
            <button type="submit" class="ui-btn ui-btn-primary">Record payment</button>
        </form>

        <div class="border-t pt-4">
            <p class="text-sm font-medium">UPI (Razorpay)</p>
            <button type="button" @click="createUpi()" class="mt-2 ui-btn ui-btn-secondary">Generate UPI QR</button>
            <p x-show="qrData" class="mt-2 break-all text-xs text-slate-600" x-text="qrData"></p>
            <button type="button" @click="checkPayment()" class="mt-2 ui-btn ui-btn-primary">Check payment</button>
            <button type="button" @click="simulatePay()" class="mt-2 ml-2 rounded-lg border px-3 py-2 text-xs text-slate-600">Simulate pay (dev)</button>
            <p x-show="payStatus" class="mt-2 text-sm" x-text="payStatus"></p>
        </div>
    </div>
    <?php else: ?>
    <p class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        ✓ Paid in full<?= !empty($invoice['paid_at']) ? ' on ' . htmlspecialchars(date('d M Y, h:i A', strtotime((string) $invoice['paid_at']))) : '' ?>
        <?php if (!empty($invoice['payment_mode'])): ?> · <span class="uppercase"><?= htmlspecialchars((string) $invoice['payment_mode']) ?></span><?php endif; ?>
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
        liveTotal: '0.00',
        init() { this.recalc(); },
        // Live total: qty × price per row − discount % + tax %, mirroring
        // InvoiceService::recalculate so the doctor sees what will be saved.
        recalc() {
            const rows = document.querySelectorAll('#line-items .grid');
            let subtotal = 0;
            rows.forEach(r => {
                const qty = parseFloat(r.querySelector('.line-qty')?.value) || 0;
                const price = parseFloat(r.querySelector('.line-price')?.value) || 0;
                subtotal += qty * price;
            });
            const disc = subtotal * ((parseFloat(document.getElementById('discount-pct')?.value) || 0) / 100);
            const taxable = Math.max(0, subtotal - disc);
            const tax = taxable * ((parseFloat(document.getElementById('tax-pct')?.value) || 0) / 100);
            this.liveTotal = (taxable + tax).toFixed(2);
        },
        addRow() {
            const container = document.getElementById('line-items');
            const row = container.querySelector('.grid').cloneNode(true);
            row.querySelectorAll('input').forEach(i => { i.value = i.classList.contains('line-qty') ? '1' : ''; });
            container.appendChild(row);
        },
        removeRow(ev) {
            const container = document.getElementById('line-items');
            if (container.querySelectorAll('.grid').length > 1) {
                ev.target.closest('.grid').remove();
                this.recalc();
            }
        },
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
