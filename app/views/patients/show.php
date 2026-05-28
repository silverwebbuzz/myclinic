<?php
$photoUrl = !empty($patient['photo_path']) ? '/' . ltrim($patient['photo_path'], '/') : null;
$qrCard = $patient['qr_card_path'] ?? null;
$tabs = ['overview', 'visits', 'vitals', 'prescriptions', 'lab', 'invoices', 'documents'];
if (!empty($hasPhotos)) {
    $tabs[] = 'photos';
}
?>
<div class="space-y-6">
    <?php if (!empty($created)): ?>
    <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Patient registered successfully.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['qr']) && $_GET['qr'] === 'regenerated'): ?>
    <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">QR token regenerated.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <?php
    // Phase 2: shared patient header partial. Action buttons stay below.
    require __DIR__ . '/_patient_header.php';
    ?>
    <div class="flex flex-wrap gap-2 -mt-2">
        <a href="/patients/<?= (int) $patient['id'] ?>/edit" class="rounded-lg border px-3 py-2 text-sm">Edit</a>
        <?php if ($qrCard): ?>
        <a href="/patients/<?= (int) $patient['id'] ?>/qr-card" target="_blank" class="rounded-lg border px-3 py-2 text-sm">Print QR</a>
        <?php endif; ?>
        <form method="post" action="/patients/<?= (int) $patient['id'] ?>/regenerate-qr" onsubmit="return confirm('Regenerate QR? Old codes will stop working.');">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="rounded-lg border px-3 py-2 text-sm text-amber-700">Regenerate QR</button>
        </form>
        <a href="/appointments/new?patient_id=<?= (int) $patient['id'] ?>" class="rounded-lg border px-3 py-2 text-sm">Book</a>
        <a href="/visits/new?patient_id=<?= (int) $patient['id'] ?>" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white">Start visit</a>
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
    if (!empty($hasPhotos)) $jump[] = ['photos', 'Photos'];
    ?>
    <nav class="sticky top-16 z-20 -mx-1 flex flex-wrap gap-1 border-b bg-slate-50/95 px-1 py-2 text-sm backdrop-blur">
        <?php foreach ($jump as [$anchor, $label]): ?>
        <a href="#sec-<?= $anchor ?>" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-white hover:text-emerald-700"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <!-- ============ OVERVIEW ============ -->
    <section id="sec-overview" class="scroll-mt-28 rounded-xl border bg-white p-6">
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
            <div class="-m-6 mb-0 grid grid-cols-2 gap-px border-b bg-slate-100 sm:grid-cols-4">
                <?php
                $tiles = [
                    ['Total visits', $visitCount, 'text-slate-900', '🩺'],
                    ['Completed', $completedCount, 'text-emerald-600', '✓'],
                    ['Cancelled', $cancelledCount, 'text-rose-500', '⚠️'],
                    ['Prescriptions', count($prescriptions), 'text-slate-900', '℞'],
                ];
                foreach ($tiles as [$lbl, $val, $cls, $ic]):
                ?>
                <div class="bg-white px-5 py-4">
                    <div class="flex items-center gap-2 text-xs text-slate-500"><span><?= $ic ?></span><?= htmlspecialchars($lbl) ?></div>
                    <div class="mt-1 text-2xl font-semibold <?= $cls ?>"><?= (int) $val ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Card grid -->
            <div class="mt-6 grid gap-5 lg:grid-cols-2">

                <!-- Medical History -->
                <div class="rounded-xl border border-slate-200 p-5">
                    <h3 class="text-sm font-semibold text-slate-900">Medical history</h3>
                    <div class="mt-4 space-y-4 text-sm">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-400">Chronic conditions</div>
                            <?php if ($chronic !== []): ?>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <?php foreach ($chronic as $c): ?>
                                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs text-amber-800"><?= htmlspecialchars($c) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?><p class="mt-1 text-slate-400">None recorded</p><?php endif; ?>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-400">Allergies</div>
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
                <div class="rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Latest vitals</h3>
                        <?php if ($hasVitals): ?><a href="?tab=vitals" class="text-xs text-emerald-600 hover:underline">Trends →</a><?php endif; ?>
                    </div>
                    <?php if ($latestVitals): ?>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <?php
                        $vrows = [
                            ['💓', 'Pulse', ($latestVitals['pulse_rate'] ?? null) ? $latestVitals['pulse_rate'] . ' bpm' : null],
                            ['🩸', 'BP', ($latestVitals['bp_systolic'] ?? null) ? $latestVitals['bp_systolic'] . '/' . ($latestVitals['bp_diastolic'] ?? '') . ' mmHg' : null],
                            ['🍬', 'Blood sugar', ($latestVitals['blood_sugar'] ?? null) ? $latestVitals['blood_sugar'] . ' mg/dL' : null],
                            ['🌡️', 'Temp', ($latestVitals['temperature'] ?? null) ? $latestVitals['temperature'] . '°F' : null],
                            ['⚖️', 'Weight', ($latestVitals['weight_kg'] ?? null) ? $latestVitals['weight_kg'] . ' kg' : null],
                            ['🫁', 'SpO₂', ($latestVitals['spo2'] ?? null) ? $latestVitals['spo2'] . '%' : null],
                        ];
                        foreach ($vrows as [$ic, $lbl, $val]): if ($val === null) continue; ?>
                        <div class="flex items-center gap-2">
                            <span class="text-base"><?= $ic ?></span>
                            <span><span class="block text-xs text-slate-400"><?= htmlspecialchars($lbl) ?></span><span class="font-medium text-slate-800"><?= htmlspecialchars((string) $val) ?></span></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="mt-4 text-sm text-slate-400">No vitals recorded yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Files & Documents -->
                <div class="rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Files &amp; documents</h3>
                        <a href="?tab=documents" class="text-xs text-emerald-600 hover:underline">View all →</a>
                    </div>
                    <?php if (!empty($documents)): ?>
                    <ul class="mt-3 space-y-2 text-sm">
                        <?php foreach (array_slice($documents, 0, 4) as $doc): ?>
                        <li class="flex items-center gap-2">
                            <span class="text-slate-400">📄</span>
                            <a href="/<?= htmlspecialchars(ltrim($doc['file_path'] ?? '#', '/')) ?>" target="_blank" class="truncate text-slate-700 hover:text-emerald-700 hover:underline"><?= htmlspecialchars($doc['title'] ?? $doc['file_name'] ?? 'Document') ?></a>
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
    <section id="sec-visits" class="scroll-mt-28 rounded-xl border bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">Visits</h2>
            <a href="/visits/new?patient_id=<?= (int) $patient['id'] ?>" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white">+ New visit</a>
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
                <a href="/visits/<?= (int) $v['id'] ?>" class="shrink-0 text-xs text-emerald-600 hover:underline">View / edit</a>
            </li>
            <?php endforeach; ?>
        </ul>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($hasVitals): ?>
    <!-- ============ VITALS ============ -->
    <section id="sec-vitals" class="scroll-mt-28 rounded-xl border bg-white p-6">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Vitals trend</h2>
            <canvas id="vitals-chart" height="120"></canvas>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
            (function() {
                const data = <?= json_encode(array_map(fn($v) => [
                    'date' => substr($v['recorded_at'] ?? '', 0, 10),
                    'weight' => $v['weight_kg'] ?? null,
                ], $vitals)) ?>;
                if (!data.length) return;
                new Chart(document.getElementById('vitals-chart'), {
                    type: 'line',
                    data: {
                        labels: data.map(d => d.date),
                        datasets: [{ label: 'Weight (kg)', data: data.map(d => d.weight), borderColor: '#0F9B6E' }]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: false } } }
                });
            })();
            </script>
    </section>
    <?php endif; ?>

    <!-- ============ PRESCRIPTIONS ============ -->
    <section id="sec-prescriptions" class="scroll-mt-28 rounded-xl border bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">Prescriptions</h2>
            <a href="/prescriptions?patient_id=<?= (int) $patient['id'] ?>" class="text-xs text-emerald-600 hover:underline">View all →</a>
        </div>
        <?php if ($prescriptions === []): ?>
        <p class="text-sm text-slate-500">No prescriptions yet.</p>
        <?php else: ?>
        <p class="text-sm text-slate-600"><?= count($prescriptions) ?> prescription line(s) on file across this patient's visits.</p>
        <?php endif; ?>
    </section>

    <!-- ============ INVOICES ============ -->
    <section id="sec-invoices" class="scroll-mt-28 rounded-xl border bg-white p-6">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Invoices &amp; payments</h2>
            <div class="mb-6 rounded-lg border bg-slate-50 p-4">
                <p class="text-sm font-medium">Advance balance: ₹<?= number_format((float) ($patient['advance_balance'] ?? 0), 2) ?></p>
                <form method="post" action="/patients/<?= (int) $patient['id'] ?>/advance" class="mt-3 flex flex-wrap gap-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="number" name="amount" step="0.01" min="1" placeholder="Amount" required class="rounded-lg border px-3 py-2 text-sm">
                    <select name="method" class="rounded-lg border px-3 py-2 text-sm">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Record advance</button>
                </form>
            </div>
            <?php if (!empty($_GET['advance'])): ?>
            <p class="mb-3 text-sm text-emerald-700">Advance recorded.</p>
            <?php endif; ?>
            <?php if ($invoices === []): ?>
            <p class="text-sm text-slate-500">No invoices yet.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-slate-500"><th>Invoice</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y">
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td class="py-2"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td><?= htmlspecialchars($inv['currency'] . ' ' . $inv['total']) ?></td>
                    <td class="capitalize"><?= htmlspecialchars($inv['status']) ?></td>
                    <td class="py-2 text-right"><a href="/billing/<?= (int) $inv['id'] ?>" class="text-emerald-600 hover:underline">Open</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
    </section>

    <!-- ============ DOCUMENTS ============ -->
    <section id="sec-documents" class="scroll-mt-28 rounded-xl border bg-white p-6">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Documents</h2>
        <?php if ($documents === []): ?>
        <p class="text-sm text-slate-500">No documents uploaded.</p>
        <?php else: ?>
        <ul class="divide-y text-sm">
            <?php foreach ($documents as $doc): ?>
            <li class="flex items-center gap-2 py-2">
                <span class="text-slate-400">📄</span>
                <a href="/<?= htmlspecialchars(ltrim($doc['file_path'], '/')) ?>" target="_blank" class="text-emerald-600 hover:underline"><?= htmlspecialchars($doc['title']) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <?php if (!empty($hasPhotos)): ?>
    <!-- ============ PHOTOS ============ -->
    <section id="sec-photos" class="scroll-mt-28 rounded-xl border bg-white p-6" x-data="{ lightbox: null }">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Photos</h2>
        <?php if ($photos === []): ?>
        <p class="text-sm text-slate-500">No photos yet. Upload from a visit.</p>
        <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <?php foreach ($photos as $ph): ?>
            <button type="button" @click="lightbox = '<?= htmlspecialchars($ph['photo_path'], ENT_QUOTES) ?>'" class="rounded-lg border overflow-hidden text-left">
                <img src="<?= htmlspecialchars($ph['photo_path']) ?>" alt="" class="h-28 w-full object-cover">
                <p class="p-2 text-xs capitalize"><?= htmlspecialchars($ph['type'] ?? '') ?></p>
            </button>
            <?php endforeach; ?>
        </div>
        <div x-show="lightbox" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click="lightbox = null" @keydown.escape.window="lightbox = null">
            <img :src="lightbox" class="max-h-full max-w-full rounded-lg" @click.stop>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
