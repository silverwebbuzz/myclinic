<?php
/**
 * /company/roles/new and /company/roles/{id} — custom role editor.
 *
 * @var array<string,mixed>|null $role
 * @var list<array<string,mixed>> $permissions  non-clinical catalogue
 * @var list<string> $selected
 * @var array<string,true> $mine  the editor's own permissions
 */
$pageTitle = $role ? 'Edit role' : 'New role';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-9 items-center justify-center whitespace-nowrap rounded-[7px] px-4 text-[13px] font-medium';
$moduleNames = [
    'company' => 'Company', 'users' => 'Users', 'roles' => 'Roles', 'audit' => 'Activity log', 'billing' => 'Plan & billing',
    'structure' => 'Branches & structure', 'employees' => 'Employees', 'screening' => 'Screening', 'booking' => 'Bookings',
    'providers' => 'Clinics & labs', 'reports' => 'Reports', 'hse' => 'Health & safety',
];
$byModule = [];
foreach ($permissions as $p) {
    $byModule[(string) $p['module']][] = $p;
}
ob_start();
?>
<div class="mx-auto max-w-3xl">
    <a href="/company/roles" class="text-tx3 hover:underline">← Roles</a>
    <h1 class="mt-2 text-[20px] font-semibold tracking-[-.015em]"><?= $role ? 'Edit ' . $e($role['name']) : 'New custom role' ?></h1>
    <p class="mt-0.5 text-tx3">Pick what people with this role can do. You can only include things you can do yourself.</p>

    <form method="post" action="/company/roles" class="mt-4 space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="role_id" value="<?= (int) ($role['id'] ?? 0) ?>">
        <section class="grid gap-4 rounded-[10px] border border-ln bg-sf p-5 sm:grid-cols-2">
            <label class="block">
                <span class="text-tx2">Role name</span>
                <input name="name" required minlength="2" maxlength="80" value="<?= $e($name) ?>" class="<?= $input ?>" placeholder="e.g. Site Nurse Coordinator">
            </label>
            <label class="block">
                <span class="text-tx2">Description <span class="text-tx3">(optional)</span></span>
                <input name="description" maxlength="255" value="<?= $e($description) ?>" class="<?= $input ?>">
            </label>
        </section>

        <section class="rounded-[10px] border border-ln bg-sf p-5">
            <h2 class="text-[15px] font-semibold">Permissions</h2>
            <div class="mt-3 grid gap-5 sm:grid-cols-2">
                <?php foreach ($byModule as $module => $items): ?>
                    <fieldset>
                        <legend class="text-[11px] font-semibold uppercase tracking-[.06em] text-tx3"><?= $e($moduleNames[$module] ?? ucfirst($module)) ?></legend>
                        <div class="mt-2 space-y-1.5">
                            <?php foreach ($items as $p): ?>
                                <?php $code = (string) $p['code']; $allowed = isset($mine[$code]); ?>
                                <label class="flex items-start gap-2 <?= $allowed ? '' : 'text-tx3' ?>" <?= $allowed ? '' : 'title="You don\'t have this permission yourself"' ?>>
                                    <input type="checkbox" name="permissions[]" value="<?= $e($code) ?>" class="mt-0.5"
                                        <?= in_array($code, $selected, true) ? 'checked' : '' ?> <?= $allowed ? '' : 'disabled' ?>>
                                    <span><?= $e($p['label']) ?>
                                        <?php if ((int) $p['phase'] > 0): ?><span class="ml-1 whitespace-nowrap rounded px-1 text-[10.5px] text-tx3 ring-1 ring-ln">coming soon</span><?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <p class="mt-4 rounded-lg bg-sf2 px-3 py-2 text-[12.5px] text-tx3">Medical details (diagnoses, test results, notes) are never available to company roles. Only the in-house Company Occupational Physician role can see them, and only with the employee's consent.</p>
        </section>

        <div class="flex gap-2">
            <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Save role</button>
            <a href="/company/roles" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
