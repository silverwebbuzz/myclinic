<?php /** Shown when doctor has no WordPress link yet. */ ?>
<div class="mx-auto max-w-lg ui-card p-8 text-center">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-violet-100 text-2xl">📝</div>
    <h2 class="text-lg font-semibold text-slate-900">Blog access not enabled</h2>
    <p class="mt-2 text-sm text-slate-600 leading-relaxed">
        Your account does not have WordPress blog access yet. Ask your clinic administrator or contact eClinicPro support to enable it from the admin panel.
    </p>
    <?php if (empty($wpConfigured)): ?>
    <p class="mt-3 text-xs text-amber-700">Note: WordPress integration is not configured on this server.</p>
    <?php endif; ?>
</div>
