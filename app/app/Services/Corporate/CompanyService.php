<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\CountryRepo;
use App\Support\Locale\LocalDate;
use App\Support\Locale\Phone;

/**
 * Companies (corp_companies) and their portal users (corp_users).
 * Every read of company-owned data goes through a company id.
 */
final class CompanyService
{
    private const REF_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';   // no 0/O/1/I

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_companies WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);

        return $st->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findUser(int $userId): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_users WHERE id = :id LIMIT 1');
        $st->execute(['id' => $userId]);

        return $st->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findUserByEmail(string $email): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_users WHERE email = :email LIMIT 1');
        $st->execute(['email' => strtolower(trim($email))]);

        return $st->fetch() ?: null;
    }

    /** platform_settings value, or $default when missing. */
    public static function setting(string $key, string $default = ''): string
    {
        try {
            $st = Database::connection()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = :k LIMIT 1');
            $st->execute(['k' => $key]);
            $v = $st->fetchColumn();

            return $v === false || $v === null ? $default : (string) $v;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function signupEnabled(): bool
    {
        return self::setting('corp_signup_enabled', '1') === '1';
    }

    /** @return list<array<string, mixed>> active industries for dropdowns */
    public static function industries(): array
    {
        return Database::connection()->query(
            'SELECT id, code, name FROM corp_industries WHERE is_active = 1 ORDER BY sort_order, name'
        )->fetchAll();
    }

    /** Employee-size bands, matching the corp_companies ENUM. */
    public static function sizeBands(): array
    {
        return ['1-49' => '1–49', '50-249' => '50–249', '250-999' => '250–999', '1000-4999' => '1,000–4,999', '5000+' => '5,000+'];
    }

    /**
     * Validate a registration form. Returns [clean input, error|null].
     *
     * @param array<string, mixed> $post
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    public static function validateRegistration(array $post): array
    {
        $in = [
            'legal_name' => trim((string) ($post['legal_name'] ?? '')),
            'country_code' => strtoupper(trim((string) ($post['country_code'] ?? ''))),
            'region_id' => (int) ($post['region_id'] ?? 0),
            'city' => trim((string) ($post['city'] ?? '')),
            'industry_id' => (int) ($post['industry_id'] ?? 0),
            'employee_size_band' => (string) ($post['employee_size_band'] ?? ''),
            'name' => trim((string) ($post['name'] ?? '')),
            'email' => strtolower(trim((string) ($post['email'] ?? ''))),
            'phone_raw' => trim((string) ($post['phone'] ?? '')),
            'accept_terms' => !empty($post['accept_terms']),
        ];
        $password = (string) ($post['password'] ?? '');
        $confirm = (string) ($post['password_confirm'] ?? '');

        $country = CountryRepo::country($in['country_code']);
        $in['phone'] = Phone::normalise($in['phone_raw'], $in['country_code']);

        $error = match (true) {
            mb_strlen($in['legal_name']) < 2 || mb_strlen($in['legal_name']) > 200 => 'Please enter your company\'s legal name.',
            $country === null || (int) $country['is_active'] !== 1 => 'Please choose your country.',
            $in['region_id'] > 0 && CountryRepo::region($in['country_code'], $in['region_id']) === null
                => 'Please choose a valid ' . strtolower((string) $country['region_label']) . '.',
            mb_strlen($in['city']) > 100 => 'City name is too long.',
            $in['industry_id'] <= 0 || !self::industryExists($in['industry_id']) => 'Please choose your industry.',
            !array_key_exists($in['employee_size_band'], self::sizeBands()) => 'Please choose the number of employees.',
            mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 160 => 'Please enter your full name.',
            !filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['email']) > 190 => 'Please enter a valid work email address.',
            $in['phone'] === null => 'Please enter a valid mobile number for ' . ($country['name'] ?? 'your country') . '.',
            !$in['accept_terms'] => 'Please accept the terms of service and privacy policy.',
            default => CorpPasswordPolicy::check($password, $confirm, $in['email'], $in['name']),
        };

        $in['password'] = $password;

        return [$in, $error];
    }

    /**
     * Create a pending company and its owner (Company Admin). Caller validated.
     *
     * @param array<string, mixed> $in from validateRegistration()
     * @return array{ok: bool, error?: string, company_id?: int, user_id?: int}
     */
    public static function register(array $in): array
    {
        if (self::findUserByEmail((string) $in['email']) !== null) {
            return ['ok' => false, 'error' => 'An account with this email already exists. Please sign in instead.'];
        }
        $country = CountryRepo::country((string) $in['country_code']);
        if ($country === null) {
            return ['ok' => false, 'error' => 'Please choose your country.'];
        }
        $roleId = self::systemRoleId('company_admin');
        if ($roleId === null) {
            error_log('[CompanyService::register] system role company_admin missing — run the workforce seed SQL');

            return ['ok' => false, 'error' => 'Registration is not available right now. Please try again later.'];
        }

        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO corp_companies
                    (public_ref, legal_name, country_code, region_id, city, industry_id, employee_size_band,
                     contact_name, contact_email, contact_phone, timezone, currency_code, data_region, status, created_at, updated_at)
                 VALUES (:ref, :legal_name, :cc, :region_id, :city, :industry_id, :band,
                     :contact_name, :contact_email, :contact_phone, :tz, :cur, :data_region, \'pending\', :created_at, :updated_at)'
            )->execute([
                'ref' => self::newPublicRef(),
                'legal_name' => $in['legal_name'],
                'cc' => $country['code'],
                'region_id' => $in['region_id'] > 0 ? $in['region_id'] : null,
                'city' => $in['city'] !== '' ? $in['city'] : null,
                'industry_id' => $in['industry_id'],
                'band' => $in['employee_size_band'],
                'contact_name' => $in['name'],
                'contact_email' => $in['email'],
                'contact_phone' => $in['phone'],
                'tz' => (string) $country['default_timezone'],
                'cur' => (string) $country['currency_code'],
                'data_region' => (string) ($_ENV['APP_DATA_REGION'] ?? 'in-1'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $companyId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO corp_users (company_id, name, email, phone, password_hash, status, is_owner, created_at, updated_at)
                 VALUES (:cid, :name, :email, :phone, :hash, \'active\', 1, :created_at, :updated_at)'
            )->execute([
                'cid' => $companyId,
                'name' => $in['name'],
                'email' => $in['email'],
                'phone' => $in['phone'],
                'hash' => CorpPasswordPolicy::hash((string) $in['password']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO corp_user_roles (company_id, user_id, role_id, scope_id, granted_by, created_at)
                 VALUES (:cid, :uid, :rid, 0, NULL, :created_at)'
            )->execute(['cid' => $companyId, 'uid' => $userId, 'rid' => $roleId, 'created_at' => $now]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[CompanyService::register] ' . $e->getMessage());
            // A race on the unique email lands here too.
            return ['ok' => false, 'error' => 'Could not create your account. Please try again.'];
        }

        CorpAudit::log($companyId, 'corp_user', $userId, 'company_registered', 'company', $companyId, [
            'country' => $country['code'], 'email' => $in['email'],
        ]);

        return ['ok' => true, 'company_id' => $companyId, 'user_id' => $userId];
    }

    /** Email the ops inbox that a company is waiting for review. */
    public static function notifyOpsNewCompany(int $companyId): void
    {
        $c = self::find($companyId);
        if ($c === null) {
            return;
        }
        $country = CountryRepo::country((string) $c['country_code']);
        CorpMailer::send(
            CorpMailer::opsEmail(),
            'New company to review: ' . $c['legal_name'],
            'A company registered for Workforce Health',
            [
                'Company: ' . $c['legal_name'] . ' (' . $c['public_ref'] . ')',
                'Country: ' . ($country['name'] ?? $c['country_code']) . ($c['city'] ? ' · ' . $c['city'] : ''),
                'Contact: ' . $c['contact_name'] . ' · ' . $c['contact_email'] . ' · ' . Phone::display($c['contact_phone']),
                'Review and approve it in super admin.',
            ],
            CorpMailer::appUrl() . '/admin/corporate/companies/' . (int) $c['id'],
            'Review company',
        );
    }

    /** id of a system role (company_id = 0) by code, or null. */
    public static function systemRoleId(string $code): ?int
    {
        $st = Database::connection()->prepare('SELECT id FROM corp_roles WHERE company_id = 0 AND code = :code LIMIT 1');
        $st->execute(['code' => $code]);
        $id = $st->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Role names of a user, for display ("Company Admin, HSE Manager").
     *
     * @return list<string>
     */
    public static function roleNames(int $companyId, int $userId): array
    {
        $st = Database::connection()->prepare(
            'SELECT r.name FROM corp_user_roles ur JOIN corp_roles r ON r.id = ur.role_id
              WHERE ur.company_id = :cid AND ur.user_id = :uid ORDER BY r.sort_order, r.name'
        );
        $st->execute(['cid' => $companyId, 'uid' => $userId]);

        return array_map('strval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** 'ECW-' + 6 unambiguous characters, unique. */
    public static function newPublicRef(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $ref = 'ECW-' . self::randomCode(6);
            $st = Database::connection()->prepare('SELECT 1 FROM corp_companies WHERE public_ref = :r LIMIT 1');
            $st->execute(['r' => $ref]);
            if ($st->fetchColumn() === false) {
                return $ref;
            }
        }

        return 'ECW-' . self::randomCode(10);
    }

    public static function randomCode(int $length): string
    {
        $max = strlen(self::REF_ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::REF_ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    private static function industryExists(int $id): bool
    {
        $st = Database::connection()->prepare('SELECT 1 FROM corp_industries WHERE id = :id AND is_active = 1 LIMIT 1');
        $st->execute(['id' => $id]);

        return $st->fetchColumn() !== false;
    }
}
