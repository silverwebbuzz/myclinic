<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($tenant['name'] ?? 'Clinic') ?> — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <a href="/admin/clinics" class="text-xs text-slate-500 hover:underline">← All clinics</a>
                <h1 class="text-xl font-semibold mt-1"><?= htmlspecialchars($tenant['name']) ?></h1>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($tenant['slug']) ?> · ID <?= (int) $tenant['id'] ?></p>
            </div>
            <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">
                <?= htmlspecialchars($tenant['plan'] ?? 'standard') ?>
            </span>
        </div>

        <?php if ($message): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <!-- ====== Trial controls ====== -->
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Trial &amp; subscription</h2>
            <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-xs text-slate-500">Trial ends</div>
                    <div class="font-medium"><?= htmlspecialchars($tenant['trial_ends_at'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Paid until</div>
                    <div class="font-medium"><?= htmlspecialchars($tenant['plan_expires_at'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Trial extension granted</div>
                    <div class="font-medium">
                        <?= empty($tenant['trial_extension_granted']) ? 'No' : 'Yes ('. htmlspecialchars($tenant['trial_extension_granted_at'] ?? '') .')' ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Founding clinic</div>
                    <div class="font-medium">
                        <?php if (!empty($tenant['is_founding_clinic'])): ?>
                            ₹999 locked until <?= htmlspecialchars($tenant['founding_clinic_locked_until'] ?? '—') ?>
                        <?php else: ?>
                            <span class="text-slate-400">No</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (empty($tenant['trial_extension_granted']) && !empty($tenant['trial_ends_at'])): ?>
            <form method="post" action="/admin/clinics/<?= (int) $tenant['id'] ?>/extend-trial" class="mt-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">
                    Extend trial by 15 days (one-time)
                </button>
            </form>
            <?php endif; ?>
        </section>

        <!-- ====== Founding clinic toggle ====== -->
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Founding clinic status</h2>
            <form method="post" action="/admin/clinics/<?= (int) $tenant['id'] ?>/founding" class="mt-3 space-y-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <?php if (empty($tenant['is_founding_clinic'])): ?>
                    <input type="hidden" name="enable" value="1">
                    <label class="block text-xs text-slate-500">Locked until (default 24 months from today)</label>
                    <input type="date" name="locked_until" value="<?= date('Y-m-d', strtotime('+24 months')) ?>"
                           class="rounded border px-2 py-1 text-sm">
                    <button type="submit" class="rounded bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600">
                        Grant founding clinic rate
                    </button>
                <?php else: ?>
                    <p class="text-xs text-slate-500">
                        Currently founding. Locked until
                        <strong><?= htmlspecialchars($tenant['founding_clinic_locked_until'] ?? '—') ?></strong>.
                    </p>
                    <button type="submit" class="rounded border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                        Revoke founding status
                    </button>
                <?php endif; ?>
            </form>
        </section>

        <!-- ====== Add-ons ====== -->
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Active add-ons</h2>
            <?php if (empty($modules)): ?>
                <p class="mt-2 text-xs text-slate-500">None.</p>
            <?php else: ?>
                <ul class="mt-3 divide-y text-sm">
                    <?php foreach ($modules as $m): ?>
                    <li class="flex items-center justify-between py-2">
                        <span>
                            <?= htmlspecialchars($m['module_name'] ?? $m['module_id']) ?>
                            <?php if (empty($m['is_active'])): ?>
                                <span class="ml-2 text-xs text-slate-400">(inactive)</span>
                            <?php endif; ?>
                        </span>
                        <form method="post" action="/admin/clinics/<?= (int) $tenant['id'] ?>/addon" class="inline">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="module_id" value="<?= htmlspecialchars($m['module_id']) ?>">
                            <?php if (!empty($m['is_active'])): ?>
                                <button type="submit" class="text-xs text-rose-600 hover:underline">Deactivate</button>
                            <?php else: ?>
                                <input type="hidden" name="activate" value="1">
                                <button type="submit" class="text-xs text-emerald-700 hover:underline">Re-activate</button>
                            <?php endif; ?>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="/admin/clinics/<?= (int) $tenant['id'] ?>/addon"
                  class="mt-4 flex items-center gap-2 border-t pt-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="activate" value="1">
                <select name="module_id" class="rounded border px-2 py-1 text-sm" required>
                    <option value="">Add an add-on…</option>
                    <?php foreach ($available as $a): ?>
                    <option value="<?= htmlspecialchars($a['id']) ?>">
                        <?= htmlspecialchars($a['name']) ?> (₹<?= number_format((float) ($a['price_monthly_usd'] ?? 0), 0) ?>/mo)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                    Activate
                </button>
            </form>
        </section>

        <!-- ====== Feature flags (read-only) ====== -->
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Feature flags for this clinic</h2>
            <p class="mt-1 text-xs text-slate-500">Read-only. Toggle scope or beta lists at <a href="/admin/feature-flags" class="text-emerald-700 hover:underline">/admin/feature-flags</a>.</p>
            <?php if (empty($flags)): ?>
                <p class="mt-2 text-xs text-slate-400">No flags configured.</p>
            <?php else: ?>
                <table class="mt-3 w-full text-left text-sm">
                    <thead class="text-xs text-slate-500">
                        <tr><th class="py-2">Flag</th><th>Scope</th><th>For this clinic</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($flags as $f): ?>
                        <tr class="border-t">
                            <td class="py-2"><?= htmlspecialchars($f['key']) ?></td>
                            <td><?= htmlspecialchars($f['scope']) ?></td>
                            <td>
                                <?php if ($f['on']): ?>
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">ON</span>
                                <?php else: ?>
                                    <span class="text-slate-400">off</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
