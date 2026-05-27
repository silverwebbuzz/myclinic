<?php
$title = 'Get listed on eClinicPro';
$clinic = $clinic ?? [];
$latest = $latest ?? null;
$specialties = $specialties ?? [];
$msg = $message ?? null;
$status = $latest['status'] ?? null;
ob_start();
?>

<div class="mx-auto max-w-2xl space-y-6">

    <header>
        <a href="/dashboard" class="text-sm text-slate-500 hover:text-slate-900">← Back to dashboard</a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Get listed on eClinicPro</h1>
        <p class="mt-1 text-sm text-slate-500">
            Show up in patient searches at
            <a href="https://eclinicpro.com/find-a-doctor" target="_blank" class="font-medium text-emerald-700 hover:underline">eclinicpro.com/find-a-doctor</a>
            and start receiving booking requests.
        </p>
    </header>

    <!-- Flash messages -->
    <?php if ($msg === 'submitted'): ?>
    <div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 p-5">
        <h3 class="flex items-center gap-2 font-semibold text-emerald-900">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Request submitted
        </h3>
        <p class="mt-2 text-sm text-emerald-800">
            Our team will review within 1–2 business days. We'll text you on
            <strong><?= htmlspecialchars((string) ($clinic['phone'] ?? '')) ?></strong>
            when your clinic goes live.
        </p>
    </div>
    <?php elseif ($msg === 'failed'): ?>
    <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800">
        Something went wrong. Please try again or contact support.
    </div>
    <?php endif; ?>

    <!-- Status of existing request (if any) -->
    <?php if ($latest && in_array($status, ['pending','phone_verified'], true)): ?>
    <div class="rounded-xl border bg-white p-5 shadow-sm">
        <h3 class="flex items-center gap-2 font-semibold text-slate-900">
            <span class="inline-flex h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
            Application under review
        </h3>
        <p class="mt-2 text-sm text-slate-600">
            We received your application on
            <strong><?= htmlspecialchars(date('M j, Y', strtotime((string) $latest['created_at']))) ?></strong>.
            Our team is reviewing — most decisions land within 1–2 business days.
        </p>
        <p class="mt-2 text-xs text-slate-500">
            Reference #<?= (int) $latest['id'] ?>
        </p>
    </div>
    <?php elseif ($latest && $status === 'rejected'): ?>
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-5">
        <h3 class="font-semibold text-rose-900">Previous application rejected</h3>
        <?php if (!empty($latest['reviewer_notes'])): ?>
        <p class="mt-2 text-sm text-rose-800">
            <strong>Reason:</strong> <?= nl2br(htmlspecialchars((string) $latest['reviewer_notes'])) ?>
        </p>
        <?php endif; ?>
        <p class="mt-2 text-sm text-rose-800">
            You can re-apply below with corrected details.
        </p>
    </div>
    <?php endif; ?>

    <!-- The form. Show even if rejected (so they can re-apply). Hide if a
         pending request exists. -->
    <?php if (!$latest || !in_array($status, ['pending','phone_verified'], true)): ?>

    <!-- What patients will see preview -->
    <section class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Preview</p>
        <p class="mt-1 text-sm text-slate-600">Here's a rough idea of what patients will see. Edit the fields below to refine.</p>
        <div class="mt-3 flex items-start gap-4 rounded-xl border bg-white p-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-lg font-bold text-emerald-700">
                <?= htmlspecialchars(strtoupper(mb_substr($clinic['name'] ?? 'C', 0, 1))) ?>
            </div>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($clinic['name'] ?? 'Your clinic')) ?></div>
                <div class="text-xs text-slate-500" id="preview-location">
                    <?= htmlspecialchars((string) ($clinic['address'] ?? '—')) ?>
                </div>
                <div class="mt-1 text-xs">
                    <span class="rounded bg-emerald-100 px-1.5 py-0.5 font-semibold text-emerald-800">✓ Verified by doctor</span>
                </div>
            </div>
        </div>
    </section>

    <form method="post" action="/onboarding/get-listed" class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Locked: we trust the tenant's own phone/email/clinic name -->
        <div class="rounded-lg bg-slate-50 p-4">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Account details (verified)</h3>
            <dl class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Clinic name</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($clinic['name'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Phone</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($clinic['phone'] ?? '—')) ?></dd>
                </div>
                <?php if (!empty($clinic['email'])): ?>
                <div>
                    <dt class="text-slate-500">Email</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars((string) $clinic['email']) ?></dd>
                </div>
                <?php endif; ?>
            </dl>
            <p class="mt-3 text-xs text-slate-500">
                To change any of these, update them under <a href="/settings?tab=general" class="text-emerald-700 hover:underline">Settings → General</a> first.
            </p>
        </div>

        <!-- Doctor name -->
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Doctor's full name *</span>
            <input type="text" name="full_name" required maxlength="120"
                   value="<?= htmlspecialchars((string) ($clinic['name'] ?? '')) ?>"
                   class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            <span class="mt-1 block text-xs text-slate-500">e.g. Dr. Riya Mehta — appears as the doctor in your public listing.</span>
        </label>

        <!-- City + state -->
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">City *</span>
                <input type="text" name="city" required maxlength="80"
                       placeholder="Mumbai"
                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">State</span>
                <input type="text" name="state" maxlength="80"
                       placeholder="Maharashtra"
                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            </label>
        </div>

        <!-- Specialty -->
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Specialty *</span>
            <select name="specialty" required
                    class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
                <option value="">Choose your primary specialty…</option>
                <?php foreach ($specialties as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>"
                        <?= (($clinic['specialty'] ?? '') === $key) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <span class="mt-1 block text-xs text-slate-500">
                Patients will find you when they search for this specialty.
            </span>
        </label>

        <!-- Optional registration -->
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Registration number <span class="font-normal normal-case text-slate-400">(optional)</span></span>
                <input type="text" name="reg_number" maxlength="60"
                       placeholder="e.g. G-12345"
                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Issued by <span class="font-normal normal-case text-slate-400">(optional)</span></span>
                <input type="text" name="reg_council" maxlength="80"
                       placeholder="Gujarat Medical Council"
                       class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none">
            </label>
        </div>

        <!-- Optional message -->
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Anything we should know? <span class="font-normal normal-case text-slate-400">(optional)</span></span>
            <textarea name="message" rows="2" maxlength="2000"
                      placeholder="e.g. I run two clinics in Ahmedabad — both should appear."
                      class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none"></textarea>
        </label>

        <!-- Trust note -->
        <p class="text-xs text-slate-500">
            🔒 Our team reviews every application within 1–2 business days to keep eClinicPro trusted.
            We may text you at <strong><?= htmlspecialchars((string) ($clinic['phone'] ?? '')) ?></strong> for verification.
        </p>

        <div class="flex items-center justify-between">
            <a href="/dashboard" class="text-sm text-slate-500 hover:text-slate-900">Cancel</a>
            <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                Submit for review
            </button>
        </div>
    </form>

    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
