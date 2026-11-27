<?php
/** /admin/wellness/events — camp & event review queue (Wellness Hub, Phase 2). */
use App\Services\Wellness\EventService as ES;
use App\Services\Wellness\CreatorService as CS;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tabLabels = ['submitted' => 'Waiting for review', 'upcoming' => 'Live · upcoming', 'ended' => 'Live · ended', 'changes_requested' => 'Changes asked',
    'draft' => 'Drafts', 'rejected' => 'Rejected', 'unpublished' => 'Unpublished', 'cancelled' => 'Cancelled'];
$countFor = static function (string $t) use ($counts): ?int {
    return in_array($t, ['upcoming', 'ended'], true) ? null : (int) ($counts[$t] ?? 0);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Camps &amp; events · Wellness Hub · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <div class="flex flex-wrap items-center gap-2">
            <div>
                <h1 class="text-xl font-semibold">Wellness Hub — camps &amp; events</h1>
                <p class="text-sm text-slate-500">Nothing is public until you publish it. Public pages: <a class="text-sky-700 underline" target="_blank" rel="noopener" href="<?= $h(CS::siteUrl('/wellness/camps')) ?>">/wellness/camps</a> · <a class="text-sky-700 underline" target="_blank" rel="noopener" href="<?= $h(CS::siteUrl('/wellness/events')) ?>">/wellness/events</a></p>
            </div>
            <span class="flex-1"></span>
            <a href="/admin/wellness/events/new?kind=free_camp" class="rounded bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">+ List a free camp</a>
            <a href="/admin/wellness/events/new?kind=event" class="rounded border bg-white px-3 py-2 text-xs font-semibold hover:bg-slate-50">+ List an event</a>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <?php foreach ($tabs as $t): $n = $countFor($t); ?>
                <a href="/admin/wellness/events?status=<?= $h($t) ?>" class="rounded-full px-3 py-1.5 text-xs font-semibold <?= $status === $t ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' ?>">
                    <?= $h($tabLabels[$t]) ?><?= $n !== null ? ' <span class="ml-1 opacity-70">' . $n . '</span>' : '' ?>
                </a>
            <?php endforeach; ?>
            <span class="flex-1"></span>
            <form method="get" action="/admin/wellness/events" class="flex gap-2">
                <input type="hidden" name="status" value="<?= $h($status) ?>">
                <input name="q" value="<?= $h($q) ?>" placeholder="Title, city or creator" class="w-56 rounded border px-2 py-1.5 text-sm">
                <button class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Search</button>
            </form>
        </div>

        <div class="mt-4 overflow-x-auto ui-card">
            <table class="w-full min-w-[860px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Listing</th><th class="px-4 py-3">When</th><th class="px-4 py-3">Where</th><th class="px-4 py-3">Creator</th><th class="px-4 py-3">Fee</th><th class="px-4 py-3"><?= $status === 'submitted' ? 'Sent' : 'Views' ?></th></tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (!$rows): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nothing here.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="/admin/wellness/events/<?= (int) $r['id'] ?>" class="font-medium text-sky-700 hover:underline"><?= $h($r['title']) ?></a>
                                <div class="text-xs text-slate-500"><?= $h(ES::KINDS[$r['kind']]) ?> · <?= $h($r['category_slug']) ?>
                                    <?php if ((int) $r['open_reports'] > 0): ?><span class="ml-1 rounded bg-rose-100 px-1.5 font-semibold text-rose-800"><?= (int) $r['open_reports'] ?> report<?= (int) $r['open_reports'] > 1 ? 's' : '' ?></span><?php endif; ?>
                                    <?php if ($r['published_at'] && $r['status'] === 'submitted'): ?><span class="ml-1 rounded bg-amber-100 px-1.5 font-semibold text-amber-800">edit of a live listing</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700"><?= $h(ES::whenText((string) $r['start_at'], (string) $r['end_at'])) ?></td>
                            <td class="px-4 py-3 text-xs text-slate-700"><?= $h($r['mode'] === 'online' ? 'Online' : ($r['city_name'] ?? '—')) ?></td>
                            <td class="px-4 py-3 text-xs"><a href="/admin/wellness/creators/<?= (int) $r['creator_id'] ?>" class="text-sky-700 hover:underline"><?= $h($r['creator_name']) ?></a><?= $r['verify_status'] === 'verified' ? ' <span class="text-sky-700">✓</span>' : '' ?>
                                <div class="text-slate-500"><?= $h(CS::TYPE_SHORT[$r['creator_type']] ?? '') ?></div></td>
                            <td class="px-4 py-3 text-xs"><?= (float) $r['fee_amount'] > 0 ? '₹' . $h(number_format((float) $r['fee_amount'], 0)) : 'Free' ?></td>
                            <td class="px-4 py-3 text-xs text-slate-500"><?= $status === 'submitted' ? $h($r['submitted_at'] ? date('d M Y, h:i A', strtotime((string) $r['submitted_at'])) : '—') : (int) $r['view_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
