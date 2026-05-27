<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Gates\ModuleGate;

final class PlanService
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return require dirname(__DIR__, 2) . '/config/plans.php';
    }

    public static function get(string $planId): ?array
    {
        $plans = self::all();

        return $plans[$planId] ?? null;
    }

    public static function seatLimitFor(string $planId): int
    {
        return (int) (self::get($planId)['seat_limit'] ?? 2);
    }

    public static function usesRazorpay(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), ['IN', 'SG', 'MY', 'BD', 'LK'], true);
    }

    public static function activatePlanModules(int $clinicId, string $planId): void
    {
        $plan = self::get($planId);
        if ($plan === null) {
            return;
        }

        $moduleIds = self::moduleIdsForPlan($planId);

        foreach ($moduleIds as $moduleId) {
            $exists = QueryBuilder::table('clinic_modules')
                ->forClinic($clinicId)
                ->where('module_id', '=', $moduleId)
                ->first();

            if ($exists !== null) {
                QueryBuilder::table('clinic_modules')
                    ->forClinic($clinicId)
                    ->where('module_id', '=', $moduleId)
                    ->update(['is_active' => 1, 'billing_cycle' => $planId === 'free' ? 'free' : 'monthly']);
            } else {
                QueryBuilder::table('clinic_modules')->insert([
                    'clinic_id' => $clinicId,
                    'module_id' => $moduleId,
                    'billing_cycle' => $planId === 'free' ? 'free' : 'monthly',
                    'is_active' => 1,
                    'is_trial' => in_array($planId, ['clinic', 'practice', 'enterprise'], true) ? 1 : 0,
                ]);
            }
        }

        ModuleGate::invalidateCache($clinicId);
        RedisClient::del("tenant:slug:" . (QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first()['slug'] ?? ''));
    }

    /** @return list<string> */
    public static function moduleIdsForPlan(string $planId): array
    {
        $plan = self::get($planId);
        if ($plan === null) {
            return ['patients', 'appointments_basic', 'invoicing_basic'];
        }

        if (($plan['modules'] ?? '') === 'all_paid') {
            $rows = QueryBuilder::table('module_catalog')->get();

            return array_values(array_map(
                static fn (array $r) => (string) $r['id'],
                array_filter($rows, static fn (array $r) => ($r['category'] ?? '') !== 'platform'),
            ));
        }

        return is_array($plan['modules']) ? $plan['modules'] : [];
    }

    public static function applyPlanToTenant(int $clinicId, string $planId, bool $withTrial = false): void
    {
        $seatLimit = self::seatLimitFor($planId);
        $data = [
            'plan' => $planId,
            'seat_limit' => $seatLimit,
            'onboarding_step' => 2,
        ];

        // After Phase 1: 30-day trial for all new tenants (was 14).
        // 'free' guard kept harmless — that tier no longer exists.
        if ($withTrial && $planId !== 'free') {
            $data['trial_ends_at'] = date('Y-m-d', strtotime('+30 days'));
        }

        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update($data);
        self::activatePlanModules($clinicId, $planId);
    }
}
