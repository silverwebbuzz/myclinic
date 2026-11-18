<?php

declare(strict_types=1);

namespace App\Support\Locale;

/**
 * Phone numbers in E.164 ("+971501234567") for any country.
 *
 *   Phone::normalise('050 123 4567', 'AE')    +971501234567
 *   Phone::normalise('0097150 1234567', 'AE') +971501234567
 *   Phone::normalise('98765 43210', 'IN')     +919876543210
 *   Phone::normalise('919876543210', 'IN')    +919876543210
 *   Phone::normalise('12345', 'IN')           null  (invalid)
 *   Phone::display('+971501234567')           +971 501234567
 *
 * The country's calling code and the allowed national-number lengths come from
 * geo_countries (phone_code, phone_nsn_lengths), so adding a country needs no
 * code change. Store E.164 in every new corp_* column; display with display().
 */
final class Phone
{
    private const E164_MIN = 8;
    private const E164_MAX = 15;

    /**
     * @param string $countryCode ISO alpha-2 used when the input has no "+" / "00" prefix
     */
    public static function normalise(?string $input, string $countryCode): ?string
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            return null;
        }

        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = (string) preg_replace('/\D+/', '', $raw);
        if ($international && str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }

        if ($international) {
            return self::validLength($digits) ? '+' . $digits : null;
        }

        $country = CountryRepo::country($countryCode);
        if ($country === null) {
            return null;
        }
        $cc = (string) $country['phone_code'];
        $lengths = self::lengths($country);

        // National form, with or without the trunk "0" ("050 123 4567").
        $national = ltrim($digits, '0');
        if (in_array(strlen($national), $lengths, true)) {
            return '+' . $cc . $national;
        }

        // Country code typed without "+" ("919876543210", "9710501234567").
        if (str_starts_with($digits, $cc)) {
            $rest = ltrim(substr($digits, strlen($cc)), '0');
            if (in_array(strlen($rest), $lengths, true)) {
                return '+' . $cc . $rest;
            }
        }

        return null;
    }

    public static function isValid(?string $e164): bool
    {
        return $e164 !== null
            && preg_match('/^\+\d+$/', $e164) === 1
            && self::validLength(substr($e164, 1));
    }

    /** "+971501234567" → "+971 501234567". Unknown codes are returned unchanged. */
    public static function display(?string $e164): string
    {
        if ($e164 === null || $e164 === '') {
            return '';
        }
        if (!self::isValid($e164)) {
            return $e164;
        }
        $digits = substr($e164, 1);
        $match = '';
        foreach (CountryRepo::allCountries() as $c) {
            $cc = (string) $c['phone_code'];
            if (str_starts_with($digits, $cc) && strlen($cc) > strlen($match)) {
                $match = $cc;
            }
        }

        return $match === '' ? $e164 : '+' . $match . ' ' . substr($digits, strlen($match));
    }

    /** @param array<string, mixed> $country @return list<int> */
    private static function lengths(array $country): array
    {
        $out = [];
        foreach (explode(',', (string) ($country['phone_nsn_lengths'] ?? '')) as $n) {
            $n = (int) trim($n);
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return $out;
    }

    private static function validLength(string $digits): bool
    {
        $len = strlen($digits);

        return $len >= self::E164_MIN && $len <= self::E164_MAX && ctype_digit($digits);
    }
}
