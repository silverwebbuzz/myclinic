<?php
/**
 * /vendor/terms — seller rules & terms (admin-editable) + accept the current version.
 *
 * @var array{title:string, body:string, version:int, updated_at:?string} $page
 * @var string $html
 * @var int $acceptedVersion
 */
$pageTitle = 'Rules & terms';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$current = $acceptedVersion >= (int) $page['version'];
ob_start();
?>
<style>
    .policy h2 { font-size: 1.1rem; font-weight: 600; margin: 1.4rem 0 .5rem; color: #0e4d34; }
    .policy h3 { font-weight: 600; margin: 1rem 0 .4rem; }
    .policy p { margin: .5rem 0; }
    .policy ul { list-style: disc; padding-left: 1.25rem; margin: .5rem 0; }
    .policy ol { list-style: decimal; padding-left: 1.25rem; margin: .5rem 0; }
    .policy li { margin: .3rem 0; }
</style>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold"><?= $e($page['title']) ?></h1>
        <p class="mt-1 text-sm text-slate-500">Version <?= (int) $page['version'] ?><?= $page['updated_at'] ? ', updated ' . $e(date('j M Y', (int) strtotime((string) $page['updated_at']))) : '' ?></p>
    </div>
    <?php if ($current): ?>
        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">✓ You have accepted this version</span>
    <?php endif; ?>
</div>

<article class="policy mt-4 rounded-2xl border border-[#ece8df] bg-white p-6 text-sm leading-6 text-slate-800"><?= $html ?></article>

<?php if (!$current): ?>
    <form method="post" action="/vendor/terms/accept" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="version" value="<?= (int) $page['version'] ?>">
        <p class="font-semibold text-amber-900"><?= $acceptedVersion > 0 ? 'These rules have been updated. Please read and accept the new version.' : 'Please read and accept these rules to sell on eClinicPro Store.' ?></p>
        <label class="mt-3 flex items-start gap-2">
            <input type="checkbox" name="agree" value="1" required class="mt-1">
            <span>I have read the seller rules &amp; terms (version <?= (int) $page['version'] ?>) and agree to follow them on behalf of <strong><?= $e($vendor['legal_name'] ?: $vendor['display_name']) ?></strong>.</span>
        </label>
        <button class="mt-3 rounded-full bg-[#0e4d34] px-5 py-2 font-medium text-white hover:bg-[#17774f]">Accept</button>
    </form>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
