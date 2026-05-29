<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Vitals</h2>
        <p class="text-sm text-slate-500"><?= (int) $total ?> readings</p>
    </div>

    <form method="get" class="ui-card p-4">
        <div class="flex flex-wrap gap-3">
            <input type="search" name="q" value="<?= htmlspecialchars($filters['q']) ?>"
                   placeholder="Search patient or UHID…"
                   class="min-w-[220px] flex-1 ui-input">
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="ui-input">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="ui-input">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="abnormal" value="1" <?= !empty($filters['abnormal']) ? 'checked' : '' ?>>
                Abnormal only
            </label>
            <button type="submit" class="ui-btn ui-btn-primary">Filter</button>
            <a href="/vitals" class="ui-btn ui-btn-secondary">Reset</a>
        </div>
    </form>

    <div class="overflow-x-auto ui-card">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-3 py-3">Recorded</th>
                    <th class="px-3 py-3">Patient</th>
                    <th class="px-3 py-3">BP</th>
                    <th class="px-3 py-3">Pulse</th>
                    <th class="px-3 py-3">SpO2</th>
                    <th class="px-3 py-3">Temp</th>
                    <th class="px-3 py-3">Sugar</th>
                    <th class="px-3 py-3">Weight / BMI</th>
                    <th class="px-3 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($rows as $row): ?>
                <?php
                    $bpHigh = ((int) ($row['bp_systolic'] ?? 0)) >= 140 || ((int) ($row['bp_diastolic'] ?? 0)) >= 90;
                    $spo2Low = !empty($row['spo2']) && (int) $row['spo2'] < 94;
                    $fever = !empty($row['temperature']) && (float) $row['temperature'] >= 38;
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-3 text-xs text-slate-500"><?= htmlspecialchars(substr((string) ($row['recorded_at'] ?? ''), 0, 16)) ?></td>
                    <td class="px-3 py-3">
                        <div class="font-medium"><?= htmlspecialchars($row['patient_name'] ?? '') ?></div>
                        <div class="font-mono text-xs text-slate-500"><?= htmlspecialchars($row['uhid'] ?? '') ?></div>
                    </td>
                    <td class="px-3 py-3 <?= $bpHigh ? 'text-red-600 font-semibold' : '' ?>">
                        <?php if (!empty($row['bp_systolic'])): ?>
                            <?= (int) $row['bp_systolic'] ?>/<?= (int) ($row['bp_diastolic'] ?? 0) ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-3 py-3"><?= !empty($row['pulse_rate']) ? (int) $row['pulse_rate'] : '—' ?></td>
                    <td class="px-3 py-3 <?= $spo2Low ? 'text-red-600 font-semibold' : '' ?>"><?= !empty($row['spo2']) ? (int) $row['spo2'] . '%' : '—' ?></td>
                    <td class="px-3 py-3 <?= $fever ? 'text-red-600 font-semibold' : '' ?>"><?= !empty($row['temperature']) ? htmlspecialchars((string) $row['temperature']) . '°' : '—' ?></td>
                    <td class="px-3 py-3">
                        <?php if (!empty($row['blood_sugar'])): ?>
                            <?= htmlspecialchars((string) $row['blood_sugar']) ?>
                            <span class="text-xs text-slate-500"><?= htmlspecialchars((string) ($row['sugar_type'] ?? '')) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-3 py-3 text-xs">
                        <?php if (!empty($row['weight_kg'])): ?>
                            <?= htmlspecialchars((string) $row['weight_kg']) ?> kg
                            <?php if (!empty($row['bmi'])): ?>
                                <span class="text-slate-500">/ BMI <?= htmlspecialchars((string) $row['bmi']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-3 py-3 text-right">
                        <a href="/visits/<?= (int) ($row['visit_id'] ?? 0) ?>" class="text-emerald-600 hover:underline">Visit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($rows)): ?>
        <p class="p-8 text-center text-sm text-slate-500">No vitals recorded yet.</p>
        <?php endif; ?>
    </div>

    <?php
    $totalPages = (int) ceil(max(1, $total) / max(1, $perPage));
    if ($totalPages > 1):
    ?>
    <div class="flex justify-center gap-2 text-sm">
        <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
        <a href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"
           class="rounded px-2 py-1 <?= $p === $page ? 'bg-emerald-100 text-emerald-800' : 'border' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
