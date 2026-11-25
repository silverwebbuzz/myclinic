<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\CountryRepo;
use App\Support\Locale\LocalDate;
use App\Support\Locale\Phone;

/**
 * The company's own profile (/company/profile). Country can't be changed here
 * (it decides currency, rules and data region — eClinicPro support changes it),
 * and the legal name locks once eClinicPro has approved the company.
 */
final class CompanyProfileService
{
    public const BUSINESS_TYPES = [
        'private' => 'Private company', 'public_listed' => 'Public listed company', 'government' => 'Government / public body',
        'ngo' => 'Non-profit / NGO', 'other' => 'Other',
    ];

    /** Fields that make the profile "complete" for the setup checklist and approval. */
    public static function isComplete(array $company): bool
    {
        return !empty($company['address_line1']) && !empty($company['city']) && !empty($company['registration_no']);
    }

    /**
     * @param array<string, mixed> $company current row
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string}
     */
    public static function update(array $company, array $post, int $actorId): array
    {
        $cc = (string) $company['country_code'];
        $country = CountryRepo::country($cc);
        $str = static fn (string $k, int $max): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);

        $in = [
            'trade_name' => $str('trade_name', 200),
            'region_id' => (int) ($post['region_id'] ?? 0),
            'city' => $str('city', 100),
            'address_line1' => $str('address_line1', 200),
            'address_line2' => $str('address_line2', 200),
            'postal_code' => $str('postal_code', 20),
            'registration_no' => $str('registration_no', 80),
            'licence_no' => $str('licence_no', 80),
            'licence_expiry' => trim((string) ($post['licence_expiry'] ?? '')),
            'tax_reg_no' => $str('tax_reg_no', 40),
            'industry_id' => (int) ($post['industry_id'] ?? 0),
            'business_type' => (string) ($post['business_type'] ?? ''),
            'employee_size_band' => (string) ($post['employee_size_band'] ?? ''),
            'contact_name' => $str('contact_name', 120),
            'contact_email' => strtolower($str('contact_email', 190)),
            'website' => $str('website', 190),
        ];
        $legal = $str('legal_name', 200);
        $canRenameLegal = ($company['status'] ?? '') === 'pending';
        $phone = trim((string) ($post['contact_phone'] ?? ''));
        $phoneE164 = $phone === '' ? null : Phone::normalise($phone, $cc);
        if ($in['website'] !== '' && !preg_match('#^https?://#i', $in['website'])) {
            $in['website'] = 'https://' . $in['website'];
        }

        $error = match (true) {
            $canRenameLegal && mb_strlen($legal) < 2 => 'Please enter the company\'s legal name.',
            $in['region_id'] > 0 && CountryRepo::region($cc, $in['region_id']) === null => 'Please choose a valid ' . strtolower((string) ($country['region_label'] ?? 'region')) . '.',
            $in['licence_expiry'] !== '' && \DateTimeImmutable::createFromFormat('!Y-m-d', $in['licence_expiry']) === false => 'Licence expiry must be a valid date.',
            $in['industry_id'] <= 0 || !self::industryExists($in['industry_id']) => 'Please choose your industry.',
            $in['business_type'] !== '' && !array_key_exists($in['business_type'], self::BUSINESS_TYPES) => 'Please choose a business type.',
            !array_key_exists($in['employee_size_band'], CompanyService::sizeBands()) => 'Please choose the number of employees.',
            $in['contact_email'] !== '' && !filter_var($in['contact_email'], FILTER_VALIDATE_EMAIL) => 'Contact email is not valid.',
            $phone !== '' && $phoneE164 === null => 'Contact phone is not a valid number for ' . ($country['name'] ?? $cc) . '.',
            $in['website'] !== '' && !filter_var($in['website'], FILTER_VALIDATE_URL) => 'Website address is not valid.',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $set = [];
        foreach ($in as $k => $v) {
            $set[$k] = ($v === '' || $v === 0) ? null : $v;
        }
        $set['industry_id'] = $in['industry_id'];
        $set['employee_size_band'] = $in['employee_size_band'];
        $set['contact_phone'] = $phoneE164;
        if ($canRenameLegal) {
            $set['legal_name'] = $legal;
        }

        $changed = [];
        foreach ($set as $k => $v) {
            if ((string) ($company[$k] ?? '') !== (string) ($v ?? '')) {
                $changed[] = $k;
            }
        }
        if ($changed === []) {
            return ['ok' => true];
        }

        $cols = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($set)));
        $params = $set + ['updated_at_v' => LocalDate::nowUtc(), 'id_v' => (int) $company['id']];
        Database::connection()->prepare("UPDATE corp_companies SET {$cols}, updated_at = :updated_at_v WHERE id = :id_v")->execute($params);
        CorpAudit::log((int) $company['id'], 'corp_user', $actorId, 'profile_updated', 'company', (int) $company['id'], ['fields' => $changed]);

        return ['ok' => true];
    }

    private static function industryExists(int $id): bool
    {
        $st = Database::connection()->prepare('SELECT 1 FROM corp_industries WHERE id = :id AND is_active = 1 LIMIT 1');
        $st->execute(['id' => $id]);

        return $st->fetchColumn() !== false;
    }
}
