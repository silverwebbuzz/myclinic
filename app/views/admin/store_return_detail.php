<?php /** @var array<string,mixed> $r */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars((string) $r['return_no']) ?> — Return</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-4xl space-y-4 p-6">
    <a href="/admin/store/returns" class="text-sm text-sky-700 hover:underline">← Returns</a>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php
    $base = '/admin/store/returns';
    $isAdmin = true;
    require dirname(__DIR__) . '/components/store_return_body.php';
    ?>
    <p class="text-sm"><a href="/admin/store/orders/<?= (int) $r['order_id'] ?>" class="text-sky-700 hover:underline">Open the order →</a></p>
</main>
</body>
</html>
