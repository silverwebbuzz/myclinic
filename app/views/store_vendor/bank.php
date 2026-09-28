<?php
/**
 * @var array<string,mixed>|null $bank
 * @var bool $cryptoReady
 */
$pageTitle = 'Bank account';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#17774f] focus:outline-none';
$bankStatus = [
    'pending' => ['Awaiting verification', 'bg-amber-100 text-amber-800'],
    'verified' => ['Verified', 'bg-emerald-100 text-emerald-800'],
    'rejected' => ['Rejected: please re-enter', 'bg-red-100 text-red-700'],
];
ob_start();
?>
<h1 class="text-2xl font-semibold">Bank account</h1>
<p class="mt-1 text-sm text-slate-500">Your sales payouts are sent here. The account must be in your business's (or proprietor's) name.</p>

<?php if ($bank !== null): ?>
    <?php [$label, $cls] = $bankStatus[$bank['status']] ?? [$bank['status'], 'bg-slate-100 text-slate-700']; ?>
    <div class="mt-5 rounded-2xl border border-[#ece8df] bg-white p-5 text-sm">
        <div class="flex items-center justify-between">
            <span class="font-semibold">Current account</span>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $cls ?>"><?= $e($label) ?></span>
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-y-2 text-slate-600 sm:grid-cols-4">
            <dt class="text-slate-400">Holder</dt><dd><?= $e($bank['holder_name']) ?></dd>
            <dt class="text-slate-400">Account</dt><dd>•••• <?= $e($bank['account_last4']) ?></dd>
            <dt class="text-slate-400">IFSC</dt><dd><?= $e($bank['ifsc']) ?></dd>
            <dt class="text-slate-400">UPI</dt><dd><?= $e($bank['upi_id'] ?? '—') ?></dd>
        </dl>
    </div>
<?php endif; ?>

<?php if (!$cryptoReady): ?>
    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Secure storage isn't configured on the server yet, so bank details can't be saved right now. Please try again later or contact support.
    </div>
<?php else: ?>
<form method="post" action="/vendor/bank" class="mt-5 rounded-2xl border border-[#ece8df] bg-white p-6" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <h2 class="font-semibold"><?= $bank ? 'Replace bank account' : 'Add bank account' ?></h2>
    <?php if ($bank): ?><p class="mt-1 text-xs text-slate-500">Changing your account resets it to "awaiting verification".</p><?php endif; ?>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="block text-sm sm:col-span-2">
            <span class="text-slate-600">Account holder name</span>
            <input name="holder_name" required maxlength="160" class="<?= $input ?>">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Account number</span>
            <input name="account_no" required inputmode="numeric" maxlength="20" class="<?= $input ?>">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Confirm account number</span>
            <input name="account_no_confirm" required inputmode="numeric" maxlength="20" class="<?= $input ?>" onpaste="return false">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">IFSC</span>
            <input name="ifsc" required maxlength="11" class="<?= $input ?> uppercase" placeholder="HDFC0001234">
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Bank name <span class="text-slate-400">(optional)</span></span>
            <input name="bank_name" maxlength="120" class="<?= $input ?>">
        </label>
        <label class="block text-sm sm:col-span-2">
            <span class="text-slate-600">UPI ID <span class="text-slate-400">(optional)</span></span>
            <input name="upi_id" maxlength="100" class="<?= $input ?>" placeholder="business@bank">
        </label>
    </div>
    <p class="mt-3 text-xs text-slate-400">Stored encrypted. We only ever show the last 4 digits.</p>
    <button class="mt-4 rounded-full bg-[#0e4d34] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#17774f]">Save bank account</button>
</form>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
