<?php
/**
 * Clinic app › Settings › Corporate work (/settings/corporate).
 *
 * @var bool $enabled
 * @var list<array<string,mixed>> $links    companies (requested / active / declined / ended)
 * @var list<array<string,mixed>> $doctors  + credential columns (id null = none yet)
 * @var string $clinicCountry
 * @var list<array<string,mixed>> $countries
 * @var string|null $flashOk
 * @var string|null $flashErr
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf ?? '') . '">';
$in = 'mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none';
$d = static fn (?string $ymd): string => $ymd ? date('d M Y', strtotime($ymd)) : '';
$coName = static fn (array $l): string => (string) (($l['trade_name'] ?? '') ?: $l['legal_name']);
$requests = array_values(array_filter($links, static fn ($l) => $l['status'] === 'requested'));
$active = array_values(array_filter($links, static fn ($l) => $l['status'] === 'active'));
$past = array_values(array_filter($links, static fn ($l) => in_array($l['status'], ['declined', 'ended'], true)));
$credPill = ['verified' => ['Verified', 'bg-emerald-50 text-emerald-800'], 'pending' => ['Waiting for eClinicPro', 'bg-amber-50 text-amber-800'], 'rejected' => ['Not accepted', 'bg-red-50 text-red-800']];
$countryNames = array_column($countries, 'name', 'code');
?>
<?= ui_page_header('Corporate work', 'Companies that send their employees to your clinic for health checks (eClinicPro Workforce Health).') ?>

<div class="mx-auto max-w-4xl space-y-4">
    <?php if (!empty($flashOk)): ?><p class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= $h($flashOk) ?></p><?php endif; ?>
    <?php if (!empty($flashErr)): ?><p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800"><?= $h($flashErr) ?></p><?php endif; ?>

    <?php if (!$enabled): ?>
        <div class="ui-card ui-card-pad">
            <h2 class="font-semibold text-slate-900">Not switched on</h2>
            <p class="mt-1 text-sm text-slate-600">Companies can book pre-employment and periodic health checks for their employees at your clinic, and your doctors issue the fitness certificate from the normal visit screen.
                To join, contact eClinicPro support. We switch it on after a short call.</p>
        </div>
    <?php else: ?>

    <div class="ui-card ui-card-pad">
        <h2 class="font-semibold text-slate-900">Requests <?= $requests !== [] ? '<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">' . count($requests) . '</span>' : '' ?></h2>
        <?php if ($requests === []): ?>
            <p class="mt-1 text-sm text-slate-500">No new requests.</p>
        <?php else: ?>
            <ul class="mt-3 divide-y divide-slate-100">
                <?php foreach ($requests as $l): ?>
                    <li class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1 basis-full sm:basis-0">
                            <div class="font-medium text-slate-900"><?= $h($coName($l)) ?> <span class="text-xs font-normal text-slate-500"><?= $h($l['city'] ?? '') ?> · about <?= number_format((int) $l['employees']) ?> employees</span></div>
                            <div class="text-sm text-slate-600">Contact: <?= $h($l['contact_name'] ?? '') ?><?= !empty($l['contact_phone']) ? ' · ' . $h($l['contact_phone']) : '' ?><?= !empty($l['contact_email']) ? ' · ' . $h($l['contact_email']) : '' ?></div>
                            <?php if (!empty($l['note'])): ?><div class="mt-1 text-sm text-slate-500">"<?= $h($l['note']) ?>"</div><?php endif; ?>
                        </div>
                        <form method="post" action="/settings/corporate/links/<?= (int) $l['id'] ?>/accept"><?= $csrfField ?><button class="ui-btn ui-btn-primary">Accept</button></form>
                        <form method="post" action="/settings/corporate/links/<?= (int) $l['id'] ?>/decline" onsubmit="return confirm('Decline this company?')"><?= $csrfField ?><button class="ui-btn ui-btn-secondary">Decline</button></form>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-2 text-xs text-slate-500">Accept only companies you have agreed terms with. They pay you directly; eClinicPro doesn't handle the money.</p>
        <?php endif; ?>
    </div>

    <div class="ui-card ui-card-pad">
        <h2 class="font-semibold text-slate-900">Companies</h2>
        <?php if ($active === []): ?>
            <p class="mt-1 text-sm text-slate-500">No companies yet.</p>
        <?php else: ?>
            <ul class="mt-3 divide-y divide-slate-100">
                <?php foreach ($active as $l): ?>
                    <li class="py-3" x-data="{ end: false }">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-0 flex-1 basis-full sm:basis-0">
                                <div class="font-medium text-slate-900"><?= $h($coName($l)) ?></div>
                                <div class="text-sm text-slate-600"><?= $h($l['contact_name'] ?? '') ?><?= !empty($l['contact_phone']) ? ' · ' . $h($l['contact_phone']) : '' ?> · since <?= $h($d(substr((string) $l['updated_at'], 0, 10))) ?></div>
                            </div>
                            <button type="button" @click="end = !end" class="text-sm text-red-600 hover:underline">End</button>
                        </div>
                        <form x-show="end" x-cloak method="post" action="/settings/corporate/links/<?= (int) $l['id'] ?>/end" class="mt-2 flex flex-wrap gap-2">
                            <?= $csrfField ?>
                            <input name="reason" required minlength="5" maxlength="500" placeholder="Why? (the company sees this)" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <button class="ui-btn ui-btn-danger">End arrangement</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($past !== []): ?>
            <details class="mt-3 text-sm"><summary class="cursor-pointer text-slate-500">Declined and ended (<?= count($past) ?>)</summary>
                <ul class="mt-2 space-y-1 text-slate-600">
                    <?php foreach ($past as $l): ?><li><?= $h($coName($l)) ?> · <?= $l['status'] === 'declined' ? 'declined' : 'ended' ?><?= !empty($l['ended_reason']) ? ': ' . $h($l['ended_reason']) : '' ?></li><?php endforeach; ?>
                </ul></details>
        <?php endif; ?>
    </div>

    <div class="ui-card ui-card-pad" id="doctors" x-data="{ edit: null }">
        <h2 class="font-semibold text-slate-900">Doctors' registration</h2>
        <p class="mt-1 text-sm text-slate-600">Each doctor who signs fitness certificates needs a medical registration that eClinicPro has verified. It's printed on every certificate. An expired registration stops signing until it's renewed here.</p>
        <ul class="mt-3 divide-y divide-slate-100">
            <?php foreach ($doctors as $doc): $uid = (int) $doc['user_id']; ?>
                <li class="py-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-0 flex-1 basis-full sm:basis-0">
                            <div class="font-medium text-slate-900"><?= $h($doc['name']) ?> <span class="text-xs font-normal text-slate-500"><?= $h($doc['qualification'] ?? '') ?></span></div>
                            <?php if ($doc['id'] !== null): ?>
                                <div class="text-sm text-slate-600"><?= $h($doc['reg_number']) ?> · <?= $h($doc['authority']) ?> · <?= $h($countryNames[$doc['country_code']] ?? $doc['country_code']) ?><?= $doc['valid_to'] ? ' · valid to ' . $h($d((string) $doc['valid_to'])) : '' ?>
                                    <?php if (!empty($doc['document_name'])): ?> · <a class="underline" target="_blank" href="/settings/corporate/credentials/<?= (int) $doc['id'] ?>/file">document</a><?php endif; ?></div>
                                <?php if ($doc['status'] === 'rejected' && !empty($doc['review_note'])): ?><div class="text-sm text-red-700">eClinicPro: <?= $h($doc['review_note']) ?></div><?php endif; ?>
                            <?php else: ?>
                                <div class="text-sm text-slate-500">No registration added.</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($doc['id'] !== null): $p = $credPill[$doc['status']] ?? $credPill['pending']; ?>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $p[1] ?>"><?= $h($p[0]) ?></span>
                        <?php endif; ?>
                        <button type="button" @click="edit = edit === <?= $uid ?> ? null : <?= $uid ?>" class="ui-btn ui-btn-secondary"><?= $doc['id'] === null ? 'Add' : 'Update' ?></button>
                    </div>
                    <form x-show="edit === <?= $uid ?>" x-cloak method="post" action="/settings/corporate/credentials" enctype="multipart/form-data" class="mt-3 grid gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-2">
                        <?= $csrfField ?>
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <label class="text-xs text-slate-600">Registration number<input name="reg_number" required maxlength="60" value="<?= $h($doc['reg_number'] ?? '') ?>" class="<?= $in ?>"></label>
                        <label class="text-xs text-slate-600">Council / authority<input name="authority" required maxlength="150" value="<?= $h($doc['authority'] ?? '') ?>" placeholder="e.g. Maharashtra Medical Council" class="<?= $in ?>"></label>
                        <label class="text-xs text-slate-600">Country<select name="country_code" class="<?= $in ?>">
                            <?php foreach ($countries as $c): $sel = (string) ($doc['country_code'] ?? $clinicCountry) === (string) $c['code']; ?>
                                <option value="<?= $h($c['code']) ?>" <?= $sel ? 'selected' : '' ?>><?= $h($c['name']) ?></option>
                            <?php endforeach; ?></select></label>
                        <label class="text-xs text-slate-600">Valid until <span class="text-slate-400">(leave empty if it doesn't expire)</span><input type="date" name="valid_to" value="<?= $h($doc['valid_to'] ?? '') ?>" class="<?= $in ?>"></label>
                        <label class="text-xs text-slate-600 sm:col-span-2">Copy of the registration certificate <?= $doc['id'] !== null ? '<span class="text-slate-400">(only to replace it)</span>' : '' ?>
                            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" <?= $doc['id'] === null ? 'required' : '' ?> class="mt-1 block w-full text-sm"></label>
                        <div class="sm:col-span-2"><button class="ui-btn ui-btn-primary">Save</button>
                            <span class="ml-2 text-xs text-slate-500">Any change is checked again by eClinicPro.</span></div>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if ($doctors === []): ?><li class="py-3 text-sm text-slate-500">No doctors in your team. Add them in Team first.</li><?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
