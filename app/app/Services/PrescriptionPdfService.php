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

        $clinicName = self::esc((string) ($clinic['name'] ?? 'Clinic'));
        $clinicPhone = self::esc((string) ($clinic['phone'] ?? ''));
        $clinicAddr = self::esc((string) ($clinic['address'] ?? ''));
        $regNo = self::esc((string) ($clinic['registration_number'] ?? ''));

        // Clinic-configured pad header/footer (Settings → General).
        $rxHeader = '';
        $rxFooter = '';
        try {
            $config = OnboardingService::specialtyConfig($clinicId) ?? [];
            $rxHeader = self::esc((string) ($config['rx_header_text'] ?? ''));
            $rxFooter = self::esc((string) ($config['rx_footer_text'] ?? ''));
        } catch (\Throwable $e) {
            // Pre-migration-024 schema — pad prints without custom notes.
        }

        // Doctor credentials for the pad header (users.qualification / specialization).
        $doctorQual = '';
        $doctorSpec = '';
        try {
            $doc = \App\Core\QueryBuilder::table('users')
                ->forClinic($clinicId)
                ->where('id', '=', (int) ($visit['doctor_id'] ?? 0))
                ->first() ?? [];
            $doctorQual = self::esc(trim((string) ($doc['qualification'] ?? '')));
            $doctorSpec = self::esc(trim((string) ($doc['specialization'] ?? '')));
        } catch (\Throwable $e) {
            // Header prints with the name only.
        }

        $patientName = self::esc((string) ($patient['name'] ?? ''));
        $uhid = self::esc((string) ($patient['uhid'] ?? ''));
        $patientPhone = self::esc((string) ($patient['phone'] ?? ''));
        $age = !empty($patient['dob']) ? self::ageFromDob((string) $patient['dob']) : '';
        $gender = self::esc(ucfirst((string) ($patient['gender'] ?? '')));
        $allergies = self::allergiesText($patient['allergies'] ?? ($visit['allergies'] ?? ''));

        $doctorName = self::esc((string) ($visit['doctor_name'] ?? ''));
        $doctorLabel = $doctorName !== '' && !preg_match('/^dr\.?\s/i', $doctorName) ? 'Dr. ' . $doctorName : $doctorName;
        $visitedAt = !empty($visit['visited_at']) ? date('d M Y', strtotime((string) $visit['visited_at'])) : '';

        // ---- Clinical summary: Examination Finding + Diagnosis ----
        $findings = self::examinationFindings($clinicId, $visitId);
        $examText = trim((string) ($visit['examination'] ?? ''));
        $diagnosis = trim((string) ($visit['diagnosis'] ?? ''));
        $icd = trim((string) ($visit['icd10_code'] ?? ''));
        $vitalsLine = self::vitalsLine($clinicId, $visitId);
        $followUp = !empty($visit['follow_up_date']) && strtotime((string) $visit['follow_up_date'])
            ? date('d M Y (l)', strtotime((string) $visit['follow_up_date']))
            : '';

        // Mix-with only matters for some specialties: drop the column when empty.
        $showMix = false;
        foreach ($lines as $line) {
            if (trim((string) ($line['mix_with'] ?? '')) !== '') {
                $showMix = true;
                break;
            }
        }
        $cols = $showMix ? 7 : 6;

        $tableRows = '';
        $n = 0;
        foreach ($lines as $line) {
            $tableRows .= self::renderMedicineRow($line, ++$n, $showMix);
        }
        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="' . $cols . '" class="empty">No medicines prescribed</td></tr>';
        }

        $rxTable = '<table class="rx" width="100%" cellspacing="0" cellpadding="0">'
            . '<thead><tr>'
            . '<th style="width:5%;">#</th>'
            . '<th style="width:' . ($showMix ? '29' : '36') . '%;">Medicine</th>'
            . '<th style="width:12%;">Dose</th>'
            . ($showMix ? '<th style="width:12%;">Mix with</th>' : '')
            . '<th style="width:' . ($showMix ? '18' : '22') . '%;">When</th>'
            . '<th style="width:12%;">Duration</th>'
            . '<th style="width:' . ($showMix ? '12' : '13') . '%;text-align:center;">Qty</th>'
            . '</tr></thead><tbody>'
            . $tableRows
            . '</tbody></table>';

        $css = '<style>
            body { font-family: sans-serif; color: #0f172a; font-size: 9.5pt; }
            .hdr td { vertical-align: top; }
            .clinic { font-size: 17pt; font-weight: bold; color: #0F766E; }
            .tag { font-size: 9pt; color: #0f766e; margin-top: 1px; }
            .muted { font-size: 8.5pt; color: #475569; }
            .doc { font-size: 11.5pt; font-weight: bold; color: #0f172a; }
            .rule { height: 3px; background: #0F766E; margin: 7px 0 0; }
            .rule2 { height: 1px; background: #99f6e4; margin: 1px 0 8px; }
            .pt { background: #f0fdfa; border: 1px solid #ccfbf1; margin-bottom: 8px; }
            .pt td { padding: 5px 8px; font-size: 9pt; vertical-align: top; }
            .lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
            .val { font-size: 10pt; font-weight: bold; color: #0f172a; }
            .allergy { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; padding: 4px 8px; font-size: 8.5pt; margin-bottom: 8px; }
            .vitals { font-size: 8.5pt; color: #334155; padding: 0 0 6px; }
            .sec { margin-bottom: 7px; }
            .sech { font-size: 8pt; font-weight: bold; color: #0F766E; text-transform: uppercase; letter-spacing: 0.6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; margin-bottom: 3px; }
            .secb { font-size: 9.5pt; color: #0f172a; line-height: 1.35; }
            .icd { color: #64748b; font-size: 8.5pt; }
            .rxsym { font-size: 20pt; font-weight: bold; color: #0F766E; }
            table.rx { border-collapse: collapse; font-size: 9pt; }
            table.rx th { background: #0F766E; color: #ffffff; font-size: 8pt; font-weight: bold; text-align: left; padding: 5px 5px; }
            table.rx td { padding: 5px 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top; font-size: 9pt; }
            table.rx td.qty { font-weight: bold; color: #0F766E; text-align: center; }
            table.rx td.num { color: #64748b; }
            table.rx td.note { background: #fffbeb; color: #78350f; font-size: 8.5pt; padding: 4px 8px; }
            table.rx td.empty { padding: 12px; text-align: center; color: #94a3b8; }
            .sub { color: #64748b; font-size: 8pt; }
            .fu { background: #f0fdfa; border-left: 3px solid #0F766E; padding: 5px 8px; font-size: 9.5pt; margin-top: 10px; }
            .sign { margin-top: 30px; }
            .signline { border-top: 1px solid #475569; padding-top: 3px; font-size: 10pt; font-weight: bold; text-align: center; }
        </style>';

        // Header: clinic on the left, doctor on the right.
        $doctorBlock = '<div class="doc">' . $doctorLabel . '</div>'
            . ($doctorQual !== '' ? '<div class="muted">' . $doctorQual . '</div>' : '')
            . ($doctorSpec !== '' ? '<div class="muted">' . $doctorSpec . '</div>' : '')
            . ($regNo !== '' ? '<div class="muted">Reg. No: ' . $regNo . '</div>' : '');

        $header = '<table class="hdr" width="100%" cellspacing="0" cellpadding="0"><tr>'
            . '<td style="width:58%;">'
            . '<div class="clinic">' . $clinicName . '</div>'
            . ($rxHeader !== '' ? '<div class="tag">' . $rxHeader . '</div>' : '')
            . ($clinicAddr !== '' ? '<div class="muted">' . $clinicAddr . '</div>' : '')
            . ($clinicPhone !== '' ? '<div class="muted">Phone: ' . $clinicPhone . '</div>' : '')
            . '</td>'
            . '<td style="width:42%;text-align:right;">' . $doctorBlock . '</td>'
            . '</tr></table>'
            . '<div class="rule"></div><div class="rule2"></div>';

        $ageSex = trim(($age !== '' ? $age . ' yrs' : '') . ($age !== '' && $gender !== '' ? ' / ' : '') . $gender);
        $patientBox = '<table class="pt" width="100%" cellspacing="0" cellpadding="0"><tr>'
            . '<td style="width:34%;"><div class="lbl">Patient</div><div class="val">' . $patientName . '</div>'
            . ($uhid !== '' ? '<div class="sub">UHID: ' . $uhid . '</div>' : '') . '</td>'
            . '<td style="width:24%;"><div class="lbl">Age / Sex</div><div class="val">' . ($ageSex !== '' ? $ageSex : '—') . '</div></td>'
            . '<td style="width:21%;"><div class="lbl">Date</div><div class="val">' . self::esc($visitedAt) . '</div></td>'
            . '<td style="width:21%;"><div class="lbl">Mobile</div><div class="val" style="font-size:9pt;">' . ($patientPhone !== '' ? $patientPhone : '—') . '</div></td>'
            . '</tr></table>';

        $clinical = '';
        if ($allergies !== '') {
            $clinical .= '<div class="allergy"><strong>Allergies:</strong> ' . self::esc($allergies) . '</div>';
        }
        if ($vitalsLine !== '') {
            $clinical .= '<div class="vitals"><strong style="color:#0F766E;">Vitals:</strong> ' . $vitalsLine . '</div>';
        }
        if ($findings !== [] || $examText !== '') {
            $body = $findings !== [] ? self::esc(implode(', ', $findings)) : '';
            if ($examText !== '') {
                $body .= ($body !== '' ? '<br>' : '') . nl2br(self::esc($examText));
            }
            $clinical .= '<div class="sec"><div class="sech">Examination Finding</div><div class="secb">' . $body . '</div></div>';
        }
        if ($diagnosis !== '' || $icd !== '') {
            $clinical .= '<div class="sec"><div class="sech">Diagnosis</div><div class="secb"><strong>'
                . self::esc($diagnosis !== '' ? $diagnosis : $icd) . '</strong>'
                . ($icd !== '' && $diagnosis !== '' ? ' <span class="icd">(ICD-10: ' . self::esc($icd) . ')</span>' : '')
                . '</div></div>';
        }

        $html = $css
            . $header
            . $patientBox
            . $clinical
            . '<div style="margin:6px 0 3px;"><span class="rxsym">&#8478;</span></div>'
            . $rxTable
            . ($followUp !== '' ? '<div class="fu"><strong>Next visit:</strong> ' . self::esc($followUp) . '</div>' : '')
            . '<table class="sign" width="100%" cellspacing="0" cellpadding="0"><tr>'
            . '<td style="width:55%;"></td>'
            . '<td style="width:45%;"><div style="height:26px;"></div><div class="signline">' . $doctorLabel . '</div>'
            . '<div class="sub" style="text-align:center;">Signature</div></td>'
            . '</tr></table>';

        $footer = '<div style="border-top:1px solid #e2e8f0;padding-top:3px;text-align:center;font-family:sans-serif;">'
            . ($rxFooter !== '' ? '<div style="font-size:8.5pt;color:#475569;">' . $rxFooter . '</div>' : '')
            . '<div style="font-size:7pt;color:#94a3b8;">' . $clinicName . ' · Printed ' . self::esc(date('d M Y, h:i A'))
            . ' · Page {PAGENO} of {nbpg}</div></div>';

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
            $mpdf = new Mpdf([
                'format' => $format,
                'tempDir' => $tmpDir,
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 9,
                'margin_bottom' => 16,
                'margin_footer' => 5,
            ]);
            $mpdf->SetTitle('Prescription - ' . html_entity_decode($patientName, ENT_QUOTES, 'UTF-8'));
            $mpdf->SetHTMLFooter($footer);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, Destination::FILE);
        } catch (\Throwable $e) {
            error_log('[PrescriptionPdfService] mpdf failed: ' . $e->getMessage());
            file_put_contents($path, strip_tags($html));
        }

        return $path;
    }

    /** @param array<string, mixed> $line */
    private static function renderMedicineRow(array $line, int $num, bool $showMix): string
    {
        $name = PrescriptionService::medicineName($line);
        $potency = trim((string) ($line['potency'] ?? ''));
        $instructions = trim((string) ($line['instructions'] ?? ''));
        $mixWith = trim((string) ($line['mix_with'] ?? ''));
        $mixWithDisplay = $mixWith !== '' ? self::titleCase($mixWith) : '';
        $taperSteps = PrescriptionService::taperingSteps($line);
        $purchase = PrescriptionService::totalQuantityToPurchase($line);
        $purchaseDisplay = $purchase['display'] ?? '';
        $cols = $showMix ? 7 : 6;
        $qtyCell = $purchaseDisplay !== '' ? self::esc($purchaseDisplay) : self::cell('');

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
                    $row .= '<td rowspan="' . $stepCount . '" class="num">' . $num . '</td>'
                        . '<td rowspan="' . $stepCount . '">' . $nameCell . '</td>';
                }
                $row .= '<td>' . self::cell($stepDose) . '</td>'
                    . ($showMix ? '<td>' . self::cell($mixWithDisplay) . '</td>' : '')
                    . '<td>' . self::whenCell($stepPreset, $stepFood) . '</td>'
                    . '<td>' . self::cell($stepDuration) . '</td>';
                if ($si === 0) {
                    $row .= '<td rowspan="' . $stepCount . '" class="qty">' . $qtyCell . '</td>';
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
                . '<td class="num">' . $num . '</td>'
                . '<td>' . self::buildNameCell($name, $potency, 0, 0) . '</td>'
                . '<td>' . self::cell($dosage) . '</td>'
                . ($showMix ? '<td>' . self::cell($mixWithDisplay) . '</td>' : '')
                . '<td>' . self::whenCell($freq, $food) . '</td>'
                . '<td>' . self::cell($duration) . '</td>'
                . '<td class="qty">' . $qtyCell . '</td>'
                . '</tr>';
        }

        if ($instructions !== '') {
            $row .= '<tr><td></td><td colspan="' . ($cols - 1) . '" class="note">'
                . '<strong>Note:</strong> ' . self::esc($instructions)
                . '</td></tr>';
        }

        return $row;
    }

    /** Frequency on top, food timing underneath — one readable "When" cell. */
    private static function whenCell(string $freq, string $food): string
    {
        if ($freq === '' && $food === '') {
            return self::cell('');
        }
        $html = $freq !== '' ? '<strong>' . self::esc($freq) . '</strong>' : '';
        if ($food !== '') {
            $html .= ($html !== '' ? '<br>' : '') . '<span class="sub">' . self::esc($food) . '</span>';
        }

        return $html;
    }

    /**
     * Examination Finding chips recorded on the visit (visit_symptoms).
     * @return list<string>
     */
    private static function examinationFindings(int $clinicId, int $visitId): array
    {
        try {
            $stmt = \App\Core\Database::connection()->prepare(
                'SELECT label FROM visit_symptoms
                  WHERE visit_id = :v AND clinic_id = :c
                  ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([':v' => $visitId, ':c' => $clinicId]);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $label) {
                $label = trim((string) $label);
                if ($label !== '') {
                    $out[] = $label;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** One-line vitals summary ("BP 120/80 mmHg · Pulse 72/min …"), escaped. */
    private static function vitalsLine(int $clinicId, int $visitId): string
    {
        try {
            $v = VitalsService::forVisit($clinicId, $visitId);
        } catch (\Throwable $e) {
            return '';
        }
        if (!is_array($v)) {
            return '';
        }
        $num = static function ($x): string {
            if ($x === null || $x === '') return '';
            $f = (float) $x;
            return $f == (int) $f ? (string) (int) $f : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
        };
        $parts = [];
        if ($num($v['bp_systolic'] ?? null) !== '' && $num($v['bp_diastolic'] ?? null) !== '') {
            $parts[] = 'BP ' . $num($v['bp_systolic']) . '/' . $num($v['bp_diastolic']) . ' mmHg';
        }
        if ($num($v['pulse_rate'] ?? null) !== '') $parts[] = 'Pulse ' . $num($v['pulse_rate']) . '/min';
        if ($num($v['temperature'] ?? null) !== '') $parts[] = 'Temp ' . $num($v['temperature']) . ' °F';
        if ($num($v['spo2'] ?? null) !== '') $parts[] = 'SpO2 ' . $num($v['spo2']) . '%';
        if ($num($v['weight_kg'] ?? null) !== '') $parts[] = 'Wt ' . $num($v['weight_kg']) . ' kg';
        if ($num($v['height_cm'] ?? null) !== '') $parts[] = 'Ht ' . $num($v['height_cm']) . ' cm';
        if ($num($v['bmi'] ?? null) !== '') $parts[] = 'BMI ' . $num($v['bmi']);
        if ($num($v['blood_sugar'] ?? null) !== '') {
            $types = ['fasting' => 'fasting', 'pp' => 'PP', 'random' => 'random'];
            $t = $types[(string) ($v['sugar_type'] ?? '')] ?? '';
            $parts[] = 'Sugar ' . $num($v['blood_sugar']) . ' mg/dL' . ($t !== '' ? ' (' . $t . ')' : '');
        }

        return self::esc(implode('  ·  ', $parts));
    }

    /** patients.allergies is free text, occasionally a JSON list. */
    private static function allergiesText($raw): string
    {
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $raw = trim((string) $raw);
            if ($raw === '') return '';
            $decoded = json_decode($raw, true);
            $list = is_array($decoded) ? $decoded : [$raw];
        }
        $list = array_values(array_filter(array_map(static fn ($x) => is_scalar($x) ? trim((string) $x) : '', $list), static fn ($x) => $x !== ''));
        $text = implode(', ', $list);
        $lower = strtolower($text);

        return in_array($lower, ['none', 'nil', 'no', 'na', 'n/a', 'nkda', '-'], true) || str_starts_with($lower, 'no known') ? '' : $text;
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
        return $value !== '' ? self::esc($value) : '<span style="color:#cbd5e1;">—</span>';
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
