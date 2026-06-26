<?php
/** /admin/outreach — doctor-acquisition worklist (super-admin). */
$f = $filters;
// Querystring carrying the current filters (for pagination + status return).
$qsParts = [];
foreach (['min_leads','city','specialty','status','q'] as $k) {
    if (!empty($f[$k])) $qsParts[$k] = $f[$k];
}
$baseQs = http_build_query($qsParts);
$statusBadge = [
    'not_contacted' => 'bg-slate-100 text-slate-600',
    'contacted'     => 'bg-sky-100 text-sky-800',
    'interested'    => 'bg-amber-100 text-amber-800',
    'joined'        => 'bg-emerald-100 text-emerald-800',
    'not_now'       => 'bg-slate-200 text-slate-700',
    'opted_out'     => 'bg-rose-100 text-rose-800',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Outreach · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-7xl px-6 py-6">
        <div class="flex items-baseline justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold">Doctor acquisition — outreach</h1>
                <p class="text-sm text-slate-500">Non-joined clinics receiving patient leads. Filter, call/message them, and track who joins. <strong><?= (int) $total ?></strong> clinics match.</p>
            </div>
            <a href="/admin/outreach/export<?= $baseQs ? '?' . htmlspecialchars($baseQs) : '' ?>"
               class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-900">
                ⬇ Export CSV
            </a>
        </div>

        <?php if ($message): ?>
        <div class="mt-3 rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="get" action="/admin/outreach" class="mt-4 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-5">
            <label class="block text-xs"><span class="text-slate-600">Min leads this month</span>
                <input type="number" min="0" name="min_leads" value="<?= htmlspecialchars((string) ($f['min_leads'] ?: '')) ?>" placeholder="e.g. 5" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
            <label class="block text-xs"><span class="text-slate-600">City</span>
                <select name="city" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <?php foreach ($options['cities'] as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $f['city'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">Specialty</span>
                <select name="specialty" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <?php foreach ($options['specialties'] as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $f['specialty'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">Outreach status</span>
                <select name="status" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <?php foreach ($statuses as $st): ?>
                    <option value="<?= $st ?>" <?= $f['status'] === $st ? 'selected' : '' ?>><?= htmlspecialchars(str_replace('_', ' ', $st)) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">Search name</span>
                <input type="text" name="q" value="<?= htmlspecialchars((string) $f['q']) ?>" placeholder="clinic / doctor" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
            <div class="sm:col-span-5 flex gap-2">
                <button type="submit" class="rounded bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Apply filters</button>
                <a href="/admin/outreach" class="rounded border px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Reset</a>
            </div>
        </form>

        <!-- Worklist -->
        <section class="mt-5 ui-card overflow-hidden rounded-xl border bg-white">
            <?php if (empty($rows)): ?>
            <p class="p-6 text-sm text-slate-500">No clinics match these filters.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Clinic / Doctor</th>
                            <th class="px-4 py-3 text-left">City</th>
                            <th class="px-4 py-3 text-right">Leads (mo)</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-left">Contact</th>
                            <th class="px-4 py-3 text-left">Status &amp; notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $r):
                            $st = (string) ($r['status'] ?? 'not_contacted');
                            $phone = (string) ($r['phone'] ?? '');
                            $waPhone = (string) ($r['intl_phone'] ?: $r['phone'] ?? '');
                            // WhatsApp-able only if it's a +91 mobile. Support /
                            // short-code numbers (1860, 1066, 500…) have no +91
                            // prefix → call-only, no WhatsApp link.
                            $isMobile = str_starts_with($waPhone, '+91');
                            $waDigits = $isMobile ? preg_replace('/\D+/', '', $waPhone) : '';
                        ?>
                        <tr class="hover:bg-slate-50 align-top">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($r['doctor_name'] ?: $r['name'])) ?></div>
                                <?php if ($r['doctor_name'] && $r['name'] !== $r['doctor_name']): ?>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars((string) $r['name']) ?></div>
                                <?php endif; ?>
                                <div class="text-[11px] text-slate-400"><?= htmlspecialchars((string) ($r['specialty'] ?? '')) ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <?= htmlspecialchars((string) ($r['city'] ?? '—')) ?>
                                <?php if (!empty($r['area'])): ?><div class="text-[11px] text-slate-400"><?= htmlspecialchars((string) $r['area']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900"><?= (int) $r['leads_this_month'] ?></td>
                            <td class="px-4 py-3 text-right text-slate-500"><?= (int) $r['leads_total'] ?></td>
                            <td class="px-4 py-3">
                                <?php if ($phone !== ''): ?>
                                <div class="flex flex-col gap-1">
                                    <a href="tel:<?= htmlspecialchars($phone) ?>" class="text-emerald-700 hover:underline">📞 <?= htmlspecialchars($phone) ?></a>
                                    <?php if ($waDigits): ?>
                                    <a href="https://wa.me/<?= htmlspecialchars($waDigits) ?>" target="_blank" rel="noopener" class="text-[11px] text-emerald-600 hover:underline">WhatsApp</a>
                                    <?php else: ?>
                                    <span class="text-[11px] text-slate-400">Call only</span>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span class="text-slate-400">No phone</span>
                                <?php endif; ?>
                                <?php if (!empty($r['last_contacted_at'])): ?>
                                <div class="mt-1 text-[10px] text-slate-400">Contacted <?= (int) $r['contacted_count'] ?>× · last <?= htmlspecialchars(date('M j', strtotime((string) $r['last_contacted_at']))) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="mb-1"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $statusBadge[$st] ?? 'bg-slate-100 text-slate-600' ?>"><?= htmlspecialchars(str_replace('_', ' ', $st)) ?></span></div>
                                <form method="post" action="/admin/outreach/status" class="flex flex-wrap items-center gap-1">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="directory_doctor_id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="return_qs" value="<?= htmlspecialchars($baseQs . ($baseQs ? '&' : '') . 'page=' . (int) $page) ?>">
                                    <select name="status" class="rounded border px-1.5 py-1 text-xs">
                                        <?php foreach ($statuses as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $st === $opt ? 'selected' : '' ?>><?= htmlspecialchars(str_replace('_', ' ', $opt)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="notes" value="<?= htmlspecialchars((string) ($r['notes'] ?? '')) ?>" placeholder="note…" class="w-32 rounded border px-1.5 py-1 text-xs">
                                    <button type="submit" class="rounded bg-slate-700 px-2 py-1 text-[11px] font-semibold text-white hover:bg-slate-900">Save</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="mt-4 flex items-center justify-between text-sm">
            <span class="text-slate-500">Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= (int) $total ?> clinics</span>
            <div class="flex gap-2">
                <?php
                $mk = fn (int $p) => '/admin/outreach?' . http_build_query(array_merge($qsParts, ['page' => $p]));
                ?>
                <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars($mk($page - 1)) ?>" class="rounded border px-3 py-1.5 hover:bg-white">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                <a href="<?= htmlspecialchars($mk($page + 1)) ?>" class="rounded border px-3 py-1.5 hover:bg-white">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <p class="mt-6 text-xs text-slate-400">
            Bulk WhatsApp/SMS/Email sending to a whole filtered segment is coming next (Phase 2).
            For now: filter → export CSV or use the per-row call / WhatsApp links, and mark status as you go.
        </p>
    </main>
</body>
</html>
