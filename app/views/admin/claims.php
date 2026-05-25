<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor claims · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Doctor claim &amp; listing requests</h1>
            <div class="text-sm text-slate-500">
                <?= count($claims) ?> pending
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <?php
            $msgLabel = [
                'approved'       => ['ok', 'Request approved. Clinic + doctor user created.'],
                'rejected'       => ['ok', 'Request rejected.'],
                'duplicate'      => ['ok', 'Marked as duplicate.'],
                'approve_failed' => ['err', 'Approval failed. Check the error log.'],
                'invalid'        => ['err', 'Invalid request.'],
                'not_found'      => ['err', 'Request not found.'],
            ][$message] ?? null;
            ?>
            <?php if ($msgLabel): ?>
                <div class="mt-4 rounded-lg px-4 py-3 text-sm <?= $msgLabel[0] === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' ?>">
                    <?= htmlspecialchars($msgLabel[1]) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="mt-6 space-y-3">
            <?php foreach ($claims as $c): ?>
                <?php $isClaim = $c['type'] === 'claim'; $listing = $c['_listing'] ?? null; ?>
                <a href="/admin/claims/<?= (int) $c['id'] ?>"
                   class="block rounded-xl border bg-white p-4 hover:border-emerald-400 hover:shadow-sm transition">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide
                                    <?= $isClaim ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' ?>">
                                    <?= $isClaim ? 'Claim' : 'New listing' ?>
                                </span>
                                <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) $c['full_name']) ?></span>
                                <span class="text-sm text-slate-500">· <?= htmlspecialchars((string) ($c['clinic_name'] ?? '')) ?></span>
                            </div>
                            <div class="mt-1 text-sm text-slate-600">
                                <?= htmlspecialchars((string) ($c['specialty'] ?? '—')) ?> ·
                                <?= htmlspecialchars((string) ($c['city'] ?? '')) ?>
                                <?php if (!empty($c['state'])): ?>, <?= htmlspecialchars((string) $c['state']) ?><?php endif; ?>
                                · <span class="font-medium"><?= htmlspecialchars((string) $c['phone']) ?></span>
                                <?php if (!empty($c['phone_verified_at'])): ?>
                                    <span class="ml-1 text-emerald-600">✓ verified</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isClaim && $listing): ?>
                                <div class="mt-1 text-xs text-slate-500">
                                    Claiming → <span class="font-mono"><?= htmlspecialchars((string) $listing['name']) ?></span>
                                    (<?= htmlspecialchars((string) $listing['area']) ?>)
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($c['message'])): ?>
                                <div class="mt-2 text-xs text-slate-500 italic">&ldquo;<?= htmlspecialchars(mb_substr((string) $c['message'], 0, 160)) ?><?= mb_strlen((string) $c['message']) > 160 ? '…' : '' ?>&rdquo;</div>
                            <?php endif; ?>
                        </div>
                        <div class="text-right text-xs text-slate-400 flex-shrink-0">
                            <?= htmlspecialchars(date('M j, H:i', strtotime((string) $c['created_at']))) ?>
                            <?php if (!empty($c['document_path'])): ?>
                                <div class="mt-1 text-emerald-600">📄 doc attached</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>

            <?php if (empty($claims)): ?>
                <div class="rounded-xl border-2 border-dashed bg-white p-8 text-center">
                    <p class="text-sm text-slate-500">Nothing waiting. Inbox zero ✨</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
