<?php
/**
 * Company portal shell (same console design as the seller portal: dark 244px
 * sidebar, 56px top bar, slate neutrals; blue accent). Views set $pageTitle and
 * capture $content, then require this.
 *
 * @var array<string,mixed> $company
 * @var array<string,mixed> $user
 * @var string $csrf
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$status = (string) ($company['status'] ?? 'pending');
$statusPill = [
    'pending' => ['Under review', 'bg-wnb text-wn'],
    'active' => ['Active', 'bg-okb text-ok'],
    'suspended' => ['Suspended', 'bg-erb text-er'],
][$status] ?? [ucfirst($status), 'bg-ntb text-nt'];
$planStatus = \App\Services\Corporate\CorpPlanStatus::for($company);
if ($status === 'active' && $planStatus['expired']) {
    $statusPill = [$planStatus['reason'] === 'trial' ? 'Trial ended' : 'Plan ended', 'bg-erb text-er'];
}

// [href, label, available, permission|null] — items not built yet show "Soon";
// items the user's roles don't allow are hidden.
$can = static fn (?string $perm): bool => $perm === null || \App\Services\Corporate\CorpAuth::can($perm);
$navGroups = [
    'Overview' => [
        ['/company/dashboard', 'Dashboard', true],
    ],
    'Workforce' => [
        ['/company/employees', 'Employees', true, 'employees.view'],
        ['/company/screening', 'Screening', true, 'screening.view_status'],
        ['/company/bookings', 'Bookings', true, 'bookings.manage'],
        ['/company/certificates', 'Certificates', true, 'screening.view_status'],
        ['/company/incidents', 'Incidents', true, 'incidents.manage'],
        ['/company/risks', 'Risk register', true, 'risk_register.manage'],
        ['/company/reports', 'Reports', true, 'reports.view'],
    ],
    'Providers' => [
        ['/company/providers', 'Clinics', true, 'providers.manage'],
    ],
    'Structure' => [
        ['/company/branches', 'Branches', true, 'branches.manage'],
        ['/company/departments', 'Departments', true, 'departments.manage'],
        ['/company/job-roles', 'Job roles', true, 'jobroles.manage'],
    ],
    'Company' => [
        ['/company/profile', 'Company profile', true, 'company.view'],
        ['/company/users', 'Users & roles', true, 'users.view'],
        ['/company/settings/reminders', 'Reminders', true, 'company.edit'],
        ['/company/activity', 'Activity log', true, 'audit.view'],
    ],
    'Account' => [
        ['/company/security', 'Security', true],
        ['/company/billing', 'Plan & trial', true],
    ],
];
$crumbs = [];
$activeHref = null;
$matchPath = str_starts_with($path, '/company/roles') ? '/company/users' : (str_starts_with($path, '/company/screening') ? '/company/screening' : $path);
foreach ($navGroups as $group => $items) {
    $navGroups[$group] = array_values(array_filter($items, static fn ($i) => $can($i[3] ?? null)));
}
$navGroups = array_filter($navGroups);
foreach ($navGroups as $group => $items) {
    foreach ($items as [$href, $label]) {
        if (($matchPath === $href || str_starts_with($matchPath, $href . '/')) && ($activeHref === null || strlen($href) > strlen($activeHref))) {
            $activeHref = $href;
            $crumbs = [$group, $label];
        }
    }
}
$userName = (string) ($user['name'] ?? '');
$initials = strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($userName)) ?: [], 0, 2)))) ?: 'C';
$companyName = (string) (($company['trade_name'] ?? '') ?: ($company['legal_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($pageTitle ?? 'Company portal') ?> · eClinicPro Workforce Health</title>
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="min-h-screen bg-bg font-sans text-tx" x-data="{ menu: false }">
<div class="flex min-h-screen">
    <div class="fixed inset-0 z-30 bg-black/40 md:hidden" x-show="menu" x-cloak @click="menu = false"></div>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-[244px] flex-none -translate-x-full flex-col bg-nav text-[#C8D1DF] transition-transform duration-200 md:sticky md:top-0 md:h-screen md:translate-x-0"
           :class="menu ? '!translate-x-0' : ''">
        <div class="flex h-14 flex-none items-center gap-2.5 border-b border-white/5 px-[18px]">
            <div class="grid h-7 w-7 flex-none place-items-center rounded-lg bg-ac text-sm font-bold text-white">e</div>
            <div class="flex min-w-0 flex-col leading-tight">
                <span class="text-sm font-semibold tracking-tight text-white">Workforce Health</span>
                <span class="truncate text-[11px] text-[#8391A8]"><?= $e($companyName) ?></span>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-2.5 pb-4 pt-2">
            <?php foreach ($navGroups as $group => $items): ?>
                <div class="mt-3">
                    <div class="px-2.5 pb-1.5 text-[10.5px] font-semibold uppercase tracking-[.06em] text-[#6B778C]"><?= $e($group) ?></div>
                    <?php foreach ($items as $item): [$href, $label, $available] = $item; ?>
                        <?php $active = $href === $activeHref; ?>
                        <?php if (!$available): ?>
                            <span class="flex h-8 w-full cursor-default items-center gap-2.5 rounded-[7px] px-2.5 text-[13px] font-medium text-[#7B879C]" title="Coming soon">
                                <span class="h-1.5 w-1.5 flex-none rounded-[2px] bg-white/10"></span>
                                <span class="flex-1 truncate"><?= $e($label) ?></span>
                                <span class="rounded px-1.5 py-px text-[10.5px] font-medium text-[#6B778C] ring-1 ring-white/10">Soon</span>
                            </span>
                        <?php else: ?>
                            <a href="<?= $e($href) ?>" class="flex h-8 w-full items-center gap-2.5 rounded-[7px] px-2.5 text-[13px] <?= $active ? 'bg-white/[.08] font-semibold text-white' : 'font-medium text-[#C8D1DF] hover:bg-white/5' ?>">
                                <span class="h-1.5 w-1.5 flex-none rounded-[2px] <?= $active ? 'bg-ac' : 'bg-white/30' ?>"></span>
                                <span class="flex-1 truncate"><?= $e($label) ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="flex flex-none items-center gap-2.5 border-t border-white/5 px-3.5 py-3">
            <div class="grid h-[30px] w-[30px] flex-none place-items-center rounded-full bg-[#1E293B] text-[11.5px] font-semibold text-[#E2E8F0]"><?= $e($initials) ?></div>
            <div class="min-w-0 leading-tight">
                <div class="truncate text-[12.5px] font-medium text-white"><?= $e($userName) ?></div>
                <div class="truncate text-[11.5px] text-[#8391A8]"><?= $e($user['email'] ?? '') ?></div>
            </div>
            <span class="flex-1"></span>
            <form method="post" action="/company/logout">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <button class="h-[26px] flex-none whitespace-nowrap rounded-md border border-white/10 px-2 text-[11.5px] text-[#C8D1DF] hover:bg-white/5">Sign out</button>
            </form>
        </div>
    </aside>

    <main class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-14 flex-none items-center gap-2 border-b border-ln bg-sf px-3 md:gap-3.5 md:px-5">
            <button type="button" @click="menu = !menu" title="Menu" class="grid h-8 w-8 flex-none place-items-center rounded-lg border border-ln bg-sf text-tx2 hover:bg-sf2 md:hidden">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
            <div class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-[13px] max-md:flex-1">
                <?php $all = array_merge(['Company'], $crumbs ?: [$pageTitle ?? '']); ?>
                <?php foreach ($all as $i => $c): $last = $i === count($all) - 1; ?>
                    <span class="flex min-w-0 items-center gap-1.5 <?= $last ? 'truncate' : 'max-md:hidden' ?>">
                        <?php if ($i > 0): ?><span class="text-ln max-md:hidden">/</span><?php endif; ?>
                        <span class="<?= $last ? 'font-medium text-tx' : 'text-tx3' ?>"><?= $e($c) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
            <span class="flex-1 max-md:hidden"></span>
            <span class="inline-flex h-[26px] items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-xs font-medium <?= $statusPill[1] ?>">
                <span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= $e($statusPill[0]) ?>
            </span>
        </header>

        <div class="flex flex-1 flex-col gap-4 p-3 md:p-5">
            <?php if ($planStatus['state'] === 'expiring_soon' && $path !== '/company/billing'): ?>
                <div class="flex flex-wrap items-center gap-2 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-wn">
                    <strong><?= $planStatus['reason'] === 'trial' ? 'Your free trial' : 'Your plan' ?> ends <?= (int) $planStatus['days_left'] === 0 ? 'today' : 'in ' . (int) $planStatus['days_left'] . ' day' . ((int) $planStatus['days_left'] === 1 ? '' : 's') ?>.</strong>
                    <span>After that, only the plan page stays open until it's renewed.</span>
                    <a href="/company/billing" class="font-semibold underline">See plan options</a>
                </div>
            <?php endif; ?>
            <?php if ($status === 'pending'): ?>
                <div class="rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-wn">
                    <strong>Your company is under review.</strong> We check every company before it can add employees. This usually takes one working day; we'll email you when it's done.
                </div>
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
