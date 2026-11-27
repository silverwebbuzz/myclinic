<?php
/** /admin/wellness/events/{id} — full listing preview + decision + reports. */
use App\Services\Wellness\EventService as ES;
use App\Services\Wellness\CreatorService as CS;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$st = (string) $e['status'];
$ended = strtotime((string) $e['end_at']) < time();
$messages = ['publish' => 'Published — it is live now.', 'changes' => 'Sent back to the creator with your note.', 'reject' => 'Rejected. The creator has been emailed.', 'unpublish' => 'Unpublished — hidden from the public.'];
$errors = ['note_required' => 'Please write a note — the creator sees it.', 'bad_decision' => 'That action isn’t possible in the current status.', 'expired' => 'Your session expired. Please try again.'];
$reasons = ['fake' => 'Looks fake / spam', 'hidden_fees' => 'Hidden fees', 'medical_claim' => 'Medical claim', 'wrong_details' => 'Wrong details', 'copyright' => 'Copyright', 'other' => 'Other'];
$row = static function (string $label, ?string $value) use ($h): string {
    return $value === null || $value === '' ? '' : '<div><dt class="text-xs text-slate-500">' . $h($label) . '</dt><dd class="text-sm text-slate-800">' . $value . '</dd></div>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $h($e['title']) ?> · Camps &amp; events · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <a href="/admin/wellness/events" class="text-xs text-slate-500 hover:underline">← Camps &amp; events</a>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold"><?= $h($e['title']) ?></h1>
            <span class="rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold ring-1 ring-slate-200"><?= $h(ES::STATUS_LABELS[$st] ?? $st) ?><?= $ended && $st === 'published' ? ' · ended' : '' ?></span>
            <?php if ($e['published_at'] && $st === 'submitted'): ?><span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Edit of a live listing (hidden until you approve)</span><?php endif; ?>
        </div>
        <?php if ($message && isset($messages[$message])): ?>
            <div class="mt-3 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"><?= $h($messages[$message]) ?>
                <?php if ($message === 'publish'): ?><a class="ml-1 font-semibold underline" target="_blank" rel="noopener" href="<?= $h(ES::publicUrl($e)) ?>">View public page ↗</a><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $h($errors[$error] ?? $error) ?></div>
        <?php endif; ?>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <section class="ui-card p-5 lg:col-span-2">
                <div class="flex flex-wrap gap-4">
                    <?php if (!empty($e['poster_path'])): ?>
                        <a href="/admin/wellness/events/<?= (int) $e['id'] ?>/poster" target="_blank" rel="noopener"><img src="/admin/wellness/events/<?= (int) $e['id'] ?>/poster" alt="Poster" class="max-h-64 rounded border"></a>
                    <?php endif; ?>
                    <dl class="min-w-[260px] flex-1 space-y-2">
                        <?= $row('Type', $h(ES::KINDS[$e['kind']]) . ' · ' . $h($e['category_slug'])) ?>
                        <?= $row('When', $h(ES::whenText((string) $e['start_at'], (string) $e['end_at']))) ?>
                        <?= $row('Mode', $h(ES::MODES[$e['mode']] ?? $e['mode'])) ?>
                        <?= $row('Venue', $e['mode'] !== 'online' ? $h(trim(($e['venue_name'] ? $e['venue_name'] . ', ' : '') . ($e['address'] ?? '') . ', ' . ($e['city_name'] ?? '') . ($e['state'] ? ', ' . $e['state'] : '') . ($e['pincode'] ? ' ' . $e['pincode'] : ''), ', ')) : null) ?>
                        <?= $row('Map', $e['map_url'] ? '<a class="text-sky-700 underline" target="_blank" rel="noopener noreferrer" href="' . $h($e['map_url']) . '">' . $h($e['map_url']) . '</a>' : null) ?>
                        <?= $row('Online link', $e['online_url'] ? '<a class="text-sky-700 underline" target="_blank" rel="noopener noreferrer" href="' . $h($e['online_url']) . '">' . $h($e['online_url']) . '</a>' : null) ?>
                        <?= $row('Fee', (float) $e['fee_amount'] > 0 ? '₹' . $h(number_format((float) $e['fee_amount'], 2)) . ($e['fee_note'] ? ' — ' . $h($e['fee_note']) : '') : 'Free') ?>
                        <?= $row('How to join', $h(ES::JOIN_MODES[$e['join_mode']] ?? '')) ?>
                        <?= $row('Registration link', $e['register_url'] ? '<a class="text-sky-700 underline" target="_blank" rel="noopener noreferrer" href="' . $h($e['register_url']) . '">' . $h($e['register_url']) . '</a>' : null) ?>
                        <?= $row('Contact', $h(trim(($e['contact_name'] ?? '') . ' ' . ($e['contact_phone'] ?? '')))) ?>
                        <?= $row('For', $h(ES::AUDIENCES[$e['audience']] ?? '')) ?>
                        <?= $row('Languages', $h($e['languages'] ?? '')) ?>
                        <?= $row('Organised by', $h($e['organised_by'] ?? '')) ?>
                    </dl>
                </div>
                <?php if ($e['summary']): ?><p class="mt-4 font-medium text-slate-800"><?= $h($e['summary']) ?></p><?php endif; ?>
                <?php if ($e['description']): ?><div class="mt-2 whitespace-pre-line text-sm text-slate-700"><?= $h($e['description']) ?></div><?php endif; ?>
                <p class="mt-4 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">Check: real organiser and venue · free camp really free (no test / registration fee) · no medical claims (“cures”, “guaranteed”) · dates make sense · links go where they say.</p>
                <?php if ($st === 'published'): ?><p class="mt-3 text-xs text-slate-500"><?= (int) $e['view_count'] ?> views · <?= (int) $e['contact_views'] ?> signed-in visitors saw the contact · <a class="text-sky-700 underline" target="_blank" rel="noopener" href="<?= $h(ES::publicUrl($e)) ?>">public page ↗</a></p><?php endif; ?>
                <?php if ($st === 'cancelled'): ?><p class="mt-3 text-sm text-slate-600">Cancelled by the creator: <?= $h($e['cancel_reason'] ?? '') ?></p><?php endif; ?>
            </section>

            <div class="space-y-4">
                <section class="ui-card p-5">
                    <h2 class="font-semibold">Creator</h2>
                    <p class="mt-1 text-sm"><a href="/admin/wellness/creators/<?= (int) $e['creator_id'] ?>" class="font-medium text-sky-700 hover:underline"><?= $h($e['creator_name']) ?></a><?= $e['verify_status'] === 'verified' ? ' <span class="text-sky-700">✓ verified</span>' : '' ?></p>
                    <p class="text-xs text-slate-500"><?= $h(CS::TYPE_SHORT[$e['creator_type']] ?? '') ?> · <?= $h($e['creator_phone'] ?? '') ?> · <?= $h($e['creator_email'] ?? '') ?></p>
                    <?php if ($e['creator_status'] === 'suspended'): ?><p class="mt-1 text-xs font-semibold text-rose-700">Creator is suspended — nothing of theirs is public.</p><?php endif; ?>
                </section>

                <section class="ui-card p-5">
                    <h2 class="font-semibold">Decision</h2>
                    <?php if ($e['review_note']): ?><p class="mt-1 text-xs text-slate-500">Last note: <?= $h($e['review_note']) ?></p><?php endif; ?>
                    <form method="post" action="/admin/wellness/events/<?= (int) $e['id'] ?>/review" class="mt-2 space-y-2">
                        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                        <textarea name="note" rows="3" maxlength="1000" placeholder="Note to the creator (required except to publish)" class="w-full rounded border px-2 py-1.5 text-sm"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <?php if (in_array($st, ['submitted', 'changes_requested', 'unpublished', 'rejected'], true)): ?>
                                <button name="decision" value="publish" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Publish</button>
                            <?php endif; ?>
                            <?php if (in_array($st, ['submitted', 'published'], true)): ?>
                                <button name="decision" value="changes" class="rounded border bg-white px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Ask for changes</button>
                            <?php endif; ?>
                            <?php if (in_array($st, ['submitted', 'changes_requested'], true)): ?>
                                <button name="decision" value="reject" class="rounded border bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                            <?php endif; ?>
                            <?php if (in_array($st, ['published', 'cancelled'], true)): ?>
                                <button name="decision" value="unpublish" class="rounded border bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50" onclick="return confirm('Hide this listing from the public?')">Unpublish</button>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if ($e['kind'] === 'free_camp'): ?>
                        <p class="mt-2 text-xs text-slate-500">Charges a fee? Reject with: “This charges attendees, so it can’t be a free camp. Please post it as an Event.”</p>
                    <?php endif; ?>
                </section>

                <section class="ui-card p-5">
                    <h2 class="font-semibold">Reports (<?= count($e['reports']) ?>)</h2>
                    <?php if (!$e['reports']): ?><p class="mt-1 text-sm text-slate-500">None.</p><?php endif; ?>
                    <ul class="mt-2 space-y-3">
                        <?php foreach ($e['reports'] as $r): ?>
                            <li class="text-sm">
                                <div><strong><?= $h($reasons[$r['reason']] ?? $r['reason']) ?></strong> <span class="text-xs text-slate-500">· <?= $h(date('d M Y, h:i A', strtotime((string) $r['created_at']))) ?> · <?= $h($r['status']) ?></span></div>
                                <?php if ($r['details']): ?><div class="text-xs text-slate-700"><?= $h($r['details']) ?></div><?php endif; ?>
                                <?php if ($r['status'] === 'open'): ?>
                                    <form method="post" action="/admin/wellness/reports/<?= (int) $r['id'] ?>" class="mt-1 flex gap-2">
                                        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                        <input type="hidden" name="return_to" value="/admin/wellness/events/<?= (int) $e['id'] ?>">
                                        <button name="status" value="resolved" class="rounded border bg-white px-2 py-1 text-xs font-semibold">Resolved</button>
                                        <button name="status" value="dismissed" class="rounded border bg-white px-2 py-1 text-xs">Dismiss</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
