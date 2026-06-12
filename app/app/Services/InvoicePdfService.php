<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class InvoicePdfService
{
    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $clinic
     */
    public static function generate(array $invoice, array $patient, array $clinic): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/invoices/' . (int) $clinic['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // The file must stay publicly reachable (WhatsApp sends the link to
        // the patient), but inv-{id}.pdf was enumerable. Embed a random token;
        // reuse the existing one so links already sent keep working.
        $token = null;
        if (!empty($invoice['pdf_path'])
            && preg_match('/inv-\d+-([a-f0-9]{16})\.pdf$/', (string) $invoice['pdf_path'], $m) === 1) {
            $token = $m[1];
        }
        $token ??= bin2hex(random_bytes(8));

        $filename = 'inv-' . (int) $invoice['id'] . '-' . $token . '.pdf';
        $path = $dir . '/' . $filename;
        $rel = '/uploads/invoices/' . (int) $clinic['id'] . '/' . $filename;

        $items = $invoice['items'] ?? InvoiceService::items((int) $invoice['id']);

        // GST-ready header: GSTIN + clinic contact details, invoice date.
        $clinicLines = array_filter([
            htmlspecialchars((string) ($clinic['address'] ?? '')),
            trim(htmlspecialchars((string) ($clinic['phone'] ?? '')) . ' ' . htmlspecialchars((string) ($clinic['email'] ?? ''))),
            !empty($clinic['gstin']) ? 'GSTIN: ' . htmlspecialchars((string) $clinic['gstin']) : '',
            !empty($clinic['registration_number']) ? 'Reg. No: ' . htmlspecialchars((string) $clinic['registration_number']) : '',
        ]);

        $html = '<div style="font-family:sans-serif;padding:16px;font-size:11pt;">'
            . '<h2 style="margin-bottom:2px;">' . htmlspecialchars((string) $clinic['name']) . '</h2>'
            . '<p style="margin-top:0;font-size:9pt;color:#475569;">' . implode('<br>', $clinicLines) . '</p>'
            . '<p>Invoice <strong>' . htmlspecialchars((string) $invoice['invoice_number']) . '</strong>'
            . ' · Date: ' . htmlspecialchars(date('d M Y', strtotime((string) ($invoice['created_at'] ?? 'now')))) . '</p>'
            . '<p>Patient: ' . htmlspecialchars((string) $patient['name']) . ' · ' . htmlspecialchars((string) $patient['uhid']) . '</p>'
            . '<table width="100%" cellpadding="6" style="border-collapse:collapse;margin-top:12px;">'
            . '<tr style="background:#f1f5f9;"><th align="left">Description</th><th>Qty</th><th>Price</th><th>Total</th></tr>';

        foreach ($items as $item) {
            $html .= '<tr><td>' . htmlspecialchars((string) $item['description']) . '</td>'
                . '<td>' . (int) $item['qty'] . '</td>'
                . '<td>' . number_format((float) $item['unit_price'], 2) . '</td>'
                . '<td>' . number_format((float) ($item['total'] ?? 0), 2) . '</td></tr>';
        }

        $paid = (float) ($invoice['amount_paid'] ?? 0) + (float) ($invoice['advance_paid'] ?? 0);
        $balance = max(0, round((float) $invoice['total'] - $paid, 2));

        $html .= '</table>'
            . '<p style="text-align:right;margin-top:16px;">Subtotal: ' . number_format((float) $invoice['subtotal'], 2) . '<br>'
            . 'Discount: ' . number_format((float) ($invoice['discount_amount'] ?? 0), 2) . '<br>'
            . htmlspecialchars((string) ($invoice['tax_label'] ?? 'Tax')) . ' (' . $invoice['tax_percent'] . '%): '
            . number_format((float) ($invoice['tax_amount'] ?? 0), 2) . '<br>'
            . '<strong>Total: ' . number_format((float) $invoice['total'], 2) . ' ' . htmlspecialchars((string) $invoice['currency']) . '</strong><br>'
            . ($paid > 0 ? 'Paid: ' . number_format($paid, 2) . '<br>' : '')
            . ($balance > 0
                ? '<strong style="color:#b45309;">Balance due: ' . number_format($balance, 2) . '</strong>'
                : '<span style="color:#047857;">PAID IN FULL</span>'
                  . (!empty($invoice['payment_mode']) ? ' · ' . strtoupper(htmlspecialchars((string) $invoice['payment_mode'])) : ''))
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
            } catch (\Throwable $e) {
                error_log('[InvoicePdfService] mpdf failed: ' . $e->getMessage());
                file_put_contents($path, strip_tags($html));
            }
        } else {
            file_put_contents($path, strip_tags($html));
        }

        return $rel;
    }
}
