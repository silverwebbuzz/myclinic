<?php
/** /admin/wellness/reports — "Report this listing" submissions. */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$reasons = ['fake' => 'Looks fake / spam', 'hidden_fees' => 'Hidden fees', 'medical_claim' => 'Medical claim', 'wrong_details' => 'Wrong details', 'copyright' => 'Copyright', 'other' => 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports · Wellness Hub · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Wellness Hub — reports</h1>
        <p class="text-sm text-slate-500">Visitors use “Report this listing” on camp and event pages. Open the listing to unpublish it if the report is right.</p>
        <div class="mt-4 flex gap-2">
            <?php foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $k => $l): ?>
                <a href="/admin/wellness/reports?status=<?= $k ?>" class="rounded-full px-3 py-1.5 text-xs font-semibold <?= $status === $k ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' ?>"><?= $l ?></a>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 overflow-x-auto ui-card">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Listing</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">From</th><th class="px-4 py-3">When</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (!$rows): ?><tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nothing here.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <?php if ($r['item_type'] === 'event' && $r['title']): ?>
                                    <a href="/admin/wellness/events/<?= (int) $r['item_id'] ?>" class="font-medium text-sky-700 hover:underline"><?= $h($r['title']) ?></a>
                                    <div class="text-xs text-slate-500">status: <?= $h($r['event_status']) ?></div>
                                <?php else: ?>
                                    <?= $h($r['item_type']) ?> #<?= (int) $r['item_id'] ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3"><strong><?= $h($reasons[$r['reason']] ?? $r['reason']) ?></strong><?php if ($r['details']): ?><div class="text-xs text-slate-700"><?= $h($r['details']) ?></div><?php endif; ?>
                                <?php if ($r['admin_note']): ?><div class="mt-1 text-xs text-slate-500">Note: <?= $h($r['admin_note']) ?></div><?php endif; ?></td>
                            <td class="px-4 py-3 text-xs text-slate-600"><?= $r['reporter_phone'] ? $h($r['reporter_phone']) : 'Not signed in' ?></td>
                            <td class="px-4 py-3 text-xs text-slate-500"><?= $h(date('d M Y, h:i A', strtotime((string) $r['created_at']))) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($r['status'] === 'open'): ?>
                                    <form method="post" action="/admin/wellness/reports/<?= (int) $r['id'] ?>" class="flex flex-wrap gap-2">
                                        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                        <input name="note" maxlength="500" placeholder="Note (optional)" class="w-40 rounded border px-2 py-1 text-xs">
                                        <button name="status" value="resolved" class="rounded border bg-white px-2 py-1 text-xs font-semibold">Resolved</button>
                                        <button name="status" value="dismissed" class="rounded border bg-white px-2 py-1 text-xs">Dismiss</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
