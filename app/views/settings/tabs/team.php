<?php
$seats = $seatUsage ?? ['used' => 0, 'limit' => 2, 'available' => 0];
$teamMessages = [
    'invited' => 'Invitation email sent.',
    'created' => 'Staff account created. Copy the credentials below and share them securely.',
    'password_reset' => 'Password reset. Share the new temporary password below.',
    'updated' => 'Team member updated.',
    'revoked' => 'Invitation revoked.',
];
$creds = is_array($staffCredentials ?? null) ? $staffCredentials : null;
$loginUrl = $loginUrl ?? 'https://app.eclinicpro.com/login';
?>
<div class="space-y-4">
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

    <?php if ($creds !== null): ?>
    <div class="ui-card ui-card-pad border-amber-200 bg-amber-50" x-data="{ copied: '' }">
        <h3 class="ui-section-title text-amber-900">Share these login details now</h3>
        <p class="mt-1 text-sm text-amber-800">
            This password is shown only once. <?= htmlspecialchars($creds['name'] ?? 'Staff') ?> must change it on first login.
        </p>
        <dl class="mt-4 space-y-3 text-sm">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-amber-700">Login URL</dt>
                <dd class="mt-1 flex flex-wrap items-center gap-2">
                    <code class="break-all rounded bg-white px-2 py-1 font-mono text-xs"><?= htmlspecialchars($creds['login_url'] ?? $loginUrl) ?></code>
                    <button type="button" class="text-xs font-medium text-amber-900 underline"
                            @click="navigator.clipboard.writeText(<?= json_encode($creds['login_url'] ?? $loginUrl) ?>); copied='url'">
                        Copy
                    </button>
                </dd>
            </div>
            <?php if (!empty($creds['username'])): ?>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-amber-700">Username</dt>
                <dd class="mt-1 flex flex-wrap items-center gap-2">
                    <code class="rounded bg-white px-2 py-1 font-mono text-sm"><?= htmlspecialchars($creds['username']) ?></code>
                    <button type="button" class="text-xs font-medium text-amber-900 underline"
                            @click="navigator.clipboard.writeText(<?= json_encode($creds['username']) ?>); copied='user'">
                        Copy
                    </button>
                </dd>
            </div>
            <?php elseif (!empty($creds['email'])): ?>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-amber-700">Email</dt>
                <dd class="mt-1 font-mono text-sm"><?= htmlspecialchars($creds['email']) ?></dd>
            </div>
            <?php endif; ?>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-amber-700">Temporary password</dt>
                <dd class="mt-1 flex flex-wrap items-center gap-2">
                    <code class="rounded bg-white px-2 py-1 font-mono text-sm tracking-wide"><?= htmlspecialchars($creds['password'] ?? '') ?></code>
                    <button type="button" class="text-xs font-medium text-amber-900 underline"
                            @click="navigator.clipboard.writeText(<?= json_encode($creds['password'] ?? '') ?>); copied='pass'">
                        Copy
                    </button>
                </dd>
            </div>
        </dl>
        <p x-show="copied" x-transition class="mt-3 text-xs text-amber-800">Copied to clipboard.</p>
    </div>
    <?php endif; ?>

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
                    <p class="mt-1 text-xs text-slate-500">No email needed — you share username &amp; password manually.</p>
                </div>
                <button type="button" @click="open = !open" class="ui-btn ui-btn-secondary ui-btn-sm" :disabled="<?= ($seats['available'] ?? 0) <= 0 ? 'true' : 'false' ?>">
                    <?= ui_icon('plus', 14) ?><span>Create</span>
                </button>
            </div>
            <form x-show="open" x-transition method="post" action="/settings/team/create" class="mt-4 grid gap-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <label class="text-sm">Name <input name="name" required class="ui-input" placeholder="Priya Sharma"></label>
                <label class="text-sm">
                    Username <span class="text-slate-400">(optional)</span>
                    <input name="username" pattern="[a-z][a-z0-9_]{2,29}" class="ui-input" placeholder="priya">
                    <span class="mt-1 block text-xs text-slate-400">Lowercase letters, numbers, underscore. Auto-generated if blank.</span>
                </label>
                <label class="text-sm">Role
                    <select name="role" class="ui-input">
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="receptionist">Receptionist</option>
                        <option value="labtech">Lab tech</option>
                    </select>
                </label>
                <button type="submit" class="ui-btn ui-btn-primary">Create &amp; show password</button>
            </form>
        </div>
    </div>

    <div class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Active staff</h3>
        <ul class="mt-2 divide-y divide-slate-100">
            <?php foreach ($staff ?? [] as $member): ?>
            <li class="py-2">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800"><?= htmlspecialchars($member['name']) ?></p>
                        <p class="truncate text-xs text-slate-400">
                            <?php if (!empty($member['username'])): ?>
                                @<?= htmlspecialchars($member['username']) ?>
                            <?php endif; ?>
                            <?php if (!empty($member['email'])): ?>
                                <?= !empty($member['username']) ? ' · ' : '' ?><?= htmlspecialchars($member['email']) ?>
                            <?php endif; ?>
                            <?php if (empty($member['username']) && empty($member['email'])): ?>
                                No login email
                            <?php endif; ?>
                        </p>
                    </div>
                    <form method="post" action="/settings/team/<?= (int) $member['id'] ?>" class="flex flex-wrap items-center gap-3">
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
                    <form method="post" action="/settings/team/<?= (int) $member['id'] ?>/reset-password"
                          onsubmit="return confirm('Generate a new temporary password for <?= htmlspecialchars(addslashes($member['name'])) ?>?');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="shrink-0 text-xs text-amber-700 hover:underline">Reset password</button>
                    </form>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
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
