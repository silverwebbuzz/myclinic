<?php
/** /admin/wellness/events/new — list a camp / event as "eClinicPro Desk" (publishes at once). */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isAdmin = true;
$isCamp = $form['kind'] === 'free_camp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>List a <?= $isCamp ? 'free camp' : 'event' ?> · Wellness Hub · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-3xl px-6 py-6">
        <a href="/admin/wellness/events" class="text-xs text-slate-500 hover:underline">← Camps &amp; events</a>
        <h1 class="mt-1 text-xl font-semibold">List a <?= $isCamp ? 'free health camp' : 'event' ?> as eClinicPro Desk</h1>
        <p class="text-sm text-slate-500">For government / public camps you found in an official source. It goes live as soon as you save.
            <a class="text-sky-700 underline" href="/admin/wellness/events/new?kind=<?= $isCamp ? 'event' : 'free_camp' ?>">Switch to <?= $isCamp ? 'event' : 'free camp' ?></a></p>

        <?php if ($error): ?>
            <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/admin/wellness/events/new" enctype="multipart/form-data" class="mt-4">
            <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
            <?php require dirname(__DIR__) . '/creator/_event_fields.php'; ?>
            <button class="mt-4 rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Publish now</button>
        </form>
    </main>
</body>
</html>
