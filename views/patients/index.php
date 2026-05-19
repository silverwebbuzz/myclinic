<div x-data="patientList()" class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Patients</h2>
        <a href="/patients/new" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">+ New patient</a>
    </div>

    <div class="rounded-xl border bg-white p-4">
        <div class="flex flex-wrap gap-3">
            <input type="search" x-model="filters.q" @input.debounce.300ms="fetchPatients()"
                   placeholder="Search name, phone, UHID…"
                   class="min-w-[200px] flex-1 rounded-lg border px-3 py-2 text-sm">
            <select x-model="filters.gender" @change="fetchPatients()" class="rounded-lg border px-2 py-2 text-sm">
                <option value="">All genders</option>
                <option value="M">Male</option>
                <option value="F">Female</option>
                <option value="Other">Other</option>
            </select>
            <select x-model="filters.blood" @change="fetchPatients()" class="rounded-lg border px-2 py-2 text-sm">
                <option value="">Blood group</option>
                <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                <option value="<?= $bg ?>"><?= $bg ?></option>
                <?php endforeach; ?>
            </select>
            <select x-model="filters.veg" @change="fetchPatients()" class="rounded-lg border px-2 py-2 text-sm">
                <option value="">Diet</option>
                <option value="veg">Veg</option>
                <option value="nonveg">Non-veg</option>
                <option value="vegan">Vegan</option>
                <option value="eggetarian">Eggetarian</option>
            </select>
            <select x-model="filters.last_visit" @change="fetchPatients()" class="rounded-lg border px-2 py-2 text-sm">
                <option value="">Last visit</option>
                <option value="7d">Within 7 days</option>
                <option value="30d">Within 30 days</option>
                <option value="90d">Within 90 days</option>
                <option value="never">Never visited</option>
            </select>
            <button type="button" @click="startScanner()" class="rounded-lg border px-3 py-2 text-sm">Scan QR</button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-3"><a href="?sort=uhid">UHID</a></th>
                    <th class="px-4 py-3"><a href="?sort=name">Name</a></th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Gender</th>
                    <th class="px-4 py-3"><a href="?sort=last_visit">Last visit</a></th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template x-for="p in rows" :key="p.id">
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs" x-text="p.uhid"></td>
                        <td class="px-4 py-3 font-medium" x-text="p.name"></td>
                        <td class="px-4 py-3" x-text="p.phone"></td>
                        <td class="px-4 py-3" x-text="p.gender || '—'"></td>
                        <td class="px-4 py-3 text-xs text-slate-500" x-text="p.last_visit ? p.last_visit.substring(0,10) : '—'"></td>
                        <td class="px-4 py-3 text-right">
                            <a :href="'/patients/' + p.id" class="text-emerald-600 hover:underline">View</a>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <?php if (empty($patients) && empty($filters['q'])): ?>
        <p class="p-8 text-center text-sm text-slate-500">No patients yet. <a href="/patients/new" class="text-emerald-600">Register the first patient</a>.</p>
        <?php endif; ?>
    </div>

    <?php
    $totalPages = (int) ceil(max(1, $total) / max(1, $perPage));
    if ($totalPages > 1):
    ?>
    <div class="flex justify-center gap-2 text-sm">
        <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
        <a href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"
           class="rounded px-2 py-1 <?= $p === $page ? 'bg-emerald-100 text-emerald-800' : 'border' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <div x-show="openScanner" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-4">
            <h3 class="font-semibold">Scan patient QR</h3>
            <video id="qr-video" class="mt-3 w-full rounded-lg bg-black" autoplay playsinline></video>
            <p class="mt-2 text-xs text-slate-500">Point camera at patient QR code</p>
            <button type="button" @click="closeScanner()" class="mt-3 w-full rounded-lg border py-2 text-sm">Close</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
function patientList() {
    return {
        rows: <?= json_encode($patients) ?>,
        total: <?= (int) $total ?>,
        page: <?= (int) $page ?>,
        filters: <?= json_encode($filters) ?>,
        openScanner: false,
        async fetchPatients() {
            const params = new URLSearchParams({ ...this.filters, page: this.page });
            const r = await fetch('/api/v1/patients/search?' + params, { credentials: 'same-origin' });
            if (!r.ok) return;
            const data = await r.json();
            this.rows = data.rows;
            this.total = data.total;
        },
        async startScanner() {
            this.openScanner = true;
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            const video = document.getElementById('qr-video');
            video.srcObject = stream;
            this._scanLoop(video, stream);
        },
        closeScanner() {
            this.openScanner = false;
            const video = document.getElementById('qr-video');
            if (video?.srcObject) video.srcObject.getTracks().forEach(t => t.stop());
        },
        _scanLoop(video, stream) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const tick = () => {
                if (!this.openScanner) return;
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0);
                    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(img.data, img.width, img.height);
                    if (code) {
                        const m = code.data.match(/\/qr\/([a-f0-9]{64})/);
                        if (m) { window.location.href = '/qr/' + m[1]; return; }
                    }
                }
                requestAnimationFrame(tick);
            };
            tick();
        }
    };
}
</script>
