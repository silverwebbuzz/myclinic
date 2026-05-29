<div class="space-y-4">
    <div class="flex flex-wrap gap-2 items-center">
        <h2 class="text-lg font-semibold flex-1">Pharmacy inventory</h2>
        <a href="/pharmacy/pos" class="ui-btn ui-btn-secondary ui-btn-sm">POS</a>
    </div>
    <?php if (!empty($_GET['added'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Batch added.</p>
    <?php endif; ?>

    <?php if ($lowStock !== [] || $expiring !== []): ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <?php if ($lowStock !== []): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
            <h3 class="font-medium text-amber-900">Low stock</h3>
            <ul class="mt-2 space-y-1">
                <?php foreach (array_slice($lowStock, 0, 5) as $item): ?>
                <li><?= htmlspecialchars($item['drug_name'] ?? '') ?> — <?= (int) ($item['quantity'] ?? 0) ?> left</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if ($expiring !== []): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm">
            <h3 class="font-medium text-red-900">Expiring soon</h3>
            <ul class="mt-2 space-y-1">
                <?php foreach (array_slice($expiring, 0, 5) as $item): ?>
                <li><?= htmlspecialchars($item['drug_name'] ?? '') ?> — <?= htmlspecialchars($item['expiry_date'] ?? '') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="post" action="/pharmacy/inventory" class="ui-card p-4 grid gap-3 sm:grid-cols-2 text-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="sm:col-span-2"><h3 class="font-medium">Add batch</h3></div>
        <div>
            <label class="text-xs font-medium">Drug</label>
            <select name="drug_id" required class="mt-1 w-full rounded-lg border px-3 py-2">
                <?php foreach ($drugs as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="text-xs">Qty</label><input name="quantity" type="number" min="1" value="100" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
        <div><label class="text-xs">Expiry</label><input name="expiry_date" type="date" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
        <div><label class="text-xs">Selling price</label><input name="selling_price" type="number" step="0.01" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
        <div><label class="text-xs">Low stock threshold</label><input name="low_stock_threshold" type="number" value="10" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
        <div class="sm:col-span-2"><button type="submit" class="ui-btn ui-btn-primary">Add batch</button></div>
    </form>

    <div class="overflow-hidden ui-card">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                <th class="px-4 py-3 text-left">Drug</th><th class="px-4 py-3">Batch</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">Expiry</th>
            </tr></thead>
            <tbody class="divide-y">
                <?php foreach ($batches as $b): ?>
                <tr>
                    <td class="px-4 py-3"><?= htmlspecialchars($b['drug_name'] ?? '') ?></td>
                    <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($b['batch_number'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= (int) $b['quantity'] ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($b['expiry_date'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
