<div x-data="dashboardPage()" x-init="startRefresh()" class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php
        $tiles = [
            ['label' => 'Patients today', 'key' => 'patients_today', 'icon' => '👤'],
            ['label' => 'Appointments pending', 'key' => 'appointments_pending', 'icon' => '📅'],
            ['label' => 'Revenue today', 'key' => 'revenue_today', 'icon' => '💰', 'money' => true],
            ['label' => 'Follow-ups due', 'key' => 'follow_ups_due', 'icon' => '🔔'],
        ];
        foreach ($tiles as $tile):
            $val = $stats[$tile['key']] ?? 0;
            $display = !empty($tile['money'])
                ? ($currency ?? 'INR') . ' ' . number_format((float) $val, 2)
                : (string) (int) $val;
        ?>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($tile['label']) ?></p>
                <span><?= $tile['icon'] ?></span>
            </div>
            <p class="mt-2 text-2xl font-semibold" x-text="stats.<?= $tile['key'] ?>Display"><?= htmlspecialchars($display) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <h2 class="text-sm font-semibold">Today's queue</h2>
                <span class="text-xs text-slate-400" x-text="lastRefresh ? 'Updated ' + lastRefresh : ''"></span>
            </div>
            <div id="queue-body" class="divide-y max-h-96 overflow-y-auto">
                <?php require __DIR__ . '/_queue_rows.php'; ?>
            </div>
        </div>

        <div class="space-y-6">
            <?php if (!empty($hasPharmacy)): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h2 class="text-sm font-semibold text-amber-900">Low stock</h2>
                <?php if ($lowStock === []): ?>
                <p class="mt-2 text-xs text-amber-800">All items above threshold.</p>
                <?php else: ?>
                <ul class="mt-2 space-y-1 text-xs text-amber-900">
                    <?php foreach ($lowStock as $item): ?>
                    <li><?= htmlspecialchars($item['drug_name'] ?? '') ?> — <?= (int) ($item['quantity'] ?? 0) ?> left</li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($checklist) && empty($checklist['dismissed']) && ($checklist['percent'] ?? 0) < 100): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-emerald-900">Getting started</h2>
                    <span class="text-xs font-medium text-emerald-700"><?= (int) ($checklist['percent'] ?? 0) ?>%</span>
                </div>
                <div class="mt-2 h-1.5 rounded-full bg-emerald-200">
                    <div class="h-1.5 rounded-full bg-emerald-600" style="width: <?= (int) ($checklist['percent'] ?? 0) ?>%"></div>
                </div>
                <ul class="mt-3 space-y-1 text-xs text-emerald-800">
                    <?php foreach ($checklist['items'] as $item): ?>
                    <li><?= !empty($item['done']) ? '✓' : '☐' ?> <?= htmlspecialchars($item['label']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php elseif (!empty($checklist) && ($checklist['percent'] ?? 0) >= 100 && empty($checklist['dismissed'])): ?>
            <form method="post" action="/dashboard/checklist/dismiss" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm font-medium text-emerald-800">Getting started complete!</p>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <button type="submit" class="mt-2 text-xs text-emerald-700 underline">Dismiss checklist</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function dashboardPage() {
    const currency = <?= json_encode($currency ?? 'INR') ?>;
    return {
        stats: {
            patients_today: <?= (int) ($stats['patients_today'] ?? 0) ?>,
            appointments_pending: <?= (int) ($stats['appointments_pending'] ?? 0) ?>,
            revenue_today: <?= (float) ($stats['revenue_today'] ?? 0) ?>,
            follow_ups_due: <?= (int) ($stats['follow_ups_due'] ?? 0) ?>,
            patients_todayDisplay: '<?= (int) ($stats['patients_today'] ?? 0) ?>',
            appointments_pendingDisplay: '<?= (int) ($stats['appointments_pending'] ?? 0) ?>',
            revenue_todayDisplay: currency + ' <?= number_format((float) ($stats['revenue_today'] ?? 0), 2) ?>',
            follow_ups_dueDisplay: '<?= (int) ($stats['follow_ups_due'] ?? 0) ?>',
        },
        lastRefresh: null,
        startRefresh() { setInterval(() => this.refreshQueue(), 60000); },
        async refreshQueue() {
            try {
                const r = await fetch('/api/v1/dashboard/queue', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const data = await r.json();
                if (data.stats) {
                    Object.assign(this.stats, {
                        patients_today: data.stats.patients_today,
                        appointments_pending: data.stats.appointments_pending,
                        revenue_today: data.stats.revenue_today,
                        follow_ups_due: data.stats.follow_ups_due,
                        patients_todayDisplay: String(data.stats.patients_today),
                        appointments_pendingDisplay: String(data.stats.appointments_pending),
                        revenue_todayDisplay: currency + ' ' + Number(data.stats.revenue_today).toFixed(2),
                        follow_ups_dueDisplay: String(data.stats.follow_ups_due),
                    });
                }
                if (data.queue_html) document.getElementById('queue-body').innerHTML = data.queue_html;
                this.lastRefresh = new Date().toLocaleTimeString();
            } catch (e) {}
        }
    };
}
</script>
