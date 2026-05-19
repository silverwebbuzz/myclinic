<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Portal') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>:root { --brand: <?= htmlspecialchars($brandColor ?? '#0F9B6E') ?>; }</style>
</head>
<body class="min-h-screen bg-slate-50">
    <header class="border-b bg-white px-4 py-3">
        <p class="text-sm font-semibold" style="color: var(--brand)"><?= htmlspecialchars($clinic['name'] ?? 'Clinic') ?></p>
        <p class="text-xs text-slate-500">Patient portal</p>
    </header>
    <main class="mx-auto max-w-lg p-4">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
