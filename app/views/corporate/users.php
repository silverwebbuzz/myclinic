<?php
/**
 * /company/users — logins, roles, invitations.
 *
 * @var list<array<string,mixed>> $users     + roles: list<{id,name}>
 * @var list<array<string,mixed>> $invites
 * @var list<array<string,mixed>> $assignable roles the signed-in user may give
 * @var bool $canManage
 * @var \App\Support\Locale\Locale $loc
 * @var array<string,mixed> $old  invite form after an error
 */
$pageTitle = 'Users & roles';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$me = (int) ($user['id'] ?? 0);
$assignableIds = array_map(static fn ($r) => (int) $r['id'], $assignable);
$branchRoleIds = array_values(array_map(static fn ($r) => (int) $r['id'], array_filter($assignable, static fn ($r) => ($r['scope_type'] ?? 'company') === 'branch')));
$branches = $branches ?? [];
$branchSelect = static function (string $name, int $selected, string $extra = '') use ($branches, $e): string {
    $html = '<select name="' . $e($name) . '" ' . $extra . ' class="mt-1 w-full rounded-[7px] border border-ln bg-sf px-2 py-1.5 text-[13px]"><option value="">Which branch?</option>';
    foreach ($branches as $b) {
        $html .= '<option value="' . (int) $b['id'] . '"' . ((int) $b['id'] === $selected ? ' selected' : '') . '>' . $e($b['name']) . '</option>';
    }

    return $html . '</select>';
};
$nowUtc = \App\Support\Locale\LocalDate::nowUtc();
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Users &amp; roles</h1>
        <p class="mt-0.5 text-tx3">People in your company who can sign in to this portal. Employees don't need a login here.</p>
    </div>
    <a href="/company/roles" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">What each role can do</a>
</div>

<?php if ($canManage): ?>
    <section class="mt-4 rounded-[10px] border border-ln bg-sf p-5" x-data="{ open: <?= !empty($old) ? 'true' : 'false' ?>, role: '<?= (int) ($old['role_id'] ?? 0) ?: '' ?>', branchRoles: <?= $e(json_encode($branchRoleIds)) ?> }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-semibold">Invite someone</h2>
                <p class="mt-0.5 text-tx3">They'll get an email to choose a password and set up two-step sign-in.</p>
            </div>
            <button type="button" @click="open = !open" class="<?= $btn ?> bg-ac text-white hover:opacity-90" x-text="open ? 'Close' : 'Invite user'">Invite user</button>
        </div>
        <form method="post" action="/company/users/invite" x-show="open" x-cloak class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <label class="block">
                <span class="text-tx2">Work email</span>
                <input name="email" type="email" required maxlength="190" value="<?= $e($old['email'] ?? '') ?>" class="<?= $input ?>">
            </label>
            <label class="block">
                <span class="text-tx2">Name <span class="text-tx3">(optional)</span></span>
                <input name="name" maxlength="160" value="<?= $e($old['name'] ?? '') ?>" class="<?= $input ?>">
            </label>
            <label class="block">
                <span class="text-tx2">Role</span>
                <select name="role_id" required x-model="role" class="<?= $input ?>">
                    <option value="">Select…</option>
                    <?php foreach ($assignable as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) ($old['role_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= $e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="<?= $btn ?> h-[38px] bg-ac px-4 text-white hover:opacity-90">Send invite</button>
            <div x-show="branchRoles.includes(Number(role))" x-cloak class="sm:col-span-4">
                <?php if ($branches === []): ?>
                    <p class="rounded-[7px] bg-wnb px-3 py-2 text-wn">Add a branch first (Workforce → Branches), then you can invite a Branch Manager.</p>
                <?php else: ?>
                    <label class="block max-w-sm"><span class="text-tx2">Branch they look after</span><?= $branchSelect('branch_id', (int) ($old['branch_id'] ?? 0)) ?></label>
                <?php endif; ?>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <div class="border-b border-ln px-5 py-3 font-semibold"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?></div>
    <ul class="divide-y divide-ln2">
        <?php foreach ($users as $u): ?>
            <?php
            $isMe = (int) $u['id'] === $me;
            $disabled = $u['status'] === 'disabled';
            $userRoleIds = array_map(static fn ($r) => (int) $r['id'], $u['roles']);
            $scopeByRole = array_column($u['roles'], 'scope_id', 'id');
            ?>
            <li class="px-5 py-3.5" x-data="{ edit: false }">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                        <div class="flex flex-wrap items-center gap-1.5 font-medium <?= $disabled ? 'text-tx3' : '' ?>">
                            <?= $e($u['name']) ?>
                            <?php if ($isMe): ?><span class="rounded-full bg-inb px-2 py-0.5 text-[11px] font-medium text-in">You</span><?php endif; ?>
                            <?php if ((int) $u['is_owner'] === 1): ?><span class="rounded-full bg-ntb px-2 py-0.5 text-[11px] font-medium text-nt">Owner</span><?php endif; ?>
                            <?php if ($disabled): ?><span class="rounded-full bg-erb px-2 py-0.5 text-[11px] font-medium text-er">Disabled</span><?php endif; ?>
                        </div>
                        <div class="truncate text-tx3"><?= $e($u['email']) ?></div>
                    </div>
                    <div class="flex flex-1 flex-wrap gap-1.5 sm:w-56 sm:flex-none">
                        <?php foreach ($u['roles'] as $r): ?>
                            <span class="rounded-md bg-sf2 px-2 py-0.5 text-[12px] text-tx2 ring-1 ring-ln"><?= $e($r['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-[12px] leading-tight text-tx3 sm:w-36">
                        <div><?= !empty($u['mfa_enabled_at']) ? '<span class="text-ok">● Two-step on</span>' : '<span class="text-wn">● Two-step not set</span>' ?></div>
                        <div class="mt-0.5"><?= !empty($u['last_login_at']) ? 'Last in ' . $e($loc->dateTime((string) $u['last_login_at'])) : 'Never signed in' ?></div>
                    </div>
                    <?php if ($canManage && !$isMe): ?>
                        <button type="button" @click="edit = !edit" class="<?= $btn ?> border border-ln hover:bg-sf2" x-text="edit ? 'Close' : 'Manage'">Manage</button>
                    <?php endif; ?>
                </div>

                <?php if ($canManage && !$isMe): ?>
                    <div x-show="edit" x-cloak class="mt-3 grid gap-4 rounded-[10px] bg-sf2 p-4 md:grid-cols-[1fr_auto]">
                        <form method="post" action="/company/users/<?= (int) $u['id'] ?>/roles">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                            <div class="font-medium">Roles</div>
                            <div class="mt-2 grid gap-1.5 sm:grid-cols-2">
                                <?php foreach ($assignable as $r): ?>
                                    <?php $isBranchRole = ($r['scope_type'] ?? 'company') === 'branch'; $has = in_array((int) $r['id'], $userRoleIds, true); ?>
                                    <label class="flex items-start gap-2" <?= $isBranchRole ? 'x-data="{ on: ' . ($has ? 'true' : 'false') . ' }"' : '' ?>>
                                        <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>" class="mt-0.5" <?= $has ? 'checked' : '' ?> <?= $isBranchRole ? 'x-model="on"' : '' ?>>
                                        <span class="flex-1"><?= $e($r['name']) ?><?php if (!empty($r['description'])): ?><span class="block text-[12px] text-tx3"><?= $e($r['description']) ?></span><?php endif; ?>
                                            <?php if ($isBranchRole): ?>
                                                <span x-show="on" x-cloak class="block"><?= $branchSelect('branch_for[' . (int) $r['id'] . ']', (int) ($scopeByRole[(int) $r['id']] ?? 0)) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                                <?php foreach ($u['roles'] as $r): ?>
                                    <?php if (!in_array((int) $r['id'], $assignableIds, true)): ?>
                                        <label class="flex items-start gap-2 text-tx3" title="You can't give or remove this role">
                                            <input type="checkbox" checked disabled class="mt-0.5"><span><?= $e($r['name']) ?></span>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button class="<?= $btn ?> mt-3 bg-ac text-white hover:opacity-90">Save roles</button>
                        </form>
                        <div class="flex flex-col gap-2 md:w-52">
                            <div class="font-medium">Account</div>
                            <?php if (!empty($u['mfa_enabled_at'])): ?>
                                <form method="post" action="/company/users/<?= (int) $u['id'] ?>/reset-mfa" onsubmit="return confirm('Reset two-step sign-in for <?= $e(addslashes((string) $u['name'])) ?>? They will be signed out and must scan a new QR code.');">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                                    <button class="<?= $btn ?> w-full border border-ln bg-sf hover:bg-sf2">Reset two-step sign-in</button>
                                </form>
                            <?php endif; ?>
                            <?php if ((int) $u['is_owner'] !== 1): ?>
                                <form method="post" action="/company/users/<?= (int) $u['id'] ?>/<?= $disabled ? 'enable' : 'disable' ?>"
                                    <?= $disabled ? '' : 'onsubmit="return confirm(\'Disable this user? They will be signed out everywhere.\');"' ?>>
                                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                                    <button class="<?= $btn ?> w-full border <?= $disabled ? 'border-ln bg-sf hover:bg-sf2' : 'border-er/30 bg-sf text-er hover:bg-erb' ?>"><?= $disabled ? 'Enable user' : 'Disable user' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($canManage && $invites !== []): ?>
    <section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
        <div class="border-b border-ln px-5 py-3 font-semibold">Pending invitations</div>
        <ul class="divide-y divide-ln2">
            <?php foreach ($invites as $i): ?>
                <?php $expired = (string) $i['expires_at'] <= $nowUtc; ?>
                <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                    <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                        <div class="font-medium"><?= $e($i['email']) ?><?= !empty($i['name']) ? ' <span class="font-normal text-tx3">· ' . $e($i['name']) . '</span>' : '' ?></div>
                        <div class="text-tx3"><?= $e($i['role_name']) ?> · invited by <?= $e($i['invited_by_name'] ?? '—') ?> ·
                            <?= $expired ? '<span class="text-er">expired</span>' : 'expires ' . $e($loc->dateTime((string) $i['expires_at'])) ?></div>
                    </div>
                    <form method="post" action="/company/users/invites/<?= (int) $i['id'] ?>/resend">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <button class="<?= $btn ?> border border-ln hover:bg-sf2">Resend</button>
                    </form>
                    <form method="post" action="/company/users/invites/<?= (int) $i['id'] ?>/revoke">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <button class="<?= $btn ?> border border-ln text-er hover:bg-erb">Cancel</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
