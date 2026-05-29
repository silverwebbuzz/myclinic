<div class="space-y-4" x-data="queueBoard(<?= (int) ($doctorId ?? 0) ?>)">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Today's queue</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/appointments" class="ui-btn ui-btn-secondary">Calendar</a>
            <a href="/appointments/new" class="ui-btn ui-btn-primary">+ Book</a>
        </div>
    </div>

    <form method="get" class="flex gap-3">
        <select name="doctor_id" class="ui-input" onchange="this.form.submit()">
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
    <p class="text-center text-xs text-slate-400">Auto-refreshes every 30 seconds</p>
</div>

<script>
function queueBoard(doctorId) {
    return {
        doctorId: doctorId || '',
        init() {
            setInterval(() => this.refresh(), 30000);
        },
        async refresh() {
            const params = new URLSearchParams();
            if (this.doctorId) params.set('doctor_id', this.doctorId);
            const r = await fetch('/api/v1/queue?' + params);
            const data = await r.json();
            const el = document.getElementById('queue-rows');
            if (el && data.html) el.innerHTML = data.html;
        },
    };
}
</script>
