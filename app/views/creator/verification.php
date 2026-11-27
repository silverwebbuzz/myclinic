<?php
/**
 * /creator/verification — Verified Doctor / Verified Organisation badge (plan §14).
 *
 * @var array<string,mixed> $creator
 * @var string|null $kind doctor|organisation|null
 * @var list<array<string,mixed>> $documents purpose = verification
 * @var array<string,mixed> $form
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$v = static fn (string $k): string => htmlspecialchars((string) ($form[$k] ?? ''), ENT_QUOTES, 'UTF-8');
$pageTitle = 'Verified badge';
$vs = (string) $creator['verify_status'];
$isGovt = ($creator['creator_type'] ?? '') === 'government';
$docRows = $kind === 'doctor'
    ? ['registration_certificate' => true, 'degree_certificate' => true, 'photo_id' => true]
    : ($isGovt ? ['photo_id' => true, 'registration_certificate' => false] : ['registration_certificate' => true, 'photo_id' => true, 'gst_certificate' => false]);
$onFile = [];
foreach ($documents as $d) {
    if ($d['status'] !== 'rejected') {
        $onFile[(string) $d['doc_type']] = true;
    }
}
$input = 'mt-1 h-9 w-full rounded-[7px] border border-ln bg-sf px-3 text-[13px] outline-none focus:border-ac';
$label = 'block text-[12.5px] font-medium text-tx2';
ob_start();
?>
<div class="max-w-3xl space-y-4">
    <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
        <h1 class="text-[15px] font-semibold"><?= $kind === 'organisation' ? 'Verified Organisation badge' : 'Verified Doctor badge' ?></h1>
        <?php if ($kind === null): ?>
            <p class="mt-2 text-[13px] text-tx2">Badges are for doctors and organisations (clinics, hospitals, NGOs, government bodies, companies). If you are one, change “I am” in your <a href="/creator/profile" class="text-ac underline">Public profile</a> first.</p>
        <?php else: ?>
            <p class="mt-1 text-[12.5px] text-tx3">
                <?= $kind === 'doctor'
                    ? 'We check your registration on the NMC Indian Medical Register (or your state, dental or AYUSH register) and match your name and photo. Then “✓ Verified Doctor” shows on your profile, posts and answers, and you may use “Dr.” in your name.'
                    : 'We check your organisation’s registration. Then “✓ Verified Organisation” shows on your profile and listings.' ?>
                Your documents stay private — only our verification team sees them.
            </p>

            <?php if ($vs === 'verified'): ?>
                <p class="mt-3 rounded-[8px] bg-inb px-3 py-2.5 text-[13px] text-in">✓ You are verified<?= $creator['verified_at'] ? ' since ' . $e(date('d M Y', strtotime((string) $creator['verified_at']))) : '' ?>.</p>
            <?php elseif ($vs === 'pending'): ?>
                <p class="mt-3 rounded-[8px] bg-wnb px-3 py-2.5 text-[13px] text-wn">Your documents are being checked — usually 2–3 working days. We’ll email you.</p>
            <?php elseif (in_array($vs, ['rejected', 'revoked'], true)): ?>
                <p class="mt-3 rounded-[8px] bg-erb px-3 py-2.5 text-[13px] text-er"><strong><?= $vs === 'revoked' ? 'Your badge was removed.' : 'We could not verify you.' ?></strong> <?= $e($creator['verify_note'] ?? '') ?> You can correct the details and apply again.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($kind !== null && !in_array($vs, ['pending', 'verified'], true)): ?>
        <form method="post" action="/creator/verification" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
                <h2 class="text-[14px] font-semibold">Registration details</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <?php if ($kind === 'doctor'): ?>
                        <label class="<?= $label ?> sm:col-span-2">Council you are registered with
                            <select name="med_council" required class="<?= $input ?>">
                                <option value="">Choose…</option>
                                <?php foreach (CS::COUNCILS as $cn): ?>
                                    <option value="<?= $e($cn) ?>" <?= ($form['med_council'] ?? '') === $cn ? 'selected' : '' ?>><?= $e($cn) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <label class="<?= $label ?>">Registration number
                            <input name="med_reg_no" value="<?= $v('med_reg_no') ?>" maxlength="60" required class="<?= $input ?>"></label>
                        <label class="<?= $label ?>">Qualification
                            <input name="med_qualification" value="<?= $v('med_qualification') ?>" maxlength="120" required class="<?= $input ?>" placeholder="MBBS, MD (Medicine)"></label>
                        <label class="<?= $label ?> sm:col-span-2">Specialty (optional)
                            <input name="specialty" value="<?= $v('specialty') ?>" maxlength="120" class="<?= $input ?>" placeholder="Diabetology"></label>
                    <?php else: ?>
                        <label class="<?= $label ?> sm:col-span-2">Organisation
                            <div class="mt-1 rounded-[7px] border border-ln bg-sf2 px-3 py-2 text-[13px] text-tx2"><?= $e($creator['org_name'] ?: '— add it in Public profile') ?></div></label>
                        <label class="<?= $label ?> sm:col-span-2">Registration number<?= $isGovt ? ' (if any)' : '' ?>
                            <input name="org_reg_no" value="<?= $v('org_reg_no') ?>" maxlength="80" <?= $isGovt ? '' : 'required' ?> class="<?= $input ?>"
                                   placeholder="<?= ($creator['creator_type'] ?? '') === 'clinic' ? 'Clinical establishment / hospital registration' : (($creator['creator_type'] ?? '') === 'ngo' ? 'Trust / society / Section 8 registration' : 'CIN / LLPIN / Udyam') ?>"></label>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
                <h2 class="text-[14px] font-semibold">Documents</h2>
                <p class="mt-1 text-[12px] text-tx3">PDF or photo, up to 5 MB each.<?= $isGovt ? ' For government bodies: the officer’s ID; an authorisation letter on letterhead helps.' : '' ?></p>
                <div class="mt-3 space-y-3">
                    <?php foreach ($docRows as $dt => $required): ?>
                        <label class="<?= $label ?>">
                            <?= $e(CS::DOC_TYPES[$dt]) ?><?= $required ? '' : ' (optional)' ?>
                            <?php if (isset($onFile[$dt])): ?><span class="ml-1 font-normal text-ok">✓ on file — upload again only to replace</span><?php endif; ?>
                            <input type="file" name="doc[<?= $e($dt) ?>]" accept="application/pdf,image/jpeg,image/png,image/webp"
                                   <?= $required && !isset($onFile[$dt]) ? 'required' : '' ?> class="mt-1 block w-full text-[12.5px]">
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
            <button class="h-9 rounded-[8px] bg-ac px-4 text-[13px] font-semibold text-white hover:bg-act">Send for verification</button>
        </form>
    <?php endif; ?>

    <?php if ($documents): ?>
        <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
            <h2 class="text-[13px] font-semibold">Documents you sent</h2>
            <ul class="mt-2 space-y-1 text-[12.5px]">
                <?php foreach ($documents as $d): ?>
                    <li><a href="/creator/documents/<?= (int) $d['id'] ?>" target="_blank" rel="noopener" class="text-ac underline"><?= $e(CS::DOC_TYPES[$d['doc_type']] ?? 'Document') ?></a>
                        <span class="text-tx3">· <?= $e(date('d M Y', strtotime((string) $d['created_at']))) ?> · <?= $e(['pending' => 'being checked', 'accepted' => 'accepted', 'rejected' => 'not accepted'][$d['status']] ?? $d['status']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
