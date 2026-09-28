<?php
/**
 * /admin/store/reports — monthly marketplace money summary.
 *
 * @var list<array<string,mixed>> $rows
 */
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
$tot = ['orders' => 0, 'collected' => 0, 'shipping' => 0, 'refunded' => 0, 'commission' => 0, 'commission_gst' => 0, 'paid_out' => 0];
foreach ($rows as $row) {
    foreach ($tot as $k => $_) {
        $tot[$k] += (int) $row[$k];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store reports — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">Store money, by month</h1>
    <p class="text-sm text-slate-500">
        <strong>Collected</strong> = paid orders (incl. shipping, GST-inclusive).
        <strong>Commission</strong> is booked when packages are delivered.
        <strong>Paid out</strong> = seller transfers marked paid. Share this with your CA for GST / TCS filings.
    </p>
    <div class="grid gap-3 sm:grid-cols-4">
        <?php foreach (['collected' => 'Collected (12 mo)', 'commission' => 'Commission earned', 'refunded' => 'Refunded', 'paid_out' => 'Paid to sellers'] as $k => $label): ?>
            <div class="rounded-xl border bg-white p-4 shadow-sm"><div class="text-xs uppercase text-slate-500"><?= $e($label) ?></div><div class="mt-1 text-xl font-semibold"><?= $r($tot[$k]) ?></div></div>
        <?php endforeach; ?>
    </div>
    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-4 py-2">Month</th><th class="px-4 py-2 text-right">Orders</th><th class="px-4 py-2 text-right">Collected</th><th class="px-4 py-2 text-right">Shipping charged</th><th class="px-4 py-2 text-right">Refunded</th><th class="px-4 py-2 text-right">Commission</th><th class="px-4 py-2 text-right">GST on commission</th><th class="px-4 py-2 text-right">Paid to sellers</th></tr>
            </thead>
            <tbody class="divide-y">
            <?php if (!$rows): ?><tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No store activity yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr><td class="px-4 py-2 font-medium"><?= $e($row['month']) ?></td>
                    <td class="px-4 py-2 text-right"><?= (int) $row['orders'] ?></td>
                    <td class="px-4 py-2 text-right"><?= $r($row['collected']) ?></td>
                    <td class="px-4 py-2 text-right"><?= $r($row['shipping']) ?></td>
                    <td class="px-4 py-2 text-right text-red-600"><?= $r($row['refunded']) ?></td>
                    <td class="px-4 py-2 text-right text-emerald-700"><?= $r($row['commission']) ?></td>
                    <td class="px-4 py-2 text-right"><?= $r($row['commission_gst']) ?></td>
                    <td class="px-4 py-2 text-right"><?= $r($row['paid_out']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
