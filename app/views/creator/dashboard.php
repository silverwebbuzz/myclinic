<?php
/**
 * /creator/dashboard
 *
 * @var array<string,mixed> $creator
 * @var array<string, array<string,mixed>> $permissions
 * @var array<string,int> $tipCounts
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pageTitle = 'Dashboard';
$kind = CS::badgeKind($creator);
$vs = (string) $creator['verify_status'];
$approved = array_keys(array_filter($permissions, static fn ($p) => $p['status'] === 'approved'));
$waiting = array_keys(array_filter($permissions, static fn ($p) => $p['status'] === 'applied'));
$pill = [
    'approved' => ['Approved', 'bg-okb text-ok'],
    'applied' => ['Waiting for review', 'bg-wnb text-wn'],
    'rejected' => ['Not approved', 'bg-erb text-er'],
    'suspended' => ['Paused', 'bg-erb text-er'],
];
$card = 'rounded-[10px] border border-ln bg-sf p-4 md:p-5';
ob_start();
?>
<div class="grid gap-4 lg:grid-cols-3">
    <section class="<?= $card ?> lg:col-span-2">
        <div class="flex items-start gap-3">
            <?php if (!empty($creator['avatar_path'])): ?>
                <img src="/creator/avatar" alt="" class="h-12 w-12 flex-none rounded-full object-cover">
            <?php else: ?>
                <div class="grid h-12 w-12 flex-none place-items-center rounded-full bg-acs text-[16px] font-semibold text-act"><?= $e(mb_substr((string) $creator['display_name'], 0, 1)) ?></div>
            <?php endif; ?>
            <div class="min-w-0">
                <h1 class="text-[17px] font-semibold">Hi, <?= $e($creator['display_name']) ?></h1>
                <p class="text-[12.5px] text-tx3"><?= $e(CS::TYPE_SHORT[$creator['creator_type']] ?? '') ?><?= $creator['city'] ? ' · ' . $e($creator['city']) : '' ?></p>
            </div>
        </div>
        <?php if (!$approved && $waiting): ?>
            <p class="mt-4 rounded-[8px] bg-wnb px-3 py-2.5 text-[13px] text-wn">Your application is with our team. We’ll email you at <strong><?= $e($creator['email'] ?? '') ?></strong> when it’s decided. Adding proof on <a href="/creator/permissions" class="font-semibold underline">What I can post</a> helps us approve you faster.</p>
        <?php elseif (!$approved): ?>
            <p class="mt-4 rounded-[8px] bg-ntb px-3 py-2.5 text-[13px] text-nt">You’re not approved to post anything yet. <a href="/creator/permissions" class="font-semibold underline">Apply for what you want to post</a>.</p>
        <?php endif; ?>

        <?php $tipReady = array_values(array_intersect(CS::TIP_TYPES, $approved)); ?>
        <?php if ($tipReady || array_intersect(['free_camp', 'event'], $approved)): ?>
            <h2 class="mt-5 text-[13px] font-semibold">Create</h2>
        <?php endif; ?>
        <?php if ($tipReady): ?>
            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                <?php foreach ($tipReady as $t): ?>
                    <a href="<?= $e(CS::siteUrl('/my-health-tips?new=' . $t)) ?>" class="rounded-[8px] border border-ln p-3 hover:border-ac hover:bg-acs">
                        <span class="block text-[13px] font-semibold">+ New <?= $e(['recipe' => 'recipe', 'exercise' => 'exercise', 'tip' => 'quick tip', 'video' => 'video'][$t] ?? $t) ?></span>
                        <span class="block text-[11.5px] text-tx3">Opens the editor</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php $evReady = array_values(array_intersect(['free_camp', 'event'], $approved)); ?>
        <?php if ($evReady): ?>
            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                <?php foreach ($evReady as $t): ?>
                    <a href="/creator/events/new?kind=<?= $e($t) ?>" class="rounded-[8px] border border-ln p-3 hover:border-ac hover:bg-acs">
                        <span class="block text-[13px] font-semibold">+ <?= $t === 'free_camp' ? 'New free camp' : 'New event' ?></span>
                        <span class="block text-[11.5px] text-tx3"><?= $t === 'free_camp' ? 'Always free to list' : 'Expo, workshop, webinar…' ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php $soon = array_values(array_intersect(CS::UPCOMING_TYPES, $approved)); ?>
        <?php if ($soon): ?>
            <p class="mt-3 text-[12.5px] text-tx3">You’re approved for <?= $e(implode(', ', array_map(static fn ($t) => strtolower(CS::CONTENT[$t][0]), $soon))) ?>. Posting these opens soon — we’ll email you.</p>
        <?php endif; ?>
    </section>

    <section class="<?= $card ?>">
        <h2 class="text-[13px] font-semibold">Verified badge</h2>
        <?php if ($kind === null): ?>
            <p class="mt-2 text-[12.5px] text-tx3">Badges are for doctors and organisations. If you are one, change your type in <a href="/creator/profile" class="underline">Public profile</a>.</p>
        <?php elseif ($vs === 'verified'): ?>
            <p class="mt-2 text-[13px] text-in">✓ <?= $e(CS::badgeLabel($creator)) ?></p>
        <?php elseif ($vs === 'pending'): ?>
            <p class="mt-2 text-[12.5px] text-wn">Your documents are being checked.</p>
        <?php else: ?>
            <p class="mt-2 text-[12.5px] text-tx3"><?= $vs === 'rejected' || $vs === 'revoked' ? 'Your last request was not approved. ' : '' ?>Get the <strong><?= $kind === 'doctor' ? 'Verified Doctor' : 'Verified Organisation' ?></strong> badge so people can trust your posts.</p>
            <a href="/creator/verification" class="mt-3 inline-flex h-8 items-center rounded-[7px] bg-ac px-3 text-[12.5px] font-semibold text-white hover:bg-act">Apply for the badge</a>
        <?php endif; ?>
    </section>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <section class="<?= $card ?>">
        <div class="flex items-center justify-between">
            <h2 class="text-[13px] font-semibold">What I can post</h2>
            <a href="/creator/permissions" class="text-[12.5px] text-ac underline">Manage</a>
        </div>
        <ul class="mt-2 divide-y divide-ln2">
            <?php foreach (CS::CONTENT as $t => [$lbl]): $p = $permissions[$t] ?? null; ?>
                <li class="flex items-center justify-between gap-2 py-2 text-[13px]">
                    <span><?= $e($lbl) ?></span>
                    <?php if ($p): ?>
                        <span class="rounded-full px-2 py-0.5 text-[11.5px] font-medium <?= $pill[$p['status']][1] ?>"><?= $e($pill[$p['status']][0]) ?></span>
                    <?php else: ?>
                        <span class="text-[11.5px] text-tx3">Not applied</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="<?= $card ?>">
        <div class="flex items-center justify-between">
            <h2 class="text-[13px] font-semibold">Recipes, tips &amp; videos</h2>
            <a href="/creator/content" class="text-[12.5px] text-ac underline">See all</a>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <?php foreach (['published' => 'Live', 'submitted' => 'In review', 'changes_requested' => 'Changes needed', 'draft' => 'Drafts'] as $k => $lbl): ?>
                <div class="rounded-[8px] bg-sf2 p-3">
                    <div class="text-[18px] font-semibold"><?= (int) ($tipCounts[$k] ?? 0) ?></div>
                    <div class="text-[11.5px] text-tx3"><?= $e($lbl) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
