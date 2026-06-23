<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\RequestContext;

/**
 * Single source of truth for "is this clinic's trial / plan still valid?".
 *
 * Read from the tenant's trial_ends_at / plan_expires_at dates. Used by both
 * SubscriptionMiddleware (the hard gate) and the UI (expired screen + the
 * "expiring soon" banner) so the rule lives in exactly one place.
 *
 * SAFETY RULE: a clinic with NO dates set at all (trial_ends_at IS NULL AND
 * plan_expires_at IS NULL) is treated as ACTIVE — never blocked. Every
 * existing clinic in the live DB is in that state, so the gate must not lock
 * them out. We only ever block when a date EXISTS and is in the past.
 */
final class SubscriptionStatus
{
    /** Warn this many days before expiry (the "expiring soon" banner). */
    public const SOON_DAYS = 5;

    /**
     * @param array<string,mixed>|null $clinic  defaults to the current tenant
     * @return array{
     *   state: string,        // 'active' | 'trial' | 'expiring_soon' | 'expired'
     *   expired: bool,
     *   reason: string,       // 'trial' | 'plan' | 'none'
     *   days_left: int|null,  // null when no relevant date
     *   ends_on: string|null  // Y-m-d of the governing date, if any
     * }
     */
    public static function forClinic(?array $clinic = null): array
    {
        $clinic ??= RequestContext::clinic();
        $none = ['state' => 'active', 'expired' => false, 'reason' => 'none', 'days_left' => null, 'ends_on' => null];
        if (!$clinic) {
            return $none;
        }

        $trialEnds = self::asDate($clinic['trial_ends_at'] ?? null);
        $planEnds  = self::asDate($clinic['plan_expires_at'] ?? null);

        // No dates at all → active forever (existing clinics). Never block.
        if ($trialEnds === null && $planEnds === null) {
            return $none;
        }

        // Pick the governing date + reason. A paid plan_expires_at takes
        // precedence once set; otherwise the trial date governs.
        if ($planEnds !== null) {
            $ends = $planEnds;
            $reason = 'plan';
        } else {
            $ends = $trialEnds;
            $reason = 'trial';
        }

        $today = new \DateTimeImmutable('today');
        $end   = (new \DateTimeImmutable($ends))->setTime(0, 0);
        $daysLeft = (int) $today->diff($end)->format('%r%a'); // negative once past

        if ($daysLeft < 0) {
            return ['state' => 'expired', 'expired' => true, 'reason' => $reason,
                    'days_left' => $daysLeft, 'ends_on' => $ends];
        }
        if ($daysLeft <= self::SOON_DAYS) {
            return ['state' => 'expiring_soon', 'expired' => false, 'reason' => $reason,
                    'days_left' => $daysLeft, 'ends_on' => $ends];
        }
        return ['state' => ($reason === 'trial' ? 'trial' : 'active'), 'expired' => false,
                'reason' => $reason, 'days_left' => $daysLeft, 'ends_on' => $ends];
    }

    public static function isExpired(?array $clinic = null): bool
    {
        return self::forClinic($clinic)['expired'];
    }

    /** Normalise a date-ish value to 'Y-m-d', or null if empty/invalid. */
    private static function asDate(mixed $raw): ?string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '' || str_starts_with($s, '0000')) {
            return null;
        }
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
