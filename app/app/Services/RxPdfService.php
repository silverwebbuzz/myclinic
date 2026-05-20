<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class RxPdfService
{
    /**
     * @param array<string, mixed> $visit
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $clinic
     * @param list<array<string, mixed>> $prescriptions
     */
    public static function generate(array $visit, array $patient, array $clinic, array $prescriptions): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/rx/' . (int) $clinic['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/visit-' . (int) $visit['id'] . '.pdf';
        $rel = '/uploads/rx/' . (int) $clinic['id'] . '/visit-' . (int) $visit['id'] . '.pdf';

        $html = '<div style="font-family:sans-serif;padding:16px;">'
            . '<h2>' . htmlspecialchars((string) $clinic['name']) . '</h2>'
            . '<p><strong>Patient:</strong> ' . htmlspecialchars((string) $patient['name'])
            . ' · UHID: ' . htmlspecialchars((string) $patient['uhid']) . '</p>'
            . '<p><strong>Date:</strong> ' . htmlspecialchars(date('d M Y', strtotime($visit['visited_at'] ?? 'now'))) . '</p>'
            . '<p><strong>Diagnosis:</strong> ' . htmlspecialchars((string) ($visit['diagnosis'] ?? '—')) . '</p>'
            . '<hr><h3>Prescription</h3><table width="100%" cellpadding="6" style="border-collapse:collapse;font-size:11pt;">'
            . '<tr style="background:#f1f5f9;"><th align="left">Medicine</th><th>Dose</th><th>Freq</th><th>Days</th></tr>';

        foreach ($prescriptions as $rx) {
            $name = $rx['drug']['name'] ?? $rx['remedy']['name'] ?? $rx['dosage'] ?? '—';
            if (!empty($rx['potency'])) {
                $name .= ' ' . $rx['potency'];
            }
            $html .= '<tr><td>' . htmlspecialchars((string) $name) . '</td>'
                . '<td>' . htmlspecialchars((string) ($rx['dosage'] ?? '—')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($rx['frequency'] ?? '—')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($rx['duration_days'] ?? '—')) . '</td></tr>';
            if (!empty($rx['instructions'])) {
                $html .= '<tr><td colspan="4" style="font-size:9pt;color:#64748b;">' . htmlspecialchars($rx['instructions']) . '</td></tr>';
            }
        }

        $html .= '</table></div>';

        if (class_exists(Mpdf::class)) {
            $mpdf = new Mpdf(['format' => 'A5']);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, Destination::FILE);
        } else {
            file_put_contents($path, strip_tags($html));
        }

        return $rel;
    }
}
