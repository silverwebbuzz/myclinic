<?php
/** /admin/wellness/comments — held + reported comments & questions (Wellness Hub Phase 5). */
use App\Services\Wellness\CreatorService as CS;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tabs = ['held' => 'Waiting for review', 'reported' => 'Reported', 'hidden' => 'Hidden', 'recent' => 'Recent (live)'];
$why = ['link' => 'has a link', 'email' => 'has an email', 'phone' => 'has a phone number', 'blocked_word' => 'blocked phrase', 'reported' => 'reported 3+ times'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Comments &amp; questions · Wellness Hub · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Wellness Hub — comments &amp; questions</h1>
        <p class="text-sm text-slate-500">Patients’ comments go live at once, except those with a link, phone number, email or a blocked phrase — they wait here. 3 reports also send a comment here. Approving one sends any emails it would have sent (new question / answered).</p>
        <?php if (!$enabled): ?>
            <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">Run <code>2026_12_01_wellness_hub_phase5.sql</code> first.</div>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($tabs as $k => $l): $n = $k === 'held' ? $counts['held'] : ($k === 'reported' ? $counts['reported'] : null); ?>
                <a href="/admin/wellness/comments?tab=<?= $k ?>" class="rounded-full px-3 py-1.5 text-xs font-semibold <?= $tab === $k ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' ?>"><?= $h($l) ?><?= $n !== null ? ' <span class="opacity-70">' . (int) $n . '</span>' : '' ?></a>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 space-y-3">
            <?php if (!$rows): ?><div class="ui-card p-6 text-center text-sm text-slate-500">Nothing here.</div><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <div class="ui-card p-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <strong class="text-slate-800"><?= $h($r['author']['name']) ?></strong>
                        <?php if ($r['author']['badge']): ?><span class="text-sky-700">✓ <?= $h($r['author']['badge']) ?></span><?php endif; ?>
                        <span><?= $h($r['p_phone']) ?></span>
                        <span class="rounded bg-slate-100 px-1.5"><?= $h($r['kind']) ?></span>
                        <?php if ((int) $r['is_creator_answer'] === 1): ?><span class="rounded bg-emerald-100 px-1.5 text-emerald-800">creator’s answer</span><?php endif; ?>
                        <?php if ($r['item']): ?>· on <a class="text-sky-700 underline" target="_blank" rel="noopener" href="<?= $h(CS::siteUrl($r['item']['path'] . '#c-' . (int) $r['id'])) ?>"><?= $h($r['item']['title']) ?></a><?php endif; ?>
                        <span>· <?= $h(date('d M Y, h:i A', strtotime((string) $r['created_at']))) ?></span>
                        <?php if ($r['hold_reason']): ?><span class="rounded bg-amber-100 px-1.5 text-amber-800"><?= $h($why[$r['hold_reason']] ?? $r['hold_reason']) ?></span><?php endif; ?>
                        <?php if ($r['open_reasons']): ?><span class="rounded bg-rose-100 px-1.5 text-rose-800">reports: <?= $h(str_replace(',', ', ', (string) $r['open_reasons'])) ?></span><?php endif; ?>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-800"><?= $h($r['body']) ?></p>
                    <form method="post" action="/admin/wellness/comments/<?= (int) $r['id'] ?>" class="mt-3 flex flex-wrap gap-2">
                        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="tab" value="<?= $h($tab) ?>">
                        <?php if ($r['status'] !== 'visible'): ?><button name="action" value="approve" class="rounded bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Approve (show)</button><?php endif; ?>
                        <?php if ($r['status'] !== 'hidden'): ?><button name="action" value="hide" class="rounded border bg-white px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hide</button><?php endif; ?>
                        <button name="action" value="delete" class="rounded border bg-white px-3 py-1 text-xs" onclick="return confirm('Delete permanently from the page?')">Delete</button>
                        <?php if ($r['open_reasons'] && $r['status'] === 'visible'): ?><button name="action" value="dismiss" class="rounded border bg-white px-3 py-1 text-xs">Dismiss reports</button><?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
