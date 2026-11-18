<?php

declare(strict_types=1);

/**
 * Workforce Health demo data (Phase 0) — two dummy companies for testing.
 *
 *   php database/seeds/corp_demo_seed.php                 create (refused when APP_ENV=production)
 *   php database/seeds/corp_demo_seed.php --allow-demo    create even on production (clearly flagged is_demo=1)
 *   php database/seeds/corp_demo_seed.php --purge         remove ALL demo companies and everything under them
 *
 * Creates (is_demo = 1, excluded from metrics):
 *   Demo Infra Pvt Ltd     ECW-DEMOIN  India · Maharashtra · Construction · INR · Asia/Kolkata
 *   Demo Gulf Marine LLC   ECW-DEMOAE  UAE · Dubai · Maritime · AED · Asia/Dubai
 * each active with a 30-day trial and one login per built-in role
 * (Branch Manager skipped until branches exist). Emails use the reserved
 * .example domain, so nothing is ever delivered to a real inbox.
 *
 * All demo logins share ONE password and ONE authenticator secret, printed at
 * the end (never stored in a file). Needs the Phase 0 SQL patches run first.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\Corporate\CorpCrypto;
use App\Services\Corporate\CorpMfaService;
use App\Services\Corporate\CorpPasswordPolicy;
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
    foreach (['corp_sessions', 'corp_user_roles', 'corp_invitations', 'corp_audit_log', 'corp_company_documents', 'corp_users', 'corp_roles'] as $t) {
        $n = $pdo->exec("DELETE FROM {$t} WHERE company_id IN ($in)");
        echo str_pad($t, 26) . " {$n} rows\n";
    }
    $pdo->exec("DELETE FROM corp_companies WHERE id IN ($in) AND is_demo = 1");
    $pdo->commit();
    foreach ($ids as $id) {
        $dir = dirname($base) . '/storage/corporate_docs/' . $id;
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
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
    ],
    [
        'ref' => 'ECW-DEMOAE', 'legal' => 'Demo Gulf Marine LLC', 'trade' => 'Gulf Marine', 'cc' => 'AE', 'region' => 'Dubai', 'city' => 'Dubai',
        'addr' => 'Plot 14, Jebel Ali Freezone South', 'postal' => '', 'reg' => 'DMCC-000123', 'tax' => '100000000000003',
        'industry' => 'maritime', 'band' => '250-999', 'domain' => 'demo-gulfmarine.example', 'phone' => '+971500000001',
        'people' => ['company_admin' => 'Priya Nair', 'hr_manager' => 'Ahmed Khan', 'hse_manager' => 'Omar Saeed', 'billing_admin' => 'Fatima Ali', 'viewer' => 'Lina Joseph', 'oh_physician' => 'Dr. Yusuf Haddad'],
    ],
];

$logins = [];
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
        $pdo->prepare(
            "INSERT INTO corp_audit_log (company_id, actor_type, actor_id, action, entity_type, entity_id, details, created_at)
             VALUES (:cid, 'system', NULL, 'company_approved', 'company', :eid, :d, :at)"
        )->execute(['cid' => $companyId, 'eid' => $companyId, 'd' => json_encode(['source' => 'demo seed', 'trial_ends' => $trialEnds]), 'at' => $now]);
        echo "Created {$c['legal']} ({$c['ref']}), trial to {$trialEnds}\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Failed, nothing was created: ' . $e->getMessage() . "\n");
    exit(1);
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
