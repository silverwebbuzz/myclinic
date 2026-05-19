<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class AppointmentSlipService
{
    /** @param array<string, mixed> $appointment @param array<string, mixed> $clinic */
    public static function generate(array $appointment, array $clinic): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/slips/' . (int) $clinic['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/appt-' . (int) $appointment['id'] . '.pdf';
        $rel = '/uploads/slips/' . (int) $clinic['id'] . '/appt-' . (int) $appointment['id'] . '.pdf';

        if (!class_exists(Mpdf::class)) {
            file_put_contents($path, 'Appointment slip');

            return $rel;
        }

        $mpdf = new Mpdf(['format' => 'A6']);
        $html = '<div style="font-family:sans-serif;padding:12px;">'
            . '<h2>' . htmlspecialchars((string) $clinic['name']) . '</h2>'
            . '<p><strong>Patient:</strong> ' . htmlspecialchars((string) ($appointment['patient_name'] ?? '')) . '</p>'
            . '<p><strong>UHID:</strong> ' . htmlspecialchars((string) ($appointment['uhid'] ?? '')) . '</p>'
            . '<p><strong>Doctor:</strong> ' . htmlspecialchars((string) ($appointment['doctor_name'] ?? '')) . '</p>'
            . '<p><strong>Date:</strong> ' . htmlspecialchars(date('d M Y H:i', strtotime($appointment['scheduled_at']))) . '</p>';
        if (!empty($appointment['token_number'])) {
            $html .= '<p style="font-size:24pt;font-weight:bold;">Token #' . (int) $appointment['token_number'] . '</p>';
        }
        $html .= '</div>';

        $mpdf->WriteHTML($html);
        $mpdf->Output($path, Destination::FILE);

        return $rel;
    }
}
