<div class="mx-auto max-w-2xl space-y-4">
    <h1 class="text-xl font-semibold">Discharge summary</h1>
    <p class="text-sm text-slate-500"><?= htmlspecialchars($clinic['name'] ?? '') ?></p>
    <div class="rounded-xl border bg-white p-6 text-sm space-y-3">
        <p><strong>Diagnosis:</strong> <?= htmlspecialchars($summary['final_diagnosis'] ?? '') ?></p>
        <p><strong>Treatment:</strong> <?= nl2br(htmlspecialchars($summary['treatment_summary'] ?? '')) ?></p>
        <p><strong>Follow-up:</strong> <?= nl2br(htmlspecialchars($summary['follow_up_instructions'] ?? '')) ?></p>
        <?php if (!empty($summary['pdf_path'])): ?>
        <a href="<?= htmlspecialchars($summary['pdf_path']) ?>" class="inline-block rounded-lg bg-emerald-600 px-4 py-2 text-white">Download PDF</a>
        <?php endif; ?>
    </div>
</div>
