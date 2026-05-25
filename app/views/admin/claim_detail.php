<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Claim #<?= (int) $claim['id'] ?> · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6">
        <a href="/admin/claims" class="text-sm text-slate-600 hover:text-slate-900">&larr; All claims</a>

        <?php $isClaim = $claim['type'] === 'claim'; $listing = $claim['_listing'] ?? null; ?>

        <div class="mt-3 flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide
                <?= $isClaim ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' ?>">
                <?= $isClaim ? 'Claim existing listing' : 'New listing request' ?>
            </span>
            <span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide
                <?= $claim['status'] === 'phone_verified' ? 'bg-emerald-100 text-emerald-800' :
                   ($claim['status'] === 'approved'      ? 'bg-emerald-100 text-emerald-800' :
                   ($claim['status'] === 'rejected'      ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700')) ?>">
                <?= htmlspecialchars((string) $claim['status']) ?>
            </span>
            <span class="text-xs text-slate-400">
                #<?= (int) $claim['id'] ?> · submitted <?= htmlspecialchars(date('M j Y, H:i', strtotime((string) $claim['created_at']))) ?>
            </span>
        </div>

        <h1 class="mt-2 text-2xl font-semibold"><?= htmlspecialchars((string) $claim['full_name']) ?></h1>
        <p class="text-slate-600"><?= htmlspecialchars((string) ($claim['clinic_name'] ?? '')) ?></p>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <!-- Submitted info -->
            <div class="rounded-xl border bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Submitted details</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex">
                        <dt class="w-32 shrink-0 text-slate-500">Phone</dt>
                        <dd class="font-medium"><?= htmlspecialchars((string) $claim['phone']) ?>
                            <?php if (!empty($claim['phone_verified_at'])): ?>
                                <span class="ml-1 text-xs text-emerald-600">✓ OTP verified</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php if (!empty($claim['email'])): ?>
                    <div class="flex">
                        <dt class="w-32 shrink-0 text-slate-500">Email</dt>
                        <dd><?= htmlspecialchars((string) $claim['email']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="flex">
                        <dt class="w-32 shrink-0 text-slate-500">Specialty</dt>
                        <dd><?= htmlspecialchars((string) ($claim['specialty'] ?? '—')) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="w-32 shrink-0 text-slate-500">Location</dt>
                        <dd>
                            <?= htmlspecialchars((string) ($claim['city'] ?? '')) ?>
                            <?php if (!empty($claim['state'])): ?>, <?= htmlspecialchars((string) $claim['state']) ?><?php endif; ?>
                        </dd>
                    </div>
                    <?php if (!empty($claim['reg_number']) || !empty($claim['reg_council'])): ?>
                    <div class="flex">
                        <dt class="w-32 shrink-0 text-slate-500">Registration</dt>
                        <dd>
                            <?= htmlspecialchars((string) ($claim['reg_number'] ?? '')) ?>
                            <?php if (!empty($claim['reg_council'])): ?>
                                <span class="text-slate-500"> — <?= htmlspecialchars((string) $claim['reg_council']) ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($claim['document_path'])): ?>
                    <div class="flex">
                        <dt class="w-32 shrink-0 text-slate-500">Document</dt>
                        <dd>
                            <a href="<?= htmlspecialchars('https://eclinicpro.com/' . ltrim((string) $claim['document_path'], '/')) ?>"
                               target="_blank" class="text-emerald-700 underline">View attached file</a>
                        </dd>
                    </div>
                    <?php endif; ?>
                </dl>
                <?php if (!empty($claim['message'])): ?>
                    <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        <div class="mb-1 text-xs font-semibold uppercase text-slate-500">Doctor's note</div>
                        <?= nl2br(htmlspecialchars((string) $claim['message'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Directory listing (claims only) -->
            <div class="rounded-xl border bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    <?= $isClaim ? 'Listing being claimed' : 'No existing listing' ?>
                </h2>
                <?php if ($isClaim && $listing): ?>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex"><dt class="w-32 shrink-0 text-slate-500">Name</dt><dd><?= htmlspecialchars((string) $listing['name']) ?></dd></div>
                        <?php if (!empty($listing['doctor_name'])): ?>
                            <div class="flex"><dt class="w-32 shrink-0 text-slate-500">Doctor</dt><dd><?= htmlspecialchars((string) $listing['doctor_name']) ?></dd></div>
                        <?php endif; ?>
                        <div class="flex"><dt class="w-32 shrink-0 text-slate-500">Phone (Google)</dt><dd><?= htmlspecialchars((string) ($listing['phone'] ?? '—')) ?></dd></div>
                        <div class="flex"><dt class="w-32 shrink-0 text-slate-500">Address</dt><dd><?= htmlspecialchars((string) ($listing['address'] ?? '—')) ?></dd></div>
                        <?php if (!empty($listing['website'])): ?>
                            <div class="flex"><dt class="w-32 shrink-0 text-slate-500">Website</dt><dd><a href="<?= htmlspecialchars((string) $listing['website']) ?>" target="_blank" class="text-emerald-700 underline">visit</a></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($listing['gmaps_url'])): ?>
                            <div class="flex"><dt class="w-32 shrink-0 text-slate-500">Google Maps</dt><dd><a href="<?= htmlspecialchars((string) $listing['gmaps_url']) ?>" target="_blank" class="text-emerald-700 underline">open</a></dd></div>
                        <?php endif; ?>
                        <div class="flex"><dt class="w-32 shrink-0 text-slate-500">Reviews</dt><dd>★ <?= htmlspecialchars((string) ($listing['rating'] ?? '0')) ?> (<?= (int) ($listing['reviews'] ?? 0) ?>)</dd></div>
                    </dl>

                    <?php
                    $phoneMatch = !empty($listing['phone']) && trim((string) $listing['phone']) !== '' && (
                        preg_replace('/\D/', '', (string) $listing['phone']) ===
                        preg_replace('/\D/', '', (string) $claim['phone'])
                    );
                    ?>
                    <?php if ($phoneMatch): ?>
                        <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                            ✓ Submitted phone matches the Google listing's phone — strong signal of ownership.
                        </div>
                    <?php else: ?>
                        <div class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            ⚠ Submitted phone differs from the Google listing's phone. Verify ownership before approving.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mt-3 text-sm text-slate-500">
                        This is a fresh request from a doctor not yet in our directory. Approving will create a brand-new clinic + doctor user.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <?php if (in_array($claim['status'], ['pending', 'phone_verified'], true)): ?>
            <div class="mt-6 rounded-xl border bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Decision</h2>
                <form method="post" class="mt-3 space-y-3">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrf) ?>">
                    <input type="hidden" name="claim_id" value="<?= (int) $claim['id'] ?>">
                    <textarea name="notes" rows="2" placeholder="Internal notes (optional, visible only to admins)"
                              class="w-full rounded-lg border px-3 py-2 text-sm"></textarea>
                    <div class="flex gap-2 flex-wrap">
                        <button type="submit" formaction="/admin/claims/approve"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                            ✓ Approve &amp; create account
                        </button>
                        <button type="submit" formaction="/admin/claims/duplicate"
                                class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400">
                            Duplicate of another request
                        </button>
                        <button type="submit" formaction="/admin/claims/reject"
                                class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                            ✕ Reject
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="mt-6 rounded-xl border bg-white p-5">
                <p class="text-sm">
                    Status: <span class="font-semibold"><?= htmlspecialchars((string) $claim['status']) ?></span>
                    <?php if (!empty($claim['reviewed_at'])): ?>
                        on <?= htmlspecialchars(date('M j Y, H:i', strtotime((string) $claim['reviewed_at']))) ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($claim['reviewer_notes'])): ?>
                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        <?= nl2br(htmlspecialchars((string) $claim['reviewer_notes'])) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($claim['created_tenant_id'])): ?>
                    <p class="mt-3 text-sm text-slate-600">
                        Created tenant #<?= (int) $claim['created_tenant_id'] ?> ·
                        user #<?= (int) $claim['created_user_id'] ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
