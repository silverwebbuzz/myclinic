<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

/**
 * Global partner-program configuration (single row, id=1). Set in admin.
 */
final class PartnerSettingsService
{
    /** @return array<string, mixed> */
    public static function get(): array
    {
        $row = QueryBuilder::table('partner_settings')->where('id', '=', 1)->first();

        // Fall back to the migration defaults if the row is somehow missing.
        return $row ?? [
            'id' => 1,
            'default_commission_percent' => 10.00,
            'commission_on_renewals' => 1,
            'clearance_days' => 15,
            'min_payout_amount' => 1000.00,
            'cookie_window_days' => 30,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function update(array $data): void
    {
        $allowed = [
            'default_commission_percent',
            'commission_on_renewals',
            'clearance_days',
            'min_payout_amount',
            'cookie_window_days',
        ];
        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }

        QueryBuilder::table('partner_settings')->where('id', '=', 1)->update($payload);
    }

    public static function defaultPercent(): float
    {
        return (float) self::get()['default_commission_percent'];
    }

    public static function commissionOnRenewals(): bool
    {
        return (bool) (int) self::get()['commission_on_renewals'];
    }

    public static function clearanceDays(): int
    {
        return (int) self::get()['clearance_days'];
    }

    public static function minPayoutAmount(): float
    {
        return (float) self::get()['min_payout_amount'];
    }

    public static function cookieWindowDays(): int
    {
        return (int) self::get()['cookie_window_days'];
    }
}
