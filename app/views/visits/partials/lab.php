<section x-show="activeTab === 'lab'" class="ui-card ui-card-pad space-y-4">
    <h3 class="font-semibold">Lab orders</h3>
    <form method="post" action="/lab/orders" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
        <input type="hidden" name="visit_id" value="<?= (int) $visit['id'] ?>">
        <div class="min-w-[200px] flex-1">
            <label class="text-xs font-medium">Test</label>
            <select name="test_id" required class="ui-input">
                <?php foreach ($labTests as $t): ?>
                <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['test_name']) ?> (<?= htmlspecialchars($t['test_code'] ?? '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="ui-btn ui-btn-primary">Order test</button>
    </form>
    <?php if ($labOrders === []): ?>
    <p class="text-sm text-slate-500">No lab orders for this visit.</p>
    <?php else: ?>
    <ul class="divide-y text-sm">
        <?php foreach ($labOrders as $lo): ?>
        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
            <span><?= htmlspecialchars($lo['test_name'] ?? '') ?> · <code><?= htmlspecialchars($lo['barcode'] ?? '') ?></code></span>
            <span class="capitalize text-xs text-slate-500"><?= htmlspecialchars($lo['status'] ?? '') ?></span>
            <a href="/lab/orders/<?= (int) $lo['id'] ?>" class="text-emerald-600 text-xs hover:underline">Open →</a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
