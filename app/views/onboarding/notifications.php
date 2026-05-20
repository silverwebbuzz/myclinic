<?php
$title = 'Notifications — ManageClinic';
$prefs = $prefs ?? [];
ob_start();
?>
<h1 class="text-2xl font-semibold text-slate-900">Notification setup</h1>
<p class="mt-1 text-sm text-slate-500">WhatsApp reminders and patient billing (optional)</p>

<form method="post" action="/onboarding/notifications" class="mt-8 space-y-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
        <h2 class="text-sm font-medium text-slate-700">WhatsApp</h2>
        <div class="space-y-2 text-sm">
            <label class="flex items-start gap-3 rounded-lg border p-3 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="whatsapp_mode" value="shared" class="mt-1" <?= ($prefs['whatsapp_mode'] ?? 'shared') === 'shared' ? 'checked' : '' ?>>
                <span><strong>Use ManageClinic shared number</strong><br><span class="text-xs text-slate-500">50 messages/month free on Free plan</span></span>
            </label>
            <label class="flex items-start gap-3 rounded-lg border p-3 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="whatsapp_mode" value="own" class="mt-1" <?= ($prefs['whatsapp_mode'] ?? '') === 'own' ? 'checked' : '' ?>>
                <span><strong>Connect my WhatsApp Business</strong><br><span class="text-xs text-slate-500">Meta API token + phone number ID</span></span>
            </label>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-xs font-medium text-slate-600">WhatsApp number</label>
                <input name="whatsapp_number" value="<?= htmlspecialchars($config['whatsapp_number'] ?? '') ?>" placeholder="+91..." class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Meta API token (optional)</label>
                <input name="whatsapp_token" type="password" value="<?= htmlspecialchars($config['whatsapp_token'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-medium text-slate-700 mb-3">Send WhatsApp for</h2>
        <div class="grid gap-2 sm:grid-cols-2 text-sm">
            <?php
            $events = [
                'appointment_reminder_24h' => 'Appointment reminder (24h before)',
                'appointment_reminder_1h' => 'Appointment reminder (1h before)',
                'rx_delivery' => 'Prescription delivery',
                'lab_report_ready' => 'Lab report ready',
                'follow_up_reminder' => 'Follow-up reminder',
            ];
            foreach ($events as $key => $label):
            ?>
            <label class="flex gap-2"><input type="checkbox" name="<?= $key ?>" value="1" <?= !empty($prefs[$key]) ? 'checked' : '' ?>> <?= $label ?></label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
        <h2 class="text-sm font-medium text-slate-700">Patient UPI billing (optional)</h2>
        <p class="text-xs text-slate-500">Connect Razorpay to collect UPI payments at your billing counter</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-xs font-medium text-slate-600">Razorpay Key ID</label>
                <input name="razorpay_key" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="rzp_live_...">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Razorpay Secret</label>
                <input name="razorpay_secret" type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </section>

    <div class="flex justify-between">
        <a href="/onboarding/specialty-config" class="text-sm text-slate-500 hover:underline">← Back</a>
        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">Continue →</button>
    </div>
</form>
<?php
$innerContent = ob_get_clean();
require __DIR__ . '/_layout.php';
