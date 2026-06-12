<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class AppointmentSlipService
{
    /**
     * Renders the appointment slip PDF and returns its public URL path.
     *
     * Never throws: the slip is a nice-to-have after booking — a PDF problem
     * (mPDF temp dir not writable on shared hosting, missing package) must not
     * turn a SUCCESSFUL booking into an error page. Returns null on failure.
     *
     * @param array<string, mixed> $appointment @param array<string, mixed> $clinic
     */
    public static function generate(array $appointment, array $clinic): ?string
    {
        try {
            $dir = dirname(__DIR__, 2) . '/public/uploads/slips/' . (int) $clinic['id'];
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return null;
            }

            // Random token so slip URLs aren't enumerable (appt ids are sequential).
            $file = 'appt-' . (int) $appointment['id'] . '-' . bin2hex(random_bytes(6)) . '.pdf';
            $path = $dir . '/' . $file;
            $rel = '/uploads/slips/' . (int) $clinic['id'] . '/' . $file;

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

            if (!class_exists(Mpdf::class)) {
                file_put_contents($path, strip_tags($html));

                return $rel;
            }

            // mPDF's default temp dir lives inside vendor/ and is not writable
            // on cPanel — the constructor throws without an explicit tempDir.
            $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
            $tmpDir = sys_get_temp_dir() . '/mpdf-' . $uid;
            if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
                $tmpDir = sys_get_temp_dir();
            }

            $mpdf = new Mpdf(['format' => 'A6', 'tempDir' => $tmpDir]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, Destination::FILE);

            return $rel;
        } catch (\Throwable $e) {
            error_log('[AppointmentSlipService] slip failed for appt '
                . (int) ($appointment['id'] ?? 0) . ': ' . $e->getMessage());

            return null;
        }
    }
}
