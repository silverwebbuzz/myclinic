<?php
/** /admin/messaging — WhatsApp/SMS control centre (super-admin). */
$val = static function (array $settings, string $key, bool $maskSecret = true): string {
    $row = $settings[$key] ?? null;
    if (!$row) return '';
    if ($maskSecret && (int) ($row['is_secret'] ?? 0) === 1 && !empty($row['setting_value'])) {
        return '••••••';
    }
    return (string) ($row['setting_value'] ?? '');
};
$enabled = ($settings['messaging_enabled']['setting_value'] ?? '0') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messaging — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl p-6 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Messaging — WhatsApp &amp; SMS</h1>
            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                <?= $enabled ? 'ENABLED' : 'OFF' ?>
            </span>
        </div>

        <?php if ($message): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <nav class="flex gap-3 text-sm">
            <a href="#connection" class="text-emerald-700 hover:underline">Connection</a>
            <a href="#templates" class="text-emerald-700 hover:underline">Templates</a>
            <a href="#rules" class="text-emerald-700 hover:underline">Rules &amp; cost control</a>
            <a href="#log" class="text-emerald-700 hover:underline">Log</a>
        </nav>

        <!-- ============ CONNECTION ============ -->
        <section id="connection" class="rounded-xl border bg-white p-5 scroll-mt-4">
            <h2 class="text-sm font-semibold">Connection &amp; credentials</h2>
            <p class="mt-1 text-xs text-slate-500">
                Meta WhatsApp Cloud API (platform-owned number). Webhook URL to paste into Meta:
                <code class="rounded bg-slate-100 px-1.5 py-0.5"><?= htmlspecialchars($webhookUrl) ?></code>
            </p>
            <form method="post" action="/admin/messaging/connection" class="mt-4 grid gap-3 sm:grid-cols-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

                <label class="flex items-center gap-2 sm:col-span-2 text-sm">
                    <input class="ui-checkbox" type="checkbox" name="messaging_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                    <span class="font-medium">Messaging enabled (master switch)</span>
                </label>

                <?php
                $fields = [
                    'wa_access_token' => 'WhatsApp Access Token',
                    'wa_phone_number_id' => 'Phone Number ID',
                    'wa_business_id' => 'Business ID',
                    'wa_webhook_verify_token' => 'Webhook Verify Token',
                    'wa_app_secret' => 'App Secret',
                    'sms_provider' => 'SMS Provider (msg91 / twilio)',
                    'sms_auth_key' => 'SMS Auth Key',
                    'sms_sender_id' => 'SMS Sender ID',
                ];
                foreach ($fields as $k => $label): ?>
                <label class="block text-xs">
                    <span class="text-slate-600"><?= htmlspecialchars($label) ?></span>
                    <input type="text" name="<?= $k ?>" value="<?= htmlspecialchars($val($settings, $k)) ?>"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm" autocomplete="off">
                </label>
                <?php endforeach; ?>

                <div class="grid grid-cols-3 gap-2 sm:col-span-2">
                    <label class="block text-xs"><span class="text-slate-600">Quota WhatsApp/mo</span>
                        <input type="number" name="quota_whatsapp_base" value="<?= htmlspecialchars($val($settings, 'quota_whatsapp_base', false)) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
                    <label class="block text-xs"><span class="text-slate-600">Quota SMS/mo</span>
                        <input type="number" name="quota_sms_base" value="<?= htmlspecialchars($val($settings, 'quota_sms_base', false)) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
                    <label class="block text-xs"><span class="text-slate-600">Global cap/mo (0=off)</span>
                        <input type="number" name="messaging_global_monthly_cap" value="<?= htmlspecialchars($val($settings, 'messaging_global_monthly_cap', false)) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
                    <label class="block text-xs"><span class="text-slate-600">Quiet start (hr)</span>
                        <input type="number" name="messaging_quiet_start" value="<?= htmlspecialchars($val($settings, 'messaging_quiet_start', false)) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
                    <label class="block text-xs"><span class="text-slate-600">Quiet end (hr)</span>
                        <input type="number" name="messaging_quiet_end" value="<?= htmlspecialchars($val($settings, 'messaging_quiet_end', false)) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Save connection</button>
                    <span class="ml-2 text-xs text-slate-400">Secrets shown as •••••• stay unchanged unless you type a new value.</span>
                </div>
            </form>

            <form method="post" action="/admin/messaging/test" class="mt-4 flex flex-wrap items-end gap-2 border-t pt-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <label class="text-xs">Test number
                    <input type="text" name="test_number" placeholder="9198xxxxxxxx" class="ml-1 rounded border px-2 py-1 text-sm"></label>
                <label class="text-xs">Template
                    <input type="text" name="test_template" value="patient_confirmed" class="ml-1 rounded border px-2 py-1 text-sm"></label>
                <button type="submit" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Send test</button>
            </form>
        </section>

        <!-- ============ TEMPLATES ============ -->
        <section id="templates" class="rounded-xl border bg-white p-5 scroll-mt-4">
            <h2 class="text-sm font-semibold">Templates (<?= count($templates) ?>)</h2>
            <p class="mt-1 text-xs text-slate-500">Approve here AFTER Meta approves the matching template name. Until approved, the system sends plain text / SMS fallback.</p>
            <?php if (empty($templates)): ?>
                <p class="mt-3 text-sm text-slate-500">No templates — run the messaging migration.</p>
            <?php else: ?>
            <div class="mt-3 space-y-3">
                <?php foreach ($templates as $t): ?>
                <form method="post" action="/admin/messaging/template/<?= (int) $t['id'] ?>" class="rounded-lg border p-3">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="flex items-center justify-between">
                        <code class="text-xs font-semibold"><?= htmlspecialchars($t['template_key']) ?></code>
                        <span class="rounded px-2 py-0.5 text-[10px] font-semibold <?= $t['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>"><?= htmlspecialchars($t['status']) ?></span>
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-4 text-xs">
                        <input type="text" name="meta_name" value="<?= htmlspecialchars($t['meta_name']) ?>" placeholder="Meta name" class="rounded border px-2 py-1">
                        <input type="text" name="language" value="<?= htmlspecialchars($t['language']) ?>" class="rounded border px-2 py-1">
                        <select name="category" class="rounded border px-2 py-1">
                            <?php foreach (['utility','marketing','authentication'] as $c): ?>
                            <option value="<?= $c ?>" <?= $t['category'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="rounded border px-2 py-1">
                            <?php foreach (['draft','submitted','approved','rejected','paused'] as $s): ?>
                            <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <textarea name="body_text" rows="2" class="mt-2 w-full rounded border px-2 py-1 text-xs" placeholder="Body with {{1}} {{2}}"><?= htmlspecialchars($t['body_text']) ?></textarea>
                    <textarea name="sms_fallback_text" rows="1" class="mt-1 w-full rounded border px-2 py-1 text-xs" placeholder="SMS fallback text"><?= htmlspecialchars((string) $t['sms_fallback_text']) ?></textarea>
                    <div class="mt-2 flex items-center justify-between">
                        <label class="text-xs"><input class="ui-checkbox" type="checkbox" name="is_active" value="1" <?= $t['is_active'] ? 'checked' : '' ?>> active</label>
                        <button type="submit" class="rounded bg-slate-800 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-900">Save</button>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- ============ RULES ============ -->
        <section id="rules" class="rounded-xl border bg-white p-5 scroll-mt-4">
            <h2 class="text-sm font-semibold">Rules &amp; cost control (<?= count($rules) ?>)</h2>
            <p class="mt-1 text-xs text-slate-500">Per audience + event + plan tier: which channel fires, and frequency caps (blank = no cap). Trial vs paid lets you throttle free clinics.</p>
            <?php if (empty($rules)): ?>
                <p class="mt-3 text-sm text-slate-500">No rules — run the messaging migration.</p>
            <?php else: ?>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-slate-500"><tr>
                        <th class="py-1">Audience</th><th>Event</th><th>Tier</th><th>Channel</th><th>Day</th><th>Week</th><th>Month</th><th>On</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rules as $r): ?>
                    <tr class="border-t">
                        <form method="post" action="/admin/messaging/rule/<?= (int) $r['id'] ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <td class="py-1.5"><?= htmlspecialchars($r['audience']) ?></td>
                        <td><?= htmlspecialchars($r['event_key']) ?></td>
                        <td><?= htmlspecialchars($r['plan_tier']) ?></td>
                        <td>
                            <select name="channel" class="rounded border px-1 py-0.5">
                                <?php foreach (['whatsapp','sms','push','off'] as $c): ?>
                                <option value="<?= $c ?>" <?= $r['channel'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="per_day_cap" value="<?= htmlspecialchars((string) ($r['per_day_cap'] ?? '')) ?>" class="w-12 rounded border px-1 py-0.5"></td>
                        <td><input type="number" name="per_week_cap" value="<?= htmlspecialchars((string) ($r['per_week_cap'] ?? '')) ?>" class="w-12 rounded border px-1 py-0.5"></td>
                        <td><input type="number" name="per_month_cap" value="<?= htmlspecialchars((string) ($r['per_month_cap'] ?? '')) ?>" class="w-12 rounded border px-1 py-0.5"></td>
                        <td><input class="ui-checkbox" type="checkbox" name="is_active" value="1" <?= $r['is_active'] ? 'checked' : '' ?>></td>
                        <td><button type="submit" class="rounded bg-slate-700 px-2 py-0.5 text-white">Save</button></td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <!-- ============ LOG ============ -->
        <section id="log" class="rounded-xl border bg-white p-5 scroll-mt-4">
            <h2 class="text-sm font-semibold">Recent messages</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-slate-500"><tr>
                        <th class="py-1">#</th><th>Clinic</th><th>Channel</th><th>Template</th><th>To</th><th>Status</th><th>Delivery</th><th>Fallback of</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($log as $n): ?>
                    <tr class="border-t">
                        <td class="py-1"><?= (int) $n['id'] ?></td>
                        <td><?= (int) $n['clinic_id'] ?></td>
                        <td><?= htmlspecialchars($n['channel']) ?></td>
                        <td><?= htmlspecialchars($n['template']) ?></td>
                        <td><?= htmlspecialchars((string) ($n['to_number'] ?? '')) ?></td>
                        <td><span class="<?= ($n['status'] ?? '') === 'failed' ? 'text-rose-600' : 'text-slate-700' ?>"><?= htmlspecialchars((string) $n['status']) ?></span></td>
                        <td><?= htmlspecialchars((string) ($n['delivery_status'] ?? '')) ?></td>
                        <td><?= $n['fallback_of'] ? '#' . (int) $n['fallback_of'] : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</body>
</html>
