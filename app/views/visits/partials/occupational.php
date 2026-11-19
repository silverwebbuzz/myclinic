<?php
/**
 * Visit screen: "Occupational exam" panel for an employer-booked visit (Workforce Health 1C-3).
 *
 * @var array<string,mixed> $occExam  booking, cases, certificates, current, today, default_valid_to, default_review_by, can_sign, visit_id
 * @var string|null $occOk
 * @var string|null $occErr
 */
use App\Services\OccHealth\OccExamService;

$oh = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$od = static fn (?string $ymd): string => $ymd ? date('d M Y', strtotime($ymd)) : '';
$ob = $occExam['booking'];
$oCur = $occExam['current'];
$oVid = (int) $occExam['visit_id'];
$oCsrf = '<input type="hidden" name="_csrf" value="' . $oh($csrf ?? '') . '">';
$oIn = 'mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm';
$oOutColour = ['fit' => 'text-emerald-700', 'fit_restricted' => 'text-amber-700', 'temp_unfit' => 'text-red-700', 'unfit' => 'text-red-700', 'further_assessment' => 'text-red-700'];
$oPast = array_values(array_filter($occExam['certificates'], static fn ($c) => $c['status'] !== 'valid'));
$oSign = $occExam['can_sign'];

// The outcome form, used for the first issue and for a correction.
$oForm = static function (bool $correct) use ($occExam, $oh, $oIn, $oCsrf, $oVid, $oSign, $oCur): string {
    $seed = $correct && $oCur !== null ? [
        'outcome' => $oCur['outcome'], 'valid_to' => $oCur['valid_to'] ?: $occExam['default_valid_to'], 'review_by' => $oCur['review_by'] ?: $occExam['default_review_by'],
        'restrictions' => json_decode((string) ($oCur['restrictions_json'] ?? '[]'), true) ?: [], 'note' => (string) ($oCur['restrictions_note'] ?? ''),
    ] : ['outcome' => '', 'valid_to' => $occExam['default_valid_to'], 'review_by' => $occExam['default_review_by'], 'restrictions' => [], 'note' => ''];
    ob_start(); ?>
    <form method="post" action="/visits/<?= $oVid ?>/occupational/sign" class="mt-3 space-y-4" x-data="{ outcome: '<?= $oh($seed['outcome']) ?>' }"
          onsubmit="return confirm('Sign and issue this certificate? It can\'t be edited afterwards, only replaced or revoked.')">
        <?= $oCsrf ?>
        <?php if (!$correct): ?>
            <div>
                <div class="text-sm font-semibold text-slate-800">Checks done today</div>
                <div class="mt-1 grid gap-1 sm:grid-cols-2">
                    <?php foreach ($occExam['cases'] as $c): if ($c['status'] !== 'scheduled') { continue; } ?>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="done[<?= (int) $c['id'] ?>]" value="1" checked> <?= $oh($c['exam_name']) ?></label>
                    <?php endforeach; ?>
                </div>
                <label class="mt-2 block text-xs text-slate-600">If a check was not done, why? <span class="text-slate-400">(it goes back to the company's due list)</span>
                    <input name="not_done_reason" maxlength="255" placeholder="e.g. X-ray machine down, referred to a lab" class="<?= $oIn ?>"></label>
            </div>
        <?php else: ?>
            <label class="block text-xs text-slate-600">What was wrong with the issued certificate? <span class="text-slate-400">(kept on record; the old one shows "replaced")</span>
                <input name="replace_reason" required minlength="5" maxlength="500" class="<?= $oIn ?>"></label>
        <?php endif; ?>

        <div>
            <div class="text-sm font-semibold text-slate-800">Fitness for work</div>
            <div class="mt-1 grid gap-1 sm:grid-cols-2">
                <?php foreach (OccExamService::OUTCOMES as $k => $label): ?>
                    <label class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm ring-1" :class="outcome === '<?= $k ?>' ? 'bg-teal-50 ring-teal-300' : 'ring-slate-200'">
                        <input type="radio" name="outcome" value="<?= $k ?>" x-model="outcome" required> <?= $oh($label) ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div x-show="['fit_restricted', 'temp_unfit', 'further_assessment', 'unfit'].includes(outcome)" x-cloak>
            <div class="text-sm font-semibold text-slate-800">Restrictions <span class="font-normal text-slate-500">(in work terms: the employer sees these)</span></div>
            <div class="mt-1 grid gap-1 sm:grid-cols-2" x-show="outcome !== 'unfit'">
                <?php foreach (OccExamService::RESTRICTIONS as $k => $label): ?>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="restrictions[]" value="<?= $k ?>" <?= in_array($k, $seed['restrictions'], true) ? 'checked' : '' ?>> <?= $oh($label) ?></label>
                <?php endforeach; ?>
            </div>
            <label class="mt-2 block text-xs text-slate-600">Other / note <span class="text-slate-400">(no diagnosis here)</span>
                <input name="restrictions_note" maxlength="500" value="<?= $oh($seed['note']) ?>" placeholder="e.g. Desk duties only for 4 weeks" class="<?= $oIn ?>"></label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs text-slate-600" x-show="['fit', 'fit_restricted'].includes(outcome)" x-cloak>Valid until
                <input type="date" name="valid_to" value="<?= $oh($seed['valid_to']) ?>" min="<?= $oh($occExam['today']) ?>" class="<?= $oIn ?>"></label>
            <label class="text-xs text-slate-600" x-show="['temp_unfit', 'further_assessment'].includes(outcome)" x-cloak>Review by
                <input type="date" name="review_by" value="<?= $oh($seed['review_by']) ?>" min="<?= $oh($occExam['today']) ?>" class="<?= $oIn ?>"></label>
        </div>

        <div class="rounded-lg bg-slate-50 p-3">
            <div class="text-sm font-semibold text-slate-800">Employee's consent</div>
            <p class="text-xs text-slate-600">Ask the employee: "May we share the outcome, restrictions and validity with your employer? Nothing else from today's exam is shared."</p>
            <label class="mt-1 mr-4 inline-flex items-center gap-2 text-sm"><input type="radio" name="consent" value="yes" required> Agrees to share</label>
            <label class="mt-1 inline-flex items-center gap-2 text-sm"><input type="radio" name="consent" value="no"> Does not agree <span class="text-xs text-slate-500">(employer sees "attended" only)</span></label>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button class="ui-btn ui-btn-primary" <?= $oSign['ok'] ? '' : 'disabled' ?>><?= $correct ? 'Sign corrected certificate' : 'Sign & issue certificate' ?></button>
            <span class="text-xs text-slate-500">Signed as you, with your registration <?= $oSign['ok'] ? $oh($oSign['credential']['reg_number']) : '' ?>.</span>
        </div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<section id="occupational" class="ui-card ui-card-pad mb-4 border-l-4 border-amber-400">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">Corporate</span>
                <h2 class="font-semibold text-slate-900">Occupational health check · <?= $oh($ob['company_name']) ?></h2>
            </div>
            <p class="text-sm text-slate-600"><?= $oh($ob['full_name']) ?> · employee no. <?= $oh($ob['employee_no']) ?></p>
        </div>
        <?php if (!in_array($ob['status'], ['booked', 'attended'], true) && $oCur === null): ?>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Booking <?= $oh($ob['status'] === 'no_show' ? 'marked did not attend' : $ob['status']) ?></span>
        <?php endif; ?>
    </div>
    <p class="mt-2 text-xs text-slate-500">Record your history, examination and notes in the visit as usual: they stay in your clinic. Only the certificate below (outcome, restrictions, validity) goes to the employee, and to the employer if the employee agrees.</p>

    <?php if (!empty($occOk)): ?><p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= $oh($occOk) ?></p><?php endif; ?>
    <?php if (!empty($occErr)): ?><p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= $oh($occErr) ?></p><?php endif; ?>
    <?php if (!$oSign['ok'] && ($oCur !== null || in_array($ob['status'], ['booked', 'attended'], true))): ?>
        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"><?= $oh($oSign['error']) ?></p>
    <?php endif; ?>

    <?php if ($oCur !== null): $oRestr = array_map(static fn ($k) => OccExamService::RESTRICTIONS[$k] ?? $k, json_decode((string) ($oCur['restrictions_json'] ?? '[]'), true) ?: []); ?>
        <div class="mt-3 rounded-lg bg-slate-50 p-3" x-data="{ fix: false, rev: false }">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="text-lg font-semibold <?= $oOutColour[$oCur['outcome']] ?? '' ?>"><?= $oh(OccExamService::OUTCOMES[$oCur['outcome']] ?? $oCur['outcome']) ?></div>
                    <?php if ($oRestr !== [] || !empty($oCur['restrictions_note'])): ?><div class="text-sm text-slate-700"><?= $oh(implode('; ', array_merge($oRestr, array_filter([(string) $oCur['restrictions_note']])))) ?></div><?php endif; ?>
                    <div class="text-sm text-slate-600"><?= $oCur['valid_to'] ? 'Valid to ' . $oh($od($oCur['valid_to'])) : ($oCur['review_by'] ? 'Review by ' . $oh($od($oCur['review_by'])) : '') ?>
                        · <?= (int) $oCur['consent_shared'] === 1 ? 'shared with employer' : '<span class="text-amber-700">not shared with employer</span>' ?></div>
                    <div class="text-xs text-slate-500"><?= $oh($oCur['cert_no']) ?> · signed <?= $oh($od(substr((string) $oCur['signed_at'], 0, 10))) ?></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if (!empty($oCur['pdf_path'])): ?><a target="_blank" href="/visits/<?= $oVid ?>/occupational/<?= (int) $oCur['id'] ?>/pdf" class="ui-btn ui-btn-secondary">Certificate PDF</a><?php endif; ?>
                    <button type="button" @click="fix = !fix; rev = false" class="ui-btn ui-btn-secondary">Correct</button>
                    <button type="button" @click="rev = !rev; fix = false" class="ui-btn ui-btn-secondary text-red-700">Revoke</button>
                </div>
            </div>
            <div x-show="fix" x-cloak><?= $oForm(true) ?></div>
            <form x-show="rev" x-cloak method="post" action="/visits/<?= $oVid ?>/occupational/<?= (int) $oCur['id'] ?>/revoke" class="mt-3 flex flex-wrap gap-2">
                <?= $oCsrf ?>
                <input name="reason" required minlength="5" maxlength="500" placeholder="Why is it withdrawn?" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button class="ui-btn ui-btn-danger">Revoke certificate</button>
            </form>
        </div>
    <?php elseif (in_array($ob['status'], ['booked', 'attended'], true)): ?>
        <?= $oForm(false) ?>
    <?php endif; ?>

    <?php if ($oPast !== []): ?>
        <details class="mt-3 text-sm"><summary class="cursor-pointer text-slate-500">Earlier certificates (<?= count($oPast) ?>)</summary>
            <ul class="mt-2 space-y-1 text-slate-600">
                <?php foreach ($oPast as $oP): ?>
                    <li><?= $oh($oP['cert_no']) ?> · <?= $oh(OccExamService::OUTCOMES[$oP['outcome']] ?? $oP['outcome']) ?> · <?= $oP['status'] === 'replaced' ? 'replaced' : 'revoked' ?><?= !empty($oP['revoked_reason']) ? ': ' . $oh($oP['revoked_reason']) : '' ?>
                        <?php if (!empty($oP['pdf_path'])): ?> · <a class="underline" target="_blank" href="/visits/<?= $oVid ?>/occupational/<?= (int) $oP['id'] ?>/pdf">PDF</a><?php endif; ?></li>
                <?php endforeach; ?>
            </ul></details>
    <?php endif; ?>
</section>
