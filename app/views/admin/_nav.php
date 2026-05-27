<?php
// Compute the pending claims badge if the parent view didn't pass it.
if (!isset($pendingClaimCount)) {
    try {
        $pendingClaimCount = \App\Services\DoctorClaimService::pendingCount();
    } catch (\Throwable $e) {
        $pendingClaimCount = 0;
    }
}
?>
<header class="border-b bg-slate-900 text-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
        <a href="/admin/dashboard" class="font-semibold">ManageClinic Admin</a>
        <nav class="flex gap-4 text-sm">
            <a href="/admin/dashboard" class="hover:underline">Dashboard</a>
            <a href="/admin/clinics" class="hover:underline">Clinics</a>
            <a href="/admin/founding-clinics" class="hover:underline">Founding</a>
            <a href="/admin/feature-flags" class="hover:underline">Flags</a>
            <a href="/admin/claims" class="hover:underline">Claims<?php if (!empty($pendingClaimCount)): ?> <span class="ml-1 inline-flex items-center rounded-full bg-amber-500 px-2 text-[10px] font-semibold text-white"><?= (int) $pendingClaimCount ?></span><?php endif; ?></a>
            <a href="/admin/leads" class="hover:underline">Leads</a>
            <a href="/admin/reviews" class="hover:underline">Reviews</a>
            <form method="post" action="/admin/logout" class="inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <button type="submit" class="hover:underline">Log out</button>
            </form>
        </nav>
    </div>
</header>
