<form method="post" action="/settings/notifications" class="space-y-6 rounded-xl border bg-white p-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <h2 class="text-lg font-semibold">Notifications</h2>

    <?php if (!empty($message)): ?><p class="text-sm text-emerald-600"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="text-sm text-red-600"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="text-xs font-medium">WhatsApp number</label>
            <input name="whatsapp_number" value="<?= htmlspecialchars($config['whatsapp_number'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium">WhatsApp API token</label>
            <input name="whatsapp_token" type="password" placeholder="••••••" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
    </div>

    <div class="space-y-2 text-sm">
        <?php
        $toggles = [
            'appointment_reminder_24h' => 'Appointment reminder (24h)',
            'appointment_reminder_1h' => 'Appointment reminder (1h)',
            'rx_delivery' => 'Prescription delivery',
            'lab_report_ready' => 'Lab report ready',
            'follow_up_reminder' => 'Follow-up reminder',
        ];
        foreach ($toggles as $key => $label):
        ?>
        <label class="flex gap-2"><input type="checkbox" name="<?= $key ?>" value="1" <?= !empty($prefs[$key]) ? 'checked' : '' ?>> <?= htmlspecialchars($label) ?></label>
        <?php endforeach; ?>
    </div>

    <div class="border-t pt-4">
        <h3 class="text-sm font-medium">Razorpay (patient payments)</h3>
        <div class="mt-2 grid gap-4 sm:grid-cols-2">
            <input name="razorpay_key" placeholder="Key ID" class="rounded-lg border px-3 py-2 text-sm">
            <input name="razorpay_secret" type="password" placeholder="Secret" class="rounded-lg border px-3 py-2 text-sm">
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Save</button>
    </div>
</form>
<form method="post" action="/settings/test-whatsapp" class="inline">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <button type="submit" class="rounded-lg border px-4 py-2 text-sm">Test WhatsApp</button>
</form>
<form method="post" action="/settings/test-razorpay" class="inline">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <button type="submit" class="rounded-lg border px-4 py-2 text-sm">Test Razorpay</button>
</form>
