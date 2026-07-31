<?php
/**
 * /admin/lab/orders — lab booking worklist (super-admin).
 *
 * Answers "who booked a lab test, and what do we owe them?" The row is a
 * summary; everything else (itemised bill, beneficiaries, address) is one
 * click away on the detail page.
 */

/** Paise → "₹1,234" for display. Orders always store paise. */
$inr = static fn ($paise): string => '₹' . number_format(((int) $paise) / 100);

/** Status pill colours — same vocabulary as the patient panel. */
$statusClass = static function (string $s): string {
    return [
        'pending'   => 'bg-amber-100 text-amber-800',
        'confirmed' => 'bg-sky-100 text-sky-800',
        'collected' => 'bg-indigo-100 text-indigo-800',
        'reported'  => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-slate-200 text-slate-600',
    ][$s] ?? 'bg-slate-100 text-slate-700';
};
$payClass = static function (string $s): string {
    return [
        'paid'     => 'bg-emerald-100 text-emerald-800',
        'unpaid'   => 'bg-rose-100 text-rose-800',
        'refunded' => 'bg-slate-200 text-slate-600',
    ][$s] ?? 'bg-slate-100 text-slate-700';
};

/** Rebuild the current query string with one key changed (paging/sorting). */
$urlWith = static function (array $overrides) use ($q, $status, $payment, $dateField, $from, $to, $page): string {
    $base = array_filter([
        'q'          => $q,
        'status'     => $status,
        'payment'    => $payment,
        'date_field' => $dateField === 'created' ? '' : $dateField,
        'from'       => $from,
        'to'         => $to,
        'page'       => $page > 1 ? $page : '',
    ], static fn ($v) => $v !== '' && $v !== null);
    return '/admin/lab/orders?' . http_build_query(array_filter(
        array_merge($base, $overrides),
        static fn ($v) => $v !== '' && $v !== null
    ));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lab Bookings — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-7xl p-6 space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Lab Bookings</h1>
                <p class="text-sm text-slate-500">Every "Book Now" submitted from the lab pages.</p>
            </div>
            <a href="/admin/lab/orders/export?<?= htmlspecialchars(http_build_query(array_filter([
                    'status' => $status, 'from' => $from, 'to' => $to,
                    'date_field' => $dateField === 'created' ? '' : $dateField,
                ], static fn ($v) => $v !== '' && $v !== null))) ?>"
               class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Export CSV
            </a>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            The <code>lab_orders</code> table doesn't exist yet. Import
            <code>app/database/patches/2026_07_27_lab_orders.sql</code> (phpMyAdmin → Import),
            then reload this page. Until then no booking can be saved at all.
        </div>
        <?php endif; ?>

        <!-- Headline figures for the CURRENT filter -->
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Bookings</div>
                <div class="mt-1 text-2xl font-semibold"><?= number_format((int) ($stats['orders'] ?? 0)) ?></div>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Value</div>
                <div class="mt-1 text-2xl font-semibold"><?= $inr($stats['revenue_paise'] ?? 0) ?></div>
                <div class="text-xs text-slate-400">excludes cancelled</div>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Awaiting action</div>
                <div class="mt-1 text-2xl font-semibold text-amber-700"><?= number_format((int) ($stats['pending'] ?? 0)) ?></div>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Booked today</div>
                <div class="mt-1 text-2xl font-semibold"><?= number_format((int) ($stats['today'] ?? 0)) ?></div>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" action="/admin/lab/orders" class="rounded-xl border bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-6">
                <label class="block text-sm md:col-span-2">
                    <span class="text-slate-600">Search</span>
                    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                           placeholder="Order ref, phone, email, name, test, pincode"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Status</span>
                    <select name="status" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'collected' => 'Sample collected', 'reported' => 'Report ready', 'cancelled' => 'Cancelled'] as $k => $label): ?>
                        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Payment</span>
                    <select name="payment" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        <?php foreach (['unpaid' => 'Unpaid', 'paid' => 'Paid', 'refunded' => 'Refunded'] as $k => $label): ?>
                        <option value="<?= $k ?>" <?= $payment === $k ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Date range on</span>
                    <select name="date_field" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                        <option value="created" <?= $dateField === 'created' ? 'selected' : '' ?>>Booked date</option>
                        <option value="appointment" <?= $dateField === 'appointment' ? 'selected' : '' ?>>Appointment date</option>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-sm">
                        <span class="text-slate-600">From</span>
                        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
                               class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">To</span>
                        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
                               class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    </label>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-3">
                <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Filter</button>
                <a href="/admin/lab/orders" class="text-sm text-slate-500 hover:underline">Reset</a>
                <span class="ml-auto text-sm text-slate-500"><?= number_format($total) ?> matching</span>
            </div>
        </form>

        <!-- List -->
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Order</th>
                        <th class="px-4 py-2">Patient</th>
                        <th class="px-4 py-2">Test / Package</th>
                        <th class="px-4 py-2">Appointment</th>
                        <th class="px-4 py-2">Location</th>
                        <th class="px-4 py-2 text-right">Total</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (!$orders): ?>
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">
                        <?= $total === 0 && $q === '' && $status === '' ? 'No lab bookings yet.' : 'No bookings match these filters.' ?>
                    </td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                    <tr class="<?= $o['status'] === 'cancelled' ? 'opacity-60' : '' ?> hover:bg-slate-50">
                        <td class="px-4 py-2 align-top">
                            <a href="/admin/lab/orders/<?= (int) $o['id'] ?>" class="font-mono font-medium text-sky-700 hover:underline">
                                <?= htmlspecialchars($o['order_ref']) ?>
                            </a>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars(date('d M Y, H:i', strtotime((string) $o['created_at']) ?: time())) ?></div>
                        </td>
                        <td class="px-4 py-2 align-top">
                            <div class="font-medium"><?= htmlspecialchars($o['contact_name']) ?></div>
                            <div class="text-xs text-slate-500">
                                <a href="tel:<?= htmlspecialchars($o['contact_phone']) ?>" class="hover:underline"><?= htmlspecialchars($o['contact_phone']) ?></a>
                            </div>
                        </td>
                        <td class="px-4 py-2 align-top">
                            <div class="max-w-xs truncate" title="<?= htmlspecialchars($o['product_name']) ?>"><?= htmlspecialchars($o['product_name']) ?></div>
                            <?php if ((int) $o['persons'] > 1): ?>
                            <div class="text-xs text-slate-500"><?= (int) $o['persons'] ?> people</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2 align-top whitespace-nowrap">
                            <div><?= htmlspecialchars(date('D, d M Y', strtotime((string) $o['appointment_date']) ?: time())) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($o['time_slot']) ?></div>
                        </td>
                        <td class="px-4 py-2 align-top">
                            <div><?= htmlspecialchars((string) ($o['city'] ?? '—')) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($o['pincode']) ?></div>
                        </td>
                        <td class="px-4 py-2 align-top text-right font-medium whitespace-nowrap">
                            <?= $inr($o['total_paise']) ?>
                            <?php if (!empty($o['coupon_code'])): ?>
                            <div class="text-xs font-normal text-emerald-700"><?= htmlspecialchars($o['coupon_code']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2 align-top whitespace-nowrap">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusClass((string) $o['status']) ?>">
                                <?= htmlspecialchars(ucfirst((string) $o['status'])) ?>
                            </span>
                            <div class="mt-1">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium <?= $payClass((string) $o['payment_status']) ?>">
                                    <?= htmlspecialchars(ucfirst((string) $o['payment_status'])) ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2 align-top text-right whitespace-nowrap">
                            <a href="/admin/lab/orders/<?= (int) $o['id'] ?>" class="text-sky-700 hover:underline">Open</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($pages ?? 1) > 1): ?>
        <div class="flex items-center justify-between text-sm">
            <div class="text-slate-500">Page <?= (int) $page ?> of <?= (int) $pages ?></div>
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
