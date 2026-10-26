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
        $contacts = self::clinicContacts($rows);

        return array_map(static function (array $row) use ($plans, $contacts): array {
            $plan = (string) ($row['plan'] ?? 'free');
            $mrr = self::mrrForTenant($row, $plans);
            $c = $contacts[(int) $row['id']] ?? [];

            return $row + [
                'mrr_inr' => $mrr,
                'plan_label' => $plans[$plan]['name'] ?? ucfirst($plan),
                'billing_status' => self::billingStatus($row),
                'churn_flag' => ($row['churn_risk_level'] ?? 'none') !== 'none',
                'city' => $c['city'] ?? '',
                'state' => $c['state'] ?? '',
                'contact_email' => trim((string) ($row['email'] ?? '')) ?: ($c['user_email'] ?? ''),
                'contact_phone' => trim((string) ($row['phone'] ?? '')) ?: ($c['user_phone'] ?? ''),
            ];
        }, $rows);
    }

    /**
     * City/state + fallback contact for a page of clinics, in two batched
     * queries (not one per clinic):
     *   - city/state from the clinic's directory listing (claimed_tenant_id or
     *     tenants.directory_doctor_id) — tenants has no city/state columns;
     *   - email/phone of the clinic's admin (else first doctor) user, used only
     *     when the tenant row's own email/phone is empty.
     *
     * @param list<array<string, mixed>> $tenants
     * @return array<int, array{city?:string, state?:string, user_email?:string, user_phone?:string}>
     */
    private static function clinicContacts(array $tenants): array
    {
        $ids = array_values(array_filter(array_map(static fn ($t) => (int) ($t['id'] ?? 0), $tenants)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        $out = [];
        $pdo = Database::connection();

        $dirIds = array_values(array_filter(array_map(static fn ($t) => (int) ($t['directory_doctor_id'] ?? 0), $tenants)));
        $dirToTenant = [];
        foreach ($tenants as $t) {
            if ((int) ($t['directory_doctor_id'] ?? 0) > 0) {
                $dirToTenant[(int) $t['directory_doctor_id']] = (int) $t['id'];
            }
        }
        try {
            $rows = $pdo->query(
                'SELECT id, claimed_tenant_id, city, state FROM directory_doctors
                  WHERE claimed_tenant_id IN (' . $in . ')'
                . ($dirIds ? ' OR id IN (' . implode(',', $dirIds) . ')' : '')
            )->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $tid = (int) ($r['claimed_tenant_id'] ?? 0) ?: ($dirToTenant[(int) $r['id']] ?? 0);
                if ($tid > 0 && empty($out[$tid]['city'])) {
                    $out[$tid]['city'] = (string) ($r['city'] ?? '');
                    $out[$tid]['state'] = (string) ($r['state'] ?? '');
                }
            }
        } catch (\Throwable) {
            // No directory table → no city/state column data.
        }

        $rows = $pdo->query(
            "SELECT clinic_id, email, phone FROM users
              WHERE clinic_id IN ({$in}) AND role IN ('admin', 'doctor')
              ORDER BY role = 'admin' DESC, id ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $cid = (int) $r['clinic_id'];
            if (!isset($out[$cid]['user_email']) && trim((string) $r['email']) !== '') {
                $out[$cid]['user_email'] = trim((string) $r['email']);
            }
            if (!isset($out[$cid]['user_phone']) && trim((string) $r['phone']) !== '') {
                $out[$cid]['user_phone'] = trim((string) $r['phone']);
            }
        }

        return $out;
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
