<?php

declare(strict_types=1);

namespace App\Services;

final class ChurnOutreachService
{
    /** @return int emails queued */
    public static function sendOutreach(): int
    {
        $clinics = ChurnRiskService::atRiskClinics();
        $sent = 0;

        foreach ($clinics as $clinic) {
            $email = $clinic['email'] ?? null;
            if ($email === null || $email === '') {
                continue;
            }

            MailService::send((string) $email, 'churn_outreach', [
                'clinic_name' => $clinic['name'] ?? 'your clinic',
                'reason' => $clinic['churn_risk_reason'] ?? 'We noticed lower activity',
                'support_url' => ($_ENV['APP_URL'] ?? 'https://app.manageclinic.com') . '/login',
            ], (int) $clinic['id']);

            $sent++;
        }

        return $sent;
    }
}
