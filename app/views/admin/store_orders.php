<?php
/**
 * /admin/store/orders
 *
 * @var list<array<string,mixed>> $rows
 * @var array<string,int> $counts
 * @var string $status
 * @var string $q
 */
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tabs = ['' => 'All', 'pending_payment' => 'Awaiting payment', 'paid' => 'Paid', 'shipped' => 'Shipped', 'delivered' => 'Delivered',
    'cancelled' => 'Cancelled', 'expired' => 'Expired'];
$badge = ['pending_payment' => 'bg-amber-100 text-amber-800', 'paid' => 'bg-sky-100 text-sky-800', 'delivered' => 'bg-emerald-100 text-emerald-800',
    'cancelled' => 'bg-slate-200 text-slate-600', 'expired' => 'bg-slate-200 text-slate-600', 'payment_failed' => 'bg-red-100 text-red-700'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store orders — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Store orders</h1>
        <form method="get" class="flex gap-2">
            <input type="hidden" name="status" value="<?= $e($status) ?>">
            <input name="q" value="<?= $e($q) ?>" placeholder="Order no, name or phone" class="rounded border px-3 py-1.5 text-sm">
            <button class="rounded bg-slate-800 px-3 py-1.5 text-sm text-white">Search</button>
        </form>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php if (!empty($tableMissing)): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Order tables not found. Import <code>2026_09_29_store_orders.sql</code>.</div>
    <?php endif; ?>
    <nav class="flex flex-wrap gap-2 text-sm">
        <?php foreach ($tabs as $key => $label): ?>
            <?php $n = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0); ?>
            <a href="?status=<?= $e($key) ?>" class="rounded-full px-3 py-1 <?= $status === $key ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' ?>"><?= $e($label) ?> <span class="opacity-70">(<?= (int) $n ?>)</span></a>
        <?php endforeach; ?>
    </nav>
    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-4 py-2">Order</th><th class="px-4 py-2">Customer</th><th class="px-4 py-2 text-center">Sellers / items</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Placed</th></tr>
            </thead>
            <tbody class="divide-y">
            <?php if (!$rows): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No orders yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $o): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2"><a href="/admin/store/orders/<?= (int) $o['id'] ?>" class="font-mono font-medium text-sky-700 hover:underline"><?= $e($o['order_no']) ?></a></td>
                    <td class="px-4 py-2"><?= $e($o['contact_name']) ?><div class="text-xs text-slate-500"><?= $e($o['contact_phone']) ?></div></td>
                    <td class="px-4 py-2 text-center"><?= (int) $o['seller_count'] ?> / <?= (int) $o['item_count'] ?></td>
                    <td class="px-4 py-2 text-right">₹<?= $e(ProductService::rupees((int) $o['grand_total_paise'])) ?></td>
                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $badge[$o['status']] ?? 'bg-slate-100' ?>"><?= $e(str_replace('_', ' ', (string) $o['status'])) ?></span></td>
                    <td class="px-4 py-2 text-slate-500"><?= $e(substr((string) $o['placed_at'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
