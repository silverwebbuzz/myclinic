<?php
/**
 * /company/security — two-step sign-in, signed-in devices, password, time zone.
 *
 * @var array<string,mixed> $user
 * @var \App\Support\Locale\Locale $loc
 * @var list<array<string,mixed>> $sessions  + device, is_current
 * @var bool $mfaEnabled
 * @var bool $mfaRequired
 * @var bool $mfaConfigured
 * @var int $recoveryLeft
 * @var string $companyTz
 * @var list<string> $timezones
 */
$pageTitle = 'Security';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-9 items-center justify-center rounded-[7px] px-3.5 text-[13px] font-medium';
$userTz = (string) ($user['timezone'] ?? '');
ob_start();
?>
<h1 class="text-[20px] font-semibold tracking-[-.015em]">Security</h1>
<p class="mt-0.5 text-tx3">Your own sign-in settings. Other people in your company manage theirs.</p>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <!-- Two-step sign-in -->
    <section class="rounded-[10px] border border-ln bg-sf p-5" x-data="{ regen: false, off: false }">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-semibold">Two-step sign-in</h2>
                <p class="mt-0.5 text-tx3">A code from an authenticator app on your phone, as well as your password.</p>
            </div>
            <span class="inline-flex h-[26px] flex-none items-center rounded-full px-2.5 text-xs font-medium <?= $mfaEnabled ? 'bg-okb text-ok' : 'bg-wnb text-wn' ?>">
                <?= $mfaEnabled ? 'On' : 'Off' ?>
            </span>
        </div>
        <?php if (!$mfaEnabled): ?>
            <?php if ($mfaConfigured): ?>
                <a href="/company/mfa/setup" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Set up two-step sign-in</a>
            <?php else: ?>
                <p class="mt-4 rounded-lg bg-ntb px-3 py-2 text-tx2">Not available on this server yet. Please contact support.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="mt-4 <?= $recoveryLeft <= 3 ? 'text-wn' : 'text-tx2' ?>">
                <?= (int) $recoveryLeft ?> backup code<?= $recoveryLeft === 1 ? '' : 's' ?> left.
                <?= $recoveryLeft <= 3 ? 'Make a new set soon.' : '' ?>
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" @click="regen = !regen; off = false" class="<?= $btn ?> border border-ln hover:bg-sf2">New backup codes</button>
                <?php if (!$mfaRequired): ?>
                    <button type="button" @click="off = !off; regen = false" class="<?= $btn ?> border border-ln text-er hover:bg-erb">Turn off</button>
                <?php endif; ?>
            </div>
            <form method="post" action="/company/security/mfa/recovery-codes" x-show="regen" x-cloak class="mt-4 space-y-3 rounded-[10px] bg-sf2 p-4">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <p class="text-tx2">Your old backup codes will stop working.</p>
                <label class="block">
                    <span class="text-tx2">Code from your app</span>
                    <input name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" class="<?= $input ?> font-mono tracking-[.2em]" placeholder="123 456">
                </label>
                <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Make new codes</button>
            </form>
            <?php if (!$mfaRequired): ?>
                <form method="post" action="/company/security/mfa/disable" x-show="off" x-cloak class="mt-4 space-y-3 rounded-[10px] bg-erb/50 p-4">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <label class="block">
                        <span class="text-tx2">Current password</span>
                        <input name="current_password" type="password" required autocomplete="current-password" class="<?= $input ?>">
                    </label>
                    <label class="block">
                        <span class="text-tx2">Code from your app</span>
                        <input name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" class="<?= $input ?> font-mono tracking-[.2em]">
                    </label>
                    <button class="<?= $btn ?> bg-er text-white hover:opacity-90">Turn off two-step sign-in</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- Password -->
    <section class="rounded-[10px] border border-ln bg-sf p-5">
        <h2 class="text-[15px] font-semibold">Change password</h2>
        <p class="mt-0.5 text-tx3">You'll stay signed in here; other devices are signed out.</p>
        <form method="post" action="/company/security/password" class="mt-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="text" name="username" value="<?= $e($user['email'] ?? '') ?>" autocomplete="username" class="hidden" tabindex="-1" aria-hidden="true">
            <label class="block">
                <span class="text-tx2">Current password</span>
                <input name="current_password" type="password" required autocomplete="current-password" class="<?= $input ?>">
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="text-tx2">New password</span>
                    <input name="password" type="password" required minlength="10" maxlength="128" autocomplete="new-password" class="<?= $input ?>">
                </label>
                <label class="block">
                    <span class="text-tx2">Confirm new password</span>
                    <input name="password_confirm" type="password" required minlength="10" maxlength="128" data-match="password" autocomplete="new-password" class="<?= $input ?>">
                </label>
            </div>
            <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Change password</button>
        </form>
    </section>

    <!-- Devices -->
    <section class="rounded-[10px] border border-ln bg-sf p-5 lg:col-span-2">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-semibold">Where you're signed in</h2>
                <p class="mt-0.5 text-tx3">Sign out of any device you don't recognise, then change your password.</p>
            </div>
            <?php if (count($sessions) > 1): ?>
                <form method="post" action="/company/security/sessions/revoke-others">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="<?= $btn ?> border border-ln hover:bg-sf2">Sign out all other devices</button>
                </form>
            <?php endif; ?>
        </div>
        <ul class="mt-3 divide-y divide-ln2">
            <?php foreach ($sessions as $s): ?>
                <li class="flex flex-wrap items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium"><?= $e($s['device']) ?>
                            <?php if ($s['is_current']): ?><span class="ml-1.5 rounded-full bg-okb px-2 py-0.5 text-[11px] font-medium text-ok">This device</span><?php endif; ?>
                        </div>
                        <div class="text-tx3">
                            <?= $e($s['ip'] ?? 'Unknown IP') ?> · signed in <?= $e($loc->dateTime((string) $s['created_at'])) ?> · last active <?= $e($loc->dateTime((string) $s['last_seen_at'])) ?>
                        </div>
                    </div>
                    <?php if (!$s['is_current']): ?>
                        <form method="post" action="/company/security/sessions/<?= (int) $s['id'] ?>/revoke">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                            <button class="<?= $btn ?> h-8 border border-ln hover:bg-sf2">Sign out</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- Time zone -->
    <section class="rounded-[10px] border border-ln bg-sf p-5 lg:col-span-2">
        <h2 class="text-[15px] font-semibold">Time zone</h2>
        <p class="mt-0.5 text-tx3">Dates and times in the portal are shown in this time zone. Right now it's <?= $e($loc->dateTime(\App\Support\Locale\LocalDate::nowUtc())) ?>.</p>
        <form method="post" action="/company/security/timezone" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <label class="block w-full sm:max-w-sm">
                <span class="text-tx2">My time zone</span>
                <select name="timezone" class="<?= $input ?>">
                    <option value="" <?= $userTz === '' ? 'selected' : '' ?>>Company default (<?= $e($companyTz) ?>)</option>
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $e($tz) ?>" <?= $userTz === $tz ? 'selected' : '' ?>><?= $e(str_replace('_', ' ', $tz)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="<?= $btn ?> border border-ln hover:bg-sf2">Save</button>
        </form>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
