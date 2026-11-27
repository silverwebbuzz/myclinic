<?php
/**
 * Creator Studio shell — same console design as the seller portal (dark 244px
 * sidebar, 56px top bar). Views set $pageTitle and capture $content, then require this.
 *
 * @var array<string,mixed>|null $creator
 * @var string $csrf
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$suspended = ($creator['status'] ?? '') === 'suspended';
$badge = $creator ? CS::badgeLabel($creator) : null;
$patientUrl = CS::siteUrl('/patient');

$navGroups = [
    'Overview' => [['/creator/dashboard', 'Dashboard']],
    'Create' => [
        ['/creator/events', 'Camps & events'],
        ['/creator/content', 'Recipes, tips & videos'],
        ['/creator/questions', 'Questions & comments'],
    ],
    'Account' => [
        ['/creator/profile', 'Public profile'],
        ['/creator/permissions', 'What I can post'],
        ['/creator/verification', 'Verified badge'],
        ['/creator/credits', 'Credits & payments'],
    ],
];
$unansweredQ = $creator ? \App\Services\Wellness\Comments::unansweredCount((int) $creator['id']) : 0;
$crumb = $pageTitle ?? '';
$activeHref = null;
foreach ($navGroups as $items) {
    foreach ($items as [$href, $label]) {
        if ($path === $href || str_starts_with($path, $href . '/')) {
            $activeHref = $href;
            $crumb = $label;
        }
    }
}
$userName = (string) ($creator['display_name'] ?? $identity['name'] ?? '');
$initials = strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($userName)) ?: [], 0, 2)))) ?: 'C';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($pageTitle ?? 'Creator Studio') ?> · eClinicPro Wellness Hub</title>
    <?php require dirname(__DIR__) . '/store_vendor/_pg_head.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-bg font-sans text-tx" x-data="{ menu: false }">
<div class="flex min-h-screen">
    <div class="fixed inset-0 z-30 bg-black/40 md:hidden" x-show="menu" x-cloak @click="menu = false"></div>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-[244px] flex-none -translate-x-full flex-col bg-nav text-[#C8D1DF] transition-transform duration-200 md:sticky md:top-0 md:h-screen md:translate-x-0"
           :class="menu ? '!translate-x-0' : ''">
        <div class="flex h-14 flex-none items-center gap-2.5 border-b border-white/5 px-[18px]">
            <div class="grid h-7 w-7 flex-none place-items-center rounded-lg bg-ac text-sm font-bold text-white">e</div>
            <div class="flex min-w-0 flex-col leading-tight">
                <span class="text-sm font-semibold tracking-tight text-white">Wellness Hub</span>
                <span class="whitespace-nowrap text-[11px] text-[#8391A8]">Creator Studio</span>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-2.5 pb-4 pt-2">
            <?php foreach ($navGroups as $group => $items): ?>
                <div class="mt-3">
                    <div class="px-2.5 pb-1.5 text-[10.5px] font-semibold uppercase tracking-[.06em] text-[#6B778C]"><?= $e($group) ?></div>
                    <?php foreach ($items as [$href, $label]): $active = $href === $activeHref; ?>
                        <a href="<?= $e($href) ?>" class="flex h-8 w-full items-center gap-2.5 rounded-[7px] px-2.5 text-[13px] <?= $active ? 'bg-white/[.08] font-semibold text-white' : 'font-medium text-[#C8D1DF] hover:bg-white/5' ?>">
                            <span class="h-1.5 w-1.5 flex-none rounded-[2px] <?= $active ? 'bg-ac' : 'bg-white/30' ?>"></span>
                            <span class="flex-1 truncate"><?= $e($label) ?></span>
                            <?php if ($href === '/creator/questions' && $unansweredQ > 0): ?><span class="grid h-[18px] min-w-[18px] place-items-center rounded-full bg-ac px-1.5 text-[10.5px] font-semibold text-white"><?= (int) $unansweredQ ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="mt-5 px-2.5">
                <a href="<?= $e($patientUrl) ?>" class="flex h-9 w-full items-center justify-center gap-1.5 rounded-[7px] border border-white/10 text-[12.5px] font-medium text-white hover:bg-white/5">← My Health (patient panel)</a>
            </div>
        </nav>

        <div class="flex flex-none items-center gap-2.5 border-t border-white/5 px-3.5 py-3">
            <div class="grid h-[30px] w-[30px] flex-none place-items-center rounded-full bg-[#1E293B] text-[11.5px] font-semibold text-[#E2E8F0]"><?= $e($initials) ?></div>
            <div class="min-w-0 leading-tight">
                <div class="truncate text-[12.5px] font-medium text-white"><?= $e($userName) ?></div>
                <div class="truncate text-[11.5px] text-[#8391A8]"><?= $e($creator ? (CS::TYPE_SHORT[$creator['creator_type']] ?? '') : '') ?></div>
            </div>
            <span class="flex-1"></span>
            <form method="post" action="/creator/logout">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <button class="h-[26px] flex-none rounded-md border border-white/10 px-2 text-[11.5px] text-[#C8D1DF] hover:bg-white/5">Sign out</button>
            </form>
        </div>
    </aside>

    <main class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-14 flex-none items-center gap-2 border-b border-ln bg-sf px-3 md:gap-3.5 md:px-5">
            <button type="button" @click="menu = !menu" title="Menu" class="grid h-8 w-8 flex-none place-items-center rounded-lg border border-ln bg-sf text-tx2 hover:bg-sf2 md:hidden">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
            <div class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-[13px] max-md:flex-1">
                <span class="text-tx3 max-md:hidden">Creator Studio</span>
                <span class="text-ln max-md:hidden">/</span>
                <span class="truncate font-medium text-tx"><?= $e($crumb) ?></span>
            </div>
            <span class="flex-1 max-md:hidden"></span>
            <a href="<?= $e($patientUrl) ?>" class="inline-flex h-8 items-center whitespace-nowrap rounded-[7px] border border-ln bg-sf px-3 text-[13px] font-medium text-tx hover:bg-sf2 max-md:hidden">← My Health</a>
            <?php if ($badge): ?>
                <span class="inline-flex h-[26px] items-center gap-1.5 whitespace-nowrap rounded-full bg-inb px-2.5 text-xs font-medium text-in">✓ <?= $e($badge) ?></span>
            <?php endif; ?>
            <span class="inline-flex h-[26px] items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-xs font-medium <?= $suspended ? 'bg-erb text-er' : 'bg-okb text-ok' ?>">
                <span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= $suspended ? 'Paused' : 'Active' ?>
            </span>
        </header>

        <div class="flex flex-1 flex-col gap-4 p-3 md:p-5">
            <?php if ($suspended): ?>
                <div class="rounded-[10px] border border-er/20 bg-erb px-4 py-3 text-er"><strong>Your creator account is paused.</strong> <?= $e($creator['status_reason'] ?? '') ?> Your posts are hidden from the public and changes can’t be saved. Please contact us at wecare@eclinicpro.com.</div>
            <?php endif; ?>
            <?php if (!empty($flashOk)): ?>
                <div class="rounded-[10px] border border-ok/20 bg-okb px-4 py-3 text-ok"><?= $e($flashOk) ?></div>
            <?php endif; ?>
            <?php if (!empty($flashErr)): ?>
                <div class="rounded-[10px] border border-er/20 bg-erb px-4 py-3 text-er"><?= $e($flashErr) ?></div>
            <?php endif; ?>
            <div class="min-w-0"><?= $content ?? '' ?></div>
        </div>
    </main>
</div>
</body>
</html>
