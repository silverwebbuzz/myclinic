<?php
/** /admin/wellness/creators/{id} — one Health Creator: permissions, badge, documents, status, history. */
use App\Services\Wellness\CreatorService as CS;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dt = static fn (?string $d): string => $d ? date('d M Y, h:i A', strtotime($d)) : '—';
$kind = CS::badgeKind($c);
$vs = (string) $c['verify_status'];
$messages = [
    'permission_saved' => 'Permission updated. The creator has been emailed (if they gave an email).',
    'verification_saved' => 'Badge decision saved and logged.',
    'status_saved' => 'Creator status updated.',
    'note_saved' => 'Saved.',
];
$errors = [
    'note_required' => 'Please write a reason — the creator sees it.',
    'register_required' => 'Write which register you checked (e.g. “NMC IMR”) before verifying.',
    'not_approved' => 'Only an approved permission can be paused.',
    'not_verified' => 'This creator is not verified.',
    'expired' => 'Your session expired. Please try again.',
];
$permPill = ['approved' => 'bg-emerald-100 text-emerald-800', 'applied' => 'bg-amber-100 text-amber-800', 'rejected' => 'bg-rose-100 text-rose-800', 'suspended' => 'bg-slate-200 text-slate-700'];
$permLabel = ['approved' => 'Approved', 'applied' => 'Waiting', 'rejected' => 'Not approved', 'suspended' => 'Paused'];
$docsBy = ['verification' => [], 'permission' => []];
foreach ($c['documents'] as $d) {
    $docsBy[$d['purpose']][] = $d;
}
$docLink = static function (array $d) use ($h): string {
    $st = ['pending' => 'text-amber-700', 'accepted' => 'text-emerald-700', 'rejected' => 'text-rose-700'][$d['status']] ?? '';

    return '<a href="/admin/wellness/documents/' . (int) $d['id'] . '" target="_blank" rel="noopener" class="text-sky-700 underline">'
        . $h(CS::DOC_TYPES[$d['doc_type']] ?? $d['doc_type']) . '</a> <span class="text-xs text-slate-500">' . $h(date('d M Y', strtotime((string) $d['created_at'])))
        . ' · <span class="' . $st . '">' . $h($d['status']) . '</span> · ' . $h($d['original_name'] ?? '') . '</span>';
};
$isDoctor = $kind === 'doctor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $h($c['display_name']) ?> · Health Creators · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <a href="/admin/wellness/creators" class="text-xs text-slate-500 hover:underline">← Health Creators</a>

        <div class="mt-2 flex flex-wrap items-start gap-4">
            <?php if (!empty($c['avatar_path'])): ?>
                <img src="/admin/wellness/creators/<?= (int) $c['id'] ?>/avatar" alt="" class="h-14 w-14 rounded-full object-cover">
            <?php endif; ?>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-semibold"><?= $h($c['display_name']) ?>
                    <?php if ($vs === 'verified'): ?><span class="ml-2 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">✓ <?= $h(CS::badgeLabel($c)) ?></span><?php endif; ?>
                    <?php if ($c['status'] === 'suspended'): ?><span class="ml-2 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">Suspended</span><?php endif; ?>
                </h1>
                <p class="text-sm text-slate-600"><?= $h(CS::TYPES[$c['creator_type']] ?? $c['creator_type']) ?><?= $c['org_name'] ? ' · ' . $h($c['org_name']) : '' ?><?= $c['city'] ? ' · ' . $h($c['city']) . ($c['state'] ? ', ' . $h($c['state']) : '') : '' ?></p>
                <p class="text-xs text-slate-500">Joined <?= $h($dt($c['created_at'])) ?> · terms <?= $c['terms_accepted_at'] ? 'accepted ' . $h(date('d M Y', strtotime((string) $c['terms_accepted_at']))) . ' (v' . $h($c['terms_version']) . ')' : 'not accepted' ?></p>
            </div>
        </div>

        <?php if ($message && isset($messages[$message])): ?>
            <div class="mt-3 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"><?= $h($messages[$message]) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $h($errors[$error] ?? $error) ?></div>
        <?php endif; ?>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <!-- Profile -->
            <section class="ui-card p-5 lg:col-span-1">
                <h2 class="font-semibold">Profile</h2>
                <dl class="mt-2 space-y-1.5 text-sm">
                    <div><dt class="text-xs text-slate-500">Phone (login)</dt><dd><?php if ($c['identity_id']): ?><a href="/admin/signups/<?= (int) $c['identity_id'] ?>" class="text-sky-700 hover:underline"><?= $h($c['identity_phone']) ?></a><?php else: ?>— (platform)<?php endif; ?></dd></div>
                    <div><dt class="text-xs text-slate-500">Email</dt><dd><?= $h($c['email'] ?: '—') ?></dd></div>
                    <?php if ($c['headline']): ?><div><dt class="text-xs text-slate-500">Headline</dt><dd><?= $h($c['headline']) ?></dd></div><?php endif; ?>
                    <?php if ($c['bio']): ?><div><dt class="text-xs text-slate-500">About</dt><dd class="whitespace-pre-line text-slate-700"><?= $h($c['bio']) ?></dd></div><?php endif; ?>
                    <?php if ($c['org_reg_no'] || $c['gstin']): ?><div><dt class="text-xs text-slate-500">Org reg. / GSTIN</dt><dd><?= $h($c['org_reg_no'] ?: '—') ?> / <?= $h($c['gstin'] ?: '—') ?></dd></div><?php endif; ?>
                    <div><dt class="text-xs text-slate-500">Links</dt><dd class="space-x-2 text-xs">
                        <?php if ($c['instagram_handle']): ?><a class="text-sky-700 underline" target="_blank" rel="noopener" href="https://instagram.com/<?= $h(rawurlencode((string) $c['instagram_handle'])) ?>">@<?= $h($c['instagram_handle']) ?></a><?php endif; ?>
                        <?php if ($c['youtube_url']): ?><a class="text-sky-700 underline" target="_blank" rel="noopener" href="<?= $h($c['youtube_url']) ?>">YouTube</a><?php endif; ?>
                        <?php if ($c['website_url']): ?><a class="text-sky-700 underline" target="_blank" rel="noopener" href="<?= $h($c['website_url']) ?>">Website</a><?php endif; ?>
                        <?php if (!$c['instagram_handle'] && !$c['youtube_url'] && !$c['website_url']): ?>—<?php endif; ?>
                    </dd></div>
                    <?php if ($c['tip_counts']): ?><div><dt class="text-xs text-slate-500">Health Tips posts</dt><dd class="text-xs"><?= (int) ($c['tip_counts']['published'] ?? 0) ?> live · <?= (int) ($c['tip_counts']['submitted'] ?? 0) ?> waiting · <?= array_sum($c['tip_counts']) ?> total</dd></div><?php endif; ?>
                </dl>

                <form method="post" action="/admin/wellness/creators/<?= (int) $c['id'] ?>/note" class="mt-4 space-y-2 border-t pt-3">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <label class="block text-xs text-slate-600">Private admin note
                        <input name="admin_note" value="<?= $h($c['admin_note'] ?? '') ?>" maxlength="255" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
                    <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="is_trusted" value="1" <?= $c['is_trusted'] ? 'checked' : '' ?>> Trusted creator <span class="text-slate-400">(auto-publish comes in a later phase)</span></label>
                    <button class="rounded border bg-white px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Save</button>
                </form>

                <?php if ($c['creator_type'] !== 'platform'): ?>
                <form method="post" action="/admin/wellness/creators/<?= (int) $c['id'] ?>/status" class="mt-4 space-y-2 border-t pt-3"
                      onsubmit="return confirm('<?= $c['status'] === 'active' ? 'Suspend this creator? All their posts are hidden and their Studio becomes read-only.' : 'Re-activate this creator?' ?>');">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="status" value="<?= $c['status'] === 'active' ? 'suspended' : 'active' ?>">
                    <?php if ($c['status'] === 'active'): ?>
                        <input name="reason" maxlength="255" required placeholder="Reason (the creator sees it)" class="w-full rounded border px-2 py-1.5 text-sm">
                        <button class="rounded border bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Suspend creator</button>
                    <?php else: ?>
                        <p class="text-xs text-rose-700">Suspended: <?= $h($c['status_reason'] ?? '') ?></p>
                        <button class="rounded border bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Re-activate</button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </section>

            <div class="space-y-4 lg:col-span-2">
                <!-- Permissions -->
                <section class="ui-card p-5">
                    <h2 class="font-semibold">What they can post</h2>
                    <p class="text-xs text-slate-500">Approve each type separately. Rejections need a reason (emailed to the creator).</p>
                    <div class="mt-3 divide-y">
                        <?php foreach (CS::CONTENT as $t => [$lbl, $proof]): $p = $c['permissions'][$t] ?? null; $st = $p['status'] ?? null; ?>
                            <div class="py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium"><?= $h($lbl) ?></span>
                                    <?php if ($st): ?><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $permPill[$st] ?>"><?= $h($permLabel[$st]) ?></span><?php else: ?><span class="text-xs text-slate-400">Not applied</span><?php endif; ?>
                                    <?php if ($p && $p['applied_at'] && $st === 'applied'): ?><span class="text-xs text-slate-500">since <?= $h($dt($p['applied_at'])) ?></span><?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-500">Usual proof: <?= $h($proof) ?></p>
                                <?php if (!empty($p['applicant_note'])): ?><p class="mt-1 rounded bg-slate-50 px-2 py-1 text-xs text-slate-700"><strong>Creator wrote:</strong> <?= $h($p['applicant_note']) ?></p><?php endif; ?>
                                <?php if (!empty($p['review_note']) && $st !== 'applied'): ?><p class="mt-1 text-xs text-slate-500">Our note: <?= $h($p['review_note']) ?></p><?php endif; ?>
                                <?php foreach ($docsBy['permission'] as $d): if ($d['content_type'] !== $t) { continue; } ?>
                                    <div class="mt-1 text-sm"><?= $docLink($d) ?></div>
                                <?php endforeach; ?>

                                <form method="post" action="/admin/wellness/creators/<?= (int) $c['id'] ?>/permission" class="mt-2 flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                    <input type="hidden" name="content_type" value="<?= $h($t) ?>">
                                    <input name="note" maxlength="600" placeholder="Note to creator<?= $st === 'applied' ? ' (required to reject)' : '' ?>" class="min-w-[220px] flex-1 rounded border px-2 py-1 text-xs">
                                    <?php if ($st === 'applied'): ?>
                                        <button name="decision" value="approve" class="rounded bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                        <button name="decision" value="reject" class="rounded border bg-white px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                                    <?php elseif ($st === 'approved'): ?>
                                        <button name="decision" value="suspend" class="rounded border bg-white px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" onclick="return confirm('Pause this permission? They can no longer post this type.')">Pause</button>
                                    <?php else: ?>
                                        <button name="decision" value="grant" class="rounded border bg-white px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"><?= $st === 'suspended' ? 'Re-approve' : 'Grant directly' ?></button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Badge -->
                <section class="ui-card p-5">
                    <h2 class="font-semibold"><?= $isDoctor ? 'Verified Doctor badge' : 'Verified Organisation badge' ?></h2>
                    <?php if ($kind === null): ?>
                        <p class="mt-1 text-sm text-slate-500">Not available for this creator type (<?= $h(CS::TYPE_SHORT[$c['creator_type']] ?? '') ?>).</p>
                    <?php else: ?>
                        <p class="mt-1 text-sm">Status: <strong><?= $h(['none' => 'Not requested', 'pending' => 'Waiting for your check', 'verified' => 'Verified', 'rejected' => 'Rejected', 'revoked' => 'Revoked'][$vs] ?? $vs) ?></strong>
                            <?php if ($vs === 'verified'): ?><span class="text-xs text-slate-500">· <?= $h($dt($c['verified_at'])) ?> · register: <?= $h($c['verify_register'] ?? '') ?></span><?php endif; ?>
                            <?php if ($vs === 'pending'): ?><span class="text-xs text-slate-500">· sent <?= $h($dt($c['verify_requested_at'])) ?></span><?php endif; ?>
                        </p>
                        <?php if ($isDoctor && ($c['med_reg_no'] || $c['med_council'])): ?>
                            <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                                <div><dt class="text-xs text-slate-500">Council</dt><dd><?= $h($c['med_council'] ?? '—') ?></dd></div>
                                <div><dt class="text-xs text-slate-500">Registration no.</dt><dd class="font-mono"><?= $h($c['med_reg_no'] ?? '—') ?></dd></div>
                                <div><dt class="text-xs text-slate-500">Qualification</dt><dd><?= $h($c['med_qualification'] ?? '—') ?></dd></div>
                                <div><dt class="text-xs text-slate-500">Specialty</dt><dd><?= $h($c['specialty'] ?: '—') ?></dd></div>
                            </dl>
                            <p class="mt-2 rounded bg-sky-50 px-3 py-2 text-xs text-sky-900">Check the number + name on the <a href="https://www.nmc.org.in/" target="_blank" rel="noopener" class="font-semibold underline">NMC website</a> → Indian Medical Register (or the state / dental / AYUSH register). Match the photo ID to the name.</p>
                        <?php elseif (!$isDoctor): ?>
                            <p class="mt-2 text-sm">Organisation: <strong><?= $h($c['org_name'] ?: '—') ?></strong> · reg. no. <span class="font-mono"><?= $h($c['org_reg_no'] ?: '—') ?></span></p>
                        <?php endif; ?>
                        <?php foreach ($docsBy['verification'] as $d): ?>
                            <div class="mt-1 text-sm"><?= $docLink($d) ?></div>
                        <?php endforeach; ?>

                        <?php if ($vs === 'pending'): ?>
                            <form method="post" action="/admin/wellness/creators/<?= (int) $c['id'] ?>/verify" class="mt-3 space-y-2">
                                <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                <input name="register" maxlength="120" placeholder="Register checked, e.g. NMC IMR / Gujarat Medical Council (required to verify)" class="w-full rounded border px-2 py-1.5 text-sm">
                                <input name="note" maxlength="600" placeholder="Note to creator (required to reject)" class="w-full rounded border px-2 py-1.5 text-sm">
                                <div class="flex gap-2">
                                    <button name="decision" value="verify" class="rounded bg-sky-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-800" onclick="return confirm('Confirm you checked the official register and the documents match.')">Verify</button>
                                    <button name="decision" value="reject" class="rounded border bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                                </div>
                            </form>
                        <?php elseif ($vs === 'verified'): ?>
                            <form method="post" action="/admin/wellness/creators/<?= (int) $c['id'] ?>/verify" class="mt-3 flex flex-wrap gap-2" onsubmit="return confirm('Revoke the badge? “Dr.” is removed from their name and this is logged.');">
                                <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                <input name="note" maxlength="600" required placeholder="Reason for revoking (the creator sees it)" class="min-w-[260px] flex-1 rounded border px-2 py-1.5 text-sm">
                                <button name="decision" value="revoke" class="rounded border bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Revoke badge</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <!-- History -->
                <section class="ui-card p-5">
                    <h2 class="font-semibold">History</h2>
                    <?php if (!$c['log']): ?><p class="mt-1 text-sm text-slate-500">Nothing yet.</p><?php endif; ?>
                    <ul class="mt-2 space-y-1 text-xs text-slate-700">
                        <?php foreach ($c['log'] as $l): ?>
                            <li><span class="text-slate-500"><?= $h($dt($l['created_at'])) ?></span> · <strong><?= $h(str_replace('_', ' ', $l['action'])) ?></strong><?= $l['detail'] ? ' — ' . $h($l['detail']) : '' ?> <span class="text-slate-400"><?= $l['admin_id'] ? '(admin #' . (int) $l['admin_id'] . ')' : '(creator)' ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
