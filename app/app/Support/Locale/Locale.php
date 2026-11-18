<?php

declare(strict_types=1);

namespace App\Support\Locale;

/**
 * The display context for one viewer: country, timezone, currency, formats.
 *
 *   $loc = Locale::forCompany($company, $corpUser);
 *   $loc->money('1500', null)          AED 1,500.00 (company currency)
 *   $loc->dateTime($row['created_at']) 18 Nov 2026, 01:30 PM (user/company tz)
 *   $loc->date($company['trial_ends_at'])
 *   $loc->phone($user['phone'])
 *   $loc->label('tax_id_label')        TRN / GSTIN / VAT number
 *
 * Timezone precedence: user.timezone → company.timezone → country default → UTC.
 * Currency precedence:  company.currency_code → country currency.
 */
final class Locale
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $timezone,
        public readonly string $currency,
        public readonly string $dateFormat = LocalDate::DATE_FORMAT,
        public readonly string $timeFormat = LocalDate::TIME_FORMAT,
    ) {
    }

    public static function forCountry(string $countryCode): self
    {
        $c = CountryRepo::country($countryCode);
        if ($c === null) {
            return new self(strtoupper($countryCode), 'UTC', 'USD');
        }

        return new self(
            (string) $c['code'],
            LocalDate::isValidZone((string) $c['default_timezone']) ? (string) $c['default_timezone'] : 'UTC',
            (string) $c['currency_code'],
            (string) ($c['date_format'] ?: LocalDate::DATE_FORMAT),
            (string) ($c['time_format'] ?: LocalDate::TIME_FORMAT),
        );
    }

    /**
     * @param array<string, mixed>      $company corp_companies row
     * @param array<string, mixed>|null $user    corp_users row (optional)
     */
    public static function forCompany(array $company, ?array $user = null): self
    {
        $base = self::forCountry((string) ($company['country_code'] ?? ''));

        $tz = $base->timezone;
        foreach ([$user['timezone'] ?? null, $company['timezone'] ?? null] as $candidate) {
            if (LocalDate::isValidZone(is_string($candidate) ? $candidate : null)) {
                $tz = (string) $candidate;
                break;
            }
        }

        $currency = trim((string) ($company['currency_code'] ?? '')) ?: $base->currency;

        return new self($base->countryCode, $tz, strtoupper($currency), $base->dateFormat, $base->timeFormat);
    }

    public function money(string|int|float|null $amount, ?string $currency = null): string
    {
        return Money::format($amount, $currency ?? $this->currency);
    }

    public function date(?string $ymd): string
    {
        return LocalDate::date($ymd, $this->dateFormat);
    }

    public function dateTime(?string $utc): string
    {
        return LocalDate::dateTime($utc, $this->timezone, $this->dateFormat, $this->timeFormat);
    }

    public function time(?string $utc): string
    {
        return LocalDate::time($utc, $this->timezone, $this->timeFormat);
    }

    public function today(): string
    {
        return LocalDate::today($this->timezone);
    }

    public function phone(?string $e164): string
    {
        return Phone::display($e164);
    }

    public function normalisePhone(?string $input): ?string
    {
        return Phone::normalise($input, $this->countryCode);
    }

    /** @return array<string, mixed>|null */
    public function country(): ?array
    {
        return CountryRepo::country($this->countryCode);
    }

    /**
     * A country-specific form label, e.g. label('tax_id_label') → "TRN".
     * Returns $fallback when the country has none (e.g. no VAT in Qatar).
     */
    public function label(string $column, string $fallback = ''): string
    {
        $v = $this->country()[$column] ?? null;

        return is_string($v) && $v !== '' ? $v : $fallback;
    }
}
