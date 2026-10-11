<?php
/** /admin/health-tips — contributor posts by status (review queue first). */
use App\Services\HealthTipAdminService as HT;

$typeIcon = ['recipe' => '🥗', 'exercise' => '🧘', 'tip' => '💡'];
$messages = ['not_found' => 'That post no longer exists.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Health Tips · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <div class="flex items-baseline justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold">Health Tips — review</h1>
                <p class="text-sm text-slate-500">Recipes, exercises and tips from invited contributors. Nothing is public until you publish it.</p>
            </div>
            <div class="flex gap-2">
                <a href="/admin/health-tips/contributors" class="rounded border bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Contributors</a>
                <a href="<?= htmlspecialchars(HT::siteUrl('/health-tips')) ?>" target="_blank" rel="noopener" class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-900">View public page ↗</a>
            </div>
        </div>

        <?php if ($message && isset($messages[$message])): ?>
        <div class="mt-3 rounded bg-amber-50 border border-amber-200 px-4 py-2 text-sm text-amber-800"><?= htmlspecialchars($messages[$message]) ?></div>
        <?php endif; ?>

        <nav class="mt-4 flex flex-wrap gap-1 border-b">
            <?php foreach (HT::STATUSES as $key => $label): ?>
                <a href="/admin/health-tips?status=<?= $key ?>"
                   class="-mb-px border-b-2 px-3 py-2 text-sm <?= $status === $key ? 'border-slate-800 font-semibold text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <?= htmlspecialchars($label) ?>
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs <?= $key === 'submitted' && $counts[$key] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-600' ?>"><?= (int) $counts[$key] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="mt-4 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Post</th>
                        <th class="px-4 py-3">Contributor</th>
                        <th class="px-4 py-3"><?= $status === 'submitted' ? 'Sent' : 'Updated' ?></th>
                        <?php if ($status === 'published'): ?><th class="px-4 py-3">Views</th><?php endif; ?>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400"><?= $status === 'submitted' ? 'Nothing waiting for review. 🎉' : 'No posts here.' ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 flex-none items-center justify-center overflow-hidden rounded bg-emerald-50 text-xl">
                                    <?php if ($r['cover_id']): ?>
                                        <img src="/admin/health-tips/media/<?= (int) $r['cover_id'] ?>" alt="" class="h-full w-full object-cover" loading="lazy">
                                    <?php else: ?><?= $typeIcon[$r['type']] ?? '•' ?><?php endif; ?>
                                </div>
                                <div>
                                    <a href="/admin/health-tips/<?= (int) $r['id'] ?>" class="font-medium text-slate-900 hover:underline"><?= htmlspecialchars($r['title']) ?></a>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars(HT::TYPES[$r['type']] ?? $r['type']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <?= htmlspecialchars($r['display_name']) ?>
                            <?php if ($r['contributor_status'] !== 'active'): ?><span class="ml-1 rounded bg-slate-200 px-1.5 text-xs text-slate-600">suspended</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <?php $when = $status === 'submitted' ? $r['submitted_at'] : $r['updated_at']; ?>
                            <?= $when ? htmlspecialchars(date('d M Y, h:i A', strtotime((string) $when))) : '—' ?>
                        </td>
                        <?php if ($status === 'published'): ?><td class="px-4 py-3"><?= (int) $r['view_count'] ?></td><?php endif; ?>
                        <td class="px-4 py-3 text-right">
                            <a href="/admin/health-tips/<?= (int) $r['id'] ?>" class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900"><?= $status === 'submitted' ? 'Review' : 'Open' ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($rows) === HT::PER_PAGE || $page > 1): ?>
        <div class="mt-3 flex justify-between text-sm">
            <?php if ($page > 1): ?><a class="text-slate-700 hover:underline" href="/admin/health-tips?status=<?= $status ?>&page=<?= $page - 1 ?>">← Previous</a><?php else: ?><span></span><?php endif; ?>
            <?php if (count($rows) === HT::PER_PAGE): ?><a class="text-slate-700 hover:underline" href="/admin/health-tips?status=<?= $status ?>&page=<?= $page + 1 ?>">Next →</a><?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
