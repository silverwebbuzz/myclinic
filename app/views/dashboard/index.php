<div x-data="dashboardPage()" x-init="startRefresh()" class="space-y-6">

    <?php
    $flash = $_GET['message'] ?? null;
    if ($flash === 'already_listed'): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        ✓ Your clinic is already listed on eClinicPro.
        <a href="https://eclinicpro.com/find-a-doctor" target="_blank" class="font-semibold underline">View public page →</a>
    </div>
    <?php endif; ?>

    <?php if (empty($isDirectoryListed)): ?>
    <!-- ============ Get listed on /find-a-doctor banner ============
         Shown to clinics that have a portal account but haven't been
         approved for the public directory yet. Dismissable per-session
         so users who aren't ready right now aren't nagged on every load. -->
    <div x-data="{ show: localStorage.getItem('ecp_hide_dir_banner') !== '1' }"
         x-show="show" x-cloak
         class="rounded-xl border-2 border-dashed border-emerald-300 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/40 p-5 shadow-sm">
        <div class="flex items-start gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="text-base font-bold text-slate-900">Reach more patients — get listed on eClinicPro</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Your clinic isn't visible to patients searching on
                    <a href="https://eclinicpro.com/find-a-doctor" class="font-semibold text-emerald-700 hover:underline" target="_blank">eclinicpro.com/find-a-doctor</a>
                    yet. Submit your details once, our team reviews within 1–2 business days, and patients can start finding you.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="/onboarding/get-listed"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Get listed — takes 1 minute
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                    <button type="button"
                            @click="show = false; localStorage.setItem('ecp_hide_dir_banner','1')"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 hover:border-slate-400">
                        Maybe later
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
            <?php if (!empty($followUps['overdue_count']) || !empty($followUps['due_week'])): ?>
            <!-- ============ Follow-ups widget (Phase 4) ============ -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Follow-ups</h2>
                    <a href="/follow-ups" class="text-xs text-emerald-700 hover:underline">View all</a>
                </div>
                <?php if (!empty($followUps['overdue_count'])): ?>
                <p class="mt-2 text-xs font-medium text-rose-700">
                    ⚠ <?= (int) $followUps['overdue_count'] ?> overdue
                </p>
                <ul class="mt-1 space-y-1 text-xs text-slate-700">
                    <?php foreach ($followUps['overdue'] as $f): ?>
                    <li class="flex items-center justify-between gap-2">
                        <a href="/visits/new?patient_id=<?= (int) $f['patient_id'] ?>" class="truncate hover:underline">
                            <?= htmlspecialchars($f['patient_name']) ?>
                        </a>
                        <span class="shrink-0 text-rose-600"><?= (int) $f['days_overdue'] ?>d</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="mt-2 flex gap-4 border-t pt-2 text-xs text-slate-500">
                    <span>📅 <?= (int) ($followUps['due_week'] ?? 0) ?> due this week</span>
                    <span>✓ <?= (int) ($followUps['done_month'] ?? 0) ?> done this month</span>
                </div>
            </div>
            <?php endif; ?>

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
