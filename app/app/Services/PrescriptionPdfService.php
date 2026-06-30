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
    /**
     * Renders the prescription PDF and returns its ABSOLUTE path.
     *
     * Files live in app/storage (NOT the public webroot): prescriptions are
     * medical records, and /uploads/... URLs were guessable (sequential visit
     * ids) and served without auth. The controller streams the file instead.
     *
     * @param 'A5'|'A4' $format prescription pad size
     */
    public static function generate(array $visit, array $patient, array $clinic, array $lines, string $format = 'A5'): string
    {
        $clinicId = (int) ($clinic['id'] ?? 0);
        $visitId = (int) ($visit['id'] ?? 0);
        $format = strtoupper($format) === 'A4' ? 'A4' : 'A5';

        $dir = dirname(__DIR__, 2) . '/storage/prescriptions/' . $clinicId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create prescription PDF directory.');
        }

        $path = $dir . '/rx-' . $visitId . '-' . strtolower($format) . '.pdf';

        $clinicName = htmlspecialchars((string) ($clinic['name'] ?? 'Clinic'), ENT_QUOTES, 'UTF-8');
        $clinicPhone = htmlspecialchars((string) ($clinic['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $clinicAddr = htmlspecialchars((string) ($clinic['address'] ?? ''), ENT_QUOTES, 'UTF-8');
        $regNo = htmlspecialchars((string) ($clinic['registration_number'] ?? ''), ENT_QUOTES, 'UTF-8');

        // Clinic-configured pad header/footer (Settings → General).
        $rxHeader = '';
        $rxFooter = '';
        try {
            $config = OnboardingService::specialtyConfig($clinicId) ?? [];
            $rxHeader = htmlspecialchars((string) ($config['rx_header_text'] ?? ''), ENT_QUOTES, 'UTF-8');
            $rxFooter = htmlspecialchars((string) ($config['rx_footer_text'] ?? ''), ENT_QUOTES, 'UTF-8');
        } catch (\Throwable $e) {
            // Pre-migration-024 schema — pad prints without custom notes.
        }

        $patientName = htmlspecialchars((string) ($patient['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $uhid = htmlspecialchars((string) ($patient['uhid'] ?? ''), ENT_QUOTES, 'UTF-8');
        $age = !empty($patient['dob']) ? self::ageFromDob((string) $patient['dob']) : '';
        $gender = htmlspecialchars((string) ($patient['gender'] ?? ''), ENT_QUOTES, 'UTF-8');

        $doctorName = htmlspecialchars((string) ($visit['doctor_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $visitedAt = !empty($visit['visited_at']) ? date('d M Y', strtotime((string) $visit['visited_at'])) : '';

        $tableRows = '';
        foreach ($lines as $line) {
            $tableRows .= self::renderMedicineRow($line);
        }

        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="7" style="padding:12px;text-align:center;color:#94a3b8;">No prescription items</td></tr>';
        }

        $thStyle = 'padding:6px 5px;border:1px solid #cbd5e1;background:#f1f5f9;font-size:9pt;font-weight:bold;text-align:left;';
        $rxTable = '<table width="100%" cellspacing="0" cellpadding="0" style="font-size:9pt;border-collapse:collapse;margin-bottom:4px;">'
            . '<thead><tr>'
            . '<th style="' . $thStyle . 'width:24%;">Name</th>'
            . '<th style="' . $thStyle . 'width:11%;">Dose</th>'
            . '<th style="' . $thStyle . 'width:11%;">Mix with</th>'
            . '<th style="' . $thStyle . 'width:16%;">Frequency</th>'
            . '<th style="' . $thStyle . 'width:12%;">Timing</th>'
            . '<th style="' . $thStyle . 'width:10%;">Duration</th>'
            . '<th style="' . $thStyle . 'width:12%;">Total qty</th>'
            . '</tr></thead><tbody>'
            . $tableRows
            . '</tbody></table>';

        $html = '<div style="font-family:sans-serif;padding:0;font-size:11pt;color:#0f172a;">'
            . '<div style="border-bottom:2px solid #0F766E;padding-bottom:8px;margin-bottom:12px;">'
            . '<h2 style="margin:0;font-size:16pt;color:#0F766E;">' . $clinicName . '</h2>'
            . ($rxHeader !== '' ? '<div style="font-size:9.5pt;color:#334155;">' . $rxHeader . '</div>' : '')
            . ($clinicAddr !== '' ? '<div style="font-size:9pt;color:#475569;">' . $clinicAddr . '</div>' : '')
            . ($clinicPhone !== '' ? '<div style="font-size:9pt;color:#475569;">Phone: ' . $clinicPhone . '</div>' : '')
            . ($regNo !== '' ? '<div style="font-size:9pt;color:#475569;">Reg. No: ' . $regNo . '</div>' : '')
            . '</div>'

            . '<table width="100%" style="margin-bottom:14px;font-size:10pt;border-collapse:collapse;">'
            . '<tr>'
            . '<td style="padding:2px 0;"><strong>Patient:</strong> ' . $patientName
            . ' <span style="color:#64748b;">· ' . $uhid . '</span></td>'
            . '<td align="right" style="padding:2px 0;"><strong>Date:</strong> ' . self::esc($visitedAt) . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="padding:2px 0;">'
            . ($age !== '' ? '<strong>Age:</strong> ' . $age . ' yrs' : '')
            . ($age !== '' && $gender !== '' ? ' · ' : '')
            . ($gender !== '' ? '<strong>Sex:</strong> ' . $gender : '')
            . '</td>'
            . '<td align="right" style="padding:2px 0;"><strong>Doctor:</strong> ' . $doctorName . '</td>'
            . '</tr>'
            . '</table>'

            . '<div style="margin:0 0 8px;font-size:12pt;font-weight:bold;color:#0F766E;border-bottom:1px solid #cbd5e1;padding-bottom:4px;">℞ Prescription</div>'
            . $rxTable

            . '<div style="margin-top:36px;text-align:right;">'
            . '<div style="display:inline-block;text-align:center;min-width:200px;">'
            . '<div style="border-top:1px solid #475569;padding-top:4px;font-size:10pt;">' . $doctorName . '</div>'
            . '<div style="font-size:9pt;color:#64748b;">Signature</div>'
            . '</div></div>'

            . ($rxFooter !== '' ? '<p style="margin-top:16px;font-size:9pt;color:#475569;text-align:center;">' . $rxFooter . '</p>' : '')
            . '<p style="margin-top:' . ($rxFooter !== '' ? '8' : '24') . 'px;font-size:8pt;color:#94a3b8;text-align:center;">'
            . 'Generated by ' . $clinicName . ' on ' . self::esc(date('d M Y, h:i A'))
            . '</p>'
            . '</div>';

        if (!class_exists(Mpdf::class)) {
            file_put_contents($path, strip_tags($html));
            return $path;
        }

        $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
        $tmpDir = sys_get_temp_dir() . '/mpdf-' . $uid;
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            $tmpDir = sys_get_temp_dir();
        }

        try {
            $mpdf = new Mpdf(['format' => $format, 'tempDir' => $tmpDir]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, Destination::FILE);
        } catch (\Throwable $e) {
            error_log('[PrescriptionPdfService] mpdf failed: ' . $e->getMessage());
            file_put_contents($path, strip_tags($html));
        }

        return $path;
    }

    /** @param array<string, mixed> $line */
    private static function renderMedicineRow(array $line): string
    {
        $name = PrescriptionService::medicineName($line);
        $potency = trim((string) ($line['potency'] ?? ''));
        $instructions = trim((string) ($line['instructions'] ?? ''));
        $mixWith = trim((string) ($line['mix_with'] ?? ''));
        $mixWithDisplay = $mixWith !== '' ? self::titleCase($mixWith) : '';
        $taperSteps = PrescriptionService::taperingSteps($line);
        $purchase = PrescriptionService::totalQuantityToPurchase($line);
        $purchaseDisplay = $purchase['display'] ?? '';
        $tdStyle = 'padding:6px 5px;border:1px solid #e2e8f0;vertical-align:top;font-size:9pt;';
        $qtyStyle = $tdStyle . 'font-weight:bold;color:#0F766E;text-align:center;';

        $row = '';

        if ($taperSteps !== []) {
            $stepCount = count($taperSteps);
            $totalDays = PrescriptionService::taperingTotalDays($taperSteps);
            $nameCell = self::buildNameCell($name, $potency, $stepCount, $totalDays);

            foreach ($taperSteps as $si => $step) {
                $stepDays = (int) ($step['days'] ?? 0);
                $stepDose = PrescriptionService::stepDoseDisplay($step, $line);
                $stepPreset = PrescriptionService::frequencyPresetLabel((string) ($step['preset'] ?? ''), $line);
                $stepFood = PrescriptionService::foodTimingLabel(isset($step['food']) ? (string) $step['food'] : null);
                $stepDuration = $stepDays > 0
                    ? $stepDays . ' day' . ($stepDays === 1 ? '' : 's')
                    : '';

                $row .= '<tr>';
                if ($si === 0) {
                    $row .= '<td rowspan="' . $stepCount . '" style="' . $tdStyle . '">' . $nameCell . '</td>';
                }
                $row .= '<td style="' . $tdStyle . '">' . self::cell($stepDose) . '</td>'
                    . '<td style="' . $tdStyle . '">' . self::cell($mixWithDisplay) . '</td>'
                    . '<td style="' . $tdStyle . '">' . self::cell($stepPreset) . '</td>'
                    . '<td style="' . $tdStyle . '">' . self::cell($stepFood) . '</td>'
                    . '<td style="' . $tdStyle . '">' . self::cell($stepDuration) . '</td>';
                if ($si === 0) {
                    $row .= '<td rowspan="' . $stepCount . '" style="' . $qtyStyle . '">'
                        . ($purchaseDisplay !== '' ? self::esc($purchaseDisplay) : self::cell(''))
                        . '</td>';
                }
                $row .= '</tr>';
            }
        } else {
            $dosage = PrescriptionService::dosageDisplay($line);
            $freq = PrescriptionService::frequencyDisplay($line);
            $food = PrescriptionService::foodTimingLabel(isset($line['food_timing']) ? (string) $line['food_timing'] : null);
            $duration = !empty($line['duration_days'])
                ? ((int) $line['duration_days']) . ' day' . ((int) $line['duration_days'] === 1 ? '' : 's')
                : '';

            $row .= '<tr>'
                . '<td style="' . $tdStyle . '">' . self::buildNameCell($name, $potency, 0, 0) . '</td>'
                . '<td style="' . $tdStyle . '">' . self::cell($dosage) . '</td>'
                . '<td style="' . $tdStyle . '">' . self::cell($mixWithDisplay) . '</td>'
                . '<td style="' . $tdStyle . '">' . self::cell($freq) . '</td>'
                . '<td style="' . $tdStyle . '">' . self::cell($food) . '</td>'
                . '<td style="' . $tdStyle . '">' . self::cell($duration) . '</td>'
                . '<td style="' . $qtyStyle . '">'
                . ($purchaseDisplay !== '' ? self::esc($purchaseDisplay) : self::cell(''))
                . '</td>'
                . '</tr>';
        }

        if ($instructions !== '') {
            $row .= '<tr>'
                . '<td colspan="7" style="padding:5px 8px;border:1px solid #e2e8f0;background:#fffbeb;font-size:8.5pt;color:#78350f;">'
                . '<strong>Note:</strong> ' . self::esc($instructions)
                . '</td></tr>';
        }

        return $row;
    }

    private static function buildNameCell(string $name, string $potency, int $stepCount, int $totalDays): string
    {
        $html = '<strong>' . self::esc($name) . '</strong>';
        if ($potency !== '') {
            $html .= '<br><span style="color:#64748b;font-size:8pt;">' . self::esc($potency) . '</span>';
        }
        if ($stepCount > 0) {
            $html .= '<br><span style="color:#64748b;font-size:8pt;">Tapering · ' . $stepCount
                . ' step' . ($stepCount === 1 ? '' : 's');
            if ($totalDays > 0) {
                $html .= ', ' . $totalDays . ' day' . ($totalDays === 1 ? '' : 's') . ' total';
            }
            $html .= '</span>';
        }

        return $html;
    }

    private static function cell(string $value): string
    {
        return $value !== '' ? self::esc($value) : '<span style="color:#0f172a;">—</span>';
    }

    private static function titleCase(string $text): string
    {
        return mb_convert_case(trim($text), MB_CASE_TITLE, 'UTF-8');
    }

    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function ageFromDob(string $dob): string
    {
        $ts = strtotime($dob);
        if ($ts === false) return '';
        $years = (int) floor((time() - $ts) / 31557600);
        return $years > 0 ? (string) $years : '';
    }
}
