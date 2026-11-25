<?php
/**
 * /company/screening/library — eClinicPro rule templates the company can adopt.
 *
 * @var list<array<string,mixed>> $templates  + items, adopted_programme_id
 * @var string $industryName
 */
use App\Services\Corporate\ProgrammeService;

$pageTitle = 'Template library';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$reqPill = ['mandatory' => 'bg-erb text-er', 'recommended' => 'bg-sf2 text-tx2 ring-1 ring-ln'];
$countryNames = array_column(\App\Support\Locale\CountryRepo::allCountries(), 'name', 'code');
$hasSample = array_filter($templates, static fn ($t) => (int) $t['is_sample'] === 1) !== [];
ob_start();
?>
<a href="/company/screening/programmes" class="text-[13px] text-tx2 hover:text-tx">&larr; Programmes</a>
<h1 class="mt-2 text-[20px] font-semibold tracking-[-.015em]">Template library</h1>
<p class="mt-0.5 text-tx3">Screening templates prepared by eClinicPro for your countries<?= $industryName !== '' ? ' and ' . $e(strtolower($industryName)) : '' ?>. Adopt the ones that fit; you can then add or remove checks and choose who each applies to.</p>

<div class="mt-4 rounded-[10px] border border-ln bg-sf2 px-4 py-3 text-[13px] text-tx2">
    Templates are guidance. Your company remains responsible for meeting the legal requirements for your workplaces.
    <?php if ($hasSample): ?><span class="font-medium text-wn">Templates marked SAMPLE are placeholders for testing, not legal advice.</span><?php endif; ?>
</div>

<?php if ($templates === []): ?>
    <div class="mt-4 rounded-[10px] border border-ln bg-sf px-5 py-10 text-center">
        <div class="font-medium">No templates for your countries yet</div>
        <p class="mt-1 text-tx3">You can still create your own programme.</p>
        <a href="/company/screening/programmes" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Back to programmes</a>
    </div>
<?php endif; ?>

<div class="mt-4 grid gap-4 xl:grid-cols-2">
    <?php foreach ($templates as $t): ?>
        <section class="flex flex-col rounded-[10px] border border-ln bg-sf" x-data="{ open: false }">
            <div class="p-5">
                <div class="flex flex-wrap items-center gap-2 text-[12px] text-tx3">
                    <span class="rounded bg-sf2 px-1.5 py-0.5 font-medium ring-1 ring-ln"><?= $e($countryNames[$t['country_code']] ?? $t['country_code']) ?></span>
                    <span><?= $e($t['industry_name'] ?? 'Any industry') ?></span>
                    <span>· v<?= (int) $t['version'] ?></span>
                    <?php if ((int) $t['is_sample'] === 1): ?><span class="rounded bg-wnb px-1.5 py-0.5 font-semibold uppercase tracking-wide text-wn">Sample</span><?php endif; ?>
                </div>
                <h2 class="mt-2 text-[16px] font-semibold"><?= $e($t['name']) ?></h2>
                <?php if (!empty($t['audience'])): ?><p class="text-tx2">For: <?= $e($t['audience']) ?></p><?php endif; ?>
                <?php if (!empty($t['description'])): ?><p class="mt-1 text-tx3"><?= $e($t['description']) ?></p><?php endif; ?>
                <?php
                $counts = ['mandatory' => 0, 'recommended' => 0];
                foreach ($t['items'] as $it) {
                    $counts[$it['requirement']]++;
                }
                ?>
                <p class="mt-2 text-[13px] text-tx2"><?= count($t['items']) ?> checks · <?= $counts['mandatory'] ?> mandatory · <?= $counts['recommended'] ?> recommended
                    <button type="button" @click="open = !open" class="ml-1 text-ac hover:underline" x-text="open ? 'Hide' : 'See checks'">See checks</button></p>
            </div>
            <div x-show="open" x-cloak class="border-t border-ln2">
                <?php foreach (ProgrammeService::CATEGORIES as $cat => $catLabel): $rows = array_filter($t['items'], static fn ($it) => $it['category'] === $cat); if ($rows === []) { continue; } ?>
                    <div class="px-5 pt-3 text-[12px] font-medium uppercase tracking-wide text-tx3"><?= $e($catLabel) ?></div>
                    <ul class="px-5 pb-2">
                        <?php foreach ($rows as $it): ?>
                            <li class="flex flex-wrap items-center gap-x-2 gap-y-0.5 py-1">
                                <span class="min-w-0 flex-1"><?= $e($it['exam_name']) ?><?php if ($it['frequency_months']): ?> <span class="text-tx3">· every <?= (int) $it['frequency_months'] ?> months</span><?php endif; ?></span>
                                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium <?= $reqPill[$it['requirement']] ?>"><?= $e(ProgrammeService::REQUIREMENTS[$it['requirement']]) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
                <?php if (!empty($t['source_note'])): ?><p class="border-t border-ln2 px-5 py-2 text-[12px] text-tx3">Source: <?= $e($t['source_note']) ?></p><?php endif; ?>
            </div>
            <div class="mt-auto flex items-center gap-2 border-t border-ln2 px-5 py-3">
                <?php if ((int) $t['adopted_programme_id'] > 0): ?>
                    <span class="text-[13px] text-ok">✓ In use</span>
                    <a href="/company/screening/programmes/<?= (int) $t['adopted_programme_id'] ?>" class="<?= $btn ?> ml-auto border border-ln hover:bg-sf2">Open programme</a>
                <?php else: ?>
                    <form method="post" action="/company/screening/library/<?= (int) $t['id'] ?>/adopt" class="ml-auto">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Adopt</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
