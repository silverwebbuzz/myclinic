<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class PrescriptionPdfService
{
    /**
     * @param array<string, mixed> $visit
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $clinic
     * @param list<array<string, mixed>> $lines
     */
    public static function generate(array $visit, array $patient, array $clinic, array $lines): string
    {
        $clinicId = (int) ($clinic['id'] ?? 0);
        $visitId = (int) ($visit['id'] ?? 0);

        $dir = dirname(__DIR__, 2) . '/public/uploads/prescriptions/' . $clinicId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create prescription PDF directory.');
        }

        $rel = '/uploads/prescriptions/' . $clinicId . '/rx-' . $visitId . '.pdf';
        $path = dirname(__DIR__, 2) . '/public' . $rel;

        $clinicName = htmlspecialchars((string) ($clinic['name'] ?? 'Clinic'), ENT_QUOTES, 'UTF-8');
        $clinicPhone = htmlspecialchars((string) ($clinic['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $clinicAddr = htmlspecialchars((string) ($clinic['address'] ?? ''), ENT_QUOTES, 'UTF-8');

        $patientName = htmlspecialchars((string) ($patient['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $uhid = htmlspecialchars((string) ($patient['uhid'] ?? ''), ENT_QUOTES, 'UTF-8');
        $age = !empty($patient['dob']) ? self::ageFromDob((string) $patient['dob']) : '';
        $gender = htmlspecialchars((string) ($patient['gender'] ?? ''), ENT_QUOTES, 'UTF-8');

        $doctorName = htmlspecialchars((string) ($visit['doctor_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $visitedAt = !empty($visit['visited_at']) ? date('d M Y', strtotime((string) $visit['visited_at'])) : '';

        $rows = '';
        foreach ($lines as $i => $line) {
            $name = htmlspecialchars((string) ($line['drug_name'] ?? $line['remedy_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $potency = htmlspecialchars((string) ($line['potency'] ?? ''), ENT_QUOTES, 'UTF-8');
            $dosage = htmlspecialchars((string) ($line['dosage'] ?? ''), ENT_QUOTES, 'UTF-8');
            $freq = htmlspecialchars((string) ($line['frequency'] ?? ''), ENT_QUOTES, 'UTF-8');
            $dur = !empty($line['duration_days']) ? ((int) $line['duration_days']) . ' days' : '';
            $instructions = htmlspecialchars((string) ($line['instructions'] ?? ''), ENT_QUOTES, 'UTF-8');

            $rows .= '<tr>'
                . '<td style="padding:6px 4px;border-bottom:1px solid #e2e8f0;">' . ($i + 1) . '</td>'
                . '<td style="padding:6px 4px;border-bottom:1px solid #e2e8f0;"><strong>' . $name . '</strong>'
                . ($potency !== '' ? ' <span style="color:#64748b;">' . $potency . '</span>' : '')
                . ($instructions !== '' ? '<br><em style="color:#64748b;font-size:9pt;">' . $instructions . '</em>' : '')
                . '</td>'
                . '<td style="padding:6px 4px;border-bottom:1px solid #e2e8f0;">' . $dosage . '</td>'
                . '<td style="padding:6px 4px;border-bottom:1px solid #e2e8f0;">' . $freq . '</td>'
                . '<td style="padding:6px 4px;border-bottom:1px solid #e2e8f0;">' . $dur . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="padding:12px;text-align:center;color:#94a3b8;">No prescription items</td></tr>';
        }

        $html = '<div style="font-family:sans-serif;padding:0;font-size:11pt;color:#0f172a;">'
            . '<div style="border-bottom:2px solid #0F9B6E;padding-bottom:8px;margin-bottom:12px;">'
            . '<h2 style="margin:0;font-size:16pt;color:#0F9B6E;">' . $clinicName . '</h2>'
            . ($clinicAddr !== '' ? '<div style="font-size:9pt;color:#475569;">' . $clinicAddr . '</div>' : '')
            . ($clinicPhone !== '' ? '<div style="font-size:9pt;color:#475569;">Phone: ' . $clinicPhone . '</div>' : '')
            . '</div>'

            . '<table width="100%" style="margin-bottom:12px;font-size:10pt;">'
            . '<tr>'
            . '<td><strong>Patient:</strong> ' . $patientName . ' <span style="color:#64748b;">· ' . $uhid . '</span></td>'
            . '<td align="right"><strong>Date:</strong> ' . htmlspecialchars($visitedAt) . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td>' . ($age !== '' ? '<strong>Age:</strong> ' . $age . ' yrs · ' : '') . ($gender !== '' ? '<strong>Sex:</strong> ' . $gender : '') . '</td>'
            . '<td align="right"><strong>Doctor:</strong> ' . $doctorName . '</td>'
            . '</tr>'
            . '</table>'

            . '<h3 style="margin:12px 0 6px;font-size:11pt;border-bottom:1px solid #cbd5e1;padding-bottom:4px;">Rx</h3>'
            . '<table width="100%" cellspacing="0" cellpadding="0" style="font-size:10pt;border-collapse:collapse;">'
            . '<thead><tr style="background:#f1f5f9;">'
            . '<th align="left" style="padding:6px 4px;width:6%;">#</th>'
            . '<th align="left" style="padding:6px 4px;width:40%;">Medication</th>'
            . '<th align="left" style="padding:6px 4px;width:18%;">Dosage</th>'
            . '<th align="left" style="padding:6px 4px;width:14%;">Frequency</th>'
            . '<th align="left" style="padding:6px 4px;width:22%;">Duration</th>'
            . '</tr></thead><tbody>'
            . $rows
            . '</tbody></table>'

            . '<div style="margin-top:40px;text-align:right;">'
            . '<div style="display:inline-block;text-align:center;min-width:200px;">'
            . '<div style="border-top:1px solid #475569;padding-top:4px;font-size:10pt;">' . $doctorName . '</div>'
            . '<div style="font-size:9pt;color:#64748b;">Signature</div>'
            . '</div></div>'

            . '<p style="margin-top:24px;font-size:8pt;color:#94a3b8;text-align:center;">'
            . 'Generated by ' . $clinicName . ' on ' . htmlspecialchars(date('d M Y, h:i A'))
            . '</p>'
            . '</div>';

        if (!class_exists(Mpdf::class)) {
            file_put_contents($path, strip_tags($html));
            return $rel;
        }

        $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
        $tmpDir = sys_get_temp_dir() . '/mpdf-' . $uid;
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            $tmpDir = sys_get_temp_dir();
        }

        try {
            $mpdf = new Mpdf(['format' => 'A5', 'tempDir' => $tmpDir]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, Destination::FILE);
        } catch (\Throwable $e) {
            error_log('[PrescriptionPdfService] mpdf failed: ' . $e->getMessage());
            file_put_contents($path, strip_tags($html));
        }

        return $rel;
    }

    private static function ageFromDob(string $dob): string
    {
        $ts = strtotime($dob);
        if ($ts === false) return '';
        $years = (int) floor((time() - $ts) / 31557600);
        return $years > 0 ? (string) $years : '';
    }
}
