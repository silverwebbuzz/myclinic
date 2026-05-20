<div class="space-y-4" x-data="queueBoard(<?= (int) ($doctorId ?? 0) ?>)">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Today's queue</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/appointments" class="rounded-lg border px-4 py-2 text-sm">Calendar</a>
            <a href="/appointments/new" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">+ Book</a>
        </div>
    </div>

    <form method="get" class="flex gap-3">
        <select name="doctor_id" class="rounded-lg border px-3 py-2 text-sm" onchange="this.form.submit()">
            <option value="">All doctors</option>
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= (int) $doc['id'] ?>" <?= (int) ($doctorId ?? 0) === (int) $doc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($doc['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>

    <div id="queue-rows" class="divide-y rounded-xl border bg-white">
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
