<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review moderation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6">
        <h1 class="text-xl font-semibold">Pending doctor reviews</h1>
        <div class="mt-4 space-y-3">
            <?php foreach ($reviews as $r): ?>
            <div class="rounded-xl border bg-white p-4">
                <p class="font-medium"><?= htmlspecialchars($r['reviewer_name'] ?? '') ?> — <?= (int) ($r['rating'] ?? 0) ?>★</p>
                <p class="mt-1 text-sm text-slate-600"><?= htmlspecialchars($r['body'] ?? '') ?></p>
                <div class="mt-3 flex gap-2">
                    <form method="post" action="/admin/reviews/approve">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <input type="hidden" name="review_id" value="<?= (int) ($r['id'] ?? 0) ?>">
                        <button type="submit" class="rounded bg-emerald-600 px-3 py-1 text-sm text-white">Approve</button>
                    </form>
                    <form method="post" action="/admin/reviews/reject">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <input type="hidden" name="review_id" value="<?= (int) ($r['id'] ?? 0) ?>">
                        <button type="submit" class="rounded border px-3 py-1 text-sm">Reject</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($reviews)): ?>
            <p class="text-sm text-slate-500">No pending reviews.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
