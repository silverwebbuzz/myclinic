<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lead SMS settings · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-4xl px-6 py-6">
        <a href="/admin/leads" class="text-sm text-slate-600 hover:text-slate-900">&larr; Back to leads</a>

        <h1 class="mt-3 text-xl font-semibold">Lead SMS settings</h1>
        <p class="text-sm text-slate-500">Controls how (and how often) we text unclaimed doctors about new patient bookings.</p>

        <?php if (!empty($message)): ?>
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <?= match ($message) {
                'saved'           => '✓ Settings saved.',
                'override_saved'  => '✓ Doctor quota saved.',
                'invalid'         => '⚠ Invalid request.',
                default           => htmlspecialchars((string) $message),
            } ?>
        </div>
        <?php endif; ?>

        <!-- ===== Global settings ===== -->
        <form method="post" action="/admin/lead-settings" class="mt-6 space-y-6 ui-card ui-card-pad">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrf) ?>">

            <!-- Master toggle -->
            <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div>
                    <div class="font-semibold text-slate-900">Lead SMS dispatch</div>
                    <div class="text-xs text-slate-500">Master switch. When OFF, leads still record in DB but no SMS goes out.</div>
                </div>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input class="ui-checkbox" type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?> class="peer sr-only">
                    <div class="h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-emerald-500 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            <!-- Default quotas -->
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Default quotas per doctor</h2>
                <p class="text-xs text-slate-500">Most doctors will follow these. Override per-doctor in the table below.</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                    <label>
                        <span class="text-xs font-medium text-slate-600">Max per day</span>
                        <input type="number" name="default_per_day" min="0" max="50" value="<?= (int) ($settings['default_per_day'] ?? 2) ?>"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">Max per week</span>
                        <input type="number" name="default_per_week" min="0" max="100" value="<?= (int) ($settings['default_per_week'] ?? 5) ?>"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">Max per month</span>
                        <input type="number" name="default_per_month" min="0" max="500" value="<?= (int) ($settings['default_per_month'] ?? 20) ?>"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                </div>
            </div>

            <!-- Quiet hours -->
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Quiet hours</h2>
                <p class="text-xs text-slate-500">No SMS during this window (TRAI best practice for transactional SMS).</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="text-xs font-medium text-slate-600">No SMS after</span>
                        <input type="time" name="quiet_hours_start" value="<?= htmlspecialchars(substr((string) ($settings['quiet_hours_start'] ?? '21:00:00'), 0, 5)) ?>"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">Resume after</span>
                        <input type="time" name="quiet_hours_end" value="<?= htmlspecialchars(substr((string) ($settings['quiet_hours_end'] ?? '08:00:00'), 0, 5)) ?>"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                </div>
            </div>

            <!-- Template -->
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">SMS template</h2>
                <p class="text-xs text-slate-500">
                    Available variables:
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">{patient_name}</code>
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">{date}</code>
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">{time}</code>
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">{url}</code>
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">{clinic}</code>
                </p>
                <textarea name="template_body" rows="3" maxlength="500"
                          class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"><?= htmlspecialchars((string) ($settings['template_body'] ?? '')) ?></textarea>
                <p class="mt-1 text-[11px] text-slate-500">⚠ Keep under 160 chars to avoid multi-part billing. Always include opt-out wording for TRAI compliance.</p>

                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="text-xs font-medium text-slate-600">DLT template ID (MSG91)</span>
                        <input type="text" name="provider_template_id" value="<?= htmlspecialchars((string) ($settings['provider_template_id'] ?? '')) ?>"
                               placeholder="e.g. 1707xxxxxxxxxxx"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">Landing URL base</span>
                        <input type="text" name="lead_landing_base" value="<?= htmlspecialchars((string) ($settings['lead_landing_base'] ?? 'https://eclinicpro.com/L/')) ?>"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Save settings
                </button>
            </div>
        </form>

        <!-- ===== Per-doctor overrides ===== -->
        <section class="mt-6 ui-card">
            <div class="border-b p-4">
                <h2 class="text-base font-semibold text-slate-900">Per-doctor overrides</h2>
                <p class="text-xs text-slate-500">
                    Custom quotas + paused doctors. Doctors who texted STOP appear here automatically.
                    To add a new override, paste the doctor's directory ID below.
                </p>
            </div>

            <?php if (empty($overrides)): ?>
            <p class="p-6 text-sm text-slate-500">No overrides yet. All doctors use the defaults above.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Doctor</th>
                            <th class="px-4 py-3 text-right">/day</th>
                            <th class="px-4 py-3 text-right">/week</th>
                            <th class="px-4 py-3 text-right">/month</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Reason</th>
                            <th class="px-4 py-3 text-left">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($overrides as $o): ?>
                        <tr class="<?= !empty($o['is_paused']) ? 'bg-rose-50/40' : '' ?>">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($o['doctor_name'] ?: $o['clinic_name'])) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars((string) ($o['city'] ?? '')) ?> · #<?= (int) $o['directory_doctor_id'] ?></div>
                            </td>
                            <td class="px-4 py-3 text-right"><?= isset($o['per_day']) && $o['per_day'] !== null ? (int) $o['per_day'] : '<span class="text-slate-400">default</span>' ?></td>
                            <td class="px-4 py-3 text-right"><?= isset($o['per_week']) && $o['per_week'] !== null ? (int) $o['per_week'] : '<span class="text-slate-400">default</span>' ?></td>
                            <td class="px-4 py-3 text-right"><?= isset($o['per_month']) && $o['per_month'] !== null ? (int) $o['per_month'] : '<span class="text-slate-400">default</span>' ?></td>
                            <td class="px-4 py-3">
                                <?php if (!empty($o['is_paused'])): ?>
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-800">Paused</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars((string) ($o['pause_reason'] ?? '—')) ?></td>
                            <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars(date('M j, H:i', strtotime((string) $o['updated_at']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Add / edit override -->
            <form method="post" action="/admin/lead-settings/doctor-quota" class="border-t bg-slate-50 p-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrf) ?>">
                <h3 class="text-sm font-semibold text-slate-900">Add / update an override</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-6">
                    <label class="sm:col-span-2">
                        <span class="text-xs font-medium text-slate-600">Directory doctor ID</span>
                        <input type="number" name="doctor_id" required min="1"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">/day</span>
                        <input type="number" name="per_day" min="0" max="50"
                               placeholder="(default)"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">/week</span>
                        <input type="number" name="per_week" min="0" max="100"
                               placeholder="(default)"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">/month</span>
                        <input type="number" name="per_month" min="0" max="500"
                               placeholder="(default)"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
                    </label>
                    <label class="flex items-end gap-2">
                        <input type="checkbox" name="is_paused" value="1" class="rounded">
                        <span class="text-xs font-medium text-slate-600">Pause SMS</span>
                    </label>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        Save override
                    </button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
