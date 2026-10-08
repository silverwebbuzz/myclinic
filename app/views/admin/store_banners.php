<?php
/** @var list<array<string,mixed>> $rows */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$in = 'mt-1 w-full rounded border px-2 py-1.5 text-sm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store banners — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-5xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">Homepage banners</h1>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <p class="text-sm text-slate-500">Up to 3 active banners show on the store homepage, below the trust strip. Wide images (about 1200×400, JPG/PNG/WEBP, max 3 MB). Don't advertise discounts on infant food or feeding bottles.</p>

    <form method="post" action="/admin/store/banners" enctype="multipart/form-data" class="grid gap-3 rounded-xl border bg-white p-5 shadow-sm sm:grid-cols-3">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <h2 class="font-semibold sm:col-span-3">New banner</h2>
        <label class="text-sm">Image<input type="file" name="image" required accept="image/jpeg,image/png,image/webp" class="mt-1 w-full text-sm"></label>
        <label class="text-sm">Alt text / title<input name="title" maxlength="190" class="<?= $in ?>" placeholder="Monsoon immunity essentials"></label>
        <label class="text-sm">Link<input name="link" class="<?= $in ?>" placeholder="/store/goal/immunity"></label>
        <label class="text-sm">Show from<input type="date" name="starts_at" class="<?= $in ?>"></label>
        <label class="text-sm">Until<input type="date" name="ends_at" class="<?= $in ?>"></label>
        <label class="text-sm">Order<input type="number" name="sort_order" value="0" min="0" class="<?= $in ?>"></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <div class="sm:col-span-2 sm:text-right"><button class="rounded bg-slate-800 px-4 py-1.5 text-sm text-white">Add banner</button></div>
    </form>

    <div class="space-y-3" data-filter="status:Status">
        <?php foreach ($rows as $b): ?>
            <form method="post" action="/admin/store/banners" enctype="multipart/form-data" data-filter-row data-f-status="<?= (int) $b['is_active'] === 1 ? 'Active' : 'Inactive' ?>" class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4 text-sm shadow-sm <?= (int) $b['is_active'] === 1 ? '' : 'opacity-60' ?>">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <img src="<?= $e($b['image_path']) ?>" alt="" class="h-16 w-48 rounded object-cover">
                <label>Title<input name="title" value="<?= $e($b['title'] ?? '') ?>" class="<?= $in ?>"></label>
                <label>Link<input name="link" value="<?= $e($b['link'] ?? '') ?>" class="<?= $in ?>"></label>
                <label>From<input type="date" name="starts_at" value="<?= $e(substr((string) ($b['starts_at'] ?? ''), 0, 10)) ?>" class="<?= $in ?>"></label>
                <label>Until<input type="date" name="ends_at" value="<?= $e(substr((string) ($b['ends_at'] ?? ''), 0, 10)) ?>" class="<?= $in ?>"></label>
                <label>Order<input type="number" name="sort_order" value="<?= (int) $b['sort_order'] ?>" class="<?= $in ?> w-16"></label>
                <label class="flex items-center gap-1 pb-2"><input type="checkbox" name="is_active" value="1" <?= (int) $b['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
                <button class="rounded bg-slate-800 px-3 py-1.5 text-white">Save</button>
            </form>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
