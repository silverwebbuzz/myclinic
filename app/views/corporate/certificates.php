<?php
/**
 * /company/certificates — each person's current fitness certificate (outcome only, with consent).
 *
 * @var string $view
 * @var string $q
 * @var array{rows: list<array<string,mixed>>, total: int, page: int, pages: int, counts: array<string,int>} $result
 * @var \App\Support\Locale\Locale $loc
 */
use App\Services\Corporate\CertificateService;

$pageTitle = 'Fitness certificates';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$tone = ['fit' => 'bg-okb text-ok', 'fit_restricted' => 'bg-wnb text-wn', 'temp_unfit' => 'bg-erb text-er', 'unfit' => 'bg-erb text-er', 'further_assessment' => 'bg-erb text-er'];
$rows = $result['rows'];
ob_start();
?>
<div>
    <h1 class="text-[20px] font-semibold tracking-[-.015em]">Fitness certificates</h1>
    <p class="mt-0.5 text-tx3">Each person's latest certificate from your clinics: fitness outcome, work restrictions and validity. Medical details stay with the doctor. If an employee didn't agree to share, you only see that they attended.</p>
</div>
<div class="mt-4 flex flex-wrap items-center gap-3">
    <nav class="flex flex-wrap gap-1 rounded-[9px] bg-sf2 p-1 ring-1 ring-ln">
        <?php foreach (CertificateService::VIEWS as $k => $label): ?>
            <a href="/company/certificates?view=<?= $k ?>" class="rounded-[7px] px-3 py-1 text-[13px] <?= $view === $k ? 'bg-sf font-medium shadow-sm' : 'text-tx2 hover:text-tx' ?>"><?= $e($label) ?> <span class="text-tx3"><?= (int) ($result['counts'][$k] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" class="flex w-full gap-2 sm:w-auto sm:min-w-0 sm:max-w-sm sm:flex-1">
        <input type="hidden" name="view" value="<?= $e($view) ?>">
        <input name="q" value="<?= $e($q) ?>" placeholder="Name, employee no. or certificate no." class="<?= $input ?>">
        <button class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Search</button>
    </form>
</div>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if ($rows === []): ?>
        <p class="px-5 py-10 text-center text-tx3">Nothing here.</p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($rows as $c): ?>
                <li class="grid gap-x-4 gap-y-1 px-5 py-3 md:grid-cols-[minmax(0,1.2fr)_minmax(0,2fr)_minmax(0,1fr)_auto] md:items-center">
                    <div class="min-w-0">
                        <a href="/company/employees/<?= (int) $c['employment_id'] ?>#certificate" class="font-medium hover:underline"><?= $e($c['full_name']) ?></a>
                        <div class="text-[13px] text-tx3"><?= $e($c['employee_no']) ?><?= $c['employee_status'] === 'terminated' ? ' · left' : '' ?></div>
                    </div>
                    <div class="min-w-0 text-[13px]">
                        <?php if ($c['shared']): ?>
                            <span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $tone[$c['outcome']] ?? 'bg-ntb text-nt' ?>"><?= $e($c['outcome_label']) ?></span>
                            <?php if ($c['restrictions'] !== []): ?><div class="mt-1 text-tx2"><?= $e(implode('; ', $c['restrictions'])) ?></div><?php endif; ?>
                        <?php else: ?>
                            <span class="text-tx2">Attended · outcome not shared by the employee</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-[13px]">
                        <?php if ($c['shared'] && $c['valid_to']): ?>
                            <div class="<?= $c['expired'] ? 'font-medium text-er' : '' ?>"><?= $c['expired'] ? 'Expired ' : 'Valid to ' ?><?= $e($loc->date($c['valid_to'])) ?></div>
                        <?php elseif ($c['shared'] && $c['review_by']): ?>
                            <div class="text-wn">Review by <?= $e($loc->date($c['review_by'])) ?></div>
                        <?php endif; ?>
                        <div class="text-tx3">Examined <?= $e($loc->date($c['exam_date'])) ?></div>
                    </div>
                    <div class="text-[13px] md:text-right">
                        <?php if ($c['shared'] && $c['has_pdf']): ?><a href="/company/certificates/<?= (int) $c['id'] ?>/pdf" target="_blank" class="text-ac hover:underline">PDF</a><?php endif; ?>
                        <div class="text-[12px] text-tx3"><?= $e($c['cert_no']) ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php if ($result['pages'] > 1): ?>
    <div class="mt-3 flex flex-wrap gap-2 text-[13px]">
        <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
            <a href="/company/certificates?view=<?= $e($view) ?>&q=<?= urlencode($q) ?>&page=<?= $p ?>" class="rounded-[7px] px-2.5 py-1 ring-1 <?= $p === $result['page'] ? 'bg-ac text-white ring-ac' : 'bg-sf ring-ln' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
