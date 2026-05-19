<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class LabReportPdfService
{
    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $clinic
     */
    public static function generate(array $order, array $patient, array $clinic): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/lab/' . (int) $clinic['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/order-' . (int) $order['id'] . '.pdf';
        $rel = '/uploads/lab/' . (int) $clinic['id'] . '/order-' . (int) $order['id'] . '.pdf';

        $html = '<div style="font-family:sans-serif;padding:16px;font-size:11pt;">'
            . '<h2>' . htmlspecialchars((string) $clinic['name']) . ' — Lab Report</h2>'
            . '<p><strong>Patient:</strong> ' . htmlspecialchars((string) $patient['name'])
            . ' · ' . htmlspecialchars((string) $patient['uhid']) . '</p>'
            . '<p><strong>Test:</strong> ' . htmlspecialchars((string) ($order['test_name'] ?? ''))
            . ' (' . htmlspecialchars((string) ($order['barcode'] ?? '')) . ')</p>'
            . '<table width="100%" cellpadding="6" style="border-collapse:collapse;margin-top:12px;">'
            . '<tr style="background:#f1f5f9;"><th>Parameter</th><th>Value</th><th>Unit</th><th>Range</th><th>Flag</th></tr>';

        foreach ($order['results'] ?? [] as $r) {
            $flag = $r['flag'] ?? 'normal';
            $style = str_starts_with((string) $flag, 'critical') ? 'color:red;font-weight:bold;' : '';
            $html .= '<tr style="' . $style . '"><td>' . htmlspecialchars((string) $r['parameter_name'])
                . '</td><td>' . htmlspecialchars((string) $r['value'])
                . '</td><td>' . htmlspecialchars((string) ($r['unit'] ?? ''))
                . '</td><td>' . htmlspecialchars((string) ($r['normal_range'] ?? ''))
                . '</td><td>' . htmlspecialchars((string) $flag) . '</td></tr>';
        }

        $html .= '</table></div>';

        if (class_exists(Mpdf::class)) {
            $mpdf = new Mpdf(['format' => 'A4']);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, Destination::FILE);
        } else {
            file_put_contents($path, strip_tags($html));
        }

        return $rel;
    }

    public static function barcodeLabelHtml(string $barcode, string $patientName, string $testName): string
    {
        $barcodeImg = '';
        if (class_exists(\Picqer\Barcode\BarcodeGeneratorPNG::class)) {
            $gen = new \Picqer\Barcode\BarcodeGeneratorPNG();
            $png = $gen->getBarcode($barcode, $gen::TYPE_CODE_128);
            $barcodeImg = '<img src="data:image/png;base64,' . base64_encode($png) . '" style="height:40px;">';
        } else {
            $barcodeImg = '<p style="font-family:monospace;font-size:14pt;">' . htmlspecialchars($barcode) . '</p>';
        }

        return '<div style="padding:8px;border:1px solid #ccc;width:200px;">'
            . $barcodeImg
            . '<p style="font-size:9pt;margin:4px 0;">' . htmlspecialchars($patientName) . '</p>'
            . '<p style="font-size:8pt;">' . htmlspecialchars($testName) . '</p></div>';
    }
}
