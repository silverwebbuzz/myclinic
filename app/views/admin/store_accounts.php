<?php
/**
 * /admin/store/accounts — one month in plain accounting terms, for eClinicPro's CA,
 * plus eClinicPro's monthly GST invoices to sellers (commission + courier + charges).
 *
 * @var string $period
 * @var list<array{title:string, note:string, rows:list<array<string,mixed>>}> $sections
 * @var list<array<string,mixed>> $sellers
 * @var list<array<string,mixed>> $invoices
 * @var bool $platformReady
 * @var bool $monthEnded
 * @var bool $tableMissing
 * @var bool $invoiceTableMissing
 */
use App\Services\Store\AccountsService;
use App\Services\Store\SellerInvoiceService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => AccountsService::rs((int) $p);
$q = rawurlencode($period);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store accounts — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Accounts · <?= $e(SellerInvoiceService::monthName($period)) ?></h1>
            <p class="max-w-2xl text-sm text-slate-500">
                eClinicPro is the <strong>marketplace</strong>: customers pay eClinicPro <strong>on behalf of the sellers</strong>.
                That money is the sellers' until it is paid out. eClinicPro's own income is only the commission, delivery fees and charges to sellers (section 2).
            </p>
        </div>
        <form method="get" class="flex flex-wrap items-end gap-2 text-sm">
            <input type="month" name="month" value="<?= $e($period) ?>" max="<?= $e(date('Y-m')) ?>" class="rounded border bg-white px-2 py-1.5">
            <button class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">Show</button>
        </form>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>

    <div class="flex flex-wrap gap-2 text-sm">
        <a href="/admin/store/accounts/export?month=<?= $e($q) ?>" class="rounded bg-slate-800 px-3 py-1.5 font-medium text-white hover:bg-slate-700">Download summary for CA (CSV)</a>
        <a href="/admin/store/accounts/ledger?month=<?= $e($q) ?>" class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">Every seller entry (CSV)</a>
        <a href="/admin/store/gst?month=<?= $e($q) ?>" class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">GST register: seller invoices</a>
        <a href="/admin/store/gst?issuer=platform&month=<?= $e($q) ?>" class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">GST register: delivery invoices</a>
        <a href="/admin/store/accounts/invoices/export?month=<?= $e($q) ?>" class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">Invoices to sellers (CSV)</a>
    </div>

    <?php if ($tableMissing): ?>
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Some store tables are missing. Run <code>check_store_tables.sql</code> to see which patch to import.</p>
    <?php endif; ?>

    <div class="grid gap-5 lg:grid-cols-2">
        <?php foreach ($sections as $sec): ?>
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="font-semibold"><?= $e($sec['title']) ?></h2>
                <p class="mt-1 text-xs text-slate-500"><?= $e($sec['note']) ?></p>
                <dl class="mt-3 divide-y text-sm">
                    <?php foreach ($sec['rows'] as $row): ?>
                        <div class="flex items-baseline justify-between gap-4 py-2 <?= !empty($row['strong']) ? 'font-semibold' : '' ?>">
                            <dt class="min-w-0"><?= $e($row['label']) ?><?php if (!empty($row['hint'])): ?><span class="block text-xs font-normal text-slate-500"><?= $e($row['hint']) ?></span><?php endif; ?></dt>
                            <dd class="whitespace-nowrap tabular-nums <?= (int) $row['amount'] < 0 ? 'text-red-700' : '' ?>"><?= $r($row['amount']) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">How to read this (for your books)</h2>
        <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-700">
            <li><strong>Customer pays ₹118</strong> → bank +₹118, and you owe the seller ₹118 ("payable to sellers"). It is not your income.</li>
            <li><strong>Package delivered</strong> → from that ₹118 you keep commission ₹10 + ₹1.80 GST and the courier charge; the rest stays payable to the seller.</li>
            <li><strong>Payout</strong> → bank −(what's left), payable to the seller cleared.</li>
            <li>Your <strong>income</strong> is section 2. The GST on it is yours to pay. The GST on the product (₹18) is the seller's: it is on their invoice and their return.</li>
            <li>Ask your CA about <strong>TCS (e-commerce operator, 0.5%)</strong> and <strong>TDS 194-O (0.1%)</strong>; the store does not deduct them yet.</li>
        </ol>
    </section>

    <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3">
            <div>
                <h2 class="font-semibold">eClinicPro's GST invoices to sellers</h2>
                <p class="text-xs text-slate-500">One invoice per seller per month for commission + courier + charges (a credit note if reversals were larger). Issued automatically on the 1st for the month before. Sellers see them under GST invoices.</p>
            </div>
            <?php if ($monthEnded && $platformReady && !$invoiceTableMissing): ?>
                <form method="post" action="/admin/store/accounts/invoices" onsubmit="return confirm('Issue any missing invoices to sellers for <?= $e(SellerInvoiceService::monthName($period)) ?>? Issued invoices are never changed.')">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="month" value="<?= $e($period) ?>">
                    <button class="rounded bg-emerald-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-800">Issue invoices for this month</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($invoiceTableMissing): ?>
            <p class="px-5 py-4 text-sm text-amber-800">Import <code>2026_10_05_store_seller_invoices.sql</code> to switch on invoices to sellers.</p>
        <?php elseif (!$platformReady): ?>
            <p class="px-5 py-4 text-sm text-amber-800">Fill in eClinicPro's legal name, GSTIN and address in <a href="/admin/store/settings" class="underline">Store settings → Invoicing</a> first.</p>
        <?php elseif (!$monthEnded): ?>
            <p class="px-5 py-4 text-sm text-slate-500">This month hasn't ended yet. Invoices are issued after the month ends.</p>
        <?php endif; ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-2">Number</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Seller</th><th class="px-4 py-2 text-right">Taxable</th><th class="px-4 py-2 text-right">GST</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2">Issued</th></tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (!$invoices): ?><tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">None for this month.</td></tr><?php endif; ?>
                    <?php foreach ($invoices as $d): ?>
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2 font-mono"><a href="/admin/store/seller-invoices/<?= (int) $d['id'] ?>" target="_blank" class="text-sky-700 hover:underline"><?= $e($d['doc_no']) ?></a></td>
                            <td class="px-4 py-2"><?= $d['doc_type'] === 'credit_note' ? '<span class="text-amber-700">Credit note</span>' : 'Invoice' ?></td>
                            <td class="px-4 py-2"><?= $e($d['vendor_name']) ?></td>
                            <td class="px-4 py-2 text-right"><?= $r($d['taxable_paise']) ?></td>
                            <td class="px-4 py-2 text-right"><?= $r((int) $d['cgst_paise'] + (int) $d['sgst_paise'] + (int) $d['igst_paise']) ?></td>
                            <td class="px-4 py-2 text-right font-medium"><?= $r($d['total_paise']) ?></td>
                            <td class="px-4 py-2 text-slate-500"><?= $e(substr((string) $d['issued_at'], 0, 10)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
        <h2 class="border-b px-5 py-3 font-semibold">Per seller this month</h2>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-2">Seller</th><th class="px-4 py-2 text-right">Sales (incl. GST)</th><th class="px-4 py-2 text-right">Commission</th><th class="px-4 py-2 text-right">GST on commission</th><th class="px-4 py-2 text-right">Courier &amp; charges</th><th class="px-4 py-2 text-right">Adjustments</th><th class="px-4 py-2 text-right">Net earned</th></tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (!$sellers): ?><tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No seller activity this month.</td></tr><?php endif; ?>
                    <?php foreach ($sellers as $s): ?>
                        <tr>
                            <td class="px-4 py-2"><?= $e($s['display_name']) ?><span class="block font-mono text-xs text-slate-400"><?= $e($s['gstin'] ?? '') ?></span></td>
                            <td class="px-4 py-2 text-right"><?= $r($s['sales']) ?></td>
                            <td class="px-4 py-2 text-right"><?= $r($s['commission']) ?></td>
                            <td class="px-4 py-2 text-right"><?= $r($s['commission_gst']) ?></td>
                            <td class="px-4 py-2 text-right"><?= $r($s['charges']) ?></td>
                            <td class="px-4 py-2 text-right"><?= $r($s['adjustments']) ?></td>
                            <td class="px-4 py-2 text-right font-medium"><?= $r($s['net']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
