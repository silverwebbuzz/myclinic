<?php
/**
 * /admin/lab/orders/{id} — one booking in full.
 *
 * Everything ops needs on one screen: who booked it, who the samples are for,
 * where the phlebotomist is going, and the exact bill the patient was shown.
 * Money is READ-ONLY here — the amounts were recomputed server-side at booking
 * time and are the billing record (see partials/lab_orders.php).
 */

$inr = static fn ($paise): string => '₹' . number_format(((int) $paise) / 100, 2);

$statusClass = static function (string $s): string {
    return [
        'pending'   => 'bg-amber-100 text-amber-800',
        'confirmed' => 'bg-sky-100 text-sky-800',
        'collected' => 'bg-indigo-100 text-indigo-800',
        'reported'  => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-slate-200 text-slate-600',
    ][$s] ?? 'bg-slate-100 text-slate-700';
};

/** Bill lines: discounts are stored positive but read as a deduction. */
$isDeduction = static fn (string $kind): bool => $kind === 'discount';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($order['order_ref']) ?> — Lab Booking</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl p-6 space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="/admin/lab/orders" class="text-sm text-slate-500 hover:underline">← All bookings</a>
                <h1 class="mt-1 text-xl font-semibold font-mono"><?= htmlspecialchars($order['order_ref']) ?></h1>
                <p class="text-sm text-slate-500">
                    Booked <?= htmlspecialchars(date('D, d M Y \a\t H:i', strtotime((string) $order['created_at']) ?: time())) ?>
                    · source <?= htmlspecialchars((string) $order['source']) ?>
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-medium <?= $statusClass((string) $order['status']) ?>">
                <?= htmlspecialchars(ucfirst((string) $order['status'])) ?>
            </span>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <div class="grid gap-6 md:grid-cols-3">

            <!-- Left: who + where -->
            <div class="space-y-6 md:col-span-2">

                <!-- Contact -->
                <section class="rounded-xl border bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-semibold">Who booked it</h2>
                    <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Name</dt>
                            <dd class="mt-0.5 font-medium"><?= htmlspecialchars($order['contact_name']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Phone</dt>
                            <dd class="mt-0.5"><a href="tel:<?= htmlspecialchars($order['contact_phone']) ?>" class="text-sky-700 hover:underline"><?= htmlspecialchars($order['contact_phone']) ?></a></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt>
                            <dd class="mt-0.5"><a href="mailto:<?= htmlspecialchars($order['contact_email']) ?>" class="text-sky-700 hover:underline break-all"><?= htmlspecialchars($order['contact_email']) ?></a></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Patient account</dt>
                            <dd class="mt-0.5">
                                <?php if ($identity): ?>
                                    <?= htmlspecialchars((string) ($identity['name'] ?: '—')) ?>
                                    <span class="text-xs text-slate-400">(#<?= (int) $identity['id'] ?>)</span>
                                    <?php if (($identity['phone'] ?? '') !== '' && $identity['phone'] !== $order['contact_phone']): ?>
                                    <div class="text-xs text-slate-500">account phone: <?= htmlspecialchars((string) $identity['phone']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-400">#<?= (int) $order['patient_identity_id'] ?> (not found)</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- Collection -->
                <section class="rounded-xl border bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-semibold">Sample collection</h2>
                    <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Address</dt>
                            <dd class="mt-0.5 whitespace-pre-line"><?= htmlspecialchars($order['address']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Pincode / City / State</dt>
                            <dd class="mt-0.5">
                                <?= htmlspecialchars($order['pincode']) ?>
                                · <?= htmlspecialchars((string) ($order['city'] ?? '—')) ?>
                                · <?= htmlspecialchars((string) ($order['state'] ?? '—')) ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Appointment</dt>
                            <dd class="mt-0.5 font-medium">
                                <?= htmlspecialchars(date('D, d M Y', strtotime((string) $order['appointment_date']) ?: time())) ?>
                                — <?= htmlspecialchars($order['time_slot']) ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Persons</dt>
                            <dd class="mt-0.5"><?= (int) $order['persons'] ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Hard copy report</dt>
                            <dd class="mt-0.5"><?= !empty($order['hard_copy']) ? 'Yes (courier)' : 'No' ?></dd>
                        </div>
                        <?php if (!empty($order['notes'])): ?>
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Patient notes</dt>
                            <dd class="mt-0.5 whitespace-pre-line rounded bg-slate-50 p-2"><?= htmlspecialchars((string) $order['notes']) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </section>

                <!-- Beneficiaries -->
                <section class="rounded-xl border bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-semibold">Who the samples are for <span class="text-sm font-normal text-slate-500">(<?= count($people) ?>)</span></h2>
                    <?php if (!$people): ?>
                    <p class="text-sm text-slate-500">No beneficiary rows recorded.</p>
                    <?php else: ?>
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="py-1">#</th><th class="py-1">Name</th><th class="py-1">Age</th><th class="py-1">Gender</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($people as $p): ?>
                            <tr>
                                <td class="py-1.5 text-slate-400"><?= (int) $p['position'] ?></td>
                                <td class="py-1.5 font-medium"><?= htmlspecialchars($p['name']) ?></td>
                                <td class="py-1.5"><?= $p['age'] !== null ? (int) $p['age'] : '—' ?></td>
                                <td class="py-1.5"><?= htmlspecialchars((string) ($p['gender'] ?? '—')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </section>

                <!-- Bill -->
                <section class="rounded-xl border bg-white p-5 shadow-sm">
                    <h2 class="mb-1 font-semibold">Bill</h2>
                    <p class="mb-3 text-xs text-slate-500">
                        Server-recomputed at booking time. Read-only — to change what a patient pays,
                        cancel and rebook.
                    </p>
                    <table class="w-full text-sm">
                        <tbody class="divide-y">
                            <?php foreach ($items as $it): ?>
                            <tr>
                                <td class="py-1.5">
                                    <?= htmlspecialchars($it['label']) ?>
                                    <?php if ((int) $it['qty'] > 1): ?>
                                    <span class="text-xs text-slate-500">× <?= (int) $it['qty'] ?></span>
                                    <?php endif; ?>
                                    <div class="text-[10px] uppercase tracking-wide text-slate-400"><?= htmlspecialchars($it['kind']) ?></div>
                                </td>
                                <td class="py-1.5 text-right whitespace-nowrap <?= $isDeduction((string) $it['kind']) ? 'text-emerald-700' : '' ?>">
                                    <?= $isDeduction((string) $it['kind']) ? '− ' : '' ?><?= $inr($it['amount_paise']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="border-t-2">
                                <td class="py-2 font-semibold">Total payable</td>
                                <td class="py-2 text-right text-lg font-semibold"><?= $inr($order['total_paise']) ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <dl class="mt-4 grid gap-2 border-t pt-3 text-xs text-slate-500 sm:grid-cols-2">
                        <div>Coupon: <strong class="text-slate-700"><?= htmlspecialchars((string) ($order['coupon_code'] ?: '—')) ?></strong>
                            <?php if ((int) $order['coupon_pct'] > 0): ?>(<?= (int) $order['coupon_pct'] ?>% on package)<?php endif; ?></div>
                        <div>Blended discount: <strong class="text-slate-700"><?= number_format(((int) $order['effective_discount_pct']) / 100, 2) ?>%</strong></div>
                        <div>Patient saved: <strong class="text-slate-700"><?= $inr($order['savings_paise']) ?></strong></div>
                        <div>
                            Browser showed:
                            <strong class="text-slate-700"><?= $order['client_total_paise'] !== null ? $inr($order['client_total_paise']) : '—' ?></strong>
                            <?php if ($order['client_total_paise'] !== null && (int) $order['client_total_paise'] !== (int) $order['total_paise']): ?>
                            <span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 font-medium text-rose-800">mismatch</span>
                            <?php endif; ?>
                        </div>
                    </dl>
                </section>
            </div>

            <!-- Right: ops actions -->
            <div class="space-y-6">
                <form method="post" action="/admin/lab/orders/<?= (int) $order['id'] ?>" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <h2 class="font-semibold">Update</h2>

                    <label class="block text-sm">
                        <span class="text-slate-600">Status</span>
                        <select name="status" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                            <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'collected' => 'Sample collected', 'reported' => 'Report ready', 'cancelled' => 'Cancelled'] as $k => $label): ?>
                            <option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="text-slate-600">Payment</span>
                        <select name="payment_status" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                            <?php foreach (['unpaid' => 'Unpaid', 'paid' => 'Paid', 'refunded' => 'Refunded'] as $k => $label): ?>
                            <option value="<?= $k ?>" <?= $order['payment_status'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="text-slate-600">Internal notes</span>
                        <textarea name="admin_notes" rows="5" placeholder="Never shown to the patient"
                                  class="mt-1 w-full rounded border px-2 py-1.5 text-sm"><?= htmlspecialchars((string) ($order['admin_notes'] ?? '')) ?></textarea>
                    </label>

                    <button type="submit" class="w-full rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        Save changes
                    </button>
                </form>

                <section class="rounded-xl border bg-white p-5 shadow-sm text-sm space-y-2">
                    <h2 class="font-semibold">Delivery &amp; audit</h2>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Patient email</span>
                        <span class="<?= !empty($order['patient_email_sent']) ? 'text-emerald-700' : 'text-rose-700' ?>">
                            <?= !empty($order['patient_email_sent']) ? 'Sent' : 'Not sent' ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Ops email</span>
                        <span class="<?= !empty($order['admin_email_sent']) ? 'text-emerald-700' : 'text-rose-700' ?>">
                            <?= !empty($order['admin_email_sent']) ? 'Sent' : 'Not sent' ?>
                        </span>
                    </div>
                    <?php if (!empty($order['cancelled_at'])): ?>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Cancelled</span>
                        <span><?= htmlspecialchars(date('d M Y, H:i', strtotime((string) $order['cancelled_at']) ?: time())) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Last updated</span>
                        <span><?= htmlspecialchars(date('d M Y, H:i', strtotime((string) $order['updated_at']) ?: time())) ?></span>
                    </div>
                    <?php if (!empty($order['product_slug'])): ?>
                    <div class="pt-2 border-t">
                        <?php
                        // Public URL is /lab/{type}/{slug} (partials/request_router.php).
                        // product_type stores package|test, matching the type segment.
                        $pubType = in_array((string) $order['product_type'], ['package', 'test'], true)
                            ? (string) $order['product_type'] : 'package';
                        ?>
                        <a href="/lab/<?= $pubType ?>/<?= urlencode((string) $order['product_slug']) ?>" target="_blank"
                           class="text-sky-700 hover:underline">View the booked test page →</a>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
