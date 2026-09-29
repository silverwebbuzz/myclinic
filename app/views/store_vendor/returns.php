<?php
/** @var list<array<string,mixed>> $rows @var string $tab */
$pageTitle = 'Returns';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<h1 class="text-[22px] font-semibold tracking-[-.015em]">Returns</h1>
<p class="mt-1 text-sm text-tx3">Approve or reject new return requests within 2 days. Earnings for a package are frozen while its return is open.</p>
<nav class="mt-4 flex gap-2 text-sm">
    <?php foreach (['open' => 'Open', 'all' => 'All'] as $k => $l): ?>
        <a href="?tab=<?= $k ?>" class="rounded-md px-3 py-1 <?= $tab === $k ? 'bg-acs text-act font-semibold' : 'border border-ln bg-sf text-tx2 hover:bg-sf2' ?>"><?= $l ?></a>
    <?php endforeach; ?>
</nav>
<div class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if (!$rows): ?>
        <p class="p-10 text-center text-sm text-tx3">No returns here.</p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($rows as $r): ?>
                <li><a href="/vendor/returns/<?= (int) $r['id'] ?>" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-sf2">
                    <span><span class="font-mono font-medium"><?= $e($r['return_no']) ?></span>
                        <span class="block text-xs text-tx3">Package <?= $e($r['sub_order_no']) ?> · <?= $e(\App\Services\Store\ReturnService::REASONS[$r['reason_code']] ?? $r['reason_code']) ?> · <?= $e(substr((string) $r['created_at'], 0, 10)) ?></span></span>
                    <span class="inline-flex h-[22px] items-center whitespace-nowrap rounded-md px-2 text-xs font-medium <?= $r['status'] === 'requested' ? 'bg-wnb text-wn' : 'bg-ntb text-nt' ?>"><?= $e(str_replace('_', ' ', (string) $r['status'])) ?></span>
                </a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
