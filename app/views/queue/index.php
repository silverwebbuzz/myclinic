<?php
$statusToasts = [
    'status_updated' => ['Status updated.', 'success'],
    'called_next' => ['Next patient called in.', 'success'],
    'queue_empty' => ['No waiting patients to call.', 'error'],
];
$errorToasts = [
    'invalid_status' => 'That status is not valid — please pick one from the list.',
    'not_found' => 'That appointment no longer exists. The queue has been refreshed.',
];
$toast = $statusToasts[$_GET['message'] ?? ''] ?? null;
if ($toast === null && isset($errorToasts[$_GET['error'] ?? ''])) {
    $toast = [$errorToasts[$_GET['error']], 'error'];
}
?>
<div class="space-y-4" x-data="queueBoard(<?= (int) ($doctorId ?? 0) ?>)">
    <?php if ($toast !== null): ?>
    <div x-init="showToast(<?= htmlspecialchars(json_encode($toast[0]), ENT_QUOTES) ?>, '<?= $toast[1] ?>')"></div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Today's queue</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/appointments" class="ui-btn ui-btn-secondary">Calendar</a>
            <a href="/appointments/new" class="ui-btn ui-btn-secondary">+ Book appointment</a>
            <form method="post" action="/queue/call-next"
                  @submit="$event.target.querySelector('button[type=submit]').disabled = true">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <input type="hidden" name="doctor_id" value="<?= (int) ($doctorId ?? 0) ?: '' ?>">
                <button type="submit" class="ui-btn ui-btn-primary">Call next patient</button>
            </form>
        </div>
    </div>

    <form method="get" class="flex items-center gap-2">
        <label for="queue-doctor-filter" class="text-xs font-medium text-slate-500">Doctor</label>
        <select id="queue-doctor-filter" name="doctor_id" class="ui-input" onchange="this.form.submit()">
            <option value="">All doctors</option>
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= (int) $doc['id'] ?>" <?= (int) ($doctorId ?? 0) === (int) $doc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($doc['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>

    <div id="queue-rows" class="divide-y ui-card">
        <?php require __DIR__ . '/_rows.php'; ?>
    </div>
    <p class="text-center text-xs text-slate-400">
        Auto-refreshes every 30 seconds<span x-show="lastRefresh" x-cloak> · updated <span x-text="lastRefresh"></span></span>
    </p>
</div>

<script>
function queueBoard(doctorId) {
    return {
        doctorId: doctorId || '',
        lastRefresh: null,
        init() {
            setInterval(() => this.refresh(), 30000);
        },
        async refresh() {
            // Don't yank the DOM out from under an open status dropdown.
            const active = document.activeElement;
            if (active && active.tagName === 'SELECT' && document.getElementById('queue-rows').contains(active)) return;
            try {
                const params = new URLSearchParams();
                if (this.doctorId) params.set('doctor_id', this.doctorId);
                const r = await fetch('/api/v1/queue?' + params, {
                    credentials: 'same-origin', headers: { 'Accept': 'application/json' },
                });
                if (!r.ok) return;
                const data = await r.json();
                const el = document.getElementById('queue-rows');
                if (el && data.html) el.innerHTML = data.html;
                this.lastRefresh = new Date().toLocaleTimeString();
            } catch (e) { /* offline — keep showing the last good queue */ }
        },
    };
}
</script>
