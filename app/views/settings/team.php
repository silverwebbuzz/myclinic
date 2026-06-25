<?php
$seats = $seatUsage ?? ['used' => 0, 'limit' => 2, 'available' => 0];
$teamMessages = [
    'invited' => 'Invitation email sent.',
    'created' => 'Staff account created.',
    'password_reset' => 'Password reset.',
    'updated' => 'Team member updated.',
    'revoked' => 'Invitation revoked.',
];
$flashUserId = is_array($passwordFlash ?? null) ? (int) ($passwordFlash['user_id'] ?? 0) : 0;
$flashPassword = is_array($passwordFlash ?? null) ? (string) ($passwordFlash['password'] ?? '') : '';
$flashUsername = is_array($passwordFlash ?? null) ? (string) ($passwordFlash['username'] ?? '') : '';
$loginUrl = $loginUrl ?? 'https://app.eclinicpro.com/login';
?>
<?= ui_page_header('Team', 'Invite staff, create logins, and manage seats.') ?>

<div class="mx-auto max-w-4xl space-y-4">
    <div class="ui-card ui-card-pad">
        <p class="text-sm text-slate-500">
            <?= (int) $seats['used'] ?> of <?= (int) $seats['limit'] ?> seats used
            (<?= (int) $seats['available'] ?> available)
        </p>
        <?php if (($seats['available'] ?? 0) <= 0): ?>
        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
            Seat limit reached.
            <a href="/settings?tab=subscription" class="font-medium underline">Upgrade plan</a> or purchase extra seats.
        </p>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($message) && isset($teamMessages[$message])): ?>
        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($teamMessages[$message]) ?></p>
        <?php endif; ?>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="ui-card ui-card-pad" x-data="{ open: false }">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="ui-section-title">Invite by email</h3>
                    <p class="mt-1 text-xs text-slate-500">Staff sets their own password via email link.</p>
                </div>
                <button type="button" @click="open = !open" class="ui-btn ui-btn-secondary ui-btn-sm" :disabled="<?= ($seats['available'] ?? 0) <= 0 ? 'true' : 'false' ?>">
                    <?= ui_icon('plus', 14) ?><span>Invite</span>
                </button>
            </div>
            <form x-show="open" x-transition method="post" action="/settings/team/invite" class="mt-4 grid gap-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <label class="text-sm">Name <input name="name" required class="ui-input"></label>
                <label class="text-sm">Email <input type="email" name="email" required class="ui-input"></label>
                <label class="text-sm">Role
                    <select name="role" class="ui-input">
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="receptionist">Receptionist</option>
                        <option value="labtech">Lab tech</option>
                    </select>
                </label>
                <button type="submit" class="ui-btn ui-btn-primary">Send invite</button>
            </form>
        </div>

        <div class="ui-card ui-card-pad" x-data="{ open: false }">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="ui-section-title">Create account</h3>
                    <p class="mt-1 text-xs text-slate-500">No email — use mobile or username as login ID.</p>
                </div>
                <button type="button" @click="open = !open" class="ui-btn ui-btn-secondary ui-btn-sm" :disabled="<?= ($seats['available'] ?? 0) <= 0 ? 'true' : 'false' ?>">
                    <?= ui_icon('plus', 14) ?><span>Create</span>
                </button>
            </div>
            <form x-show="open" x-transition method="post" action="/settings/team/create" class="mt-4 grid gap-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <label class="text-sm">Name <input name="name" required class="ui-input" placeholder="Priya Sharma"></label>
                <label class="text-sm">
                    Mobile or username <span class="text-slate-400">(optional)</span>
                    <input name="login_id" class="ui-input" placeholder="9876543210 or priya" inputmode="text" autocomplete="off">
                    <span class="mt-1 block text-xs text-slate-400">10-digit mobile is easiest for login. Auto-generated from name if blank.</span>
                </label>
                <label class="text-sm">Role
                    <select name="role" class="ui-input">
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="receptionist">Receptionist</option>
                        <option value="labtech">Lab tech</option>
                    </select>
                </label>
                <button type="submit" class="ui-btn ui-btn-primary">Create account</button>
            </form>
        </div>
    </div>

    <div class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Active staff</h3>
        <ul class="mt-2 divide-y divide-slate-100">
            <?php foreach ($staff ?? [] as $member): ?>
            <?php
                $memberId = (int) $member['id'];
                $loginId = !empty($member['username'])
                    ? (string) $member['username']
                    : (!empty($member['email']) ? (string) $member['email'] : '');
                $showPassword = $flashUserId === $memberId && $flashPassword !== '';
            ?>
            <li class="py-3">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($member['name']) ?></p>
                        <?php if ($loginId !== ''): ?>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Login: <span class="font-mono text-slate-700"><?= htmlspecialchars($loginId) ?></span>
                            <?php if (preg_match('/^\d{10}$/', $loginId)): ?>
                            <span class="text-slate-400">(mobile)</span>
                            <?php endif; ?>
                        </p>
                        <?php elseif (!empty($member['email'])): ?>
                        <p class="mt-0.5 text-xs text-slate-500">Login: <?= htmlspecialchars($member['email']) ?></p>
                        <?php endif; ?>
                        <?php if ($showPassword): ?>
                        <?php if ($flashUsername !== ''): ?>
                        <p class="mt-1 text-xs text-amber-800">
                            Login ID: <code class="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-sm tracking-wide"><?= htmlspecialchars($flashUsername) ?></code>
                            <button type="button" class="ml-1 font-medium underline"
                                    onclick="copyClinicText(<?= json_encode($flashUsername) ?>, this)">
                                Copy
                            </button>
                        </p>
                        <?php endif; ?>
                        <p class="mt-1 text-xs text-amber-800">
                            Password: <code id="staff-pass-<?= $memberId ?>" class="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-sm tracking-wide"><?= htmlspecialchars($flashPassword) ?></code>
                            <button type="button" class="ml-1 font-medium underline"
                                    onclick="copyClinicText(<?= json_encode($flashPassword) ?>, this)">
                                Copy
                            </button>
                            <span class="ml-1 hidden text-amber-700 copy-done">Copied</span>
                            <span class="block text-amber-700/80">Shown once — share now. Staff must change on first login.</span>
                        </p>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="/settings/team/<?= $memberId ?>" class="flex flex-wrap items-center gap-3">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <select name="role" class="ui-input w-32 shrink-0" <?= !empty($member['is_owner']) ? 'disabled' : '' ?>>
                            <?php foreach (['admin','doctor','nurse','receptionist','labtech'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($member['role'] ?? '') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="flex shrink-0 items-center gap-1.5 text-xs text-slate-600">
                            <input class="ui-checkbox" type="checkbox" name="is_active" value="1" <?= (int) ($member['is_active'] ?? 1) ? 'checked' : '' ?> <?= !empty($member['is_owner']) ? 'disabled' : '' ?>>
                            Active
                        </label>
                        <?php if (empty($member['is_owner'])): ?>
                        <button type="submit" class="shrink-0 text-xs font-medium text-brand hover:underline">Save</button>
                        <?php else: ?>
                        <span class="shrink-0 text-xs text-slate-400">Owner</span>
                        <?php endif; ?>
                    </form>
                    <?php if (empty($member['is_owner'])): ?>
                    <form method="post" action="/settings/team/<?= $memberId ?>/reset-password"
                          onsubmit="return confirm('Generate a new temporary password for <?= htmlspecialchars(addslashes($member['name'])) ?>?');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="shrink-0 text-xs text-amber-700 hover:underline">Reset password</button>
                    </form>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-3 text-xs text-slate-400">Login URL: <span class="font-mono"><?= htmlspecialchars($loginUrl) ?></span></p>
    </div>

    <?php if (!empty($invitations)): ?>
    <div class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Pending invitations</h3>
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
            <?php foreach ($invitations as $inv): ?>
            <?php if (($inv['status'] ?? '') !== 'pending') continue; ?>
            <li class="flex items-center justify-between py-2">
                <span><?= htmlspecialchars($inv['name']) ?> · <?= htmlspecialchars($inv['email']) ?> · <?= htmlspecialchars($inv['role']) ?></span>
                <form method="post" action="/settings/team/invites/<?= (int) $inv['id'] ?>/revoke">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="text-xs text-red-600 hover:underline">Revoke</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
