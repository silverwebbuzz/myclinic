<form method="post" action="/settings/notifications" class="ui-card">
    <div class="ui-card-pad space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <?php if (!empty($message)): ?><p class="text-sm text-emerald-600"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p class="text-sm text-red-600"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div>
            <h3 class="ui-group-label mb-3">Send reminders for</h3>
            <div class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                <?php
                $toggles = [
                    'appointment_reminder_24h' => ['Appointment reminder (24h before)', 'Sent one day before the visit'],
                    'appointment_reminder_1h'  => ['Appointment reminder (1h before)', 'A final nudge an hour before'],
                    'rx_delivery'              => ['Prescription delivery', 'Send the Rx to the patient on WhatsApp'],
                    'follow_up_reminder'       => ['Follow-up reminder', 'Remind patients of their follow-up date'],
                ];
                foreach ($toggles as $key => [$label, $desc]):
                ?>
                <?= ui_toggle($key, '1', !empty($prefs[$key]), ['label' => $label, 'sub' => $desc]) ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="ui-btn ui-btn-primary">Save changes</button>
        </div>
    </div>
</form>
