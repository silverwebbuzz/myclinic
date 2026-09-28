<?php
/**
 * /admin/store/brands — brands (sellers can add new ones; admin tidies up).
 *
 * @var list<array<string,mixed>> $rows
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store brands — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-4xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">Store brands</h1>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <p class="text-sm text-slate-500">Sellers can type a new brand while adding a product. Rename duplicates or deactivate junk here. Featured brands appear in "Shop by brand".</p>

    <form method="post" action="/admin/store/brands" class="flex gap-2 rounded-xl border bg-white p-4 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input name="name" required maxlength="160" placeholder="Add a brand, e.g. Omron" class="flex-1 rounded border px-3 py-1.5 text-sm">
        <button class="rounded bg-slate-800 px-4 py-1.5 text-sm text-white">Add</button>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
        <?php if (!$rows): ?><p class="p-6 text-center text-sm text-slate-400">No brands yet.</p><?php endif; ?>
        <?php foreach ($rows as $b): ?>
            <form method="post" action="/admin/store/brands" class="flex flex-wrap items-center gap-3 border-b px-4 py-2 text-sm last:border-0 <?= empty($b['is_active']) ? 'opacity-50' : '' ?>">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <input name="name" value="<?= $e($b['name']) ?>" class="flex-1 rounded border px-2 py-1">
                <span class="w-24 text-xs text-slate-500"><?= (int) $b['product_count'] ?> product(s)</span>
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_featured" value="1" <?= !empty($b['is_featured']) ? 'checked' : '' ?>> Featured</label>
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" <?= !empty($b['is_active']) ? 'checked' : '' ?>> Active</label>
                <button class="rounded bg-slate-800 px-2.5 py-1 text-xs text-white">Save</button>
            </form>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
