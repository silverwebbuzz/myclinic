<?php
/**
 * /creator/profile — public profile (also copied to the Health Tips author page).
 *
 * @var array<string,mixed> $creator
 * @var array<string,mixed> $form
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$v = static fn (string $k): string => htmlspecialchars((string) ($form[$k] ?? ''), ENT_QUOTES, 'UTF-8');
$pageTitle = 'Public profile';
$type = (string) ($form['creator_type'] ?? $creator['creator_type']);
$lockedType = in_array($creator['verify_status'], ['pending', 'verified'], true);
$input = 'mt-1 h-9 w-full rounded-[7px] border border-ln bg-sf px-3 text-[13px] outline-none focus:border-ac';
$label = 'block text-[12.5px] font-medium text-tx2';
ob_start();
?>
<form method="post" action="/creator/profile" enctype="multipart/form-data" class="max-w-3xl space-y-4" x-data="{ type: <?= $e(json_encode($type)) ?> }">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
        <h1 class="text-[15px] font-semibold">Public profile</h1>
        <p class="mt-1 text-[12.5px] text-tx3">Shown with every post you publish.</p>

        <div class="mt-4 flex items-center gap-3">
            <?php if (!empty($creator['avatar_path'])): ?>
                <img src="/creator/avatar?v=<?= $e(substr(md5((string) $creator['avatar_path']), 0, 8)) ?>" alt="" class="h-16 w-16 rounded-full object-cover">
            <?php else: ?>
                <div class="grid h-16 w-16 place-items-center rounded-full bg-acs text-[20px] font-semibold text-act"><?= $e(mb_substr((string) $creator['display_name'], 0, 1)) ?></div>
            <?php endif; ?>
            <label class="<?= $label ?>">Photo or logo (JPG, PNG, WebP · up to 3 MB)
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="mt-1 block text-[12.5px]"></label>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="<?= $label ?> sm:col-span-2">I am
                <?php if ($lockedType): ?>
                    <input type="hidden" name="creator_type" value="<?= $e($creator['creator_type']) ?>">
                    <div class="mt-1 rounded-[7px] border border-ln bg-sf2 px-3 py-2 text-[13px] text-tx2"><?= $e(CS::TYPES[$creator['creator_type']] ?? '') ?> <span class="text-tx3">(locked while your badge is <?= $creator['verify_status'] === 'verified' ? 'verified' : 'being checked' ?>)</span></div>
                <?php else: ?>
                    <select name="creator_type" x-model="type" class="<?= $input ?>">
                        <?php foreach (CS::TYPES as $k => $lbl): ?>
                            <option value="<?= $e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= $e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </label>
            <label class="<?= $label ?> sm:col-span-2">Name readers will see
                <input name="display_name" value="<?= $v('display_name') ?>" maxlength="120" required class="<?= $input ?>">
                <span class="mt-1 block text-[11.5px] font-normal text-tx3">“Dr.” can be used only after your Verified Doctor badge is approved.</span></label>
            <label class="<?= $label ?> sm:col-span-2" x-show="['clinic','ngo','government','company'].includes(type)" x-cloak>Organisation’s registered name
                <input name="org_name" value="<?= $v('org_name') ?>" maxlength="160" class="<?= $input ?>" :required="['clinic','ngo','government','company'].includes(type)"></label>
            <label class="<?= $label ?>" x-show="['clinic','ngo','government','company'].includes(type)" x-cloak>Registration no.
                <input name="org_reg_no" value="<?= $v('org_reg_no') ?>" maxlength="80" class="<?= $input ?>"></label>
            <label class="<?= $label ?>" x-show="['clinic','ngo','government','company'].includes(type)" x-cloak>GSTIN (optional)
                <input name="gstin" value="<?= $v('gstin') ?>" maxlength="15" class="<?= $input ?> uppercase"></label>
            <label class="<?= $label ?>">Email (for approval updates)
                <input type="email" name="email" value="<?= $v('email') ?>" maxlength="160" required class="<?= $input ?>"></label>
            <label class="<?= $label ?>">One-line headline
                <input name="headline" value="<?= $v('headline') ?>" maxlength="120" class="<?= $input ?>"></label>
            <label class="<?= $label ?>">City
                <input name="city" value="<?= $v('city') ?>" maxlength="80" class="<?= $input ?>"></label>
            <label class="<?= $label ?>">State
                <input name="state" value="<?= $v('state') ?>" maxlength="80" class="<?= $input ?>"></label>
            <label class="<?= $label ?> sm:col-span-2">About you
                <textarea name="bio" maxlength="600" rows="4" class="<?= str_replace('h-9', '', $input) ?> py-2"><?= $v('bio') ?></textarea></label>
            <label class="<?= $label ?>">Instagram handle
                <input name="instagram_handle" value="<?= $v('instagram_handle') ?>" maxlength="60" class="<?= $input ?>"></label>
            <label class="<?= $label ?>">YouTube channel link
                <input name="youtube_url" value="<?= $v('youtube_url') ?>" maxlength="255" class="<?= $input ?>"></label>
            <label class="<?= $label ?> sm:col-span-2">Website
                <input name="website_url" value="<?= $v('website_url') ?>" maxlength="255" class="<?= $input ?>"></label>
        </div>
    </section>
    <button class="h-9 rounded-[8px] bg-ac px-4 text-[13px] font-semibold text-white hover:bg-act">Save profile</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
