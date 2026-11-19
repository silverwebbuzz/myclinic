<?php
/**
 * /company/employees — the workforce list with filters.
 *
 * @var array<string,string> $filters
 * @var array{rows: list<array<string,mixed>>, total: int, page: int, pages: int} $result
 * @var array{active:int,on_leave:int,terminated:int,pending:int,declined:int} $counts
 * @var list<array<string,mixed>> $branches  (already limited to the user's scope)
 * @var list<array<string,mixed>> $departments
 * @var list<array<string,mixed>> $jobRoles
 * @var bool $canManage
 * @var bool $canExport
 * @var bool $scoped   user sees only some branches
 * @var \App\Support\Locale\Locale $loc
 */
use App\Services\Corporate\EmployeeService;

$pageTitle = 'Employees';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'w-full rounded-[7px] border border-ln bg-sf px-2.5 py-1.5 text-[13px] focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$statusPill = ['active' => 'bg-okb text-ok', 'on_leave' => 'bg-wnb text-wn', 'terminated' => 'bg-sf2 text-tx3 ring-1 ring-ln'];
$linkPill = ['pending' => 'bg-sf2 text-tx2 ring-1 ring-ln', 'confirmed' => 'bg-acs text-ac', 'declined' => 'bg-erb text-er'];
$typeShort = ['contractor' => 'Contractor', 'temporary' => 'Temporary', 'intern' => 'Intern'];
$qs = static function (array $over) use ($filters): string {
    $q = array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== null);
    if (($q['status'] ?? '') === 'current') {
        unset($q['status']);
    }

    return $q === [] ? '' : '?' . http_build_query($q);
};
$hasFilter = array_filter(array_diff_key($filters, ['status' => 1])) !== [] || $filters['status'] !== 'current';
$rows = $result['rows'];
$opt = static fn (string $value, string $label, string $current): string => '<option value="' . htmlspecialchars($value, ENT_QUOTES) . '"' . ($value === $current ? ' selected' : '') . '>' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Employees</h1>
        <p class="mt-0.5 text-tx3">
            <?= number_format($counts['active']) ?> active<?php if ($counts['on_leave'] > 0): ?> · <?= number_format($counts['on_leave']) ?> on leave<?php endif; ?><?php if ($counts['terminated'] > 0): ?> · <?= number_format($counts['terminated']) ?> left<?php endif; ?><?php if ($scoped): ?> · <span class="text-tx2">your branches only</span><?php endif; ?>
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if (\App\Services\Corporate\CorpAuth::can('employees.import')): ?>
            <a href="/company/employees/import" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Import from Excel</a>
        <?php endif; ?>
        <?php if ($canExport && $result['total'] > 0): ?>
            <a href="/company/employees/export<?= $e($qs([])) ?>" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Export CSV</a>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <?php if ($branches !== []): ?><a href="/company/employees/new" class="<?= $btn ?> bg-ac text-white hover:opacity-90">Add employee</a><?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (($notInvited ?? 0) > 0): ?>
    <form method="post" action="/company/employees/invite-all" class="mt-4 flex flex-wrap items-center gap-3 rounded-[10px] border border-ac/30 bg-acs px-4 py-3">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <span class="text-tx2"><?= number_format((int) $notInvited) ?> employee<?= (int) $notInvited === 1 ? ' hasn\'t' : 's haven\'t' ?> been invited to confirm their link yet.</span>
        <button class="<?= $btn ?> ml-auto bg-ac text-white hover:opacity-90">Send invitations<?= (int) $notInvited > \App\Services\Corporate\EmployeeInviteService::BULK_LIMIT ? ' (next ' . \App\Services\Corporate\EmployeeInviteService::BULK_LIMIT . ')' : '' ?></button>
    </form>
<?php endif; ?>
<?php if ($counts['declined'] > 0 || $counts['pending'] > 0): ?>
    <div class="mt-4 flex flex-wrap gap-2 text-[13px]">
        <?php if ($counts['declined'] > 0): ?>
            <a href="/company/employees<?= $e($qs(['link' => 'declined', 'page' => ''])) ?>" class="rounded-full bg-erb px-3 py-1 text-er hover:opacity-90"><?= number_format($counts['declined']) ?> declined the link: check their mobile number</a>
        <?php endif; ?>
        <?php if ($counts['pending'] > 0): ?>
            <a href="/company/employees<?= $e($qs(['link' => 'pending', 'page' => ''])) ?>" class="rounded-full bg-sf2 px-3 py-1 text-tx2 ring-1 ring-ln hover:bg-sf"><?= number_format($counts['pending']) ?> haven't confirmed yet</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="get" action="/company/employees" class="mt-4 grid grid-cols-2 gap-2 rounded-[10px] border border-ln bg-sf p-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-8">
    <input type="search" name="q" value="<?= $e($filters['q']) ?>" placeholder="Name, employee no., mobile, email" class="<?= $input ?> col-span-2 sm:col-span-3 lg:col-span-2" aria-label="Search">
    <select name="branch" class="<?= $input ?>" aria-label="Branch">
        <option value="">All branches</option>
        <?php foreach ($branches as $b): ?><?= $opt((string) $b['id'], (string) $b['name'], $filters['branch']) ?><?php endforeach; ?>
    </select>
    <select name="department" class="<?= $input ?>" aria-label="Department">
        <option value="">All departments</option>
        <?php foreach ($departments as $d): ?><?= $opt((string) $d['id'], (string) $d['name'], $filters['department']) ?><?php endforeach; ?>
    </select>
    <select name="job_role" class="<?= $input ?>" aria-label="Job role">
        <option value="">All job roles</option>
        <?php foreach ($jobRoles as $j): ?><?= $opt((string) $j['id'], (string) $j['title'], $filters['job_role']) ?><?php endforeach; ?>
    </select>
    <select name="status" class="<?= $input ?>" aria-label="Status">
        <?= $opt('current', 'Current staff', $filters['status']) ?>
        <?php foreach (EmployeeService::STATUSES as $k => $l): ?><?= $opt($k, $l, $filters['status']) ?><?php endforeach; ?>
        <?= $opt('all', 'Everyone', $filters['status']) ?>
    </select>
    <select name="type" class="<?= $input ?>" aria-label="Type">
        <option value="">All types</option>
        <?php foreach (EmployeeService::TYPES as $k => $l): ?><?= $opt($k, $l, $filters['type']) ?><?php endforeach; ?>
    </select>
    <select name="link" class="<?= $input ?>" aria-label="Account link">
        <option value="">Any link</option>
        <?php foreach (EmployeeService::LINK_STATUSES as $k => $l): ?><?= $opt($k, $l, $filters['link']) ?><?php endforeach; ?>
    </select>
    <div class="col-span-full flex items-center gap-2">
        <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Filter</button>
        <?php if ($hasFilter): ?><a href="/company/employees" class="text-[13px] text-tx2 hover:text-tx">Clear</a><?php endif; ?>
        <span class="ml-auto text-[13px] text-tx3"><?= number_format($result['total']) ?> found</span>
    </div>
</form>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if ($branches === [] && !$scoped): ?>
        <div class="px-5 py-10 text-center">
            <div class="font-medium">Add a branch first</div>
            <p class="mt-1 text-tx3">Every employee belongs to a branch (office, site, yard…).</p>
            <?php if (\App\Services\Corporate\CorpAuth::can('branches.manage')): ?>
                <a href="/company/branches" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Add branches</a>
            <?php endif; ?>
        </div>
    <?php elseif ($rows === []): ?>
        <div class="px-5 py-10 text-center">
            <div class="font-medium"><?= $hasFilter ? 'No one matches these filters' : 'No employees yet' ?></div>
            <?php if (!$hasFilter && $canManage): ?>
                <p class="mt-1 text-tx3">Add people one by one, or import them all from an Excel or CSV file.</p>
                <a href="/company/employees/new" class="<?= $btn ?> mt-4 bg-ac text-white hover:opacity-90">Add the first employee</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="hidden grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.3fr)_auto] gap-4 border-b border-ln bg-sf2 px-5 py-2 text-[12px] font-medium uppercase tracking-wide text-tx3 md:grid">
            <div>Name</div><div>Job</div><div>Branch</div><div class="w-[170px] text-right">Status</div>
        </div>
        <ul class="divide-y divide-ln2">
            <?php foreach ($rows as $r): ?>
                <li>
                    <a href="/company/employees/<?= (int) $r['id'] ?>" class="grid gap-x-4 gap-y-1 px-5 py-3 hover:bg-sf2 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.3fr)_auto] md:items-center <?= $r['status'] === 'terminated' ? 'opacity-70' : '' ?>">
                        <div class="min-w-0">
                            <div class="truncate font-medium"><?= $e($r['full_name']) ?></div>
                            <div class="truncate text-[13px] text-tx3"><span class="font-mono"><?= $e($r['employee_no']) ?></span> · <?= $e($loc->phone($r['work_phone'])) ?></div>
                        </div>
                        <div class="min-w-0 text-[13px]">
                            <div class="truncate"><?= $e($r['job_title'] ?? '—') ?><?php if (isset($typeShort[$r['employment_type']])): ?> <span class="ml-1 rounded bg-sf2 px-1.5 py-0.5 text-[11px] text-tx2 ring-1 ring-ln"><?= $e($typeShort[$r['employment_type']]) ?></span><?php endif; ?></div>
                            <div class="truncate text-tx3"><?= $e($r['department_name'] ?? '') ?: '&nbsp;' ?></div>
                        </div>
                        <div class="min-w-0 truncate text-[13px] text-tx2"><?= $e($r['branch_name'] ?? '—') ?></div>
                        <div class="flex flex-wrap gap-1.5 md:w-[170px] md:justify-end">
                            <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium <?= $statusPill[$r['status']] ?? '' ?>"><?= $e(EmployeeService::STATUSES[$r['status']] ?? $r['status']) ?></span>
                            <?php if ($r['status'] !== 'terminated'): ?>
                                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium <?= $linkPill[$r['link_status']] ?? '' ?>" title="Has the employee confirmed this is them?"><?= $e(['pending' => 'Unconfirmed', 'confirmed' => 'Linked', 'declined' => 'Declined'][$r['link_status']] ?? '') ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($result['pages'] > 1): ?>
            <div class="flex items-center justify-between border-t border-ln px-5 py-3 text-[13px]">
                <span class="text-tx3">Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
                <div class="flex gap-2">
                    <?php if ($result['page'] > 1): ?><a href="/company/employees<?= $e($qs(['page' => (string) ($result['page'] - 1)])) ?>" class="<?= $btn ?> border border-ln hover:bg-sf2">Previous</a><?php endif; ?>
                    <?php if ($result['page'] < $result['pages']): ?><a href="/company/employees<?= $e($qs(['page' => (string) ($result['page'] + 1)])) ?>" class="<?= $btn ?> border border-ln hover:bg-sf2">Next</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
