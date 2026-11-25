<?php
/** /admin/directory — all directory_doctors listings with Hide / Show. */
$f = $filters;
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$qsParts = array_filter($f, static fn ($v) => $v !== '');
$baseQs = http_build_query($qsParts);
$returnQs = $baseQs . ($baseQs ? '&' : '') . 'page=' . (int) $page;
$messages = [
    'hidden'  => 'Listing hidden. It is off the website now.',
    'shown'   => 'Listing is visible on the website again.',
];
$errors = [
    'csrf'      => 'Session expired. Reload the page and try again.',
    'not_found' => 'That listing no longer exists.',
];
$btn = 'rounded px-2 py-1 text-[11px] font-semibold';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Directory listings · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-7xl px-6 py-6">
        <div>
            <h1 class="text-xl font-semibold">Directory listings</h1>
            <p class="text-sm text-slate-500">Every doctor and clinic in the public directory. <strong>Hide</strong> takes a listing off the website; <strong>Show</strong> puts it back. <strong><?= number_format((int) $total) ?></strong> listings match.</p>
        </div>

        <?php if ($message !== '' && isset($messages[$message])): ?>
        <div class="mt-3 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"><?= $h($messages[$message]) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
        <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $h($errors[$error] ?? 'Something went wrong.') ?></div>
        <?php endif; ?>

        <form method="get" action="/admin/directory" class="mt-4 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-5">
            <label class="block text-xs sm:col-span-2"><span class="text-slate-600">Search</span>
                <input type="text" name="q" value="<?= $h($f['q']) ?>" placeholder="Name, listing ID or phone" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
            <label class="block text-xs"><span class="text-slate-600">City</span>
                <select name="city" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <?php foreach ($options['cities'] as $c): ?>
                    <option value="<?= $h($c) ?>" <?= $f['city'] === $c ? 'selected' : '' ?>><?= $h($c) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">Specialty</span>
                <select name="specialty" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <?php foreach ($options['specialties'] as $s): ?>
                    <option value="<?= $h($s) ?>" <?= $f['specialty'] === $s ? 'selected' : '' ?>><?= $h($s) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">On website</span>
                <select name="visibility" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <?php foreach ($visibility as $val => $label): ?>
                    <option value="<?= $h($val) ?>" <?= $f['visibility'] === $val ? 'selected' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">Claimed</span>
                <select name="claimed" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <option value="1" <?= $f['claimed'] === '1' ? 'selected' : '' ?>>Claimed (joined)</option>
                    <option value="0" <?= $f['claimed'] === '0' ? 'selected' : '' ?>>Not claimed</option>
                </select></label>
            <div class="flex items-end gap-2 sm:col-span-4">
                <button type="submit" class="rounded bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Apply filters</button>
                <a href="/admin/directory" class="rounded border px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Reset</a>
            </div>
        </form>

        <section class="mt-5 overflow-hidden rounded-xl border bg-white">
            <?php if (empty($rows)): ?>
            <p class="p-6 text-sm text-slate-500">No listings match these filters.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">ID</th>
                            <th class="px-4 py-3 text-left">Doctor / Clinic</th>
                            <th class="px-4 py-3 text-left">City</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-right">Bookings</th>
                            <th class="px-4 py-3 text-left">On website</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $r):
                            $live = $r['is_active'] && $r['status'] === 'OPERATIONAL';
                            $title = $r['doctor_name'] !== '' ? $r['doctor_name'] : $r['name'];
                        ?>
                        <tr class="align-top hover:bg-slate-50 <?= $live ? '' : 'bg-slate-50/60' ?>">
                            <td class="px-4 py-3 text-xs text-slate-400">#<?= (int) $r['id'] ?></td>
                            <td class="px-4 py-3">
                                <div class="font-semibold <?= $live ? 'text-slate-900' : 'text-slate-500' ?>"><?= $h($title) ?></div>
                                <?php if ($r['doctor_name'] !== '' && $r['name'] !== $r['doctor_name']): ?>
                                <div class="text-xs text-slate-500"><?= $h($r['name']) ?></div>
                                <?php endif; ?>
                                <div class="text-[11px] text-slate-400">
                                    <?= $h($r['specialty']) ?>
                                    <?php if ($r['rating'] !== null): ?> · ★ <?= $h(number_format($r['rating'], 1)) ?> (<?= (int) $r['reviews'] ?>)<?php endif; ?>
                                    <?php if ($r['is_claimed']): ?> · <span class="font-semibold text-emerald-700">Claimed</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <?= $h($r['city']) ?>
                                <?php if ($r['area'] !== ''): ?><div class="text-[11px] text-slate-400"><?= $h($r['area']) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $r['phone'] !== '' ? $h($r['phone']) : '<span class="text-slate-400">—</span>' ?></td>
                            <td class="px-4 py-3 text-right <?= $r['bookings'] > 0 ? 'font-semibold text-slate-900' : 'text-slate-400' ?>"><?= (int) $r['bookings'] ?></td>
                            <td class="px-4 py-3">
                                <?php if ($live): ?>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Showing</span>
                                <?php elseif (!$r['is_active']): ?>
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700">Hidden</span>
                                <?php if (!$r['hidden_by_admin']): ?><div class="mt-1 text-[10px] text-slate-400">by importer</div><?php endif; ?>
                                <?php else: ?>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Closed</span>
                                <div class="mt-1 text-[10px] text-slate-400"><?= $h(strtolower(str_replace('_', ' ', $r['status']))) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1">
                                    <?php if ($live): ?>
                                    <a href="<?= $h('https://eclinicpro.com' . $r['url']) ?>" target="_blank" rel="noopener" class="<?= $btn ?> border text-slate-600 hover:bg-white">View</a>
                                    <?php endif; ?>

                                    <form method="post" action="/admin/directory/<?= (int) $r['id'] ?>/<?= $r['is_active'] ? 'hide' : 'show' ?>">
                                        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                        <input type="hidden" name="return_qs" value="<?= $h($returnQs) ?>">
                                        <?php if ($r['is_active']): ?>
                                        <button type="submit" class="<?= $btn ?> bg-slate-700 text-white hover:bg-slate-900">Hide</button>
                                        <?php else: ?>
                                        <button type="submit" class="<?= $btn ?> bg-emerald-600 text-white hover:bg-emerald-700">Show</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($pages > 1):
            $mk = static fn (int $p): string => '/admin/directory?' . http_build_query(array_merge($qsParts, ['page' => $p]));
        ?>
        <div class="mt-4 flex items-center justify-between text-sm">
            <span class="text-slate-500">Page <?= (int) $page ?> of <?= number_format((int) $pages) ?></span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="<?= $h($mk($page - 1)) ?>" class="rounded border px-3 py-1.5 hover:bg-white">← Prev</a><?php endif; ?>
                <?php if ($page < $pages): ?><a href="<?= $h($mk($page + 1)) ?>" class="rounded border px-3 py-1.5 hover:bg-white">Next →</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <p class="mt-6 text-xs text-slate-400">
            Hide and Show take effect on the website immediately: saved copies of public pages are cleared on every change.
            Hidden listings stay hidden when doctors are re-imported.
        </p>
    </main>
</body>
</html>
