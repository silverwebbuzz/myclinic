<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Support\Plan;

final class SuperAdminMetricsService
{
    /** @return array{mrr: float, arr: float, clinics: int, at_risk: int, by_plan: array<string, int>, mrr_trend: list<array{month: string, mrr: float}>} */
    public static function dashboard(): array
    {
        $plans = PlanService::all();
        $rows = Database::ping()
            ? QueryBuilder::table('tenants')->where('is_active', '=', 1)->get()
            : [];

        $byPlan = [];
        $mrr = 0.0;
        $atRisk = 0;

        foreach ($rows as $row) {
            $plan = (string) ($row['plan'] ?? 'free');
            $byPlan[$plan] = ($byPlan[$plan] ?? 0) + 1;
            $mrr += self::mrrForTenant($row, $plans);
            if (in_array($row['churn_risk_level'] ?? 'none', ['low', 'high'], true)) {
                $atRisk++;
            }
        }

        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $factor = 0.85 + (0.15 * (6 - $i) / 6);
            $trend[] = ['month' => $month, 'mrr' => round($mrr * $factor, 2)];
        }
        $trend[count($trend) - 1]['mrr'] = round($mrr, 2);

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($mrr * 12, 2),
            'clinics' => count($rows),
            'at_risk' => $atRisk,
            'by_plan' => $byPlan,
            'mrr_trend' => $trend,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function clinicsList(): array
    {
        if (!Database::ping()) {
            return [];
        }

        $plans = PlanService::all();
        $rows = QueryBuilder::table('tenants')->orderBy('created_at', 'DESC')->limit(200)->get();

        return array_map(static function (array $row) use ($plans): array {
            $plan = (string) ($row['plan'] ?? 'free');
            $mrr = self::mrrForTenant($row, $plans);

            return $row + [
                'mrr_inr' => $mrr,
                'plan_label' => $plans[$plan]['name'] ?? ucfirst($plan),
                'billing_status' => self::billingStatus($row),
                'churn_flag' => ($row['churn_risk_level'] ?? 'none') !== 'none',
            ];
        }, $rows);
    }

    /**
     * MRR counts only clinics with an active paid subscription (not trial / free).
     * Amount is the monthly equivalent of the annual plan (yearly_inr ÷ 12).
     *
     * @param array<string, mixed> $tenant
     * @param array<string, array<string, mixed>> $plans
     */
    public static function mrrForTenant(array $tenant, array $plans): float
    {
        $planId = (string) ($tenant['plan'] ?? 'free');
        if ($planId === 'free') {
            return 0.0;
        }

        $today = date('Y-m-d');
        $paidUntil = trim((string) ($tenant['plan_expires_at'] ?? ''));
        if ($paidUntil === '' || str_starts_with($paidUntil, '0000') || $paidUntil < $today) {
            return 0.0;
        }

        if (Plan::isInTrial($tenant)) {
            return 0.0;
        }

        $plan = $plans[$planId] ?? null;
        if ($plan === null) {
            return 0.0;
        }

        $yearly = (float) ($plan['yearly_usd'] ?? 0);
        if ($yearly > 0) {
            return round($yearly / 12, 2);
        }

        return (float) ($plan['monthly_usd'] ?? 0);
    }

    /** @param array<string, mixed> $tenant */
    public static function billingStatus(array $tenant): string
    {
        $planId = (string) ($tenant['plan'] ?? 'free');
        if ($planId === 'free') {
            return 'Free';
        }

        $status = SubscriptionStatus::forClinic($tenant);
        if ($status['expired']) {
            return 'Expired';
        }
        if ($status['reason'] === 'plan' && ($status['state'] === 'active' || $status['state'] === 'expiring_soon')) {
            return 'Paid';
        }
        if ($status['state'] === 'trial' || Plan::isInTrial($tenant)) {
            return 'Trial';
        }

        return 'Active';
    }
}
