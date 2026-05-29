<?php
$params = $order['parameters'] ?? [];
$results = $order['results'] ?? [];
$status = $order['status'] ?? 'ordered';
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="ui-section-title"><?= htmlspecialchars($order['test_name'] ?? '') ?></h2>
            <p class="text-sm text-slate-500"><?= htmlspecialchars($order['patient_name'] ?? '') ?> · <?= htmlspecialchars($order['barcode'] ?? '') ?></p>
            <p class="text-xs capitalize text-slate-400">Status: <?= htmlspecialchars($status) ?></p>
        </div>
        <a href="/lab/orders/<?= (int) $order['id'] ?>/barcode" target="_blank" class="ui-btn ui-btn-secondary ui-btn-sm">Print barcode</a>
    </div>

    <?php if (!empty($_GET['shared'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Report finalized and shared with patient (24h link).</p>
    <?php endif; ?>

    <div class="ui-card p-4"><?= $barcodeHtml ?></div>

    <?php if ($status === 'ordered'): ?>
    <form method="post" action="/lab/orders/<?= (int) $order['id'] ?>/collect">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white">Mark sample collected</button>
    </form>
    <?php endif; ?>

    <?php if (in_array($status, ['sample_collected', 'ordered', 'resulted'], true)): ?>
    <form method="post" action="/lab/orders/<?= (int) $order['id'] ?>/results" class="ui-card p-4 space-y-3">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <h3 class="font-medium text-sm">Enter results</h3>
        <?php
        $rows = $results !== [] ? $results : array_map(static fn ($p) => [
            'parameter_name' => $p['name'] ?? '',
            'value' => '',
            'unit' => $p['unit'] ?? '',
            'normal_range' => isset($p['min'], $p['max']) ? $p['min'] . '–' . $p['max'] : '',
        ], is_array($params) ? $params : []);
        foreach ($rows as $i => $r):
        ?>
        <div class="grid gap-2 sm:grid-cols-4 text-sm">
            <input type="hidden" name="parameter_name[]" value="<?= htmlspecialchars($r['parameter_name'] ?? '') ?>">
            <span class="font-medium"><?= htmlspecialchars($r['parameter_name'] ?? '') ?></span>
            <input name="value[]" value="<?= htmlspecialchars($r['value'] ?? '') ?>" placeholder="Value" class="rounded border px-2 py-1">
            <input name="unit[]" value="<?= htmlspecialchars($r['unit'] ?? '') ?>" placeholder="Unit" class="rounded border px-2 py-1">
            <input name="normal_range[]" value="<?= htmlspecialchars($r['normal_range'] ?? '') ?>" placeholder="Range" class="rounded border px-2 py-1">
        </div>
        <?php endforeach; ?>
        <button type="submit" class="ui-btn ui-btn-primary">Save results</button>
    </form>
    <?php endif; ?>

    <?php if ($status === 'resulted'): ?>
    <form method="post" action="/lab/orders/<?= (int) $order['id'] ?>/finalize">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="ui-btn ui-btn-primary">Finalize PDF &amp; share</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($order['report_path'])): ?>
    <a href="<?= htmlspecialchars($order['report_path']) ?>" target="_blank" class="text-sm text-emerald-600 hover:underline">Download report PDF</a>
    <?php endif; ?>
</div>
