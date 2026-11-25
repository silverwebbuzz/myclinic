<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Application;
use App\Core\Database;
use App\Support\Locale\CountryRepo;
use App\Support\Locale\LocalDate;
use App\Support\Locale\Phone;

/**
 * Bulk employee import: upload → map columns → check (dry run) → confirm.
 * Design: workforce-health-phase1-design.md §2.6 (P7).
 *
 *  - Nothing touches corp_employments until the company confirms.
 *  - Every row goes through the same rules as the add-employee form
 *    (EmployeeService::prepare / persist).
 *  - Re-importing the same file UPDATES (matched by employee no., or by
 *    mobile when the row has no employee no.); it never duplicates. A blank
 *    cell keeps what is already stored.
 *  - Branch / department / job role are matched by name or code. Unknown
 *    ones are errors, never created (they'd spread typos).
 *  - The ID number is encrypted at the check step and never stored in plain,
 *    not even for error rows (HR re-enters it in the corrected file).
 *  - The file is deleted once the import is committed or cancelled.
 */
final class EmployeeImportService
{
    public const MAX_ROWS = 10000;
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const BATCH = 300;

    /**
     * Template columns: key => [label, required for a NEW employee, header synonyms].
     * Synonyms are compared after normalising (lower case, letters and digits only).
     */
    public const FIELDS = [
        'employee_no' => ['Employee no.', false, ['emp id', 'employee id', 'emp no', 'employee number', 'emp code', 'employee code', 'staff id', 'staff no', 'emp number']],
        'full_name' => ['Full name', true, ['name', 'employee name', 'emp name', 'staff name', 'full name']],
        'mobile' => ['Mobile', true, ['mobile no', 'mobile number', 'phone', 'phone no', 'phone number', 'contact', 'contact no', 'contact number', 'cell', 'whatsapp', 'work phone']],
        'branch' => ['Branch (name or code)', true, ['branch name', 'branch code', 'location', 'site', 'work location', 'office', 'work site']],
        'email' => ['Email', false, ['email id', 'email address', 'work email', 'mail', 'e mail']],
        'date_of_birth' => ['Date of birth', false, ['dob', 'birth date', 'birthdate', 'd o b']],
        'gender' => ['Gender', false, ['sex']],
        'nationality' => ['Nationality', false, ['country', 'citizenship']],
        'department' => ['Department', false, ['dept', 'department name', 'division', 'team']],
        'job_role' => ['Job role', false, ['designation', 'job title', 'position', 'role', 'title', 'occupation']],
        'employment_type' => ['Employment type', false, ['type', 'emp type', 'employee type', 'category', 'contract type']],
        'contractor_company' => ['Contractor company', false, ['contractor', 'agency', 'vendor', 'subcontractor', 'contractor name']],
        'joined_on' => ['Joining date', false, ['doj', 'date of joining', 'join date', 'joining date', 'start date', 'hire date', 'joined']],
        'id_type' => ['ID type', false, ['id document', 'document type', 'id doc type']],
        'id_number' => ['ID number', false, ['id no', 'document number', 'passport no', 'passport number', 'emirates id', 'emirates id no', 'national id']],
    ];

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public static function find(int $companyId, int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_employee_imports WHERE id = :id AND company_id = :cid LIMIT 1');
        $st->execute(['id' => $id, 'cid' => $companyId]);

        return $st->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $companyId, int $limit = 10): array
    {
        $st = Database::connection()->prepare(
            'SELECT i.*, u.name AS user_name FROM corp_employee_imports i
               LEFT JOIN corp_users u ON u.id = i.created_by AND u.company_id = i.company_id
              WHERE i.company_id = :cid ORDER BY i.id DESC LIMIT ' . max(1, min(50, $limit))
        );
        $st->execute(['cid' => $companyId]);

        return $st->fetchAll();
    }

    /**
     * Checked rows for the results screen.
     *
     * @param list<string> $actions
     * @return list<array{row_no: int, action: string, raw: array<string, string>, errors: list<string>, employment_id: ?int}>
     */
    public static function rows(int $companyId, int $importId, array $actions, int $limit = 200): array
    {
        $actions = array_values(array_intersect($actions, ['create', 'update', 'skip', 'error']));
        if ($actions === []) {
            return [];
        }
        $st = Database::connection()->prepare(
            "SELECT row_no, action, data_json, errors_json, employment_id FROM corp_employee_import_rows
              WHERE import_id = :iid AND company_id = :cid AND action IN ('" . implode("','", $actions) . "')
              ORDER BY row_no LIMIT " . max(1, min(10000, $limit))
        );
        $st->execute(['iid' => $importId, 'cid' => $companyId]);
        $out = [];
        while (($r = $st->fetch()) !== false) {
            $data = json_decode((string) $r['data_json'], true) ?: [];
            $out[] = [
                'row_no' => (int) $r['row_no'],
                'action' => (string) $r['action'],
                'raw' => is_array($data['raw'] ?? null) ? $data['raw'] : [],
                'errors' => json_decode((string) ($r['errors_json'] ?? ''), true) ?: [],
                'employment_id' => $r['employment_id'] !== null ? (int) $r['employment_id'] : null,
            ];
        }

        return $out;
    }

    /**
     * Header + a few sample rows for the mapping screen.
     *
     * @return array{ok: bool, error?: string, header?: list<string>, samples?: list<list<string>>}
     */
    public static function preview(array $import): array
    {
        $read = self::readFile($import, 3);
        if (!$read['ok']) {
            return $read;
        }

        return ['ok' => true, 'header' => $read['header'], 'samples' => $read['rows']];
    }

    /** CSV template (header only, so no example row is ever imported by mistake). */
    public static function templateCsv(): string
    {
        return "\xEF\xBB\xBF" . implode(',', array_keys(self::FIELDS)) . "\r\n";
    }

    // ------------------------------------------------------------------
    // Steps
    // ------------------------------------------------------------------

    /**
     * Step 1: save the uploaded file, read its header, guess the mapping.
     *
     * @param array<string, mixed> $file  one $_FILES entry
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function upload(int $companyId, int $actorId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'File too large (max 10 MB).'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Please choose a file to upload.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File too large (max 10 MB).'];
        }
        $orig = mb_substr(basename((string) ($file['name'] ?? 'employees')), 0, 190);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $okMime = match ($ext) {
            'csv', 'txt' => str_starts_with($mime, 'text/') || in_array($mime, ['application/csv', 'application/vnd.ms-excel', 'application/octet-stream'], true),
            'xlsx' => in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], true),
            default => false,
        };
        if (!$okMime) {
            return ['ok' => false, 'error' => $ext === 'xls'
                ? 'Old Excel (.xls) files aren\'t supported. In Excel choose File → Save As → Excel Workbook (.xlsx) or CSV.'
                : 'Upload a .xlsx or .csv file.'];
        }

        self::housekeeping($companyId);
        $dir = self::root() . '/' . $companyId;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not save the file. Please try again.'];
        }
        if (!is_file(self::root() . '/.htaccess')) {
            @file_put_contents(self::root() . '/.htaccess', "Require all denied\n");
        }
        $name = 'import-' . bin2hex(random_bytes(12)) . '.' . ($ext === 'txt' ? 'csv' : $ext);
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        $relPath = $companyId . '/' . $name;

        $read = self::readFile(['file_path' => $relPath], 0);
        if (!$read['ok']) {
            @unlink($dir . '/' . $name);

            return $read;
        }
        if ($read['total'] === 0) {
            @unlink($dir . '/' . $name);

            return ['ok' => false, 'error' => 'The file has a header row but no employees under it.'];
        }

        $pdo = Database::connection();
        $pdo->prepare(
            "INSERT INTO corp_employee_imports (company_id, file_name, file_path, status, mapping_json, total_rows, created_by, created_at)
             VALUES (:cid, :fn, :fp, 'uploaded', :map, :tot, :by, :at)"
        )->execute([
            'cid' => $companyId, 'fn' => $orig, 'fp' => $relPath, 'map' => json_encode(self::guessMapping($read['header'])),
            'tot' => $read['total'], 'by' => $actorId, 'at' => LocalDate::nowUtc(),
        ]);

        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /**
     * Step 2: dry run. Validates every row and stores the outcome; writes no employees.
     *
     * @param array<string, mixed> $post  map[field] = column index ('' = not in file)
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string}
     */
    public static function check(int $companyId, int $importId, array $post, ?array $scope): array
    {
        $import = self::find($companyId, $importId);
        if ($import === null || !in_array($import['status'], ['uploaded', 'checked'], true)) {
            return ['ok' => false, 'error' => 'This import can no longer be changed.'];
        }
        self::raiseLimits();
        $read = self::readFile($import, self::MAX_ROWS);
        if (!$read['ok']) {
            return $read;
        }
        $mapping = [];
        $raw = is_array($post['map'] ?? null) ? $post['map'] : [];
        foreach (array_keys(self::FIELDS) as $f) {
            $col = $raw[$f] ?? '';
            if ($col !== '' && ctype_digit((string) $col) && (int) $col < count($read['header'])) {
                $mapping[$f] = (int) $col;
            }
        }
        if (!isset($mapping['employee_no']) && !isset($mapping['mobile'])) {
            return ['ok' => false, 'error' => 'Choose the column with the employee no. or the mobile number, so we can tell new people from existing ones.'];
        }
        if (count(array_unique($mapping)) !== count($mapping)) {
            return ['ok' => false, 'error' => 'The same file column is chosen for two fields.'];
        }

        $company = CompanyService::find($companyId) ?? [];
        $ctx = self::context($companyId);
        // "Passport No" column with no ID type column: the header says the type.
        $ctx['default_id_type'] = isset($mapping['id_number']) && !isset($mapping['id_type'])
            ? self::idTypeFromHeader((string) $read['header'][$mapping['id_number']]) : '';
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM corp_employee_import_rows WHERE import_id = :iid AND company_id = :cid')->execute(['iid' => $importId, 'cid' => $companyId]);

        $counts = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];
        $seenNo = [];
        $seenPhone = [];
        $buffer = [];
        $total = 0;
        foreach ($read['rows'] as $i => $cells) {
            $vals = [];
            foreach ($mapping as $f => $col) {
                $vals[$f] = self::cell($cells[$col] ?? '', $f);
            }
            if (implode('', $vals) === '') {
                continue; // blank line
            }
            $total++;
            $rowNo = $i + 2; // spreadsheet line (header is line 1)
            [$action, $data, $errors, $empId] = self::checkRow($companyId, $vals, $scope, $ctx, (string) ($company['country_code'] ?? 'IN'), $rowNo, $seenNo, $seenPhone);
            $counts[$action]++;
            $safeRaw = $vals;
            unset($safeRaw['id_number']);
            if (($vals['id_number'] ?? '') !== '') {
                $safeRaw['id_number'] = $action === 'error' ? '' : '••••' . substr((string) preg_replace('/\s+/', '', $vals['id_number']), -4);
            }
            $buffer[] = [$importId, $companyId, $rowNo, json_encode(['raw' => $safeRaw, 'data' => $data], JSON_UNESCAPED_UNICODE),
                $action, $errors !== [] ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null, $empId];
            if (count($buffer) >= 200) {
                self::insertRows($buffer);
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            self::insertRows($buffer);
        }

        $pdo->prepare(
            "UPDATE corp_employee_imports SET status = 'checked', mapping_json = :map, total_rows = :tot, create_rows = :cr, update_rows = :up,
                    error_rows = :er, done_rows = 0 WHERE id = :id AND company_id = :cid"
        )->execute([
            'map' => json_encode($mapping), 'tot' => $total, 'cr' => $counts['create'], 'up' => $counts['update'], 'er' => $counts['error'],
            'id' => $importId, 'cid' => $companyId,
        ]);

        return ['ok' => true];
    }

    /**
     * Step 3: write the next batch of checked rows. Call until finished.
     *
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string, done?: int, total?: int, failed?: int, finished?: bool}
     */
    public static function commitBatch(int $companyId, int $actorId, int $importId, ?array $scope): array
    {
        $import = self::find($companyId, $importId);
        if ($import === null || !in_array($import['status'], ['checked', 'committing'], true)) {
            return ['ok' => false, 'error' => 'This import is not ready to be imported.'];
        }
        self::raiseLimits();
        $pdo = Database::connection();
        if ($import['status'] === 'checked') {
            $pdo->prepare("UPDATE corp_employee_imports SET status = 'committing' WHERE id = :id AND company_id = :cid AND status = 'checked'")
                ->execute(['id' => $importId, 'cid' => $companyId]);
        }
        $toDo = (int) $import['create_rows'] + (int) $import['update_rows'];

        $st = $pdo->prepare(
            "SELECT id, row_no, action, data_json, employment_id FROM corp_employee_import_rows
              WHERE import_id = :iid AND company_id = :cid AND done = 0 AND action IN ('create', 'update')
              ORDER BY row_no LIMIT " . self::BATCH
        );
        $st->execute(['iid' => $importId, 'cid' => $companyId]);
        $batch = $st->fetchAll();
        $mark = $pdo->prepare('UPDATE corp_employee_import_rows SET employment_id = :eid WHERE id = :rid AND company_id = :cid');
        $fail = $pdo->prepare("UPDATE corp_employee_import_rows SET action = 'error', errors_json = :err WHERE id = :rid AND company_id = :cid");

        // Claim each row before writing it, so two open tabs can't import the same row twice.
        $claim = $pdo->prepare('UPDATE corp_employee_import_rows SET done = 1 WHERE id = :rid AND company_id = :cid AND done = 0');
        foreach ($batch as $r) {
            $claim->execute(['rid' => (int) $r['id'], 'cid' => $companyId]);
            if ($claim->rowCount() !== 1) {
                continue;
            }
            $payload = json_decode((string) $r['data_json'], true) ?: [];
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : null;
            $effective = $data['_effective'] ?? null;
            unset($data['_effective']);
            $existing = null;
            if ($r['action'] === 'update') {
                $existing = EmployeeService::find($companyId, (int) $r['employment_id'], $scope);
            }
            $res = match (true) {
                $data === null => ['ok' => false, 'error' => 'Row data missing. Check the file again.'],
                $r['action'] === 'update' && $existing === null => ['ok' => false, 'error' => 'The employee was removed from your branches after the check.'],
                default => EmployeeService::persist($companyId, $actorId, $existing, $data, is_string($effective) ? $effective : null, false),
            };
            if ($res['ok']) {
                $mark->execute(['eid' => (int) $res['id'], 'rid' => (int) $r['id'], 'cid' => $companyId]);
            } else {
                $fail->execute(['err' => json_encode([$res['error'] ?? 'Could not save.'], JSON_UNESCAPED_UNICODE), 'rid' => (int) $r['id'], 'cid' => $companyId]);
            }
        }

        $cnt = $pdo->prepare(
            "SELECT SUM(done = 1 AND action IN ('create', 'update')) AS ok_rows, SUM(done = 1 AND action = 'error') AS failed_rows,
                    SUM(done = 0 AND action IN ('create', 'update')) AS left_rows
               FROM corp_employee_import_rows WHERE import_id = :iid AND company_id = :cid"
        );
        $cnt->execute(['iid' => $importId, 'cid' => $companyId]);
        $c = $cnt->fetch() ?: [];
        $done = (int) ($c['ok_rows'] ?? 0);
        $failed = (int) ($c['failed_rows'] ?? 0);
        $finished = (int) ($c['left_rows'] ?? 0) === 0;

        if ($finished) {
            $pdo->prepare(
                "UPDATE corp_employee_imports SET status = 'committed', done_rows = :dn, error_rows = error_rows + :fl, committed_at = :at, file_path = NULL
                  WHERE id = :id AND company_id = :cid AND status = 'committing'"
            )->execute(['dn' => $done, 'fl' => $failed, 'at' => LocalDate::nowUtc(), 'id' => $importId, 'cid' => $companyId]);
            self::deleteFile($import);
            CorpAudit::log($companyId, 'corp_user', $actorId, 'employees_imported', 'corp_employee_import', $importId, [
                'file' => $import['file_name'], 'created' => (int) $import['create_rows'], 'updated' => (int) $import['update_rows'],
                'failed_on_import' => $failed,
            ]);
        } else {
            $pdo->prepare('UPDATE corp_employee_imports SET done_rows = :dn WHERE id = :id AND company_id = :cid')
                ->execute(['dn' => $done, 'id' => $importId, 'cid' => $companyId]);
        }

        return ['ok' => true, 'done' => $done, 'total' => $toDo, 'failed' => $failed, 'finished' => $finished];
    }

    /** Back from the results to the mapping step (nothing was imported yet). */
    public static function reopen(int $companyId, int $importId): void
    {
        $pdo = Database::connection();
        $st = $pdo->prepare("UPDATE corp_employee_imports SET status = 'uploaded' WHERE id = :id AND company_id = :cid AND status = 'checked'");
        $st->execute(['id' => $importId, 'cid' => $companyId]);
        if ($st->rowCount() !== 1) {
            return; // someone started importing meanwhile: keep the rows
        }
        $pdo->prepare('DELETE FROM corp_employee_import_rows WHERE import_id = :iid AND company_id = :cid')->execute(['iid' => $importId, 'cid' => $companyId]);
    }

    /** @return array{ok: bool, error?: string} */
    public static function cancel(int $companyId, int $actorId, int $importId): array
    {
        $import = self::find($companyId, $importId);
        if ($import === null || !in_array($import['status'], ['uploaded', 'checked'], true)) {
            return ['ok' => false, 'error' => 'This import can no longer be cancelled.'];
        }
        $pdo = Database::connection();
        $st = $pdo->prepare("UPDATE corp_employee_imports SET status = 'cancelled', file_path = NULL
                              WHERE id = :id AND company_id = :cid AND status IN ('uploaded', 'checked')");
        $st->execute(['id' => $importId, 'cid' => $companyId]);
        if ($st->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'This import has already started.'];
        }
        $pdo->prepare('DELETE FROM corp_employee_import_rows WHERE import_id = :iid AND company_id = :cid')->execute(['iid' => $importId, 'cid' => $companyId]);
        self::deleteFile($import);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'employee_import_cancelled', 'corp_employee_import', $importId, ['file' => $import['file_name']]);

        return ['ok' => true];
    }

    /** CSV of the rows with problems + a reason column, in template order. */
    public static function errorsCsv(int $companyId, int $importId): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_merge(['row'], array_keys(self::FIELDS), ['problem']), ',', '"', '');
        foreach (self::rows($companyId, $importId, ['error'], 10000) as $r) {
            $line = [(string) $r['row_no']];
            foreach (array_keys(self::FIELDS) as $f) {
                $line[] = EmployeeService::csvSafe((string) ($r['raw'][$f] ?? ''));
            }
            $line[] = EmployeeService::csvSafe(implode(' ', $r['errors']));
            fputcsv($out, $line, ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    // ------------------------------------------------------------------
    // Row check
    // ------------------------------------------------------------------

    /**
     * @param array<string, string> $v  mapped, trimmed cell values
     * @param list<int>|null $scope
     * @param array<string, mixed> $ctx  from context()
     * @param array<string, int> $seenNo    employee no. => first row (by reference)
     * @param array<string, int> $seenPhone mobile => first row (by reference)
     * @return array{0: string, 1: ?array<string, mixed>, 2: list<string>, 3: ?int}
     */
    private static function checkRow(int $companyId, array $v, ?array $scope, array $ctx, string $companyCountry, int $rowNo, array &$seenNo, array &$seenPhone): array
    {
        $errors = [];
        $get = static fn (string $k): string => (string) ($v[$k] ?? '');

        // Structure, by name or code.
        $branchId = $deptId = $jobId = null;
        if ($get('branch') !== '') {
            $branchId = $ctx['branch_by'][self::key($get('branch'))] ?? null;
            if ($branchId === null) {
                $errors[] = 'Unknown branch "' . $get('branch') . '". Use a branch name or code from Structure → Branches.';
            }
        }
        if ($get('department') !== '') {
            $deptId = $ctx['department_by'][self::key($get('department'))] ?? null;
            if ($deptId === null) {
                $errors[] = 'Unknown department "' . $get('department') . '".';
            }
        }
        if ($get('job_role') !== '') {
            $jobId = $ctx['job_role_by'][self::key($get('job_role'))] ?? null;
            if ($jobId === null) {
                $errors[] = 'Unknown job role "' . $get('job_role') . '".';
            }
        }

        // Which existing employee is this? Employee no. first; mobile only when the row has none.
        $empNo = strtoupper($get('employee_no'));
        $existing = null;
        if ($empNo !== '') {
            $existing = $ctx['by_no'][$empNo] ?? null;
        } elseif ($get('mobile') !== '') {
            $country = $branchId !== null ? (string) $ctx['lookup']['branch'][$branchId]['country_code'] : $companyCountry;
            $phone = Phone::normalise($get('mobile'), $country);
            $existing = $phone !== null ? ($ctx['by_phone'][$phone] ?? null) : null;
        }
        if ($existing !== null && !EmployeeService::inScope($existing['branch_id'] !== null ? (int) $existing['branch_id'] : null, $scope)) {
            return ['error', null, ['Employee ' . $existing['employee_no'] . ' is in a branch you don\'t manage.'], null];
        }

        // Typed values.
        $dob = self::date($get('date_of_birth'));
        $joined = self::date($get('joined_on'));
        $gender = self::gender($get('gender'));
        $nat = self::country($get('nationality'));
        $type = self::employmentType($get('employment_type'));
        foreach ([
            [$dob === false, 'Date of birth "' . $get('date_of_birth') . '" is not a date we can read (use DD/MM/YYYY).'],
            [$joined === false, 'Joining date "' . $get('joined_on') . '" is not a date we can read (use DD/MM/YYYY).'],
            [$gender === false, 'Gender "' . $get('gender') . '" should be Male, Female or Other.'],
            [$nat === false, 'Nationality "' . $get('nationality') . '" is not a country we know (use the name or the 2-letter code).'],
            [$type === false, 'Employment type "' . $get('employment_type') . '" should be Employee, Contractor, Temporary or Intern.'],
        ] as [$bad, $msg]) {
            if ($bad) {
                $errors[] = $msg;
            }
        }
        if ($errors !== []) {
            return ['error', null, $errors, $existing !== null ? (int) $existing['id'] : null];
        }

        // Start from what is stored; a blank cell keeps it.
        $post = $existing !== null ? [
            'full_name' => $existing['full_name'], 'employee_no' => $existing['employee_no'], 'work_phone' => $existing['work_phone'],
            'work_email' => $existing['work_email'], 'dob' => $existing['dob'], 'gender' => $existing['gender'], 'nationality' => $existing['nationality'],
            'branch_id' => $existing['branch_id'], 'department_id' => $existing['department_id'], 'job_role_id' => $existing['job_role_id'],
            'employment_type' => $existing['employment_type'], 'contractor_company' => $existing['contractor_company'], 'joined_on' => $existing['joined_on'],
        ] : ['employment_type' => 'employee'];
        $overlay = [
            'full_name' => $get('full_name'), 'employee_no' => $empNo, 'work_phone' => $get('mobile'), 'work_email' => $get('email'),
            'dob' => (string) $dob, 'gender' => (string) $gender, 'nationality' => (string) $nat,
            'branch_id' => (string) ($branchId ?? ''), 'department_id' => (string) ($deptId ?? ''), 'job_role_id' => (string) ($jobId ?? ''),
            'employment_type' => (string) $type, 'contractor_company' => $get('contractor_company'), 'joined_on' => (string) $joined,
        ];
        foreach ($overlay as $k => $val) {
            if ($val !== '') {
                $post[$k] = $val;
            }
        }
        if ($get('id_number') !== '') {
            $branchCountry = (string) ($ctx['lookup']['branch'][(int) ($post['branch_id'] ?? 0)]['country_code'] ?? $companyCountry);
            $idType = self::idType($get('id_type') !== '' ? $get('id_type') : (string) ($ctx['default_id_type'] ?? ''), $branchCountry);
            if ($idType === null) {
                return ['error', null, [$get('id_type') === ''
                    ? 'Give the ID type for the ID number (e.g. ' . implode(', ', array_values(EmployeeService::idTypesFor($branchCountry))) . ').'
                    : 'ID type "' . $get('id_type') . '" is not accepted here. Use one of: ' . implode(', ', array_values(EmployeeService::idTypesFor($branchCountry))) . '. We never store Aadhaar.'],
                    $existing !== null ? (int) $existing['id'] : null];
            }
            $post['id_type'] = $idType;
            $post['id_number'] = $get('id_number');
        }
        if ($existing === null) {
            $missing = array_filter(['full_name' => 'full name', 'work_phone' => 'mobile', 'branch_id' => 'branch'], static fn ($k) => ($post[$k] ?? '') === '', ARRAY_FILTER_USE_KEY);
            if ($missing !== []) {
                return ['error', null, ['New employee: ' . implode(', ', $missing) . ' ' . (count($missing) === 1 ? 'is' : 'are') . ' required.'], null];
            }
        }

        $res = EmployeeService::prepare($companyId, $post, $scope, $existing, $ctx['lookup']);
        if (isset($res['error'])) {
            return ['error', null, [$res['error']], $existing !== null ? (int) $existing['id'] : null];
        }
        $data = $res['data'];
        $eid = $existing !== null ? (int) $existing['id'] : null;

        // Duplicates against stored employees and earlier rows of this file.
        $owner = $ctx['by_phone'][$data['work_phone']] ?? null;
        if ($owner !== null && (int) $owner['id'] !== $eid) {
            $errors[] = 'Mobile ' . $data['work_phone'] . ' already belongs to employee ' . $owner['employee_no'] . '.';
        }
        if (isset($seenPhone[$data['work_phone']])) {
            $errors[] = 'Same mobile as row ' . $seenPhone[$data['work_phone']] . '.';
        }
        if ($data['employee_no'] !== '') {
            $noOwner = $ctx['by_no'][$data['employee_no']] ?? null;
            if ($noOwner !== null && (int) $noOwner['id'] !== $eid) {
                $errors[] = 'Employee no. ' . $data['employee_no'] . ' already belongs to ' . $noOwner['full_name'] . '.';
            }
            if (isset($seenNo[$data['employee_no']])) {
                $errors[] = 'Same employee no. as row ' . $seenNo[$data['employee_no']] . '.';
            }
        }
        if ($errors !== []) {
            return ['error', null, $errors, $eid];
        }
        $seenPhone[$data['work_phone']] = $rowNo;
        if ($data['employee_no'] !== '') {
            $seenNo[$data['employee_no']] = $rowNo;
        }

        $action = $existing === null ? 'create' : (EmployeeService::hasChanges($existing, $data) ? 'update' : 'skip');
        $data['_effective'] = $res['effective_on'];

        return [$action, $action === 'skip' ? null : $data, [], $eid];
    }

    /**
     * Everything the row check needs, loaded once.
     *
     * @return array<string, mixed>
     */
    private static function context(int $companyId): array
    {
        $lookup = ['branch' => [], 'department' => [], 'job_role' => []];
        $by = ['branch_by' => [], 'department_by' => [], 'job_role_by' => []];
        foreach ([
            'branch' => [StructureService::branches($companyId), 'name'],
            'department' => [StructureService::departments($companyId), 'name'],
            'job_role' => [StructureService::jobRoles($companyId), 'title'],
        ] as $kind => [$rows, $nameCol]) {
            foreach ($rows as $r) {
                $lookup[$kind][(int) $r['id']] = $r;
                if ((int) $r['is_active'] !== 1) {
                    continue; // inactive ones can't be picked by name
                }
                $by[$kind . '_by'][self::key((string) $r[$nameCol])] = (int) $r['id'];
                if (!empty($r['code'])) {
                    $by[$kind . '_by'][self::key((string) $r['code'])] ??= (int) $r['id'];
                }
            }
        }
        $st = Database::connection()->prepare('SELECT * FROM corp_employments WHERE company_id = :cid');
        $st->execute(['cid' => $companyId]);
        $byNo = $byPhone = [];
        while (($e = $st->fetch()) !== false) {
            $byNo[strtoupper((string) $e['employee_no'])] = $e;
            if (!empty($e['work_phone'])) {
                $byPhone[(string) $e['work_phone']] = $e;
            }
        }

        return ['lookup' => $lookup, 'by_no' => $byNo, 'by_phone' => $byPhone] + $by;
    }

    // ------------------------------------------------------------------
    // Value parsing (public for tests)
    // ------------------------------------------------------------------

    /** Header text → field key, for the automatic mapping. @param list<string> $header @return array<string, int> */
    public static function guessMapping(array $header): array
    {
        $map = [];
        $used = [];
        foreach (self::FIELDS as $field => [$label, , $syn]) {
            $names = array_map([self::class, 'key'], array_merge([$field, str_replace('_', ' ', $field), $label], $syn));
            foreach ($header as $i => $h) {
                if (!isset($used[$i]) && in_array(self::key($h), $names, true)) {
                    $map[$field] = $i;
                    $used[$i] = true;
                    break;
                }
            }
        }

        return $map;
    }

    /** Lower case, letters and digits only: "Mobile No." → "mobileno". */
    public static function key(string $s): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($s)));
    }

    /**
     * '' = empty, false = unreadable, else Y-m-d. DD/MM is assumed for
     * ambiguous dates (stated on screen). Excel serial numbers are accepted.
     */
    public static function date(string $v): string|false
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^\d{1,5}(\.\d+)?$/', $v) === 1) {
            // A bare year ("1990") is not a date; other numbers are Excel serial dates.
            if ((float) $v < 1000 || (float) $v >= 80000 || preg_match('/^(19|20)\d\d$/', $v) === 1) {
                return false;
            }

            return (new \DateTimeImmutable('1899-12-30'))->modify('+' . (int) $v . ' days')->format('Y-m-d');
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/', $v, $m) === 1) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $v, $m) === 1) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\s\-\/]+([A-Za-z]{3,9})[\s\-\/,]+(\d{4})$/', $v, $m) === 1) {
            $mo = array_search(strtolower(substr($m[2], 0, 3)), ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], true);
            if ($mo === false) {
                return false;
            }
            [$d, $mo, $y] = [(int) $m[1], $mo + 1, (int) $m[3]];
        } else {
            return false;
        }

        return checkdate($mo, $d, $y) && $y >= 1900 && $y <= 2100 ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : false;
    }

    public static function gender(string $v): string|false
    {
        return match (self::key($v)) {
            '' => '',
            'm', 'male', 'man' => 'M',
            'f', 'female', 'woman' => 'F',
            'o', 'other', 'others', 'x', 'nonbinary' => 'Other',
            default => false,
        };
    }

    public static function employmentType(string $v): string|false
    {
        return match (self::key($v)) {
            '' => '',
            'employee', 'permanent', 'staff', 'fulltime', 'regular', 'direct', 'payroll' => 'employee',
            'contractor', 'contract', 'contractual', 'subcontractor', 'thirdparty', 'outsourced', 'agency' => 'contractor',
            'temporary', 'temp', 'casual', 'seasonal', 'daily', 'dailywage' => 'temporary',
            'intern', 'trainee', 'apprentice', 'internship' => 'intern',
            default => false,
        };
    }

    /** 2-letter code or country name → code. */
    public static function country(string $v): string|false
    {
        $k = self::key($v);
        if ($k === '') {
            return '';
        }
        $aliases = ['uae' => 'AE', 'emirati' => 'AE', 'indian' => 'IN', 'india' => 'IN', 'uk' => 'GB', 'britain' => 'GB', 'british' => 'GB',
            'usa' => 'US', 'us' => 'US', 'american' => 'US', 'pakistani' => 'PK', 'bangladeshi' => 'BD', 'nepali' => 'NP', 'nepalese' => 'NP',
            'filipino' => 'PH', 'philippines' => 'PH', 'srilankan' => 'LK', 'egyptian' => 'EG', 'saudi' => 'SA', 'kenyan' => 'KE', 'nigerian' => 'NG'];
        if (isset($aliases[$k])) {
            return $aliases[$k];
        }
        foreach (CountryRepo::allCountries() as $c) {
            if ($k === strtolower((string) $c['code']) || $k === self::key((string) $c['name'])) {
                return (string) $c['code'];
            }
        }

        return strlen($k) === 2 && ctype_alpha($k) ? strtoupper($k) : false;
    }

    /** "Passport No" → 'passport', "Emirates ID" → 'emirates_id'; '' if the header doesn't say. */
    public static function idTypeFromHeader(string $header): string
    {
        $k = self::key($header);

        return match (true) {
            str_contains($k, 'aadha') => '',
            str_contains($k, 'passport') => 'passport',
            str_contains($k, 'emirates') || $k === 'eid' || $k === 'eidno' => 'emirates_id',
            str_contains($k, 'pan') && !str_contains($k, 'company') => 'pan',
            str_contains($k, 'labour') || str_contains($k, 'labor') => 'labour_card',
            str_contains($k, 'nationalid') => 'national_id',
            default => '',
        };
    }

    /** Key or label of an ID type offered in that country; null if not allowed (Aadhaar never is). */
    public static function idType(string $v, string $countryCode): ?string
    {
        $k = self::key($v);
        if ($k === '' || str_contains($k, 'aadhaar') || str_contains($k, 'aadhar')) {
            return null;
        }
        foreach (EmployeeService::idTypesFor($countryCode) as $type => $label) {
            if ($k === self::key($type) || $k === self::key($label) || ($type === 'emirates_id' && $k === 'eid')) {
                return $type;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // File handling
    // ------------------------------------------------------------------

    public static function root(): string
    {
        return dirname(Application::basePath()) . '/storage/corporate_imports';
    }

    /**
     * Read the header and up to $limit data rows ($limit 0 = only count them).
     *
     * @param array<string, mixed> $import  needs file_path
     * @return array{ok: bool, error?: string, header?: list<string>, rows?: list<list<string>>, total?: int}
     */
    private static function readFile(array $import, int $limit): array
    {
        $rel = (string) ($import['file_path'] ?? '');
        $path = self::root() . '/' . $rel;
        if ($rel === '' || str_contains($rel, '..') || !is_file($path)) {
            return ['ok' => false, 'error' => 'The uploaded file is no longer available. Please upload it again.'];
        }
        try {
            $all = str_ends_with($rel, '.xlsx') ? self::readXlsx($path) : self::readCsv($path);
        } catch (\Throwable $e) {
            error_log('[EmployeeImport] read: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'We couldn\'t read this file. Save it again as .xlsx or CSV (UTF-8) and retry.'];
        }
        if ($all === null) {
            return ['ok' => false, 'error' => 'The file has more than ' . number_format(self::MAX_ROWS) . ' rows. Split it into smaller files.'];
        }
        $header = array_map(static fn ($h): string => trim((string) $h), array_shift($all) ?? []);
        while ($header !== [] && end($header) === '') {
            array_pop($header);
        }
        if ($header === [] || count(array_filter($header, static fn ($h) => $h !== '')) < 2) {
            return ['ok' => false, 'error' => 'The first row must hold the column names (download the template to see them).'];
        }
        $rows = array_values(array_filter($all, static fn (array $r): bool => implode('', array_map('strval', $r)) !== ''));

        return ['ok' => true, 'header' => $header, 'rows' => $limit > 0 ? array_slice($all, 0, $limit) : [], 'total' => count($rows)];
    }

    /** @return list<list<string>>|null null = too many rows */
    private static function readCsv(string $path): ?array
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('open failed');
        }
        $first = (string) fgets($fh);
        $first = (string) preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        rewind($fh);
        $out = [];
        $n = 0;
        while (($row = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            if ($n === 0 && isset($row[0])) {
                $row[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }
            if (++$n > self::MAX_ROWS + 1) {
                fclose($fh);

                return null;
            }
            $out[] = array_map(static function ($c): string {
                $c = (string) $c;

                return mb_check_encoding($c, 'UTF-8') ? $c : (string) mb_convert_encoding($c, 'UTF-8', 'Windows-1252');
            }, $row);
        }
        fclose($fh);

        return $out;
    }

    /** @return list<list<string>>|null */
    private static function readXlsx(string $path): ?array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);
        if ($sheet->getHighestDataRow() > self::MAX_ROWS + 1) {
            return null;
        }
        $out = [];
        foreach ($sheet->toArray(null, true, false, false) as $row) {
            $out[] = array_map(static fn ($c): string => is_float($c) && floor($c) === $c && abs($c) < 1e15 ? sprintf('%.0f', $c) : trim((string) $c), $row);
        }

        return $out;
    }

    private static function cell(string $v, string $field): string
    {
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));
        // Excel turns long mobile numbers into 9.71501E+11 when the column isn't text.
        if ($field === 'mobile' && preg_match('/^\d(\.\d+)?E\+\d+$/i', $v) === 1) {
            return '';
        }

        return mb_substr($v, 0, 200);
    }

    /** @param list<array{0:int,1:int,2:int,3:string,4:string,5:?string,6:?int}> $rows */
    private static function insertRows(array $rows): void
    {
        $sql = 'INSERT INTO corp_employee_import_rows (import_id, company_id, row_no, data_json, action, errors_json, employment_id) VALUES '
            . implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?, ?, ?, ?)'));
        Database::connection()->prepare($sql)->execute(array_merge(...array_map('array_values', $rows)));
    }

    /** @param array<string, mixed> $import */
    private static function deleteFile(array $import): void
    {
        $rel = (string) ($import['file_path'] ?? '');
        if ($rel !== '' && !str_contains($rel, '..')) {
            @unlink(self::root() . '/' . $rel);
        }
    }

    /** Drop files of imports left unfinished for over 7 days (they hold personal data). */
    private static function housekeeping(int $companyId): void
    {
        $st = Database::connection()->prepare(
            "SELECT id, file_path FROM corp_employee_imports
              WHERE company_id = :cid AND status IN ('uploaded', 'checked') AND created_at < :cutoff AND file_path IS NOT NULL"
        );
        $st->execute(['cid' => $companyId, 'cutoff' => LocalDate::utcPlusSeconds(-7 * 86400)]);
        foreach ($st->fetchAll() as $old) {
            self::deleteFile($old);
            Database::connection()->prepare("UPDATE corp_employee_imports SET status = 'cancelled', file_path = NULL WHERE id = :id AND company_id = :cid")
                ->execute(['id' => (int) $old['id'], 'cid' => $companyId]);
            Database::connection()->prepare('DELETE FROM corp_employee_import_rows WHERE import_id = :iid AND company_id = :cid')
                ->execute(['iid' => (int) $old['id'], 'cid' => $companyId]);
        }
    }

    private static function raiseLimits(): void
    {
        @set_time_limit(300);
        $mem = (string) ini_get('memory_limit');
        if ($mem !== '-1' && (int) $mem > 0 && (int) $mem < 512 && str_ends_with(strtoupper($mem), 'M')) {
            @ini_set('memory_limit', '512M');
        }
    }
}
