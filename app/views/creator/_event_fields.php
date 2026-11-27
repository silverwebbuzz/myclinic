<?php
/**
 * Camp / event form fields — shared by Creator Studio (creator/event_form.php)
 * and the admin "list as eClinicPro Desk" page (admin/wellness_event_new.php).
 * Plain Tailwind colours so it looks right in both shells. Needs Alpine.
 *
 * @var array<string,mixed> $form
 * @var list<array{slug:string,name:string}> $categories
 * @var list<array{name:string,slug:string,state:string}> $cities
 * @var bool $isAdmin
 */
use App\Services\Wellness\EventService as ES;

$fe = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fv = static fn (string $k): string => htmlspecialchars((string) ($form[$k] ?? ''), ENT_QUOTES, 'UTF-8');
$kind = (string) $form['kind'];
$isCamp = $kind === 'free_camp';
$in = 'mt-1 h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-[13px] outline-none focus:border-emerald-600';
$ta = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-[13px] outline-none focus:border-emerald-600';
$lb = 'block text-[12.5px] font-medium text-slate-600';
$box = 'rounded-[10px] border border-slate-200 bg-white p-4 md:p-5';
$hint = 'mt-1 block text-[11.5px] font-normal text-slate-500';
$state = [
    'mode' => (string) ($form['mode'] ?? 'in_person'),
    'fee' => (string) ($form['fee_type'] ?? 'free'),
    'join' => (string) ($form['join_mode'] ?? 'walk_in'),
    'city' => (string) ($form['city_slug'] ?? ''),
];
?>
<div class="space-y-4" x-data='<?= $fe(json_encode($state)) ?>'>
    <input type="hidden" name="kind" value="<?= $fe($kind) ?>">

    <section class="<?= $box ?>">
        <h2 class="text-[14px] font-semibold text-slate-900">About the <?= $isCamp ? 'camp' : 'event' ?></h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="<?= $lb ?> sm:col-span-2">Title
                <input name="title" value="<?= $fv('title') ?>" maxlength="160" required class="<?= $in ?>"
                       placeholder="<?= $isCamp ? 'Free eye check-up camp for senior citizens' : 'Diabetes care workshop for families' ?>"></label>
            <label class="<?= $lb ?>">Category
                <select name="category_slug" required class="<?= $in ?>">
                    <option value="">Choose…</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $fe($cat['slug']) ?>" <?= ($form['category_slug'] ?? '') === $cat['slug'] ? 'selected' : '' ?>><?= $fe($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="<?= $lb ?>">Who is it for?
                <select name="audience" class="<?= $in ?>">
                    <?php foreach (ES::AUDIENCES as $k => $l): ?>
                        <option value="<?= $fe($k) ?>" <?= ($form['audience'] ?? 'all') === $k ? 'selected' : '' ?>><?= $fe($l) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="<?= $lb ?> sm:col-span-2">One-line summary
                <input name="summary" value="<?= $fv('summary') ?>" maxlength="300" class="<?= $in ?>"
                       placeholder="<?= $isCamp ? 'Free eye tests and spectacle advice by our ophthalmologists.' : 'A 2-hour session on food, exercise and sugar checks.' ?>"></label>
            <label class="<?= $lb ?> sm:col-span-2">Details
                <textarea name="description" rows="6" maxlength="5000" class="<?= $ta ?>" placeholder="What happens there, which tests or talks, what to bring, who the doctors / speakers are…"><?= $fv('description') ?></textarea>
                <span class="<?= $hint ?>">Describe what it is — no promises of cures or results (Creator terms §2).</span></label>
            <label class="<?= $lb ?>">Languages (optional)
                <input name="languages" value="<?= $fv('languages') ?>" maxlength="120" class="<?= $in ?>" placeholder="Hindi, Gujarati, English"></label>
            <label class="<?= $lb ?>">Poster (optional · JPG, PNG, WebP up to 3 MB)
                <input type="file" name="poster" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-[12.5px]"></label>
        </div>
    </section>

    <section class="<?= $box ?>">
        <h2 class="text-[14px] font-semibold text-slate-900">When</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <label class="<?= $lb ?>">Start date
                <input type="date" name="start_date" value="<?= $fv('start_date') ?>" required class="<?= $in ?>"></label>
            <label class="<?= $lb ?>">Start time
                <input type="time" name="start_time" value="<?= $fv('start_time') ?>" required class="<?= $in ?>"></label>
            <label class="<?= $lb ?>">End date
                <input type="date" name="end_date" value="<?= $fv('end_date') ?>" class="<?= $in ?>"></label>
            <label class="<?= $lb ?>">End time
                <input type="time" name="end_time" value="<?= $fv('end_time') ?>" required class="<?= $in ?>"></label>
        </div>
        <p class="mt-2 text-[11.5px] text-slate-500">Leave the end date empty if it ends the same day. Up to 31 days. For a camp held every week, post each date separately.</p>
    </section>

    <section class="<?= $box ?>">
        <h2 class="text-[14px] font-semibold text-slate-900">Where</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach (ES::MODES as $k => $l): ?>
                <label class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-[13px]" :class="mode === '<?= $k ?>' ? '!border-emerald-600 bg-emerald-50' : ''">
                    <input type="radio" name="mode" value="<?= $fe($k) ?>" x-model="mode" class="accent-emerald-600"> <?= $fe($l) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2" x-show="mode !== 'online'">
            <label class="<?= $lb ?> sm:col-span-2">Venue name
                <input name="venue_name" value="<?= $fv('venue_name') ?>" maxlength="160" class="<?= $in ?>" placeholder="Community hall / hospital / school name"></label>
            <label class="<?= $lb ?> sm:col-span-2">Address
                <input name="address" value="<?= $fv('address') ?>" maxlength="300" class="<?= $in ?>" :required="mode !== 'online'"></label>
            <label class="<?= $lb ?>">City
                <select name="city_slug" x-model="city" class="<?= $in ?>" :required="mode !== 'online'">
                    <option value="">Choose…</option>
                    <?php foreach ($cities as $ct): ?>
                        <option value="<?= $fe($ct['slug']) ?>" <?= $state['city'] === $ct['slug'] ? 'selected' : '' ?>><?= $fe($ct['name']) ?><?= $ct['state'] ? ', ' . $fe($ct['state']) : '' ?></option>
                    <?php endforeach; ?>
                    <option value="_other" <?= $state['city'] === '_other' ? 'selected' : '' ?>>Other city / town…</option>
                </select></label>
            <label class="<?= $lb ?>" x-show="city === '_other'">City / town name
                <input name="city_other" value="<?= $fv('city_other') ?>" maxlength="80" class="<?= $in ?>" :required="city === '_other' && mode !== 'online'"></label>
            <label class="<?= $lb ?>">State
                <input name="state" value="<?= $fv('state') ?>" maxlength="80" class="<?= $in ?>"></label>
            <label class="<?= $lb ?>">Pincode
                <input name="pincode" value="<?= $fv('pincode') ?>" maxlength="6" inputmode="numeric" class="<?= $in ?>"></label>
            <label class="<?= $lb ?> sm:col-span-2">Google Maps link (optional)
                <input name="map_url" value="<?= $fv('map_url') ?>" maxlength="500" class="<?= $in ?>" placeholder="https://maps.app.goo.gl/…">
                <span class="<?= $hint ?>">In Google Maps: find the venue → Share → Copy link.</span></label>
        </div>
        <label class="<?= $lb ?> mt-3" x-show="mode !== 'in_person'" x-cloak>Joining link (Zoom / Meet / YouTube live)
            <input name="online_url" value="<?= $fv('online_url') ?>" maxlength="500" class="<?= $in ?>" placeholder="https://">
            <span class="<?= $hint ?>">Shown only to people who sign in.</span></label>
    </section>

    <section class="<?= $box ?>">
        <h2 class="text-[14px] font-semibold text-slate-900">Fee &amp; how to join</h2>
        <?php if ($isCamp): ?>
            <p class="mt-2 rounded-md bg-emerald-50 px-3 py-2 text-[12.5px] text-emerald-800"><strong>Free camp:</strong> no fee of any kind for attendees — registration, tests or consultation. If anything is charged, post it as an Event instead.</p>
        <?php else: ?>
            <div class="mt-3 flex flex-wrap gap-2">
                <label class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-[13px]" :class="fee === 'free' ? '!border-emerald-600 bg-emerald-50' : ''"><input type="radio" name="fee_type" value="free" x-model="fee" class="accent-emerald-600"> Free entry</label>
                <label class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-[13px]" :class="fee === 'paid' ? '!border-emerald-600 bg-emerald-50' : ''"><input type="radio" name="fee_type" value="paid" x-model="fee" class="accent-emerald-600"> Paid ticket</label>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2" x-show="fee === 'paid'" x-cloak>
                <label class="<?= $lb ?>">Ticket price (₹)
                    <input name="fee_amount" value="<?= $fv('fee_amount') !== '0.00' ? $fv('fee_amount') : '' ?>" inputmode="decimal" class="<?= $in ?>" :required="fee === 'paid'"></label>
                <label class="<?= $lb ?>">Price note (optional)
                    <input name="fee_note" value="<?= $fv('fee_note') ?>" maxlength="160" class="<?= $in ?>" placeholder="Early bird ₹299 till 10 Dec"></label>
            </div>
        <?php endif; ?>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="<?= $lb ?>">How do people join?
                <select name="join_mode" x-model="join" class="<?= $in ?>">
                    <?php foreach (ES::JOIN_MODES as $k => $l): ?>
                        <option value="<?= $fe($k) ?>" <?= $state['join'] === $k ? 'selected' : '' ?>><?= $fe($l) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="<?= $lb ?>" x-show="join === 'register'" x-cloak>Registration link
                <input name="register_url" value="<?= $fv('register_url') ?>" maxlength="500" class="<?= $in ?>" placeholder="https://" :required="join === 'register'"></label>
            <label class="<?= $lb ?>">Contact person
                <input name="contact_name" value="<?= $fv('contact_name') ?>" maxlength="120" class="<?= $in ?>"></label>
            <label class="<?= $lb ?>">Contact number
                <input name="contact_phone" value="<?= $fe(preg_replace('/^\+91/', '', (string) ($form['contact_phone'] ?? ''))) ?>" maxlength="15" inputmode="tel" class="<?= $in ?>" placeholder="98765 43210">
                <span class="<?= $hint ?>">Shown only to people who sign in to eClinicPro — keeps it away from spam bots.</span></label>
        </div>
    </section>

    <?php if ($isAdmin): ?>
        <section class="<?= $box ?>">
            <h2 class="text-[14px] font-semibold text-slate-900">Listed on behalf of</h2>
            <label class="<?= $lb ?> mt-2">Organised by
                <input name="organised_by" value="<?= $fv('organised_by') ?>" maxlength="160" required class="<?= $in ?>" placeholder="District Health Society, Surat"></label>
            <p class="mt-1 text-[11.5px] text-slate-500">Shown as “Organised by …” with “Listed by eClinicPro Desk”. Only list camps you found in an official source.</p>
        </section>
    <?php else: ?>
        <section class="<?= $box ?> space-y-2">
            <label class="flex items-start gap-2.5 text-[13px] text-slate-700">
                <input type="checkbox" name="confirm_genuine" value="1" class="mt-0.5 accent-emerald-600">
                <span>The details are true, and I or my organisation is running this <?= $isCamp ? 'camp' : 'event' ?>.</span>
            </label>
            <?php if ($isCamp): ?>
                <label class="flex items-start gap-2.5 text-[13px] text-slate-700">
                    <input type="checkbox" name="confirm_free" value="1" class="mt-0.5 accent-emerald-600">
                    <span>It is completely free for everyone who attends — no registration, test or consultation fee.</span>
                </label>
            <?php endif; ?>
            <p class="text-[11.5px] text-slate-500">Needed only when you send it for review.</p>
        </section>
    <?php endif; ?>
</div>
