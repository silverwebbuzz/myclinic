<?php
/**
 * /admin/corporate/companies/{id} — one company: review, lifecycle, documents, users, activity.
 *
 * @var array<string,mixed> $company
 * @var \App\Support\Locale\Locale $loc   company's own locale (dates in company timezone)
 * @var array<string,mixed>|null $country
 * @var array<string,mixed>|null $region
 * @var string $industry
 * @var list<array<string,mixed>> $documents
 * @var array<string,string> $docTypes
 * @var list<array<string,mixed>> $users
 * @var list<array<string,mixed>> $activity
 * @var array<string,string> $labels
 * @var array<string,bool> $scope
 * @var int $trialDays
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$id = (int) $company['id'];
$status = (string) $company['status'];
$pill = [
    'pending' => ['Waiting for review', 'bg-amber-50 text-amber-800 ring-amber-200'], 'active' => ['Active', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
    'suspended' => ['Suspended', 'bg-rose-50 text-rose-800 ring-rose-200'], 'rejected' => ['Rejected', 'bg-slate-100 text-slate-600 ring-slate-200'],
    'closed' => ['Closed', 'bg-slate-100 text-slate-600 ring-slate-200'],
][$status] ?? [$status, ''];
$docPill = ['pending' => 'bg-amber-50 text-amber-800', 'approved' => 'bg-emerald-50 text-emerald-800', 'rejected' => 'bg-rose-50 text-rose-800'];
$field = static function (string $label, $value) use ($h): string {
    return '<div class="flex justify-between gap-4 py-1.5"><dt class="text-slate-500">' . $h($label) . '</dt><dd class="text-right text-slate-900">'
        . ($value !== null && $value !== '' ? $h($value) : '<span class="text-slate-400">—</span>') . '</dd></div>';
};
$approvedDocs = count(array_filter($documents, static fn ($d) => $d['status'] === 'approved'));
$btn = 'rounded px-3 py-1.5 text-xs font-semibold';
$input = 'mt-1 w-full rounded border border-slate-200 px-2.5 py-1.5 text-sm';
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($company['legal_name']) ?> · Workforce Health · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl px-6 py-6">
        <a href="/admin/corporate/companies" class="text-sm text-slate-500 hover:underline">← Companies</a>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-semibold"><?= $h($company['legal_name']) ?></h1>
            <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 <?= $pill[1] ?>"><?= $h($pill[0]) ?></span>
            <?php if ((int) $company['is_demo'] === 1): ?><span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700">Demo data</span><?php endif; ?>
        </div>
        <p class="text-sm text-slate-500"><?= $h($company['public_ref']) ?> · <?= $h($country['name'] ?? $company['country_code']) ?> · registered <?= $h($loc->dateTime((string) $company['created_at'])) ?> (<?= $h($loc->timezone) ?>)</p>
        <?php if (!empty($company['status_reason'])): ?>
            <p class="mt-2 rounded bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200"><strong>Reason on record:</strong> <?= $h($company['status_reason']) ?></p>
        <?php endif; ?>

        <?php require __DIR__ . '/_corporate_flash.php'; ?>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <!-- Left: profile + documents + users -->
            <div class="space-y-4 lg:col-span-2">
                <section class="ui-card p-5">
                    <h2 class="font-semibold">Company details</h2>
                    <dl class="mt-2 grid gap-x-8 text-sm sm:grid-cols-2">
                        <div>
                            <?= $field('Trading name', $company['trade_name']) ?>
                            <?= $field('Industry', $industry) ?>
                            <?= $field('Employees', $company['employee_size_band']) ?>
                            <?= $field('Business type', \App\Services\Corporate\CompanyProfileService::BUSINESS_TYPES[$company['business_type'] ?? ''] ?? null) ?>
                            <?= $field($country['company_id_label'] ?? 'Registration no.', $company['registration_no']) ?>
                            <?= $field('Licence no.', $company['licence_no']) ?>
                            <?= $field('Licence expiry', $company['licence_expiry'] ? $loc->date((string) $company['licence_expiry']) : null) ?>
                            <?= $field($country['tax_id_label'] ?? 'Tax no.', $company['tax_reg_no']) ?>
                        </div>
                        <div>
                            <?= $field('Address', trim(($company['address_line1'] ?? '') . ' ' . ($company['address_line2'] ?? ''))) ?>
                            <?= $field($country['region_label'] ?? 'Region', $region['name'] ?? null) ?>
                            <?= $field('City', $company['city']) ?>
                            <?= $field($country['postal_label'] ?? 'Postal code', $company['postal_code']) ?>
                            <?= $field('Contact', $company['contact_name']) ?>
                            <?= $field('Contact email', $company['contact_email']) ?>
                            <?= $field('Contact phone', \App\Support\Locale\Phone::display($company['contact_phone'])) ?>
                            <?= $field('Website', $company['website']) ?>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-slate-500">Currency <?= $h($company['currency_code']) ?> · time zone <?= $h($company['timezone']) ?> · data region <?= $h($company['data_region']) ?></p>
                </section>

                <section class="ui-card p-5">
                    <h2 class="font-semibold">Documents <span class="text-sm font-normal text-slate-500">(<?= $approvedDocs ?> approved of <?= count($documents) ?>)</span></h2>
                    <?php if ($documents === []): ?>
                        <p class="mt-2 text-sm text-slate-500">No documents uploaded yet. Companies upload them from Company profile.</p>
                    <?php else: ?>
                        <ul class="mt-2 divide-y divide-slate-100 text-sm">
                            <?php foreach ($documents as $d): ?>
                                <li class="py-3" x-data="{ rej: false }">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="/admin/corporate/companies/<?= $id ?>/documents/<?= (int) $d['id'] ?>" target="_blank" rel="noopener" class="font-medium text-slate-900 underline"><?= $h($docTypes[$d['doc_type']] ?? $d['doc_type']) ?></a>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $docPill[$d['status']] ?? '' ?>"><?= $h(ucfirst((string) $d['status'])) ?></span>
                                        <span class="text-xs text-slate-500"><?= $h($d['original_name'] ?? '') ?> · <?= $h($loc->dateTime((string) $d['created_at'])) ?></span>
                                        <?php if ($scope['review'] && $d['status'] !== 'approved'): ?>
                                            <form method="post" action="/admin/corporate/companies/<?= $id ?>/documents/<?= (int) $d['id'] ?>/review" class="ml-auto">
                                                <?= $csrfField ?><input type="hidden" name="decision" value="approve">
                                                <button class="<?= $btn ?> bg-emerald-600 text-white">Approve</button>
                                            </form>
                                            <button type="button" @click="rej = !rej" class="<?= $btn ?> bg-white text-rose-700 ring-1 ring-rose-200">Reject</button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($d['review_note'])): ?><p class="mt-1 text-xs text-slate-500">Note: <?= $h($d['review_note']) ?></p><?php endif; ?>
                                    <form method="post" action="/admin/corporate/companies/<?= $id ?>/documents/<?= (int) $d['id'] ?>/review" x-show="rej" x-cloak class="mt-2 flex gap-2">
                                        <?= $csrfField ?><input type="hidden" name="decision" value="reject">
                                        <input name="note" required maxlength="255" placeholder="Why? The company sees this." class="flex-1 rounded border border-slate-200 px-2.5 py-1.5 text-sm">
                                        <button class="<?= $btn ?> bg-rose-600 text-white">Reject document</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="ui-card p-5">
                    <h2 class="font-semibold">Users (<?= count($users) ?>)</h2>
                    <ul class="mt-2 divide-y divide-slate-100 text-sm">
                        <?php foreach ($users as $u): ?>
                            <li class="flex flex-wrap items-center gap-3 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium"><?= $h($u['name']) ?><?= (int) $u['is_owner'] === 1 ? ' <span class="text-xs text-slate-500">(owner)</span>' : '' ?><?= $u['status'] === 'disabled' ? ' <span class="text-xs text-rose-600">disabled</span>' : '' ?></div>
                                    <div class="text-xs text-slate-500"><?= $h($u['email']) ?> · <?= $h(implode(', ', array_column($u['roles'], 'name'))) ?> · <?= !empty($u['mfa_enabled_at']) ? 'two-step on' : 'two-step not set' ?></div>
                                </div>
                                <?php if ($scope['reset'] && !empty($u['mfa_enabled_at'])): ?>
                                    <form method="post" action="/admin/corporate/companies/<?= $id ?>/users/<?= (int) $u['id'] ?>/reset-mfa"
                                          onsubmit="return confirm('Reset two-step sign-in for this user? Only do this after checking their identity (e.g. call back on the number we hold).');">
                                        <?= $csrfField ?><button class="<?= $btn ?> bg-white text-slate-700 ring-1 ring-slate-200">Reset two-step sign-in</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mt-2 text-xs text-slate-500">Reset two-step sign-in only after verifying who is asking (call back on the company's registered number). It's logged in the company's activity log as "eClinicPro team".</p>
                </section>
            </div>

            <!-- Right: actions -->
            <div class="space-y-4">
                <?php if ($scope['review'] && in_array($status, ['pending', 'rejected'], true)): ?>
                    <section class="ui-card p-5" x-data="{ rej: false }">
                        <h2 class="font-semibold">Review</h2>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li><?= \App\Services\Corporate\CompanyProfileService::isComplete($company) ? '✅' : '⚠️' ?> Profile complete (address, city, registration no.)</li>
                            <li><?= $approvedDocs > 0 ? '✅' : '⚠️' ?> At least one document approved</li>
                            <li><?= !empty($users[0]['email_verified_at']) || count(array_filter($users, static fn ($u) => !empty($u['email_verified_at']))) > 0 ? '✅' : '⚠️' ?> Owner confirmed their email</li>
                        </ul>
                        <form method="post" action="/admin/corporate/companies/<?= $id ?>/approve" class="mt-3" onsubmit="return confirm('Approve this company and start its <?= (int) $trialDays ?>-day trial?');">
                            <?= $csrfField ?><button class="<?= $btn ?> w-full bg-emerald-600 py-2 text-white">Approve and start <?= (int) $trialDays ?>-day trial</button>
                        </form>
                        <?php if ($status === 'pending'): ?>
                            <button type="button" @click="rej = !rej" class="<?= $btn ?> mt-2 w-full bg-white py-2 text-rose-700 ring-1 ring-rose-200">Reject…</button>
                            <form method="post" action="/admin/corporate/companies/<?= $id ?>/reject" x-show="rej" x-cloak class="mt-2 space-y-2">
                                <?= $csrfField ?>
                                <textarea name="reason" required maxlength="255" rows="2" placeholder="Reason (emailed to the company)" class="<?= $input ?>"></textarea>
                                <button class="<?= $btn ?> w-full bg-rose-600 py-2 text-white">Reject registration</button>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ($status === 'active' || $status === 'suspended'): ?>
                    <section class="ui-card p-5">
                        <h2 class="font-semibold">Plan &amp; trial</h2>
                        <dl class="mt-2 text-sm">
                            <?= $field('Trial ends', $company['trial_ends_at'] ? $loc->date((string) $company['trial_ends_at']) . ((int) $company['trial_extension_granted'] === 1 ? ' (extended)' : '') : null) ?>
                            <?= $field('Paid until', $company['plan_expires_at'] ? $loc->date((string) $company['plan_expires_at']) : null) ?>
                            <?= $field('Plan', $company['plan_id']) ?>
                        </dl>
                        <?php if ($scope['plan']): ?>
                            <form method="post" action="/admin/corporate/companies/<?= $id ?>/extend-trial" class="mt-3 flex items-end gap-2">
                                <?= $csrfField ?>
                                <label class="flex-1 text-xs text-slate-600">Extend trial by (days)<input name="days" type="number" min="1" max="90" value="15" class="<?= $input ?>"></label>
                                <button class="<?= $btn ?> bg-slate-800 py-2 text-white">Extend</button>
                            </form>
                            <form method="post" action="/admin/corporate/companies/<?= $id ?>/plan" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                                <?= $csrfField ?>
                                <label class="block text-xs text-slate-600">Paid until (after payment received)<input name="plan_expires_at" type="date" value="<?= $h($company['plan_expires_at'] ?? '') ?>" class="<?= $input ?>"></label>
                                <label class="block text-xs text-slate-600">Plan name / reference<input name="plan_id" maxlength="40" value="<?= $h($company['plan_id'] ?? '') ?>" placeholder="e.g. corp-annual-500" class="<?= $input ?>"></label>
                                <button class="<?= $btn ?> w-full bg-slate-800 py-2 text-white">Save plan</button>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ($scope['status'] && $status === 'active'): ?>
                    <section class="ui-card p-5" x-data="{ open: false }">
                        <h2 class="font-semibold">Suspend</h2>
                        <p class="mt-1 text-xs text-slate-500">Signs everyone out and blocks sign-in until reactivated.</p>
                        <button type="button" @click="open = !open" class="<?= $btn ?> mt-2 w-full bg-white py-2 text-rose-700 ring-1 ring-rose-200">Suspend company…</button>
                        <form method="post" action="/admin/corporate/companies/<?= $id ?>/suspend" x-show="open" x-cloak class="mt-2 space-y-2">
                            <?= $csrfField ?>
                            <textarea name="reason" required maxlength="255" rows="2" placeholder="Reason (emailed to the company)" class="<?= $input ?>"></textarea>
                            <button class="<?= $btn ?> w-full bg-rose-600 py-2 text-white">Suspend now</button>
                        </form>
                    </section>
                <?php elseif ($scope['status'] && $status === 'suspended'): ?>
                    <section class="ui-card p-5">
                        <h2 class="font-semibold">Suspended</h2>
                        <form method="post" action="/admin/corporate/companies/<?= $id ?>/reactivate" class="mt-2">
                            <?= $csrfField ?><button class="<?= $btn ?> w-full bg-emerald-600 py-2 text-white">Reactivate company</button>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="ui-card p-5">
                    <h2 class="font-semibold">Recent activity</h2>
                    <ul class="mt-2 space-y-1.5 text-xs">
                        <?php foreach ($activity as $a): ?>
                            <li><span class="text-slate-400"><?= $h($loc->dateTime((string) $a['created_at'])) ?></span><br>
                                <span class="text-slate-800"><?= $h($a['actor_type'] === 'platform_admin' ? 'eClinicPro team' : ($a['actor_name'] ?? 'System')) ?></span> ·
                                <?= $h($labels[$a['action']] ?? $a['action']) ?></li>
                        <?php endforeach; ?>
                        <?php if ($activity === []): ?><li class="text-slate-500">Nothing yet.</li><?php endif; ?>
                    </ul>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
