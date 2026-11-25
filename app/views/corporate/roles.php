<?php
/**
 * /company/roles — built-in and custom roles with their permissions.
 *
 * @var list<array<string,mixed>> $roles  + permissions, user_count
 */
$pageTitle = 'Roles';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$canEdit = \App\Services\Corporate\CorpAuth::can('roles.manage');
$labels = [];
foreach (\App\Services\Corporate\RoleService::allPermissions() as $p) {
    $labels[(string) $p['code']] = [(string) $p['label'], (int) $p['phase'], (int) $p['is_clinical']];
}
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Roles</h1>
        <p class="mt-0.5 text-tx3">What each role can see and do. Company roles never include employees' medical details.</p>
    </div>
    <div class="flex gap-2">
        <a href="/company/users" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Back to users</a>
        <?php if ($canEdit): ?><a href="/company/roles/new" class="<?= $btn ?> bg-ac text-white hover:opacity-90">New custom role</a><?php endif; ?>
    </div>
</div>

<div class="mt-4 grid gap-4 md:grid-cols-2">
    <?php foreach ($roles as $r): ?>
        <section class="flex flex-col rounded-[10px] border border-ln bg-sf p-5" x-data="{ more: false }">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-[15px] font-semibold"><?= $e($r['name']) ?></h2>
                    <?php if (!empty($r['description'])): ?><p class="mt-0.5 text-tx3"><?= $e($r['description']) ?></p><?php endif; ?>
                </div>
                <span class="flex-none rounded-full px-2 py-0.5 text-[11px] font-medium <?= (int) $r['is_system'] === 1 ? 'bg-ntb text-nt' : 'bg-inb text-in' ?>">
                    <?= (int) $r['is_system'] === 1 ? 'Built-in' : 'Custom' ?>
                </span>
            </div>
            <div class="mt-2 text-[12px] text-tx3"><?= (int) $r['user_count'] ?> user<?= (int) $r['user_count'] === 1 ? '' : 's' ?>
                <?= $r['scope_type'] === 'branch' ? ' · limited to one branch (available once branches are added)' : '' ?></div>
            <?php $list = $r['permissions']; $shown = array_slice($list, 0, 6); ?>
            <ul class="mt-3 space-y-1 text-[12.5px]">
                <?php foreach ($list as $i => $code): ?>
                    <?php [$label, $phase, $clinical] = $labels[$code] ?? [$code, 0, 0]; ?>
                    <li class="flex items-start gap-2" <?= $i >= 6 ? 'x-show="more" x-cloak' : '' ?>>
                        <span class="mt-[5px] h-1.5 w-1.5 flex-none rounded-full <?= $clinical ? 'bg-rv' : 'bg-ac' ?>"></span>
                        <span><?= $e($label) ?>
                            <?php if ($phase > 0): ?><span class="ml-1 whitespace-nowrap rounded px-1 text-[10.5px] text-tx3 ring-1 ring-ln">coming soon</span><?php endif; ?>
                            <?php if ($clinical): ?><span class="ml-1 rounded bg-rvb px-1 text-[10.5px] text-rv">clinical</span><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($list) > 6): ?>
                <button type="button" @click="more = !more" class="mt-2 self-start text-[12.5px] font-medium text-act hover:underline"
                        x-text="more ? 'Show less' : 'Show all <?= count($list) ?>'">Show all <?= count($list) ?></button>
            <?php endif; ?>
            <?php if ($canEdit && (int) $r['is_system'] === 0): ?>
                <div class="mt-4 flex gap-2 border-t border-ln2 pt-3">
                    <a href="/company/roles/<?= (int) $r['id'] ?>" class="<?= $btn ?> border border-ln hover:bg-sf2">Edit</a>
                    <form method="post" action="/company/roles/<?= (int) $r['id'] ?>/delete" onsubmit="return confirm('Delete this role?');">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <button class="<?= $btn ?> border border-ln text-er hover:bg-erb">Delete</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
