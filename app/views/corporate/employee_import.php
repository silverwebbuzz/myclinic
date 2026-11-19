<?php
/**
 * /company/employees/import[/{id}] — one view for every step of an import.
 *
 * @var string $stage  upload | map | checked | committing | committed | cancelled | failed
 * @var array<string, array{0:string,1:bool,2:list<string>}> $fields
 * @var array<string,mixed>|null $import
 * @var list<array<string,mixed>> $recent        (upload)
 * @var bool $hasBranches                        (upload)
 * @var list<string> $header                     (map)
 * @var list<list<string>> $samples              (map)
 * @var array<string,int> $mapping               (map / results)
 * @var list<array<string,mixed>> $errors        (results) first 200 problem rows
 * @var list<array<string,mixed>> $changes       (checked) first 50 new / updated rows
 * @var \App\Support\Locale\Locale $loc
 */
$pageTitle = 'Import employees';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$import = $import ?? null;
$id = (int) ($import['id'] ?? 0);
$statusText = [
    'uploaded' => ['Waiting for column matching', 'bg-sf2 text-tx2 ring-1 ring-ln'], 'checked' => ['Checked, not imported', 'bg-wnb text-wn'],
    'committing' => ['Importing…', 'bg-acs text-ac'], 'committed' => ['Imported', 'bg-okb text-ok'],
    'cancelled' => ['Cancelled', 'bg-sf2 text-tx3 ring-1 ring-ln'], 'failed' => ['Failed', 'bg-erb text-er'],
];
$notes = [
    'employee_no' => 'Your own number. Used to recognise people when you import again. Blank = numbered automatically.',
    'full_name' => '',
    'mobile' => 'They sign in with it. Local format is read using the branch\'s country; add the country code for foreign numbers.',
    'branch' => 'Name or code exactly as in Structure → Branches.',
    'date_of_birth' => 'DD/MM/YYYY, YYYY-MM-DD or 01-Mar-1990.',
    'gender' => 'Male, Female or Other.',
    'nationality' => 'Country name or 2-letter code.',
    'department' => 'Name or code as in Structure.',
    'job_role' => 'Title or code as in Structure.',
    'employment_type' => 'Employee, Contractor, Temporary or Intern.',
    'contractor_company' => 'Required when the type is Contractor.',
    'joined_on' => 'Same date formats as date of birth.',
    'id_type' => 'e.g. Passport, Emirates ID, PAN card. Never Aadhaar.',
    'id_number' => 'Stored encrypted.',
];
$rowName = static fn (array $raw): string => (string) (($raw['full_name'] ?? '') !== '' ? $raw['full_name'] : (($raw['employee_no'] ?? '') !== '' ? $raw['employee_no'] : ($raw['mobile'] ?? '—')));
ob_start();
?>
<a href="<?= $stage === 'upload' ? '/company/employees' : '/company/employees/import' ?>" class="text-[13px] text-tx2 hover:text-tx">&larr; <?= $stage === 'upload' ? 'Employees' : 'Import employees' ?></a>
<div class="mt-2 flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Import employees</h1>
        <?php if ($import !== null): ?>
            <p class="mt-0.5 break-words text-tx3"><?= $e($import['file_name']) ?> · <?= number_format((int) $import['total_rows']) ?> rows · uploaded <?= $e($loc->dateTime($import['created_at'])) ?></p>
        <?php else: ?>
            <p class="mt-0.5 text-tx3">Add or update many employees at once from Excel (.xlsx) or CSV. Nothing is saved until you've seen the check results and confirmed.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($import !== null): ?>
    <ol class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-[13px]">
        <?php
        $steps = ['Upload', 'Match columns', 'Check', 'Import'];
        $at = match ($stage) { 'map' => 1, 'checked' => 2, default => 3 };
        foreach ($steps as $i => $s):
            $done = $i < $at || $stage === 'committed';
            ?>
            <li class="flex items-center gap-2 <?= $i === $at && $stage !== 'committed' ? 'font-semibold text-tx' : ($done ? 'text-ok' : 'text-tx3') ?>">
                <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px] <?= $done ? 'bg-okb text-ok' : ($i === $at ? 'bg-ac text-white' : 'bg-sf2 ring-1 ring-ln') ?>"><?= $done ? '✓' : $i + 1 ?></span><?= $e($s) ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php if ($stage === 'upload'): ?>
    <?php if (!$hasBranches): ?>
        <div class="mt-4 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-wn">Add your branches first (Structure → Branches). Every employee row needs one.</div>
    <?php endif; ?>
    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section class="rounded-[10px] border border-ln bg-sf p-5">
            <h2 class="text-[15px] font-semibold">1. Prepare your file</h2>
            <p class="mt-1 text-tx3">Use our template, or your own sheet: you'll match its columns in the next step. The first row must hold the column names.</p>
            <a href="/company/employees/import/template" class="<?= $btn ?> mt-3 border border-ln bg-sf hover:bg-sf2">Download template (CSV)</a>
            <div class="mt-4 overflow-hidden rounded-[7px] border border-ln2">
                <table class="w-full text-left text-[13px]">
                    <thead class="bg-sf2 text-[12px] text-tx3"><tr><th class="px-3 py-2 font-medium">Column</th><th class="px-3 py-2 font-medium">Notes</th></tr></thead>
                    <tbody class="divide-y divide-ln2">
                        <?php foreach ($fields as $key => [$label, $required]): ?>
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 align-top"><span class="font-mono text-[12px]"><?= $e($key) ?></span><?php if ($required): ?> <span class="ml-1 rounded bg-acs px-1 text-[10px] font-medium uppercase text-ac">new</span><?php endif; ?></td>
                                <td class="px-3 py-2 text-tx2"><?= $e($notes[$key] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-[12px] text-tx3"><span class="rounded bg-acs px-1 text-[10px] font-medium uppercase text-ac">new</span> = needed for a new employee. When updating, a blank cell keeps what is already saved.</p>
        </section>

        <div class="space-y-4">
            <section class="rounded-[10px] border border-ln bg-sf p-5">
                <h2 class="text-[15px] font-semibold">2. Upload</h2>
                <form method="post" action="/company/employees/import" enctype="multipart/form-data" class="mt-3 space-y-3">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <input type="file" name="file" required accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                           class="block w-full text-[13px] file:mr-3 file:rounded-[7px] file:border file:border-ln file:bg-sf2 file:px-3 file:py-1.5 file:text-[13px] file:font-medium">
                    <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90" <?= $hasBranches ? '' : 'disabled' ?>>Upload and continue</button>
                </form>
                <ul class="mt-4 space-y-1.5 text-[12px] text-tx3">
                    <li>.xlsx (first sheet) or .csv · up to <?= number_format(\App\Services\Corporate\EmployeeImportService::MAX_ROWS) ?> rows · 10 MB</li>
                    <li>Dates like 03/04/1990 are read as day/month (3 April).</li>
                    <li>Existing people are matched by employee no., or by mobile when there's no employee no. Importing the same file again updates them; it never makes duplicates.</li>
                    <li>Unknown branches, departments or job roles are reported, not created.</li>
                    <li>Format the mobile column as Text in Excel, or long numbers turn into 9.71E+11.</li>
                </ul>
            </section>

            <?php if ($recent !== []): ?>
                <section class="rounded-[10px] border border-ln bg-sf">
                    <h2 class="px-5 pt-4 text-[15px] font-semibold">Recent imports</h2>
                    <ul class="mt-2 divide-y divide-ln2">
                        <?php foreach ($recent as $r): [$st, $stc] = $statusText[$r['status']] ?? [$r['status'], '']; ?>
                            <li><a href="/company/employees/import/<?= (int) $r['id'] ?>" class="block px-5 py-2.5 hover:bg-sf2">
                                <div class="flex items-center justify-between gap-2"><span class="truncate font-medium"><?= $e($r['file_name']) ?></span><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium <?= $stc ?>"><?= $e($st) ?></span></div>
                                <div class="text-[12px] text-tx3"><?= $e($loc->dateTime($r['created_at'])) ?><?= !empty($r['user_name']) ? ' · ' . $e($r['user_name']) : '' ?><?php if ($r['status'] === 'committed'): ?> · <?= (int) $r['create_rows'] ?> new, <?= (int) $r['update_rows'] ?> updated<?php endif; ?></div>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($stage === 'map'): ?>
    <section class="mt-4 rounded-[10px] border border-ln bg-sf p-5"
             x-data="{ samples: <?= $e(json_encode($samples, JSON_UNESCAPED_UNICODE)) ?>, sample(col) { if (col === '') return ''; return this.samples.map(r => r[col] ?? '').filter(v => v !== '').slice(0, 3).join(' · '); } }">
        <h2 class="text-[15px] font-semibold">Match your columns</h2>
        <p class="mt-1 text-tx3">We've matched what we could from the column names. Check each one; leave a field as "Not in my file" if you don't have it.</p>
        <form method="post" action="/company/employees/import/<?= $id ?>/check" class="mt-4">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <div class="divide-y divide-ln2 rounded-[7px] border border-ln2">
                <?php foreach ($fields as $key => [$label, $required]): $sel = array_key_exists($key, $mapping) ? (string) $mapping[$key] : ''; ?>
                    <div class="grid gap-x-4 gap-y-1 px-4 py-3 sm:grid-cols-[200px_minmax(0,260px)_minmax(0,1fr)] sm:items-center" x-data="{ col: '<?= $e($sel) ?>' }">
                        <div class="font-medium"><?= $e($label) ?><?php if ($required): ?> <span class="ml-1 rounded bg-acs px-1 text-[10px] font-medium uppercase text-ac">new</span><?php endif; ?></div>
                        <select name="map[<?= $e($key) ?>]" x-model="col" class="w-full rounded-[7px] border border-ln bg-sf px-2.5 py-1.5 text-[13px]">
                            <option value="">Not in my file</option>
                            <?php foreach ($header as $i => $h): ?><option value="<?= (int) $i ?>" <?= $sel === (string) $i ? 'selected' : '' ?>><?= $e($h !== '' ? $h : 'Column ' . ($i + 1)) ?></option><?php endforeach; ?>
                        </select>
                        <div class="truncate text-[12px] text-tx3" x-text="sample(col)"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90">Check <?= number_format((int) $import['total_rows']) ?> rows</button>
                <span class="text-[13px] text-tx3">Nothing is saved yet.</span>
            </div>
        </form>
        <form method="post" action="/company/employees/import/<?= $id ?>/cancel" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="text-[13px] text-tx2 hover:text-er">Cancel and delete the file</button>
        </form>
    </section>

<?php elseif (in_array($stage, ['checked', 'committing', 'committed'], true)): ?>
    <?php
    $create = (int) $import['create_rows'];
    $update = (int) $import['update_rows'];
    $errCount = (int) $import['error_rows'];
    $skip = max(0, (int) $import['total_rows'] - $create - $update - $errCount);
    $toDo = $create + $update;
    ?>
    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <?php foreach ([
            [$stage === 'committed' ? 'Added' : 'New employees', $create, 'text-ok'],
            [$stage === 'committed' ? 'Updated' : 'Will be updated', $update, 'text-ac'],
            ['No change', $skip, 'text-tx2'],
            ['Problems', $errCount, $errCount > 0 ? 'text-er' : 'text-tx2'],
        ] as [$label, $n, $cls]): ?>
            <div class="rounded-[10px] border border-ln bg-sf px-4 py-3"><div class="text-[12px] text-tx3"><?= $e($label) ?></div><div class="mt-0.5 text-[22px] font-semibold <?= $cls ?>"><?= number_format($n) ?></div></div>
        <?php endforeach; ?>
    </div>

    <?php if ($stage === 'checked' || $stage === 'committing'): ?>
        <section class="mt-4 rounded-[10px] border border-ac/30 bg-sf p-5"
                 x-data="{ running: <?= $stage === 'committing' ? 'true' : 'false' ?>, done: <?= (int) $import['done_rows'] ?>, total: <?= $toDo ?>, error: '',
                           async run() {
                               this.running = true; this.error = '';
                               while (true) {
                                   const f = new FormData(); f.append('_csrf', '<?= $e($csrf) ?>');
                                   let d;
                                   try {
                                       const r = await fetch('/company/employees/import/<?= $id ?>/commit', { method: 'POST', body: f, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                                       d = await r.json();
                                   } catch (e) { d = { ok: false, error: 'Connection lost. Press Continue to carry on from where it stopped.' }; }
                                   if (!d.ok) { this.error = d.error || 'Stopped.'; this.running = false; return; }
                                   this.done = d.done; this.total = d.total;
                                   if (d.finished) { window.location.reload(); return; }
                               }
                           } }"
                 x-init="if (running) run()">
            <?php if ($toDo === 0): ?>
                <h2 class="text-[15px] font-semibold">Nothing to import</h2>
                <p class="mt-1 text-tx3"><?= $errCount > 0 ? 'Every row has a problem or no changes. Download the problem rows, fix them and upload again.' : 'Everyone in this file is already saved with the same details.' ?></p>
            <?php else: ?>
                <div x-show="!running">
                    <h2 class="text-[15px] font-semibold"><?= $stage === 'committing' ? 'Import paused' : 'Ready to import' ?></h2>
                    <p class="mt-1 text-tx3"><?= number_format($create) ?> new and <?= number_format($update) ?> updated employee<?= $toDo === 1 ? '' : 's' ?> will be saved.<?= $errCount > 0 ? ' Rows with problems are skipped; you can fix and import them later.' : '' ?> New employees confirm the link the first time they sign in to eClinicPro.</p>
                    <p x-show="error" x-text="error" class="mt-2 text-er"></p>
                    <form method="post" action="/company/employees/import/<?= $id ?>/commit" @submit.prevent="run()" class="mt-3 flex flex-wrap gap-2">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90"><?= $stage === 'committing' ? 'Continue import' : 'Import ' . number_format($toDo) . ' employee' . ($toDo === 1 ? '' : 's') ?></button>
                    </form>
                </div>
                <div x-show="running" x-cloak>
                    <h2 class="text-[15px] font-semibold">Importing… keep this page open</h2>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-sf2 ring-1 ring-ln"><div class="h-full bg-ac transition-all" :style="'width:' + (total ? Math.round(done * 100 / total) : 0) + '%'"></div></div>
                    <p class="mt-2 text-[13px] text-tx3"><span x-text="done.toLocaleString('en-IN')"></span> of <span x-text="total.toLocaleString('en-IN')"></span></p>
                </div>
            <?php endif; ?>
            <?php if ($stage === 'checked'): ?>
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-ln2 pt-3 text-[13px]" x-show="!running">
                    <form method="post" action="/company/employees/import/<?= $id ?>/remap"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-ac hover:underline">Change column matching</button></form>
                    <form method="post" action="/company/employees/import/<?= $id ?>/cancel"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-tx2 hover:text-er">Cancel and delete the file</button></form>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <div class="mt-4 rounded-[10px] border border-ok/20 bg-okb px-4 py-3 text-ok">
            Import finished <?= $e($loc->dateTime($import['committed_at'])) ?>. The file has been deleted from our server.
            <a href="/company/employees" class="ml-1 font-medium underline">See employees</a>
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-4">
                <h2 class="text-[15px] font-semibold">Rows with problems</h2>
                <a href="/company/employees/import/<?= $id ?>/errors" class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Download as CSV</a>
            </div>
            <p class="px-5 text-[13px] text-tx3">Fix these in the downloaded file (it has a "problem" column) and upload it again. ID numbers are left out of it; re-enter them.</p>
            <ul class="mt-2 divide-y divide-ln2">
                <?php foreach ($errors as $r): ?>
                    <li class="grid gap-x-4 gap-y-0.5 px-5 py-2.5 sm:grid-cols-[70px_minmax(0,220px)_minmax(0,1fr)]">
                        <div class="text-[12px] text-tx3">Row <?= (int) $r['row_no'] ?></div>
                        <div class="truncate font-medium"><?= $e($rowName($r['raw'])) ?></div>
                        <div class="text-er"><?= $e(implode(' ', $r['errors'])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($errCount > count($errors)): ?><p class="border-t border-ln2 px-5 py-2.5 text-[12px] text-tx3">Showing the first <?= count($errors) ?> of <?= number_format($errCount) ?>. The CSV has them all.</p><?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (($changes ?? []) !== []): ?>
        <section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
            <h2 class="px-5 pt-4 text-[15px] font-semibold">Preview</h2>
            <ul class="mt-2 divide-y divide-ln2">
                <?php foreach ($changes as $r): ?>
                    <li class="grid gap-x-4 gap-y-0.5 px-5 py-2.5 sm:grid-cols-[70px_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-center">
                        <div class="text-[12px] text-tx3">Row <?= (int) $r['row_no'] ?></div>
                        <div class="min-w-0"><div class="truncate font-medium"><?= $e($rowName($r['raw'])) ?></div><div class="truncate text-[12px] text-tx3"><?= $e(implode(' · ', array_filter([$r['raw']['employee_no'] ?? '', $r['raw']['mobile'] ?? '']))) ?></div></div>
                        <div class="truncate text-[13px] text-tx2"><?= $e(implode(' · ', array_filter([$r['raw']['job_role'] ?? '', $r['raw']['branch'] ?? '']))) ?></div>
                        <div><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium <?= $r['action'] === 'create' ? 'bg-okb text-ok' : 'bg-acs text-ac' ?>"><?= $r['action'] === 'create' ? 'New' : 'Update' ?></span></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($toDo > count($changes)): ?><p class="border-t border-ln2 px-5 py-2.5 text-[12px] text-tx3">First <?= count($changes) ?> of <?= number_format($toDo) ?>.</p><?php endif; ?>
        </section>
    <?php endif; ?>

<?php else: ?>
    <div class="mt-4 rounded-[10px] border border-ln bg-sf px-5 py-6 text-tx2">
        This import was <?= $stage === 'failed' ? 'stopped by an error' : 'cancelled' ?>; nothing from it was saved. <a href="/company/employees/import" class="text-ac hover:underline">Start a new import</a>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
