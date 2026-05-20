<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class BillingExportService
{
    /** @param list<array<string, mixed>> $invoices */
    public static function excel(array $invoices): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/invoices-' . date('Ymd-His') . '.xlsx';

        if (!class_exists(Spreadsheet::class)) {
            $csv = $dir . '/invoices-' . date('Ymd-His') . '.csv';
            $fp = fopen($csv, 'w');
            fputcsv($fp, ['Invoice', 'Patient', 'UHID', 'Total', 'Status', 'Date']);
            foreach ($invoices as $inv) {
                fputcsv($fp, [
                    $inv['invoice_number'] ?? '',
                    $inv['patient_name'] ?? '',
                    $inv['uhid'] ?? '',
                    $inv['total'] ?? 0,
                    $inv['status'] ?? '',
                    $inv['created_at'] ?? '',
                ]);
            }
            fclose($fp);

            return $csv;
        }

        $sheet = new Spreadsheet();
        $active = $sheet->getActiveSheet();
        $active->fromArray(['Invoice', 'Patient', 'UHID', 'Total', 'Status', 'Date'], null, 'A1');
        $row = 2;
        foreach ($invoices as $inv) {
            $active->fromArray([
                $inv['invoice_number'] ?? '',
                $inv['patient_name'] ?? '',
                $inv['uhid'] ?? '',
                $inv['total'] ?? 0,
                $inv['status'] ?? '',
                $inv['created_at'] ?? '',
            ], null, 'A' . $row);
            $row++;
        }

        (new Xlsx($sheet))->save($path);

        return $path;
    }

    /** @param list<array<string, mixed>> $invoices */
    public static function tallyXml(array $invoices, array $clinic): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/tally-' . date('Ymd-His') . '.xml';
        $company = htmlspecialchars($clinic['name'] ?? 'Clinic');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= "<ENVELOPE><HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER><BODY><IMPORTDATA>\n";
        $xml .= "<REQUESTDESC><REPORTNAME>Vouchers</REPORTNAME><STATICVARIABLES><SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY></STATICVARIABLES></REQUESTDESC>\n";
        $xml .= "<REQUESTDATA>\n";

        foreach ($invoices as $inv) {
            $amt = number_format((float) ($inv['total'] ?? 0), 2, '.', '');
            $date = date('Ymd', strtotime($inv['created_at'] ?? 'now'));
            $voucher = htmlspecialchars($inv['invoice_number'] ?? 'INV');
            $xml .= "<TALLYMESSAGE><VOUCHER VCHTYPE=\"Sales\" ACTION=\"Create\" DATE=\"{$date}\">\n";
            $xml .= "<VOUCHERNUMBER>{$voucher}</VOUCHERNUMBER><PARTYLEDGERNAME>Sales</PARTYLEDGERNAME><AMOUNT>{$amt}</AMOUNT>\n";
            $xml .= "</VOUCHER></TALLYMESSAGE>\n";
        }

        $xml .= "</REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>";
        file_put_contents($path, $xml);

        return $path;
    }
}
