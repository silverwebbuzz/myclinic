<?php
/**
 * /company/employees/new and /company/employees/{id} — add, view, edit,
 * status changes and history of one employee.
 *
 * @var array<string,mixed>|null $emp      null = new
 * @var array<string,mixed>|null $old      form values after an error
 * @var list<array<string,mixed>> $events
 * @var list<array<string,mixed>> $branches   pickable (scope + active, plus the current one)
 * @var list<array<string,mixed>> $departments
 * @var list<array<string,mixed>> $jobRoles
 * @var array<int|string,string> $allBranches  id => name (for history)
 * @var array<int|string,string> $allDepartments
 * @var array<int|string,string> $allJobRoles
 * @var list<array<string,mixed>> $countries
 * @var array<string, array<string,string>> $idTypes  branch country => [type => label]
 * @var bool $canManage
 * @var string $today
 * @var \App\Support\Locale\Locale $loc
 */
use App\Services\Corporate\EmployeeService;

$isNew = $emp === null;
$pageTitle = $isNew ? 'Add employee' : (string) $emp['full_name'];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none disabled:bg-sf2 disabled:text-tx2';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$fields = ['full_name', 'employee_no', 'work_phone', 'work_email', 'dob', 'gender', 'nationality', 'branch_id', 'department_id', 'job_role_id',
    'employment_type', 'contractor_company', 'joined_on', 'id_type'];
$v = [];
foreach ($fields as $f) {
    $v[$f] = (string) ($old[$f] ?? $emp[$f] ?? '');
}
if ($v['employment_type'] === '') {
    $v['employment_type'] = 'employee';
}
if ($isNew && $v['branch_id'] === '' && count($branches) === 1) {
    $v['branch_id'] = (string) $branches[0]['id'];
}
$branchCountry = [];
foreach ($branches as $b) {
    $branchCountry[(int) $b['id']] = (string) $b['country_code'];
}
$statusPill = ['active' => 'bg-okb text-ok', 'on_leave' => 'bg-wnb text-wn', 'terminated' => 'bg-sf2 text-tx3 ring-1 ring-ln'];
$linkText = [
    'pending' => ['Not confirmed yet', 'bg-sf2 text-tx2 ring-1 ring-ln', 'When they sign in to eClinicPro with this mobile number, they\'ll be asked to confirm they work here.'],
    'confirmed' => ['Linked', 'bg-acs text-ac', 'The employee confirmed this is them.'],
    'declined' => ['Declined', 'bg-erb text-er', 'The person using this mobile number said they don\'t work here. Check the number and save it again.'],
];
$eventLabel = [
    'joined' => 'Added', 'updated' => 'Details changed', 'transferred' => 'Transferred', 'role_changed' => 'Job role changed',
    'contract_changed' => 'Employment type changed', 'on_leave' => 'On leave', 'returned' => 'Back at work', 'terminated' => 'Left the company',
    'rehired' => 'Re-hired', 'linked' => 'Employee confirmed the link', 'link_declined' => 'Employee declined the link', 'invited' => 'Invitation sent',
];
$fieldLabel = [
    'full_name' => 'Name', 'employee_no' => 'Employee no.', 'work_phone' => 'Mobile', 'work_email' => 'Email', 'dob' => 'Date of birth',
    'gender' => 'Gender', 'nationality' => 'Nationality', 'branch_id' => 'Branch', 'department_id' => 'Department', 'job_role_id' => 'Job role',
    'employment_type' => 'Type', 'contractor_company' => 'Contractor company', 'joined_on' => 'Joined', 'id_type' => 'ID type', 'id_number' => 'ID number',
    'status' => 'Status', 'channels' => 'Sent by', 'left_on' => 'Left on', 'leave_reason' => 'Reason', 'reason' => 'Reason', 'account_link' => 'Account link',
];
$countryNames = array_column($countries, 'name', 'code');
$show = static function (string $field, $value) use ($allBranches, $allDepartments, $allJobRoles, $countryNames, $loc): string {
    if ($value === null || $value === '') {
        return '—';
    }

    return match ($field) {
        'branch_id' => $allBranches[(int) $value] ?? '#' . $value,
        'department_id' => $allDepartments[(int) $value] ?? '#' . $value,
        'job_role_id' => $allJobRoles[(int) $value] ?? '#' . $value,
        'dob', 'joined_on', 'left_on' => $loc->date((string) $value),
        'employment_type' => EmployeeService::TYPES[$value] ?? (string) $value,
        'status' => EmployeeService::STATUSES[$value] ?? (string) $value,
        'gender' => EmployeeService::GENDERS[$value] ?? (string) $value,
        'nationality' => $countryNames[$value] ?? (string) $value,
        'work_phone' => $loc->phone((string) $value),
        'id_type' => EmployeeService::idTypeLabel((string) $value),
        'id_number' => $value === 'set' ? 'updated' : 'removed',
        'account_link' => 'needs confirming again',
        default => (string) $value,
    };
};
ob_start();
?>
<a href="/company/employees" class="text-[13px] text-tx2 hover:text-tx">&larr; Employees</a>

<?php if ($isNew): ?>
    <h1 class="mt-2 text-[20px] font-semibold tracking-[-.015em]">Add employee</h1>
    <p class="mt-0.5 text-tx3">Only the name, mobile and branch are required. The employee confirms the link the first time they sign in to eClinicPro with this mobile number.</p>
<?php else: ?>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="break-words text-[20px] font-semibold tracking-[-.015em]"><?= $e($emp['full_name']) ?></h1>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-tx3">
                <span class="font-mono"><?= $e($emp['employee_no']) ?></span>
                <span>·</span><span><?= $e($emp['job_title'] ?? 'No job role') ?></span>
                <span>·</span><span><?= $e($emp['branch_name'] ?? '') ?></span>
                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium <?= $statusPill[$emp['status']] ?? '' ?>"><?= $e(EmployeeService::STATUSES[$emp['status']] ?? '') ?></span>
            </div>
        </div>
        <?php if ($canManage): ?>
            <div class="flex flex-wrap gap-2" x-data>
                <?php if ($emp['status'] === 'active'): ?>
                    <button type="button" @click="$dispatch('status-panel', 'leave')" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">On leave</button>
                <?php endif; ?>
                <?php if ($emp['status'] === 'on_leave'): ?>
                    <button type="button" @click="$dispatch('status-panel', 'return')" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Back at work</button>
                <?php endif; ?>
                <?php if ($emp['status'] !== 'terminated'): ?>
                    <button type="button" @click="$dispatch('status-panel', 'terminate')" class="<?= $btn ?> border border-er/30 bg-sf text-er hover:bg-erb">Mark as left</button>
                <?php else: ?>
                    <button type="button" @click="$dispatch('status-panel', 'rehire')" class="<?= $btn ?> bg-ac text-white hover:opacity-90">Re-hire</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <section x-data="{ panel: '' }" @status-panel.window="panel = $event.detail; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" x-show="panel" x-cloak
                 class="mt-4 rounded-[10px] border border-ln bg-sf p-5">
            <?php foreach ([
                'leave' => ['On leave', 'From', 'Mark as on leave', false, ''],
                'return' => ['Back at work', 'Back from', 'Mark as back', false, ''],
                'terminate' => ['Mark as left', 'Last working day', 'Mark as left', true, 'Nothing is deleted. Their records and history stay, and you can re-hire them later.'],
                'rehire' => ['Re-hire', 'New joining date', 'Re-hire', false, 'Their earlier history is kept.'],
            ] as $k => [$title, $dateLabel, $submit, $needReason, $note]): ?>
                <form x-show="panel === '<?= $k ?>'" method="post" action="/company/employees/<?= (int) $emp['id'] ?>/<?= $k ?>" class="grid gap-3 sm:grid-cols-[180px_minmax(0,1fr)_auto] sm:items-end">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <div class="sm:col-span-3"><h2 class="text-[15px] font-semibold"><?= $e($title) ?></h2><?php if ($note !== ''): ?><p class="text-tx3"><?= $e($note) ?></p><?php endif; ?></div>
                    <label class="block"><span class="text-tx2"><?= $e($dateLabel) ?></span><input type="date" name="date" required value="<?= $e($today) ?>" class="<?= $input ?>"></label>
                    <?php if ($needReason): ?>
                        <label class="block"><span class="text-tx2">Reason</span>
                            <input name="reason" required maxlength="255" list="leave-reasons" class="<?= $input ?>" placeholder="Resigned, contract ended, transferred to another company…">
                            <datalist id="leave-reasons"><option value="Resigned"><option value="Contract ended"><option value="Retired"><option value="Dismissed"><option value="Transferred to another company"><option value="Deceased"></datalist>
                        </label>
                    <?php else: ?>
                        <label class="block"><span class="text-tx2">Note <span class="text-tx3">(optional)</span></span><input name="reason" maxlength="255" class="<?= $input ?>"></label>
                    <?php endif; ?>
                    <div class="flex gap-2">
                        <button class="<?= $btn ?> h-9 px-4 <?= $k === 'terminate' ? 'bg-er' : 'bg-ac' ?> text-white hover:opacity-90"><?= $e($submit) ?></button>
                        <button type="button" @click="panel = ''" class="<?= $btn ?> h-9 border border-ln hover:bg-sf2">Cancel</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($emp['status'] === 'terminated'): ?>
        <div class="mt-4 rounded-[10px] border border-ln bg-sf2 px-4 py-3 text-tx2">Left on <?= $e($loc->date($emp['left_on'])) ?><?= !empty($emp['leave_reason']) ? ' · ' . $e($emp['leave_reason']) : '' ?></div>
    <?php elseif ($emp['link_status'] === 'declined'): ?>
        <div class="mt-4 rounded-[10px] border border-er/20 bg-erb px-4 py-3 text-er"><?= $e($linkText['declined'][2]) ?></div>
    <?php endif; ?>
<?php endif; ?>

<div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
    <section id="edit" class="rounded-[10px] border border-ln bg-sf p-5"
             x-data="{ branch: '<?= $e($v['branch_id']) ?>', type: '<?= $e($v['employment_type']) ?>', idType: '<?= $e($v['id_type']) ?>',
                       start: { b: '<?= $e($v['branch_id']) ?>', d: '<?= $e($v['department_id']) ?>', j: '<?= $e($v['job_role_id']) ?>' }, dept: '<?= $e($v['department_id']) ?>', job: '<?= $e($v['job_role_id']) ?>',
                       countries: <?= $e(json_encode($branchCountry)) ?>, idTypes: <?= $e(json_encode($idTypes, JSON_UNESCAPED_UNICODE)) ?>,
                       get ids() { return this.idTypes[this.countries[this.branch]] || {}; },
                       get moved() { return <?= $isNew ? 'false' : 'true' ?> && (this.branch !== this.start.b || this.dept !== this.start.d || this.job !== this.start.j); } }">
        <h2 class="text-[15px] font-semibold"><?= $isNew ? 'Details' : 'Details' ?></h2>
        <form method="post" action="<?= $isNew ? '/company/employees' : '/company/employees/' . (int) $emp['id'] ?>" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <fieldset <?= $canManage ? '' : 'disabled' ?> class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2"><span class="text-tx2">Full name</span><input name="full_name" required maxlength="160" value="<?= $e($v['full_name']) ?>" class="<?= $input ?>" placeholder="As on their ID"></label>
                <label class="block"><span class="text-tx2">Mobile number</span><input name="work_phone" required maxlength="30" inputmode="tel" value="<?= $e($v['work_phone'] !== '' && !$old ? $loc->phone($v['work_phone']) : $v['work_phone']) ?>" class="<?= $input ?>" placeholder="98765 43210">
                    <span class="mt-1 block text-[12px] text-tx3">They sign in to eClinicPro with this number. Add the country code for a foreign number.</span></label>
                <label class="block"><span class="text-tx2">Employee no. <?php if ($isNew): ?><span class="text-tx3">(optional)</span><?php endif; ?></span><input name="employee_no" maxlength="40" value="<?= $e($v['employee_no']) ?>" class="<?= $input ?> font-mono uppercase" placeholder="<?= $isNew ? 'Leave blank to number automatically' : '' ?>" <?= $isNew ? '' : 'required' ?>></label>

                <label class="block"><span class="text-tx2">Branch</span>
                    <select name="branch_id" required x-model="branch" class="<?= $input ?>">
                        <option value="">Choose…</option>
                        <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (string) $b['id'] === $v['branch_id'] ? 'selected' : '' ?>><?= $e($b['name']) ?><?= (int) $b['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endforeach; ?>
                    </select></label>
                <label class="block"><span class="text-tx2">Department <span class="text-tx3">(optional)</span></span>
                    <select name="department_id" x-model="dept" class="<?= $input ?>">
                        <option value="">—</option>
                        <?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (string) $d['id'] === $v['department_id'] ? 'selected' : '' ?>><?= $e($d['name']) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="block"><span class="text-tx2">Job role <span class="text-tx3">(optional)</span></span>
                    <select name="job_role_id" x-model="job" class="<?= $input ?>">
                        <option value="">—</option>
                        <?php foreach ($jobRoles as $j): ?><option value="<?= (int) $j['id'] ?>" <?= (string) $j['id'] === $v['job_role_id'] ? 'selected' : '' ?>><?= $e($j['title']) ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($jobRoles === []): ?><span class="mt-1 block text-[12px] text-tx3">No job roles yet. Screening is assigned by job role, so add them under Structure.</span><?php endif; ?></label>
                <label class="block"><span class="text-tx2">Joining date <span class="text-tx3">(optional)</span></span><input type="date" name="joined_on" value="<?= $e($v['joined_on']) ?>" class="<?= $input ?>"></label>

                <div x-show="moved" x-cloak class="rounded-[7px] border border-ac/30 bg-acs px-3 py-2 sm:col-span-2">
                    <label class="flex flex-wrap items-center gap-2"><span class="text-tx2">This is a transfer or role change, effective</span>
                        <input type="date" name="effective_on" value="<?= $e($today) ?>" class="rounded-[7px] border border-ln bg-sf px-2 py-1"></label>
                </div>

                <label class="block"><span class="text-tx2">Type</span>
                    <select name="employment_type" x-model="type" class="<?= $input ?>">
                        <?php foreach (EmployeeService::TYPES as $k => $l): ?><option value="<?= $e($k) ?>" <?= $k === $v['employment_type'] ? 'selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="block" x-show="type === 'contractor'" x-cloak><span class="text-tx2">Contractor company</span><input name="contractor_company" maxlength="160" value="<?= $e($v['contractor_company']) ?>" class="<?= $input ?>" placeholder="Who employs them"></label>
                <div class="hidden sm:block" x-show="type !== 'contractor'"></div>

                <label class="block"><span class="text-tx2">Work email <span class="text-tx3">(optional)</span></span><input type="email" name="work_email" maxlength="190" value="<?= $e($v['work_email']) ?>" class="<?= $input ?>"></label>
                <label class="block"><span class="text-tx2">Date of birth <span class="text-tx3">(optional)</span></span><input type="date" name="dob" max="<?= $e($today) ?>" value="<?= $e($v['dob']) ?>" class="<?= $input ?>"></label>
                <label class="block"><span class="text-tx2">Gender <span class="text-tx3">(optional)</span></span>
                    <select name="gender" class="<?= $input ?>">
                        <option value="">—</option>
                        <?php foreach (EmployeeService::GENDERS as $k => $l): ?><option value="<?= $e($k) ?>" <?= $k === $v['gender'] ? 'selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="block"><span class="text-tx2">Nationality <span class="text-tx3">(optional)</span></span>
                    <select name="nationality" class="<?= $input ?>">
                        <option value="">—</option>
                        <?php foreach ($countries as $c): ?><option value="<?= $e($c['code']) ?>" <?= $c['code'] === $v['nationality'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option><?php endforeach; ?>
                    </select></label>

                <div class="border-t border-ln2 pt-4 sm:col-span-2">
                    <div class="font-medium">ID document <span class="font-normal text-tx3">(optional)</span></div>
                    <p class="text-[12px] text-tx3">Stored encrypted. Only HR can see the full number; everyone else sees the last 4 digits. We never store Aadhaar.</p>
                    <?php if (!$isNew && !empty($emp['id_last4'])): ?>
                        <div class="mt-2 flex flex-wrap items-center gap-2" x-data="{ full: '', busy: false }">
                            <span><?= $e(EmployeeService::idTypeLabel($emp['id_type'])) ?> ending <span class="font-mono" x-text="full || '<?= $e($emp['id_last4']) ?>'"><?= $e($emp['id_last4']) ?></span></span>
                            <?php if ($canManage): ?>
                                <button type="button" x-show="!full" :disabled="busy" class="text-[13px] text-ac hover:underline"
                                        @click="busy = true; const f = new FormData(); f.append('_csrf', '<?= $e($csrf) ?>');
                                                fetch('/company/employees/<?= (int) $emp['id'] ?>/reveal-id', { method: 'POST', body: f, credentials: 'same-origin' })
                                                  .then(r => r.json()).then(d => { full = d.ok ? d.id_number : ''; if (!d.ok) alert(d.error || 'Not available'); }).finally(() => busy = false)">Show full number</button>
                                <label class="ml-2 flex items-center gap-1.5 text-[13px] text-tx2"><input type="checkbox" name="id_remove" value="1" class="rounded border-ln"> Remove</label>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($canManage): ?>
                        <div class="mt-2 grid gap-4 sm:grid-cols-2">
                            <label class="block"><span class="text-tx2">ID type</span>
                                <select name="id_type" x-model="idType" class="<?= $input ?>">
                                    <option value="">—</option>
                                    <template x-for="(label, key) in ids" :key="key"><option :value="key" x-text="label" :selected="key === idType"></option></template>
                                </select></label>
                            <label class="block"><span class="text-tx2"><?= !$isNew && !empty($emp['id_last4']) ? 'Replace with a new number' : 'ID number' ?></span><input name="id_number" maxlength="60" autocomplete="off" class="<?= $input ?> font-mono uppercase"></label>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($canManage): ?>
                    <div class="flex gap-2 sm:col-span-2">
                        <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90"><?= $isNew ? 'Add employee' : 'Save changes' ?></button>
                        <a href="/company/employees<?= $isNew ? '' : '/' . (int) $emp['id'] ?>" class="<?= $btn ?> h-9 border border-ln hover:bg-sf2">Cancel</a>
                    </div>
                <?php endif; ?>
            </fieldset>
        </form>
    </section>

    <?php if (!$isNew): ?>
        <aside class="space-y-4">
            <section class="rounded-[10px] border border-ln bg-sf p-5">
                <h2 class="text-[15px] font-semibold">eClinicPro account</h2>
                <?php [$lt, $lc, $lh] = $linkText[$emp['link_status']] ?? $linkText['pending']; ?>
                <span class="mt-2 inline-block rounded-full px-2 py-0.5 text-[11px] font-medium <?= $lc ?>"><?= $e($lt) ?></span>
                <p class="mt-2 text-tx3"><?= $e($lh) ?></p>
                <?php if ($emp['link_status'] === 'pending' && $emp['status'] !== 'terminated'): ?>
                    <?php $whyNot = \App\Services\Corporate\EmployeeInviteService::cannotInvite($emp); ?>
                    <?php if (!empty($emp['invited_at'])): ?><p class="mt-1 text-[12px] text-tx3">Invitation sent <?= $e($loc->dateTime($emp['invited_at'])) ?></p><?php endif; ?>
                    <?php if ($canManage && $whyNot === null): ?>
                        <form method="post" action="/company/employees/<?= (int) $emp['id'] ?>/invite" class="mt-2">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                            <button class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2"><?= empty($emp['invited_at']) ? 'Send invitation' : 'Send again' ?></button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($emp['link_status'] === 'confirmed' && !empty($emp['link_confirmed_at'])): ?>
                    <p class="mt-1 text-[12px] text-tx3">Confirmed <?= $e($loc->dateTime($emp['link_confirmed_at'])) ?></p>
                <?php endif; ?>
                <p class="mt-3 border-t border-ln2 pt-3 text-[12px] text-tx3">You see only what your company entered. The employee's own health profile stays private to them.</p>
            </section>

            <?php if (($screening ?? null) !== null): ?>
                <section id="screening" class="rounded-[10px] border border-ln bg-sf p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-[15px] font-semibold">Screening</h2>
                        <a href="/company/screening?bucket=open&amp;q=<?= $e(rawurlencode((string) $emp['employee_no'])) ?>" class="text-[13px] text-ac hover:underline">Manage</a>
                    </div>
                    <?php if ($screening === []): ?>
                        <p class="mt-2 text-tx3">No checks. They appear when a screening programme applies to this person's job role, department or branch.</p>
                    <?php else: ?>
                        <ul class="mt-2 divide-y divide-ln2">
                            <?php foreach ($screening as $sc): $open = in_array($sc['status'], ['not_scheduled', 'scheduled'], true); $late = $open && $sc['due_on'] < $today; ?>
                                <li class="flex items-start justify-between gap-3 py-2">
                                    <div class="min-w-0">
                                        <div class="<?= $open ? '' : 'text-tx2' ?>"><?= $e($sc['exam_name']) ?></div>
                                        <div class="text-[12px] text-tx3"><?= $e(\App\Services\Corporate\ScreeningCaseService::CATEGORIES[$sc['category']] ?? $sc['category']) ?></div>
                                    </div>
                                    <div class="whitespace-nowrap text-right text-[12px]">
                                        <?php if ($open): ?>
                                            <div class="<?= $late ? 'font-medium text-er' : 'text-tx2' ?>"><?= $late ? 'Overdue' : 'Due' ?> <?= $e($loc->date($sc['due_on'])) ?></div>
                                            <?php if ($sc['status'] === 'scheduled'): ?><div class="text-tx3">booked</div><?php endif; ?>
                                        <?php elseif ($sc['status'] === 'completed'): ?>
                                            <div class="text-ok">Done <?= $e($loc->date($sc['completed_on'])) ?></div>
                                        <?php else: ?>
                                            <div class="text-tx3">Waived</div>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="rounded-[10px] border border-ln bg-sf p-5">
                <h2 class="text-[15px] font-semibold">History</h2>
                <?php if ($events === []): ?>
                    <p class="mt-2 text-tx3">No changes yet.</p>
                <?php else: ?>
                    <ol class="mt-3 space-y-3">
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $from = json_decode((string) ($ev['from_json'] ?? ''), true) ?: [];
                            $to = json_decode((string) ($ev['to_json'] ?? ''), true) ?: [];
                            $lines = [];
                            if ($ev['event'] !== 'joined') {
                                foreach ($to as $k => $val) {
                                    if (!isset($fieldLabel[$k]) || in_array($k, ['status'], true)) {
                                        continue;
                                    }
                                    $lines[] = $k === 'id_number' || $k === 'account_link' || in_array($k, ['reason', 'leave_reason'], true) || !array_key_exists($k, $from)
                                        ? $fieldLabel[$k] . ': ' . $show($k, $val)
                                        : $fieldLabel[$k] . ': ' . $show($k, $from[$k]) . ' → ' . $show($k, $val);
                                }
                            }
                            ?>
                            <li class="relative pl-4 before:absolute before:left-0 before:top-[7px] before:h-1.5 before:w-1.5 before:rounded-full before:bg-ln">
                                <div class="font-medium"><?= $e($eventLabel[$ev['event']] ?? $ev['event']) ?><?php if (!empty($ev['effective_on'])): ?> <span class="font-normal text-tx3">· <?= $e($loc->date($ev['effective_on'])) ?></span><?php endif; ?></div>
                                <?php foreach ($lines as $line): ?><div class="break-words text-[12px] text-tx2"><?= $e($line) ?></div><?php endforeach; ?>
                                <div class="text-[12px] text-tx3"><?= $e($loc->dateTime($ev['created_at'])) ?><?= !empty($ev['actor_name']) ? ' · ' . $e($ev['actor_name']) : ($ev['actor_type'] === 'employee' ? ' · the employee' : '') ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </aside>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
