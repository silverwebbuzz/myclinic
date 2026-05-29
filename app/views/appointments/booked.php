<div class="mx-auto max-w-lg space-y-4 rounded-xl border bg-white p-8 text-center">
    <p class="text-4xl">✓</p>
    <h2 class="text-xl font-semibold">Appointment booked</h2>
    <p class="text-sm text-slate-600">
        <?= htmlspecialchars($appointment['patient_name'] ?? '') ?> with
        <?= htmlspecialchars($appointment['doctor_name'] ?? '') ?>
        on <?= htmlspecialchars(date('d M Y, H:i', strtotime($appointment['scheduled_at']))) ?>
    </p>
    <?php if (!empty($appointment['token_number'])): ?>
    <p class="text-2xl font-bold text-emerald-700">Token #<?= (int) $appointment['token_number'] ?></p>
    <?php endif; ?>
    <p class="text-xs text-slate-500">WhatsApp reminder queued for 24 hours before visit.</p>
    <div class="flex flex-wrap justify-center gap-3 pt-4">
        <?php if (!empty($slipUrl)): ?>
        <a href="<?= htmlspecialchars($slipUrl) ?>" target="_blank" class="ui-btn ui-btn-secondary">Download slip</a>
        <?php endif; ?>
        <a href="/queue" class="ui-btn ui-btn-primary">View queue</a>
        <a href="/appointments/new" class="ui-btn ui-btn-secondary">Book another</a>
    </div>
</div>
