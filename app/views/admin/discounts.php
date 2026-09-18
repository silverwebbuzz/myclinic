<?php
/** /admin/discounts — subscription discount codes (super-admin). */
$fmtDate = static fn ($d): string => $d ? date('d M Y', strtotime((string) $d)) : '—';
$today = date('Y-m-d');
$statusOf = static function (array $c) use ($today): array {
    if (empty($c['is_active'])) {
        return ['Inactive', 'bg-slate-200 text-slate-600'];
    }
    if (!empty($c['valid_until']) && $today > $c['valid_until']) {
        return ['Expired', 'bg-rose-100 text-rose-700'];
    }
    if (!empty($c['valid_from']) && $today < $c['valid_from']) {
        return ['Scheduled', 'bg-amber-100 text-amber-700'];
    }
    if ($c['max_uses'] !== null && (int) $c['used_count'] >= (int) $c['max_uses']) {
        return ['Used up', 'bg-slate-200 text-slate-600'];
    }
    return ['Active', 'bg-emerald-100 text-emerald-700'];
};
$planPrice = \App\Services\BillingGatewayService::monthlyPriceInr();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Plan Discounts — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php /* Alpine is loaded globally by admin/_nav.php */ ?>
</head>
<body class="min-h-screen bg-slate-100" x-data="{ editing: null, type: 'percent' }">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Plan Discounts</h1>
            <span class="text-sm text-slate-500"><?= count($codes) ?> code<?= count($codes) === 1 ? '' : 's' ?></span>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $message))) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            The discount tables couldn't be created automatically. Run
            <code>app/database/patches/2026_09_18_plan_discount_codes.sql</code>.
        </div>
        <?php endif; ?>

        <p class="text-sm text-slate-500">
            Codes doctors can enter on the sign-up <strong>Checkout</strong> page before paying.
            The discount comes off the plan price (currently ₹<?= number_format($planPrice, 2) ?>/month) <strong>before GST</strong>.
            Each clinic can use a code once, and a code only counts as used after the payment succeeds.
        </p>

        <!-- ===== Add / Edit form ===== -->
        <form method="post" action="/admin/discounts" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="id" :value="editing ? editing.id : ''">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold" x-text="editing ? ('Edit: ' + editing.code) : 'Add a discount code'"></h2>
                <button type="button" x-show="editing" @click="editing = null; type = 'percent'"
                        class="text-xs text-slate-500 hover:underline">Cancel edit</button>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-slate-600">Code <span class="text-slate-400">(letters, numbers, - _)</span></span>
                    <input type="text" name="code" required maxlength="40" :value="editing ? editing.code : ''"
                           placeholder="WELCOME20"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm font-mono uppercase">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Discount type</span>
                    <select name="discount_type" x-model="type" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                        <option value="percent">Percentage (%)</option>
                        <option value="flat">Flat amount (₹)</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600" x-text="type === 'flat' ? 'Amount off (₹)' : 'Percent off (1–100)'"></span>
                    <input type="number" name="discount_value" required step="0.01" min="0.01" :max="type === 'percent' ? 100 : null"
                           :value="editing ? editing.discount_value : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>

                <label class="block text-sm">
                    <span class="text-slate-600">Usage limit <span class="text-slate-400">(blank = unlimited)</span></span>
                    <input type="number" name="max_uses" min="1" :value="editing ? (editing.max_uses ?? '') : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Valid from <span class="text-slate-400">(optional)</span></span>
                    <input type="date" name="valid_from" :value="editing ? (editing.valid_from ?? '') : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Valid until <span class="text-slate-400">(optional)</span></span>
                    <input type="date" name="valid_until" :value="editing ? (editing.valid_until ?? '') : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
            </div>

            <label class="block text-sm">
                <span class="text-slate-600">Description <span class="text-slate-400">(internal note, optional)</span></span>
                <input type="text" name="description" maxlength="190" :value="editing ? (editing.description ?? '') : ''"
                       placeholder="Launch offer for Ahmedabad doctors"
                       class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
            </label>

            <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" :checked="editing ? !!editing.is_active : true" value="1">
                    <span class="text-slate-600">Active</span>
                </label>
                <button type="submit"
                        class="ml-auto rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    <span x-text="editing ? 'Save changes' : 'Add code'"></span>
                </button>
            </div>
        </form>

        <!-- ===== Existing codes ===== -->
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Code</th>
                        <th class="px-4 py-2">Discount</th>
                        <th class="px-4 py-2">Customer pays</th>
                        <th class="px-4 py-2">Used</th>
                        <th class="px-4 py-2">Valid</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                <?php foreach ($codes as $c): ?>
                    <?php
                    [$statusText, $statusClass] = $statusOf($c);
                    $net = $planPrice - \App\Services\DiscountService::amountOff($c, $planPrice);
                    $row = [
                        'id' => (int) $c['id'],
                        'code' => $c['code'],
                        'description' => $c['description'] ?? '',
                        'discount_type' => $c['discount_type'],
                        'discount_value' => (float) $c['discount_value'],
                        'max_uses' => $c['max_uses'] !== null ? (int) $c['max_uses'] : null,
                        'valid_from' => $c['valid_from'],
                        'valid_until' => $c['valid_until'],
                        'is_active' => (int) $c['is_active'],
                    ];
                    ?>
                    <tr>
                        <td class="px-4 py-2">
                            <div class="font-mono font-semibold"><?= htmlspecialchars((string) $c['code']) ?></div>
                            <?php if (!empty($c['description'])): ?>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars((string) $c['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2"><?= htmlspecialchars(\App\Services\DiscountService::label($c)) ?></td>
                        <td class="px-4 py-2 text-slate-600">₹<?= number_format($net, 2) ?> <span class="text-xs text-slate-400">+ GST</span></td>
                        <td class="px-4 py-2"><?= (int) $c['used_count'] ?><?= $c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : '' ?></td>
                        <td class="px-4 py-2 text-xs text-slate-600">
                            <?= $c['valid_from'] || $c['valid_until']
                                ? htmlspecialchars($fmtDate($c['valid_from']) . ' → ' . $fmtDate($c['valid_until']))
                                : 'Always' ?>
                        </td>
                        <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button"
                                        @click='editing = <?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>; type = editing.discount_type; window.scrollTo({top:0,behavior:"smooth"})'
                                        class="text-xs text-indigo-600 hover:underline">Edit</button>
                                <form method="post" action="/admin/discounts/<?= (int) $c['id'] ?>/toggle" class="inline">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <button class="text-xs text-slate-500 hover:underline"><?= !empty($c['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <?php if ((int) $c['used_count'] === 0): ?>
                                <form method="post" action="/admin/discounts/<?= (int) $c['id'] ?>/delete" class="inline"
                                      onsubmit="return confirm('Delete code <?= htmlspecialchars((string) $c['code'], ENT_QUOTES) ?>?');">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($codes === []): ?>
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No discount codes yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
