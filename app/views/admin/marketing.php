<?php
/** /admin/marketing — doctor list + manual WhatsApp drafts (super-admin). */
$f = $filters;
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

// Current filters as a querystring (pagination + sort links keep them).
$qsParts = array_filter($f, fn ($v) => $v !== '' && $v !== []);
$mk = static fn (array $over): string => '/admin/marketing?' . http_build_query(array_merge($qsParts, $over));
$sortLink = static function (string $key, string $label) use ($f, $mk, $h): string {
    $active = $f['sort'] === $key;
    $dir = $active && $f['dir'] === 'desc' ? 'asc' : 'desc';
    $arrow = $active ? ($f['dir'] === 'asc' ? ' ↑' : ' ↓') : '';
    return '<a href="' . $h($mk(['sort' => $key, 'dir' => $dir, 'page' => 1])) . '" class="hover:text-slate-800">' . $h($label) . $arrow . '</a>';
};
$selectedSpecs = array_flip($f['spec']);

$sel = static fn (string $cur, string $val): string => $cur === $val ? 'selected' : '';

// Per-row data for the message modal.
$modalData = [];
foreach ($rows as $r) {
    $modalData[(int) $r['id']] = [
        'name' => (string) ($r['doctor_name'] ?: $r['name']),
        'clinic' => (string) $r['name'],
        'specialty' => (string) $r['specialty_label'],
        'city' => trim((string) $r['city'] . ($r['state'] ? ', ' . $r['state'] : '')),
        'phone' => (string) ($r['intl_phone'] ?: $r['phone']),
        'wa' => (string) $r['wa_digits'],
        'profile' => (string) ($r['profile_url'] ?? ''),
        'message' => (string) $r['message'],
        'template' => $r['template_key'] === 'default' ? 'Default' : (string) $r['specialty_label'],
        'sent_at' => $r['last_contacted_at'] ? date('d M Y, h:i A', strtotime((string) $r['last_contacted_at'])) : '',
        'opted_out' => (int) $r['opted_out'] === 1,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marketing · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-7xl px-4 sm:px-6 py-6">
        <div class="flex items-baseline justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold">Doctor marketing</h1>
                <p class="text-sm text-slate-500">Filter listings, open a doctor's draft, send it yourself on WhatsApp, then mark it sent. <?= (int) $perPage ?> listings per page.</p>
            </div>
            <a href="/admin/marketing/templates" class="rounded border bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">✎ Message templates</a>
        </div>

        <!-- Filters -->
        <form method="get" action="/admin/marketing" class="mt-4 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="text-xs sm:col-span-2 lg:col-span-4">
                <span class="text-slate-600">Specialty <span class="text-slate-400">(pick one or more · counts = all / unclaimed)</span></span>
                <details class="mt-1 rounded border">
                    <summary class="cursor-pointer select-none px-2 py-1.5 text-sm">
                        <?php if ($f['spec']):
                            $names = [];
                            foreach ($options['specialties'] as $s) {
                                if (isset($selectedSpecs[$s['code']])) $names[] = $s['label'];
                            } ?>
                            <?= $h(implode(', ', $names)) ?>
                        <?php else: ?>
                            <span class="text-slate-500">All specialties</span>
                        <?php endif; ?>
                    </summary>
                    <div class="border-t p-2">
                        <input type="search" placeholder="Search specialty…" class="mb-2 w-full rounded border px-2 py-1 text-sm" oninput="filterSpecs(this.value)">
                        <div id="specList" class="grid max-h-64 gap-x-4 gap-y-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($options['specialties'] as $s): ?>
                            <label class="flex items-center gap-2 text-sm" data-label="<?= $h(strtolower($s['label'] . ' ' . $s['code'])) ?>">
                                <input type="checkbox" name="spec[]" value="<?= $h($s['code']) ?>" <?= isset($selectedSpecs[$s['code']]) ? 'checked' : '' ?>>
                                <span class="flex-1"><?= $h($s['label']) ?></span>
                                <span class="text-[11px] text-slate-400"><?= number_format($s['total']) ?> / <?= number_format($s['unclaimed']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            </div>

            <label class="block text-xs"><span class="text-slate-600">State</span>
                <select name="state" class="mt-1 w-full rounded border px-2 py-1.5 text-sm" onchange="this.form.city.value='';this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($options['states'] as $s): ?>
                    <option value="<?= $h($s) ?>" <?= $sel($f['state'], $s) ?>><?= $h($s) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">City</span>
                <input type="text" name="city" list="cityList" value="<?= $h($f['city']) ?>" placeholder="Any city" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                <datalist id="cityList">
                    <?php foreach ($options['cities'] as $c): ?><option value="<?= $h($c) ?>"><?php endforeach; ?>
                </datalist></label>
            <label class="block text-xs"><span class="text-slate-600">Doctor / clinic name</span>
                <input type="text" name="q" value="<?= $h($f['q']) ?>" placeholder="Search name" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
            <label class="block text-xs"><span class="text-slate-600">Phone</span>
                <select name="phone" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">Any</option>
                    <option value="mobile" <?= $sel($f['phone'], 'mobile') ?>>Valid mobile (WhatsApp-able)</option>
                    <option value="other" <?= $sel($f['phone'], 'other') ?>>Landline / other</option>
                    <option value="none" <?= $sel($f['phone'], 'none') ?>>No phone</option>
                </select></label>

            <label class="block text-xs"><span class="text-slate-600">Claimed</span>
                <select name="claimed" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="0" <?= $sel($f['claimed'], '0') ?>>Unclaimed (not joined)</option>
                    <option value="1" <?= $sel($f['claimed'], '1') ?>>Claimed (joined)</option>
                    <option value="all" <?= $sel($f['claimed'], 'all') ?>>All</option>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">Profile</span>
                <select name="listed" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">Any</option>
                    <option value="1" <?= $sel($f['listed'], '1') ?>>Published</option>
                    <option value="0" <?= $sel($f['listed'], '0') ?>>Unpublished</option>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">WhatsApp message</span>
                <select name="sent" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">Any</option>
                    <option value="no" <?= $sel($f['sent'], 'no') ?>>Not sent</option>
                    <option value="yes" <?= $sel($f['sent'], 'yes') ?>>Sent</option>
                </select></label>
            <label class="block text-xs"><span class="text-slate-600">Patient leads</span>
                <select name="leads" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="">Any</option>
                    <option value="yes" <?= $sel($f['leads'], 'yes') ?>>Has booking requests</option>
                </select></label>

            <label class="block text-xs"><span class="text-slate-600">Sent from</span>
                <input type="date" name="sent_from" value="<?= $h($f['sent_from']) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
            <label class="block text-xs"><span class="text-slate-600">Sent to</span>
                <input type="date" name="sent_to" value="<?= $h($f['sent_to']) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
            <label class="block text-xs"><span class="text-slate-600">Asked to STOP</span>
                <select name="optout" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                    <option value="all" <?= $sel($f['optout'], 'all') ?>>Show all (stopped in red)</option>
                    <option value="exclude" <?= $sel($f['optout'], 'exclude') ?>>Hide stopped</option>
                    <option value="only" <?= $sel($f['optout'], 'only') ?>>Only stopped</option>
                </select></label>
            <input type="hidden" name="sort" value="<?= $h($f['sort']) ?>">
            <input type="hidden" name="dir" value="<?= $h($f['dir']) ?>">

            <div class="flex items-end gap-2">
                <button type="submit" class="rounded bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Apply filters</button>
                <a href="/admin/marketing" class="rounded border px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Reset</a>
            </div>
        </form>

        <!-- List -->
        <section class="mt-5 overflow-hidden rounded-xl border bg-white">
            <?php if (empty($rows)): ?>
            <p class="p-6 text-sm text-slate-500">No listings match these filters.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left"><?= $sortLink('name', 'Doctor / clinic') ?></th>
                            <th class="px-4 py-3 text-left">Specialty</th>
                            <th class="px-4 py-3 text-left"><?= $sortLink('city', 'City') ?></th>
                            <th class="px-4 py-3 text-left">Mobile</th>
                            <th class="px-4 py-3 text-left">Profile</th>
                            <th class="px-4 py-3 text-right"><?= $sortLink('leads', 'Leads') ?></th>
                            <th class="px-4 py-3 text-left"><?= $sortLink('sent', 'WhatsApp') ?></th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $r):
                            $id = (int) $r['id'];
                            $display = (string) ($r['doctor_name'] ?: $r['name']);
                            $phone = (string) ($r['intl_phone'] ?: $r['phone']);
                            $stopped = (int) $r['opted_out'] === 1;
                        ?>
                        <tr class="align-top <?= $stopped ? 'bg-rose-50 hover:bg-rose-100' : 'hover:bg-slate-50' ?>" id="row-<?= $id ?>">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900"><?= $h($display) ?></div>
                                <?php if ($r['doctor_name'] && $r['name'] !== $r['doctor_name']): ?>
                                <div class="text-xs text-slate-500"><?= $h($r['name']) ?></div>
                                <?php endif; ?>
                                <?php if ($r['rating']): ?>
                                <div class="text-[11px] text-slate-400">★ <?= $h($r['rating']) ?> · <?= number_format((int) $r['reviews']) ?> reviews</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?= $h($r['specialty_label'] ?: '—') ?></td>
                            <td class="px-4 py-3 text-slate-600">
                                <?= $h($r['city'] ?: '—') ?>
                                <div class="text-[11px] text-slate-400"><?= $h(trim(($r['area'] ?? '') . ($r['state'] ? ' · ' . $r['state'] : ''), ' ·')) ?></div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if ($phone !== ''): ?>
                                <a href="tel:<?= $h($phone) ?>" class="text-emerald-700 hover:underline"><?= $h($phone) ?></a>
                                <div class="text-[11px] <?= $r['wa_digits'] ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $r['wa_digits'] ? 'Mobile' : 'Not a mobile' ?></div>
                                <?php else: ?>
                                <span class="text-slate-400">No phone</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($r['profile_url']): ?>
                                <a href="<?= $h($r['profile_url']) ?>" target="_blank" rel="noopener" class="text-sky-700 hover:underline">View ↗</a>
                                <?php endif; ?>
                                <div class="mt-0.5 flex flex-wrap gap-1">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= (int) $r['is_active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= (int) $r['is_active'] ? 'Published' : 'Unpublished' ?></span>
                                    <?php if ((int) $r['is_claimed']): ?><span class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">Claimed</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-800"><?= (int) $r['leads'] ?: '<span class="font-normal text-slate-300">0</span>' ?></td>
                            <td class="px-4 py-3 whitespace-nowrap" data-sent-cell>
                                <?php if ($stopped): ?>
                                <span class="rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-semibold text-white">STOP · don't message</span>
                                <?php elseif ($r['last_contacted_at']): ?>
                                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">Sent</span>
                                <div class="mt-0.5 text-[11px] text-slate-400"><?= $h(date('d M Y', strtotime((string) $r['last_contacted_at']))) ?><?= (int) $r['contacted_count'] > 1 ? ' · ' . (int) $r['contacted_count'] . '×' : '' ?></div>
                                <?php else: ?>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Not sent</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" onclick="openMsg(<?= $id ?>)" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Message</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($page > 1 || $hasMore): ?>
        <div class="mt-4 flex items-center justify-between text-sm">
            <span class="text-slate-500">Page <?= (int) $page ?> · listings <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(($page - 1) * $perPage + count($rows)) ?></span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                <a href="<?= $h($mk(['page' => $page - 1])) ?>" class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">← Prev</a>
                <?php endif; ?>
                <?php if ($hasMore): ?>
                <a href="<?= $h($mk(['page' => $page + 1])) ?>" class="rounded border bg-white px-3 py-1.5 hover:bg-slate-50">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Message modal -->
    <div id="msgModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" onclick="if (event.target === this) closeMsg()">
        <div class="max-h-[95vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="mName" class="text-lg font-semibold text-slate-900"></h2>
                    <p id="mMeta" class="text-xs text-slate-500"></p>
                </div>
                <button type="button" onclick="closeMsg()" class="text-xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close">×</button>
            </div>
            <div class="mt-3 grid gap-1 text-xs text-slate-600">
                <div>📞 <span id="mPhone"></span></div>
                <div id="mProfileWrap">🔗 <a id="mProfile" target="_blank" rel="noopener" class="text-sky-700 hover:underline"></a></div>
                <div id="mSent" class="text-sky-700"></div>
                <div id="mStop" class="rounded bg-rose-600 px-3 py-2 text-sm font-semibold text-white">This doctor asked us to STOP. Do not message them.
                    <button type="button" onclick="setStop(false)" class="ml-2 rounded bg-white/20 px-2 py-0.5 text-[11px] font-semibold hover:bg-white/30">Undo (marked by mistake)</button></div>
                <div id="mWarn" class="rounded bg-amber-50 px-2 py-1 text-amber-800"></div>
            </div>
            <label class="mt-3 block text-xs text-slate-600">Message (edit before sending) · template: <strong id="mTpl"></strong>
                <textarea id="mText" rows="12" class="mt-1 w-full rounded border p-2 text-sm leading-relaxed"></textarea>
            </label>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" onclick="copyMsg()" id="mCopy" class="rounded border px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Copy message</button>
                <a id="mWa" target="_blank" rel="noopener" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Open WhatsApp</a>
                <button type="button" onclick="setStop(true)" id="mStopBtn" class="rounded border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Doctor said STOP</button>
                <button type="button" onclick="markSent()" id="mMark" class="ml-auto rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">I sent it: mark sent</button>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">Nothing is sent from here. Copy or Open WhatsApp, send it yourself, then mark it sent.</p>
        </div>
    </div>

    <script type="application/json" id="msgData"><?= json_encode($modalData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script>
    const MSG = JSON.parse(document.getElementById('msgData').textContent);
    const CSRF = <?= json_encode($csrf) ?>;
    let cur = null;
    const $ = (id) => document.getElementById(id);

    function filterSpecs(q) {
        q = q.toLowerCase();
        document.querySelectorAll('#specList label').forEach(l => {
            l.style.display = l.dataset.label.includes(q) ? '' : 'none';
        });
    }

    function openMsg(id) {
        const d = MSG[id];
        if (!d) return;
        cur = id;
        $('mName').textContent = d.name;
        $('mMeta').textContent = [d.specialty, d.clinic !== d.name ? d.clinic : '', d.city].filter(Boolean).join(' · ');
        $('mPhone').textContent = d.phone || 'No phone';
        $('mProfileWrap').style.display = d.profile ? '' : 'none';
        $('mProfile').href = d.profile; $('mProfile').textContent = d.profile;
        $('mText').value = d.message;
        $('mTpl').textContent = d.template;
        renderSent(d);
        const warn = [];
        $('mStop').style.display = d.opted_out ? '' : 'none';
        $('mStopBtn').style.display = d.opted_out ? 'none' : '';
        $('mText').disabled = d.opted_out;
        $('mCopy').style.display = d.opted_out ? 'none' : '';
        if (!d.opted_out && !d.wa) warn.push('Not a mobile number, so WhatsApp is unlikely to work.');
        if (!d.profile) warn.push('No public profile, so the profile link line was left out.');
        $('mWarn').textContent = warn.join(' ');
        $('mWarn').style.display = warn.length ? '' : 'none';
        const canWa = d.wa && !d.opted_out;
        $('mWa').style.display = canWa ? '' : 'none';
        $('mMark').style.display = d.opted_out ? 'none' : '';
        $('mCopy').textContent = 'Copy message';
        $('msgModal').classList.replace('hidden', 'flex');
    }
    function closeMsg() { $('msgModal').classList.replace('flex', 'hidden'); cur = null; }
    function renderSent(d) {
        $('mSent').textContent = d.sent_at ? 'Already sent on ' + d.sent_at : '';
        $('mSent').style.display = d.sent_at ? '' : 'none';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMsg(); });

    // Uses the edited text at click time.
    $('mWa').addEventListener('click', () => {
        $('mWa').href = 'https://wa.me/' + MSG[cur].wa + '?text=' + encodeURIComponent($('mText').value);
    });

    async function copyMsg() {
        const t = $('mText');
        try { await navigator.clipboard.writeText(t.value); }
        catch (e) { t.select(); document.execCommand('copy'); }
        $('mCopy').textContent = 'Copied ✓';
    }

    async function markSent(again = false) {
        const d = MSG[cur];
        if (!again && !confirm('Did you actually send the WhatsApp message to ' + d.name + '?')) return;
        const body = new FormData();
        body.append('_csrf', CSRF);
        body.append('directory_doctor_id', cur);
        if (again) body.append('again', '1');
        const res = await fetch('/admin/marketing/sent', { method: 'POST', body });
        const j = await res.json().catch(() => ({ ok: false, error: 'network' }));
        if (!j.ok && j.error === 'already_sent') {
            if (confirm('Already marked sent on ' + j.sent_at + '. Record another message?')) return markSent(true);
            return;
        }
        if (!j.ok) { alert(j.error === 'opted_out' ? 'This doctor has opted out.' : 'Could not save (' + j.error + '). Reload and try again.'); return; }
        d.sent_at = new Date().toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        renderSent(d);
        const cell = document.querySelector('#row-' + cur + ' [data-sent-cell]');
        if (cell) cell.innerHTML = '<span class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">Sent</span><div class="mt-0.5 text-[11px] text-slate-400">just now</div>';
        closeMsg();
    }

    async function setStop(stop) {
        const d = MSG[cur];
        const q = stop
            ? d.name + ' asked us to stop messaging? They will be shown in red and blocked from messages.'
            : 'Undo STOP for ' + d.name + '? Only do this if it was marked by mistake.';
        if (!confirm(q)) return;
        const body = new FormData();
        body.append('_csrf', CSRF);
        body.append('directory_doctor_id', cur);
        body.append('stop', stop ? '1' : '0');
        const res = await fetch('/admin/marketing/stop', { method: 'POST', body });
        const j = await res.json().catch(() => ({ ok: false, error: 'network' }));
        if (!j.ok) { alert('Could not save (' + j.error + '). Reload and try again.'); return; }
        d.opted_out = stop;
        const row = $('row-' + cur);
        if (row) {
            row.classList.toggle('bg-rose-50', stop);
            row.classList.toggle('hover:bg-rose-100', stop);
            row.classList.toggle('hover:bg-slate-50', !stop);
            const cell = row.querySelector('[data-sent-cell]');
            if (cell) cell.innerHTML = stop
                ? '<span class="rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-semibold text-white">STOP · don\'t message</span>'
                : '<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">' + (d.sent_at ? 'Sent' : 'Not sent') + '</span>';
        }
        closeMsg();
    }
    </script>
</body>
</html>
