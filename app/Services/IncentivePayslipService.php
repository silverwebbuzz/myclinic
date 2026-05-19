<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class IncentivePayslipService
{
    /** @param array<string, mixed> $incentive @param array<string, mixed> $clinic */
    public static function generate(array $incentive, array $clinic): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/incentives/' . (int) $clinic['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/payslip-' . (int) $incentive['id'] . '.pdf';
        $rel = '/uploads/incentives/' . (int) $clinic['id'] . '/payslip-' . (int) $incentive['id'] . '.pdf';

        $html = '<div style="font-family:sans-serif;padding:20px;">'
            . '<h2>' . htmlspecialchars((string) $clinic['name']) . '</h2>'
            . '<h3>Doctor incentive payslip</h3>'
            . '<p><strong>Doctor:</strong> ' . htmlspecialchars((string) ($incentive['doctor_name'] ?? '')) . '</p>'
            . '<p><strong>Period:</strong> ' . htmlspecialchars((string) ($incentive['period_month'] ?? '')) . '</p>'
            . '<table width="100%" cellpadding="8" style="border-collapse:collapse;margin-top:16px;">'
            . '<tr><td>Revenue generated</td><td align="right">₹' . number_format((float) ($incentive['revenue_generated'] ?? 0), 2) . '</td></tr>'
            . '<tr><td>Incentive %</td><td align="right">' . htmlspecialchars((string) ($incentive['incentive_percent'] ?? 0)) . '%</td></tr>'
            . '<tr><td>Flat fee</td><td align="right">₹' . number_format((float) ($incentive['flat_fee'] ?? 0), 2) . '</td></tr>'
            . '<tr><td>Gross incentive</td><td align="right">₹' . number_format((float) ($incentive['gross_incentive'] ?? 0), 2) . '</td></tr>'
            . '<tr><td>TDS</td><td align="right">₹' . number_format((float) ($incentive['tds_amount'] ?? 0), 2) . '</td></tr>'
            . '<tr style="font-weight:bold;"><td>Net payable</td><td align="right">₹' . number_format((float) ($incentive['net_payable'] ?? 0), 2) . '</td></tr>'
            . '</table></div>';

        if (class_exists(Mpdf::class)) {
            $mpdf = new Mpdf(['format' => 'A4']);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, Destination::FILE);
        } else {
            file_put_contents($path, strip_tags($html));
        }

        return $rel;
    }
}
