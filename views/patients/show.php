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

    <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border bg-white p-6">
        <div class="flex gap-4">
            <?php if ($photoUrl): ?>
            <img src="<?= htmlspecialchars($photoUrl) ?>" alt="" class="h-16 w-16 rounded-full object-cover">
            <?php else: ?>
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-xl font-bold text-emerald-700">
                <?= htmlspecialchars(mb_substr($patient['name'], 0, 1)) ?>
            </span>
            <?php endif; ?>
            <div>
                <h2 class="text-xl font-semibold"><?= htmlspecialchars($patient['name']) ?></h2>
                <p class="font-mono text-sm text-slate-500"><?= htmlspecialchars($patient['uhid']) ?></p>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <?php if ($patient['gender']): ?><span class="rounded-full bg-slate-100 px-2 py-0.5"><?= htmlspecialchars($patient['gender']) ?></span><?php endif; ?>
                    <?php if ($patient['blood_group']): ?><span class="rounded-full bg-red-50 px-2 py-0.5"><?= htmlspecialchars($patient['blood_group']) ?></span><?php endif; ?>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5"><?= htmlspecialchars($patient['veg_type'] ?? '') ?></span>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
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
    </div>

    <nav class="flex flex-wrap gap-1 border-b text-sm">
        <?php
        $tabLabels = [
            'overview' => 'Overview', 'visits' => 'Visits', 'vitals' => 'Vitals',
            'prescriptions' => 'Prescriptions', 'lab' => 'Lab / Radiology',
            'invoices' => 'Invoices', 'documents' => 'Documents',
        ];
        foreach ($tabs as $t):
            if ($t === 'vitals' && empty($hasVitals)) continue;
            if ($t === 'lab' && empty($hasLab) && empty($hasRadiology)) continue;
        ?>
        <a href="?tab=<?= $t ?>"
           class="border-b-2 px-3 py-2 <?= $tab === $t ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500' ?>">
            <?= $tabLabels[$t] ?? $t ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="rounded-xl border bg-white p-6">
        <?php if ($tab === 'overview'): ?>
            <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                <div><dt class="text-slate-500">Phone</dt><dd><?= htmlspecialchars($patient['phone']) ?></dd></div>
                <div><dt class="text-slate-500">Email</dt><dd><?= htmlspecialchars($patient['email'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-500">DOB</dt><dd><?= htmlspecialchars($patient['dob'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-500">Address</dt><dd><?= nl2br(htmlspecialchars($patient['address'] ?? '—')) ?></dd></div>
            </dl>
            <?php if ($allergies !== []): ?>
            <p class="mt-4 text-sm"><strong>Allergies:</strong> <?= htmlspecialchars(implode(', ', $allergies)) ?></p>
            <?php endif; ?>
            <?php if ($chronic !== []): ?>
            <p class="mt-2 text-sm"><strong>Chronic:</strong> <?= htmlspecialchars(implode(', ', $chronic)) ?></p>
            <?php endif; ?>

        <?php elseif ($tab === 'visits'): ?>
            <?php if ($visits === []): ?>
            <p class="text-sm text-slate-500">No visits recorded yet.</p>
            <?php else: ?>
            <ul class="divide-y text-sm">
                <?php foreach ($visits as $v): ?>
                <li class="flex justify-between gap-2 py-3">
                    <span>
                        <span class="font-medium"><?= htmlspecialchars($v['visited_at'] ?? '') ?></span>
                        — <?= htmlspecialchars($v['diagnosis'] ?? $v['chief_complaint'] ?? 'Visit') ?>
                    </span>
                    <a href="/visits/<?= (int) $v['id'] ?>" class="shrink-0 text-xs text-emerald-600 hover:underline">View</a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

        <?php elseif ($tab === 'vitals' && $hasVitals): ?>
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

        <?php elseif ($tab === 'prescriptions'): ?>
            <?php if ($prescriptions === []): ?>
            <p class="text-sm text-slate-500">No prescriptions yet.</p>
            <?php else: ?>
            <p class="text-sm"><?= count($prescriptions) ?> prescription line(s) on file.</p>
            <?php endif; ?>

        <?php elseif ($tab === 'lab'): ?>
            <p class="text-sm text-slate-500">Lab and radiology orders appear here when modules are active (Sprint 8+).</p>

        <?php elseif ($tab === 'invoices'): ?>
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

        <?php elseif ($tab === 'documents'): ?>
            <?php if ($documents === []): ?>
            <p class="text-sm text-slate-500">No documents uploaded.</p>
            <?php else: ?>
            <ul class="text-sm">
                <?php foreach ($documents as $doc): ?>
                <li class="py-2"><a href="<?= htmlspecialchars($doc['file_path']) ?>" class="text-emerald-600"><?= htmlspecialchars($doc['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

        <?php elseif ($tab === 'photos'): ?>
            <div x-data="{ lightbox: null }">
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
            </div>
        <?php endif; ?>
    </div>
</div>
