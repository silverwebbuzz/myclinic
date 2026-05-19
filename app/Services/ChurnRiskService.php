<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class ChurnRiskService
{
    /** @return int clinics flagged */
    public static function run(): int
    {
        if (!Database::ping()) {
            return 0;
        }

        $count = 0;
        $tenants = QueryBuilder::table('tenants')->where('is_active', '=', 1)->get();
        $now = time();

        foreach ($tenants as $tenant) {
            $clinicId = (int) $tenant['id'];
            $level = 'none';
            $reason = null;

            $lastLogin = $tenant['last_staff_login_at'] ?? null;
            if ($lastLogin !== null && strtotime((string) $lastLogin) < $now - (14 * 86400)) {
                $level = 'high';
                $reason = 'No staff login in 14+ days';
            } elseif ($lastLogin === null) {
                $created = strtotime((string) ($tenant['created_at'] ?? 'now'));
                if ($created < $now - (14 * 86400)) {
                    $level = 'low';
                    $reason = 'Never logged in';
                }
            }

            $trialEnds = $tenant['trial_ends_at'] ?? null;
            if ($trialEnds !== null) {
                $days = (int) floor((strtotime((string) $trialEnds) - $now) / 86400);
                if ($days >= 0 && $days <= 3) {
                    $level = $level === 'high' ? 'high' : 'low';
                    $reason = ($reason ? $reason . '; ' : '') . "Trial ends in {$days} day(s)";
                }
            }

            if (($tenant['plan'] ?? 'free') !== 'free' && empty($tenant['stripe_customer_id']) && empty($tenant['razorpay_customer_id'])) {
                if ($level === 'none') {
                    $level = 'low';
                    $reason = 'Paid plan without billing customer id';
                }
            }

            if (($tenant['churn_risk_level'] ?? 'none') !== $level || ($tenant['churn_risk_reason'] ?? '') !== $reason) {
                QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
                    'churn_risk_level' => $level,
                    'churn_risk_reason' => $reason,
                ]);
                if ($level !== 'none') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** @return list<array<string, mixed>> */
    public static function atRiskClinics(): array
    {
        return QueryBuilder::table('tenants')
            ->where('is_active', '=', 1)
            ->where('churn_risk_level', '!=', 'none')
            ->orderBy('churn_risk_level', 'DESC')
            ->get();
    }
}
