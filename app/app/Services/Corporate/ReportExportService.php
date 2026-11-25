<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Http\Response;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Report downloads for the company portal (design §6, R2): CSV, Excel, PDF.
 * Built in memory / a temp file and streamed; nothing is kept on the server.
 * Text cells are formula-safe (EmployeeService::csvSafe).
 */
final class ReportExportService
{
    public const FORMATS = ['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'];

    /** @param list<list<string|int|float|null>> $rows header first */
    public static function response(string $format, array $rows, string $title, string $companyName, string $fileBase): Response
    {
        $name = preg_replace('/[^\w\-]+/', '-', $fileBase) . '-' . date('Y-m-d');

        return match ($format) {
            'xlsx' => self::xlsx($rows, $title, $name),
            'pdf' => self::pdf($rows, $title, $companyName, $name),
            default => self::csv($rows, $name),
        };
    }

    /** @param list<list<mixed>> $rows */
    private static function csv(array $rows, string $name): Response
    {
        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "\xEF\xBB\xBF");   // BOM so Excel reads UTF-8
        foreach ($rows as $r) {
            fputcsv($fp, array_map(static fn ($v) => is_string($v) ? EmployeeService::csvSafe($v) : $v, $r));
        }
        rewind($fp);
        $csv = (string) stream_get_contents($fp);
        fclose($fp);

        return self::file($csv, 'text/csv; charset=UTF-8', $name . '.csv');
    }

    /** @param list<list<mixed>> $rows */
    private static function xlsx(array $rows, string $title, string $name): Response
    {
        if (!class_exists(Spreadsheet::class)) {
            return self::csv($rows, $name);
        }
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title) ?: 'Report', 0, 31));
        $r = 1;
        foreach ($rows as $row) {
            $c = 1;
            foreach ($row as $v) {
                $cell = $sheet->getCell([$c, $r]);
                if (is_int($v) || is_float($v)) {
                    $cell->setValue($v);
                } else {
                    $cell->setValueExplicit((string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);   // never a formula
                }
                $c++;
            }
            $r++;
        }
        $sheet->getStyle([1, 1, max(1, count($rows[0] ?? [])), 1])->getFont()->setBold(true);
        foreach (range(1, max(1, count($rows[0] ?? []))) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'corpx');
        (new Xlsx($book))->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return self::file($bytes, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $name . '.xlsx');
    }

    /** @param list<list<mixed>> $rows */
    private static function pdf(array $rows, string $title, string $companyName, string $name): Response
    {
        if (!class_exists(Mpdf::class)) {
            return self::csv($rows, $name);
        }
        $h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $head = array_shift($rows) ?? [];
        $html = '<div style="font-family:sans-serif;font-size:9pt;color:#0f172a;">'
            . '<div style="font-size:14pt;font-weight:bold;">' . $h($title) . '</div>'
            . '<div style="color:#475569;margin-bottom:10px;">' . $h($companyName) . ' · generated ' . $h(date('d M Y, h:i A')) . ' · eClinicPro Workforce Health</div>'
            . '<table width="100%" cellpadding="5" style="border-collapse:collapse;">'
            . '<tr style="background:#f1f5f9;">' . implode('', array_map(static fn ($v) => '<th align="left" style="border-bottom:1px solid #cbd5e1;">' . $h($v) . '</th>', $head)) . '</tr>';
        foreach ($rows as $i => $row) {
            $html .= '<tr' . ($i === count($rows) - 1 ? ' style="font-weight:bold;"' : '') . '>'
                . implode('', array_map(static fn ($v) => '<td style="border-bottom:1px solid #e2e8f0;">' . $h($v) . '</td>', $row)) . '</tr>';
        }
        $html .= '</table><div style="margin-top:10px;color:#64748b;font-size:8pt;">Administrative compliance only. No medical details.</div></div>';
        $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
        $tmpDir = sys_get_temp_dir() . '/mpdf-' . $uid;
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            $tmpDir = sys_get_temp_dir();
        }
        $mpdf = new Mpdf(['format' => 'A4-L', 'tempDir' => $tmpDir, 'margin_top' => 12, 'margin_bottom' => 12]);
        $mpdf->WriteHTML($html);

        return self::file($mpdf->Output('', 'S'), 'application/pdf', $name . '.pdf');
    }

    private static function file(string $bytes, string $type, string $filename): Response
    {
        return new Response($bytes, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
