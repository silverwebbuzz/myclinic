<?php
/**
 * /creator/join — become a Health Creator. The person is already signed in
 * with their patient account; this adds the creator profile + first
 * permission applications.
 *
 * @var array<string,mixed> $form
 * @var array<string,mixed> $identity
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$v = static fn (string $k): string => htmlspecialchars((string) ($form[$k] ?? ''), ENT_QUOTES, 'UTF-8');
$type = (string) ($form['creator_type'] ?? '');
$want = (array) ($form['content_types'] ?? []);
$input = 'mt-1 h-9 w-full rounded-[7px] border border-ln bg-sf px-3 text-[13px] outline-none focus:border-ac';
$label = 'block text-[12.5px] font-medium text-tx2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Become a Health Creator · eClinicPro Wellness Hub</title>
    <?php require dirname(__DIR__) . '/store_vendor/_pg_head.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-bg font-sans text-tx">
<div class="mx-auto max-w-3xl px-3 py-6 md:px-4 md:py-10">
    <div class="mb-5 flex flex-wrap items-center gap-2.5">
        <div class="grid h-9 w-9 place-items-center rounded-[10px] bg-ac text-base font-bold text-white">e</div>
        <div class="leading-tight">
            <div class="text-[17px] font-semibold">Become a Health Creator</div>
            <div class="text-[12.5px] text-tx3">Signed in as <?= $e($identity['name'] ?? '') ?> · <?= $e($identity['phone'] ?? '') ?></div>
        </div>
        <span class="flex-1"></span>
        <a href="<?= $e(CS::siteUrl('/patient')) ?>" class="text-[12.5px] text-tx3 underline">← Back to My Health</a>
        <form method="post" action="/creator/logout"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-[12.5px] text-tx3 underline">Sign out</button></form>
    </div>

    <?php if (!empty($flashErr)): ?>
        <div class="mb-4 rounded-[10px] border border-er/20 bg-erb px-4 py-3 text-er"><?= $e($flashErr) ?></div>
    <?php endif; ?>

    <form method="post" action="/creator/join" class="space-y-4" x-data="{ type: <?= $e(json_encode($type)) ?> }">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

        <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
            <h2 class="text-[14px] font-semibold">1. Who are you?</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <?php foreach (CS::TYPES as $k => $lbl): ?>
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-[8px] border border-ln p-3 hover:bg-sf2" :class="type === '<?= $k ?>' ? '!border-ac bg-acs' : ''">
                        <input type="radio" name="creator_type" value="<?= $e($k) ?>" x-model="type" class="mt-0.5 accent-[#059669]" required <?= $type === $k ? 'checked' : '' ?>>
                        <span class="text-[13px]"><?= $e($lbl) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 text-[12px] text-tx3" x-show="type === 'doctor'" x-cloak>Doctors can apply for a <strong>Verified Doctor</strong> badge after joining. Until it’s approved, please don’t add “Dr.” to your name.</p>
            <p class="mt-2 text-[12px] text-tx3" x-show="['clinic','ngo','government','company'].includes(type)" x-cloak>Organisations can apply for a <strong>Verified Organisation</strong> badge after joining. Sign up with the coordinator’s own mobile number.</p>
        </section>

        <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
            <h2 class="text-[14px] font-semibold">2. Your public profile</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="<?= $label ?> sm:col-span-2">Name readers will see
                    <input name="display_name" value="<?= $v('display_name') ?>" maxlength="120" required class="<?= $input ?>" placeholder="e.g. Pooja Mehta or Sunrise Hospital"></label>
                <label class="<?= $label ?> sm:col-span-2" x-show="['clinic','ngo','government','company'].includes(type)" x-cloak>Organisation’s registered name
                    <input name="org_name" value="<?= $v('org_name') ?>" maxlength="160" class="<?= $input ?>" :required="['clinic','ngo','government','company'].includes(type)"></label>
                <label class="<?= $label ?>" x-show="['clinic','ngo','company'].includes(type)" x-cloak>Registration no. (optional now)
                    <input name="org_reg_no" value="<?= $v('org_reg_no') ?>" maxlength="80" class="<?= $input ?>"></label>
                <label class="<?= $label ?>" x-show="['clinic','ngo','company'].includes(type)" x-cloak>GSTIN (optional)
                    <input name="gstin" value="<?= $v('gstin') ?>" maxlength="15" class="<?= $input ?> uppercase" placeholder="24ABCDE1234F1Z5"></label>
                <label class="<?= $label ?>">Email
                    <input type="email" name="email" value="<?= $v('email') ?>" maxlength="160" required class="<?= $input ?>"></label>
                <label class="<?= $label ?>">One-line headline
                    <input name="headline" value="<?= $v('headline') ?>" maxlength="120" class="<?= $input ?>" placeholder="Protein-rich vegetarian recipes"></label>
                <label class="<?= $label ?>">City
                    <input name="city" value="<?= $v('city') ?>" maxlength="80" class="<?= $input ?>"></label>
                <label class="<?= $label ?>">State
                    <input name="state" value="<?= $v('state') ?>" maxlength="80" class="<?= $input ?>"></label>
                <label class="<?= $label ?> sm:col-span-2">About you (optional)
                    <textarea name="bio" maxlength="600" rows="3" class="<?= str_replace('h-9', '', $input) ?> py-2"><?= $v('bio') ?></textarea></label>
                <label class="<?= $label ?>">Instagram handle
                    <input name="instagram_handle" value="<?= $v('instagram_handle') ?>" maxlength="60" class="<?= $input ?>" placeholder="yourhandle"></label>
                <label class="<?= $label ?>">YouTube channel link
                    <input name="youtube_url" value="<?= $v('youtube_url') ?>" maxlength="255" class="<?= $input ?>" placeholder="https://youtube.com/@…"></label>
                <label class="<?= $label ?> sm:col-span-2">Website
                    <input name="website_url" value="<?= $v('website_url') ?>" maxlength="255" class="<?= $input ?>" placeholder="https://"></label>
            </div>
        </section>

        <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
            <h2 class="text-[14px] font-semibold">3. What do you want to post?</h2>
            <p class="mt-1 text-[12.5px] text-tx3">Our team approves each one separately. You can apply for more later.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <?php foreach (CS::CONTENT as $k => [$lbl, $proof]): ?>
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-[8px] border border-ln p-3 hover:bg-sf2">
                        <input type="checkbox" name="content_types[]" value="<?= $e($k) ?>" class="mt-0.5 accent-[#059669]" <?= in_array($k, $want, true) ? 'checked' : '' ?>>
                        <span class="min-w-0">
                            <span class="block text-[13px] font-medium"><?= $e($lbl) ?><?= in_array($k, CS::UPCOMING_TYPES, true) ? ' <span class="ml-1 rounded bg-ntb px-1.5 py-px text-[10.5px] font-medium text-nt">Opens soon</span>' : '' ?></span>
                            <span class="block text-[11.5px] text-tx3">We usually ask for: <?= $e($proof) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <label class="<?= $label ?> mt-3">Anything that helps us approve you? (optional)
                <textarea name="applicant_note" maxlength="600" rows="2" class="<?= str_replace('h-9', '', $input) ?> py-2" placeholder="Your experience, qualifications, links to your work…"><?= $v('applicant_note') ?></textarea></label>
        </section>

        <section class="rounded-[10px] border border-ln bg-sf p-4 md:p-5">
            <h2 class="text-[14px] font-semibold">4. Terms</h2>
            <div class="mt-3 max-h-56 overflow-y-auto rounded-[8px] border border-ln bg-sf2 p-3"><?php require __DIR__ . '/_terms.php'; ?></div>
            <label class="mt-3 flex items-start gap-2.5 text-[13px]">
                <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5 accent-[#059669]">
                <span>I have read and accept the Health Creator terms<span class="font-medium text-tx"> — including the no medical claims rule.</span></span>
            </label>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button class="h-10 rounded-[8px] bg-ac px-5 text-[14px] font-semibold text-white hover:bg-act">Send my application</button>
            <span class="text-[12px] text-tx3">You can upload proof documents on the next page.</span>
        </div>
    </form>
</div>
</body>
</html>
