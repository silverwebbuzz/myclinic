<?php
/**
 * Settings → "Listed on eClinicPro".
 *
 * Shows the clinic's public-directory status and, once approved, lets the
 * doctor edit their public profile fields (directory_doctors). Edits go live
 * immediately — an approved clinic owns its verified listing.
 *
 * Requires: $listingStatus (from DoctorClaimService::listingStatus),
 *           $listing (the directory_doctors row or null), $csrf.
 */
$state  = $listingStatus['state'] ?? 'none';
$reason = $listingStatus['reason'] ?? null;
$row    = $listing ?? null;
?>
<div class="ui-card ui-card-pad">

    <?php if ($state === 'none'): ?>
        <p class="ui-section-sub">Your clinic isn't listed on the public directory yet.</p>
        <p class="mt-1 text-sm text-slate-600">Submit your details once and our team reviews within 1–2 business days. Patients searching on eclinicpro.com/find-a-doctor can then find you.</p>
        <a href="/onboarding/get-listed" class="ui-btn ui-btn-primary ui-btn-sm mt-3 inline-flex">Get listed — takes 1 minute</a>

    <?php elseif ($state === 'pending'): ?>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Under review</span>
            <?php if (!empty($listingStatus['submitted_at'])): ?>
            <span class="text-xs text-slate-500">Submitted <?= htmlspecialchars(date('d M Y', strtotime((string) $listingStatus['submitted_at']))) ?></span>
            <?php endif; ?>
        </div>
        <p class="mt-2 text-sm text-slate-600">Your listing request is being reviewed. We'll email you once it's approved — usually within 1–2 business days.</p>

    <?php elseif ($state === 'rejected'): ?>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">Not approved</span>
        </div>
        <p class="mt-2 text-sm text-slate-600">Your last listing request wasn't approved.</p>
        <?php if (!empty($reason)): ?>
        <p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800"><span class="font-semibold">Reason:</span> <?= htmlspecialchars((string) $reason) ?></p>
        <?php endif; ?>
        <a href="/onboarding/get-listed" class="ui-btn ui-btn-primary ui-btn-sm mt-3 inline-flex">Review &amp; re-apply</a>

    <?php elseif ($state === 'approved' && $row === null): ?>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Listed</span>
        </div>
        <p class="mt-2 text-sm text-slate-600">Your clinic is listed on the public directory. The editable public profile will appear here shortly.</p>

    <?php else: /* approved + has a directory row → editable */ ?>
        <div class="flex items-center justify-between gap-2">
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Listed &amp; live</span>
            <?php if (!empty($row['listing_slug']) || !empty($row['id'])): ?>
            <a href="https://eclinicpro.com/find-a-doctor" target="_blank" class="text-xs font-medium text-emerald-700 hover:underline">View public page →</a>
            <?php endif; ?>
        </div>
        <p class="mt-2 text-sm text-slate-600">Changes you save here go live on your public profile immediately.</p>

        <form method="post" action="/settings/listing" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div>
                <label class="ui-label mb-1 block">Doctor name (shown publicly)</label>
                <input type="text" name="doctor_name" maxlength="160" class="ui-input"
                       value="<?= htmlspecialchars((string) ($row['doctor_name'] ?? '')) ?>"
                       placeholder="e.g. Dr. Mitesh Prajapati">
            </div>

            <div>
                <label class="ui-label mb-1 block">About / bio</label>
                <textarea name="bio" rows="4" maxlength="2000" class="ui-input"
                          placeholder="Tell patients about your practice, experience, and approach."><?= htmlspecialchars((string) ($row['bio'] ?? '')) ?></textarea>
            </div>

            <div>
                <label class="ui-label mb-1 block">Address</label>
                <textarea name="address" rows="2" maxlength="500" class="ui-input"><?= htmlspecialchars((string) ($row['address'] ?? '')) ?></textarea>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="ui-label mb-1 block">Area / locality</label>
                    <input type="text" name="area" maxlength="120" class="ui-input"
                           value="<?= htmlspecialchars((string) ($row['area'] ?? '')) ?>">
                </div>
                <div>
                    <label class="ui-label mb-1 block">Consultation fee (<?= htmlspecialchars((string) ($row['consultation_fee_currency'] ?? 'INR')) ?>)</label>
                    <input type="number" name="consultation_fee" min="0" step="1" class="ui-input"
                           value="<?= htmlspecialchars((string) ($row['consultation_fee'] ?? '')) ?>"
                           placeholder="e.g. 500">
                </div>
            </div>

            <div>
                <label class="ui-label mb-1 block">Website</label>
                <input type="url" name="website" maxlength="500" class="ui-input"
                       value="<?= htmlspecialchars((string) ($row['website'] ?? '')) ?>"
                       placeholder="https://">
            </div>

            <div class="pt-1">
                <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm">Save public profile</button>
            </div>
        </form>
    <?php endif; ?>

</div>
