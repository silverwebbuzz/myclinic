<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class SuperAdminMetricsService
{
    /** @return array{mrr: float, arr: float, clinics: int, at_risk: int, by_plan: array<string, int>, mrr_trend: list<array{month: string, mrr: float}>} */
    public static function dashboard(): array
    {
        $plans = require dirname(__DIR__, 2) . '/config/plans.php';
        $rows = Database::ping()
            ? QueryBuilder::table('tenants')->where('is_active', '=', 1)->get()
            : [];

        $byPlan = ['free' => 0, 'clinic' => 0, 'practice' => 0, 'enterprise' => 0];
        $mrr = 0.0;
        $atRisk = 0;

        foreach ($rows as $row) {
            $plan = (string) ($row['plan'] ?? 'free');
            if (!isset($byPlan[$plan])) {
                $byPlan[$plan] = 0;
            }
            $byPlan[$plan]++;
            $mrr += (float) ($plans[$plan]['monthly_usd'] ?? 0);
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

        $plans = require dirname(__DIR__, 2) . '/config/plans.php';
        $rows = QueryBuilder::table('tenants')->orderBy('created_at', 'DESC')->limit(200)->get();

        return array_map(static function (array $row) use ($plans): array {
            $plan = (string) ($row['plan'] ?? 'free');
            $mrr = (float) ($plans[$plan]['monthly_usd'] ?? 0);

            return $row + [
                'mrr_usd' => $mrr,
                'plan_label' => $plans[$plan]['name'] ?? ucfirst($plan),
                'churn_flag' => ($row['churn_risk_level'] ?? 'none') !== 'none',
            ];
        }, $rows);
    }
}
