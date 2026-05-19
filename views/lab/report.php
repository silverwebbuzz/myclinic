<div class="mx-auto max-w-2xl space-y-4 p-6">
    <h1 class="text-xl font-semibold">Lab report</h1>
    <p class="text-sm text-slate-600"><?= htmlspecialchars($order['patient_name'] ?? '') ?> — <?= htmlspecialchars($order['test_name'] ?? '') ?></p>
    <table class="w-full text-sm border-collapse">
        <thead><tr class="bg-slate-100"><th class="p-2 text-left">Parameter</th><th class="p-2">Value</th><th class="p-2">Flag</th></tr></thead>
        <tbody>
        <?php foreach ($order['results'] ?? [] as $r): ?>
        <tr class="border-b">
            <td class="p-2"><?= htmlspecialchars($r['parameter_name'] ?? '') ?></td>
            <td class="p-2 text-center"><?= htmlspecialchars($r['value'] ?? '') ?></td>
            <td class="p-2 text-center <?= str_starts_with((string)($r['flag']??''), 'critical') ? 'text-red-600 font-bold' : '' ?>"><?= htmlspecialchars($r['flag'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
