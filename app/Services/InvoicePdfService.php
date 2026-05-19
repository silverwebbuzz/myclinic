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

        $path = $dir . '/inv-' . (int) $invoice['id'] . '.pdf';
        $rel = '/uploads/invoices/' . (int) $clinic['id'] . '/inv-' . (int) $invoice['id'] . '.pdf';

        $items = $invoice['items'] ?? InvoiceService::items((int) $invoice['id']);

        $html = '<div style="font-family:sans-serif;padding:16px;font-size:11pt;">'
            . '<h2>' . htmlspecialchars((string) $clinic['name']) . '</h2>'
            . '<p>Invoice <strong>' . htmlspecialchars((string) $invoice['invoice_number']) . '</strong></p>'
            . '<p>Patient: ' . htmlspecialchars((string) $patient['name']) . ' · ' . htmlspecialchars((string) $patient['uhid']) . '</p>'
            . '<table width="100%" cellpadding="6" style="border-collapse:collapse;margin-top:12px;">'
            . '<tr style="background:#f1f5f9;"><th align="left">Description</th><th>Qty</th><th>Price</th><th>Total</th></tr>';

        foreach ($items as $item) {
            $html .= '<tr><td>' . htmlspecialchars((string) $item['description']) . '</td>'
                . '<td>' . (int) $item['qty'] . '</td>'
                . '<td>' . number_format((float) $item['unit_price'], 2) . '</td>'
                . '<td>' . number_format((float) ($item['total'] ?? 0), 2) . '</td></tr>';
        }

        $html .= '</table>'
            . '<p style="text-align:right;margin-top:16px;">Subtotal: ' . number_format((float) $invoice['subtotal'], 2) . '<br>'
            . 'Discount: ' . number_format((float) ($invoice['discount_amount'] ?? 0), 2) . '<br>'
            . htmlspecialchars((string) ($invoice['tax_label'] ?? 'Tax')) . ' (' . $invoice['tax_percent'] . '%): '
            . number_format((float) ($invoice['tax_amount'] ?? 0), 2) . '<br>'
            . '<strong>Total: ' . number_format((float) $invoice['total'], 2) . ' ' . htmlspecialchars((string) $invoice['currency']) . '</strong></p>'
            . '</div>';

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
