<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lead analytics · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <div class="flex items-baseline justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold">Lead pipeline</h1>
                <p class="text-sm text-slate-500">Patient bookings on unclaimed directory clinics — your doctor-acquisition funnel.</p>
            </div>
            <a href="/admin/lead-settings" class="ui-btn ui-btn-secondary ui-btn-sm">
                <?= ui_icon('settings', 15) ?><span>SMS settings</span>
            </a>
        </div>

        <!-- KPI tiles -->
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="ui-card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Bookings (30d)</div>
                <div class="mt-1 text-2xl font-bold text-slate-900"><?= (int) ($kpis['book_submitted_30d'] ?? 0) ?></div>
                <div class="text-xs text-slate-500">All time: <?= (int) ($kpis['book_submitted_total'] ?? 0) ?></div>
            </div>
            <div class="ui-card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">This week</div>
                <div class="mt-1 text-2xl font-bold text-slate-900"><?= (int) ($kpis['book_submitted_7d'] ?? 0) ?></div>
                <div class="text-xs text-slate-500">vs. <?= max(0, (int) ($kpis['book_submitted_30d'] ?? 0) - (int) ($kpis['book_submitted_7d'] ?? 0)) ?> in 7–30d</div>
            </div>
            <div class="ui-card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">SMS sent (30d)</div>
                <div class="mt-1 text-2xl font-bold text-slate-900"><?= (int) ($kpis['sms_sent_30d'] ?? 0) ?></div>
                <div class="text-xs text-slate-500">Est. spend: ₹<?= number_format(((int) ($kpis['sms_sent_30d'] ?? 0)) * 0.20, 2) ?></div>
            </div>
            <div class="ui-card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Doctor-view rate</div>
                <div class="mt-1 text-2xl font-bold text-emerald-600"><?= htmlspecialchars((string) ($kpis['doctor_view_rate'] ?? 0)) ?>%</div>
                <div class="text-xs text-slate-500"><?= (int) ($kpis['doctor_views_total'] ?? 0) ?> doctors opened SMS</div>
            </div>
        </div>

        <!-- SMS dispatch breakdown -->
        <section class="mt-6 ui-card p-4">
            <h2 class="text-sm font-semibold text-slate-900">SMS dispatch breakdown (last 30d)</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php
                $statusLabels = [
                    'sent'              => ['Sent',                'bg-emerald-100 text-emerald-800'],
                    'pending'           => ['Pending',             'bg-slate-100 text-slate-700'],
                    'suppressed_quota'  => ['Suppressed (quota)',  'bg-amber-100 text-amber-800'],
                    'suppressed_quiet'  => ['Suppressed (quiet)',  'bg-sky-100 text-sky-800'],
                    'suppressed_paused' => ['Suppressed (paused)', 'bg-rose-100 text-rose-800'],
                    'failed'            => ['Failed',              'bg-red-200 text-red-900'],
                    'not_applicable'    => ['Not applicable',      'bg-slate-100 text-slate-500'],
                ];
                foreach ($smsBreakdown as $b):
                    $label = $statusLabels[$b['sms_status']] ?? [$b['sms_status'], 'bg-slate-100 text-slate-700'];
                ?>
                <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold <?= $label[1] ?>">
                    <?= htmlspecialchars($label[0]) ?>
                    <span class="rounded-full bg-white/60 px-1.5 py-0 text-[10px]"><?= (int) $b['n'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($smsBreakdown)): ?>
                <p class="text-xs text-slate-500">No SMS attempts in the last 30 days yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Sales call list -->
        <section class="mt-6 ui-card">
            <div class="border-b p-4">
                <h2 class="ui-section-title">Sales call list — top unclaimed doctors by lead volume</h2>
                <p class="mt-0.5 text-xs text-slate-500">Last 30 days. Call these doctors first — they're getting demand they don't even know about.</p>
            </div>
            <?php if (empty($topDoctors)): ?>
            <p class="p-6 text-sm text-slate-500">No lead activity yet.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Clinic / Doctor</th>
                            <th class="px-4 py-3 text-left">City</th>
                            <th class="px-4 py-3 text-left">Specialty</th>
                            <th class="px-4 py-3 text-right">Leads</th>
                            <th class="px-4 py-3 text-right">SMS sent</th>
                            <th class="px-4 py-3 text-right">Opened</th>
                            <th class="px-4 py-3 text-left">Last lead</th>
                            <th class="px-4 py-3 text-left">Contact</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($topDoctors as $d): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($d['doctor_name'] ?: $d['name'])) ?></div>
                                <?php if ($d['doctor_name'] && $d['name'] !== $d['doctor_name']): ?>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars((string) $d['name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($d['city'] ?? '—')) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($d['specialty'] ?? '—')) ?></td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900"><?= (int) $d['lead_count'] ?></td>
                            <td class="px-4 py-3 text-right text-slate-600"><?= (int) $d['sms_sent'] ?></td>
                            <td class="px-4 py-3 text-right">
                                <?php if ((int) $d['landing_views'] > 0): ?>
                                    <span class="font-semibold text-emerald-600"><?= (int) $d['landing_views'] ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500"><?= !empty($d['last_lead_at']) ? htmlspecialchars(date('M j', strtotime((string) $d['last_lead_at']))) : '—' ?></td>
                            <td class="px-4 py-3">
                                <?php if (!empty($d['phone'])): ?>
                                <a href="tel:<?= htmlspecialchars((string) $d['phone']) ?>" class="text-emerald-700 hover:underline"><?= htmlspecialchars((string) $d['phone']) ?></a>
                                <?php else: ?>
                                <span class="text-slate-400">No phone</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($d['is_claimed']): ?>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Claimed</span>
                                <?php else: ?>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Unclaimed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <!-- City heatmap -->
        <section class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="ui-card p-4">
                <h2 class="ui-section-title">Top cities (unclaimed demand)</h2>
                <p class="text-xs text-slate-500">Where to focus your acquisition efforts.</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($topCities as $i => $c): ?>
                    <li class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
                        <span class="flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-600"><?= $i + 1 ?></span>
                            <span class="font-medium text-slate-900"><?= htmlspecialchars((string) ($c['city'] ?? '—')) ?></span>
                            <span class="text-xs text-slate-500"><?= htmlspecialchars((string) ($c['state'] ?? '')) ?></span>
                        </span>
                        <span class="text-xs text-slate-600">
                            <strong class="text-slate-900"><?= (int) $c['lead_count'] ?></strong> leads
                            · <?= (int) $c['clinics'] ?> clinics
                        </span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($topCities)): ?>
                    <li class="text-xs text-slate-500">No data yet.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Daily sparkline -->
            <div class="ui-card p-4">
                <h2 class="ui-section-title">Bookings — last 30 days</h2>
                <p class="text-xs text-slate-500">Daily count of patient booking submissions.</p>
                <div class="mt-3 flex h-32 items-end gap-1">
                    <?php
                    $max = max(1, max(array_map(static fn ($r) => (int) $r['n'], $series ?: [['n' => 0]])));
                    // Make sure we render the full 30-day window even when some days are 0.
                    $byDay = [];
                    foreach ($series as $r) $byDay[$r['d']] = (int) $r['n'];
                    for ($i = 29; $i >= 0; $i--):
                        $day = date('Y-m-d', strtotime("-$i days"));
                        $n = $byDay[$day] ?? 0;
                        $h = (int) round($n / $max * 100);
                    ?>
                    <div class="group relative flex-1">
                        <div class="rounded-t bg-emerald-400 transition group-hover:bg-emerald-600"
                             style="height: <?= max(2, $h) ?>%;"
                             title="<?= htmlspecialchars(date('M j', strtotime($day))) ?>: <?= $n ?> booking<?= $n === 1 ? '' : 's' ?>"></div>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-slate-400">
                    <span><?= date('M j', strtotime('-29 days')) ?></span>
                    <span>Today</span>
                </div>
            </div>
        </section>

        <!-- Recent leads feed -->
        <section class="mt-6 ui-card">
            <div class="border-b p-4">
                <h2 class="ui-section-title">Recent leads</h2>
                <p class="mt-0.5 text-xs text-slate-500">Newest first. Click a row to view details / contact patient.</p>
            </div>
            <?php if (empty($recent)): ?>
            <p class="p-6 text-sm text-slate-500">No leads recorded yet.</p>
            <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recent as $l): ?>
                <div class="flex flex-wrap items-center gap-3 p-4 hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="text-slate-900"><?= htmlspecialchars((string) ($l['patient_name'] ?? 'Patient')) ?></strong>
                            <span class="text-xs text-slate-400">→</span>
                            <span class="text-slate-700"><?= htmlspecialchars((string) ($l['doctor_name'] ?: $l['clinic_name'])) ?></span>
                            <?php if ($l['is_claimed']): ?>
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Claimed</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            <?= htmlspecialchars((string) ($l['clinic_city'] ?? '')) ?>
                            <?php if ($l['preferred_date']): ?>
                                · <?= htmlspecialchars(date('M j', strtotime((string) $l['preferred_date']))) ?>
                                <?= htmlspecialchars($l['preferred_time'] ? '@ ' . date('g:i A', strtotime('2000-01-01 ' . $l['preferred_time'])) : '') ?>
                            <?php endif; ?>
                            <?php if (!empty($l['reason'])): ?>
                                · <?= htmlspecialchars(mb_substr((string) $l['reason'], 0, 60)) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right text-xs">
                        <?php
                        $smsBadge = $statusLabels[$l['sms_status']] ?? [$l['sms_status'], 'bg-slate-100 text-slate-600'];
                        ?>
                        <span class="rounded-full px-2 py-0.5 font-semibold <?= $smsBadge[1] ?>"><?= htmlspecialchars($smsBadge[0]) ?></span>
                        <?php if (!empty($l['doctor_viewed_at'])): ?>
                        <div class="mt-1 text-emerald-600">Doctor opened</div>
                        <?php endif; ?>
                        <div class="mt-1 text-slate-400"><?= htmlspecialchars(date('M j H:i', strtotime((string) $l['created_at']))) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
