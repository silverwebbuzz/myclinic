<?php
/** Follow-ups page. $data = FollowUpService::dashboardData(). */
$d = $data ?? ['overdue' => [], 'overdue_count' => 0, 'due_week' => 0, 'done_month' => 0];
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Follow-ups</h2>
        <div class="flex gap-4 text-sm text-slate-500">
            <span class="text-rose-700 font-medium"><?= (int) $d['overdue_count'] ?> overdue</span>
            <span><?= (int) $d['due_week'] ?> due this week</span>
            <span><?= (int) $d['done_month'] ?> done this month</span>
        </div>
    </div>

    <div class="ui-card">
        <div class="border-b px-4 py-3">
            <h3 class="text-sm font-semibold text-rose-700">Overdue</h3>
        </div>
        <?php if (empty($d['overdue'])): ?>
        <p class="p-6 text-center text-sm text-slate-500">No overdue follow-ups. 🎉</p>
        <?php else: ?>
        <ul class="divide-y text-sm">
            <?php foreach ($d['overdue'] as $f): ?>
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-medium"><?= htmlspecialchars($f['patient_name']) ?></p>
                    <p class="text-xs text-slate-500">
                        Due <?= htmlspecialchars(date('d M Y', strtotime((string) $f['due_date']))) ?>
                        · <span class="text-rose-600"><?= (int) $f['days_overdue'] ?> days overdue</span>
                        <?php if (!empty($f['reason'])): ?>
                        · <?= htmlspecialchars(str_replace('_', ' ', (string) $f['reason'])) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <?php if (!empty($f['patient_phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($f['patient_phone']) ?>" class="text-xs text-slate-600 hover:underline">Call</a>
                    <?php endif; ?>
                    <a href="/visits/new?patient_id=<?= (int) $f['patient_id'] ?>"
                       class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-medium text-white hover:bg-emerald-700">
                        Start visit
                    </a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <p class="text-xs text-slate-400">
        Reminders are sent automatically over WhatsApp for clinics with the Patient Connect add-on.
    </p>
</div>
