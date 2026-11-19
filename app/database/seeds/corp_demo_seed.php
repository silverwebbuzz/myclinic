<?php

declare(strict_types=1);

/**
 * Workforce Health demo data (Phase 0 + 1A + 1B) — two dummy companies for testing.
 *
 *   php database/seeds/corp_demo_seed.php                 create (refused when APP_ENV=production)
 *   php database/seeds/corp_demo_seed.php --allow-demo    create even on production (clearly flagged is_demo=1)
 *   php database/seeds/corp_demo_seed.php --purge         remove ALL demo companies and everything under them
 *
 * Creates (is_demo = 1, excluded from metrics):
 *   Demo Infra Pvt Ltd     ECW-DEMOIN  India · Maharashtra · Construction · INR · Asia/Kolkata
 *   Demo Gulf Marine LLC   ECW-DEMOAE  UAE · Dubai · Maritime · AED · Asia/Dubai
 * each active with a 30-day trial and one login per built-in role, plus
 * (Phase 1A) 4 branches (Gulf Marine has one in India: a branch can be in
 * another country), 5 departments, 8 job roles and 100 employees each:
 * contractors, temporary staff, interns, some on leave or left, and a mix of
 * confirmed / unconfirmed / declined links. Emails use the reserved .example
 * domain, so nothing is ever delivered to a real inbox. Demo employees have
 * made-up mobiles (+91 5…, +971 59…, not real mobile ranges) and are NOT
 * linked to any patient account; invitations to them can't be delivered
 * (WhatsApp needs a recorded opt-in, email goes to .example).
 *
 * Phase 1B (when its patch is run): the SAMPLE screening templates adopted
 * and applied two years ago, with replayed history: done, waived, overdue,
 * due soon and later checks. HSE staff are left uncovered on purpose.
 *
 * All demo logins share ONE password and ONE authenticator secret, printed at
 * the end (never stored in a file). Needs the Phase 0 SQL patches run first.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpCrypto;
use App\Services\Corporate\CorpMfaService;
use App\Services\Corporate\CorpPasswordPolicy;
use App\Services\Corporate\ProgrammeService;
use App\Services\Corporate\ScreeningCaseService;
use App\Services\Corporate\ScreeningEngine;
use App\Services\Corporate\TotpService;
use App\Support\ClinicTime;
use App\Support\Locale\LocalDate;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = dirname(__DIR__, 2);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}
ClinicTime::bootstrap();

$args = array_slice($argv, 1);
$purge = in_array('--purge', $args, true);
$allow = in_array('--allow-demo', $args, true);
$pdo = Database::connection();

$hasTable = static function (string $t) use ($pdo): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
    $st->execute(['t' => $t]);

    return (int) $st->fetchColumn() > 0;
};
$demoCompanyIds = static fn (): array => array_map('intval', $pdo->query('SELECT id FROM corp_companies /* cross-company: admin */ WHERE is_demo = 1')->fetchAll(PDO::FETCH_COLUMN));

// ---------------------------------------------------------------------------
// Purge
// ---------------------------------------------------------------------------
if ($purge) {
    $ids = $demoCompanyIds();
    if ($ids === []) {
        echo "No demo companies found.\n";
        exit(0);
    }
    $in = implode(',', $ids);
    $userIds = array_map('intval', $pdo->query("SELECT id FROM corp_users WHERE company_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN));
    $pdo->beginTransaction();
    if ($userIds !== []) {
        $uin = implode(',', $userIds);
        $pdo->exec("DELETE FROM corp_password_resets WHERE user_id IN ($uin)");
    }
    $customRoles = array_map('intval', $pdo->query("SELECT id FROM corp_roles WHERE company_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN));
    if ($customRoles !== []) {
        $pdo->exec('DELETE FROM corp_role_permissions WHERE role_id IN (' . implode(',', $customRoles) . ')');
    }
    foreach (['corp_case_events', 'corp_screening_cases', 'corp_programme_assignments', 'corp_programme_items', 'corp_programmes',
              'corp_sessions', 'corp_user_roles', 'corp_invitations', 'corp_audit_log', 'corp_company_documents', 'corp_users', 'corp_roles',
              'corp_employment_events', 'corp_employee_import_rows', 'corp_employee_imports', 'corp_employments',
              'corp_branches', 'corp_departments', 'corp_job_roles'] as $t) {
        if (!$hasTable($t)) {
            continue;   // a later phase's patch not run yet
        }
        $n = $pdo->exec("DELETE FROM {$t} WHERE company_id IN ($in)");
        echo str_pad($t, 26) . " {$n} rows\n";
    }
    $pdo->exec("DELETE FROM corp_companies WHERE id IN ($in) AND is_demo = 1");
    $pdo->commit();
    foreach ($ids as $id) {
        foreach (['corporate_docs', 'corporate_imports'] as $folder) {
            $dir = dirname($base) . '/storage/' . $folder . '/' . $id;
            if (is_dir($dir)) {
                array_map('unlink', glob($dir . '/*') ?: []);
                @rmdir($dir);
            }
        }
    }
    echo 'Removed ' . count($ids) . " demo companies.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Create
// ---------------------------------------------------------------------------
if (($_ENV['APP_ENV'] ?? 'local') === 'production' && !$allow) {
    fwrite(STDERR, "APP_ENV=production. Re-run with --allow-demo if you really want demo companies here (they are flagged is_demo=1 and can be removed with --purge).\n");
    exit(1);
}
if ($pdo->query("SELECT COUNT(*) FROM corp_companies /* cross-company: admin */ WHERE public_ref IN ('ECW-DEMOIN','ECW-DEMOAE')")->fetchColumn() > 0) {
    fwrite(STDERR, "Demo companies already exist. Run with --purge first to recreate them.\n");
    exit(1);
}

$password = 'Demo-Workforce-' . random_int(1000, 9999);
$hash = CorpPasswordPolicy::hash($password);
$mfaOn = CorpMfaService::isConfigured();
$secret = TotpService::generateSecret();
$recovery = CorpMfaService::newRecoveryCodes();
$recoveryJson = json_encode(array_map(static fn (string $c): string => password_hash(str_replace('-', '', $c), PASSWORD_BCRYPT), $recovery));
$now = LocalDate::nowUtc();

$one = static function (string $sql, array $params) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchColumn();
};

$companies = [
    [
        'ref' => 'ECW-DEMOIN', 'legal' => 'Demo Infra Pvt Ltd', 'trade' => 'Demo Infra', 'cc' => 'IN', 'region' => 'Maharashtra', 'city' => 'Mumbai',
        'addr' => 'Plot 21, MIDC Industrial Area, Andheri East', 'postal' => '400093', 'reg' => 'U45200MH2015PTC000001', 'tax' => '27AAACD0000A1Z5',
        'industry' => 'construction', 'band' => '1000-4999', 'domain' => 'demo-infra.example', 'phone' => '+919800000001',
        'people' => ['company_admin' => 'Anita Sharma', 'hr_manager' => 'Rohit Verma', 'hse_manager' => 'Suresh Patil', 'billing_admin' => 'Kavya Iyer', 'viewer' => 'Manoj Rao', 'oh_physician' => 'Dr. Neha Kulkarni'],
        'branch_manager' => 'Vikram Desai',
        'no_prefix' => 'DI-', 'phone_base' => '+91500000', 'nationalities' => ['IN'],
        // [code, name, type, country, region, city]
        'branches' => [
            ['HQ-MUM', 'Mumbai Head Office', 'office', 'IN', 'Maharashtra', 'Mumbai'],
            ['SITE-NM', 'Navi Mumbai Metro Site', 'site', 'IN', 'Maharashtra', 'Navi Mumbai'],
            ['PLANT-PUNE', 'Pune Precast Plant', 'factory', 'IN', 'Maharashtra', 'Pune'],
            ['SITE-BLR', 'Bengaluru Tech Park Site', 'site', 'IN', 'Karnataka', 'Bengaluru'],
        ],
        'departments' => ['Projects', 'HSE', 'Plant & Machinery', 'Administration', 'Quality'],
        // [title, risk, ISCO-08, department index]
        'jobs' => [
            ['Site Engineer', 'medium', '2142', 0], ['Mason', 'high', '7112', 0], ['Welder', 'high', '7212', 2], ['Crane Operator', 'high', '8343', 2],
            ['Electrician', 'high', '7411', 2], ['Safety Officer', 'medium', '2263', 1], ['Office Executive', 'low', '4110', 3], ['Quality Inspector', 'medium', '3115', 4],
        ],
        // Phase 1B: [template code, applies to, [department names / job titles]]. HSE is left uncovered on purpose.
        'programmes' => [
            ['IN-ALL-OFFICE', 'department', ['Administration']],
            ['IN-CON-SITE', 'department', ['Projects', 'Plant & Machinery', 'Quality']],
            ['IN-CON-HEIGHT', 'job_role', ['Mason']],
            ['IN-CON-WELD', 'job_role', ['Welder']],
            ['IN-CON-PLANT', 'job_role', ['Crane Operator', 'Electrician']],
        ],
        'contractors' => ['Shree Labour Services', 'Om Sai Manpower', 'BuildForce Contractors'],
        'first_m' => ['Rajesh', 'Amit', 'Sunil', 'Ganesh', 'Imran', 'Prakash', 'Ravi', 'Santosh', 'Vijay', 'Arjun', 'Deepak', 'Mahesh'],
        'first_f' => ['Pooja', 'Sunita', 'Kavita', 'Meena', 'Ayesha', 'Lakshmi', 'Divya', 'Rekha'],
        'last' => ['Patil', 'Yadav', 'Shaikh', 'Kumar', 'Naik', 'Jadhav', 'Pawar', 'Gowda', 'Reddy', 'Singh', 'More', 'Khan', 'Pillai', 'Joshi'],
    ],
    [
        'ref' => 'ECW-DEMOAE', 'legal' => 'Demo Gulf Marine LLC', 'trade' => 'Gulf Marine', 'cc' => 'AE', 'region' => 'Dubai', 'city' => 'Dubai',
        'addr' => 'Plot 14, Jebel Ali Freezone South', 'postal' => '', 'reg' => 'DMCC-000123', 'tax' => '100000000000003',
        'industry' => 'maritime', 'band' => '250-999', 'domain' => 'demo-gulfmarine.example', 'phone' => '+971500000001',
        'people' => ['company_admin' => 'Priya Nair', 'hr_manager' => 'Ahmed Khan', 'hse_manager' => 'Omar Saeed', 'billing_admin' => 'Fatima Ali', 'viewer' => 'Lina Joseph', 'oh_physician' => 'Dr. Yusuf Haddad'],
        'branch_manager' => 'Rajesh Menon',
        'no_prefix' => 'E-', 'phone_base' => '+97159000', 'nationalities' => ['IN', 'PK', 'PH', 'AE', 'EG', 'BD', 'NP'],
        'branches' => [
            ['HO-DXB', 'Head Office Dubai', 'office', 'AE', 'Dubai', 'Dubai'],
            ['JAFZ-YARD', 'Jebel Ali Yard', 'yard', 'AE', 'Dubai', 'Dubai'],
            ['AUH-BASE', 'Abu Dhabi Offshore Base', 'vessel', 'AE', 'Abu Dhabi', 'Abu Dhabi'],
            ['MUM-FAB', 'Mumbai Fabrication Yard', 'yard', 'IN', 'Maharashtra', 'Mumbai'],
        ],
        'departments' => ['Marine Operations', 'Fabrication', 'HSE', 'Logistics', 'Administration'],
        'jobs' => [
            ['Deck Officer', 'medium', '3152', 0], ['Able Seaman', 'high', '8350', 0], ['Welder', 'high', '7212', 1], ['Crane Operator', 'high', '8343', 1],
            ['Marine Engineer', 'high', '2151', 0], ['HSE Officer', 'medium', '2263', 2], ['Storekeeper', 'low', '4321', 3], ['Office Administrator', 'low', '4110', 4],
        ],
        'programmes' => [
            ['AE-ALL-OFFICE', 'department', ['Administration', 'Logistics']],
            ['AE-MAR-SEAFARER', 'department', ['Marine Operations']],
            ['AE-MAR-YARD', 'department', ['Fabrication']],
        ],
        'contractors' => ['Al Noor Manpower Supply', 'Seacrest Crew Services', 'Desert Star Technical'],
        'first_m' => ['Ahmed', 'Mohammed', 'Joseph', 'Ramesh', 'Bilal', 'Jose', 'Tariq', 'Anil', 'Hassan', 'Rizwan', 'Mark', 'Suresh'],
        'first_f' => ['Fatima', 'Maria', 'Aisha', 'Grace', 'Noura', 'Anjali', 'Sara', 'Leah'],
        'last' => ['Al Mansoori', 'Hussain', 'D\'Souza', 'Fernandes', 'Rahman', 'Santos', 'Qureshi', 'Menon', 'Hassan', 'Reyes', 'Mathew', 'Iqbal', 'Thomas', 'Ali'],
    ],
];

/**
 * Phase 1A: branches, departments, job roles, a Branch Manager login and 100 employees.
 * Deterministic (same data every run). Returns a one-line summary.
 */
$seedWorkforce = static function (int $companyId, array $c, array $country, string $hash, string $now, bool $mfaOn, string $secret, string $recoveryJson, array &$logins) use ($pdo, $one): string {
    $branchIds = [];
    $branchCountry = [];
    foreach ($c['branches'] as [$code, $bname, $type, $cc, $region, $city]) {
        $tz = (string) $one('SELECT default_timezone FROM geo_countries WHERE code = :c', ['c' => $cc]);
        $pdo->prepare(
            'INSERT INTO corp_branches (company_id, name, code, type, country_code, region_id, city, timezone, is_active, created_at, updated_at)
             VALUES (:cid, :n, :code, :type, :cc, :rid, :city, :tz, 1, :cr, :up)'
        )->execute([
            'cid' => $companyId, 'n' => $bname, 'code' => $code, 'type' => $type, 'cc' => $cc,
            'rid' => $one('SELECT id FROM geo_regions WHERE country_code = :c AND name = :n', ['c' => $cc, 'n' => $region]) ?: null,
            'city' => $city, 'tz' => $tz, 'cr' => $now, 'up' => $now,
        ]);
        $branchIds[] = (int) $pdo->lastInsertId();
        $branchCountry[] = $cc;
    }
    $deptIds = [];
    foreach ($c['departments'] as $dname) {
        $pdo->prepare('INSERT INTO corp_departments (company_id, name, is_active, created_at, updated_at) VALUES (:cid, :n, 1, :cr, :up)')
            ->execute(['cid' => $companyId, 'n' => $dname, 'cr' => $now, 'up' => $now]);
        $deptIds[] = (int) $pdo->lastInsertId();
    }
    $jobIds = [];
    $jobDept = [];
    foreach ($c['jobs'] as [$title, $risk, $isco, $deptIndex]) {
        $pdo->prepare('INSERT INTO corp_job_roles (company_id, title, risk_level, isco_code, is_active, created_at, updated_at) VALUES (:cid, :t, :r, :i, 1, :cr, :up)')
            ->execute(['cid' => $companyId, 't' => $title, 'r' => $risk, 'i' => $isco, 'cr' => $now, 'up' => $now]);
        $jobIds[] = (int) $pdo->lastInsertId();
        $jobDept[] = $deptIds[$deptIndex];
    }

    // Branch Manager for the second branch (a site / yard).
    $bmRole = $one('SELECT id FROM corp_roles WHERE company_id = 0 AND code = :c', ['c' => 'branch_manager']);
    if ($bmRole !== false) {
        $email = 'branch.manager@' . $c['domain'];
        $pdo->prepare(
            'INSERT INTO corp_users (company_id, name, email, password_hash, status, is_owner, email_verified_at,
                mfa_secret_enc, mfa_enabled_at, mfa_recovery, created_at, updated_at)
             VALUES (:cid, :name, :email, :hash, \'active\', 0, :verified, :mfa, :mfa_at, :rec, :created, :updated)'
        )->execute([
            'cid' => $companyId, 'name' => $c['branch_manager'], 'email' => $email, 'hash' => $hash, 'verified' => $now,
            'mfa' => $mfaOn ? CorpCrypto::encrypt($secret) : null, 'mfa_at' => $mfaOn ? $now : null, 'rec' => $mfaOn ? $recoveryJson : null,
            'created' => $now, 'updated' => $now,
        ]);
        $pdo->prepare('INSERT INTO corp_user_roles (company_id, user_id, role_id, scope_id, created_at) VALUES (:cid, :uid, :rid, :sid, :at)')
            ->execute(['cid' => $companyId, 'uid' => (int) $pdo->lastInsertId(), 'rid' => (int) $bmRole, 'sid' => $branchIds[1], 'at' => $now]);
        $logins[] = [$c['legal'], $c['branch_manager'], 'branch_manager (' . $c['branches'][1][1] . ' only)', $email];
    }

    $emp = $pdo->prepare(
        'INSERT INTO corp_employments (company_id, identity_id, employee_no, full_name, work_email, work_phone, dob, gender, nationality,
            branch_id, department_id, job_role_id, employment_type, contractor_company, status, joined_on, left_on, leave_reason,
            link_status, link_confirmed_at, invited_at, created_by, created_at, updated_at)
         VALUES (:cid, NULL, :no, :name, :email, :phone, :dob, :gender, :nat, :bid, :did, :jid, :type, :contr, :status, :joined, :left_on, :reason,
            :link, :link_at, :inv, 0, :cr, :up)'
    );
    $ev = $pdo->prepare(
        "INSERT INTO corp_employment_events (company_id, employment_id, event, from_json, to_json, effective_on, actor_type, actor_id, created_at)
         VALUES (:cid, :eid, :ev, :fj, :tj, :eff, 'system', NULL, :at)"
    );
    $today = LocalDate::today((string) $country['default_timezone']);
    $count = ['contractor' => 0, 'left' => 0, 'confirmed' => 0];
    for ($i = 1; $i <= 100; $i++) {
        $female = $i % 4 === 0;
        $first = $female ? $c['first_f'][$i % count($c['first_f'])] : $c['first_m'][$i % count($c['first_m'])];
        $name = $first . ' ' . $c['last'][($i * 7) % count($c['last'])];
        $j = ($i * 3) % count($jobIds);
        $type = match (true) { $i % 7 === 0 => 'contractor', $i % 23 === 0 => 'temporary', $i % 31 === 0 => 'intern', default => 'employee' };
        $status = match (true) { $i % 19 === 0 => 'terminated', $i % 17 === 0 => 'on_leave', default => 'active' };
        $link = match (true) { $i % 13 === 0 => 'declined', in_array($i % 5, [0, 1, 2], true) => 'confirmed', default => 'pending' };
        $joined = LocalDate::addDays('2016-01-01', ($i * 37) % 3500);
        $left = $status === 'terminated' ? LocalDate::addDays($today, -(10 + $i)) : null;
        $no = $c['no_prefix'] . (1000 + $i);
        $emp->execute([
            'cid' => $companyId, 'no' => $no, 'name' => $name,
            'email' => $i % 3 === 0 ? null : strtolower(str_replace([' ', '\''], ['.', ''], $name)) . $i . '@' . $c['domain'],
            'phone' => $c['phone_base'] . sprintf('%04d', 1000 + $i),
            'dob' => sprintf('%04d-%02d-%02d', 1968 + ($i * 53) % 34, 1 + $i % 12, 1 + ($i * 5) % 28),
            'gender' => $female ? 'F' : 'M', 'nat' => $c['nationalities'][$i % count($c['nationalities'])],
            'bid' => $branchIds[$i % count($branchIds)], 'did' => $jobDept[$j], 'jid' => $jobIds[$j],
            'type' => $type, 'contr' => $type === 'contractor' ? $c['contractors'][$i % count($c['contractors'])] : null,
            'status' => $status, 'joined' => $joined, 'left_on' => $left, 'reason' => $left !== null ? ($i % 2 ? 'Resigned' : 'Contract ended') : null,
            'link' => $link, 'link_at' => $link === 'confirmed' ? $now : null, 'inv' => $link === 'pending' && $i % 2 ? null : $now,
            'cr' => $now, 'up' => $now,
        ]);
        $eid = (int) $pdo->lastInsertId();
        $ev->execute(['cid' => $companyId, 'eid' => $eid, 'ev' => 'joined', 'fj' => null, 'tj' => json_encode(['employee_no' => $no, 'source' => 'demo seed']), 'eff' => $joined, 'at' => $now]);
        if ($left !== null) {
            $ev->execute(['cid' => $companyId, 'eid' => $eid, 'ev' => 'terminated', 'fj' => json_encode(['status' => 'active']),
                'tj' => json_encode(['status' => 'terminated', 'left_on' => $left, 'leave_reason' => $i % 2 ? 'Resigned' : 'Contract ended']), 'eff' => $left, 'at' => $now]);
            $count['left']++;
        }
        $count['contractor'] += $type === 'contractor' ? 1 : 0;
        $count['confirmed'] += $link === 'confirmed' ? 1 : 0;
    }

    return count($branchIds) . ' branches (' . implode(', ', array_unique($branchCountry)) . '), ' . count($deptIds) . ' departments, ' . count($jobIds)
        . " job roles, 100 employees ({$count['contractor']} contractors, {$count['left']} left, {$count['confirmed']} linked)";
};

/**
 * Phase 1B: adopt the SAMPLE templates, apply them as if two years ago, then
 * replay the history with the real engine: each round the engine creates the
 * due checks, and of those already due ~68% are marked done elsewhere (a few
 * days either side of the due date), ~8% waived with a reason and the rest
 * left open (overdue). Done / waived checks start the next cycle, so the
 * result mixes overdue, due soon, later, done and waived like a live account.
 * Runs after the main commit (the services use their own transactions).
 */
$seedScreening = static function (int $companyId, array $c, string $tz) use ($pdo, $one): string {
    $actor = (int) $one('SELECT id FROM corp_users WHERE company_id = :cid AND email = :e', ['cid' => $companyId, 'e' => 'hse.manager@' . $c['domain']]);
    $hr = (int) $one('SELECT id FROM corp_users WHERE company_id = :cid AND email = :e', ['cid' => $companyId, 'e' => 'hr.manager@' . $c['domain']]);
    $today = LocalDate::today($tz);
    $applied = LocalDate::addDays($today, -730) . ' 06:00:00';   // UTC; a morning in both IN and AE
    $made = 0;
    foreach ($c['programmes'] as [$code, $type, $names]) {
        $tid = (int) $one("SELECT id FROM oh_rule_templates WHERE code = :c AND status = 'published' ORDER BY version DESC LIMIT 1", ['c' => $code]);
        $res = $tid > 0 ? ProgrammeService::adopt($companyId, $actor, $tid) : ['ok' => false, 'error' => 'not published'];
        if (!$res['ok']) {
            echo "  ! {$code}: " . ($res['error'] ?? '') . "\n";
            continue;
        }
        foreach ($names as $n) {
            $target = $type === 'job_role'
                ? (int) $one('SELECT id FROM corp_job_roles WHERE company_id = :cid AND title = :n', ['cid' => $companyId, 'n' => $n])
                : (int) $one('SELECT id FROM corp_departments WHERE company_id = :cid AND name = :n', ['cid' => $companyId, 'n' => $n]);
            ProgrammeService::addAssignment($companyId, $actor, (int) $res['id'], $type, $target);
        }
        $made++;
    }
    $pdo->prepare('UPDATE corp_programmes SET created_at = :d, updated_at = :d2 WHERE company_id = :cid')->execute(['d' => $applied, 'd2' => $applied, 'cid' => $companyId]);
    $pdo->prepare('UPDATE corp_programme_assignments SET created_at = :d WHERE company_id = :cid')->execute(['d' => $applied, 'cid' => $companyId]);

    $reasons = ['Medically exempt: doctor letter on file', 'On long project abroad, will do on return', 'Done by previous employer last month, papers awaited', 'Role changed to office work this cycle'];
    $pick = static fn (int $id, string $salt): int => (int) (crc32($id . ':' . $salt) % 100);
    $openDue = $pdo->prepare("SELECT id, due_on FROM corp_screening_cases WHERE company_id = :cid AND status = 'not_scheduled' AND due_on <= :today ORDER BY due_on, id");
    $done = $pdo->prepare(
        "UPDATE corp_screening_cases SET status = 'completed', completed_on = :d, completion_source = 'elsewhere', note = :n, updated_at = :up
          WHERE id = :id AND company_id = :cid AND status = 'not_scheduled'"
    );
    $waive = $pdo->prepare(
        "UPDATE corp_screening_cases SET status = 'waived', closed_reason = :r, updated_at = :up
          WHERE id = :id AND company_id = :cid AND status = 'not_scheduled'"
    );
    $event = $pdo->prepare(
        "INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
         VALUES (:cid, :case, :ev, :d, 'corp_user', :uid, :at)"
    );
    $decided = [];
    $tally = ['done' => 0, 'waived' => 0];
    for ($round = 0; $round < 10; $round++) {
        ScreeningEngine::syncCompany($companyId);
        $openDue->execute(['cid' => $companyId, 'today' => $today]);
        $fresh = array_filter($openDue->fetchAll(), static fn (array $r): bool => !isset($decided[(int) $r['id']]));
        if ($fresh === []) {
            break;
        }
        $now = LocalDate::nowUtc();
        $pdo->beginTransaction();
        foreach ($fresh as $r) {
            $id = (int) $r['id'];
            $decided[$id] = true;
            $p = $pick($id, 'fate');
            if ($p < 8) {
                $reason = $reasons[$pick($id, 'why') % count($reasons)];
                $waive->execute(['r' => $reason, 'up' => $now, 'id' => $id, 'cid' => $companyId]);
                $event->execute(['cid' => $companyId, 'case' => $id, 'ev' => 'waived', 'd' => json_encode(['reason' => $reason]), 'uid' => $hr, 'at' => $now]);
                CorpAudit::log($companyId, 'corp_user', $hr, 'screening_waived', 'corp_screening_case', $id, ['reason' => $reason, 'source' => 'demo seed']);
                $tally['waived']++;
            } elseif ($p < 76) {
                $on = LocalDate::addDays((string) $r['due_on'], $pick($id, 'day') % 50 - 10);
                $on = min($on, $today);
                $done->execute(['d' => $on, 'n' => 'Demo history', 'up' => $now, 'id' => $id, 'cid' => $companyId]);
                $event->execute(['cid' => $companyId, 'case' => $id, 'ev' => 'done_elsewhere', 'd' => json_encode(['completed_on' => $on, 'note' => 'Demo history']), 'uid' => $hr, 'at' => $now]);
                $tally['done']++;
            }
            // else: left open → overdue
        }
        $pdo->commit();
    }
    $counts = ScreeningCaseService::counts($companyId, null, $today);

    return "{$made} programmes; checks: {$tally['done']} done, {$tally['waived']} waived, "
        . $counts['overdue'] . ' overdue, ' . $counts['soon'] . ' due in 30 days, ' . $counts['later'] . ' later; '
        . ($counts['up_to_date_pct'] ?? 0) . '% of covered people up to date';
};

$logins = [];
$made = [];
$pdo->beginTransaction();
try {
    foreach ($companies as $c) {
        $country = $pdo->prepare('SELECT * FROM geo_countries WHERE code = :c');
        $country->execute(['c' => $c['cc']]);
        $country = $country->fetch();
        if (!$country) {
            throw new RuntimeException('Country ' . $c['cc'] . ' missing — run the Phase 0 seed SQL first.');
        }
        $regionId = $one('SELECT id FROM geo_regions WHERE country_code = :c AND name = :n', ['c' => $c['cc'], 'n' => $c['region']]) ?: null;
        $industryId = $one('SELECT id FROM corp_industries WHERE code = :c', ['c' => $c['industry']]) ?: null;
        $trialEnds = LocalDate::addDays(LocalDate::today((string) $country['default_timezone']), 30);

        $pdo->prepare(
            "INSERT INTO corp_companies (public_ref, legal_name, trade_name, country_code, region_id, city, address_line1, postal_code,
                registration_no, tax_reg_no, industry_id, business_type, employee_size_band, contact_name, contact_email, contact_phone,
                timezone, currency_code, data_region, status, approved_at, trial_ends_at, is_demo, created_at, updated_at)
             VALUES (:ref, :legal, :trade, :cc, :region, :city, :addr, :postal, :reg, :tax, :ind, 'private', :band, :cname, :cemail, :cphone,
                :tz, :cur, 'in-1', 'active', :approved, :trial, 1, :created, :updated)"
        )->execute([
            'ref' => $c['ref'], 'legal' => $c['legal'], 'trade' => $c['trade'], 'cc' => $c['cc'], 'region' => $regionId, 'city' => $c['city'],
            'addr' => $c['addr'], 'postal' => $c['postal'] !== '' ? $c['postal'] : null, 'reg' => $c['reg'], 'tax' => $c['tax'], 'ind' => $industryId,
            'band' => $c['band'], 'cname' => $c['people']['company_admin'], 'cemail' => 'admin@' . $c['domain'], 'cphone' => $c['phone'],
            'tz' => $country['default_timezone'], 'cur' => $country['currency_code'], 'approved' => $now, 'trial' => $trialEnds,
            'created' => $now, 'updated' => $now,
        ]);
        $companyId = (int) $pdo->lastInsertId();

        $first = true;
        foreach ($c['people'] as $roleCode => $name) {
            $roleId = $one('SELECT id FROM corp_roles WHERE company_id = 0 AND code = :c', ['c' => $roleCode]);
            if ($roleId === false) {
                throw new RuntimeException("System role {$roleCode} missing — run the Phase 0 seed SQL first.");
            }
            $email = str_replace('_', '.', $roleCode) . '@' . $c['domain'];
            $pdo->prepare(
                'INSERT INTO corp_users (company_id, name, email, password_hash, status, is_owner, email_verified_at,
                    mfa_secret_enc, mfa_enabled_at, mfa_recovery, created_at, updated_at)
                 VALUES (:cid, :name, :email, :hash, \'active\', :owner, :verified, :mfa, :mfa_at, :rec, :created, :updated)'
            )->execute([
                'cid' => $companyId, 'name' => $name, 'email' => $email, 'hash' => $hash, 'owner' => $first ? 1 : 0, 'verified' => $now,
                'mfa' => $mfaOn ? CorpCrypto::encrypt($secret) : null, 'mfa_at' => $mfaOn ? $now : null, 'rec' => $mfaOn ? $recoveryJson : null,
                'created' => $now, 'updated' => $now,
            ]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO corp_user_roles (company_id, user_id, role_id, scope_id, created_at) VALUES (:cid, :uid, :rid, 0, :at)')
                ->execute(['cid' => $companyId, 'uid' => $userId, 'rid' => (int) $roleId, 'at' => $now]);
            $logins[] = [$c['legal'], $name, $roleCode, $email];
            $first = false;
        }
        $stats = $seedWorkforce($companyId, $c, $country, $hash, $now, $mfaOn, $secret, $recoveryJson, $logins);
        echo "  {$stats}\n";
        $pdo->prepare(
            "INSERT INTO corp_audit_log (company_id, actor_type, actor_id, action, entity_type, entity_id, details, created_at)
             VALUES (:cid, 'system', NULL, 'company_approved', 'company', :eid, :d, :at)"
        )->execute(['cid' => $companyId, 'eid' => $companyId, 'd' => json_encode(['source' => 'demo seed', 'trial_ends' => $trialEnds]), 'at' => $now]);
        echo "Created {$c['legal']} ({$c['ref']}), trial to {$trialEnds}\n";
        $made[] = [$companyId, $c, (string) $country['default_timezone']];
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Failed, nothing was created: ' . $e->getMessage() . "\n");
    exit(1);
}

// Phase 1B screening (skipped when the 1B patch isn't run yet).
if ($hasTable('corp_screening_cases') && $hasTable('oh_rule_templates')) {
    echo "\nScreening history:\n";
    foreach ($made as [$companyId, $c, $tz]) {
        try {
            echo '  ' . $c['trade'] . ': ' . $seedScreening($companyId, $c, $tz) . "\n";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fwrite(STDERR, '  ' . $c['trade'] . ': screening history failed (' . $e->getMessage() . "). Companies and employees were created; run --purge and try again.\n");
        }
    }
} else {
    echo "\nScreening skipped: run the Phase 1B SQL patch first (then --purge and re-seed).\n";
}

echo "\nDemo logins (sign in at /company/login):\n";
foreach ($logins as [$company, $name, $role, $email]) {
    echo '  ' . str_pad($email, 40) . str_pad($role, 16) . $name . ' · ' . $company . "\n";
}
echo "\nPassword for every demo login: {$password}\n";
if ($mfaOn) {
    echo "Authenticator setup key (same for every demo login): {$secret}\n";
    echo "  Add it in Google/Microsoft Authenticator as 'Enter a setup key', type: time-based.\n";
    echo 'Backup codes (each works once per login): ' . implode('  ', array_slice($recovery, 0, 5)) . " …\n";
} else {
    echo "Two-step sign-in NOT pre-set (no CORP_DATA_KEY/STORE_DATA_KEY); each user sets it up at first sign-in if required.\n";
}
echo "\nRemove everything with: php database/seeds/corp_demo_seed.php --purge\n";
