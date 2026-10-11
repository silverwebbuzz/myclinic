<?php
/** /admin/health-tips/contributors — who may post Health Tips. */
use App\Services\HealthTipAdminService as HT;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$messages = [
    'contributor_granted' => 'Contributor access granted. They’ll see “My health tips” in their patient panel (/patient) next time they open it.',
    'contributor_suspended' => 'Contributor paused — their posts are hidden from the public site.',
    'contributor_activated' => 'Contributor re-activated — their live posts are visible again.',
];
$errors = ['identity_not_found' => 'That account was not found or is inactive.'];
$existingIds = array_map(static fn ($c) => (int) $c['identity_id'], $contributors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Health Tips contributors · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Health Tips — contributors</h1>
        <p class="text-sm text-slate-500">Invite-only. A contributor is an existing patient account (they sign up at /patient with their phone) that you give posting access to.</p>

        <?php if ($message && isset($messages[$message])): ?>
        <div class="mt-3 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"><?= $h($messages[$message]) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $h($errors[$error] ?? $error) ?></div>
        <?php endif; ?>

        <!-- Grant -->
        <div class="mt-4 ui-card p-5">
            <h2 class="font-semibold">Add a contributor</h2>
            <form method="get" action="/admin/health-tips/contributors" class="mt-3 flex flex-wrap items-end gap-2">
                <label class="block text-xs text-slate-600">Their mobile number
                    <input type="tel" name="phone" value="<?= $h($phone) ?>" placeholder="98765 43210" class="mt-1 w-56 rounded border px-2 py-1.5 text-sm"></label>
                <button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-900">Find account</button>
            </form>

            <?php if ($matches !== null): ?>
                <?php if (!$matches): ?>
                    <p class="mt-3 rounded bg-amber-50 p-3 text-sm text-amber-900">No patient account with that number. Ask them to sign up first at <strong>eclinicpro.com/patient</strong> (phone + OTP, takes a minute), then search again.</p>
                <?php else: ?>
                    <div class="mt-3 divide-y rounded border">
                        <?php foreach ($matches as $m): ?>
                        <div class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                            <div>
                                <div class="font-medium"><?= $h($m['name']) ?></div>
                                <div class="text-xs text-slate-500"><?= $h($m['phone']) ?><?= $m['email'] ? ' · ' . $h($m['email']) : '' ?> · signed up <?= $h(date('d M Y', strtotime((string) $m['created_at']))) ?></div>
                            </div>
                            <?php if (in_array((int) $m['id'], $existingIds, true)): ?>
                                <span class="text-xs text-slate-500">Already a contributor (see below)</span>
                            <?php else: ?>
                            <form method="post" action="/admin/health-tips/contributors/grant" class="flex flex-wrap items-center gap-2">
                                <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                <input type="hidden" name="identity_id" value="<?= (int) $m['id'] ?>">
                                <input type="hidden" name="return_to" value="/admin/health-tips/contributors">
                                <input type="text" name="admin_note" maxlength="255" placeholder="Private note, e.g. @paushtikpalette" class="w-60 rounded border px-2 py-1.5 text-xs">
                                <button class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Make contributor</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- List -->
        <div class="mt-6 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Contributor</th>
                        <th class="px-4 py-3">Account</th>
                        <th class="px-4 py-3">Posts</th>
                        <th class="px-4 py-3">Terms</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$contributors): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No contributors yet — add your first one above.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($contributors as $c): ?>
                    <tr class="border-b align-top">
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= $h($c['display_name']) ?></div>
                            <?php if ($c['headline']): ?><div class="text-xs text-slate-500"><?= $h($c['headline']) ?></div><?php endif; ?>
                            <?php if ($c['admin_note']): ?><div class="mt-1 text-xs text-slate-400">📝 <?= $h($c['admin_note']) ?></div><?php endif; ?>
                            <a href="<?= $h(HT::siteUrl('/health-tips/by/' . $c['slug'])) ?>" target="_blank" rel="noopener" class="text-xs text-sky-700 underline">/health-tips/by/<?= $h($c['slug']) ?></a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            <a href="/admin/signups/<?= (int) $c['identity_id'] ?>" class="hover:underline"><?= $h($c['identity_name'] ?? '—') ?></a>
                            <div class="text-xs"><?= $h($c['identity_phone'] ?? '') ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            <?= (int) $c['live_count'] ?> live · <?= (int) $c['pending_count'] ?> waiting · <?= (int) $c['total_count'] ?> total
                        </td>
                        <td class="px-4 py-3 text-xs"><?= $c['terms_accepted_at'] ? '✓ ' . $h(date('d M Y', strtotime((string) $c['terms_accepted_at']))) : '<span class="text-amber-700">Not yet</span>' ?></td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $c['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>"><?= $c['status'] === 'active' ? 'Active' : 'Paused' ?></span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="post" action="/admin/health-tips/contributors/<?= (int) $c['id'] ?>/status"
                                  onsubmit="return confirm('<?= $c['status'] === 'active' ? 'Pause this contributor? All their posts are hidden until you re-activate.' : 'Re-activate this contributor?' ?>');">
                                <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                <input type="hidden" name="return_to" value="/admin/health-tips/contributors">
                                <input type="hidden" name="status" value="<?= $c['status'] === 'active' ? 'suspended' : 'active' ?>">
                                <button class="rounded border bg-white px-3 py-1.5 text-xs font-semibold <?= $c['status'] === 'active' ? 'text-rose-700 hover:bg-rose-50' : 'text-emerald-700 hover:bg-emerald-50' ?>"><?= $c['status'] === 'active' ? 'Pause' : 'Re-activate' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
