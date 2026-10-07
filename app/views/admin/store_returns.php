<?php
/** @var list<array<string,mixed>> $rows @var string $status */
use App\Services\Store\ReturnService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store returns — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">Store returns</h1>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php if (!empty($tableMissing)): ?><div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm">Import <code>2026_09_30_store_fulfilment.sql</code>.</div><?php endif; ?>
    <nav class="flex flex-wrap gap-2 text-sm">
        <?php foreach (['open' => 'Open', 'requested' => 'Awaiting decision', 'qc_failed' => 'QC failed', 'refunded' => 'Refunded', 'rejected' => 'Rejected', '' => 'All'] as $k => $l): ?>
            <a href="?status=<?= $k ?>" class="rounded-full px-3 py-1 <?= $status === $k ? 'bg-slate-800 text-white' : 'bg-white' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Return</th><th class="px-4 py-2">Seller</th><th class="px-4 py-2">Reason</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Requested</th></tr></thead>
            <tbody class="divide-y">
            <?php if (!$rows): ?><tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No returns.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $late = $r['status'] === 'requested' && strtotime((string) $r['created_at']) < time() - 2 * 86400; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2"><a href="/admin/store/returns/<?= (int) $r['id'] ?>" class="font-mono text-sky-700 hover:underline"><?= $e($r['return_no']) ?></a><div class="text-xs text-slate-500"><?= $e($r['order_no']) ?></div></td>
                    <td class="px-4 py-2"><?= $e($r['vendor_name']) ?></td>
                    <td class="px-4 py-2"><?= $e(ReturnService::REASONS[$r['reason_code']] ?? $r['reason_code']) ?></td>
                    <td class="px-4 py-2"><?= $e(str_replace('_', ' ', (string) $r['status'])) ?><?= $late ? ' <span class="text-xs font-semibold text-red-600">seller late: decide</span>' : '' ?></td>
                    <td class="px-4 py-2 text-slate-500"><?= $e(\App\Support\IndianDate::dateTime($r['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
