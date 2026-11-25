<?php
/**
 * /admin/corporate/providers — clinics switched on for corporate work, and doctor registrations to verify.
 *
 * @var string $tab clinics|doctors
 * @var string $q
 * @var string $status pending|verified|rejected
 * @var list<array<string,mixed>> $clinics
 * @var list<array<string,mixed>> $credentials
 * @var int $pending
 * @var bool $canEdit
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
$tabCls = static fn (bool $on) => $on ? 'border-slate-800 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800';
$d = static fn (?string $ymd): string => $ymd ? date('d M Y', strtotime($ymd)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clinics · Workforce Health · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Clinics for corporate work</h1>
        <p class="text-sm text-slate-600">Switch clinics on after you've talked to them; companies can then add them. Verify each doctor's registration before they can sign fitness certificates.</p>
        <nav class="mt-4 flex gap-4 border-b border-slate-200 text-sm font-medium">
            <a href="/admin/corporate/providers" class="-mb-px border-b-2 px-1 pb-2 <?= $tabCls($tab === 'clinics') ?>">Clinics</a>
            <a href="/admin/corporate/providers?tab=doctors" class="-mb-px border-b-2 px-1 pb-2 <?= $tabCls($tab === 'doctors') ?>">Doctors to verify <?= $pending > 0 ? '<span class="ml-1 rounded-full bg-amber-100 px-1.5 text-xs text-amber-800">' . $pending . '</span>' : '' ?></a>
        </nav>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>

        <?php if ($tab === 'clinics'): ?>
            <form method="get" class="mt-4 flex gap-2">
                <input name="q" value="<?= $h($q) ?>" placeholder="Find any clinic: name, address, email or phone" class="w-full max-w-md rounded border border-slate-200 px-3 py-1.5 text-sm">
                <button class="rounded bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white">Search</button>
                <?php if ($q !== ''): ?><a href="/admin/corporate/providers" class="px-2 py-1.5 text-sm text-slate-600 underline">Show switched-on only</a><?php endif; ?>
            </form>
            <div class="mt-3 overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs text-slate-500"><tr>
                        <th class="px-4 py-2 font-medium">Clinic</th><th class="px-3 py-2 font-medium">Country</th>
                        <th class="px-3 py-2 text-right font-medium">Companies</th><th class="px-3 py-2 text-right font-medium">Waiting</th><th class="px-3 py-2 text-right font-medium">Verified doctors</th>
                        <th class="px-4 py-2 text-right font-medium">Corporate work</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($clinics as $c): $on = (int) $c['corporate_enabled'] === 1; ?>
                            <tr>
                                <td class="px-4 py-2"><div class="font-medium"><?= $h($c['name']) ?> <span class="text-xs text-slate-400">#<?= (int) $c['id'] ?></span></div>
                                    <div class="max-w-md truncate text-xs text-slate-500"><?= $h($c['address'] ?? '') ?><?= !empty($c['email']) ? ' · ' . $h($c['email']) : '' ?></div></td>
                                <td class="px-3 py-2"><?= $h($c['country_code']) ?></td>
                                <td class="px-3 py-2 text-right"><?= (int) $c['companies'] ?></td>
                                <td class="px-3 py-2 text-right"><?= (int) $c['waiting'] ?></td>
                                <td class="px-3 py-2 text-right"><?= (int) $c['verified_doctors'] ?></td>
                                <td class="px-4 py-2 text-right">
                                    <?php if ($canEdit): ?>
                                        <form method="post" action="/admin/corporate/providers/<?= (int) $c['id'] ?>/toggle" <?= $on ? 'onsubmit="return confirm(\'Switch off? Companies cannot add this clinic any more; existing history stays.\')"' : '' ?>>
                                            <?= $csrfField ?><input type="hidden" name="on" value="<?= $on ? '0' : '1' ?>"><input type="hidden" name="q" value="<?= $h($q) ?>">
                                            <button class="rounded px-3 py-1 text-xs font-semibold <?= $on ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-slate-800 text-white' ?>"><?= $on ? 'On · switch off' : 'Switch on' ?></button>
                                        </form>
                                    <?php else: ?>
                                        <?= $on ? '<span class="text-emerald-700">On</span>' : '<span class="text-slate-400">Off</span>' ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($clinics === []): ?><tr><td colspan="6" class="px-4 py-6 text-center text-slate-500"><?= $q === '' ? 'No clinic is switched on yet. Search for one above.' : 'No clinic matches.' ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="mt-4 flex gap-2 text-sm">
                <?php foreach (['pending' => 'Waiting', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $k => $label): ?>
                    <a href="/admin/corporate/providers?tab=doctors&status=<?= $k ?>" class="rounded-full px-3 py-1 <?= $status === $k ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 space-y-3">
                <?php foreach ($credentials as $c): $expired = !empty($c['valid_to']) && (string) $c['valid_to'] < date('Y-m-d'); ?>
                    <div class="rounded-lg bg-white p-4 shadow-sm" x-data="{ reject: false }">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium"><?= $h($c['doctor_name']) ?> <span class="text-xs text-slate-500"><?= $h($c['qualification'] ?? '') ?> · <?= $h($c['clinic_name']) ?></span></div>
                                <div class="text-sm text-slate-700"><span class="font-mono"><?= $h($c['reg_number']) ?></span> · <?= $h($c['authority']) ?> · <?= $h($c['country_code']) ?>
                                    · <?= $c['valid_to'] ? 'valid to ' . $h($d((string) $c['valid_to'])) . ($expired ? ' <span class="font-semibold text-red-700">(expired)</span>' : '') : 'no expiry stated' ?></div>
                                <div class="text-xs text-slate-500">Sent <?= $h($d(substr((string) $c['updated_at'], 0, 10))) ?>
                                    <?php if (!empty($c['document_name'])): ?> · <a class="underline" target="_blank" href="/admin/corporate/credentials/<?= (int) $c['id'] ?>/file">Open document</a><?php endif; ?>
                                    <?php if (!empty($c['review_note'])): ?> · note: <?= $h($c['review_note']) ?><?php endif; ?></div>
                            </div>
                            <?php if ($canEdit && $c['status'] !== 'verified'): ?>
                                <form method="post" action="/admin/corporate/credentials/<?= (int) $c['id'] ?>/review"><?= $csrfField ?><input type="hidden" name="decision" value="verify">
                                    <button class="rounded bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white">Verify</button></form>
                            <?php endif; ?>
                            <?php if ($canEdit && $c['status'] !== 'rejected'): ?>
                                <button type="button" @click="reject = !reject" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700"><?= $c['status'] === 'verified' ? 'Withdraw' : 'Reject' ?></button>
                            <?php endif; ?>
                        </div>
                        <form x-show="reject" x-cloak method="post" action="/admin/corporate/credentials/<?= (int) $c['id'] ?>/review" class="mt-3 flex flex-wrap gap-2">
                            <?= $csrfField ?><input type="hidden" name="decision" value="reject">
                            <input name="note" required maxlength="255" placeholder="Reason (the clinic sees it), e.g. Number not found on the council register" class="min-w-0 flex-1 rounded border border-slate-200 px-3 py-1.5 text-sm">
                            <button class="rounded bg-red-700 px-3 py-1.5 text-xs font-semibold text-white">Reject</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if ($credentials === []): ?><p class="rounded-lg bg-white px-4 py-6 text-center text-sm text-slate-500 shadow-sm">Nothing here.</p><?php endif; ?>
            </div>
            <p class="mt-3 text-xs text-slate-500">Check the number on the council's public register (India: NMC / state medical council; UAE: DHA / DOH / MOHAP) before verifying.</p>
        <?php endif; ?>
    </main>
</body>
</html>
