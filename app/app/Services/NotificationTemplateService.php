<?php

declare(strict_types=1);

namespace App\Services;

final class NotificationTemplateService
{
    /** @param array<string, mixed> $payload */
    public static function render(string $template, array $payload): string
    {
        return match ($template) {
            'appointment_reminder' => sprintf(
                "Hi %s, reminder: appointment at %s on %s.",
                $payload['patient_name'] ?? 'Patient',
                $payload['clinic_name'] ?? 'clinic',
                date('d M Y H:i', strtotime((string) ($payload['scheduled_at'] ?? 'now'))),
            ),
            'rx_delivery' => sprintf(
                "Hi %s, your prescription from %s is ready.%s",
                $payload['patient_name'] ?? 'Patient',
                $payload['clinic_name'] ?? 'clinic',
                !empty($payload['rx_url']) ? ' Download: ' . ($payload['rx_url'] ?? '') : '',
            ),
            'follow_up_reminder' => sprintf(
                "Hi %s, follow-up reminder from %s. Please book your visit.",
                $payload['patient_name'] ?? 'Patient',
                $payload['clinic_name'] ?? 'clinic',
            ),
            'lab_report_ready' => sprintf(
                "Hi %s, your lab report from %s is ready.",
                $payload['patient_name'] ?? 'Patient',
                $payload['clinic_name'] ?? 'clinic',
            ),
            'invoice_paid' => sprintf(
                "Hi %s, payment received for invoice %s (₹%s). Thank you — %s",
                $payload['patient_name'] ?? 'Patient',
                $payload['invoice_number'] ?? '',
                $payload['total'] ?? '0',
                $payload['clinic_name'] ?? 'clinic',
            ),
            default => json_encode($payload),
        };
    }
}
