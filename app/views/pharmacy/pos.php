<div class="space-y-4" x-data="{ cart: [{ drug_id: '', qty: 1 }] }">
    <div class="flex flex-wrap gap-2">
        <h2 class="text-lg font-semibold flex-1">Pharmacy POS</h2>
        <a href="/pharmacy/inventory" class="ui-btn ui-btn-secondary ui-btn-sm">Inventory</a>
        <a href="/pharmacy/narcotic" class="ui-btn ui-btn-secondary ui-btn-sm">Narcotic register</a>
    </div>
    <?php if (!empty($_GET['sale'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Sale complete — total ₹<?= htmlspecialchars($_GET['total'] ?? '0') ?></p>
    <?php endif; ?>
    <form method="post" action="/pharmacy/pos/checkout" class="ui-card p-4 space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <template x-for="(line, idx) in cart" :key="idx">
            <div class="grid gap-2 sm:grid-cols-3">
                <select :name="'drug_id[]'" x-model="line.drug_id" class="ui-input sm:col-span-2">
                    <option value="">Select drug…</option>
                    <?php foreach ($stock as $s): ?>
                    <option value="<?= (int) $s['drug_id'] ?>"><?= htmlspecialchars($s['drug_name'] ?? '') ?> — batch <?= htmlspecialchars($s['batch_number'] ?? '') ?> (<?= (int) $s['quantity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="number" :name="'qty[]'" x-model="line.qty" min="1" class="ui-input">
            </div>
        </template>
        <button type="button" @click="cart.push({ drug_id: '', qty: 1 })" class="text-sm text-emerald-600">+ Add line</button>
        <div class="flex flex-wrap gap-3 items-center">
            <select name="payment_mode" class="ui-input">
                <option value="cash">Cash</option>
                <option value="upi">UPI</option>
                <option value="card">Card</option>
            </select>
            <button type="submit" class="ui-btn ui-btn-primary">Checkout (FIFO)</button>
        </div>
    </form>
</div>
