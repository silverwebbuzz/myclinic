<?php
$title = 'Notifications — ManageClinic';
$prefs = $prefs ?? [];
ob_start();
?>
<h1 class="text-2xl font-semibold text-slate-900">Notification setup</h1>
<p class="mt-1 text-sm text-slate-500">Choose which automated reminders to send</p>

<form method="post" action="/onboarding/notifications"
      data-onboarding-draft="/onboarding/notifications/draft"
      class="mt-8 space-y-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-medium text-slate-700 mb-3">Send reminders for</h2>
        <div class="grid gap-2 sm:grid-cols-2 text-sm">
            <?php
            $events = [
                'appointment_reminder_24h' => 'Appointment reminder (24h before)',
                'appointment_reminder_1h' => 'Appointment reminder (1h before)',
                'rx_delivery' => 'Prescription delivery',
                'follow_up_reminder' => 'Follow-up reminder',
            ];
            foreach ($events as $key => $label):
            ?>
            <label class="flex gap-2"><input type="checkbox" name="<?= $key ?>" value="1" <?= !empty($prefs[$key]) ? 'checked' : '' ?>> <?= $label ?></label>
            <?php endforeach; ?>
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
