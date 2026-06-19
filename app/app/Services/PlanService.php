<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Gates\ModuleGate;
use PDO;
use Throwable;

final class PlanService
{
    /** Request-scoped cache so we don't re-query/re-decode per call. */
    private static ?array $cache = null;

    /**
     * All plans, keyed by plan_id.
     *
     * Source of truth is the `plans` table (managed at /admin/plans). If the
     * table is missing or empty (fresh deploy before migration, or someone
     * deactivated everything), fall back to config/plans.php so onboarding and
     * checkout never break.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $fromDb = self::fromTable();

        return self::$cache = $fromDb !== [] ? $fromDb : self::fromConfig();
    }

    /** @return array<string, array<string, mixed>> */
    private static function fromConfig(): array
    {
        return require dirname(__DIR__, 2) . '/config/plans.php';
    }

    /**
     * Read the plans table and map rows back to the legacy array shape that
     * downstream code (OnboardingController, SubscriptionController, etc.)
     * expects — including the historical *_usd keys (which hold INR).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function fromTable(): array
    {
        try {
            $rows = Database::connection()
                ->query('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC, name ASC')
                ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return []; // table not migrated yet → caller falls back to config
        }

        $plans = [];
        foreach ($rows as $r) {
            $modules = self::decodeModules($r['modules'] ?? null);
            $plans[(string) $r['plan_id']] = [
                'name' => (string) $r['name'],
                'tagline' => (string) ($r['tagline'] ?? ''),
                // Legacy key names; values are INR.
                'monthly_usd' => (float) $r['monthly_inr'],
                'yearly_usd' => (float) $r['yearly_inr'],
                'seat_limit' => (int) $r['seat_limit'],
                'patient_limit' => $r['patient_limit'] !== null ? (int) $r['patient_limit'] : null,
                'featured' => (bool) $r['featured'],
                'trial_days' => (int) $r['trial_days'],
                'modules' => $modules,
                'highlights' => self::decodeJsonList($r['highlights'] ?? null),
                'limits' => self::decodeJsonList($r['limits'] ?? null),
            ];
        }

        return $plans;
    }

    /** modules is either the string "all_paid" or a JSON array of module ids. */
    private static function decodeModules(?string $raw): array|string
    {
        $decoded = $raw !== null && $raw !== '' ? json_decode($raw, true) : null;
        if ($decoded === 'all_paid') {
            return 'all_paid';
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<string> */
    private static function decodeJsonList(?string $raw): array
    {
        $decoded = $raw !== null && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** Test/admin seam — drop the request cache after an edit. */
    public static function flushCache(): void
    {
        self::$cache = null;
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
        // tenants.plan has a foreign key to plans.plan_id (fk_tenants_plan), so
        // it can only hold a plan_id that exists in the catalog. Guard against a
        // stale/retired id from an old call site by falling back to the paid
        // plan rather than letting the write throw a constraint error.
        if (self::get($planId) === null) {
            $planId = 'standard';
        }

        // tenants.seat_limit is TINYINT UNSIGNED (max 255). Config uses 999 to
        // mean "unlimited in practice" — cap it so the value fits the column and
        // doesn't overflow under MySQL strict mode (which aborts the write).
        $seatLimit = min(255, self::seatLimitFor($planId));
        $data = [
            'plan' => $planId,
            'seat_limit' => $seatLimit,
            'onboarding_step' => 2,
        ];

        // After Phase 1: 30-day trial for all new tenants (was 14).
        // The trial clock starts at registration (AuthService::registerClinic),
        // so only SET a trial here if the tenant doesn't already have one —
        // never overwrite/extend an in-progress trial during onboarding.
        if ($withTrial && $planId !== 'free') {
            $existing = QueryBuilder::table('tenants')
                ->where('id', '=', $clinicId)
                ->first();
            if (empty($existing['trial_ends_at'])) {
                $data['trial_ends_at'] = date('Y-m-d', strtotime('+1 month'));
            }
        }

        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update($data);
        self::activatePlanModules($clinicId, $planId);
    }
}
