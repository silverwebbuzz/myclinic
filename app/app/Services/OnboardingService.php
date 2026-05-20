<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class OnboardingService
{
    public static function currentStep(): int
    {
        $clinic = RequestContext::clinic();

        return (int) ($clinic['onboarding_step'] ?? 1);
    }

    public static function advanceTo(int $clinicId, int $step): void
    {
        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
            'onboarding_step' => $step,
        ]);
        self::refreshClinicContext($clinicId);
    }

    public static function complete(int $clinicId): void
    {
        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
            'onboarding_step' => 5,
            'onboarding_completed_at' => date('Y-m-d H:i:s'),
        ]);
        self::refreshClinicContext($clinicId);
    }

    public static function refreshClinicContext(int $clinicId): void
    {
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($clinic !== null) {
            RequestContext::setClinic($clinic);
            RedisClient::del('tenant:slug:' . $clinic['slug']);
        }
    }

    public static function specialtyConfig(int $clinicId): ?array
    {
        return QueryBuilder::table('specialty_configs')
            ->where('clinic_id', '=', $clinicId)
            ->first();
    }

    /** @return array<string, mixed> */
    public static function defaultWorkingHours(): array
    {
        return [
            'mon' => ['enabled' => true, 'sessions' => [['start' => '09:00', 'end' => '13:00'], ['start' => '16:00', 'end' => '20:00']]],
            'tue' => ['enabled' => true, 'sessions' => [['start' => '09:00', 'end' => '13:00'], ['start' => '16:00', 'end' => '20:00']]],
            'wed' => ['enabled' => true, 'sessions' => [['start' => '09:00', 'end' => '13:00'], ['start' => '16:00', 'end' => '20:00']]],
            'thu' => ['enabled' => true, 'sessions' => [['start' => '09:00', 'end' => '13:00'], ['start' => '16:00', 'end' => '20:00']]],
            'fri' => ['enabled' => true, 'sessions' => [['start' => '09:00', 'end' => '13:00'], ['start' => '16:00', 'end' => '20:00']]],
            'sat' => ['enabled' => true, 'sessions' => [['start' => '09:00', 'end' => '13:00']]],
            'sun' => ['enabled' => false, 'sessions' => []],
        ];
    }

    public static function currencyForCountry(string $countryCode): string
    {
        return match (strtoupper($countryCode)) {
            'IN' => 'INR',
            'US' => 'USD',
            'GB' => 'GBP',
            'AE' => 'AED',
            default => 'USD',
        };
    }

    public static function taxLabelForCountry(string $countryCode): string
    {
        return match (strtoupper($countryCode)) {
            'IN' => 'GST',
            'GB' => 'VAT',
            'CA' => 'HST',
            default => 'Tax',
        };
    }
}
