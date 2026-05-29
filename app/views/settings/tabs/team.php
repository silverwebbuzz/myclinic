<?php $seats = $seatUsage ?? ['used' => 0, 'limit' => 2, 'available' => 0]; ?>
<div class="space-y-4">
    <div class="ui-card ui-card-pad">
        <h2 class="ui-section-title">Team & seats</h2>
        <p class="mt-1 text-sm text-slate-500">
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
        <?php if (!empty($message)): ?>
        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Team updated.</p>
        <?php endif; ?>
    </div>

    <div class="ui-card ui-card-pad" x-data="{ open: false }">
        <div class="flex items-center justify-between">
            <h3 class="ui-section-title">Invite staff</h3>
            <button type="button" @click="open = !open" class="ui-btn ui-btn-secondary ui-btn-sm" :disabled="<?= ($seats['available'] ?? 0) <= 0 ? 'true' : 'false' ?>">
                <?= ui_icon('plus', 14) ?><span>Invite</span>
            </button>
        </div>
        <form x-show="open" x-transition method="post" action="/settings/team/invite" class="mt-4 grid gap-3 sm:grid-cols-2">
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
            <div class="sm:col-span-2">
                <button type="submit" class="ui-btn ui-btn-primary">Send invite</button>
            </div>
        </form>
    </div>

    <div class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Active staff</h3>
        <ul class="mt-2 divide-y divide-slate-100">
            <?php foreach ($staff ?? [] as $member): ?>
            <li>
                <form method="post" action="/settings/team/<?= (int) $member['id'] ?>" class="flex flex-wrap items-center gap-3 py-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800"><?= htmlspecialchars($member['name']) ?></p>
                        <p class="truncate text-xs text-slate-400"><?= htmlspecialchars($member['email']) ?></p>
                    </div>
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
