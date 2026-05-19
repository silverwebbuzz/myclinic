<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'ManageClinic') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b bg-white px-6 py-4 flex justify-between items-center">
        <a href="/dashboard" class="font-semibold text-emerald-600"><?= htmlspecialchars($clinic['name'] ?? 'Clinic') ?></a>
        <nav class="flex items-center gap-4 text-sm text-slate-600">
            <a href="/settings/password" class="hover:text-emerald-600">Password</a>
            <a href="/settings/sessions" class="hover:text-emerald-600">Sessions</a>
            <form method="post" action="/logout" class="inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <button type="submit" class="text-slate-500 hover:text-slate-700">Log out</button>
            </form>
        </nav>
    </header>
    <main class="mx-auto max-w-lg p-6">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
