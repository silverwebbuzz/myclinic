<?php
$statusStyles = [
    'draft' => 'bg-slate-100 text-slate-600',
    'sent' => 'bg-blue-100 text-blue-800',
    'partial' => 'bg-amber-100 text-amber-800',
    'paid' => 'bg-emerald-100 text-emerald-800',
    'overdue' => 'bg-rose-100 text-rose-700',
    'refunded' => 'bg-slate-200 text-slate-500',
];
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Billing <span class="ml-1 text-sm font-normal text-slate-500">(<?= (int) ($total ?? 0) ?>)</span></h2>
        <div class="flex flex-wrap gap-2">
            <a href="/billing/export/excel" class="ui-btn ui-btn-secondary ui-btn-sm">Export Excel</a>
            <a href="/billing/export/tally" class="ui-btn ui-btn-secondary ui-btn-sm">Tally XML</a>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars((string) $_GET['error']) ?></p>
    <?php endif; ?>

    <form method="get" class="flex flex-wrap gap-2 ui-card p-4">
        <input type="search" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Search invoice, patient…" class="min-w-[200px] flex-1 ui-input">
        <select name="status" class="ui-input">
            <option value="">All statuses</option>
            <?php foreach (['draft','sent','partial','paid','overdue'] as $st): ?>
            <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="ui-btn ui-btn-primary">Filter</button>
        <a href="/billing" class="ui-btn ui-btn-secondary">Reset</a>
    </form>

    <div class="overflow-hidden ui-card">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Balance due</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Mode</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if ($invoices === []): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No invoices<?= !empty($filters['q']) || !empty($filters['status']) ? ' match these filters' : ' yet. Complete a visit to auto-create a draft' ?>.</td></tr>
                <?php else: ?>
                <?php foreach ($invoices as $inv):
                    $balance = max(0, round((float) $inv['total'] - (float) ($inv['advance_paid'] ?? 0) - (float) ($inv['amount_paid'] ?? 0), 2));
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs">
                        <a href="/billing/<?= (int) $inv['id'] ?>" class="text-brand hover:underline"><?= htmlspecialchars($inv['invoice_number']) ?></a>
                    </td>
                    <td class="px-4 py-3"><?= htmlspecialchars($inv['patient_name'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= number_format((float) $inv['total'], 2) ?> <?= htmlspecialchars($inv['currency'] ?? '') ?></td>
                    <td class="px-4 py-3 <?= $balance > 0 ? 'font-medium text-amber-700' : 'text-slate-400' ?>">
                        <?= $balance > 0 ? number_format($balance, 2) : '—' ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs capitalize <?= $statusStyles[$inv['status'] ?? ''] ?? 'bg-slate-100 text-slate-600' ?>">
                            <?= htmlspecialchars($inv['status'] ?? '') ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs uppercase text-slate-500"><?= htmlspecialchars($inv['payment_mode'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-xs"><?= htmlspecialchars(substr($inv['created_at'] ?? '', 0, 10)) ?></td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <a href="/billing/<?= (int) $inv['id'] ?>" class="font-medium text-brand hover:underline">Open</a>
                            <a href="/billing/<?= (int) $inv['id'] ?>/pdf" class="text-slate-400 hover:text-slate-700" title="Download PDF"><?= ui_icon('emr', 16) ?></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    // Windowed pager with Prev/Next — filters preserved.
    $totalPages = (int) ceil(max(1, (int) ($total ?? 0)) / max(1, (int) ($perPage ?? 20)));
    $page = (int) ($page ?? 1);
    if ($totalPages > 1):
        $pageUrl = static fn (int $p): string => '?' . http_build_query(array_merge(array_filter($filters), ['page' => $p]));
        $winStart = max(1, $page - 2);
        $winEnd = min($totalPages, $page + 2);
    ?>
    <div class="flex flex-wrap items-center justify-center gap-2 text-sm">
        <?php if ($page > 1): ?>
        <a href="<?= htmlspecialchars($pageUrl($page - 1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50">← Prev</a>
        <?php endif; ?>
        <?php if ($winStart > 1): ?>
        <a href="<?= htmlspecialchars($pageUrl(1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50">1</a>
        <?php if ($winStart > 2): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $winStart; $p <= $winEnd; $p++): ?>
        <a href="<?= htmlspecialchars($pageUrl($p)) ?>"
           class="rounded-lg px-3 py-1.5 font-medium <?= $p === $page ? 'bg-brand text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($winEnd < $totalPages): ?>
        <?php if ($winEnd < $totalPages - 1): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
        <a href="<?= htmlspecialchars($pageUrl($totalPages)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50"><?= $totalPages ?></a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
        <a href="<?= htmlspecialchars($pageUrl($page + 1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
