<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(ucwords(str_replace('-', ' ', $specialty))) ?> in <?= htmlspecialchars(ucwords(str_replace('-', ' ', $city))) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
    <header class="border-b bg-white px-4 py-4">
        <a href="/doctors" class="text-emerald-700 hover:underline">← Directory</a>
        <h1 class="mt-2 text-xl font-bold"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $specialty))) ?> — <?= htmlspecialchars(ucwords(str_replace('-', ' ', $city))) ?></h1>
    </header>
    <main class="mx-auto max-w-3xl space-y-3 p-4">
        <?php foreach ($doctors as $d): ?>
        <a href="/doctors/profile/<?= urlencode($d['slug'] ?? '') ?>" class="block rounded-xl border bg-white p-4 hover:shadow">
            <p class="font-medium"><?= htmlspecialchars($d['full_name'] ?? '') ?></p>
            <p class="text-sm text-slate-600"><?= htmlspecialchars($d['specialty_primary'] ?? '') ?> · <?= htmlspecialchars($d['city'] ?? '') ?></p>
        </a>
        <?php endforeach; ?>
        <?php if (empty($doctors)): ?>
        <p class="text-slate-500">No doctors listed yet. Run <code>php workers/directory_sync.php</code>.</p>
        <?php endif; ?>
    </main>
</body>
</html>
