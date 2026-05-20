<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Hello, <?= htmlspecialchars($patient['name'] ?? '') ?></h1>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($patient['uhid'] ?? '') ?></p>
        </div>
        <form method="post" action="/portal/logout">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="text-sm text-slate-500 hover:underline">Log out</button>
        </form>
    </div>

    <?php if (!empty($canBook)): ?>
    <a href="<?= htmlspecialchars($bookUrl) ?>" class="block rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        Book an appointment →
    </a>
    <?php endif; ?>

    <section class="rounded-xl border bg-white p-4">
        <h2 class="text-sm font-semibold">Upcoming appointments</h2>
        <?php if ($appointments === []): ?>
        <p class="mt-2 text-sm text-slate-500">None scheduled.</p>
        <?php else: ?>
        <ul class="mt-2 divide-y text-sm">
            <?php foreach ($appointments as $a): ?>
            <li class="py-2">
                <p class="font-medium"><?= htmlspecialchars(date('d M Y H:i', strtotime($a['scheduled_at']))) ?></p>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($a['doctor_name'] ?? '') ?>
                    <?php if (!empty($a['meet_link'])): ?> · <a href="<?= htmlspecialchars($a['meet_link']) ?>" class="text-emerald-600" target="_blank">Join Meet</a><?php endif; ?>
                </p>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-xl border bg-white p-4">
        <h2 class="text-sm font-semibold">Visits &amp; prescriptions</h2>
        <ul class="mt-2 divide-y text-sm">
            <?php foreach ($visits as $v): ?>
            <li class="py-2 flex justify-between gap-2">
                <span><?= htmlspecialchars(date('d M Y', strtotime($v['visited_at']))) ?> — <?= htmlspecialchars($v['diagnosis'] ?? $v['chief_complaint'] ?? 'Visit') ?></span>
                <?php if (!empty($v['download_token'])): ?>
                <a href="/portal/download/<?= urlencode($v['download_token']) ?>" class="text-emerald-600 text-xs shrink-0">Rx PDF</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="rounded-xl border bg-white p-4">
        <h2 class="text-sm font-semibold">Lab reports</h2>
        <ul class="mt-2 divide-y text-sm">
            <?php if ($labs === []): ?><li class="py-2 text-slate-500">No shared reports yet.</li><?php endif; ?>
            <?php foreach ($labs as $l): ?>
            <li class="py-2 flex justify-between">
                <span><?= htmlspecialchars($l['test_name'] ?? '') ?></span>
                <?php if (!empty($l['download_token'])): ?>
                <a href="/portal/download/<?= urlencode($l['download_token']) ?>" class="text-emerald-600 text-xs">Download</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="rounded-xl border bg-white p-4">
        <h2 class="text-sm font-semibold">Invoices</h2>
        <ul class="mt-2 divide-y text-sm">
            <?php foreach ($invoices as $inv): ?>
            <li class="py-2 flex justify-between">
                <span><?= htmlspecialchars($inv['invoice_number'] ?? '') ?> — ₹<?= number_format((float)($inv['total']??0),2) ?></span>
                <?php if (!empty($inv['download_token'])): ?>
                <a href="/portal/download/<?= urlencode($inv['download_token']) ?>" class="text-emerald-600 text-xs">PDF</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
