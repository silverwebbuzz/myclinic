<?php
/** @var array<string,mixed> $partner */
$pStatus = $partner['status'] ?? 'pending';
$statusColors = [
    'pending'   => 'bg-amber-500',
    'active'    => 'bg-emerald-500',
    'suspended' => 'bg-slate-500',
    'rejected'  => 'bg-red-500',
];
?>
<header class="border-b bg-slate-900 text-white">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-3">
        <a href="/partner/dashboard" class="font-semibold">eClinicPro Partners</a>
        <nav class="flex items-center gap-4 text-sm">
            <a href="/partner/dashboard" class="hover:underline">Dashboard</a>
            <a href="/partner/referrals" class="hover:underline">Referrals</a>
            <a href="/partner/earnings" class="hover:underline">Earnings</a>
            <a href="/partner/documents" class="hover:underline">Documents</a>
            <span class="inline-flex items-center gap-1 rounded-full <?= $statusColors[$pStatus] ?? 'bg-slate-500' ?> px-2 py-0.5 text-[10px] font-semibold uppercase">
                <?= htmlspecialchars($pStatus) ?>
            </span>
            <form method="post" action="/partner/logout" class="inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? \App\Services\CsrfService::token()) ?>">
                <button type="submit" class="hover:underline">Log out</button>
            </form>
        </nav>
    </div>
</header>
<?php if (($partner['status'] ?? '') === 'pending'): ?>
<div class="bg-amber-50 text-amber-800">
    <div class="mx-auto max-w-5xl px-6 py-2 text-sm">
        Your account is pending approval. Upload your KYC <a href="/partner/documents" class="font-semibold underline">documents</a> to speed it up.
    </div>
</div>
<?php endif; ?>
