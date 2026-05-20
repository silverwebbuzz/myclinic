<section x-show="activeTab === 'photos'" class="rounded-xl border bg-white p-6 space-y-4">
    <h3 class="font-semibold">Before / after photos</h3>
    <?php if (!empty($_GET['photo_uploaded'])): ?><p class="text-sm text-emerald-600">Photo uploaded.</p><?php endif; ?>

    <form method="post" action="/visits/<?= (int) $visit['id'] ?>/photos" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2 text-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label class="block">Type
            <select name="type" class="mt-1 w-full rounded border px-2 py-1">
                <option value="before">Before</option>
                <option value="after">After</option>
                <option value="progress">Progress</option>
            </select>
        </label>
        <label class="block">Label<input name="condition_label" class="mt-1 w-full rounded border px-2 py-1" placeholder="e.g. Acne"></label>
        <label class="block sm:col-span-2">Image<input type="file" name="photo" accept="image/*" required class="mt-1 w-full text-sm"></label>
        <label class="flex gap-2 text-xs sm:col-span-2"><input type="checkbox" name="is_public" value="1"> Mark public (triggers webhook stub)</label>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-white sm:col-span-2 sm:w-auto">Upload</button>
    </form>

    <?php if ($visitPhotos !== []): ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <?php foreach ($visitPhotos as $ph): ?>
        <a href="<?= htmlspecialchars($ph['photo_path']) ?>" target="_blank" class="block rounded-lg border overflow-hidden">
            <img src="<?= htmlspecialchars($ph['photo_path']) ?>" alt="" class="h-32 w-full object-cover">
            <p class="p-2 text-xs capitalize"><?= htmlspecialchars($ph['type'] ?? '') ?> <?= htmlspecialchars($ph['condition_label'] ?? '') ?></p>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
