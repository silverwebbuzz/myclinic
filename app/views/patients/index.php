<?php
$qs = static function (array $extra) use ($filters, $sort, $dir): string {
    return http_build_query(array_filter(array_merge([
        'q' => $filters['q'] ?? '',
        'gender' => $filters['gender'] ?? '',
        'blood' => $filters['blood_group'] ?? '',
        'veg' => $filters['veg_type'] ?? '',
        'source' => $filters['source'] ?? '',
        'referred_by' => $filters['referred_by'] ?? '',
        'last_visit' => $filters['last_visit'] ?? '',
        'sort' => $sort,
        'dir' => $dir,
    ], $extra), static fn ($v) => $v !== '' && $v !== null));
};
$sortLink = static function (string $col) use ($sort, $dir, $qs): string {
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    return '?' . $qs(['sort' => $col, 'dir' => $nextDir]);
};
$sortIcon = static function (string $col) use ($sort, $dir): string {
    if ($sort !== $col) return '';
    return $dir === 'asc' ? ' ↑' : ' ↓';
};
?>
<div class="space-y-4" x-data="patientScanner()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-page-title">Patients <span class="ml-1 text-sm font-normal text-slate-500">(<?= (int) $total ?>)</span></h2>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="startScanner()" class="ui-btn ui-btn-secondary ui-btn-sm"><?= ui_icon('qr', 16) ?><span>Scan QR</span></button>
            <a href="/patients/new" class="ui-btn ui-btn-primary"><?= ui_icon('plus', 16) ?><span>New patient</span></a>
        </div>
    </div>

    <form method="get" class="ui-card ui-card-pad">
        <div class="flex flex-wrap gap-3">
            <input type="search" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>"
                   placeholder="Search name, phone, UHID…"
                   class="ui-input min-w-[220px] flex-1">
            <select name="gender" class="ui-input w-auto">
                <option value="">All genders</option>
                <?php foreach (['M' => 'Male', 'F' => 'Female', 'Other' => 'Other'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($filters['gender'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <select name="blood" class="ui-input w-auto">
                <option value="">Blood group</option>
                <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= ($filters['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                <?php endforeach; ?>
            </select>
            <select name="veg" class="ui-input w-auto">
                <option value="">Diet</option>
                <?php foreach (['veg' => 'Veg', 'nonveg' => 'Non-veg', 'vegan' => 'Vegan', 'eggetarian' => 'Eggetarian'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($filters['veg_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <select name="last_visit" class="ui-input w-auto">
                <option value="">Last visit</option>
                <?php foreach (['7d' => 'Within 7 days', '30d' => 'Within 30 days', '90d' => 'Within 90 days', 'never' => 'Never visited'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($filters['last_visit'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="ui-btn ui-btn-primary">Search</button>
            <a href="/patients" class="ui-btn ui-btn-secondary">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden ui-card">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 bg-slate-50 text-left ui-group-label">
                <tr>
                    <th class="px-4 py-3">
                        <a href="<?= htmlspecialchars($sortLink('uhid')) ?>" class="hover:underline">UHID<?= $sortIcon('uhid') ?></a>
                    </th>
                    <th class="px-4 py-3">
                        <a href="<?= htmlspecialchars($sortLink('name')) ?>" class="hover:underline">Name<?= $sortIcon('name') ?></a>
                    </th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Gender</th>
                    <th class="px-4 py-3">
                        <a href="<?= htmlspecialchars($sortLink('last_visit')) ?>" class="hover:underline">Last visit<?= $sortIcon('last_visit') ?></a>
                    </th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($patients as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs">
                        <a href="/patients/<?= (int) $p['id'] ?>" class="text-brand hover:underline">
                            <?= htmlspecialchars((string) ($p['uhid'] ?? '')) ?>
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <a href="/patients/<?= (int) $p['id'] ?>" class="font-medium text-slate-900 hover:text-brand hover:underline">
                            <?= htmlspecialchars((string) ($p['name'] ?? '')) ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($p['phone'] ?? '')) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($p['gender'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-xs text-slate-500">
                        <?= !empty($p['last_visit']) ? htmlspecialchars(substr((string) $p['last_visit'], 0, 10)) : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-3">
                            <a href="/patients/<?= (int) $p['id'] ?>" class="font-medium text-brand hover:underline">View</a>
                            <a href="/patients/<?= (int) $p['id'] ?>/edit" class="text-slate-500 hover:underline">Edit</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($patients)): ?>
        <p class="p-8 text-center text-sm text-slate-500">
            <?php if (!empty($filters['q']) || !empty($filters['gender']) || !empty($filters['blood_group']) || !empty($filters['veg_type']) || !empty($filters['last_visit'])): ?>
                No patients match these filters. <a href="/patients" class="text-brand hover:underline">Clear filters</a>.
            <?php else: ?>
                No patients yet. <a href="/patients/new" class="text-brand hover:underline">Register the first patient</a>.
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>

    <?php
    $totalPages = (int) ceil(max(1, $total) / max(1, $perPage));
    if ($totalPages > 1):
    ?>
    <div class="flex justify-center gap-2 text-sm">
        <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
        <a href="?<?= htmlspecialchars($qs(['page' => $p])) ?>"
           class="rounded-lg px-3 py-1.5 font-medium <?= $p === $page ? 'bg-brand text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <template x-if="openScanner">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-4">
                <h3 class="font-semibold">Scan patient QR</h3>
                <video id="qr-video" class="mt-3 w-full rounded-lg bg-black" autoplay playsinline></video>
                <p class="mt-2 text-xs text-slate-500">Point camera at patient QR code</p>
                <button type="button" @click="closeScanner()" class="mt-3 w-full rounded-lg border py-2 text-sm">Close</button>
            </div>
        </div>
    </template>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
function patientScanner() {
    return {
        openScanner: false,
        async startScanner() {
            this.openScanner = true;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                const video = document.getElementById('qr-video');
                video.srcObject = stream;
                this._scanLoop(video, stream);
            } catch (e) {
                this.openScanner = false;
                alert('Camera access denied or unavailable.');
            }
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
