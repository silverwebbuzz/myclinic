<?php
$photoUrl = !empty($patient['photo_path']) ? '/' . ltrim($patient['photo_path'], '/') : null;
?>
<div class="space-y-6">
    <?php if (!empty($created)): ?>
    <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Patient registered successfully.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <?php
    // Phase 2: shared patient header partial. Action buttons stay below.
    require __DIR__ . '/_patient_header.php';
    ?>
    <div class="flex flex-wrap gap-2 -mt-2">
        <a href="/patients/<?= (int) $patient['id'] ?>/edit" class="ui-btn ui-btn-secondary ui-btn-sm">Edit</a>
        <a href="/appointments/new?patient_id=<?= (int) $patient['id'] ?>" class="ui-btn ui-btn-secondary ui-btn-sm">Book</a>
        <a href="/visits/new?patient_id=<?= (int) $patient['id'] ?>" class="ui-btn ui-btn-primary ui-btn-sm"><?= ui_icon('plus', 16) ?><span>Start visit</span></a>
    </div>

    <?php
    // Single-page layout (no tabs): every section stacked, each scrolls where
    // needed. A sticky in-page nav lets the doctor jump to a section.
    $jump = [
        ['overview', 'Overview'],
        ['visits', 'Visits'],
    ];
    if ($hasVitals) $jump[] = ['vitals', 'Vitals'];
    $jump[] = ['prescriptions', 'Prescriptions'];
    $jump[] = ['invoices', 'Invoices'];
    $jump[] = ['documents', 'Documents'];
    ?>
    <nav class="sticky top-16 z-20 -mx-1 flex flex-wrap gap-1 border-b border-slate-200 bg-slate-50/95 px-1 py-2 text-sm backdrop-blur">
        <?php foreach ($jump as [$anchor, $label]): ?>
        <a href="#sec-<?= $anchor ?>" class="rounded-lg px-3 py-1.5 font-medium text-slate-600 hover:bg-white hover:text-brand"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <!-- ============ OVERVIEW ============ -->
    <section id="sec-overview" class="scroll-mt-28 ui-card p-6">
            <?php
            // ---- Derive overview stats from the data the controller already loads ----
            $visitCount = count($visits);
            $completedCount = 0; $cancelledCount = 0;
            foreach ($visits as $vv) {
                $st = $vv['status'] ?? '';
                if ($st === 'completed') $completedCount++;
                if ($st === 'cancelled') $cancelledCount++;
            }
            $latestVitals = !empty($vitals) ? end($vitals) : null;  // vitals are ASC → last = newest
            $vegLabels = ['veg' => 'Vegetarian', 'nonveg' => 'Non-veg', 'vegan' => 'Vegan', 'eggetarian' => 'Eggetarian'];
            ?>

            <!-- Stat tiles -->
            <div class="-m-6 mb-0 grid grid-cols-2 gap-px border-b border-slate-100 bg-slate-100 sm:grid-cols-4">
                <?php
                $tiles = [
                    ['Total visits', $visitCount, 'text-slate-900', 'emr'],
                    ['Completed', $completedCount, 'text-emerald-600', 'check'],
                    ['Cancelled', $cancelledCount, 'text-rose-500', 'bell'],
                    ['Prescriptions', count($prescriptions), 'text-slate-900', 'prescription'],
                ];
                foreach ($tiles as [$lbl, $val, $cls, $ic]):
                ?>
                <div class="bg-white px-5 py-4">
                    <div class="flex items-center gap-2 ui-help"><?= ui_icon($ic, 14) ?><?= htmlspecialchars($lbl) ?></div>
                    <div class="mt-1 text-2xl font-semibold <?= $cls ?>"><?= (int) $val ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Card grid -->
            <div class="mt-6 grid gap-5 lg:grid-cols-2">

                <!-- Medical History -->
                <div class="ui-card p-5">
                    <h3 class="ui-section-title">Medical history</h3>
                    <div class="mt-4 space-y-4 text-sm">
                        <div>
                            <div class="ui-group-label">Chronic conditions</div>
                            <?php if ($chronic !== []): ?>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <?php foreach ($chronic as $c): ?>
                                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs text-amber-800"><?= htmlspecialchars($c) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?><p class="mt-1 text-slate-400">None recorded</p><?php endif; ?>
                        </div>
                        <div>
                            <div class="ui-group-label">Allergies</div>
                            <?php if ($allergies !== []): ?>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <?php foreach ($allergies as $a): ?>
                                <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs text-rose-700"><?= htmlspecialchars($a) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?><p class="mt-1 text-slate-400">None recorded</p><?php endif; ?>
                        </div>
                        <div class="grid grid-cols-2 gap-3 border-t pt-3 text-xs">
                            <div><span class="text-slate-400">Blood group</span><div class="text-slate-800"><?= htmlspecialchars($patient['blood_group'] ?? '—') ?></div></div>
                            <div><span class="text-slate-400">Diet</span><div class="text-slate-800"><?= htmlspecialchars($vegLabels[$patient['veg_type'] ?? ''] ?? '—') ?></div></div>
                            <div><span class="text-slate-400">Phone</span><div class="text-slate-800"><?= htmlspecialchars($patient['phone']) ?></div></div>
                            <div><span class="text-slate-400">Email</span><div class="text-slate-800 truncate"><?= htmlspecialchars($patient['email'] ?? '—') ?></div></div>
                        </div>
                    </div>
                </div>

                <!-- Vitals (latest) -->
                <div class="ui-card p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="ui-section-title">Latest vitals</h3>
                        <?php if ($hasVitals): ?><a href="#sec-vitals" class="text-xs font-medium text-brand hover:underline">Trends →</a><?php endif; ?>
                    </div>
                    <?php if ($latestVitals): ?>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <?php
                        $vrows = [
                            ['Pulse', ($latestVitals['pulse_rate'] ?? null) ? $latestVitals['pulse_rate'] . ' bpm' : null],
                            ['BP', ($latestVitals['bp_systolic'] ?? null) ? $latestVitals['bp_systolic'] . '/' . ($latestVitals['bp_diastolic'] ?? '') . ' mmHg' : null],
                            ['Blood sugar', ($latestVitals['blood_sugar'] ?? null) ? $latestVitals['blood_sugar'] . ' mg/dL' : null],
                            ['Temp', ($latestVitals['temperature'] ?? null) ? $latestVitals['temperature'] . '°F' : null],
                            ['Weight', ($latestVitals['weight_kg'] ?? null) ? $latestVitals['weight_kg'] . ' kg' : null],
                            ['SpO₂', ($latestVitals['spo2'] ?? null) ? $latestVitals['spo2'] . '%' : null],
                        ];
                        foreach ($vrows as [$lbl, $val]): if ($val === null) continue; ?>
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-light text-brand"><?= ui_icon('vitals', 15) ?></span>
                            <span><span class="block text-xs text-slate-400"><?= htmlspecialchars($lbl) ?></span><span class="font-medium text-slate-800"><?= htmlspecialchars((string) $val) ?></span></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="mt-4 text-sm text-slate-400">No vitals recorded yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Files & Documents -->
                <div class="ui-card p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="ui-section-title">Files &amp; documents</h3>
                        <a href="#sec-documents" class="text-xs font-medium text-brand hover:underline">View all →</a>
                    </div>
                    <?php if (!empty($documents)): ?>
                    <ul class="mt-3 space-y-2 text-sm">
                        <?php foreach (array_slice($documents, 0, 4) as $doc): ?>
                        <li class="flex items-center gap-2">
                            <span class="text-slate-400"><?= ui_icon('emr', 15) ?></span>
                            <a href="/<?= htmlspecialchars(ltrim($doc['file_path'] ?? '#', '/')) ?>" target="_blank" class="truncate text-slate-700 hover:text-brand hover:underline"><?= htmlspecialchars($doc['title'] ?? $doc['file_name'] ?? 'Document') ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="mt-3 text-sm text-slate-400">No documents uploaded.</p>
                    <?php endif; ?>
                </div>

            </div>
    </section>

    <!-- ============ VISITS ============ -->
    <section id="sec-visits" class="scroll-mt-28 ui-card p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="ui-section-title">Visits</h2>
            <a href="/visits/new?patient_id=<?= (int) $patient['id'] ?>" class="ui-btn ui-btn-primary ui-btn-sm"><?= ui_icon('plus', 15) ?><span>New visit</span></a>
        </div>
        <?php if ($visits === []): ?>
        <p class="text-sm text-slate-500">No visits recorded yet.</p>
        <?php else: ?>
        <div class="max-h-96 overflow-y-auto">
        <ul class="divide-y text-sm">
            <?php foreach ($visits as $v): ?>
            <li class="flex items-center justify-between gap-2 py-3">
                <span class="min-w-0">
                    <span class="block font-medium text-slate-800"><?= htmlspecialchars($v['diagnosis'] ?? $v['chief_complaint'] ?? 'Visit') ?></span>
                    <span class="block text-xs text-slate-400"><?= htmlspecialchars(date('d M Y', strtotime((string) ($v['visited_at'] ?? 'now')))) ?> · <span class="capitalize"><?= htmlspecialchars($v['status'] ?? '') ?></span></span>
                </span>
                <a href="/visits/<?= (int) $v['id'] ?>" class="shrink-0 text-xs font-medium text-brand hover:underline">View / edit</a>
            </li>
            <?php endforeach; ?>
        </ul>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($hasVitals): ?>
    <!-- ============ VITALS ============ -->
    <section id="sec-vitals" class="scroll-mt-28 ui-card p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="ui-section-title">Vitals trend</h2>
            <span class="ui-help">Click a legend item to show/hide that metric</span>
        </div>
            <?php if ($vitals === []): ?>
            <p class="text-sm text-slate-500">No vitals recorded yet. Vitals are captured during a visit.</p>
            <?php else: ?>
            <canvas id="vitals-chart" height="120"></canvas>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
            (function() {
                const data = <?= json_encode(array_map(fn ($v) => [
                    'date' => substr($v['recorded_at'] ?? '', 0, 10),
                    'weight' => $v['weight_kg'] ?? null,
                    'bp_sys' => $v['bp_systolic'] ?? null,
                    'bp_dia' => $v['bp_diastolic'] ?? null,
                    'spo2' => $v['spo2'] ?? null,
                    'sugar' => $v['blood_sugar'] ?? null,
                ], $vitals)) ?>;
                if (!data.length) return;
                // Only plot metrics that have at least one reading; spanGaps
                // bridges visits where a metric wasn't taken.
                const metrics = [
                    { key: 'weight', label: 'Weight (kg)', color: '#0F766E' },
                    { key: 'bp_sys', label: 'BP systolic', color: '#ef4444' },
                    { key: 'bp_dia', label: 'BP diastolic', color: '#f97316' },
                    { key: 'spo2',  label: 'SpO₂ (%)', color: '#3b82f6' },
                    { key: 'sugar', label: 'Blood sugar (mg/dL)', color: '#8b5cf6' },
                ].filter(m => data.some(d => d[m.key] !== null && d[m.key] !== ''));
                new Chart(document.getElementById('vitals-chart'), {
                    type: 'line',
                    data: {
                        labels: data.map(d => d.date),
                        datasets: metrics.map(m => ({
                            label: m.label,
                            data: data.map(d => d[m.key]),
                            borderColor: m.color,
                            backgroundColor: m.color,
                            spanGaps: true,
                            tension: 0.25,
                            pointRadius: 3,
                        })),
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: false } } }
                });
            })();
            </script>
            <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ============ PRESCRIPTIONS ============ -->
    <section id="sec-prescriptions" class="scroll-mt-28 ui-card p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="ui-section-title">Prescriptions</h2>
            <a href="/prescriptions?patient_id=<?= (int) $patient['id'] ?>" class="text-xs font-medium text-brand hover:underline">View all →</a>
        </div>
        <?php if ($prescriptions === []): ?>
        <p class="text-sm text-slate-500">No prescriptions yet.</p>
        <?php else: ?>
        <p class="text-sm text-slate-600"><?= count($prescriptions) ?> prescription line(s) on file across this patient's visits.</p>
        <?php endif; ?>
    </section>

    <!-- ============ INVOICES ============ -->
    <section id="sec-invoices" class="scroll-mt-28 ui-card p-6">
        <h2 class="mb-4 ui-section-title">Invoices &amp; payments</h2>
            <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-medium text-slate-800">Advance balance: ₹<?= number_format((float) ($patient['advance_balance'] ?? 0), 2) ?></p>
                <form method="post" action="/patients/<?= (int) $patient['id'] ?>/advance" class="mt-3 flex flex-wrap gap-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="number" name="amount" step="0.01" min="1" placeholder="Amount" required class="ui-input w-auto">
                    <select name="method" class="ui-input w-auto">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                    </select>
                    <button type="submit" class="ui-btn ui-btn-primary">Record advance</button>
                </form>
            </div>
            <?php if (!empty($_GET['advance'])): ?>
            <p class="mb-3 text-sm text-emerald-700">Advance recorded.</p>
            <?php endif; ?>
            <?php if ($invoices === []): ?>
            <p class="text-sm text-slate-500">No invoices yet.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead><tr class="text-left ui-group-label"><th class="pb-2">Invoice</th><th class="pb-2">Total</th><th class="pb-2">Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($invoices as $inv):
                    $st = strtolower((string) $inv['status']);
                    $tone = in_array($st, ['paid', 'completed'], true) ? 'success' : (in_array($st, ['unpaid', 'overdue', 'cancelled'], true) ? 'danger' : 'warning');
                ?>
                <tr>
                    <td class="py-2 text-slate-700"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td class="text-slate-700"><?= htmlspecialchars($inv['currency'] . ' ' . $inv['total']) ?></td>
                    <td><?= ui_badge(ucfirst((string) $inv['status']), $tone) ?></td>
                    <td class="py-2 text-right"><a href="/billing/<?= (int) $inv['id'] ?>" class="font-medium text-brand hover:underline">Open</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
    </section>

    <!-- ============ DOCUMENTS ============ -->
    <section id="sec-documents" class="scroll-mt-28 ui-card p-6">
        <h2 class="mb-4 ui-section-title">Documents</h2>
        <?php if ($documents === []): ?>
        <p class="text-sm text-slate-500">No documents uploaded.</p>
        <?php else: ?>
        <ul class="divide-y divide-slate-100 text-sm">
            <?php foreach ($documents as $doc): ?>
            <li class="flex items-center gap-2 py-2">
                <span class="text-slate-400"><?= ui_icon('emr', 15) ?></span>
                <a href="/<?= htmlspecialchars(ltrim($doc['file_path'], '/')) ?>" target="_blank" class="font-medium text-brand hover:underline"><?= htmlspecialchars($doc['title']) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
