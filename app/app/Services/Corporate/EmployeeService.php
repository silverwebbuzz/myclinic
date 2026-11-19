<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\CountryRepo;
use App\Support\Locale\LocalDate;
use App\Support\Locale\Phone;

/**
 * The company's record of the people who work for it (corp_employments) and
 * their history (corp_employment_events). Design: workforce-health-phase1-design.md §2.
 *
 *  - The record is the COMPANY's data. The person's own account
 *    (patient_identities) is found by phone or created minimally, and is
 *    never changed from here (P2). The link stays 'pending' until the
 *    employee confirms it in their Work health tab (P3, chunk 1A-4).
 *  - Every read and write takes a branch scope (null = all branches,
 *    list = only these branch ids) from CorpAuth::branchScope() (P6).
 *  - Nothing is deleted: leaving is status 'terminated' + a date (P8).
 *  - Every change is an event (P9).
 */
final class EmployeeService
{
    public const TYPES = ['employee' => 'Employee', 'contractor' => 'Contractor', 'temporary' => 'Temporary', 'intern' => 'Intern / trainee'];
    public const STATUSES = ['active' => 'Active', 'on_leave' => 'On leave', 'terminated' => 'Left'];
    public const LINK_STATUSES = ['pending' => 'Not confirmed yet', 'confirmed' => 'Confirmed', 'declined' => 'Declined'];
    public const GENDERS = ['M' => 'Male', 'F' => 'Female', 'Other' => 'Other'];
    public const PER_PAGE = 50;

    /**
     * ID documents offered, by the branch's country. Aadhaar is never offered
     * or stored (house rule; Aadhaar Act s.29 storage limits).
     */
    public const ID_TYPES = [
        'IN' => ['passport' => 'Passport', 'pan' => 'PAN card', 'driving_licence' => 'Driving licence', 'voter_id' => 'Voter ID'],
        'AE' => ['emirates_id' => 'Emirates ID', 'passport' => 'Passport', 'labour_card' => 'Labour card'],
        '*' => ['passport' => 'Passport', 'national_id' => 'National ID card', 'residence_permit' => 'Residence permit', 'driving_licence' => 'Driving licence'],
    ];

    /** Fields whose change is a transfer / role change / contract change event. */
    private const ORG_FIELDS = ['branch_id', 'department_id'];
    private const ROLE_FIELDS = ['job_role_id'];
    private const CONTRACT_FIELDS = ['employment_type', 'contractor_company'];

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $f  q, branch, department, job_role, status, type, link
     * @param list<int>|null $scope
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function search(int $companyId, array $f, ?array $scope, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = self::filterSql($companyId, $f, $scope);
        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM corp_employments e WHERE e.company_id = :cid AND ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $st = $pdo->prepare(
            'SELECT e.id, e.employee_no, e.full_name, e.work_email, e.work_phone, e.employment_type, e.contractor_company,
                    e.status, e.joined_on, e.left_on, e.link_status, e.id_type, e.id_last4, e.branch_id, e.department_id, e.job_role_id,
                    b.name AS branch_name, b.country_code AS branch_country, d.name AS department_name, j.title AS job_title, j.risk_level
               FROM corp_employments e
               LEFT JOIN corp_branches b ON b.id = e.branch_id AND b.company_id = e.company_id
               LEFT JOIN corp_departments d ON d.id = e.department_id AND d.company_id = e.company_id
               LEFT JOIN corp_job_roles j ON j.id = e.job_role_id AND j.company_id = e.company_id
              WHERE e.company_id = :cid AND ' . $where . '
              ORDER BY e.status = \'terminated\', e.full_name, e.id
              LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
        );
        $st->execute($params);

        return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Counts for the list header (inside the user's scope, ignoring filters).
     *
     * @param list<int>|null $scope
     * @return array{active: int, on_leave: int, terminated: int, pending: int, declined: int}
     */
    public static function counts(int $companyId, ?array $scope): array
    {
        [$where, $params] = self::filterSql($companyId, [], $scope);
        $st = Database::connection()->prepare(
            'SELECT SUM(e.status = \'active\') AS active, SUM(e.status = \'on_leave\') AS on_leave, SUM(e.status = \'terminated\') AS terminated,
                    SUM(e.status <> \'terminated\' AND e.link_status = \'pending\') AS pending,
                    SUM(e.status <> \'terminated\' AND e.link_status = \'declined\') AS declined
               FROM corp_employments e WHERE e.company_id = :cid AND ' . $where
        );
        $st->execute($params);
        $r = $st->fetch() ?: [];

        return array_map('intval', [
            'active' => $r['active'] ?? 0, 'on_leave' => $r['on_leave'] ?? 0, 'terminated' => $r['terminated'] ?? 0,
            'pending' => $r['pending'] ?? 0, 'declined' => $r['declined'] ?? 0,
        ]);
    }

    /**
     * One employment, if it is in the company AND the user's branch scope.
     *
     * @param list<int>|null $scope
     * @return array<string, mixed>|null
     */
    public static function find(int $companyId, int $id, ?array $scope): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT e.*, b.name AS branch_name, b.country_code AS branch_country, b.timezone AS branch_timezone,
                    d.name AS department_name, j.title AS job_title, j.risk_level
               FROM corp_employments e
               LEFT JOIN corp_branches b ON b.id = e.branch_id AND b.company_id = e.company_id
               LEFT JOIN corp_departments d ON d.id = e.department_id AND d.company_id = e.company_id
               LEFT JOIN corp_job_roles j ON j.id = e.job_role_id AND j.company_id = e.company_id
              WHERE e.id = :id AND e.company_id = :cid LIMIT 1'
        );
        $st->execute(['id' => $id, 'cid' => $companyId]);
        $row = $st->fetch() ?: null;
        if ($row === null || !self::inScope($row['branch_id'] !== null ? (int) $row['branch_id'] : null, $scope)) {
            return null;
        }

        return $row;
    }

    /** @return list<array<string, mixed>> newest first */
    public static function events(int $companyId, int $employmentId): array
    {
        $st = Database::connection()->prepare(
            'SELECT ev.*, u.name AS actor_name
               FROM corp_employment_events ev
               LEFT JOIN corp_users u ON u.id = ev.actor_id AND u.company_id = ev.company_id AND ev.actor_type = \'corp_user\'
              WHERE ev.company_id = :cid AND ev.employment_id = :eid
              ORDER BY ev.id DESC LIMIT 200'
        );
        $st->execute(['cid' => $companyId, 'eid' => $employmentId]);

        return $st->fetchAll();
    }

    /** @return array<string, string> */
    public static function idTypesFor(?string $countryCode): array
    {
        return self::ID_TYPES[strtoupper((string) $countryCode)] ?? self::ID_TYPES['*'];
    }

    /** Every ID type label any country offers (for showing a stored value). */
    public static function idTypeLabel(?string $type): string
    {
        foreach (self::ID_TYPES as $list) {
            if (isset($list[(string) $type])) {
                return $list[(string) $type];
            }
        }

        return (string) $type;
    }

    /**
     * Decrypt the stored ID number for someone allowed to see it. The caller
     * checks employees.manage; this logs every reveal.
     *
     * @param list<int>|null $scope
     */
    public static function revealId(int $companyId, int $actorId, int $id, ?array $scope): ?string
    {
        $row = self::find($companyId, $id, $scope);
        if ($row === null || empty($row['id_number_enc'])) {
            return null;
        }
        $plain = CorpCrypto::decrypt((string) $row['id_number_enc']);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'employee_id_viewed', 'corp_employment', $id, ['employee_no' => $row['employee_no']]);

        return $plain;
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Add (id 0) or edit an employee.
     *
     * @param array<string, mixed> $post
     * @param list<int>|null $scope  branches the actor may manage
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function save(int $companyId, int $actorId, int $id, array $post, ?array $scope): array
    {
        $existing = null;
        if ($id > 0) {
            $existing = self::find($companyId, $id, $scope);
            if ($existing === null) {
                return ['ok' => false, 'error' => 'Employee not found.'];
            }
        }

        $clean = self::prepare($companyId, $post, $scope, $existing);
        if (isset($clean['error'])) {
            return ['ok' => false, 'error' => $clean['error']];
        }

        return self::persist($companyId, $actorId, $existing, $clean['data'], $clean['effective_on'], true);
    }

    /**
     * Write validated data (from prepare()): duplicate checks, identity
     * find/create, insert or update, events. Used by the form and the import.
     *
     * @param array<string, mixed>|null $existing  the current row for an update
     * @param array<string, mixed> $in
     * @return array{ok: bool, error?: string, id?: int, changed?: bool}
     */
    public static function persist(int $companyId, int $actorId, ?array $existing, array $in, ?string $effective, bool $audit): array
    {
        $id = $existing !== null ? (int) $existing['id'] : 0;
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();

        // Mobile must be unique inside the company (it is the person's login).
        $dup = $pdo->prepare('SELECT id, employee_no FROM corp_employments WHERE company_id = :cid AND work_phone = :ph AND id <> :id LIMIT 1');
        $dup->execute(['cid' => $companyId, 'ph' => $in['work_phone'], 'id' => $id]);
        if (($d = $dup->fetch()) !== false) {
            return ['ok' => false, 'error' => 'Employee ' . $d['employee_no'] . ' already has this mobile number.'];
        }
        if ($in['employee_no'] === '') {
            $in['employee_no'] = self::nextEmployeeNo($companyId);
        }
        $dupNo = $pdo->prepare('SELECT id FROM corp_employments WHERE company_id = :cid AND employee_no = :no AND id <> :id LIMIT 1');
        $dupNo->execute(['cid' => $companyId, 'no' => $in['employee_no'], 'id' => $id]);
        if ($dupNo->fetchColumn() !== false) {
            return ['ok' => false, 'error' => 'Another employee already has employee no. ' . $in['employee_no'] . '.'];
        }

        // Find or create the person's own account by phone (P3). A new / changed
        // phone means a different person may own it: the link starts over.
        $phoneChanged = $existing === null || (string) $existing['work_phone'] !== $in['work_phone'];
        if ($phoneChanged) {
            $in['identity_id'] = self::identityFor($in['work_phone'], $in['full_name']);
            $in['link_status'] = 'pending';
            $in['link_confirmed_at'] = null;
        }

        try {
            $pdo->beginTransaction();
            if ($existing === null) {
                $cols = array_keys($in);
                $pdo->prepare(
                    'INSERT INTO corp_employments (company_id, ' . implode(', ', $cols) . ', status, created_by, created_at, updated_at)
                     VALUES (:cid_v, :' . implode(', :', $cols) . ', \'active\', :by_v, :cr_v, :up_v)'
                )->execute($in + ['cid_v' => $companyId, 'by_v' => $actorId, 'cr_v' => $now, 'up_v' => $now]);
                $id = (int) $pdo->lastInsertId();
                self::event($companyId, $id, 'joined', null, self::eventView($in), $in['joined_on'] ?? null, 'corp_user', $actorId);
            } else {
                $changes = self::diff($existing, $in);
                $changed = $changes !== [];
                if ($changes !== []) {
                    $set = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($in)));
                    $pdo->prepare("UPDATE corp_employments SET {$set}, updated_at = :upd_v WHERE id = :id_v AND company_id = :cid_v")
                        ->execute($in + ['upd_v' => $now, 'id_v' => $id, 'cid_v' => $companyId]);
                    self::changeEvents($companyId, $id, $existing, $changes, $effective, $actorId);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[EmployeeService] save: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save the employee. Please try again.'];
        }

        if ($audit) {
            CorpAudit::log($companyId, 'corp_user', $actorId, $existing === null ? 'employee_created' : 'employee_updated', 'corp_employment', $id,
                ['employee_no' => $in['employee_no']]);
        }

        return ['ok' => true, 'id' => $id, 'changed' => $changed ?? true];
    }

    /**
     * Leaving / on leave / back / rehire.
     *
     * @param 'terminate'|'leave'|'return'|'rehire' $action
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string}
     */
    public static function changeStatus(int $companyId, int $actorId, int $id, string $action, string $date, string $reason, ?array $scope): array
    {
        $row = self::find($companyId, $id, $scope);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Employee not found.'];
        }
        $date = trim($date);
        if (!self::isYmd($date)) {
            return ['ok' => false, 'error' => 'Choose a date.'];
        }
        $reason = mb_substr(trim($reason), 0, 255);
        $status = (string) $row['status'];
        [$allowedFrom, $to, $event] = match ($action) {
            'terminate' => [['active', 'on_leave'], 'terminated', 'terminated'],
            'leave' => [['active'], 'on_leave', 'on_leave'],
            'return' => [['on_leave'], 'active', 'returned'],
            'rehire' => [['terminated'], 'active', 'rehired'],
        };
        if (!in_array($status, $allowedFrom, true)) {
            return ['ok' => false, 'error' => 'That change is not possible for an employee who is ' . strtolower(self::STATUSES[$status] ?? $status) . '.'];
        }
        if ($action === 'terminate') {
            if ($reason === '') {
                return ['ok' => false, 'error' => 'Please give a reason for leaving.'];
            }
            if (!empty($row['joined_on']) && $date < (string) $row['joined_on']) {
                return ['ok' => false, 'error' => 'The leaving date is before the joining date.'];
            }
        }
        $set = match ($action) {
            'terminate' => ['status' => 'terminated', 'left_on' => $date, 'leave_reason' => $reason],
            'rehire' => ['status' => 'active', 'left_on' => null, 'leave_reason' => null, 'joined_on' => $date],
            default => ['status' => $to],
        };
        $cols = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($set)));
        $pdo = Database::connection();
        $pdo->prepare("UPDATE corp_employments SET {$cols}, updated_at = :upd_v WHERE id = :id_v AND company_id = :cid_v")
            ->execute($set + ['upd_v' => LocalDate::nowUtc(), 'id_v' => $id, 'cid_v' => $companyId]);
        $from = ['status' => $status];
        if ($action === 'rehire') {
            $from += ['left_on' => $row['left_on'], 'leave_reason' => $row['leave_reason'], 'joined_on' => $row['joined_on']];
        }
        self::event($companyId, $id, $event, $from, array_merge($set, $reason !== '' && $action !== 'terminate' ? ['reason' => $reason] : []), $date, 'corp_user', $actorId);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'employee_' . $event, 'corp_employment', $id, ['employee_no' => $row['employee_no']]);

        return ['ok' => true];
    }

    /**
     * CSV of the filtered list. Never contains ID numbers (only the type and last 4).
     *
     * @param array<string, mixed> $f
     * @param list<int>|null $scope
     */
    public static function exportCsv(int $companyId, array $f, ?array $scope): string
    {
        [$where, $params] = self::filterSql($companyId, $f, $scope);
        $st = Database::connection()->prepare(
            'SELECT e.employee_no, e.full_name, e.work_phone, e.work_email, e.dob, e.gender, e.nationality, e.id_type, e.id_last4,
                    b.code AS branch_code, b.name AS branch_name, d.name AS department_name, j.title AS job_title,
                    e.employment_type, e.contractor_company, e.status, e.joined_on, e.left_on, e.leave_reason, e.link_status
               FROM corp_employments e
               LEFT JOIN corp_branches b ON b.id = e.branch_id AND b.company_id = e.company_id
               LEFT JOIN corp_departments d ON d.id = e.department_id AND d.company_id = e.company_id
               LEFT JOIN corp_job_roles j ON j.id = e.job_role_id AND j.company_id = e.company_id
              WHERE e.company_id = :cid AND ' . $where . ' ORDER BY e.employee_no LIMIT 50000'
        );
        $st->execute($params);
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // Excel reads UTF-8 names correctly with a BOM
        fputcsv($out, ['employee_no', 'full_name', 'mobile', 'email', 'date_of_birth', 'gender', 'nationality', 'id_type', 'id_last4',
            'branch_code', 'branch', 'department', 'job_role', 'employment_type', 'contractor_company', 'status', 'joined_on', 'left_on',
            'leave_reason', 'account_link'], ',', '"', '');
        while (($r = $st->fetch()) !== false) {
            $r = array_map(static fn ($v): string => self::csvSafe((string) ($v ?? '')), $r);
            fputcsv($out, array_values($r), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Validate and normalise one employee (form post, or an import row
     * turned into the same shape).
     *
     * @param array<string, mixed> $post
     * @param list<int>|null $scope
     * @param array<string, mixed>|null $existing
     * @param array{branch: array<int, array<string, mixed>>, department: array<int, array<string, mixed>>, job_role: array<int, array<string, mixed>>}|null $lookup
     *        preloaded structure rows by id (the import passes these so 10,000 rows don't mean 30,000 queries)
     * @return array{error: string}|array{data: array<string, mixed>, effective_on: ?string}
     */
    public static function prepare(int $companyId, array $post, ?array $scope, ?array $existing, ?array $lookup = null): array
    {
        $s = static fn (string $k, int $max): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);
        $get = static fn (string $kind, int $id): ?array => $id <= 0 ? null
            : ($lookup !== null ? ($lookup[$kind][$id] ?? null) : StructureService::find($kind, $companyId, $id));
        $branchId = (int) ($post['branch_id'] ?? 0);
        $branch = $get('branch', $branchId);
        $deptId = (int) ($post['department_id'] ?? 0);
        $jobId = (int) ($post['job_role_id'] ?? 0);
        $dept = $get('department', $deptId);
        $job = $get('job_role', $jobId);

        $in = [
            'employee_no' => strtoupper($s('employee_no', 40)),
            'full_name' => preg_replace('/\s+/', ' ', $s('full_name', 160)) ?? '',
            'work_email' => strtolower($s('work_email', 190)),
            'work_phone' => '',
            'dob' => $s('dob', 10),
            'gender' => $s('gender', 5),
            'nationality' => strtoupper($s('nationality', 2)),
            'branch_id' => $branchId,
            'department_id' => $deptId > 0 ? $deptId : null,
            'job_role_id' => $jobId > 0 ? $jobId : null,
            'employment_type' => $s('employment_type', 20) ?: 'employee',
            'contractor_company' => $s('contractor_company', 160),
            'joined_on' => $s('joined_on', 10),
        ];
        $rawPhone = $s('work_phone', 30);
        $phone = $branch !== null ? Phone::normalise($rawPhone, (string) $branch['country_code']) : null;
        $today = LocalDate::today($branch !== null ? (string) $branch['timezone'] : 'UTC');

        // A deactivated branch/department/role can be kept but not newly chosen.
        $keeps = static fn (string $f, ?array $row): bool => $row !== null && ((int) $row['is_active'] === 1 || ($existing !== null && (int) ($existing[$f] ?? 0) === (int) $row['id']));

        $error = match (true) {
            mb_strlen($in['full_name']) < 2 => 'Please enter the employee\'s full name.',
            $in['employee_no'] !== '' && preg_match('/^[A-Z0-9][A-Z0-9_\-\/.]{0,39}$/', $in['employee_no']) !== 1 => 'Employee no. can use letters, numbers and - _ / . only.',
            $branch === null || !$keeps('branch_id', $branch) => 'Choose the branch this person works at.',
            !self::inScope($branchId, $scope) => 'You can only add or move employees within your own branches.',
            $rawPhone === '' => 'Mobile number is required. It is how the employee signs in to see their health records.',
            $phone === null => 'That mobile number doesn\'t look right for ' . self::countryName((string) $branch['country_code']) . '. Include the country code if it is a foreign number (e.g. +971…).',
            $in['work_email'] !== '' && filter_var($in['work_email'], FILTER_VALIDATE_EMAIL) === false => 'Please enter a valid email address, or leave it blank.',
            $in['dob'] !== '' && !self::isYmd($in['dob']) => 'Date of birth is not a valid date.',
            $in['dob'] !== '' && ($in['dob'] >= $today || self::years($in['dob'], $today) < 14 || self::years($in['dob'], $today) > 100) => 'Please check the date of birth.',
            $in['gender'] !== '' && !array_key_exists($in['gender'], self::GENDERS) => 'Choose a gender, or leave it blank.',
            $in['nationality'] !== '' && CountryRepo::country($in['nationality']) === null && !self::isKnownCountry($in['nationality']) => 'Choose a nationality from the list.',
            $deptId > 0 && !$keeps('department_id', $dept) => 'Choose a department from the list.',
            $jobId > 0 && !$keeps('job_role_id', $job) => 'Choose a job role from the list.',
            !array_key_exists($in['employment_type'], self::TYPES) => 'Choose the type of employment.',
            $in['employment_type'] === 'contractor' && $in['contractor_company'] === '' => 'Enter the contractor\'s company (who employs them).',
            $in['joined_on'] !== '' && !self::isYmd($in['joined_on']) => 'Joining date is not a valid date.',
            $in['joined_on'] !== '' && $in['dob'] !== '' && $in['joined_on'] <= $in['dob'] => 'Joining date is before the date of birth.',
            default => null,
        };
        if ($error !== null) {
            return ['error' => $error];
        }
        $in['work_phone'] = $phone;
        if ($in['employment_type'] !== 'contractor') {
            $in['contractor_company'] = '';
        }
        foreach (['work_email', 'dob', 'gender', 'nationality', 'contractor_company', 'joined_on'] as $k) {
            $in[$k] = $in[$k] !== '' ? $in[$k] : null;
        }

        // ID document (optional, encrypted, never Aadhaar).
        $idType = $s('id_type', 30);
        $idNumber = strtoupper((string) preg_replace('/\s+/', '', $s('id_number', 60)));
        $removeId = !empty($post['id_remove']);
        $allowed = self::idTypesFor((string) $branch['country_code']);
        if ($removeId) {
            $in['id_type'] = null;
            $in['id_number_enc'] = null;
            $in['id_last4'] = null;
        } elseif ($idNumber !== '') {
            $idError = match (true) {
                $idType === '' => 'Choose the type of ID document.',
                !array_key_exists($idType, $allowed) && !($existing !== null && $existing['id_type'] === $idType) => 'Choose an ID type from the list.',
                preg_match('/^\d{12}$/', $idNumber) === 1 && strtoupper((string) $branch['country_code']) === 'IN' && $idType !== 'passport' => 'That looks like an Aadhaar number. We don\'t store Aadhaar; please use another ID or leave it blank.',
                preg_match('/^[A-Z0-9\-\/]{4,40}$/', $idNumber) !== 1 => 'ID number can use letters, numbers, - and / (4–40 characters).',
                !CorpCrypto::isConfigured() => 'ID numbers can\'t be stored yet (encryption key not set up). Leave it blank for now.',
                default => null,
            };
            if ($idError !== null) {
                return ['error' => $idError];
            }
            $in['id_type'] = $idType;
            $in['id_number_enc'] = CorpCrypto::encrypt($idNumber);
            $in['id_last4'] = substr($idNumber, -4);
        } elseif ($existing === null) {
            $in['id_type'] = null;
            $in['id_number_enc'] = null;
            $in['id_last4'] = null;
        }
        // (Editing with the number left blank keeps the stored ID unchanged.)

        $effective = $s('effective_on', 10);

        return ['data' => $in, 'effective_on' => self::isYmd($effective) ? $effective : $today];
    }

    /**
     * Find the person's own account by phone, or create a minimal one that
     * they take over the first time they sign in with that number.
     */
    private static function identityFor(string $phone, string $name): ?int
    {
        $pdo = Database::connection();
        $find = $pdo->prepare('SELECT id FROM patient_identities WHERE phone = :p LIMIT 1');
        $find->execute(['p' => $phone]);
        $id = $find->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        try {
            $pdo->prepare("INSERT INTO patient_identities (phone, name, source) VALUES (:p, :n, 'imported')")
                ->execute(['p' => $phone, 'n' => mb_substr($name, 0, 120)]);

            return (int) $pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Someone signed up with this number a moment ago (uq_phone).
            $find->execute(['p' => $phone]);
            $id = $find->fetchColumn();

            return $id !== false ? (int) $id : null;
        }
    }

    /**
     * Would writing $in change anything? (The import shows these rows as "no change".)
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $in
     */
    public static function hasChanges(array $existing, array $in): bool
    {
        return self::diff($existing, $in) !== [];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $in
     * @return array<string, array{0: mixed, 1: mixed}> field => [from, to]
     */
    private static function diff(array $existing, array $in): array
    {
        $out = [];
        foreach ($in as $k => $v) {
            $old = $existing[$k] ?? null;
            if (in_array($k, ['branch_id', 'department_id', 'job_role_id', 'identity_id'], true)) {
                $old = $old !== null ? (int) $old : null;
                $v = $v !== null ? (int) $v : null;
            }
            if ($k === 'id_number_enc') {
                // Re-encryption makes a new ciphertext every time: compare the plain value.
                if ($v !== null && $old !== null && CorpCrypto::decrypt((string) $old) === CorpCrypto::decrypt((string) $v)) {
                    continue;
                }
                if ($v === $old) {
                    continue;
                }
                $out[$k] = ['•', '•'];
                continue;
            }
            if ((string) ($old ?? '') !== (string) ($v ?? '')) {
                $out[$k] = [$old, $v];
            }
        }

        return $out;
    }

    /**
     * One event per kind of change: transferred / role_changed / contract_changed / updated.
     *
     * @param array<string, mixed> $existing
     * @param array<string, array{0: mixed, 1: mixed}> $changes
     */
    private static function changeEvents(int $companyId, int $id, array $existing, array $changes, ?string $effective, int $actorId): void
    {
        $groups = [
            'transferred' => self::ORG_FIELDS,
            'role_changed' => self::ROLE_FIELDS,
            'contract_changed' => self::CONTRACT_FIELDS,
        ];
        $used = [];
        foreach ($groups as $event => $fields) {
            $part = array_intersect_key($changes, array_flip($fields));
            if ($part !== []) {
                self::event($companyId, $id, $event, self::eventView(array_map(static fn (array $p) => $p[0], $part)),
                    self::eventView(array_map(static fn (array $p) => $p[1], $part)), $effective, 'corp_user', $actorId);
                $used += $part;
            }
        }
        $rest = array_diff_key($changes, $used, array_flip(['identity_id', 'link_status', 'link_confirmed_at', 'id_type', 'id_last4']));
        if (isset($changes['id_type']) || isset($changes['id_last4'])) {
            $rest['id_number_enc'] = ['•', '•'];
        }
        if (isset($changes['identity_id']) || isset($changes['work_phone'])) {
            $rest['account_link'] = [(string) ($existing['link_status'] ?? ''), 'pending'];
        }
        if ($rest !== []) {
            self::event($companyId, $id, 'updated', self::eventView(array_map(static fn (array $p) => $p[0], $rest)),
                self::eventView(array_map(static fn (array $p) => $p[1], $rest)), null, 'corp_user', $actorId);
        }
    }

    /**
     * What an event stores: no ciphertext, ids kept (names are looked up when shown).
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function eventView(array $values): array
    {
        unset($values['identity_id'], $values['link_confirmed_at']);
        if (array_key_exists('id_number_enc', $values)) {
            $values['id_number'] = $values['id_number_enc'] === null ? null : 'set';
            unset($values['id_number_enc']);
        }
        unset($values['id_last4']);

        return $values;
    }

    /**
     * @param array<string, mixed>|null $from
     * @param array<string, mixed>|null $to
     */
    public static function event(int $companyId, int $employmentId, string $event, ?array $from, ?array $to, ?string $effectiveOn, string $actorType, ?int $actorId): void
    {
        Database::connection()->prepare(
            'INSERT INTO corp_employment_events (company_id, employment_id, event, from_json, to_json, effective_on, actor_type, actor_id, created_at)
             VALUES (:cid, :eid, :ev, :fj, :tj, :eff, :at, :aid, :t)'
        )->execute([
            'cid' => $companyId, 'eid' => $employmentId, 'ev' => $event,
            'fj' => $from !== null ? json_encode($from, JSON_UNESCAPED_UNICODE) : null,
            'tj' => $to !== null ? json_encode($to, JSON_UNESCAPED_UNICODE) : null,
            'eff' => $effectiveOn !== null && self::isYmd($effectiveOn) ? $effectiveOn : null,
            'at' => $actorType, 'aid' => $actorId, 't' => LocalDate::nowUtc(),
        ]);
    }

    /**
     * Filter conditions + params for the list / counts / export. The caller's
     * SQL holds "e.company_id = :cid" itself (so the tenant guard test sees it);
     * :cid is in the params here. Every placeholder appears once
     * (EMULATE_PREPARES=false).
     *
     * @param array<string, mixed> $f
     * @param list<int>|null $scope
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function filterSql(int $companyId, array $f, ?array $scope): array
    {
        $where = ['1 = 1'];
        $params = ['cid' => $companyId];
        if ($scope !== null) {
            $where[] = $scope === [] ? '1 = 0' : 'e.branch_id IN (' . implode(',', array_map('intval', $scope)) . ')';
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 80), '%_\\') . '%';
            $digits = (string) preg_replace('/\D+/', '', $q);
            $cond = ['e.full_name LIKE :q1', 'e.employee_no LIKE :q2', 'e.work_email LIKE :q3'];
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
            if (strlen($digits) >= 4) {
                $cond[] = 'e.work_phone LIKE :q4';
                $params['q4'] = '%' . $digits . '%';
            }
            $where[] = '(' . implode(' OR ', $cond) . ')';
        }
        foreach (['branch' => 'branch_id', 'department' => 'department_id', 'job_role' => 'job_role_id'] as $key => $col) {
            if ((int) ($f[$key] ?? 0) > 0) {
                $where[] = "e.{$col} = :f_{$key}";
                $params['f_' . $key] = (int) $f[$key];
            }
        }
        $status = (string) ($f['status'] ?? 'current');
        if ($status === 'current') {
            $where[] = 'e.status <> \'terminated\'';
        } elseif (array_key_exists($status, self::STATUSES)) {
            $where[] = 'e.status = :f_status';
            $params['f_status'] = $status;
        }
        if (array_key_exists((string) ($f['type'] ?? ''), self::TYPES)) {
            $where[] = 'e.employment_type = :f_type';
            $params['f_type'] = (string) $f['type'];
        }
        if (array_key_exists((string) ($f['link'] ?? ''), self::LINK_STATUSES)) {
            $where[] = 'e.link_status = :f_link';
            $params['f_link'] = (string) $f['link'];
        }

        return [implode(' AND ', $where), $params];
    }

    /** @param list<int>|null $scope */
    public static function inScope(?int $branchId, ?array $scope): bool
    {
        return $scope === null || ($branchId !== null && in_array($branchId, $scope, true));
    }

    private static function nextEmployeeNo(int $companyId): string
    {
        $st = Database::connection()->prepare('SELECT COUNT(*) FROM corp_employments WHERE company_id = :cid');
        $st->execute(['cid' => $companyId]);
        $n = (int) $st->fetchColumn() + 1;
        $check = Database::connection()->prepare('SELECT 1 FROM corp_employments WHERE company_id = :cid AND employee_no = :no LIMIT 1');
        for ($i = 0; $i < 1000; $i++, $n++) {
            $no = sprintf('EMP-%05d', $n);
            $check->execute(['cid' => $companyId, 'no' => $no]);
            if ($check->fetchColumn() === false) {
                return $no;
            }
        }

        return 'EMP-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /** Stops a spreadsheet from running a cell as a formula (CSV injection). */
    public static function csvSafe(string $v): string
    {
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && !preg_match('/^[+-]?\d[\d\s]*$/', $v) ? "'" . $v : $v;
    }

    public static function isYmd(string $v): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private static function years(string $from, string $to): int
    {
        return (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->y;
    }

    private static function countryName(string $code): string
    {
        return (string) (CountryRepo::country($code)['name'] ?? $code);
    }

    private static function isKnownCountry(string $code): bool
    {
        return in_array($code, array_column(CountryRepo::allCountries(), 'code'), true);
    }
}
