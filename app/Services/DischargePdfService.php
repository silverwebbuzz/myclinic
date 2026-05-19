<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class DischargePdfService
{
    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $clinic
     */
    public static function generate(array $summary, array $patient, array $clinic, ?string $doctorSigPath): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/discharge/' . (int) $clinic['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $visitId = (int) ($summary['visit_id'] ?? 0);
        $rel = '/uploads/discharge/' . (int) $clinic['id'] . '/summary-' . $visitId . '.pdf';

        $html = '<h2>Discharge Summary</h2><p><strong>' . htmlspecialchars((string) $clinic['name']) . '</strong></p>'
            . '<p>Patient: ' . htmlspecialchars((string) $patient['name']) . ' · ' . htmlspecialchars((string) $patient['uhid']) . '</p>'
            . '<h3>Diagnosis</h3><p>' . nl2br(htmlspecialchars((string) ($summary['final_diagnosis'] ?? ''))) . '</p>'
            . '<h3>Treatment</h3><p>' . nl2br(htmlspecialchars((string) ($summary['treatment_summary'] ?? ''))) . '</p>'
            . '<h3>Follow-up</h3><p>' . nl2br(htmlspecialchars((string) ($summary['follow_up_instructions'] ?? ''))) . '</p>'
            . '<h3>Diet</h3><p>' . nl2br(htmlspecialchars((string) ($summary['diet_at_discharge'] ?? ''))) . '</p>';

        if ($doctorSigPath !== null) {
            $html .= '<p><img src="' . htmlspecialchars($doctorSigPath) . '" style="max-height:60px;"></p>';
        }

        if (class_exists(Mpdf::class)) {
            $mpdf = new Mpdf();
            $mpdf->WriteHTML($html);
            $mpdf->Output(dirname(__DIR__, 2) . '/public' . $rel, Destination::FILE);
        } else {
            file_put_contents(dirname(__DIR__, 2) . '/public' . $rel, strip_tags($html));
        }

        return $rel;
    }
}
