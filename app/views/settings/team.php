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
$flashName = is_array($passwordFlash ?? null) ? (string) ($passwordFlash['name'] ?? '') : '';
$flashRole = is_array($passwordFlash ?? null) ? (string) ($passwordFlash['role'] ?? '') : '';
$flashMustChange = is_array($passwordFlash ?? null) ? (bool) ($passwordFlash['must_change'] ?? true) : true;
$flashCreated = is_array($passwordFlash ?? null) && !empty($passwordFlash['created']);
$loginUrl = $loginUrl ?? 'https://app.eclinicpro.com/login';
?>
<?= ui_page_header('Team', 'Add your receptionist and other staff, and manage their logins.') ?>

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

    <?php if ($flashCreated && $flashPassword !== ''): ?>
    <?php
        $shareText = 'Hi ' . $flashName . ', your clinic login is ready.' . "\n"
            . 'Open: ' . $loginUrl . "\n"
            . 'Login ID: ' . $flashUsername . "\n"
            . 'Password: ' . $flashPassword
            . ($flashMustChange ? "\n" . '(You will be asked to set a new password on first login.)' : '');
        $waTarget = preg_match('/^\d{10}$/', $flashUsername) ? '91' . $flashUsername : '';
        $waUrl = 'https://wa.me/' . $waTarget . '?text=' . rawurlencode($shareText);
    ?>
    <div class="ui-card ui-card-pad border-emerald-200 bg-emerald-50/60">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="ui-section-title text-emerald-900"><?= htmlspecialchars(['receptionist' => 'Receptionist', 'nurse' => 'Nurse', 'doctor' => 'Doctor', 'labtech' => 'Lab tech'][$flashRole] ?? 'Staff') ?> account ready: <?= htmlspecialchars($flashName) ?></h3>
                <p class="mt-1 text-xs text-emerald-800">Share these details now. The password is shown only once.</p>
            </div>
        </div>
        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
            <div class="rounded-lg bg-white px-3 py-2">
                <dt class="text-xs text-slate-500">Login page</dt>
                <dd class="mt-0.5 break-all font-mono text-sm text-slate-800"><?= htmlspecialchars($loginUrl) ?></dd>
            </div>
            <div class="rounded-lg bg-white px-3 py-2">
                <dt class="text-xs text-slate-500">Login ID</dt>
                <dd class="mt-0.5 font-mono text-base font-semibold tracking-wide text-slate-900"><?= htmlspecialchars($flashUsername) ?></dd>
            </div>
            <div class="rounded-lg bg-white px-3 py-2">
                <dt class="text-xs text-slate-500">Password</dt>
                <dd class="mt-0.5 font-mono text-base font-semibold tracking-wide text-slate-900"><?= htmlspecialchars($flashPassword) ?></dd>
            </div>
        </dl>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="ui-btn ui-btn-primary ui-btn-sm"
                    onclick="copyClinicText(<?= htmlspecialchars(json_encode($shareText), ENT_QUOTES) ?>, this)">
                Copy login details
            </button>
            <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="ui-btn ui-btn-secondary ui-btn-sm">
                Send on WhatsApp
            </a>
        </div>
        <?php if ($flashMustChange): ?>
        <p class="mt-2 text-xs text-emerald-800">This is a temporary password. They will set their own on first login.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Quick add: the common case (a receptionist with a mobile number) in one short form. -->
    <div class="ui-card ui-card-pad" x-data="{ role: 'receptionist', setPw: false }">
        <div>
            <h3 class="ui-section-title">Add receptionist</h3>
            <p class="mt-1 text-xs text-slate-500">Name and mobile number are enough. Their mobile number becomes their login ID. No email needed.</p>
        </div>
        <form method="post" action="/settings/team/create" class="mt-4 grid gap-3 sm:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label class="text-sm">Full name
                <input name="name" required class="ui-input" placeholder="Priya Sharma" autocomplete="off">
            </label>
            <label class="text-sm">Mobile number
                <input name="login_id" class="ui-input" placeholder="9876543210" inputmode="numeric" maxlength="14" autocomplete="off">
                <span class="mt-1 block text-xs text-slate-400">Leave blank to auto-create a username from the name.</span>
            </label>
            <div class="text-sm sm:col-span-2">
                <span>Role</span>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <?php foreach (['receptionist' => 'Receptionist', 'nurse' => 'Nurse', 'doctor' => 'Doctor', 'labtech' => 'Lab tech'] as $rv => $rl): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="role" value="<?= $rv ?>" x-model="role" class="sr-only" <?= $rv === 'receptionist' ? 'checked' : '' ?>>
                        <span class="inline-block rounded-full border px-3 py-1 text-xs"
                              :class="role === '<?= $rv ?>' ? 'border-brand bg-brand text-white' : 'border-slate-300 bg-white text-slate-700'"><?= $rl ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="text-sm sm:col-span-2">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" class="ui-checkbox" x-model="setPw">
                    I'll set the password myself
                </label>
                <template x-if="setPw">
                    <input type="text" name="password" class="ui-input mt-2 sm:max-w-xs" placeholder="e.g. Clinic2026"
                           minlength="8" autocomplete="new-password">
                </template>
                <p class="mt-1 text-xs text-slate-400" x-show="setPw">At least 8 characters, one capital letter and one number. They can log in with it straight away.</p>
                <p class="mt-1 text-xs text-slate-400" x-show="!setPw">We'll create a temporary password and show it to you after saving.</p>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="ui-btn ui-btn-primary" <?= ($seats['available'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                    <?= ui_icon('plus', 14) ?><span x-text="'Add ' + ({receptionist:'receptionist', nurse:'nurse', doctor:'doctor', labtech:'lab tech'})[role]">Add receptionist</span>
                </button>
            </div>
        </form>
    </div>

    <details class="ui-card ui-card-pad">
        <summary class="cursor-pointer select-none text-sm font-medium text-slate-700">Invite by email instead</summary>
        <p class="mt-1 text-xs text-slate-500">Staff sets their own password via an email link.</p>
        <form method="post" action="/settings/team/invite" class="mt-4 grid gap-3 sm:grid-cols-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label class="text-sm">Name <input name="name" required class="ui-input"></label>
            <label class="text-sm">Email <input type="email" name="email" required class="ui-input"></label>
            <label class="text-sm">Role
                <select name="role" class="ui-input">
                    <option value="receptionist" selected>Receptionist</option>
                    <option value="nurse">Nurse</option>
                    <option value="doctor">Doctor</option>
                    <option value="labtech">Lab tech</option>
                </select>
            </label>
            <div class="sm:col-span-3">
                <button type="submit" class="ui-btn ui-btn-secondary" <?= ($seats['available'] ?? 0) <= 0 ? 'disabled' : '' ?>>Send invite</button>
            </div>
        </form>
    </details>

    <div class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Active staff</h3>
        <ul class="mt-2 divide-y divide-slate-100">
            <?php foreach ($staff ?? [] as $member): ?>
            <?php
                $memberId = (int) $member['id'];
                $loginId = !empty($member['username'])
                    ? (string) $member['username']
                    : (!empty($member['email']) ? (string) $member['email'] : '');
                $showPassword = $flashUserId === $memberId && $flashPassword !== '' && !$flashCreated;
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
