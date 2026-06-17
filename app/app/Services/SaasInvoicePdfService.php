<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Tax invoice for a clinic's SaaS plan purchase. SELLER = the eClinicPro
 * vendor entity (config/company.php, GST registered); BUYER = the clinic.
 * Stored under app/storage (private) and emailed/streamed to the clinic.
 */
final class SaasInvoicePdfService
{
    /**
     * @param array<string, mixed> $invoice saas_invoices row
     * @param array<string, mixed> $clinic  tenants row (the buyer)
     * @return string absolute path to the PDF
     */
    public static function generate(array $invoice, array $clinic): string
    {
        $company = require dirname(__DIR__, 2) . '/config/company.php';

        $invoiceId = (int) ($invoice['id'] ?? 0);
        $dir = dirname(__DIR__, 2) . '/storage/saas_invoices';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $dir = sys_get_temp_dir();
        }
        $path = $dir . '/saas-inv-' . $invoiceId . '.pdf';

        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $invNo = $e($invoice['invoice_no'] ?? ('SAAS-' . $invoiceId));
        $date = $e(date('d M Y', strtotime((string) ($invoice['paid_at'] ?? $invoice['created_at'] ?? 'now'))));
        $planLabel = ucfirst((string) ($invoice['plan_id'] ?? 'Subscription'));
        $cycle = (string) ($invoice['billing_cycle'] ?? '');
        $amount = (float) ($invoice['amount'] ?? $invoice['total_usd'] ?? 0);
        $currency = $e($invoice['currency'] ?? 'INR');
        $symbol = $currency === 'INR' ? '₹' : ($currency . ' ');
        $status = strtolower((string) ($invoice['status'] ?? 'paid'));

        // GST split. base+tax recorded at checkout; if absent (legacy rows),
        // back-calculate from the gross at the stored tax %.
        $taxPct = (float) ($invoice['tax_percent'] ?? 0);
        $base = (float) ($invoice['base_amount'] ?? 0);
        $tax = (float) ($invoice['tax_amount'] ?? 0);
        if ($base <= 0 && $taxPct > 0) {
            $base = round($amount / (1 + $taxPct / 100), 2);
            $tax = round($amount - $base, 2);
        } elseif ($base <= 0) {
            $base = $amount;
        }
        $halfTax = round($tax / 2, 2); // CGST + SGST (intra-state, Gujarat)

        $sellerAddr = implode('<br>', array_map($e, $company['address_lines'] ?? []));
        $buyerLines = array_filter([
            $e($clinic['name'] ?? 'Clinic'),
            $e($clinic['address'] ?? ''),
            !empty($clinic['gstin']) ? 'GSTIN: ' . $e($clinic['gstin']) : '',
            !empty($clinic['email']) ? $e($clinic['email']) : '',
        ]);

        $period = '';
        if (!empty($invoice['period_start']) && !empty($invoice['period_end'])) {
            $period = ' (' . $e(date('d M Y', strtotime((string) $invoice['period_start'])))
                . ' – ' . $e(date('d M Y', strtotime((string) $invoice['period_end']))) . ')';
        }

        $statusBadge = $status === 'paid'
            ? '<span style="color:#047857;font-weight:bold;">PAID</span>'
            : '<span style="color:#b45309;font-weight:bold;">' . strtoupper($e($status)) . '</span>';

        $html = '<div style="font-family:sans-serif;color:#0f172a;font-size:11pt;">'
            // Seller header
            . '<table width="100%" style="margin-bottom:18px;"><tr>'
            . '<td style="vertical-align:top;">'
            . '<div style="font-size:15pt;font-weight:bold;color:#0F766E;">' . $e($company['brand'] ?? 'eClinicPro') . '</div>'
            . '<div style="font-size:10pt;margin-top:2px;">' . $e($company['legal_name'] ?? '') . '</div>'
            . '<div style="font-size:9pt;color:#475569;margin-top:2px;">' . $sellerAddr . '</div>'
            . '<div style="font-size:9pt;color:#475569;">GSTIN: ' . $e($company['gstin'] ?? '') . '</div>'
            . '</td>'
            . '<td style="vertical-align:top;text-align:right;">'
            . '<div style="font-size:14pt;font-weight:bold;">TAX INVOICE</div>'
            . '<div style="font-size:10pt;margin-top:4px;">Invoice No: <strong>' . $invNo . '</strong></div>'
            . '<div style="font-size:10pt;">Date: ' . $date . '</div>'
            . '<div style="font-size:10pt;margin-top:4px;">' . $statusBadge . '</div>'
            . '</td>'
            . '</tr></table>'

            // Buyer
            . '<div style="border-top:1px solid #cbd5e1;padding-top:10px;margin-bottom:14px;">'
            . '<div style="font-size:9pt;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Billed to</div>'
            . '<div style="font-size:10pt;margin-top:3px;">' . implode('<br>', $buyerLines) . '</div>'
            . '</div>'

            // Line item
            . '<table width="100%" cellpadding="8" style="border-collapse:collapse;font-size:10pt;">'
            . '<thead><tr style="background:#f1f5f9;text-align:left;">'
            . '<th style="padding:8px;">Description</th><th style="padding:8px;text-align:right;">Amount</th>'
            . '</tr></thead><tbody>'
            . '<tr><td style="padding:8px;border-bottom:1px solid #e2e8f0;">'
            . 'eClinicPro <strong>' . $e($planLabel) . '</strong> plan'
            . ($cycle !== '' ? ' — ' . $e($cycle) . ' subscription' : '') . $period
            . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;">' . $symbol . number_format($base, 2) . '</td></tr>'
            . '</tbody></table>'

            // Totals with GST split
            . '<table width="100%" style="margin-top:12px;font-size:10pt;">'
            . '<tr><td style="text-align:right;color:#475569;padding:2px 0;">Taxable value</td>'
            . '<td style="text-align:right;width:120px;padding:2px 0;">' . $symbol . number_format($base, 2) . '</td></tr>'
            . ($tax > 0
                ? '<tr><td style="text-align:right;color:#475569;padding:2px 0;">CGST @ ' . number_format($taxPct / 2, 1) . '%</td>'
                  . '<td style="text-align:right;padding:2px 0;">' . $symbol . number_format($halfTax, 2) . '</td></tr>'
                  . '<tr><td style="text-align:right;color:#475569;padding:2px 0;">SGST @ ' . number_format($taxPct / 2, 1) . '%</td>'
                  . '<td style="text-align:right;padding:2px 0;">' . $symbol . number_format($tax - $halfTax, 2) . '</td></tr>'
                : '')
            . '<tr><td style="text-align:right;font-weight:bold;border-top:1px solid #cbd5e1;padding:6px 0;">Total</td>'
            . '<td style="text-align:right;font-weight:bold;border-top:1px solid #cbd5e1;padding:6px 0;">' . $symbol . number_format($amount, 2) . '</td></tr>'
            . '</table>'
            . '<p style="font-size:8.5pt;color:#94a3b8;margin-top:6px;">'
            . 'Place of supply: Gujarat (24). This is a computer-generated invoice.'
            . '</p>'

            . '<p style="margin-top:24px;font-size:8.5pt;color:#94a3b8;text-align:center;">'
            . $e($company['legal_name'] ?? '') . ' · ' . $e($company['support_email'] ?? '') . ' · ' . $e($company['website'] ?? '')
            . '</p>'
            . '</div>';

        if (class_exists(Mpdf::class)) {
            $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
            $tmpDir = sys_get_temp_dir() . '/mpdf-' . $uid;
            if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
                $tmpDir = sys_get_temp_dir();
            }
            try {
                $mpdf = new Mpdf(['format' => 'A4', 'tempDir' => $tmpDir]);
                $mpdf->WriteHTML($html);
                $mpdf->Output($path, Destination::FILE);
            } catch (\Throwable $ex) {
                error_log('[SaasInvoicePdfService] mpdf failed: ' . $ex->getMessage());
                file_put_contents($path, strip_tags($html));
            }
        } else {
            file_put_contents($path, strip_tags($html));
        }

        return $path;
    }
}
