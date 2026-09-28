<?php
/**
 * @var array<string,mixed> $vendor
 * @var array{items: list<array{key:string,label:string,done:bool,href:string}>, complete: bool} $checklist
 */
$pageTitle = 'Dashboard';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$status = (string) $vendor['status'];
$doneCount = count(array_filter($checklist['items'], static fn ($i) => $i['done']));
$total = count($checklist['items']);
ob_start();
?>
<?php if (!empty($welcome)): ?>
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        Welcome aboard! Complete the steps below and submit your account for review. Once approved you can start listing products.
    </div>
<?php endif; ?>

<h1 class="text-2xl font-semibold">Hello, <?= $e(explode(' ', (string) ($vendorUser['name'] ?? ''))[0] ?: 'there') ?></h1>

<?php if ($status === 'approved'): ?>
    <div class="mt-4 rounded-2xl border border-[#d5e6da] bg-[#edf5ef] p-6">
        <h2 class="text-lg font-semibold text-[#0e4d34]">Your store is approved 🎉</h2>
        <p class="mt-1 text-sm text-slate-600">Add your products, set prices and stock from the <strong>Products</strong> menu. Each product is checked by our team before it goes live.</p>
        <a href="/vendor/products/new" class="mt-3 inline-block rounded-full bg-[#0e4d34] px-5 py-2 text-sm font-medium text-white hover:bg-[#17774f]">+ Add a product</a>
        <?php if (!empty($vendor['slug'])): ?>
            <p class="mt-3 text-sm text-slate-600">Your public store page will be: <code class="rounded bg-white px-1.5 py-0.5">eclinicpro.com/store/seller/<?= $e($vendor['slug']) ?></code></p>
        <?php endif; ?>
    </div>
<?php elseif ($status === 'pending_review'): ?>
    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-6">
        <h2 class="text-lg font-semibold text-amber-900">Your account is under review</h2>
        <p class="mt-1 text-sm text-amber-900/80">Submitted <?= $e(substr((string) ($vendor['submitted_at'] ?? ''), 0, 10)) ?>. We usually respond within 2 working days. Business and tax details are locked during review; addresses, bank and documents can still be updated.</p>
    </div>
<?php endif; ?>

<?php if (in_array($status, ['draft', 'rejected', 'pending_review'], true)): ?>
<section class="mt-6 rounded-2xl border border-[#ece8df] bg-white p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Account setup</h2>
        <span class="text-sm text-slate-500"><?= $doneCount ?> of <?= $total ?> done</span>
    </div>
    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-[#17774f]" style="width: <?= $total ? (int) round($doneCount / $total * 100) : 0 ?>%"></div>
    </div>
    <ul class="mt-5 divide-y divide-slate-100">
        <?php foreach ($checklist['items'] as $item): ?>
            <li class="flex items-center justify-between gap-3 py-3">
                <span class="flex items-center gap-3 text-sm">
                    <?php if ($item['done']): ?>
                        <span class="grid h-6 w-6 place-items-center rounded-full bg-[#17774f] text-white"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                    <?php else: ?>
                        <span class="h-6 w-6 rounded-full border-2 border-slate-300"></span>
                    <?php endif; ?>
                    <span class="<?= $item['done'] ? 'text-slate-500' : 'font-medium' ?>"><?= $e($item['label']) ?></span>
                </span>
                <a href="<?= $e($item['href']) ?>" class="shrink-0 text-sm font-medium text-[#17774f] hover:underline"><?= $item['done'] ? 'Edit' : 'Start' ?> →</a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (in_array($status, ['draft', 'rejected'], true)): ?>
        <form method="post" action="/vendor/submit" class="mt-5 border-t border-slate-100 pt-5">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button <?= $checklist['complete'] ? '' : 'disabled' ?>
                    class="rounded-full bg-[#0e4d34] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#17774f] disabled:cursor-not-allowed disabled:bg-slate-300">
                <?= $status === 'rejected' ? 'Resubmit for review' : 'Submit for review' ?>
            </button>
            <?php if (!$checklist['complete']): ?>
                <span class="ml-3 text-sm text-slate-500">Complete all steps to submit.</span>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="mt-6 rounded-2xl border border-[#ece8df] bg-white p-6 text-sm text-slate-600">
    <h2 class="text-base font-semibold text-[#13294b]">What you can sell</h2>
    <p class="mt-2">Health, wellness and personal-care products across 21 departments. Some categories need a licence on file before you can list in them:
        <strong>FSSAI</strong> for supplements and foods, <strong>AYUSH</strong> for Ayurvedic products, and medical-device registration for devices such as BP monitors.
        Upload these under <a href="/vendor/documents" class="text-[#17774f] underline">Documents</a>. Prescription and other drug-licensed products are not sold on the store.</p>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
