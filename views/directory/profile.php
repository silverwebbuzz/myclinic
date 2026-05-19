<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($profile['full_name'] ?? 'Doctor') ?> — ManageClinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="application/ld+json"><?= $jsonLd ?? '{}' ?></script>
</head>
<body class="bg-slate-50">
    <header class="border-b bg-white px-4 py-4">
        <a href="/doctors" class="text-sm text-emerald-700 hover:underline">← Directory</a>
    </header>
    <main class="mx-auto max-w-2xl p-6">
        <h1 class="text-2xl font-bold"><?= htmlspecialchars($profile['full_name'] ?? '') ?></h1>
        <p class="text-emerald-700"><?= htmlspecialchars($profile['specialty_primary'] ?? '') ?></p>
        <p class="mt-1 text-sm text-slate-600">★ <?= number_format((float) ($profile['avg_rating'] ?? 0), 1) ?> (<?= (int) ($profile['total_reviews'] ?? 0) ?> reviews)</p>
        <?php if (!empty($profile['bio'])): ?>
        <p class="mt-4 text-slate-700"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
        <?php endif; ?>
        <?php foreach ($locations as $loc): ?>
        <div class="mt-4 rounded-lg border bg-white p-4 text-sm">
            <p class="font-medium"><?= htmlspecialchars($loc['clinic_name'] ?? 'Clinic') ?></p>
            <p class="text-slate-600"><?= htmlspecialchars($loc['address'] ?? '') ?></p>
            <p><?= htmlspecialchars($loc['city'] ?? '') ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (!empty($reviews)): ?>
        <h2 class="mt-8 font-semibold">Reviews</h2>
        <?php foreach ($reviews as $r): ?>
        <div class="mt-2 rounded border bg-white p-3 text-sm">
            <p class="font-medium"><?= htmlspecialchars($r['reviewer_name'] ?? '') ?> — <?= (int) ($r['rating'] ?? 0) ?>★</p>
            <p class="text-slate-600"><?= htmlspecialchars($r['body'] ?? '') ?></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
