<?php

declare(strict_types=1);

namespace Tests\Locale;

use App\Support\Locale\CountryRepo;

/** Primes CountryRepo with seed-identical rows so locale tests never touch the DB. */
final class LocaleFixture
{
    public static function prime(): void
    {
        $country = static fn (string $code, string $name, string $phone, string $lengths, string $cur, string $tz, string $time = 'h:i A', string $tax = 'VAT', string $taxId = 'VAT number', int $active = 0): array => [
            'code' => $code, 'name' => $name, 'phone_code' => $phone, 'phone_nsn_lengths' => $lengths,
            'currency_code' => $cur, 'default_timezone' => $tz, 'date_format' => 'd M Y', 'time_format' => $time,
            'tax_label' => $tax, 'tax_id_label' => $taxId, 'is_active' => $active, 'sort_order' => 100,
        ];

        CountryRepo::prime(
            [
                $country('IN', 'India', '91', '10', 'INR', 'Asia/Kolkata', 'h:i A', 'GST', 'GSTIN', 1),
                $country('AE', 'United Arab Emirates', '971', '8,9', 'AED', 'Asia/Dubai', 'h:i A', 'VAT', 'TRN', 1),
                $country('QA', 'Qatar', '974', '8', 'QAR', 'Asia/Qatar', 'h:i A', '', ''),
                $country('KW', 'Kuwait', '965', '8', 'KWD', 'Asia/Kuwait'),
                $country('KE', 'Kenya', '254', '9', 'KES', 'Africa/Nairobi'),
                $country('GB', 'United Kingdom', '44', '9,10', 'GBP', 'Europe/London', 'H:i'),
            ],
            [
                ['code' => 'INR', 'symbol' => '₹',   'minor_units' => 2],
                ['code' => 'AED', 'symbol' => 'AED', 'minor_units' => 2],
                ['code' => 'QAR', 'symbol' => 'QAR', 'minor_units' => 2],
                ['code' => 'KWD', 'symbol' => 'KWD', 'minor_units' => 3],
                ['code' => 'KES', 'symbol' => 'KSh', 'minor_units' => 2],
                ['code' => 'GBP', 'symbol' => '£',   'minor_units' => 2],
                ['code' => 'EUR', 'symbol' => '€',   'minor_units' => 2],
            ],
        );
    }
}
