<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Analytics</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/analytics/export/excel" class="ui-btn ui-btn-secondary ui-btn-sm">Export Excel</a>
            <a href="/analytics/export/tally" class="ui-btn ui-btn-secondary ui-btn-sm">Tally XML</a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="ui-card p-4">
            <h3 class="text-sm font-medium text-slate-600 mb-3">12-month revenue vs expenses</h3>
            <canvas id="revenue-chart" height="200"></canvas>
        </div>
        <div class="ui-card p-4">
            <h3 class="text-sm font-medium text-slate-600 mb-3">Patient flow</h3>
            <canvas id="flow-chart" height="200"></canvas>
        </div>
    </div>

    <div class="ui-card p-4">
        <h3 class="text-sm font-medium text-slate-600 mb-3">No-show heatmap (day × hour)</h3>
        <div id="heatmap" class="overflow-x-auto"></div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="ui-card p-4 lg:col-span-1">
            <h3 class="text-sm font-semibold">P&amp;L (<?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?>)</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt>Revenue</dt><dd>₹<?= number_format($pnl['revenue'], 2) ?></dd></div>
                <div class="flex justify-between"><dt>Expenses</dt><dd>₹<?= number_format($pnl['expenses'], 2) ?></dd></div>
                <div class="flex justify-between font-semibold border-t pt-2"><dt>Profit</dt><dd class="<?= $pnl['profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">₹<?= number_format($pnl['profit'], 2) ?></dd></div>
            </dl>
            <form method="get" class="mt-4 flex flex-wrap gap-2 text-xs">
                <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="rounded border px-2 py-1">
                <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="rounded border px-2 py-1">
                <button type="submit" class="rounded bg-slate-800 px-2 py-1 text-white">Apply</button>
            </form>
        </div>
        <div class="ui-card p-4 lg:col-span-2">
            <h3 class="text-sm font-semibold">Doctor performance</h3>
            <table class="mt-3 w-full text-sm">
                <thead class="text-xs text-slate-500"><tr><th class="text-left py-1">Doctor</th><th>Visits</th><th>Revenue</th></tr></thead>
                <tbody class="divide-y">
                    <?php foreach ($doctors as $d): ?>
                    <tr><td class="py-2"><?= htmlspecialchars($d['name']) ?></td><td class="text-center"><?= (int) ($d['visit_count'] ?? 0) ?></td><td class="text-right">₹<?= number_format((float) ($d['revenue'] ?? 0), 2) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="ui-card p-4">
        <h3 class="text-sm font-semibold">Record expense</h3>
        <form method="post" action="/analytics/expenses" class="mt-3 grid gap-3 sm:grid-cols-4 text-sm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <select name="category" class="rounded-lg border px-3 py-2">
                <?php foreach (['rent','utilities','salaries','consumables','equipment','marketing','maintenance','other'] as $c): ?>
                <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="description" required placeholder="Description" class="rounded-lg border px-3 py-2 sm:col-span-2">
            <input name="amount" type="number" step="0.01" required placeholder="Amount" class="rounded-lg border px-3 py-2">
            <input name="expense_date" type="date" value="<?= date('Y-m-d') ?>" class="rounded-lg border px-3 py-2">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-white sm:col-span-4 sm:w-auto">Add expense</button>
        </form>
        <?php if ($expenses !== []): ?>
        <ul class="mt-4 divide-y text-sm">
            <?php foreach (array_slice($expenses, 0, 10) as $e): ?>
            <li class="py-2 flex justify-between"><span><?= htmlspecialchars($e['description']) ?></span><span>₹<?= number_format((float) $e['amount'], 2) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
<script>
const rev = <?= json_encode($revenueSeries) ?>;
new Chart(document.getElementById('revenue-chart'), {
    type: 'bar',
    data: {
        labels: rev.labels,
        datasets: [
            { label: 'Revenue', data: rev.revenue, backgroundColor: '#0F9B6E' },
            { label: 'Expenses', data: rev.expenses, backgroundColor: '#f59e0b' },
        ],
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } },
});
const flow = <?= json_encode($flowSeries) ?>;
new Chart(document.getElementById('flow-chart'), {
    type: 'line',
    data: {
        labels: flow.labels,
        datasets: [
            { label: 'Visits', data: flow.visits, borderColor: '#0F9B6E', tension: 0.3 },
            { label: 'New patients', data: flow.new_patients, borderColor: '#3b82f6', tension: 0.3 },
        ],
    },
    options: { responsive: true },
});
const heatData = <?= json_encode($heatmap) ?>;
const days = ['','Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const hours = Array.from({length:24}, (_,i)=>i);
const cellW=28, cellH=22;
const svg = d3.select('#heatmap').append('svg')
    .attr('width', 60 + hours.length * cellW)
    .attr('height', 20 + 7 * cellH);
const maxC = d3.max(heatData, d=>d.count) || 1;
heatData.forEach(d => {
    svg.append('rect')
        .attr('x', 50 + d.hour * cellW)
        .attr('y', 16 + (d.day - 1) * cellH)
        .attr('width', cellW - 2)
        .attr('height', cellH - 2)
        .attr('fill', d3.interpolateReds(d.count / maxC))
        .append('title').text(days[d.day] + ' ' + d.hour + ':00 — ' + d.count);
});
hours.filter(h=>h%3===0).forEach(h=>{
    svg.append('text').attr('x',52+h*cellW).attr('y',12).attr('font-size',9).text(h);
});
[1,2,3,4,5,6,7].forEach(d=>{
    svg.append('text').attr('x',4).attr('y',28+(d-1)*cellH).attr('font-size',9).text(days[d]);
});
</script>
