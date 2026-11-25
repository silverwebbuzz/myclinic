<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Super-admin editing of country master data (geo_countries / geo_regions)
 * and the industry list (corp_industries). Country admins may edit the
 * countries they're assigned to, but not switch countries on/off or edit
 * industries.
 */
final class GeoAdminService
{
    public const DATE_FORMATS = ['d M Y' => '18 Nov 2026', 'd/m/Y' => '18/11/2026', 'm/d/Y' => '11/18/2026', 'Y-m-d' => '2026-11-18', 'd.m.Y' => '18.11.2026', 'd-m-Y' => '18-11-2026'];
    public const TIME_FORMATS = ['h:i A' => '02:30 PM (12-hour)', 'H:i' => '14:30 (24-hour)'];
    public const WEEK_STARTS = [1 => 'Monday', 0 => 'Sunday', 6 => 'Saturday'];

    /** @return list<array<string, mixed>> */
    public static function countries(): array
    {
        return Database::connection()->query(
            'SELECT c.*, (SELECT COUNT(*) FROM geo_regions r WHERE r.country_code = c.code) AS region_count
               FROM geo_countries c ORDER BY c.is_active DESC, c.sort_order, c.name'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function currencies(): array
    {
        return Database::connection()->query('SELECT * FROM geo_currencies ORDER BY code')->fetchAll();
    }

    /** @return list<array<string, mixed>> all regions incl. inactive */
    public static function regions(string $countryCode): array
    {
        $st = Database::connection()->prepare('SELECT * FROM geo_regions WHERE country_code = :cc ORDER BY sort_order, name');
        $st->execute(['cc' => strtoupper($countryCode)]);

        return $st->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function country(string $code): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM geo_countries WHERE code = :c LIMIT 1');
        $st->execute(['c' => strtoupper($code)]);

        return $st->fetch() ?: null;
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string}
     */
    public static function updateCountry(string $code, array $post, bool $canToggleActive): array
    {
        $country = self::country($code);
        if ($country === null) {
            return ['ok' => false, 'error' => 'Country not found.'];
        }
        $s = static fn (string $k, int $max): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);
        $in = [
            'name' => $s('name', 80),
            'phone_code' => $s('phone_code', 5),
            'phone_nsn_lengths' => preg_replace('/\s+/', '', $s('phone_nsn_lengths', 20)),
            'currency_code' => strtoupper($s('currency_code', 3)),
            'default_timezone' => $s('default_timezone', 50),
            'date_format' => $s('date_format', 20),
            'time_format' => $s('time_format', 20),
            'week_start' => (int) ($post['week_start'] ?? 1),
            'region_label' => $s('region_label', 30),
            'tax_label' => $s('tax_label', 20),
            'tax_id_label' => $s('tax_id_label', 40),
            'company_id_label' => $s('company_id_label', 60),
            'postal_label' => $s('postal_label', 30),
            'sort_order' => (int) ($post['sort_order'] ?? 100),
        ];
        $currencyOk = false;
        foreach (self::currencies() as $c) {
            $currencyOk = $currencyOk || $c['code'] === $in['currency_code'];
        }
        $error = match (true) {
            mb_strlen($in['name']) < 2 => 'Country name is required.',
            preg_match('/^\d{1,4}$/', $in['phone_code']) !== 1 => 'Calling code must be 1–4 digits, without +.',
            preg_match('/^\d{1,2}(,\d{1,2})*$/', (string) $in['phone_nsn_lengths']) !== 1 => 'Phone number lengths must be numbers separated by commas, e.g. 8,9.',
            !$currencyOk => 'Choose a currency from the list.',
            !LocalDate::isValidZone($in['default_timezone']) => 'Choose a valid time zone.',
            !array_key_exists($in['date_format'], self::DATE_FORMATS) => 'Choose a date format.',
            !array_key_exists($in['time_format'], self::TIME_FORMATS) => 'Choose a time format.',
            !array_key_exists($in['week_start'], self::WEEK_STARTS) => 'Choose the first day of the week.',
            mb_strlen($in['region_label']) < 2 => 'Region label is required (e.g. State, Emirate, County).',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        foreach (['tax_label', 'tax_id_label', 'company_id_label', 'postal_label'] as $k) {
            $in[$k] = $in[$k] !== '' ? $in[$k] : null;
        }
        if ($canToggleActive) {
            $in['is_active'] = !empty($post['is_active']) ? 1 : 0;
        }
        $cols = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($in)));
        Database::connection()->prepare("UPDATE geo_countries SET {$cols} WHERE code = :code_v")->execute($in + ['code_v' => $country['code']]);

        return ['ok' => true];
    }

    public static function addRegion(string $countryCode, string $name, string $regionCode, string $timezone): array
    {
        $name = mb_substr(trim($name), 0, 80);
        $regionCode = strtoupper(mb_substr(trim($regionCode), 0, 10));
        $timezone = trim($timezone);
        if (self::country($countryCode) === null) {
            return ['ok' => false, 'error' => 'Country not found.'];
        }
        if (mb_strlen($name) < 2) {
            return ['ok' => false, 'error' => 'Region name is required.'];
        }
        if ($timezone !== '' && !LocalDate::isValidZone($timezone)) {
            return ['ok' => false, 'error' => 'Choose a valid time zone, or leave it empty.'];
        }
        try {
            Database::connection()->prepare(
                'INSERT INTO geo_regions (country_code, code, name, timezone, is_active, sort_order) VALUES (:cc, :code, :name, :tz, 1, 100)'
            )->execute(['cc' => strtoupper($countryCode), 'code' => $regionCode !== '' ? $regionCode : null, 'name' => $name, 'tz' => $timezone !== '' ? $timezone : null]);
        } catch (\PDOException) {
            return ['ok' => false, 'error' => 'That region already exists.'];
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string, country?: string} */
    public static function toggleRegion(int $regionId, ?array $allowedCountries): array
    {
        $st = Database::connection()->prepare('SELECT country_code FROM geo_regions WHERE id = :id LIMIT 1');
        $st->execute(['id' => $regionId]);
        $cc = $st->fetchColumn();
        if ($cc === false) {
            return ['ok' => false, 'error' => 'Region not found.'];
        }
        if ($allowedCountries !== null && !in_array((string) $cc, $allowedCountries, true)) {
            return ['ok' => false, 'error' => 'That region is outside your countries.'];
        }
        Database::connection()->prepare('UPDATE geo_regions SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $regionId]);

        return ['ok' => true, 'country' => (string) $cc];
    }

    /** @return list<array<string, mixed>> */
    public static function industries(): array
    {
        return Database::connection()->query(
            'SELECT i.*, (SELECT COUNT(*) FROM corp_companies c /* cross-company: admin */ WHERE c.industry_id = i.id) AS company_count
               FROM corp_industries i ORDER BY i.is_active DESC, i.sort_order, i.name'
        )->fetchAll();
    }

    public static function addIndustry(string $name, string $isic): array
    {
        $name = mb_substr(trim($name), 0, 100);
        $isic = strtoupper(mb_substr(trim($isic), 0, 10));
        if (mb_strlen($name) < 2) {
            return ['ok' => false, 'error' => 'Industry name is required.'];
        }
        $code = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_') ?: 'industry';
        $code = substr($code, 0, 36);
        $st = Database::connection()->prepare('SELECT 1 FROM corp_industries WHERE code = :c OR LOWER(name) = LOWER(:n) LIMIT 1');
        $st->execute(['c' => $code, 'n' => $name]);
        if ($st->fetchColumn() !== false) {
            return ['ok' => false, 'error' => 'That industry already exists.'];
        }
        Database::connection()->prepare(
            'INSERT INTO corp_industries (code, name, isic_code, sort_order, is_active) VALUES (:c, :n, :i, 500, 1)'
        )->execute(['c' => $code, 'n' => $name, 'i' => $isic !== '' ? $isic : null]);

        return ['ok' => true];
    }

    public static function toggleIndustry(int $id): void
    {
        Database::connection()->prepare('UPDATE corp_industries SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public static function platformAdmins(): array
    {
        return Database::connection()->query(
            'SELECT id, name, email, is_active, role, country_codes, last_login_at FROM platform_admins ORDER BY is_active DESC, name'
        )->fetchAll();
    }

    /**
     * @param list<string> $countries
     * @return array{ok: bool, error?: string}
     */
    public static function setAdminRole(int $adminId, int $actingAdminId, string $role, array $countries): array
    {
        if (!array_key_exists($role, PlatformAdminScope::ROLES)) {
            return ['ok' => false, 'error' => 'Choose a role.'];
        }
        if ($adminId === $actingAdminId && $role !== 'super') {
            return ['ok' => false, 'error' => 'You can\'t remove your own super admin access. Ask another super admin.'];
        }
        $countries = array_values(array_unique(array_filter(array_map(static fn ($c) => strtoupper(trim((string) $c)), $countries),
            static fn (string $c): bool => preg_match('/^[A-Z]{2}$/', $c) === 1 && self::country($c) !== null)));
        if ($role === 'country_admin' && $countries === []) {
            return ['ok' => false, 'error' => 'Pick at least one country for a country admin.'];
        }
        if ($role !== 'super') {
            $supers = (int) Database::connection()->query("SELECT COUNT(*) FROM platform_admins WHERE is_active = 1 AND role = 'super'")->fetchColumn();
            $st = Database::connection()->prepare("SELECT role FROM platform_admins WHERE id = :id LIMIT 1");
            $st->execute(['id' => $adminId]);
            if ($st->fetchColumn() === 'super' && $supers <= 1) {
                return ['ok' => false, 'error' => 'At least one active super admin is required.'];
            }
        }
        Database::connection()->prepare('UPDATE platform_admins SET role = :r, country_codes = :c WHERE id = :id')->execute([
            'r' => $role,
            'c' => $role === 'country_admin' ? json_encode($countries) : null,
            'id' => $adminId,
        ]);

        return ['ok' => true];
    }
}
