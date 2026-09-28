<?php
/**
 * /vendor/gst — the seller's own GST invoices and credit notes for a month.
 *
 * @var list<array<string,mixed>> $docs
 * @var list<array<string,mixed>> $hsn
 * @var array<string,int> $totals
 * @var string $month
 */
$pageTitle = 'GST invoices';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . number_format((int) $p / 100, 2);
$reasons = ['return' => 'Return', 'cancel' => 'Cancelled', 'rto' => 'Returned to you', 'lost' => 'Lost in transit', 'damaged' => 'Damaged in transit'];
$card = 'rounded-2xl border border-[#ece8df] bg-white';
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">GST invoices</h1>
        <p class="mt-1 text-sm text-slate-500">Invoices issued in your name when your packages were dispatched, and credit notes for items refunded afterwards. Report these in your GST returns (share the CSV with your CA).</p>
    </div>
    <form method="get" class="flex items-end gap-2 text-sm">
        <input type="month" name="month" value="<?= $e($month) ?>" class="rounded-lg border border-slate-300 px-3 py-2">
        <button class="rounded-full border border-slate-300 px-4 py-2 hover:bg-white">Show</button>
        <a href="/vendor/gst/export?month=<?= $e(rawurlencode($month)) ?>" class="rounded-full bg-[#0e4d34] px-4 py-2 text-white hover:bg-[#17774f]">Download CSV</a>
    </form>
</div>

<?php if ($totals): ?>
<div class="mt-4 grid gap-3 sm:grid-cols-3">
    <div class="<?= $card ?> p-4"><p class="text-xs text-slate-500">Documents</p><p class="text-lg font-semibold"><?= (int) $totals['invoices'] ?> invoices · <?= (int) $totals['credit_notes'] ?> credit notes</p></div>
    <div class="<?= $card ?> p-4"><p class="text-xs text-slate-500">Net taxable value</p><p class="text-lg font-semibold"><?= $r($totals['taxable']) ?></p></div>
    <div class="<?= $card ?> p-4"><p class="text-xs text-slate-500">Net GST</p><p class="text-lg font-semibold"><?= $r($totals['cgst'] + $totals['sgst'] + $totals['igst']) ?></p></div>
</div>
<?php endif; ?>

<section class="mt-4 <?= $card ?> overflow-hidden">
    <h2 class="border-b border-[#ece8df] px-4 py-3 font-semibold">By HSN / rate</h2>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-[#faf7f1] text-left text-xs text-slate-500"><tr><th class="px-4 py-2">HSN</th><th class="px-4 py-2">GST %</th><th class="px-4 py-2 text-right">Qty</th><th class="px-4 py-2 text-right">Taxable</th><th class="px-4 py-2 text-right">CGST</th><th class="px-4 py-2 text-right">SGST</th><th class="px-4 py-2 text-right">IGST</th></tr></thead>
        <tbody>
        <?php if (!$hsn): ?><tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Nothing this month.</td></tr><?php endif; ?>
        <?php foreach ($hsn as $h): ?>
            <tr class="border-t border-[#ece8df]"><td class="px-4 py-2"><?= $e($h['hsn_sac'] ?? '—') ?></td><td class="px-4 py-2"><?= (int) $h['gst_bp'] / 100 ?>%</td><td class="px-4 py-2 text-right"><?= (int) $h['qty'] ?></td>
                <td class="px-4 py-2 text-right"><?= $r($h['taxable']) ?></td><td class="px-4 py-2 text-right"><?= $r($h['cgst']) ?></td><td class="px-4 py-2 text-right"><?= $r($h['sgst']) ?></td><td class="px-4 py-2 text-right"><?= $r($h['igst']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="mt-4 <?= $card ?> overflow-hidden">
    <h2 class="border-b border-[#ece8df] px-4 py-3 font-semibold">Documents</h2>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-[#faf7f1] text-left text-xs text-slate-500"><tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Number</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Order</th><th class="px-4 py-2 text-right">Taxable</th><th class="px-4 py-2 text-right">GST</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
        <tbody>
        <?php if (!$docs): ?><tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No documents this month.</td></tr><?php endif; ?>
        <?php foreach ($docs as $d): $cn = $d['doc_type'] === 'credit_note'; ?>
            <tr class="border-t border-[#ece8df]">
                <td class="px-4 py-2 whitespace-nowrap"><?= $e(date('d M', (int) strtotime((string) $d['issued_at']))) ?></td>
                <td class="px-4 py-2"><a class="font-mono text-xs text-[#0e4d34] underline" href="/vendor/gst/documents/<?= (int) $d['id'] ?>"><?= $e($d['doc_no']) ?></a></td>
                <td class="px-4 py-2"><?= $cn ? 'Credit note <span class="text-xs text-slate-500">(' . $e(($reasons[$d['reason']] ?? $d['reason']) . ', against ' . ($d['refers_to_no'] ?? '')) . ')</span>' : ((int) $d['is_bill_of_supply'] ? 'Bill of supply' : 'Invoice') ?></td>
                <td class="px-4 py-2"><a class="underline" href="/vendor/orders/<?= (int) $d['vendor_order_id'] ?>"><?= $e($d['order_no']) ?></a></td>
                <td class="px-4 py-2 text-right"><?= $cn ? '−' : '' ?><?= $r($d['taxable_paise']) ?></td>
                <td class="px-4 py-2 text-right"><?= $cn ? '−' : '' ?><?= $r((int) $d['cgst_paise'] + (int) $d['sgst_paise'] + (int) $d['igst_paise']) ?></td>
                <td class="px-4 py-2 text-right"><?= $cn ? '−' : '' ?><?= $r($d['total_paise']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
