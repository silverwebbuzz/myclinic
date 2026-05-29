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

        <div>
            <h3 class="ui-group-label mb-3">WhatsApp channel</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <?= ui_field('WhatsApp number', 'whatsapp_number', $config['whatsapp_number'] ?? '', ['placeholder' => '+91 98765 43210']) ?>
                <?= ui_field('WhatsApp API token', 'whatsapp_token', '', ['type' => 'password', 'placeholder' => '••••••', 'help' => 'Stored encrypted. Leave blank to keep current.']) ?>
            </div>
        </div>

        <div>
            <h3 class="ui-group-label mb-3">Automated patient messages</h3>
            <div class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                <?php
                $toggles = [
                    'appointment_reminder_24h' => ['Appointment reminder (24h)', 'Sent one day before the visit'],
                    'appointment_reminder_1h'  => ['Appointment reminder (1h)', 'A final nudge an hour before'],
                    'rx_delivery'              => ['Prescription delivery', 'Send the Rx to the patient on WhatsApp'],
                    'lab_report_ready'         => ['Lab report ready', 'Notify when results are uploaded'],
                    'follow_up_reminder'       => ['Follow-up reminder', 'Remind patients of their follow-up date'],
                ];
                foreach ($toggles as $key => [$label, $desc]):
                ?>
                <?= ui_toggle($key, '1', !empty($prefs[$key]), ['label' => $label, 'sub' => $desc]) ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <h3 class="ui-group-label mb-3">Razorpay (patient payments)</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <?= ui_field('Key ID', 'razorpay_key', '', ['placeholder' => 'rzp_live_…']) ?>
                <?= ui_field('Secret', 'razorpay_secret', '', ['type' => 'password', 'placeholder' => 'Secret']) ?>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
            <p class="ui-help">Changes apply to all patient-facing notifications.</p>
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
