<?php
/**
 * /creator/permissions — what the creator may post; apply / re-apply / add proof.
 *
 * @var array<string,mixed> $creator
 * @var array<string, array<string,mixed>> $permissions
 * @var list<array<string,mixed>> $documents purpose = permission
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pageTitle = 'What I can post';
$pill = [
    'approved' => ['Approved', 'bg-okb text-ok'],
    'applied' => ['Waiting for review', 'bg-wnb text-wn'],
    'rejected' => ['Not approved', 'bg-erb text-er'],
    'suspended' => ['Paused by our team', 'bg-erb text-er'],
];
$docsByType = [];
foreach ($documents as $d) {
    $docsByType[(string) $d['content_type']][] = $d;
}
$docSelect = static function () use ($e): string {
    $o = '';
    foreach (CS::DOC_TYPES as $k => $lbl) {
        $o .= '<option value="' . $e($k) . '"' . ($k === 'other' ? ' selected' : '') . '>' . $e($lbl) . '</option>';
    }

    return $o;
};
$input = 'mt-1 h-9 w-full rounded-[7px] border border-ln bg-sf px-3 text-[13px] outline-none focus:border-ac';
ob_start();
?>
<h1 class="text-[17px] font-semibold">What I can post</h1>
<p class="mt-1 max-w-3xl text-[12.5px] text-tx3">Our team approves each type separately. Every post is still reviewed before it goes live. Free health camps are always free to list.</p>

<div class="mt-4 grid gap-3 lg:grid-cols-2">
    <?php foreach (CS::CONTENT as $t => [$lbl, $proof]): $p = $permissions[$t] ?? null; $st = $p['status'] ?? null; ?>
        <section class="rounded-[10px] border border-ln bg-sf p-4" x-data="{ open: false }">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-[14px] font-semibold"><?= $e($lbl) ?></h2>
                <?php if (in_array($t, CS::UPCOMING_TYPES, true)): ?>
                    <span class="rounded bg-ntb px-1.5 py-px text-[10.5px] font-medium text-nt">Opens soon</span>
                <?php endif; ?>
                <span class="flex-1"></span>
                <?php if ($st): ?>
                    <span class="rounded-full px-2 py-0.5 text-[11.5px] font-medium <?= $pill[$st][1] ?>"><?= $e($pill[$st][0]) ?></span>
                <?php else: ?>
                    <span class="text-[11.5px] text-tx3">Not applied</span>
                <?php endif; ?>
            </div>
            <p class="mt-1 text-[12px] text-tx3">We usually ask for: <?= $e($proof) ?></p>
            <?php if (!empty($p['review_note']) && in_array($st, ['rejected', 'suspended', 'approved'], true)): ?>
                <p class="mt-2 rounded-[8px] bg-sf2 px-3 py-2 text-[12.5px] text-tx2"><strong>Our note:</strong> <?= $e($p['review_note']) ?></p>
            <?php endif; ?>

            <?php if (!empty($docsByType[$t])): ?>
                <ul class="mt-2 space-y-1 text-[12.5px]">
                    <?php foreach ($docsByType[$t] as $d): ?>
                        <li><a href="/creator/documents/<?= (int) $d['id'] ?>" target="_blank" rel="noopener" class="text-ac underline"><?= $e(CS::DOC_TYPES[$d['doc_type']] ?? 'Document') ?></a>
                            <span class="text-tx3">· <?= $e(date('d M Y', strtotime((string) $d['created_at']))) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($st === null || $st === 'rejected'): ?>
                <button type="button" class="mt-3 inline-flex h-8 items-center rounded-[7px] bg-ac px-3 text-[12.5px] font-semibold text-white hover:bg-act" @click="open = !open" x-show="!open"><?= $st === 'rejected' ? 'Apply again' : 'Apply' ?></button>
                <form method="post" action="/creator/permissions/apply" enctype="multipart/form-data" class="mt-3 space-y-2" x-show="open" x-cloak>
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="content_type" value="<?= $e($t) ?>">
                    <label class="block text-[12.5px] font-medium text-tx2">Tell us about your experience (optional)
                        <textarea name="applicant_note" maxlength="600" rows="2" class="<?= str_replace('h-9', '', $input) ?> py-2"></textarea></label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="block text-[12.5px] font-medium text-tx2">Proof type
                            <select name="doc_type" class="<?= $input ?>"><?= $docSelect() ?></select></label>
                        <label class="block text-[12.5px] font-medium text-tx2">Files (PDF / image, up to 3, 5 MB each)
                            <input type="file" name="proof[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp" class="mt-1 block w-full text-[12px]"></label>
                    </div>
                    <div class="flex gap-2">
                        <button class="h-8 rounded-[7px] bg-ac px-3 text-[12.5px] font-semibold text-white hover:bg-act">Send for review</button>
                        <button type="button" class="h-8 rounded-[7px] border border-ln px-3 text-[12.5px]" @click="open = false">Cancel</button>
                    </div>
                </form>
            <?php elseif ($st === 'applied'): ?>
                <form method="post" action="/creator/permissions/proof" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="content_type" value="<?= $e($t) ?>">
                    <label class="block text-[12px] font-medium text-tx2">Add proof
                        <select name="doc_type" class="<?= $input ?> !h-8 w-44"><?= $docSelect() ?></select></label>
                    <input type="file" name="proof_one" required accept="application/pdf,image/jpeg,image/png,image/webp" class="text-[12px]">
                    <button class="h-8 rounded-[7px] border border-ln bg-sf px-3 text-[12.5px] font-medium hover:bg-sf2">Upload</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
