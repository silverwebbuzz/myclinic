<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6">
        <?php if (!empty($_GET['message'])): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($_GET['message']) ?></p>
        <?php endif; ?>
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border bg-white p-4">
                <p class="text-xs text-slate-500">MRR</p>
                <p class="text-2xl font-bold">$<?= number_format($metrics['mrr'] ?? 0, 0) ?></p>
            </div>
            <div class="rounded-xl border bg-white p-4">
                <p class="text-xs text-slate-500">ARR</p>
                <p class="text-2xl font-bold">$<?= number_format($metrics['arr'] ?? 0, 0) ?></p>
            </div>
            <div class="rounded-xl border bg-white p-4">
                <p class="text-xs text-slate-500">Clinics</p>
                <p class="text-2xl font-bold"><?= (int) ($metrics['clinics'] ?? 0) ?></p>
            </div>
            <div class="rounded-xl border bg-white p-4">
                <p class="text-xs text-slate-500">At churn risk</p>
                <p class="text-2xl font-bold text-amber-600"><?= (int) ($metrics['at_risk'] ?? 0) ?></p>
            </div>
        </div>
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border bg-white p-4">
                <h2 class="font-semibold">MRR trend</h2>
                <canvas id="mrrChart" height="200"></canvas>
            </div>
            <div class="rounded-xl border bg-white p-4">
                <h2 class="font-semibold">Clinics by plan</h2>
                <canvas id="planChart" height="200"></canvas>
            </div>
        </div>
        <form method="post" action="/admin/churn/run" class="mt-6">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Run churn scan + outreach</button>
        </form>
    </main>
    <script>
    const trend = <?= json_encode($metrics['mrr_trend'] ?? [], JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('mrrChart'), {
        type: 'line',
        data: {
            labels: trend.map(r => r.month),
            datasets: [{ label: 'MRR (USD)', data: trend.map(r => r.mrr), borderColor: '#0F9B6E', fill: false }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
    const byPlan = <?= json_encode($metrics['by_plan'] ?? [], JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('planChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(byPlan),
            datasets: [{ data: Object.values(byPlan), backgroundColor: ['#94a3b8','#0F9B6E','#3b82f6','#8b5cf6'] }]
        }
    });
    </script>
</body>
</html>
