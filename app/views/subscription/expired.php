<?php
$title = 'Plan expired — eClinicPro';
$isTrial = ($reason ?? 'plan') === 'trial';
ob_start();
?>
<div class="mx-auto max-w-lg" x-data="{ csrf: '<?= htmlspecialchars($csrf ?? '') ?>' }">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">

        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>

        <h1 class="text-xl font-bold text-slate-900">
            <?= $isTrial ? 'Your free trial has ended' : 'Your plan has expired' ?>
        </h1>
        <p class="mx-auto mt-2 max-w-sm text-sm text-slate-600">
            <?= $isTrial
                ? 'Thanks for trying eClinicPro. To keep using your dashboard, appointments and patient records, choose a plan to continue.'
                : 'Your subscription has expired. Renew now to restore access to your dashboard, appointments and patient records.' ?>
            <?php if (!empty($endsOn)): ?>
            <span class="mt-1 block text-xs text-slate-400">
                <?= $isTrial ? 'Trial ended' : 'Plan expired' ?> on <?= htmlspecialchars(date('d M Y', strtotime((string) $endsOn))) ?>.
            </span>
            <?php endif; ?>
        </p>

        <!-- Primary action: renew / choose a plan via the existing Cashfree checkout. -->
        <a href="/settings?tab=subscription"
           class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
            <?= $isTrial ? 'Choose a plan to continue' : 'Renew your plan' ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>

        <p class="mt-4 text-xs text-slate-500">
            Questions about billing? Email
            <a href="mailto:wecare@eclinicpro.com" class="font-medium text-emerald-700 hover:underline">wecare@eclinicpro.com</a>
        </p>

        <form method="post" action="/logout" class="mt-6 border-t border-slate-100 pt-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <button type="submit" class="text-xs text-slate-400 hover:text-slate-600">Sign out</button>
        </form>
    </div>
</div>
<?php
$innerContent = ob_get_clean();
require __DIR__ . '/../onboarding/_layout.php';
