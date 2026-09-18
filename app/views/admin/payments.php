<?php
/** /admin/payments — subscription (plan) payments from saas_invoices (super-admin). */
$fmtDate = static fn ($d): string => $d ? date('d M Y, h:i A', strtotime((string) $d)) : '—';
$money = static fn ($v): string => '₹' . number_format((float) $v, 2);
$statusBadge = [
    'paid' => 'bg-emerald-100 text-emerald-700',
    'pending' => 'bg-amber-100 text-amber-700',
    'failed' => 'bg-rose-100 text-rose-700',
];
$query = array_filter([
    'q' => $filters['q'], 'status' => $filters['status'], 'from' => $filters['from'],
    'to' => $filters['to'], 'clinic' => $filters['clinic'] ?: null,
], static fn ($v) => $v !== '' && $v !== null);
$urlWith = static fn (array $extra): string => '/admin/payments?' . http_build_query(array_merge($query, $extra));
$currentUrl = $urlWith($page > 1 ? ['page' => $page] : []);
// Same URL for test + live; the dashboard shows whichever mode you're in.
$rzpDash = 'https://dashboard.razorpay.com/app/payments/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscription Payments — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-7xl p-6 space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Subscription Payments</h1>
                <p class="text-sm text-slate-500">Every plan purchase and renewal: who paid, how much, and the Razorpay reference.</p>
            </div>
            <a href="<?= htmlspecialchars('/admin/payments/export?' . http_build_query($query)) ?>"
               class="rounded border bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Export CSV</a>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $message))) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            Couldn't load payments (the <code>saas_invoices</code> table may be missing). Check the server error log.
        </div>
        <?php endif; ?>

        <!-- ===== Headline numbers (all time, unfiltered) ===== -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <?php foreach ([
                ['Collected', $money($stats['paid_amount']), 'text-slate-900'],
                ['Paid invoices', (string) $stats['paid_count'], 'text-emerald-700'],
                ['Repeat-paying clinics', (string) $stats['repeat_clinics'], 'text-sky-700'],
                ['Pending', (string) $stats['pending'], 'text-amber-700'],
                ['Failed', (string) $stats['failed'], 'text-rose-700'],
            ] as [$label, $value, $cls]): ?>
            <div class="rounded-xl border bg-white p-4">
                <div class="text-xs text-slate-500"><?= $label ?></div>
                <div class="mt-1 text-lg font-semibold <?= $cls ?>"><?= htmlspecialchars($value) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===== Filters ===== -->
        <form method="get" action="/admin/payments" class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4">
            <?php if ($filters['clinic'] > 0): ?>
            <input type="hidden" name="clinic" value="<?= (int) $filters['clinic'] ?>">
            <?php endif; ?>
            <div class="flex-1 min-w-[16rem]">
                <label class="block text-xs text-slate-500 mb-1">Search</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>"
                       placeholder="pay_… / order_… / ECP-2026-… / clinic name, email, phone"
                       class="w-full rounded border border-slate-300 px-3 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Status</label>
                <select name="status" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <?php foreach (['paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">From</label>
                <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">To</label>
                <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            </div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-1.5 text-sm font-semibold text-white hover:bg-slate-900">Filter</button>
            <?php if ($query): ?>
            <a href="/admin/payments" class="text-sm text-slate-500 hover:underline">Clear</a>
            <?php endif; ?>
        </form>

        <?php if ($filters['clinic'] > 0): ?>
        <p class="text-sm text-slate-600">
            Showing payments for <strong><?= htmlspecialchars($clinicName ?? ('clinic #' . $filters['clinic'])) ?></strong>
            · <a href="/admin/clinics/<?= (int) $filters['clinic'] ?>" class="text-sky-700 hover:underline">Clinic details</a>
        </p>
        <?php endif; ?>

        <!-- ===== Table ===== -->
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Invoice / date</th>
                        <th class="px-4 py-2">Paid by</th>
                        <th class="px-4 py-2">Plan</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2">Razorpay reference</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No payments found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $status = (string) ($r['status'] ?? '');
                    $seq = (int) ($r['pay_seq'] ?? 0);
                    $paidTotal = (int) ($r['clinic_paid_total'] ?? 0);
                    ?>
                    <tr class="border-t align-top">
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= htmlspecialchars($r['invoice_no'] ?? '—') ?></div>
                            <div class="text-xs text-slate-500">Created <?= $fmtDate($r['created_at'] ?? null) ?></div>
                            <?php if (!empty($r['paid_at'])): ?>
                            <div class="text-xs text-emerald-700">Paid <?= $fmtDate($r['paid_at']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/admin/clinics/<?= (int) $r['clinic_id'] ?>" class="font-medium text-sky-700 hover:underline">
                                <?= htmlspecialchars($r['clinic_name'] ?? ('Clinic #' . (int) $r['clinic_id'])) ?>
                            </a>
                            <?php if (!empty($r['payer_name'])): ?>
                            <div class="text-xs text-slate-600"><?= htmlspecialchars($r['payer_name']) ?></div>
                            <?php endif; ?>
                            <div class="text-xs text-slate-500">
                                <?= htmlspecialchars(implode(' · ', array_filter([$r['payer_email'] ?? '', $r['payer_phone'] ?? '']))) ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div><?= htmlspecialchars(ucfirst((string) ($r['plan_id'] ?? ''))) ?> · <?= htmlspecialchars((string) ($r['billing_cycle'] ?? '')) ?></div>
                            <?php if (!empty($r['period_start'])): ?>
                            <div class="text-xs text-slate-500">
                                <?= date('d M Y', strtotime((string) $r['period_start'])) ?> → <?= !empty($r['period_end']) ? date('d M Y', strtotime((string) $r['period_end'])) : '—' ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($status === 'paid' && $seq > 0): ?>
                            <a href="<?= htmlspecialchars('/admin/payments?clinic=' . (int) $r['clinic_id']) ?>"
                               class="mt-1 inline-block rounded px-1.5 py-0.5 text-[11px] font-semibold <?= $seq === 1 ? 'bg-slate-100 text-slate-600' : 'bg-sky-100 text-sky-700' ?>"
                               title="This clinic has <?= $paidTotal ?> paid invoice<?= $paidTotal === 1 ? '' : 's' ?>">
                                <?= $seq === 1 ? 'First payment' : 'Repeat payment #' . $seq ?> (of <?= $paidTotal ?>)
                            </a>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="font-semibold"><?= $money($r['amount'] ?? 0) ?></div>
                            <?php if (isset($r['tax_amount']) && (float) $r['tax_amount'] > 0): ?>
                            <div class="text-xs text-slate-500"><?= $money($r['base_amount'] ?? 0) ?> + <?= $money($r['tax_amount']) ?> GST</div>
                            <?php endif; ?>
                            <?php if (!empty($r['discount_code'])): ?>
                            <div class="text-xs text-violet-700"><?= htmlspecialchars($r['discount_code']) ?> −<?= $money($r['discount_amount'] ?? 0) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            <div title="Payment ID">
                                <?php if (!empty($r['gateway_payment_id'])): ?>
                                <a href="<?= htmlspecialchars($rzpDash . rawurlencode((string) $r['gateway_payment_id'])) ?>" target="_blank" rel="noopener" class="text-sky-700 hover:underline">
                                    <?= htmlspecialchars($r['gateway_payment_id']) ?>
                                </a>
                                <?php else: ?>
                                <span class="text-slate-400">no payment id</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-slate-500" title="Order ID"><?= htmlspecialchars($r['gateway_order_id'] ?? '—') ?></div>
                            <?php if (($r['gateway'] ?? '') !== '' && ($r['gateway'] ?? '') !== 'razorpay'): ?>
                            <div class="text-slate-400"><?= htmlspecialchars($r['gateway']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold <?= $statusBadge[$status] ?? 'bg-slate-100 text-slate-600' ?>">
                                <?= htmlspecialchars(ucfirst($status ?: '—')) ?>
                            </span>
                            <?php if ((int) ($r['dup_nearby'] ?? 0) > 0): ?>
                            <div class="mt-1 text-[11px] font-semibold text-rose-700" title="Another paid invoice for this clinic within 72 hours">
                                ⚠ Possible double payment
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <?php if ($status === 'paid'): ?>
                            <a href="/admin/payments/<?= (int) $r['id'] ?>/pdf" target="_blank" class="text-xs text-sky-700 hover:underline">Invoice PDF</a>
                            <?php elseif (($r['gateway'] ?? '') === 'razorpay' && !empty($r['gateway_order_id'])): ?>
                            <form method="post" action="/admin/payments/<?= (int) $r['id'] ?>/recheck"
                                  onsubmit="return confirm('Ask Razorpay about this order? If it was paid, the plan will be activated for this clinic.');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="back" value="<?= htmlspecialchars($currentUrl) ?>">
                                <button type="submit" class="rounded border px-2 py-1 text-xs hover:bg-slate-50">Re-check with Razorpay</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($pages ?? 1) > 1): ?>
        <div class="flex items-center justify-between text-sm">
            <div class="text-slate-500">Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= (int) $total ?> invoices</div>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars($urlWith(['page' => $page - 1])) ?>" class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">Previous</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                <a href="<?= htmlspecialchars($urlWith(['page' => $page + 1])) ?>" class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
