<?php
/** /admin/plans — pricing plan catalog manager (super-admin). */

/** Turn a JSON column into a textarea-friendly newline list. "all_paid" passes through. */
$listFromJson = static function ($raw): string {
    $d = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if ($d === 'all_paid') {
        return 'all_paid';
    }
    return is_array($d) ? implode("\n", $d) : '';
};

/** Compact preview of modules for the table. */
$modulesSummary = static function ($raw): string {
    $d = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if ($d === 'all_paid') {
        return 'all paid modules';
    }
    return is_array($d) ? count($d) . ' modules' : '—';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Plans — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php /* Alpine is loaded globally by admin/_nav.php */ ?>
</head>
<body class="min-h-screen bg-slate-100" x-data="{ editing: null }">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Plans</h1>
            <span class="text-sm text-slate-500"><?= count($plans) ?> total</span>
        </div>

        <?php if (!empty($message)): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars(str_replace('_', ' ', (string) $message)) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tableMissing)): ?>
        <div class="rounded bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            The <code>plans</code> table doesn't exist yet. Run
            <code>app/database/migrations/030_plans_table.sql</code> first.
            Until then the app falls back to <code>config/plans.php</code>.
        </div>
        <?php endif; ?>

        <p class="text-sm text-slate-500">
            The source of truth for pricing across onboarding, the public pricing page, and
            Cashfree checkout. <code>PlanService</code> reads active plans here and only falls
            back to <code>config/plans.php</code> if this table is empty. Prices are in
            <strong>INR</strong>. <code>modules</code> may be the literal <code>all_paid</code>
            (every core module) or one module id per line.
        </p>

        <!-- ===== Add / Edit form ===== -->
        <form method="post" action="/admin/plans"
              class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="mode" :value="editing ? 'edit' : 'create'">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold" x-text="editing ? ('Edit: ' + editing.name) : 'Add a plan'"></h2>
                <button type="button" x-show="editing" @click="editing = null"
                        class="text-xs text-slate-500 hover:underline">Cancel edit</button>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-slate-600">Plan ID <span class="text-slate-400">(immutable on edit)</span></span>
                    <input type="text" name="plan_id" :value="editing ? editing.plan_id : ''" :readonly="!!editing"
                           placeholder="practice"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm read-only:bg-slate-100">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Name</span>
                    <input type="text" name="name" :value="editing ? editing.name : ''"
                           placeholder="Practice"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Tagline</span>
                    <input type="text" name="tagline" :value="editing ? editing.tagline : ''"
                           placeholder="Multi-doctor clinic"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>

                <label class="block text-sm">
                    <span class="text-slate-600">Monthly ₹</span>
                    <input type="number" step="0.01" min="0" name="monthly_inr" :value="editing ? editing.monthly_inr : 0"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Yearly ₹</span>
                    <input type="number" step="0.01" min="0" name="yearly_inr" :value="editing ? editing.yearly_inr : 0"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Trial days</span>
                    <input type="number" min="0" name="trial_days" :value="editing ? editing.trial_days : 0"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>

                <label class="block text-sm">
                    <span class="text-slate-600">Seat limit <span class="text-slate-400">(max 255)</span></span>
                    <input type="number" min="0" max="255" name="seat_limit" :value="editing ? editing.seat_limit : 2"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Patient limit <span class="text-slate-400">(blank = unlimited)</span></span>
                    <input type="number" min="0" name="patient_limit" :value="editing ? (editing.patient_limit ?? '') : ''"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Sort order</span>
                    <input type="number" name="sort_order" :value="editing ? editing.sort_order : 100"
                           class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-slate-600">Modules <span class="text-slate-400">(all_paid, or one id per line)</span></span>
                    <textarea name="modules" rows="6"
                              placeholder="all_paid"
                              class="mt-1 w-full rounded border px-2 py-1.5 text-sm font-mono"
                              x-text="editing ? editing._modules : ''"></textarea>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Highlights <span class="text-slate-400">(one per line)</span></span>
                    <textarea name="highlights" rows="6"
                              class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                              x-text="editing ? editing._highlights : ''"></textarea>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Limits <span class="text-slate-400">(one per line)</span></span>
                    <textarea name="limits" rows="6"
                              class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                              x-text="editing ? editing._limits : ''"></textarea>
                </label>
            </div>

            <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="featured" :checked="editing ? !!editing.featured : false" value="1">
                    <span class="text-slate-600">Featured</span>
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" :checked="editing ? !!editing.is_active : true" value="1">
                    <span class="text-slate-600">Active</span>
                </label>
                <button type="submit"
                        class="ml-auto rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    <span x-text="editing ? 'Save changes' : 'Add plan'"></span>
                </button>
            </div>
        </form>

        <!-- ===== Existing plans ===== -->
        <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Plan</th>
                        <th class="px-4 py-2">Monthly</th>
                        <th class="px-4 py-2">Yearly</th>
                        <th class="px-4 py-2">Seats</th>
                        <th class="px-4 py-2">Trial</th>
                        <th class="px-4 py-2">Modules</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                <?php foreach ($plans as $p): ?>
                    <?php
                    $row = [
                        'plan_id' => $p['plan_id'],
                        'name' => $p['name'],
                        'tagline' => $p['tagline'] ?? '',
                        'monthly_inr' => (float) $p['monthly_inr'],
                        'yearly_inr' => (float) $p['yearly_inr'],
                        'seat_limit' => (int) $p['seat_limit'],
                        'patient_limit' => $p['patient_limit'] !== null ? (int) $p['patient_limit'] : null,
                        'trial_days' => (int) $p['trial_days'],
                        'featured' => (int) $p['featured'],
                        'is_active' => (int) $p['is_active'],
                        'sort_order' => (int) $p['sort_order'],
                        '_modules' => $listFromJson($p['modules'] ?? null),
                        '_highlights' => $listFromJson($p['highlights'] ?? null),
                        '_limits' => $listFromJson($p['limits'] ?? null),
                    ];
                    ?>
                    <tr>
                        <td class="px-4 py-2">
                            <div class="font-medium"><?= htmlspecialchars((string) $p['name']) ?>
                                <?php if (!empty($p['featured'])): ?>
                                <span class="ml-1 rounded bg-indigo-100 px-1.5 text-[10px] font-semibold text-indigo-700">FEATURED</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars((string) $p['plan_id']) ?></div>
                        </td>
                        <td class="px-4 py-2">₹<?= number_format((float) $p['monthly_inr']) ?></td>
                        <td class="px-4 py-2">₹<?= number_format((float) $p['yearly_inr']) ?></td>
                        <td class="px-4 py-2"><?= (int) $p['seat_limit'] ?></td>
                        <td class="px-4 py-2"><?= (int) $p['trial_days'] ?>d</td>
                        <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($modulesSummary($p['modules'] ?? null)) ?></td>
                        <td class="px-4 py-2">
                            <?php if (!empty($p['is_active'])): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">Active</span>
                            <?php else: ?>
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-600">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button"
                                        @click='editing = <?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>; window.scrollTo({top:0,behavior:"smooth"})'
                                        class="text-xs text-indigo-600 hover:underline">Edit</button>
                                <form method="post" action="/admin/plans/<?= urlencode((string) $p['plan_id']) ?>/toggle" class="inline">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <button class="text-xs text-slate-500 hover:underline"><?= !empty($p['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <form method="post" action="/admin/plans/<?= urlencode((string) $p['plan_id']) ?>/delete" class="inline"
                                      onsubmit="return confirm('Delete plan &quot;<?= htmlspecialchars((string) $p['plan_id']) ?>&quot;? Tenants currently on it block deletion.');">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($plans === []): ?>
                    <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">No plans yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
