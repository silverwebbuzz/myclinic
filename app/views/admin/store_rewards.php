<?php
/**
 * /admin/store/rewards — eClinicPro Points (StoreRewardsAdminController).
 *
 * @var string $tab overview | referrals | customer
 * @var array<string, mixed> $stats
 * @var array<string, string> $settings
 * @var array<string, array{0: string, 1: int, 2: int}> $intSettings
 * @var list<array<string, mixed>> $referrals
 * @var array<string, mixed>|null $customer
 * @var string $q
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$in = 'mt-1 w-full rounded border px-2 py-1.5 text-sm';
$n = static fn ($v): string => number_format((int) $v);
$d = static fn ($v): string => $v ? date('d M Y', strtotime((string) $v)) : '—';
$refStatus = ['signed_up' => 'Waiting for first delivered order', 'qualified' => 'Delivered · in return window', 'rewarded' => 'Points given', 'rejected' => 'Rejected'];
$tabs = ['overview' => 'Overview & settings', 'referrals' => 'Referrals', 'customer' => 'Customer lookup'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Points &amp; referrals — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">eClinicPro Points &amp; referrals</h1>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <nav class="flex gap-2 text-sm">
        <?php foreach ($tabs as $k => $label): ?>
            <a href="/admin/store/rewards?tab=<?= $e($k) ?>" class="rounded-full px-3 py-1.5 <?= $tab === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border' ?>"><?= $e($label) ?></a>
        <?php endforeach; ?>
    </nav>

<?php if ($tab === 'overview'): ?>
    <div class="grid gap-3 sm:grid-cols-4">
        <?php foreach ([
            ['Spendable points outstanding', $stats['outstanding'] ?? 0, 'What you owe as future discounts (1 point = ₹1)'],
            ['Pending points', $stats['pending'] ?? 0, 'Referral / loyalty waiting on delivery or the return window'],
            ['Points used', $stats['spent'] ?? 0, 'Net of points returned on unpaid / cancelled orders'],
            ['Points expired', $stats['expired'] ?? 0, ''],
        ] as [$label, $val, $hint]): ?>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <div class="text-2xl font-semibold"><?= $n($val) ?></div>
                <div class="text-sm font-medium text-slate-700"><?= $e($label) ?></div>
                <?php if ($hint): ?><div class="mt-1 text-xs text-slate-500"><?= $e($hint) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="text-sm text-slate-600">
        <?= $n($stats['customers'] ?? 0) ?> customers hold points ·
        <?= $n($stats['referrals'] ?? 0) ?> referrals, <?= $n($stats['rewarded'] ?? 0) ?> paid out ·
        <a class="text-teal-700 underline" href="/admin/store/rewards?tab=referrals&flagged=1"><?= $n($stats['flagged'] ?? 0) ?> flagged for review</a>
    </p>

    <form method="post" action="/admin/store/rewards/settings" class="space-y-4 rounded-xl border bg-white p-5 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <h2 class="font-semibold">Programme settings</h2>
        <p class="text-sm text-slate-500">Changes apply to points given from now on. Points customers already hold keep working. Points are always a platform-funded discount: sellers are paid the full price.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="store_points_enabled" value="1" <?= ($settings['store_points_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                Points switched on (cart, checkout, patient panel, sign-up referral codes)
            </label>
            <label class="text-sm">Launch date &amp; time <span class="text-slate-400">(only sign-ups after this get welcome points)</span>
                <input type="datetime-local" name="store_points_launch_at" value="<?= $e(($settings['store_points_launch_at'] ?? '') !== '' && strtotime($settings['store_points_launch_at']) ? date('Y-m-d\\TH:i', strtotime($settings['store_points_launch_at'])) : '') ?>" class="<?= $in ?>">
            </label>
            <?php foreach ($intSettings as $k => [$label]):
                $raw = (int) ($settings[$k] ?? 0);
                $shown = str_ends_with($k, '_paise') || str_ends_with($k, '_bp') ? rtrim(rtrim(number_format($raw / 100, 2, '.', ''), '0'), '.') : (string) $raw; ?>
                <label class="text-sm"><?= $e($label) ?>
                    <input name="<?= $e($k) ?>" value="<?= $e($shown) ?>" inputmode="decimal" class="<?= $in ?>">
                </label>
            <?php endforeach; ?>
        </div>
        <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save settings</button>
    </form>

<?php elseif ($tab === 'referrals'): ?>
    <p class="text-sm text-slate-500">Flags are hints only (the friend's order phone or address matches the referrer's); nothing is blocked automatically. Rejecting cancels both people's pending points. Points already given can be removed from <a class="underline" href="/admin/store/rewards?tab=customer">Customer lookup</a>.</p>
    <p class="text-sm"><a class="text-teal-700 underline" href="/admin/store/rewards?tab=referrals<?= empty($flagged) ? '&flagged=1' : '' ?>"><?= empty($flagged) ? 'Show flagged only' : 'Show all' ?></a></p>
    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-3 py-2">Referrer</th><th class="px-3 py-2">Friend</th><th class="px-3 py-2">Code</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Qualifying order</th><th class="px-3 py-2">Flag</th><th class="px-3 py-2"></th></tr>
            </thead>
            <tbody class="divide-y">
            <?php if (!$referrals): ?>
                <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">No referrals yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($referrals as $r): ?>
                <tr class="<?= $r['flag_reason'] && $r['status'] !== 'rejected' ? 'bg-amber-50' : '' ?>">
                    <td class="px-3 py-2"><a class="underline" href="/admin/store/rewards?tab=customer&q=<?= $e(rawurlencode((string) $r['referrer_phone'])) ?>"><?= $e($r['referrer_name']) ?></a><div class="text-xs text-slate-500"><?= $e($r['referrer_phone']) ?></div></td>
                    <td class="px-3 py-2"><a class="underline" href="/admin/store/rewards?tab=customer&q=<?= $e(rawurlencode((string) $r['referee_phone'])) ?>"><?= $e($r['referee_name']) ?></a><div class="text-xs text-slate-500"><?= $e($r['referee_phone']) ?> · joined <?= $e($d($r['created_at'])) ?></div></td>
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= $e($r['code']) ?></td>
                    <td class="px-3 py-2"><?= $e($refStatus[$r['status']] ?? $r['status']) ?></td>
                    <td class="whitespace-nowrap px-3 py-2"><?= $r['order_no'] ? '<a class="underline" href="/admin/store/orders?q=' . $e(rawurlencode((string) $r['order_no'])) . '">' . $e($r['order_no']) . '</a>' : '—' ?></td>
                    <td class="px-3 py-2 text-xs text-amber-800"><?= $e($r['flag_reason'] ?? '') ?></td>
                    <td class="px-3 py-2">
                        <?php if (in_array($r['status'], ['signed_up', 'qualified'], true)): ?>
                            <form method="post" action="/admin/store/rewards/referral/<?= (int) $r['id'] ?>" onsubmit="return confirm('Reject this referral? Both people lose these pending points.')" class="flex gap-1">
                                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                                <input name="reason" placeholder="Reason" class="w-28 rounded border px-2 py-1 text-xs">
                                <button class="rounded border border-red-300 px-2 py-1 text-xs text-red-700">Reject</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <form method="get" action="/admin/store/rewards" class="flex gap-2">
        <input type="hidden" name="tab" value="customer">
        <input name="q" value="<?= $e($q) ?>" placeholder="Customer phone (10 digits)" class="w-64 rounded border px-3 py-2 text-sm">
        <button class="rounded bg-slate-900 px-4 py-2 text-sm text-white">Look up</button>
    </form>
    <?php if ($q !== '' && $customer === null): ?>
        <p class="text-sm text-slate-500">No patient account with that phone number.</p>
    <?php elseif ($customer !== null):
        $s = $customer['summary']; $id = $customer['identity']; ?>
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold"><?= $e($id['name']) ?> <span class="text-sm font-normal text-slate-500"><?= $e($id['phone']) ?> · <?= $e($id['source']) ?> · joined <?= $e($d($id['created_at'])) ?></span></h2>
                <div class="text-sm">Code <span class="font-mono"><?= $e($s['referral']['code']) ?></span> <?= $s['referral']['active'] ? '(unlocked)' : '(locked until first delivered order)' ?></div>
            </div>
            <p class="mt-2 text-sm"><strong><?= $n($s['balance']['available']) ?></strong> spendable (welcome <?= $n($s['balance']['welcome']) ?>, other <?= $n($s['balance']['standard']) ?>) · <strong><?= $n($s['balance']['pending']) ?></strong> pending · <?= count($s['referral']['friends']) ?> friends referred</p>
            <form method="post" action="/admin/store/rewards/adjust" class="mt-4 flex flex-wrap items-end gap-2">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="identity_id" value="<?= (int) $id['id'] ?>">
                <input type="hidden" name="q" value="<?= $e($q) ?>">
                <label class="text-sm">Points (+ add / − remove)<input name="points" inputmode="numeric" placeholder="e.g. 50 or -50" class="<?= $in ?> w-40"></label>
                <label class="flex-1 text-sm">Reason (customer sees it in their history)<input name="note" maxlength="200" placeholder="e.g. Goodwill for late delivery" class="<?= $in ?>"></label>
                <button class="rounded bg-slate-900 px-4 py-2 text-sm text-white" onclick="return confirm('Update this customer\'s points?')">Apply</button>
            </form>
        </div>
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2">Lot</th><th class="px-3 py-2">Kind</th><th class="px-3 py-2">Points</th><th class="px-3 py-2">Left</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Available</th><th class="px-3 py-2">Expires</th><th class="px-3 py-2">Note</th></tr>
                </thead>
                <tbody class="divide-y">
                <?php foreach ($customer['all_lots'] as $l): ?>
                    <tr>
                        <td class="px-3 py-2 text-xs text-slate-500">#<?= (int) $l['id'] ?></td>
                        <td class="px-3 py-2"><?= $e($l['kind']) ?></td>
                        <td class="px-3 py-2"><?= $n($l['points']) ?></td>
                        <td class="px-3 py-2"><?= $n($l['points_left']) ?></td>
                        <td class="px-3 py-2"><?= $e($l['status']) ?></td>
                        <td class="px-3 py-2"><?= $e($d($l['available_at'])) ?></td>
                        <td class="px-3 py-2"><?= $e($d($l['expires_at'])) ?></td>
                        <td class="px-3 py-2 text-xs text-slate-600"><?= $e($l['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($s['history']): ?>
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">When</th><th class="px-3 py-2">What</th><th class="px-3 py-2">Points</th><th class="px-3 py-2">Order</th><th class="px-3 py-2">Note</th></tr></thead>
                <tbody class="divide-y">
                <?php foreach ($s['history'] as $t): ?>
                    <tr><td class="px-3 py-2"><?= $e($d($t['at'])) ?></td><td class="px-3 py-2"><?= $e($t['type'] . ' · ' . $t['kind']) ?></td><td class="px-3 py-2"><?= ($t['points'] > 0 ? '+' : '') . (int) $t['points'] ?></td><td class="px-3 py-2"><?= $e($t['order_no'] ?? '—') ?></td><td class="px-3 py-2 text-xs text-slate-600"><?= $e($t['note'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
