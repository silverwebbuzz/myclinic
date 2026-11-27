<?php
/** /admin/wellness/creators — Health Creators (Wellness Hub, Phase 1). */
use App\Services\Wellness\CreatorAdminService as CA;
use App\Services\Wellness\CreatorService as CS;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$permPill = ['approved' => 'bg-emerald-100 text-emerald-800', 'applied' => 'bg-amber-100 text-amber-800', 'rejected' => 'bg-rose-100 text-rose-800', 'suspended' => 'bg-slate-200 text-slate-700'];
$short = ['recipe' => 'Recipes', 'exercise' => 'Exercises', 'tip' => 'Tips', 'video' => 'Videos', 'free_camp' => 'Free camps', 'event' => 'Events'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Health Creators · Wellness Hub · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Wellness Hub — Health Creators</h1>
        <p class="text-sm text-slate-500">Creators sign up at <strong>app.eclinicpro.com/creator</strong>. Approve what each one may post, and check documents before giving a Verified badge.</p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <?php foreach (CA::TABS as $k => $lbl): ?>
                <a href="/admin/wellness/creators?tab=<?= $h($k) ?>"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold <?= $tab === $k ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' ?>">
                    <?= $h($lbl) ?> <span class="ml-1 opacity-70"><?= (int) ($counts[$k] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
            <span class="flex-1"></span>
            <form method="get" action="/admin/wellness/creators" class="flex gap-2">
                <input type="hidden" name="tab" value="<?= $h($tab) ?>">
                <input name="q" value="<?= $h($q) ?>" placeholder="Name, email, city or phone" class="w-60 rounded border px-2 py-1.5 text-sm">
                <button class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Search</button>
            </form>
        </div>

        <div class="mt-4 overflow-x-auto ui-card">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Creator</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Permissions</th><th class="px-4 py-3">Badge</th><th class="px-4 py-3">Joined</th></tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (!$rows): ?>
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nothing here.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="/admin/wellness/creators/<?= (int) $r['id'] ?>" class="font-medium text-sky-700 hover:underline"><?= $h($r['display_name']) ?></a>
                                <div class="text-xs text-slate-500"><?= $h(CS::TYPE_SHORT[$r['creator_type']] ?? $r['creator_type']) ?><?= $r['org_name'] ? ' · ' . $h($r['org_name']) : '' ?><?= $r['city'] ? ' · ' . $h($r['city']) : '' ?></div>
                                <?php if ($r['status'] === 'suspended'): ?><span class="mt-1 inline-block rounded bg-rose-100 px-1.5 text-[11px] font-semibold text-rose-800">Suspended</span><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600"><?= $h($r['identity_phone'] ?? '') ?><div><?= $h($r['email'] ?? '') ?></div></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach (array_filter(explode(',', (string) $r['perms'])) as $pair): [$t, $s] = array_pad(explode(':', $pair), 2, ''); ?>
                                        <span class="rounded px-1.5 py-0.5 text-[11px] font-medium <?= $permPill[$s] ?? 'bg-slate-100' ?>"><?= $h($short[$t] ?? $t) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <?php $vs = (string) $r['verify_status']; ?>
                                <?= $vs === 'verified' ? '<span class="font-semibold text-sky-700">✓ Verified</span>' : ($vs === 'pending' ? '<span class="font-semibold text-amber-700">Waiting</span>' : ($vs === 'none' ? '<span class="text-slate-400">—</span>' : $h(ucfirst($vs)))) ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500"><?= $h(date('d M Y', strtotime((string) $r['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-slate-500">Colours: <span class="rounded bg-amber-100 px-1">waiting</span> <span class="rounded bg-emerald-100 px-1">approved</span> <span class="rounded bg-rose-100 px-1">not approved</span> <span class="rounded bg-slate-200 px-1">paused</span></p>
    </main>
</body>
</html>
