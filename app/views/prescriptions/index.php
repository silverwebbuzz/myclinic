<?php
$formatLine = static function (array $line): string {
    $name = $line['drug_name'] ?? $line['remedy_name'] ?? '—';
    $parts = [(string) $name];
    if (!empty($line['potency'])) {
        $parts[] = (string) $line['potency'];
    }
    $detail = [];
    if (!empty($line['dosage'])) $detail[] = (string) $line['dosage'];
    if (!empty($line['frequency'])) $detail[] = (string) $line['frequency'];
    if (!empty($line['duration_days'])) $detail[] = ((int) $line['duration_days']) . 'd';
    $main = implode(' ', $parts);
    return $detail !== [] ? $main . ' — ' . implode(' · ', $detail) : $main;
};

// wa.me click-to-chat link with the prescription summary prefilled.
$waLink = static function (array $visit) use ($formatLine): ?string {
    $phone = preg_replace('/[^0-9]/', '', (string) ($visit['patient_phone'] ?? '')) ?? '';
    if ($phone === '') {
        return null;
    }
    if (strlen($phone) === 10) {
        $phone = '91' . $phone; // bare Indian mobile — add country code
    }
    $lines = [];
    foreach ($visit['lines'] as $i => $line) {
        $lines[] = ($i + 1) . '. ' . $formatLine($line);
    }
    $text = 'Prescription for ' . ($visit['patient_name'] ?? '')
        . ' (' . date('d M Y', strtotime((string) $visit['visited_at'])) . ")\n"
        . implode("\n", $lines)
        . "\n— " . ($visit['doctor_name'] ?? '');

    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
};
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Prescriptions</h2>
        <p class="text-sm text-slate-500"><?= (int) $total ?> visit<?= $total === 1 ? '' : 's' ?> with prescriptions</p>
    </div>

    <?php if (!empty($_GET['error'])): ?>
    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars((string) $_GET['error']) ?></p>
    <?php endif; ?>

    <form method="get" class="ui-card p-4">
        <div class="flex flex-wrap gap-3">
            <input type="search" name="q" value="<?= htmlspecialchars($filters['q']) ?>"
                   placeholder="Search patient, UHID, drug, remedy…"
                   class="min-w-[220px] flex-1 ui-input">
            <select name="mode" class="rounded-lg border px-2 py-2 text-sm">
                <option value="">All modes</option>
                <option value="allopathic" <?= $filters['mode'] === 'allopathic' ? 'selected' : '' ?>>Allopathic</option>
                <option value="homeopathic" <?= $filters['mode'] === 'homeopathic' ? 'selected' : '' ?>>Homeopathic</option>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="ui-input">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="ui-input">
            <button type="submit" class="ui-btn ui-btn-primary">Filter</button>
            <a href="/prescriptions" class="ui-btn ui-btn-secondary">Reset</a>
        </div>
    </form>

    <div class="space-y-3">
        <?php foreach ($rows as $visit): ?>
        <div class="ui-card p-4 transition hover:shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b pb-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-baseline gap-3">
                        <a href="/patients/<?= (int) $visit['patient_id'] ?>"
                           class="text-base font-semibold text-slate-900 hover:text-emerald-700 hover:underline">
                            <?= htmlspecialchars($visit['patient_name'] ?? '') ?>
                        </a>
                        <span class="font-mono text-xs text-slate-500"><?= htmlspecialchars($visit['uhid'] ?? '') ?></span>
                        <?php if (!empty($visit['patient_phone'])): ?>
                            <span class="text-xs text-slate-500">· <?= htmlspecialchars($visit['patient_phone']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1.5"><?= ui_icon('appointments', 13) ?><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string) $visit['visited_at']))) ?></span>
                        <span class="inline-flex items-center gap-1.5"><?= ui_icon('staff', 13) ?><?= htmlspecialchars($visit['doctor_name'] ?? '') ?></span>
                        <span><?= (int) $visit['line_count'] ?> item<?= (int) $visit['line_count'] === 1 ? '' : 's' ?></span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="/visits/<?= (int) $visit['visit_id'] ?>"
                       class="ui-btn ui-btn-secondary ui-btn-sm">View visit</a>
                    <?php $wa = $waLink($visit); ?>
                    <?php if ($wa !== null): ?>
                    <a href="<?= htmlspecialchars($wa) ?>" target="_blank" rel="noopener"
                       class="ui-btn ui-btn-secondary ui-btn-sm">WhatsApp</a>
                    <?php endif; ?>
                    <a href="/prescriptions/<?= (int) $visit['visit_id'] ?>/pdf" target="_blank"
                       class="ui-btn ui-btn-primary ui-btn-sm">
                        <?= ui_icon('emr', 14) ?><span>Print Rx</span>
                    </a>
                </div>
            </div>

            <ul class="mt-3 space-y-1.5">
                <?php foreach ($visit['lines'] as $line): ?>
                <li class="flex flex-wrap items-baseline gap-2 text-sm">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    <span class="font-medium text-slate-800">
                        <?= htmlspecialchars((string) ($line['drug_name'] ?? $line['remedy_name'] ?? '—')) ?>
                        <?php if (!empty($line['potency'])): ?>
                            <span class="text-xs text-slate-500"><?= htmlspecialchars((string) $line['potency']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="text-xs text-slate-500">
                        <?php
                        $bits = [];
                        if (!empty($line['dosage'])) $bits[] = htmlspecialchars((string) $line['dosage']);
                        if (!empty($line['frequency'])) $bits[] = htmlspecialchars((string) $line['frequency']);
                        if (!empty($line['duration_days'])) $bits[] = ((int) $line['duration_days']) . ' days';
                        echo $bits !== [] ? '· ' . implode(' · ', $bits) : '';
                        ?>
                    </span>
                    <?php if (!empty($line['instructions'])): ?>
                        <span class="text-xs italic text-slate-500">— <?= htmlspecialchars((string) $line['instructions']) ?></span>
                    <?php endif; ?>
                    <span class="ml-auto rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-slate-600">
                        <?= htmlspecialchars((string) ($line['mode'] ?? '')) ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <?php if (empty($rows)): ?>
        <div class="ui-card p-12 text-center">
            <p class="mb-3 flex justify-center text-slate-300"><?= ui_icon('prescription', 40) ?></p>
            <p class="text-sm font-medium text-slate-700">No prescriptions found</p>
            <p class="mt-1 text-xs text-slate-500">Adjust filters or wait for new visits to be completed.</p>
        </div>
        <?php endif; ?>
    </div>

    <?php
    // Windowed pager with Prev/Next — every page reachable, filters preserved.
    $totalPages = (int) ceil(max(1, $total) / max(1, $perPage));
    if ($totalPages > 1):
        $pageUrl = static fn (int $p): string => '?' . http_build_query(array_merge(array_filter($filters), ['page' => $p]));
        $winStart = max(1, $page - 2);
        $winEnd = min($totalPages, $page + 2);
    ?>
    <div class="flex flex-wrap items-center justify-center gap-2 text-sm">
        <?php if ($page > 1): ?>
        <a href="<?= htmlspecialchars($pageUrl($page - 1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50">← Prev</a>
        <?php endif; ?>
        <?php if ($winStart > 1): ?>
        <a href="<?= htmlspecialchars($pageUrl(1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50">1</a>
        <?php if ($winStart > 2): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $winStart; $p <= $winEnd; $p++): ?>
        <a href="<?= htmlspecialchars($pageUrl($p)) ?>"
           class="rounded-lg px-3 py-1.5 font-medium <?= $p === $page ? 'bg-brand text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($winEnd < $totalPages): ?>
        <?php if ($winEnd < $totalPages - 1): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
        <a href="<?= htmlspecialchars($pageUrl($totalPages)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50"><?= $totalPages ?></a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
        <a href="<?= htmlspecialchars($pageUrl($page + 1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
