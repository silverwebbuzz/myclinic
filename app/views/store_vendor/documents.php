<?php
/**
 * @var list<array<string,mixed>> $documents
 * @var array<string,string> $docTypes
 */
$pageTitle = 'Documents';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 text-sm focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$statusCls = [
    'pending' => 'bg-wnb text-wn',
    'approved' => 'bg-okb text-ok',
    'rejected' => 'bg-erb text-er',
    'expired' => 'bg-slate-200 text-tx2',
];
$licenceTypes = ['fssai_license', 'ayush_license', 'medical_device_registration', 'drug_license', 'trade_license'];
ob_start();
?>
<h1 class="text-[22px] font-semibold tracking-[-.015em]">Documents</h1>
<p class="mt-1 text-sm text-tx3">Required: <strong>PAN card</strong> and a <strong>cancelled cheque</strong> (plus your <strong>GST certificate</strong> if you entered a GSTIN).
    Licences such as FSSAI, AYUSH or medical-device registration unlock the categories that need them.</p>

<form method="post" action="/vendor/documents" enctype="multipart/form-data" class="mt-5 rounded-[10px] border border-ln bg-sf p-6"
      x-data="{ type: 'pan', licences: <?= $e(json_encode($licenceTypes)) ?> }">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm">
            <span class="text-tx2">Document type</span>
            <select name="doc_type" x-model="type" class="<?= $input ?>">
                <?php foreach ($docTypes as $k => $label): ?>
                    <option value="<?= $e($k) ?>"><?= $e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-sm">
            <span class="text-tx2">File</span>
            <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 w-full text-sm">
            <span class="text-xs text-tx3">PDF, JPG, PNG or WEBP, max 5 MB.</span>
        </label>
        <label class="block text-sm" x-show="licences.includes(type) || type === 'gst_cert'">
            <span class="text-tx2">Licence / registration number</span>
            <input name="doc_number" maxlength="80" class="<?= $input ?>">
        </label>
        <label class="block text-sm" x-show="licences.includes(type)">
            <span class="text-tx2">Valid until</span>
            <input type="date" name="valid_until" class="<?= $input ?>">
        </label>
    </div>
    <button class="mt-5 rounded-[7px] bg-ac px-6 py-2.5 text-sm font-medium text-white hover:opacity-90">Upload</button>
</form>

<section class="mt-6 rounded-[10px] border border-ln bg-sf p-6">
    <h2 class="font-semibold">Uploaded</h2>
    <?php if (!$documents): ?>
        <p class="mt-2 text-sm text-tx3">Nothing uploaded yet.</p>
    <?php else: ?>
        <ul class="mt-3 divide-y divide-ln2 text-sm">
            <?php foreach ($documents as $d): ?>
                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div>
                        <div class="font-medium"><?= $e($docTypes[$d['doc_type']] ?? $d['doc_type']) ?><?= !empty($d['doc_number']) ? ' · ' . $e($d['doc_number']) : '' ?></div>
                        <div class="text-xs text-tx3">
                            <?= $e($d['original_name'] ?? '') ?> · uploaded <?= $e(\App\Support\IndianDate::date($d['created_at'])) ?>
                            <?= !empty($d['valid_until']) ? ' · valid until ' . $e($d['valid_until']) : '' ?>
                        </div>
                        <?php if ($d['status'] === 'rejected' && !empty($d['reject_reason'])): ?>
                            <div class="mt-1 text-xs text-red-700">Rejected: <?= $e($d['reject_reason']) ?> Please upload a new copy.</div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/vendor/documents/<?= (int) $d['id'] ?>/file" target="_blank" rel="noopener" class="text-xs text-act hover:underline">View</a>
                        <span class="inline-flex h-[22px] items-center whitespace-nowrap rounded-md px-2 text-xs font-medium <?= $statusCls[$d['status']] ?? 'bg-sf2' ?>"><?= $e(ucfirst((string) $d['status'])) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
