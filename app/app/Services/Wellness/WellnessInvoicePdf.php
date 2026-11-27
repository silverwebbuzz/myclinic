<?php

declare(strict_types=1);

namespace App\Services\Wellness;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Tax invoice for a Health Creator's credit-pack purchase. SELLER = the
 * eClinicPro vendor entity (config/company.php); BUYER = the creator (legal
 * name / GSTIN / state they gave at checkout). CGST + SGST when the place of
 * supply is the seller's state, IGST otherwise. Stored privately under
 * app/storage/wellness_invoices. Same look as SaasInvoicePdfService.
 *
 * Confirm the SAC code and wording with your CA before relying on it.
 */
final class WellnessInvoicePdf
{
    private const SAC = '998439';   // Other online contents n.e.c. — CONFIRM WITH CA

    /**
     * @param array<string,mixed> $order wh_orders row (paid)
     * @param array{code:string,name:string,intra:bool} $pos place of supply
     * @return string absolute path
     */
    public static function generate(array $order, array $pos): string
    {
        $company = require dirname(__DIR__, 3) . '/config/company.php';
        $dir = dirname(__DIR__, 3) . '/storage/wellness_invoices';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            $dir = sys_get_temp_dir();
        }
        $path = $dir . '/wh-inv-' . (int) $order['id'] . '.pdf';
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $money = static fn ($v): string => '₹' . number_format((float) $v, 2);

        $base = (float) $order['base_amount'];
        $tax = (float) $order['gst_amount'];
        $pct = (float) $order['gst_pct'];
        $half = round($tax / 2, 2);
        $qty = (int) $order['pack_qty'];

        $taxRows = $tax <= 0 ? '' : ($pos['intra']
            ? '<tr><td style="text-align:right;color:#475569;padding:2px 0;">CGST @ ' . number_format($pct / 2, 1) . '%</td><td style="text-align:right;padding:2px 0;">' . $money($half) . '</td></tr>'
              . '<tr><td style="text-align:right;color:#475569;padding:2px 0;">SGST @ ' . number_format($pct / 2, 1) . '%</td><td style="text-align:right;padding:2px 0;">' . $money($tax - $half) . '</td></tr>'
            : '<tr><td style="text-align:right;color:#475569;padding:2px 0;">IGST @ ' . number_format($pct, 1) . '%</td><td style="text-align:right;padding:2px 0;">' . $money($tax) . '</td></tr>');

        $buyer = array_filter([
            '<strong>' . $e($order['buyer_name'] ?? 'Health Creator') . '</strong>',
            !empty($order['buyer_gstin']) ? 'GSTIN: ' . $e($order['buyer_gstin']) : '',
            !empty($order['buyer_state']) ? $e($order['buyer_state']) : '',
            !empty($order['buyer_email']) ? $e($order['buyer_email']) : '',
        ]);

        $html = '<div style="font-family:sans-serif;color:#0f172a;font-size:11pt;">'
            . '<table width="100%" style="margin-bottom:18px;"><tr>'
            . '<td style="vertical-align:top;">'
            . '<div style="font-size:15pt;font-weight:bold;color:#0F766E;">' . $e($company['brand'] ?? 'eClinicPro') . '</div>'
            . '<div style="font-size:10pt;margin-top:2px;">' . $e($company['legal_name'] ?? '') . '</div>'
            . '<div style="font-size:9pt;color:#475569;margin-top:2px;">' . implode('<br>', array_map($e, $company['address_lines'] ?? [])) . '</div>'
            . '<div style="font-size:9pt;color:#475569;">GSTIN: ' . $e($company['gstin'] ?? '') . '</div>'
            . '</td><td style="vertical-align:top;text-align:right;">'
            . '<div style="font-size:14pt;font-weight:bold;">TAX INVOICE</div>'
            . '<div style="font-size:10pt;margin-top:4px;">Invoice No: <strong>' . $e($order['invoice_no']) . '</strong></div>'
            . '<div style="font-size:10pt;">Date: ' . $e(date('d M Y', strtotime((string) ($order['paid_at'] ?? 'now')))) . '</div>'
            . '<div style="font-size:10pt;margin-top:4px;color:#047857;font-weight:bold;">PAID</div>'
            . '</td></tr></table>'
            . '<div style="border-top:1px solid #cbd5e1;padding-top:10px;margin-bottom:14px;">'
            . '<div style="font-size:9pt;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Billed to</div>'
            . '<div style="font-size:10pt;margin-top:3px;">' . implode('<br>', $buyer) . '</div></div>'
            . '<table width="100%" cellpadding="8" style="border-collapse:collapse;font-size:10pt;">'
            . '<thead><tr style="background:#f1f5f9;text-align:left;"><th>Description</th><th>SAC</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Amount</th></tr></thead><tbody>'
            . '<tr><td style="border-bottom:1px solid #e2e8f0;">eClinicPro Wellness Hub — post credits (1 credit = 1 extra listing). Credits do not expire.</td>'
            . '<td style="border-bottom:1px solid #e2e8f0;">' . self::SAC . '</td>'
            . '<td style="border-bottom:1px solid #e2e8f0;text-align:right;">' . $qty . '</td>'
            . '<td style="border-bottom:1px solid #e2e8f0;text-align:right;">' . $money($base) . '</td></tr>'
            . '</tbody></table>'
            . '<table width="100%" style="margin-top:12px;font-size:10pt;">'
            . '<tr><td style="text-align:right;color:#475569;padding:2px 0;">Taxable value</td><td style="text-align:right;width:120px;padding:2px 0;">' . $money($base) . '</td></tr>'
            . $taxRows
            . '<tr><td style="text-align:right;font-weight:bold;border-top:1px solid #cbd5e1;padding:6px 0;">Total</td><td style="text-align:right;font-weight:bold;border-top:1px solid #cbd5e1;padding:6px 0;">' . $money($order['total_amount']) . '</td></tr>'
            . '</table>'
            . '<p style="font-size:8.5pt;color:#94a3b8;margin-top:6px;">Place of supply: ' . $e($pos['name'] ?: '—') . ' (' . $e($pos['code']) . '). Payment ref: ' . $e($order['razorpay_payment_id'] ?: $order['razorpay_order_id']) . '. This is a computer-generated invoice.</p>'
            . '<p style="margin-top:24px;font-size:8.5pt;color:#94a3b8;text-align:center;">' . $e($company['legal_name'] ?? '') . ' · ' . $e($company['support_email'] ?? '') . ' · ' . $e($company['website'] ?? '') . '</p>'
            . '</div>';

        if (class_exists(Mpdf::class)) {
            $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
            $tmp = sys_get_temp_dir() . '/mpdf-' . $uid;
            if (!is_dir($tmp) && !@mkdir($tmp, 0755, true) && !is_dir($tmp)) {
                $tmp = sys_get_temp_dir();
            }
            try {
                $mpdf = new Mpdf(['format' => 'A4', 'tempDir' => $tmp]);
                $mpdf->WriteHTML($html);
                $mpdf->Output($path, Destination::FILE);

                return $path;
            } catch (\Throwable $ex) {
                error_log('[WellnessInvoicePdf] mpdf failed: ' . $ex->getMessage());
            }
        }
        $txt = $dir . '/wh-inv-' . (int) $order['id'] . '.txt';
        file_put_contents($txt, trim(html_entity_decode(strip_tags(str_replace(['<br>', '</tr>', '</div>'], "\n", $html)))));

        return $txt;
    }
}
