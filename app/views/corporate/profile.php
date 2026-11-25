<?php
/**
 * /company/profile — company details + registration documents.
 *
 * @var array<string,mixed> $company
 * @var array<string,mixed>|null $country
 * @var list<array<string,mixed>> $regions
 * @var list<array<string,mixed>> $industries
 * @var array<string,string> $sizeBands
 * @var array<string,string> $businessTypes
 * @var list<array<string,mixed>> $documents
 * @var array<string,string> $docTypes
 * @var bool $canEdit
 * @var array<string,mixed>|null $old  form values after a validation error
 * @var \App\Support\Locale\Locale $loc
 */
$pageTitle = 'Company profile';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none disabled:bg-sf2 disabled:text-tx3';
$btn = 'inline-flex h-9 items-center justify-center whitespace-nowrap rounded-[7px] px-4 text-[13px] font-medium';
$v = static fn (string $k) => is_array($old ?? null) && array_key_exists($k, $old) ? $old[$k] : ($company[$k] ?? '');
$dis = $canEdit ? '' : 'disabled';
$legalLocked = ($company['status'] ?? '') !== 'pending';
$docPill = ['pending' => ['Waiting for review', 'bg-wnb text-wn'], 'approved' => ['Approved', 'bg-okb text-ok'], 'rejected' => ['Not accepted', 'bg-erb text-er']];
$phoneShown = is_array($old ?? null) ? (string) ($old['contact_phone'] ?? '') : \App\Support\Locale\Phone::display($company['contact_phone'] ?? null);
$field = static function (string $name, string $label, string $value, string $extra = '') use ($e, $input, $dis): string {
    return '<label class="block"><span class="text-tx2">' . $e($label) . '</span><input name="' . $e($name) . '" value="' . $e($value) . '" '
        . $extra . ' ' . $dis . ' class="' . $input . '"></label>';
};
ob_start();
?>
<h1 class="text-[20px] font-semibold tracking-[-.015em]">Company profile</h1>
<p class="mt-0.5 text-tx3"><?= $e($company['public_ref'] ?? '') ?> · <?= $e($country['name'] ?? '') ?> · <?= $e($company['currency_code'] ?? '') ?> · <?= $e($company['timezone'] ?? '') ?></p>

<form method="post" action="/company/profile" class="mt-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <section class="rounded-[10px] border border-ln bg-sf p-5">
        <h2 class="text-[15px] font-semibold">Company</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-tx2">Legal name</span>
                <input name="legal_name" maxlength="200" value="<?= $e($v('legal_name')) ?>" <?= $legalLocked ? 'disabled' : $dis ?> class="<?= $input ?>">
                <?php if ($legalLocked): ?><span class="text-xs text-tx3">Locked after approval. Contact support to change it.</span><?php endif; ?>
            </label>
            <?= $field('trade_name', 'Trading name (if different)', (string) $v('trade_name'), 'maxlength="200"') ?>
            <label class="block">
                <span class="text-tx2">Industry</span>
                <select name="industry_id" required <?= $dis ?> class="<?= $input ?>">
                    <?php foreach ($industries as $i): ?>
                        <option value="<?= (int) $i['id'] ?>" <?= (int) $v('industry_id') === (int) $i['id'] ? 'selected' : '' ?>><?= $e($i['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="text-tx2">Employees</span>
                    <select name="employee_size_band" required <?= $dis ?> class="<?= $input ?>">
                        <?php foreach ($sizeBands as $k => $l): ?><option value="<?= $e($k) ?>" <?= $v('employee_size_band') === $k ? 'selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="text-tx2">Business type</span>
                    <select name="business_type" <?= $dis ?> class="<?= $input ?>">
                        <option value="">Select…</option>
                        <?php foreach ($businessTypes as $k => $l): ?><option value="<?= $e($k) ?>" <?= $v('business_type') === $k ? 'selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
            <?= $field('registration_no', (string) ($country['company_id_label'] ?? 'Registration number'), (string) $v('registration_no'), 'maxlength="80"') ?>
            <?php if (!empty($country['tax_id_label'])): ?>
                <?= $field('tax_reg_no', (string) $country['tax_id_label'] . ' (if registered)', (string) $v('tax_reg_no'), 'maxlength="40"') ?>
            <?php endif; ?>
            <?= $field('licence_no', 'Licence number', (string) $v('licence_no'), 'maxlength="80"') ?>
            <?= $field('licence_expiry', 'Licence expiry', (string) $v('licence_expiry'), 'type="date"') ?>
        </div>
    </section>

    <section class="rounded-[10px] border border-ln bg-sf p-5">
        <h2 class="text-[15px] font-semibold">Registered address</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <?= $field('address_line1', 'Address line 1', (string) $v('address_line1'), 'maxlength="200"') ?>
            <?= $field('address_line2', 'Address line 2', (string) $v('address_line2'), 'maxlength="200"') ?>
            <?= $field('city', 'City', (string) $v('city'), 'maxlength="100"') ?>
            <label class="block">
                <span class="text-tx2"><?= $e($country['region_label'] ?? 'Region') ?></span>
                <select name="region_id" <?= $dis ?> class="<?= $input ?>">
                    <option value="">Select…</option>
                    <?php foreach ($regions as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $v('region_id') === (int) $r['id'] ? 'selected' : '' ?>><?= $e($r['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($country['postal_label'])): ?>
                <?= $field('postal_code', (string) $country['postal_label'], (string) $v('postal_code'), 'maxlength="20"') ?>
            <?php endif; ?>
            <label class="block">
                <span class="text-tx2">Country</span>
                <input value="<?= $e($country['name'] ?? '') ?>" disabled class="<?= $input ?>">
            </label>
        </div>
    </section>

    <section class="rounded-[10px] border border-ln bg-sf p-5">
        <h2 class="text-[15px] font-semibold">Main contact</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <?= $field('contact_name', 'Name', (string) $v('contact_name'), 'maxlength="120"') ?>
            <?= $field('contact_email', 'Email', (string) $v('contact_email'), 'type="email" maxlength="190"') ?>
            <?= $field('contact_phone', 'Phone', $phoneShown, 'type="tel" maxlength="20" placeholder="+' . $e($country['phone_code'] ?? '') . '"') ?>
            <?= $field('website', 'Website', (string) $v('website'), 'maxlength="190" placeholder="www.example.com"') ?>
        </div>
    </section>

    <?php if ($canEdit): ?>
        <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Save profile</button>
    <?php else: ?>
        <p class="text-tx3">Only people with permission to edit the company profile can change these details.</p>
    <?php endif; ?>
</form>

<section id="documents" class="mt-6 rounded-[10px] border border-ln bg-sf p-5">
    <h2 class="text-[15px] font-semibold">Registration documents</h2>
    <p class="mt-0.5 text-tx3">We check these before approving your company. Upload your trade / business licence at minimum. PDF, JPG or PNG, up to 10 MB.</p>
    <?php if ($canEdit): ?>
        <form method="post" action="/company/documents" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <label class="block">
                <span class="text-tx2">Document type</span>
                <select name="doc_type" required class="<?= $input ?>">
                    <?php foreach ($docTypes as $k => $l): ?><option value="<?= $e($k) ?>"><?= $e($l) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="text-tx2">File</span>
                <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" class="mt-1 block w-full text-[13px] file:mr-3 file:rounded-[7px] file:border file:border-ln file:bg-sf file:px-3 file:py-1.5 file:text-[13px]">
            </label>
            <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Upload</button>
        </form>
    <?php endif; ?>
    <ul class="mt-4 divide-y divide-ln2">
        <?php foreach ($documents as $d): ?>
            <?php $p = $docPill[$d['status']] ?? [$d['status'], 'bg-ntb text-nt']; ?>
            <li class="flex flex-wrap items-center gap-3 py-3">
                <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                    <a href="/company/documents/<?= (int) $d['id'] ?>" target="_blank" rel="noopener" class="font-medium text-act hover:underline"><?= $e($docTypes[$d['doc_type']] ?? $d['doc_type']) ?></a>
                    <div class="truncate text-tx3"><?= $e($d['original_name'] ?? '') ?> · uploaded <?= $e($loc->dateTime((string) $d['created_at'])) ?></div>
                    <?php if ($d['status'] === 'rejected' && !empty($d['review_note'])): ?>
                        <div class="mt-1 text-er">Reason: <?= $e($d['review_note']) ?>. Please upload a new copy.</div>
                    <?php endif; ?>
                </div>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $p[1] ?>"><?= $e($p[0]) ?></span>
                <?php if ($canEdit && $d['status'] !== 'approved'): ?>
                    <form method="post" action="/company/documents/<?= (int) $d['id'] ?>/delete" onsubmit="return confirm('Delete this document?');">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <button class="h-8 rounded-[7px] border border-ln px-3 text-[13px] font-medium text-er hover:bg-erb">Delete</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if ($documents === []): ?><li class="py-3 text-tx3">No documents yet.</li><?php endif; ?>
    </ul>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
