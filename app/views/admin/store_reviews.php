<?php
/** @var list<array<string,mixed>> $rows @var string $status */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store reviews — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-5xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">Product reviews</h1>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <p class="text-sm text-slate-500">Verified buyers only. Reject reviews with medical claims ("cured my…"), personal data, abuse or spam. Ratings update when you publish or reject.</p>
    <nav class="flex gap-2 text-sm">
        <?php foreach (['pending' => 'To moderate', 'published' => 'Published', 'rejected' => 'Rejected', '' => 'All'] as $k => $l): ?>
            <a href="?status=<?= $k ?>" class="rounded-full px-3 py-1 <?= $status === $k ? 'bg-slate-800 text-white' : 'bg-white' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="space-y-3">
        <?php if (!$rows): ?><p class="rounded-xl border bg-white p-8 text-center text-sm text-slate-400">Nothing here.</p><?php endif; ?>
        <?php foreach ($rows as $rv): ?>
            <article class="rounded-xl border bg-white p-4 text-sm shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span><span class="text-amber-500"><?= str_repeat('★', (int) $rv['rating']) ?></span><span class="text-slate-300"><?= str_repeat('★', 5 - (int) $rv['rating']) ?></span>
                        <strong class="ml-1"><?= $e($rv['title'] ?? '') ?></strong></span>
                    <span class="text-xs text-slate-500"><?= $e($rv['product_name']) ?> · <?= $e($rv['vendor_name']) ?> · <?= $e(\App\Support\IndianDate::date($rv['created_at'])) ?> · <?= $e($rv['status']) ?></span>
                </div>
                <?php if (!empty($rv['body'])): ?><p class="mt-2 whitespace-pre-line text-slate-700"><?= $e($rv['body']) ?></p><?php endif; ?>
                <?php if (!empty($rv['vendor_reply'])): ?><p class="mt-2 rounded bg-slate-50 px-3 py-2 text-slate-600"><strong>Seller:</strong> <?= $e($rv['vendor_reply']) ?></p><?php endif; ?>
                <form method="post" action="/admin/store/reviews/<?= (int) $rv['id'] ?>" class="mt-3 flex gap-2">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="back" value="<?= $e($status) ?>">
                    <?php if ($rv['status'] !== 'published'): ?><button name="decision" value="published" class="rounded bg-emerald-600 px-3 py-1 text-white">Publish</button><?php endif; ?>
                    <?php if ($rv['status'] !== 'rejected'): ?><button name="decision" value="rejected" class="rounded border border-red-300 px-3 py-1 text-red-700">Reject</button><?php endif; ?>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
