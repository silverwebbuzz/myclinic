<?php
/**
 * /company/screening/programmes — the company's screening programmes.
 *
 * @var list<array<string,mixed>> $programmes  + item_count, mandatory_removed, assignments, covered
 * @var bool $showArchived
 * @var bool $canCustomise
 */
$pageTitle = 'Screening programmes';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Screening programmes</h1>
        <p class="mt-0.5 text-tx3">What checks your people need and how often. Each programme applies to the job roles, departments, branches or people you choose.</p>
    </div>
    <?php if ($canCustomise): ?>
        <div class="flex flex-wrap gap-2" x-data="{ own: false }">
            <button type="button" @click="own = !own" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Create your own</button>
            <a href="/company/screening/library" class="<?= $btn ?> bg-ac text-white hover:opacity-90">Browse templates</a>
            <form x-show="own" x-cloak method="post" action="/company/screening/programmes" class="flex w-full gap-2">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input name="name" required maxlength="150" placeholder="Programme name, e.g. Food handlers" class="min-w-0 flex-1 rounded-[7px] border border-ln bg-sf px-3 py-1.5">
                <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Create</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if ($programmes === []): ?>
        <div class="px-5 py-10 text-center">
            <div class="font-medium"><?= $showArchived ? 'No programmes' : 'No programmes yet' ?></div>
            <?php if ($canCustomise && !$showArchived): ?>
                <p class="mt-1 text-tx3">Start from an eClinicPro template for your country and industry.</p>
                <a href="/company/screening/library" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Browse templates</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($programmes as $p): ?>
                <li>
                    <a href="/company/screening/programmes/<?= (int) $p['id'] ?>" class="grid gap-x-4 gap-y-1 px-5 py-3 hover:bg-sf2 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_auto] md:items-center <?= $p['status'] === 'archived' ? 'opacity-60' : '' ?>">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 font-medium"><?= $e($p['name']) ?>
                                <?php if ((int) ($p['is_sample'] ?? 0) === 1): ?><span class="rounded bg-wnb px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-wn">Sample</span><?php endif; ?>
                                <?php if ($p['status'] === 'archived'): ?><span class="text-[12px] font-normal text-tx3">archived</span><?php endif; ?>
                            </div>
                            <div class="text-[13px] text-tx3"><?= (int) $p['item_count'] ?> checks<?= (int) $p['template_id'] > 0 ? ' · from eClinicPro template v' . (int) $p['template_version'] : ' · your own' ?>
                                <?php if ((int) $p['mandatory_removed'] > 0): ?><span class="text-er"> · <?= (int) $p['mandatory_removed'] ?> mandatory removed</span><?php endif; ?></div>
                        </div>
                        <div class="min-w-0 truncate text-[13px] text-tx2">
                            <?= $p['assignments'] === [] ? '<span class="text-wn">Not applied to anyone yet</span>' : $e(implode(', ', array_column($p['assignments'], 'label'))) ?>
                        </div>
                        <div class="text-[13px] text-tx2 md:text-right"><?= number_format((int) $p['covered']) ?> people</div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<p class="mt-3 text-[13px]"><a href="/company/screening/programmes<?= $showArchived ? '' : '?archived=1' ?>" class="text-tx2 hover:text-tx"><?= $showArchived ? 'Hide archived' : 'Show archived' ?></a></p>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
