<?php
/** Help & Guide — role + module aware. $isClinical, $visibleModules, $role in scope. */
$has = static fn (string $m) => in_array($m, $visibleModules ?? [], true);
?>
<div class="mx-auto max-w-5xl" x-data="{ section: 'overview' }">
    <div class="grid gap-6 lg:grid-cols-[220px_1fr]">

        <!-- TOC -->
        <aside class="lg:sticky lg:top-4 lg:self-start">
            <div class="rounded-xl border bg-white p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Contents</p>
                <nav class="mt-2 space-y-0.5 text-sm">
                    <a href="#overview" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">System overview</a>
                    <a href="#roles" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Roles &amp; permissions</a>
                    <a href="#day" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">A typical clinic day</a>
                    <a href="#dashboard" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Dashboard</a>
                    <?php if ($isClinical): ?>
                    <a href="#visit" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Patient visit screen</a>
                    <a href="#symptoms" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Symptoms</a>
                    <a href="#prescription" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Prescription</a>
                    <a href="#followups" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Follow-ups</a>
                    <?php if ($has('diet')): ?>
                    <a href="#diet" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Diet plans</a>
                    <?php endif; ?>
                    <?php endif; ?>
                    <a href="#appointments" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Appointments &amp; queue</a>
                    <a href="#billing" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Billing</a>
                    <a href="#plan" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">Plan &amp; add-ons</a>
                    <a href="#faq" class="block rounded px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-emerald-700">FAQ</a>
                </nav>
            </div>
        </aside>

        <!-- Content -->
        <div class="space-y-5">

            <section id="overview" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">System overview</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    eClinicPro runs your clinic end-to-end: appointments and walk-in queue,
                    patient records, the consultation screen, prescriptions, billing, and
                    follow-ups. Everything a patient touches — from booking to invoice — lives here.
                </p>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    The product is <strong>simple by default</strong>. Your screen only shows
                    what your specialty needs; you can reveal more anytime.
                </p>
            </section>

            <section id="roles" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Roles &amp; permissions</h2>
                <table class="mt-3 w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs uppercase text-slate-500">
                            <th class="py-2">Feature</th><th>Doctor</th><th>Asst.</th><th>Reception</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600">
                        <tr class="border-b"><td class="py-1.5">View patients</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr class="border-b"><td class="py-1.5">Add / edit visit notes</td><td>✓</td><td>✓</td><td>—</td></tr>
                        <tr class="border-b"><td class="py-1.5">Prescriptions</td><td>✓</td><td>✓</td><td>—</td></tr>
                        <tr class="border-b"><td class="py-1.5">Manage queue / walk-ins</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr class="border-b"><td class="py-1.5">Billing &amp; invoices</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td class="py-1.5">Clinic settings</td><td>✓</td><td>—</td><td>—</td></tr>
                    </tbody>
                </table>
            </section>

            <section id="day" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">A typical clinic day</h2>
                <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-slate-600">
                    <li><strong>Reception</strong> opens the queue, adds walk-ins, marks patients arrived.</li>
                    <li><strong>Doctor</strong> opens a patient → fills the single-screen visit → saves.</li>
                    <li>At save, the doctor sets a <strong>follow-up date</strong> if needed.</li>
                    <li><strong>Reception</strong> collects payment and prints/shares the invoice.</li>
                    <li>Returning patients show a <strong>follow-up badge</strong> in the queue.</li>
                </ol>
            </section>

            <section id="dashboard" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Dashboard</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Top tiles: patients today, pending appointments, revenue today, follow-ups due.
                    The <strong>Follow-ups</strong> widget lists overdue patients so none slip through.
                </p>
            </section>

            <?php if ($isClinical): ?>
            <section id="visit" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Patient visit screen</h2>
                <p class="mt-2 text-sm text-slate-600">
                    One screen, no tabs. The four core sections are always visible:
                    <strong>Symptoms, Diagnosis, Prescription, Notes</strong>. Optional sections
                    (<?php
                        $opt = [];
                        if ($has('vitals')) $opt[] = 'Vitals';
                        if ($has('labs')) $opt[] = 'Labs';
                        if ($has('photos')) $opt[] = 'Photos';
                        if ($has('diet')) $opt[] = 'Diet';
                        if ($has('consent')) $opt[] = 'Consent';
                        if ($has('case_specialty')) $opt[] = 'Case taking';
                        echo htmlspecialchars(implode(', ', $opt) ?: 'none for your specialty');
                    ?>) appear based on your specialty. The form <strong>auto-saves</strong> every
                    30 seconds and when you switch tabs — you never lose work.
                </p>
                <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
                    💡 <strong>Same as last visit</strong> copies the prior visit's symptoms and
                    prescription into the current draft — perfect for follow-ups.
                </div>
            </section>

            <section id="symptoms" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Symptoms</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Type to search; matches appear ranked for your specialty. Press <strong>Enter</strong>
                    to add the top match as a chip. If a symptom isn't in the list, type it and choose
                    <em>"Add as custom"</em>. Your custom symptoms are remembered for next time.
                </p>
            </section>

            <section id="prescription" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Prescription</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Pick a medicine, set frequency with the <strong>1-0-1</strong> presets, days, and
                    food timing. For tapering doses, open the <strong>⋮ drawer</strong> on a row and
                    add steps (e.g. 3 days 1-1-1, then 3 days 1-0-1). Save common combinations as
                    <strong>templates</strong> — apply them in one tap on future visits.
                </p>
            </section>

            <section id="followups" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Follow-ups</h2>
                <p class="mt-2 text-sm text-slate-600">
                    At the bottom of the Notes section, tap a date chip (<strong>+3d, +5d, +1w, +2w</strong>)
                    or pick a custom date, then choose a reason. The patient appears on your dashboard's
                    overdue widget, and reception sees a badge in the queue when they next visit.
                </p>
                <div class="mt-3 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-900">
                    With the <strong>Patient Connect</strong> add-on, follow-up reminders go out
                    automatically over WhatsApp.
                </div>
            </section>

            <?php if ($has('diet')): ?>
            <section id="diet" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Diet plans</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Pick a ready-made plan (diabetic, low-salt, weight-loss, etc.), tweak it for the
                    patient, and save your edits as a personal template. Share it on WhatsApp, or copy
                    a link if you don't have the Patient Connect add-on.
                </p>
            </section>
            <?php endif; ?>
            <?php endif; ?>

            <section id="appointments" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Appointments &amp; queue</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Patients book online or walk in. Reception adds them to today's queue and marks
                    them arrived; the doctor calls them from the queue. Status flows:
                    <strong>waiting → in consultation → done</strong>.
                </p>
            </section>

            <section id="billing" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Billing</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Generate GST-compliant invoices per visit. Collect payment by cash, UPI, or card.
                    Invoices can be printed or shared with the patient.
                </p>
            </section>

            <section id="plan" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">Plan &amp; add-ons</h2>
                <p class="mt-2 text-sm text-slate-600">
                    One plan, ₹1,499/month, includes everything to run your clinic. Two optional
                    add-ons: <strong>Patient Connect</strong> (WhatsApp automation) and
                    <strong>Clinic Network</strong> (extra branches). Manage these under
                    <a href="/settings?tab=subscription" class="text-emerald-700 hover:underline">Settings → Subscription</a>.
                </p>
            </section>

            <section id="faq" class="scroll-mt-4 rounded-xl border bg-white p-6">
                <h2 class="text-lg font-bold text-slate-900">FAQ</h2>
                <div class="mt-3 space-y-3 text-sm" x-data="{ open: null }">
                    <?php
                    $faqs = [
                        ['Do I lose work if my tab closes?', 'No. The visit screen auto-saves a draft every 30 seconds and when you switch away. Reopen the visit and your work is there.'],
                        ['How do I see a different specialty section?', 'Tap "+ Add: …" at the bottom of the visit form. If you use a section 3 times, it stays visible from then on.'],
                        ['Can reception see clinical notes?', 'No. Reception sees the queue, patient info, and billing — not the clinical visit form.'],
                        ['How does "Same as last visit" work?', 'It copies the patient\'s previous completed visit\'s symptoms and prescription into the current draft. Edit anything, then save.'],
                        ['Where do my custom symptoms go?', 'They are saved to your personal list and suggested first next time. If many doctors use the same one, our team may add it to the shared library.'],
                    ];
                    foreach ($faqs as $i => $faq): ?>
                    <div class="rounded-lg border">
                        <button type="button" @click="open === <?= $i ?> ? open = null : open = <?= $i ?>"
                                class="flex w-full items-center justify-between px-3 py-2 text-left font-medium text-slate-800">
                            <span><?= htmlspecialchars($faq[0]) ?></span>
                            <span x-text="open === <?= $i ?> ? '−' : '+'"></span>
                        </button>
                        <div x-show="open === <?= $i ?>" x-collapse class="px-3 pb-3 text-slate-600">
                            <?= htmlspecialchars($faq[1]) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <p class="px-2 text-center text-xs text-slate-400">
                Last updated <?= date('M Y') ?> · Need more help? WhatsApp our support line.
            </p>
        </div>
    </div>
</div>
