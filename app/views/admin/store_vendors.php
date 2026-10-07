<?php
/**
 * /admin/store/vendors — seller review queue + directory.
 *
 * @var list<array<string,mixed>> $rows
 * @var array<string,int> $counts
 * @var string $status
 * @var string $q
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tabs = [
    'pending_review' => 'Awaiting review',
    'approved' => 'Approved',
    'draft' => 'Setting up',
    'rejected' => 'Changes requested',
    'suspended' => 'Suspended',
    'closed' => 'Closed',
    '' => 'All',
];
$badge = [
    'draft' => 'bg-slate-100 text-slate-700',
    'pending_review' => 'bg-amber-100 text-amber-800',
    'approved' => 'bg-emerald-100 text-emerald-800',
    'rejected' => 'bg-red-100 text-red-700',
    'suspended' => 'bg-red-100 text-red-700',
    'closed' => 'bg-slate-200 text-slate-500',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store sellers — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Store sellers</h1>
        <form method="get" class="flex gap-2">
            <input type="hidden" name="status" value="<?= $e($status) ?>">
            <input name="q" value="<?= $e($q) ?>" placeholder="Name, email or GSTIN" class="rounded border px-3 py-1.5 text-sm">
            <button class="rounded bg-slate-800 px-3 py-1.5 text-sm text-white">Search</button>
        </form>
    </div>

    <?php require __DIR__ . '/_store_flash.php'; ?>

    <?php if (!empty($tableMissing)): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Store tables not found. Import <code>app/database/patches/2026_09_28_store_foundation.sql</code> and <code>2026_09_28_store_vendors.sql</code> first.
        </div>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2 text-sm">
        <?php foreach ($tabs as $key => $label): ?>
            <?php $n = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0); ?>
            <a href="?status=<?= $e($key) ?>" class="rounded-full px-3 py-1 <?= $status === $key ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' ?>">
                <?= $e($label) ?> <span class="opacity-70">(<?= (int) $n ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-2">Seller</th>
                <th class="px-4 py-2">Contact</th>
                <th class="px-4 py-2">GSTIN</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Submitted</th>
                <th class="px-4 py-2 text-center">Docs to review</th>
            </tr>
            </thead>
            <tbody class="divide-y">
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No sellers here.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $v): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2">
                        <a href="/admin/store/vendors/<?= (int) $v['id'] ?>" class="font-medium text-sky-700 hover:underline"><?= $e($v['display_name']) ?></a>
                        <?php if (!empty($v['is_featured'])): ?><span class="ml-1 text-xs text-amber-600">★</span><?php endif; ?>
                        <div class="text-xs text-slate-500"><?= $e($v['legal_name'] ?? '') ?></div>
                    </td>
                    <td class="px-4 py-2 text-slate-600"><?= $e($v['contact_name']) ?><div class="text-xs text-slate-400"><?= $e($v['email']) ?> · <?= $e($v['phone']) ?></div></td>
                    <td class="px-4 py-2 font-mono text-xs"><?= $e($v['gstin'] ?? '—') ?></td>
                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $badge[$v['status']] ?? '' ?>"><?= $e(str_replace('_', ' ', (string) $v['status'])) ?></span></td>
                    <td class="px-4 py-2 text-slate-500"><?= $e(\App\Support\IndianDate::date($v['submitted_at'] ?? null, '—')) ?></td>
                    <td class="px-4 py-2 text-center"><?= (int) $v['pending_docs'] > 0 ? '<span class="rounded-full bg-amber-500 px-2 text-xs font-semibold text-white">' . (int) $v['pending_docs'] . '</span>' : '<span class="text-slate-300">0</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
