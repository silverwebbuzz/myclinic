<?php
/**
 * /admin/corporate/settings — Workforce Health settings + eClinicPro team roles (super admins only).
 *
 * @var int $trialDays
 * @var bool $signupEnabled
 * @var bool $mfaRequired
 * @var bool $mfaConfigured
 * @var list<array<string,mixed>> $admins
 * @var list<array<string,mixed>> $countries
 * @var array<string,string> $roles
 * @var int $me
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings · Workforce Health · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl px-6 py-6">
        <h1 class="text-xl font-semibold">Workforce Health · Settings &amp; team</h1>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>

        <form method="post" action="/admin/corporate/settings" class="mt-4 ui-card space-y-4 p-5">
            <?= $csrfField ?>
            <h2 class="font-semibold">Company accounts</h2>
            <label class="block text-sm">Free trial length (days)
                <input name="trial_days" type="number" min="1" max="365" value="<?= (int) $trialDays ?>" class="mt-1 block w-32 rounded border border-slate-200 px-2.5 py-1.5 text-sm">
                <span class="text-xs text-slate-500">Starts when you approve a company. Changing it doesn't affect trials already running.</span>
            </label>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="signup_enabled" value="1" class="mt-0.5" <?= $signupEnabled ? 'checked' : '' ?>>
                <span>Allow companies to register themselves at app.eclinicpro.com/company/register</span>
            </label>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="mfa_required" value="1" class="mt-0.5" <?= $mfaRequired ? 'checked' : '' ?>>
                <span>Require two-step sign-in for every company user <span class="text-xs text-slate-500">(recommended: they manage workforce health records)</span></span>
            </label>
            <?php if (!$mfaConfigured): ?>
                <p class="rounded bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200"><strong>Two-step sign-in can't work yet:</strong> set <code>CORP_DATA_KEY</code> (or <code>STORE_DATA_KEY</code>) in app/.env. Until then it is not enforced.</p>
            <?php endif; ?>
            <button class="rounded bg-slate-800 px-4 py-2 text-xs font-semibold text-white">Save settings</button>
        </form>

        <section class="mt-6 ui-card p-5">
            <h2 class="font-semibold">eClinicPro team access</h2>
            <p class="mt-1 text-sm text-slate-500">Super admins see everything. Other roles only see Workforce Health; country admins only for their countries.</p>
            <ul class="mt-3 divide-y divide-slate-100">
                <?php foreach ($admins as $a): ?>
                    <?php $sel = json_decode((string) ($a['country_codes'] ?? '[]'), true) ?: []; $role = (string) ($a['role'] ?? 'super'); ?>
                    <li class="py-3" x-data="{ role: '<?= $h($role) ?>' }">
                        <form method="post" action="/admin/corporate/settings/admins/<?= (int) $a['id'] ?>" class="flex flex-wrap items-start gap-3">
                            <?= $csrfField ?>
                            <div class="min-w-[220px] flex-1">
                                <div class="text-sm font-medium"><?= $h($a['name']) ?><?= (int) $a['id'] === $me ? ' <span class="text-xs text-slate-500">(you)</span>' : '' ?><?= (int) $a['is_active'] !== 1 ? ' <span class="text-xs text-rose-600">inactive</span>' : '' ?></div>
                                <div class="text-xs text-slate-500"><?= $h($a['email']) ?></div>
                            </div>
                            <select name="role" x-model="role" class="rounded border border-slate-200 px-2 py-1.5 text-sm" <?= (int) $a['id'] === $me ? 'disabled' : '' ?>>
                                <?php foreach ($roles as $k => $label): ?><option value="<?= $h($k) ?>" <?= $role === $k ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                            </select>
                            <div x-show="role === 'country_admin'" x-cloak class="flex max-w-md flex-wrap gap-x-3 gap-y-1">
                                <?php foreach ($countries as $c): ?>
                                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="countries[]" value="<?= $h($c['code']) ?>" <?= in_array($c['code'], $sel, true) ? 'checked' : '' ?>> <?= $h($c['code']) ?></label>
                                <?php endforeach; ?>
                            </div>
                            <?php if ((int) $a['id'] !== $me): ?>
                                <button class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Save</button>
                            <?php endif; ?>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-2 text-xs text-slate-500">New team members are added the same way as today; they start as super admins, so set their role here straight away.</p>
        </section>
    </main>
</body>
</html>
