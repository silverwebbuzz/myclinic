<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Radiology order #<?= (int) ($order['id'] ?? 0) ?></h2>
        <a href="/radiology" class="text-sm text-slate-500 hover:underline">← Back to list</a>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border bg-white p-4 md:col-span-1">
            <p class="text-xs uppercase text-slate-500">Patient</p>
            <p class="font-medium"><?= htmlspecialchars($order['patient_name'] ?? '') ?></p>
            <p class="font-mono text-xs text-slate-500"><?= htmlspecialchars($order['uhid'] ?? '') ?></p>

            <p class="mt-4 text-xs uppercase text-slate-500">Modality</p>
            <p class="font-medium uppercase"><?= htmlspecialchars((string) ($order['modality'] ?? '')) ?></p>

            <p class="mt-4 text-xs uppercase text-slate-500">Body part</p>
            <p><?= htmlspecialchars((string) ($order['body_part'] ?? '—')) ?></p>

            <p class="mt-4 text-xs uppercase text-slate-500">Status</p>
            <p><?= htmlspecialchars((string) ($order['status'] ?? '')) ?></p>

            <p class="mt-4 text-xs uppercase text-slate-500">Ordered at</p>
            <p class="text-sm"><?= htmlspecialchars((string) ($order['ordered_at'] ?? '')) ?></p>

            <?php if (!empty($order['reported_at'])): ?>
            <p class="mt-4 text-xs uppercase text-slate-500">Reported at</p>
            <p class="text-sm"><?= htmlspecialchars((string) $order['reported_at']) ?></p>
            <?php endif; ?>
        </div>

        <div class="space-y-4 md:col-span-2">
            <div class="rounded-xl border bg-white p-4">
                <p class="text-xs uppercase text-slate-500">Clinical indication</p>
                <p class="mt-1 whitespace-pre-wrap text-sm"><?= htmlspecialchars((string) ($order['clinical_indication'] ?? '—')) ?></p>
            </div>

            <?php if (!empty($order['report_text'])): ?>
            <div class="rounded-xl border bg-white p-4">
                <p class="text-xs uppercase text-slate-500">Report</p>
                <div class="mt-1 whitespace-pre-wrap text-sm"><?= htmlspecialchars((string) $order['report_text']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($order['impression'])): ?>
            <div class="rounded-xl border bg-white p-4">
                <p class="text-xs uppercase text-slate-500">Impression</p>
                <p class="mt-1 whitespace-pre-wrap text-sm font-medium"><?= htmlspecialchars((string) $order['impression']) ?></p>
            </div>
            <?php endif; ?>

            <?php
                $images = !empty($order['image_paths']) ? (json_decode((string) $order['image_paths'], true) ?: []) : [];
                if ($images):
            ?>
            <div class="rounded-xl border bg-white p-4">
                <p class="text-xs uppercase text-slate-500 mb-2">Images</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($images as $img): ?>
                    <a href="<?= htmlspecialchars((string) $img) ?>" target="_blank" class="block">
                        <img src="<?= htmlspecialchars((string) $img) ?>" alt="" class="h-32 w-32 rounded border object-cover">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
