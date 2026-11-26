<?php
/** /admin/rx-whatsapp — WhatsApp prescription allowance (super-admin). */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$packRows = $packs;
while (count($packRows) < 4) {
    $packRows[] = ['qty' => '', 'price' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rx WhatsApp — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl space-y-6 p-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">WhatsApp prescriptions — allowance</h1>
            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $ready ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                <?= $ready ? 'SENDING ON' : 'NOT READY' ?>
            </span>
        </div>

        <?php if (!$ready): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Sending starts when WhatsApp is connected in <a class="underline" href="/admin/messaging">Messaging</a> <strong>and</strong>
            the template <code>rx_whatsapp_doc</code> (Meta name <code>rx_prescription_doc</code>, Document header) is set to <strong>approved</strong> there.
            Until then nothing is reserved or charged, and doctors don't see the WhatsApp button.
        </div>
        <?php endif; ?>
        <?php if ($message !== ''): ?>
        <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"><?= $h($message) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
        <div class="rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800"><?= $h($error) ?></div>
        <?php endif; ?>

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Free messages used (<?= $h(date('F Y')) ?>)</p>
                <p class="mt-1 text-2xl font-semibold"><?= (int) $totals['free_used'] ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Purchased messages used</p>
                <p class="mt-1 text-2xl font-semibold"><?= (int) $totals['paid_used'] ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Pack revenue this month (incl. GST)</p>
                <p class="mt-1 text-2xl font-semibold">₹<?= number_format((float) $totals['revenue'], 2) ?></p>
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-semibold">Defaults</h2>
            <form method="post" action="/admin/rx-whatsapp/settings" class="mt-3 space-y-4">
                <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                <label class="block text-sm">Free messages per clinic per month
                    <input type="number" min="0" name="free_monthly" value="<?= (int) $defaultFree ?>" class="mt-1 block w-40 rounded border px-2 py-1.5">
                    <span class="text-xs text-slate-500">Applies to every clinic without an override. Resets on the 1st; unused don't carry over.</span>
                </label>
                <div>
                    <p class="text-sm">Packs doctors can buy <span class="text-xs text-slate-500">(price in ₹ before 18% GST; leave a row blank to remove it)</span></p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-4">
                        <?php foreach ($packRows as $p): ?>
                        <div class="flex items-center gap-1 rounded border p-2 text-sm">
                            <input type="number" min="1" name="pack_qty[]" value="<?= $h($p['qty']) ?>" placeholder="Qty" class="w-20 rounded border px-2 py-1">
                            <span class="text-slate-400">for ₹</span>
                            <input type="number" min="1" step="0.01" name="pack_price[]" value="<?= $h($p['price']) ?>" placeholder="Price" class="w-20 rounded border px-2 py-1">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Save defaults</button>
            </form>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">Recent pack purchases</h2>
                <a href="/admin/payments" class="text-sm text-emerald-700 hover:underline">All payments &amp; invoices →</a>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs uppercase text-slate-500">
                            <th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Clinic</th><th class="py-2 pr-3">Messages</th>
                            <th class="py-2 pr-3">Amount (incl. GST)</th><th class="py-2 pr-3">Status</th><th class="py-2">Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr class="border-b">
                            <td class="py-2 pr-3"><?= $h(date('d M Y, h:i A', strtotime((string) $o['created_at']))) ?></td>
                            <td class="py-2 pr-3"><?= $h($o['clinic_name'] ?? ('#' . $o['clinic_id'])) ?></td>
                            <td class="py-2 pr-3"><?= number_format((int) $o['qty']) ?></td>
                            <td class="py-2 pr-3">₹<?= number_format((float) $o['total_amount'], 2) ?></td>
                            <td class="py-2 pr-3">
                                <span class="rounded-full px-2 py-0.5 text-xs <?= $o['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($o['status'] === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800') ?>"><?= $h($o['status']) ?></span>
                            </td>
                            <td class="py-2">
                                <?php if (!empty($o['invoice_id'])): ?>
                                <a href="/admin/payments/<?= (int) $o['invoice_id'] ?>/pdf" target="_blank" class="text-emerald-700 hover:underline"><?= $h($o['invoice_no']) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($orders === []): ?>
                        <tr><td colspan="6" class="py-4 text-center text-slate-400">No pack purchases yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">Clinics</h2>
                <form method="get" class="flex gap-2">
                    <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Clinic name or ID" class="rounded border px-2 py-1.5 text-sm">
                    <button class="rounded border px-3 py-1.5 text-sm">Search</button>
                </form>
            </div>
            <p class="mt-1 text-xs text-slate-500">Top 200 by usage this month. Override blank = default (<?= (int) $defaultFree ?>). "Add / remove" changes the purchased balance (use a negative number to remove).</p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs uppercase text-slate-500">
                            <th class="py-2 pr-3">Clinic</th>
                            <th class="py-2 pr-3">Free used</th>
                            <th class="py-2 pr-3">Paid used</th>
                            <th class="py-2 pr-3">Paid balance</th>
                            <th class="py-2">Settings</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clinics as $c):
                        $limit = $c['monthly_free_override'] !== null ? (int) $c['monthly_free_override'] : (int) $defaultFree;
                        $enabled = $c['is_enabled'] === null || (int) $c['is_enabled'] === 1;
                    ?>
                        <tr id="c<?= (int) $c['id'] ?>" class="border-b align-top">
                            <td class="py-2 pr-3">
                                <a href="/admin/clinics/<?= (int) $c['id'] ?>" class="font-medium text-emerald-700 hover:underline"><?= $h($c['name']) ?></a>
                                <div class="text-xs text-slate-400">#<?= (int) $c['id'] ?><?= !$enabled ? ' · <span class="text-red-600">off</span>' : '' ?></div>
                            </td>
                            <td class="py-2 pr-3"><?= (int) $c['free_used'] ?> / <?= $limit ?></td>
                            <td class="py-2 pr-3"><?= (int) $c['paid_used_month'] ?></td>
                            <td class="py-2 pr-3 font-semibold"><?= (int) ($c['paid_balance'] ?? 0) ?></td>
                            <td class="py-2">
                                <form method="post" action="/admin/rx-whatsapp/clinic/<?= (int) $c['id'] ?>" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                    <label class="text-xs text-slate-500">Free/month
                                        <input type="number" min="0" name="monthly_free_override" value="<?= $c['monthly_free_override'] !== null ? (int) $c['monthly_free_override'] : '' ?>" placeholder="<?= (int) $defaultFree ?>" class="ml-1 w-20 rounded border px-2 py-1 text-sm">
                                    </label>
                                    <label class="text-xs text-slate-500">Add / remove
                                        <input type="number" name="adjust" placeholder="0" class="ml-1 w-20 rounded border px-2 py-1 text-sm">
                                    </label>
                                    <input type="text" name="note" placeholder="Note (optional)" class="w-36 rounded border px-2 py-1 text-sm">
                                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_enabled" value="1" <?= $enabled ? 'checked' : '' ?>> On</label>
                                    <button class="rounded bg-slate-800 px-3 py-1 text-xs text-white">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($clinics === []): ?>
                        <tr><td colspan="5" class="py-4 text-center text-slate-400">No clinics found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
