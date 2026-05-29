<form method="post" action="/settings/notifications" class="ui-card">
    <div class="ui-card-header">
        <div>
            <h2 class="ui-section-title">Notifications</h2>
            <p class="ui-section-sub mt-0.5">Configure patient messaging channels and reminders.</p>
        </div>
    </div>
    <div class="ui-card-pad space-y-6">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <?php if (!empty($message)): ?><p class="text-sm text-emerald-600"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p class="text-sm text-red-600"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="grid gap-4 sm:grid-cols-2">
            <?= ui_field('WhatsApp number', 'whatsapp_number', $config['whatsapp_number'] ?? '') ?>
            <?= ui_field('WhatsApp API token', 'whatsapp_token', '', ['type' => 'password', 'placeholder' => '••••••']) ?>
        </div>

        <div class="divide-y divide-slate-100 rounded-lg border border-slate-200">
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
            <div class="px-4"><?= ui_toggle($key, '1', !empty($prefs[$key]), ['label' => $label]) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="border-t border-slate-100 pt-5">
            <h3 class="ui-label">Razorpay (patient payments)</h3>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <input name="razorpay_key" placeholder="Key ID" class="ui-input">
                <input name="razorpay_secret" type="password" placeholder="Secret" class="ui-input">
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-5">
            <button type="submit" class="ui-btn ui-btn-primary">Save changes</button>
        </div>
    </div>
</form>
<div class="mt-3 flex flex-wrap gap-2">
    <form method="post" action="/settings/test-whatsapp">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm">Test WhatsApp</button>
    </form>
    <form method="post" action="/settings/test-razorpay">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm">Test Razorpay</button>
    </form>
</div>
