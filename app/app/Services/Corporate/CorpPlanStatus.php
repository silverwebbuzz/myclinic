<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Support\Locale\LocalDate;

/**
 * Is this company's trial / paid plan still valid? Same rules as the clinic
 * SubscriptionStatus, but "today" is the COMPANY's calendar day (a UAE company
 * shouldn't expire at Indian midnight).
 *
 *  - No dates at all (pending, or set up by hand)  → active, never blocked.
 *  - plan_expires_at set                           → it governs (paid).
 *  - otherwise trial_ends_at governs.
 *  - The end date itself is still usable; blocked from the day after.
 */
final class CorpPlanStatus
{
    public const SOON_DAYS = 5;

    /**
     * @param array<string, mixed> $company corp_companies row
     * @param string|null $today Y-m-d override (tests)
     * @return array{state: string, expired: bool, reason: string, days_left: int|null, ends_on: string|null}
     *   state: active | trial | expiring_soon | expired
     */
    public static function for(array $company, ?string $today = null): array
    {
        $plan = self::date($company['plan_expires_at'] ?? null);
        $trial = self::date($company['trial_ends_at'] ?? null);
        if ($plan === null && $trial === null) {
            return ['state' => 'active', 'expired' => false, 'reason' => 'none', 'days_left' => null, 'ends_on' => null];
        }
        [$ends, $reason] = $plan !== null ? [$plan, 'plan'] : [$trial, 'trial'];
        $today ??= LocalDate::today((string) ($company['timezone'] ?? 'UTC'));
        $days = (int) (new \DateTimeImmutable($today))->diff(new \DateTimeImmutable($ends))->format('%r%a');

        if ($days < 0) {
            return ['state' => 'expired', 'expired' => true, 'reason' => $reason, 'days_left' => $days, 'ends_on' => $ends];
        }
        $state = $days <= self::SOON_DAYS ? 'expiring_soon' : ($reason === 'trial' ? 'trial' : 'active');

        return ['state' => $state, 'expired' => false, 'reason' => $reason, 'days_left' => $days, 'ends_on' => $ends];
    }

    private static function date(mixed $v): ?string
    {
        if (!is_string($v) || $v === '' || str_starts_with($v, '0000-00-00')) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Y-m-d', substr($v, 0, 10)) !== false ? substr($v, 0, 10) : null;
    }
}
