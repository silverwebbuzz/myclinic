<?php
/**
 * /company/risks/new and /company/risks/{id} — add / edit a risk.
 *
 * @var array<string,mixed>|null $risk
 * @var array<string,mixed>|null $old   posted values after a failed save
 * @var list<array<string,mixed>> $branches
 * @var list<array<string,mixed>> $jobRoles
 * @var list<array{id:int, name:string}> $owners
 */
use App\Services\Corporate\RiskService;

$v = is_array($old ?? null) ? $old : ($risk ?? []);
$val = static fn (string $k, $d = '') => $v[$k] ?? $d;
$pageTitle = $risk ? 'Edit risk ' . $risk['ref'] : 'Add a risk';
$e = static fn ($x): string => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 text-[13px] text-tx focus:border-ac focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$rate = static function (string $name, array $labels, $current, bool $allowNone) use ($e, $input): string {
    $h = '<select name="' . $e($name) . '" class="' . $input . '">' . ($allowNone ? '<option value="0">Not assessed</option>' : '<option value="">Choose…</option>');
    foreach ($labels as $n => $l) {
        $h .= '<option value="' . $n . '"' . ((int) $current === $n ? ' selected' : '') . '>' . $n . ' · ' . $e($l) . '</option>';
    }

    return $h . '</select>';
};
ob_start();
?>
<a href="/company/risks" class="text-[13px] text-tx2 hover:text-tx">← Risk register</a>
<h1 class="mt-2 text-[20px] font-semibold tracking-[-.015em]"><?= $e($pageTitle) ?></h1>
<form method="post" action="/company/risks<?= $risk ? '/' . (int) $risk['id'] : '' ?>" class="mt-4 space-y-4"
      x-data="{ l: <?= (int) $val('likelihood', 0) ?>, s: <?= (int) $val('severity', 0) ?>, rl: <?= (int) $val('residual_likelihood', 0) ?>, rs: <?= (int) $val('residual_severity', 0) ?>,
               band(x) { return x <= 0 ? '' : (x <= 4 ? 'Low' : (x <= 9 ? 'Medium' : (x <= 16 ? 'High' : 'Very high'))); } }">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <section class="grid gap-3 rounded-[10px] border border-ln bg-sf p-5 sm:grid-cols-2">
        <label class="text-[12px] text-tx3 sm:col-span-2">Activity or area<input name="activity" required maxlength="200" value="<?= $e($val('activity')) ?>" placeholder="e.g. Welding in the fabrication bay" class="<?= $input ?>"></label>
        <label class="text-[12px] text-tx3">Hazard<input name="hazard" required maxlength="200" value="<?= $e($val('hazard')) ?>" placeholder="e.g. Welding fume" class="<?= $input ?>"></label>
        <label class="text-[12px] text-tx3">Category<select name="category" required class="<?= $input ?>"><option value="">Choose…</option>
            <?php foreach (RiskService::CATEGORIES as $k => $l): ?><option value="<?= $e($k) ?>" <?= $val('category') === $k ? 'selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?></select></label>
        <label class="text-[12px] text-tx3">Branch<select name="branch_id" class="<?= $input ?>"><option value="0">All branches</option>
            <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) $val('branch_id', 0) === (int) $b['id'] ? 'selected' : '' ?>><?= $e($b['name']) ?></option><?php endforeach; ?></select></label>
        <label class="text-[12px] text-tx3">Job role <span class="text-tx3">(optional)</span><select name="job_role_id" class="<?= $input ?>"><option value="0">Not one job role</option>
            <?php foreach ($jobRoles as $j): ?><option value="<?= (int) $j['id'] ?>" <?= (int) $val('job_role_id', 0) === (int) $j['id'] ? 'selected' : '' ?>><?= $e($j['title']) ?></option><?php endforeach; ?></select></label>
        <label class="text-[12px] text-tx3 sm:col-span-2">Who is exposed <span class="text-tx3">(optional)</span><input name="who_exposed" maxlength="300" value="<?= $e($val('who_exposed')) ?>" placeholder="e.g. Welders, helpers and anyone within 5 m" class="<?= $input ?>"></label>
    </section>

    <section class="grid gap-3 rounded-[10px] border border-ln bg-sf p-5 sm:grid-cols-2">
        <div class="sm:col-span-2 text-[13px] font-semibold">Rating before controls <span class="font-normal text-tx3" x-text="l && s ? '= ' + (l * s) + ' · ' + band(l * s) : ''"></span></div>
        <label class="text-[12px] text-tx3" @change="l = +$event.target.value">Likelihood<?= $rate('likelihood', RiskService::LIKELIHOOD, $val('likelihood', 0), false) ?></label>
        <label class="text-[12px] text-tx3" @change="s = +$event.target.value">Severity<?= $rate('severity', RiskService::SEVERITY, $val('severity', 0), false) ?></label>
        <label class="text-[12px] text-tx3 sm:col-span-2">Controls in place<textarea name="controls" rows="3" maxlength="2000" class="<?= $input ?>" placeholder="e.g. Local exhaust ventilation, FFP3 masks, job rotation"><?= $e($val('controls')) ?></textarea></label>
        <label class="text-[12px] text-tx3 sm:col-span-2">Further actions <span class="text-tx3">(optional)</span><textarea name="actions" rows="2" maxlength="2000" class="<?= $input ?>"><?= $e($val('actions')) ?></textarea></label>
        <div class="sm:col-span-2 text-[13px] font-semibold">Rating with controls <span class="font-normal text-tx3" x-text="rl && rs ? '= ' + (rl * rs) + ' · ' + band(rl * rs) : '(optional)'"></span></div>
        <label class="text-[12px] text-tx3" @change="rl = +$event.target.value">Likelihood<?= $rate('residual_likelihood', RiskService::LIKELIHOOD, $val('residual_likelihood', 0), true) ?></label>
        <label class="text-[12px] text-tx3" @change="rs = +$event.target.value">Severity<?= $rate('residual_severity', RiskService::SEVERITY, $val('residual_severity', 0), true) ?></label>
    </section>

    <section class="grid gap-3 rounded-[10px] border border-ln bg-sf p-5 sm:grid-cols-3">
        <label class="text-[12px] text-tx3">Owner<select name="owner_user_id" class="<?= $input ?>"><option value="0">Nobody yet</option>
            <?php foreach ($owners as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (int) $val('owner_user_id', 0) === $o['id'] ? 'selected' : '' ?>><?= $e($o['name']) ?></option><?php endforeach; ?></select></label>
        <label class="text-[12px] text-tx3">Review by<input type="date" name="review_on" value="<?= $e($val('review_on')) ?>" class="<?= $input ?>"></label>
        <label class="text-[12px] text-tx3">Status<select name="status" class="<?= $input ?>">
            <?php foreach (RiskService::STATUSES as $k => $l): ?><option value="<?= $e($k) ?>" <?= $val('status', 'open') === $k ? 'selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?></select></label>
    </section>
    <div class="flex flex-wrap items-center gap-3">
        <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90">Save risk</button>
        <span class="text-[13px] text-tx3">Every change is written to the activity log.</span>
    </div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
