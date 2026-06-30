<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($tenant['name'] ?? 'Clinic') ?> — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php
    $overview = $overview ?? [];
    $config = $overview['config'] ?? [];
    $directoryListing = $overview['directory_listing'] ?? null;
    $counts = $overview['counts'] ?? [];
    $users = $overview['users'] ?? [];
    $doctors = $overview['doctors'] ?? [];
    $workingHours = $overview['working_hours'] ?? [];
    $specialtySlug = (string) ($tenant['specialty'] ?? 'gp');
    $specialtyLabel = ($specialties ?? [])[$specialtySlug]['label'] ?? ucfirst($specialtySlug);
    ?>
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6 space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="/admin/clinics" class="text-xs text-slate-500 hover:underline">← All clinics</a>
                <h1 class="text-xl font-semibold mt-1"><?= htmlspecialchars($tenant['name']) ?></h1>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($tenant['slug']) ?> · ID <?= (int) $tenant['id'] ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">
                    <?= htmlspecialchars($planLabel ?? $tenant['plan'] ?? 'standard') ?>
                </span>
                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                    <?= htmlspecialchars($billingStatus ?? '—') ?>
                </span>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        <div class="rounded bg-rose-50 border border-rose-200 px-4 py-2 text-sm text-rose-800">
            <?php if ($error === 'confirm_slug'): ?>
                Type the clinic slug exactly to confirm deletion.
            <?php elseif ($error === 'delete_failed'): ?>
                Could not delete this clinic. Check server logs.
            <?php else: ?>
                <?= htmlspecialchars((string) $error) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ====== Clinic profile (from doctor onboarding) ====== -->
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Clinic profile</h2>
            <p class="mt-1 text-xs text-slate-500">Information entered during setup by the clinic admin / doctor.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <div class="text-xs text-slate-500">Specialty</div>
                    <div class="font-medium"><?= htmlspecialchars($specialtyLabel) ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Onboarding</div>
                    <div class="font-medium">
                        Step <?= (int) ($tenant['onboarding_step'] ?? 1) ?>
                        <?php if (!empty($tenant['onboarding_completed_at'])): ?>
                            · completed <?= htmlspecialchars((string) $tenant['onboarding_completed_at']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Phone</div>
                    <div class="font-medium"><?= htmlspecialchars($tenant['phone'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Email</div>
                    <div class="font-medium"><?= htmlspecialchars($tenant['email'] ?? '—') ?></div>
                </div>
                <div class="sm:col-span-2">
                    <div class="text-xs text-slate-500">Address</div>
                    <div class="font-medium whitespace-pre-line"><?= htmlspecialchars($tenant['address'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">GSTIN</div>
                    <div class="font-medium"><?= htmlspecialchars($tenant['gstin'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Country / currency</div>
                    <div class="font-medium"><?= htmlspecialchars($tenant['country_code'] ?? 'IN') ?> · <?= htmlspecialchars($tenant['currency'] ?? 'INR') ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">UHID prefix</div>
                    <div class="font-medium"><?= htmlspecialchars($config['uhid_prefix'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Consultation fee</div>
                    <div class="font-medium">
                        <?php
                        $listingFee = isset($directoryListing['consultation_fee']) && $directoryListing['consultation_fee'] !== null
                            ? (float) $directoryListing['consultation_fee']
                            : null;
                        ?>
                        <?= $listingFee !== null ? '₹' . number_format($listingFee, 0) : '—' ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Invoice tax</div>
                    <div class="font-medium">
                        <?= htmlspecialchars($config['invoice_tax_label'] ?? 'GST') ?>
                        <?= isset($config['invoice_tax_percent']) ? '(' . (float) $config['invoice_tax_percent'] . '%)' : '' ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">WhatsApp</div>
                    <div class="font-medium"><?= htmlspecialchars($config['whatsapp_number'] ?? '—') ?></div>
                </div>
                <?php if (!empty($tenant['logo_path'])): ?>
                <div class="sm:col-span-2">
                    <div class="text-xs text-slate-500 mb-1">Logo</div>
                    <img src="<?= htmlspecialchars($tenant['logo_path']) ?>" alt="Clinic logo" class="h-12 rounded border bg-white">
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($workingHours)): ?>
            <div class="mt-4 border-t border-slate-100 pt-4">
                <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Working hours</div>
                <ul class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                    <?php foreach ($workingHours as $day => $block): ?>
                    <li class="flex justify-between gap-2 rounded bg-slate-50 px-2 py-1">
                        <span class="uppercase text-xs text-slate-500"><?= htmlspecialchars((string) $day) ?></span>
                        <span class="text-right">
                            <?php if (empty($block['enabled'])): ?>
                                <span class="text-slate-400">Closed</span>
                            <?php else: ?>
                                <?php
                                $sessions = $block['sessions'] ?? [];
                                $parts = [];
                                foreach ($sessions as $s) {
                                    $parts[] = ($s['start'] ?? '') . '–' . ($s['end'] ?? '');
                                }
                                echo htmlspecialchars(implode(', ', $parts) ?: '—');
                                ?>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </section>

        <!-- ====== Usage stats ====== -->
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Usage</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5 text-center text-sm">
                <?php foreach (['patients' => 'Patients', 'appointments' => 'Appointments', 'visits' => 'Visits', 'invoices' => 'Invoices', 'users' => 'Team'] as $key => $label): ?>
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <div class="text-lg font-bold text-slate-900"><?= (int) ($counts[$key] ?? 0) ?></div>
                    <div class="text-xs text-slate-500"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ====== Team ====== -->
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Team members</h2>
            <?php if (empty($users)): ?>
                <p class="mt-2 text-xs text-slate-500">No users found.</p>
            <?php else: ?>
                <table class="mt-3 w-full text-left text-sm">
                    <thead class="text-xs text-slate-500">
                        <tr><th class="py-2">Name</th><th>Role</th><th>Login</th><th>Phone</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="border-t">
                            <td class="py-2 font-medium"><?= htmlspecialchars($u['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($u['role'] ?? '') ?></td>
                            <td class="text-xs text-slate-600">
                                <?= htmlspecialchars($u['email'] ?? $u['username'] ?? '—') ?>
                            </td>
                            <td><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (!empty($doctors)): ?>
            <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Doctor profiles</h3>
            <ul class="mt-2 divide-y text-sm">
                <?php foreach ($doctors as $d): ?>
                <li class="py-2 flex flex-wrap justify-between gap-2">
                    <span><?= htmlspecialchars($d['user_name'] ?? 'Doctor') ?></span>
                    <span class="text-slate-500 text-xs">
                        <?= htmlspecialchars($d['specialization'] ?? $d['specialty'] ?? '') ?>
                        <?php if (!empty($d['consultation_fee'])): ?>
                            · ₹<?= number_format((float) $d['consultation_fee'], 0) ?>
                        <?php endif; ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

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
            </div>

            <?php if (empty($tenant['trial_extension_granted']) && !empty($tenant['trial_ends_at'])): ?>
            <form method="post" action="/admin/clinics/<?= (int) $tenant['id'] ?>/extend-trial" class="mt-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">
                    Extend trial by 15 days (one-time)
                </button>
            </form>
            <?php endif; ?>

            <form method="post" action="/admin/clinics/<?= (int) $tenant['id'] ?>/plan" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Assign plan</label>
                    <select name="plan" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="standard" <?= ($tenant['plan'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard (full trial / paid)</option>
                        <option value="free" <?= ($tenant['plan'] ?? '') === 'free' ? 'selected' : '' ?>>Free (limited — admin only)</option>
                    </select>
                </div>
                <button type="submit" class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">
                    Update plan
                </button>
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

        <!-- ====== Danger zone ====== -->
        <section class="rounded-xl border border-rose-200 bg-rose-50/50 p-5">
            <h2 class="text-sm font-semibold text-rose-900">Delete clinic</h2>
            <p class="mt-1 text-xs text-rose-800/80">
                Permanently removes this clinic and <strong>all</strong> related data (patients, visits, appointments, billing, team, files).
                This cannot be undone. Use for demo / test cleanup.
            </p>
            <form method="post" action="/admin/clinics/<?= (int) $tenant['id'] ?>/delete"
                  class="mt-4 space-y-3"
                  onsubmit="return confirm('Delete <?= htmlspecialchars($tenant['name'], ENT_QUOTES) ?> and ALL its data forever?');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div>
                    <label class="block text-xs text-rose-900 mb-1">
                        Type <code class="rounded bg-white px-1"><?= htmlspecialchars($tenant['slug']) ?></code> to confirm
                    </label>
                    <input type="text" name="confirm_slug" autocomplete="off" required
                           class="w-full max-w-sm rounded border border-rose-300 px-3 py-2 text-sm"
                           placeholder="<?= htmlspecialchars($tenant['slug']) ?>">
                </div>
                <button type="submit" class="rounded bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                    Delete clinic permanently
                </button>
            </form>
        </section>

    </main>
</body>
</html>
