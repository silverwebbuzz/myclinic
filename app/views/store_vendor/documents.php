<?php
/**
 * @var list<array<string,mixed>> $documents
 * @var array<string,string> $docTypes
 */
$pageTitle = 'Documents';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#17774f] focus:outline-none';
$statusCls = [
    'pending' => 'bg-amber-100 text-amber-800',
    'approved' => 'bg-emerald-100 text-emerald-800',
    'rejected' => 'bg-red-100 text-red-700',
    'expired' => 'bg-slate-200 text-slate-600',
];
$licenceTypes = ['fssai_license', 'ayush_license', 'medical_device_registration', 'drug_license', 'trade_license'];
ob_start();
?>
<h1 class="text-2xl font-semibold">Documents</h1>
<p class="mt-1 text-sm text-slate-500">Required: <strong>PAN card</strong> and a <strong>cancelled cheque</strong> (plus your <strong>GST certificate</strong> if you entered a GSTIN).
    Licences such as FSSAI, AYUSH or medical-device registration unlock the categories that need them.</p>

<form method="post" action="/vendor/documents" enctype="multipart/form-data" class="mt-5 rounded-2xl border border-[#ece8df] bg-white p-6"
      x-data="{ type: 'pan', licences: <?= $e(json_encode($licenceTypes)) ?> }">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm">
            <span class="text-slate-600">Document type</span>
            <select name="doc_type" x-model="type" class="<?= $input ?>">
                <?php foreach ($docTypes as $k => $label): ?>
                    <option value="<?= $e($k) ?>"><?= $e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">File</span>
            <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 w-full text-sm">
            <span class="text-xs text-slate-400">PDF, JPG, PNG or WEBP, max 5 MB.</span>
        </label>
        <label class="block text-sm" x-show="licences.includes(type) || type === 'gst_cert'">
            <span class="text-slate-600">Licence / registration number</span>
            <input name="doc_number" maxlength="80" class="<?= $input ?>">
        </label>
        <label class="block text-sm" x-show="licences.includes(type)">
            <span class="text-slate-600">Valid until</span>
            <input type="date" name="valid_until" class="<?= $input ?>">
        </label>
    </div>
    <button class="mt-5 rounded-full bg-[#0e4d34] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#17774f]">Upload</button>
</form>

<section class="mt-6 rounded-2xl border border-[#ece8df] bg-white p-6">
    <h2 class="font-semibold">Uploaded</h2>
    <?php if (!$documents): ?>
        <p class="mt-2 text-sm text-slate-500">Nothing uploaded yet.</p>
    <?php else: ?>
        <ul class="mt-3 divide-y divide-slate-100 text-sm">
            <?php foreach ($documents as $d): ?>
                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div>
                        <div class="font-medium"><?= $e($docTypes[$d['doc_type']] ?? $d['doc_type']) ?><?= !empty($d['doc_number']) ? ' · ' . $e($d['doc_number']) : '' ?></div>
                        <div class="text-xs text-slate-500">
                            <?= $e($d['original_name'] ?? '') ?> · uploaded <?= $e(substr((string) $d['created_at'], 0, 10)) ?>
                            <?= !empty($d['valid_until']) ? ' · valid until ' . $e($d['valid_until']) : '' ?>
                        </div>
                        <?php if ($d['status'] === 'rejected' && !empty($d['reject_reason'])): ?>
                            <div class="mt-1 text-xs text-red-700">Rejected: <?= $e($d['reject_reason']) ?> Please upload a new copy.</div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/vendor/documents/<?= (int) $d['id'] ?>/file" target="_blank" rel="noopener" class="text-xs text-[#17774f] hover:underline">View</a>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $statusCls[$d['status']] ?? 'bg-slate-100' ?>"><?= $e(ucfirst((string) $d['status'])) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
