<?php
/**
 * /admin/store/hsn — HSN master: the GST rate for each HSN code. Sellers pick the code; the rate follows.
 *
 * @var list<array<string,mixed>> $rows
 * @var list<array<string,mixed>> $mismatches
 * @var bool $tableMissing
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rates = \App\Services\Store\CatalogService::GST_RATES_BP;
$in = 'mt-1 w-full rounded border px-2 py-1';
$form = static function (?array $r) use ($e, $rates, $in, $csrf): string {
    $alt = $r !== null && !empty($r['alt_rates']) ? array_map('intval', explode(',', (string) $r['alt_rates'])) : [];
    ob_start(); ?>
    <form method="post" action="/admin/store/hsn" class="grid gap-2 text-sm md:grid-cols-12 md:items-end">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>">
        <label class="md:col-span-1"><span class="text-xs text-slate-500">HSN</span>
            <input name="code" required maxlength="8" inputmode="numeric" value="<?= $e($r['code'] ?? '') ?>" class="<?= $in ?> font-mono"></label>
        <label class="md:col-span-4"><span class="text-xs text-slate-500">Description</span>
            <input name="description" required maxlength="300" value="<?= $e($r['description'] ?? '') ?>" class="<?= $in ?>"></label>
        <label class="md:col-span-1"><span class="text-xs text-slate-500">GST</span>
            <select name="gst_bp" class="<?= $in ?>">
                <?php foreach ($rates as $bp => $label): ?><option value="<?= (int) $bp ?>" <?= (int) ($r['gst_bp'] ?? 1800) === (int) $bp ? 'selected' : '' ?>><?= (int) $bp / 100 ?>%</option><?php endforeach; ?>
            </select></label>
        <fieldset class="md:col-span-2"><legend class="text-xs text-slate-500">Rate varies? Allowed rates</legend>
            <div class="mt-1 flex flex-wrap gap-2">
                <?php foreach ($rates as $bp => $label): ?>
                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="alt_rates[]" value="<?= (int) $bp ?>" <?= in_array((int) $bp, $alt, true) ? 'checked' : '' ?>><?= (int) $bp / 100 ?>%</label>
                <?php endforeach; ?>
            </div></fieldset>
        <label class="md:col-span-2"><span class="text-xs text-slate-500">Notes (admin only)</span>
            <input name="notes" maxlength="300" value="<?= $e($r['notes'] ?? '') ?>" class="<?= $in ?>"></label>
        <div class="flex items-center gap-2 md:col-span-2">
            <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" <?= $r === null || !empty($r['is_active']) ? 'checked' : '' ?>> Active</label>
            <button class="ml-auto rounded bg-slate-800 px-3 py-1 text-xs text-white hover:bg-slate-700"
                <?= $r !== null ? 'onclick="return confirm(\'Save? If the rate changed, every product with this HSN switches to the new rate for new orders.\')"' : '' ?>><?= $r === null ? 'Add' : 'Save' ?></button>
        </div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HSN codes &amp; GST — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-7xl space-y-5 p-6">
    <div>
        <h1 class="text-xl font-semibold">HSN codes &amp; GST</h1>
        <p class="text-sm text-slate-500">The GST rate for each HSN code. Sellers choose only the HSN code; the rate on their product (and on the customer's invoice) comes from here.
            Codes match by prefix, so <code>3004</code> also covers <code>30049011</code>. Tick "rate varies" rates only for codes whose rate depends on the exact product; the seller then picks one and you check it at product review.</p>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php if ($tableMissing): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">Import <code>2026_10_02_store_hsn_codes.sql</code> to enable the HSN list. Until then sellers still choose the rate themselves.</div>
    <?php else: ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900"><strong>Get these confirmed by your CA.</strong> The starting list is a draft. A wrong rate means wrong invoices for every product using that code.</div>
    <?php endif; ?>

    <?php if ($mismatches): ?>
    <section class="rounded-xl border border-red-200 bg-white shadow-sm">
        <h2 class="border-b px-4 py-3 font-semibold text-red-700"><?= count($mismatches) ?> product(s) need fixing</h2>
        <div class="overflow-x-auto">
        <table data-filter="Seller,Problem" class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500"><tr><th class="px-4 py-2">Product</th><th class="px-4 py-2">Seller</th><th class="px-4 py-2">HSN</th><th class="px-4 py-2">Problem</th></tr></thead>
            <tbody>
            <?php foreach ($mismatches as $m): ?>
                <tr class="border-t"><td class="px-4 py-2"><a class="text-sky-700 hover:underline" href="/admin/store/products/<?= (int) $m['id'] ?>"><?= $e($m['name']) ?></a> <span class="text-xs text-slate-400"><?= $e($m['status']) ?></span></td>
                    <td class="px-4 py-2"><?= $e($m['vendor_name']) ?></td><td class="px-4 py-2 font-mono"><?= $e($m['hsn_code'] ?? '—') ?></td><td class="px-4 py-2 text-red-700"><?= $e($m['problem']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="border-t px-4 py-2 text-xs text-slate-500">Either add the missing HSN code below, or ask the seller to edit the product (they see the same list on their Products page).</p>
    </section>
    <?php endif; ?>

    <?php if (!$tableMissing): ?>
    <form method="post" action="/admin/store/hsn" class="flex flex-wrap items-center gap-3 rounded-xl border bg-white p-4 text-sm shadow-sm"
          onsubmit="return confirm('Set every product to its HSN code\'s rate (single-rate codes only)? New orders will use it.')">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="apply">
        <span class="flex-1 text-slate-600">After your CA confirms the list, apply the rates to existing products once. (Saving a code does this automatically for that code.)</span>
        <button class="rounded border px-3 py-1.5 hover:bg-slate-50">Apply rates to all products</button>
    </form>

    <section class="rounded-xl border bg-white p-4 shadow-sm">
        <h2 class="mb-2 font-semibold">Add an HSN code</h2>
        <?= $form(null) ?>
    </section>

    <section class="space-y-2">
        <?php foreach ($rows as $r): ?>
            <div class="rounded-xl border bg-white p-3 shadow-sm <?= empty($r['is_active']) ? 'opacity-60' : '' ?>"><?= $form($r) ?></div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
