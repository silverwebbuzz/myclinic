<form method="post" action="/settings/hours" class="space-y-6 rounded-xl border bg-white p-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <h2 class="text-lg font-semibold">Working hours</h2>
    <p class="text-sm text-slate-500">Saving regenerates doctor schedule slots.</p>
    <?php require dirname(__DIR__, 2) . '/partials/working-hours-form.php'; ?>
    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save hours</button>
</form>
