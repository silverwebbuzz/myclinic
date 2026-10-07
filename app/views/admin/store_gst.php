<?php
/**
 * /admin/store/gst — monthly GST register (seller invoices / credit notes, or eClinicPro delivery invoices).
 *
 * @var list<array<string,mixed>> $docs
 * @var list<array<string,mixed>> $hsn
 * @var array<string,int> $totals
 * @var string $month
 * @var ?int $vendorId
 * @var string $issuer
 * @var list<array<string,mixed>> $vendors
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . number_format((int) $p / 100, 2);
$reasons = ['return' => 'Return', 'cancel' => 'Cancelled', 'rto' => 'RTO', 'lost' => 'Lost', 'damaged' => 'Damaged'];
$qs = '?' . http_build_query(array_filter(['month' => $month, 'vendor' => $vendorId, 'issuer' => $issuer], static fn ($v) => $v !== null && $v !== ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GST register — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">GST register</h1>
            <p class="text-sm text-slate-500">Invoices are issued when a package is dispatched; credit notes when an invoiced item is refunded. Share the CSV with your CA.</p>
        </div>
        <a href="/admin/store/gst/export<?= $e($qs) ?>" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Download CSV</a>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php if (!empty($tableMissing)): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">Import <code>2026_10_01_store_tax_documents.sql</code> to enable GST documents.</div>
    <?php endif; ?>
    <?php if (!$platformReady): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">eClinicPro's GST details are missing, so <strong>delivery charges are not being invoiced</strong>. Add them in <a class="underline" href="/admin/store/settings">Store settings → Invoicing</a>.</div>
    <?php endif; ?>
    <?php if ($noHsn > 0): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800"><?= (int) $noHsn ?> live/pending product(s) have no HSN code. Their invoice lines will show "—" until the seller edits the product.</div>
    <?php endif; ?>

    <form method="get" class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4 text-sm shadow-sm">
        <label><span class="block text-slate-600">Month</span><input type="month" name="month" value="<?= $e($month) ?>" class="mt-1 rounded border px-2 py-1.5"></label>
        <label><span class="block text-slate-600">Issued by</span>
            <select name="issuer" class="mt-1 rounded border px-2 py-1.5">
                <option value="vendor" <?= $issuer === 'vendor' ? 'selected' : '' ?>>Sellers (goods)</option>
                <option value="platform" <?= $issuer === 'platform' ? 'selected' : '' ?>>eClinicPro (delivery charges)</option>
            </select></label>
        <label><span class="block text-slate-600">Seller</span>
            <select name="vendor" class="mt-1 rounded border px-2 py-1.5">
                <option value="">All sellers</option>
                <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $vendorId === (int) $v['id'] ? 'selected' : '' ?>><?= $e($v['display_name']) ?></option><?php endforeach; ?>
            </select></label>
        <button class="rounded border px-3 py-1.5 hover:bg-slate-50">Show</button>
    </form>

    <?php if ($totals): ?>
    <section class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Documents</p><p class="text-lg font-semibold"><?= (int) $totals['invoices'] ?> invoices · <?= (int) $totals['credit_notes'] ?> credit notes</p></div>
        <div class="rounded-xl border bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Net taxable value</p><p class="text-lg font-semibold"><?= $r($totals['taxable']) ?></p></div>
        <div class="rounded-xl border bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Net GST (CGST + SGST + IGST)</p><p class="text-lg font-semibold"><?= $r($totals['cgst'] + $totals['sgst'] + $totals['igst']) ?></p></div>
        <div class="rounded-xl border bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Net total</p><p class="text-lg font-semibold"><?= $r($totals['total']) ?></p></div>
    </section>
    <?php endif; ?>

    <section class="rounded-xl border bg-white shadow-sm">
        <h2 class="border-b px-4 py-3 font-semibold">Summary by HSN / rate <span class="text-xs font-normal text-slate-500">(credit notes subtracted)</span></h2>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500"><tr><th class="px-4 py-2">HSN/SAC</th><th class="px-4 py-2">GST %</th><th class="px-4 py-2 text-right">Qty</th><th class="px-4 py-2 text-right">Taxable</th><th class="px-4 py-2 text-right">CGST</th><th class="px-4 py-2 text-right">SGST</th><th class="px-4 py-2 text-right">IGST</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
            <tbody>
            <?php if (!$hsn): ?><tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">Nothing this month.</td></tr><?php endif; ?>
            <?php foreach ($hsn as $h): ?>
                <tr class="border-t"><td class="px-4 py-2"><?= $e($h['hsn_sac'] ?? '—') ?></td><td class="px-4 py-2"><?= (int) $h['gst_bp'] / 100 ?>%</td><td class="px-4 py-2 text-right"><?= (int) $h['qty'] ?></td>
                    <td class="px-4 py-2 text-right"><?= $r($h['taxable']) ?></td><td class="px-4 py-2 text-right"><?= $r($h['cgst']) ?></td><td class="px-4 py-2 text-right"><?= $r($h['sgst']) ?></td><td class="px-4 py-2 text-right"><?= $r($h['igst']) ?></td><td class="px-4 py-2 text-right"><?= $r($h['total']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="rounded-xl border bg-white shadow-sm">
        <h2 class="border-b px-4 py-3 font-semibold">Documents</h2>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500"><tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Number</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Seller</th><th class="px-4 py-2">Order</th><th class="px-4 py-2">Supply</th><th class="px-4 py-2 text-right">Taxable</th><th class="px-4 py-2 text-right">GST</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
            <tbody>
            <?php if (!$docs): ?><tr><td colspan="9" class="px-4 py-6 text-center text-slate-400">No documents this month.</td></tr><?php endif; ?>
            <?php foreach ($docs as $d): $cn = $d['doc_type'] === 'credit_note'; ?>
                <tr class="border-t">
                    <td class="px-4 py-2 whitespace-nowrap"><?= $e(date('d M Y', (int) strtotime((string) $d['issued_at']))) ?></td>
                    <td class="px-4 py-2"><a class="font-mono text-xs text-blue-700 hover:underline" href="/admin/store/gst/documents/<?= (int) $d['id'] ?>"><?= $e($d['doc_no']) ?></a></td>
                    <td class="px-4 py-2"><?= $cn ? '<span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Credit note</span> <span class="text-xs text-slate-500">' . $e(($reasons[$d['reason']] ?? $d['reason']) . ' · vs ' . ($d['refers_to_no'] ?? '')) . '</span>' : ((int) $d['is_bill_of_supply'] ? 'Bill of supply' : 'Invoice') ?></td>
                    <td class="px-4 py-2"><?= $e($d['vendor_name'] ?? '') ?></td>
                    <td class="px-4 py-2"><a class="text-blue-700 hover:underline" href="/admin/store/orders/<?= (int) $d['order_id'] ?>"><?= $e($d['order_no']) ?></a></td>
                    <td class="px-4 py-2 text-xs"><?= $d['supply_type'] === 'intra' ? 'CGST+SGST' : 'IGST' ?></td>
                    <td class="px-4 py-2 text-right"><?= $cn ? '−' : '' ?><?= $r($d['taxable_paise']) ?></td>
                    <td class="px-4 py-2 text-right"><?= $cn ? '−' : '' ?><?= $r((int) $d['cgst_paise'] + (int) $d['sgst_paise'] + (int) $d['igst_paise']) ?></td>
                    <td class="px-4 py-2 text-right"><?= $cn ? '−' : '' ?><?= $r($d['total_paise']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>
</body>
</html>
