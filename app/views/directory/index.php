<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a doctor — ManageClinic Directory</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50">
    <header class="border-b bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="/doctors" class="text-lg font-semibold text-emerald-700">ManageClinic Doctors</a>
            <a href="/register" class="text-sm text-slate-600 hover:underline">For clinics</a>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-8">
        <h1 class="text-2xl font-bold">Find doctors near you</h1>
        <p class="mt-1 text-slate-600">Verified profiles from clinics on ManageClinic.</p>

        <h2 class="mt-8 text-sm font-semibold uppercase text-slate-500">Browse by city</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($cities as $city): ?>
            <a href="/doctors/<?= urlencode($city['slug'] ?? '') ?>/general-physician"
               class="rounded-full border bg-white px-4 py-1.5 text-sm hover:border-emerald-300">
                <?= htmlspecialchars($city['name'] ?? '') ?>
                <span class="text-slate-400">(<?= (int) ($city['doctor_count'] ?? 0) ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($featured)): ?>
        <h2 class="mt-10 text-sm font-semibold uppercase text-slate-500">Featured</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <?php foreach ($featured as $d): ?>
            <a href="/doctors/profile/<?= urlencode($d['slug'] ?? '') ?>" class="rounded-xl border bg-white p-4 hover:shadow-md">
                <p class="font-medium"><?= htmlspecialchars($d['full_name'] ?? '') ?></p>
                <p class="text-sm text-slate-600"><?= htmlspecialchars($d['specialty_primary'] ?? '') ?></p>
                <p class="mt-1 text-xs text-amber-600">★ <?= number_format((float) ($d['avg_rating'] ?? 0), 1) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
