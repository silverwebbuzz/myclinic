<?php
/**
 * /admin/store/vendors/{id} — full seller review.
 *
 * @var array<string,mixed> $vendor
 * @var array<string,mixed>|null $owner
 * @var list<array<string,mixed>> $addresses
 * @var array<string,mixed>|null $bank
 * @var list<array<string,mixed>> $documents
 * @var array<string,string> $docTypes
 * @var array{items: list<array<string,mixed>>, complete: bool} $checklist
 * @var list<array<string,mixed>> $audit
 * @var array<string,string> $businessTypes
 * @var string|null $tempPassword
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$vid = (int) $vendor['id'];
$status = (string) $vendor['status'];
$docCls = ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-emerald-100 text-emerald-800',
    'rejected' => 'bg-red-100 text-red-700', 'expired' => 'bg-slate-200 text-slate-600'];
$bankCls = ['pending' => 'bg-amber-100 text-amber-800', 'verified' => 'bg-emerald-100 text-emerald-800',
    'rejected' => 'bg-red-100 text-red-700', 'inactive' => 'bg-slate-200 text-slate-600'];
$actions = [
    'approve' => ['Approve seller', 'bg-emerald-600 hover:bg-emerald-700', ['pending_review', 'rejected', 'suspended'], false],
    'reject' => ['Request changes', 'bg-amber-600 hover:bg-amber-700', ['pending_review'], true],
    'suspend' => ['Suspend', 'bg-red-600 hover:bg-red-700', ['approved'], true],
    'reactivate' => ['Reactivate', 'bg-emerald-600 hover:bg-emerald-700', ['suspended'], false],
    'close' => ['Close account', 'bg-slate-600 hover:bg-slate-700', ['draft', 'pending_review', 'approved', 'rejected', 'suspended'], true],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $e($vendor['display_name']) ?> — Store seller</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <a href="/admin/store/vendors" class="text-sm text-sky-700 hover:underline">← All sellers</a>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <?php if (!empty($vendor['logo_path'])): ?>
                <img src="<?= $e($vendor['logo_path']) ?>" alt="" class="h-14 w-14 rounded-xl border bg-white object-cover">
            <?php endif; ?>
            <div>
                <h1 class="text-xl font-semibold"><?= $e($vendor['display_name']) ?> <?= !empty($vendor['is_featured']) ? '<span class="text-amber-500">★</span>' : '' ?></h1>
                <div class="text-sm text-slate-500">
                    <?= $e(str_replace('_', ' ', $status)) ?> · joined <?= $e(substr((string) $vendor['created_at'], 0, 10)) ?>
                    <?= !empty($vendor['submitted_at']) ? ' · submitted ' . $e(substr((string) $vendor['submitted_at'], 0, 10)) : '' ?>
                    · /store/seller/<?= $e($vendor['slug']) ?>
                </div>
                <?php if (!empty($vendor['status_reason'])): ?>
                    <div class="mt-1 text-sm text-red-700">Reason on file: <?= $e($vendor['status_reason']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <form method="post" action="/admin/store/vendors/<?= $vid ?>/feature">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="rounded border bg-white px-3 py-1.5 text-sm hover:bg-slate-50"><?= !empty($vendor['is_featured']) ? 'Unfeature' : 'Feature on store' ?></button>
        </form>
    </div>

    <?php require __DIR__ . '/_store_flash.php'; ?>

    <?php if (!empty($tempPassword)): ?>
        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Temporary password for <strong><?= $e($owner['email'] ?? '') ?></strong>: <code class="rounded bg-white px-2 py-0.5 text-base"><?= $e($tempPassword) ?></code>
            <span class="block text-xs text-sky-700">Shown once. Share it privately and ask the seller to log in.</span>
        </div>
    <?php endif; ?>

    <!-- Decision -->
    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Decision</h2>
        <?php if ($status === 'pending_review' && !$checklist['complete']): ?>
            <p class="mt-1 text-sm text-amber-700">Heads-up: the seller's checklist is no longer complete (something was removed after submitting).</p>
        <?php endif; ?>
        <form method="post" action="/admin/store/vendors/<?= $vid ?>/status" class="mt-3 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <label class="flex-1 text-sm">
                <span class="text-slate-600">Reason / note to seller <span class="text-slate-400">(required to reject, suspend or close)</span></span>
                <input name="reason" maxlength="500" class="mt-1 w-full rounded border px-3 py-1.5 text-sm">
            </label>
            <?php foreach ($actions as $key => [$label, $cls, $from, $needsReason]): ?>
                <?php if (in_array($status, $from, true)): ?>
                    <button name="action" value="<?= $e($key) ?>" class="rounded px-4 py-2 text-sm font-medium text-white <?= $cls ?>"
                        <?= $key === 'close' ? 'onclick="return confirm(\'Close this seller account permanently?\')"' : '' ?>><?= $e($label) ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </form>
    </section>

    <div class="grid gap-5 lg:grid-cols-2">
        <!-- Business -->
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold">Business</h2>
            <dl class="mt-3 grid grid-cols-3 gap-y-2 text-sm">
                <dt class="text-slate-400">Legal name</dt><dd class="col-span-2"><?= $e($vendor['legal_name'] ?? '—') ?></dd>
                <dt class="text-slate-400">Type</dt><dd class="col-span-2"><?= $e($businessTypes[$vendor['business_type'] ?? ''] ?? '—') ?></dd>
                <dt class="text-slate-400">GSTIN</dt><dd class="col-span-2 font-mono"><?= $e($vendor['gstin'] ?? '— (not GST-registered)') ?></dd>
                <dt class="text-slate-400">PAN</dt><dd class="col-span-2 font-mono"><?= !empty($vendor['pan_last4']) ? '••••••' . $e($vendor['pan_last4']) : '—' ?></dd>
                <dt class="text-slate-400">Contact</dt><dd class="col-span-2"><?= $e($vendor['contact_name']) ?> · <?= $e($vendor['phone']) ?></dd>
                <dt class="text-slate-400">Login email</dt><dd class="col-span-2"><?= $e($vendor['email']) ?></dd>
                <dt class="text-slate-400">Dispatch</dt><dd class="col-span-2"><?= (int) $vendor['handling_days'] ?> days · returns <?= (int) $vendor['default_return_window_days'] ?> days</dd>
                <dt class="text-slate-400">About</dt><dd class="col-span-2 whitespace-pre-line text-slate-600"><?= $e($vendor['description'] ?? '—') ?></dd>
            </dl>
            <form method="post" action="/admin/store/vendors/<?= $vid ?>/reset-password" class="mt-4 border-t pt-3"
                  onsubmit="return confirm('Generate a new temporary password? The seller\'s current password stops working.')">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <button class="text-sm text-sky-700 hover:underline">Reset seller password…</button>
            </form>
        </section>

        <!-- Bank -->
        <section class="rounded-xl border bg-white p-5 shadow-sm" x-data="{ acct: null, err: null }">
            <h2 class="font-semibold">Bank account</h2>
            <?php if ($bank === null): ?>
                <p class="mt-2 text-sm text-slate-400">Not added yet.</p>
            <?php else: ?>
                <dl class="mt-3 grid grid-cols-3 gap-y-2 text-sm">
                    <dt class="text-slate-400">Status</dt><dd class="col-span-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $bankCls[$bank['status']] ?? '' ?>"><?= $e($bank['status']) ?></span></dd>
                    <dt class="text-slate-400">Holder</dt><dd class="col-span-2"><?= $e($bank['holder_name']) ?></dd>
                    <dt class="text-slate-400">Account</dt>
                    <dd class="col-span-2 font-mono">
                        <span x-text="acct || '•••• <?= $e($bank['account_last4']) ?>'"></span>
                        <button type="button" x-show="!acct" class="ml-2 font-sans text-xs text-sky-700 hover:underline"
                                @click="fetch('/admin/store/bank/<?= (int) $bank['id'] ?>/reveal', {method:'POST', headers:{'X-CSRF-Token':'<?= $e($csrf) ?>','Accept':'application/json'}})
                                        .then(r => r.json()).then(d => { acct = d.account || null; err = d.error || null; })">Reveal (logged)</button>
                        <span x-show="err" x-text="err" class="block font-sans text-xs text-red-600"></span>
                    </dd>
                    <dt class="text-slate-400">IFSC</dt><dd class="col-span-2 font-mono"><?= $e($bank['ifsc']) ?> <?= !empty($bank['bank_name']) ? '· ' . $e($bank['bank_name']) : '' ?></dd>
                    <dt class="text-slate-400">UPI</dt><dd class="col-span-2"><?= $e($bank['upi_id'] ?? '—') ?></dd>
                </dl>
                <p class="mt-3 text-xs text-slate-500">Check the holder name and account against the cancelled cheque below before verifying.</p>
                <form method="post" action="/admin/store/bank/<?= (int) $bank['id'] ?>/verify" class="mt-2 flex gap-2">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button name="decision" value="verified" class="rounded bg-emerald-600 px-3 py-1.5 text-sm text-white hover:bg-emerald-700">Mark verified</button>
                    <button name="decision" value="rejected" class="rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">Reject</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <!-- Documents -->
    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">KYC &amp; licence documents</h2>
        <?php if (!$documents): ?>
            <p class="mt-2 text-sm text-slate-400">Nothing uploaded.</p>
        <?php else: ?>
            <div class="mt-3 divide-y text-sm">
                <?php foreach ($documents as $d): ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <div class="font-medium"><?= $e($docTypes[$d['doc_type']] ?? $d['doc_type']) ?><?= !empty($d['doc_number']) ? ' · <span class="font-mono">' . $e($d['doc_number']) . '</span>' : '' ?></div>
                            <div class="text-xs text-slate-500"><?= $e($d['original_name'] ?? '') ?> · <?= $e(substr((string) $d['created_at'], 0, 10)) ?><?= !empty($d['valid_until']) ? ' · valid until ' . $e($d['valid_until']) : '' ?></div>
                            <?php if (!empty($d['reject_reason'])): ?><div class="text-xs text-red-700">Rejected: <?= $e($d['reject_reason']) ?></div><?php endif; ?>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="/admin/store/documents/<?= (int) $d['id'] ?>/file" target="_blank" rel="noopener" class="text-sky-700 hover:underline">Open</a>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $docCls[$d['status']] ?? '' ?>"><?= $e($d['status']) ?></span>
                            <form method="post" action="/admin/store/documents/<?= (int) $d['id'] ?>/review" class="flex items-center gap-1">
                                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                                <input name="reason" placeholder="Reject reason" class="w-36 rounded border px-2 py-1 text-xs">
                                <button name="decision" value="approved" class="rounded bg-emerald-600 px-2 py-1 text-xs text-white">Approve</button>
                                <button name="decision" value="rejected" class="rounded bg-red-600 px-2 py-1 text-xs text-white">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="grid gap-5 lg:grid-cols-2">
        <!-- Addresses -->
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold">Addresses</h2>
            <?php if (!$addresses): ?><p class="mt-2 text-sm text-slate-400">None.</p><?php endif; ?>
            <ul class="mt-3 space-y-3 text-sm">
                <?php foreach ($addresses as $a): ?>
                    <li class="<?= empty($a['is_active']) ? 'opacity-50' : '' ?>">
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium uppercase"><?= $e($a['type']) ?></span>
                        <?= empty($a['is_active']) ? '<span class="text-xs text-slate-400">(removed)</span>' : '' ?>
                        <div class="mt-1"><?= $e($a['contact_name']) ?> · <?= $e($a['phone']) ?></div>
                        <div class="text-slate-600"><?= $e($a['line1']) ?><?= !empty($a['line2']) ? ', ' . $e($a['line2']) : '' ?>, <?= $e($a['city']) ?>, <?= $e($a['state']) ?> – <?= $e($a['pincode']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Audit -->
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold">Activity</h2>
            <ul class="mt-3 max-h-80 space-y-1.5 overflow-y-auto text-xs text-slate-600">
                <?php foreach ($audit as $a): ?>
                    <li><span class="text-slate-400"><?= $e(substr((string) $a['created_at'], 0, 16)) ?></span> · <?= $e($a['actor_type']) ?> · <span class="font-mono"><?= $e($a['action']) ?></span></li>
                <?php endforeach; ?>
                <?php if (!$audit): ?><li class="text-slate-400">No activity yet.</li><?php endif; ?>
            </ul>
        </section>
    </div>
</main>
</body>
</html>
