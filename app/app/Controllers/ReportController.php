<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\IncomeReportService;
use App\Support\Layout;

final class ReportController
{
    /**
     * GET /reports/income — money billed and collected. Defaults to today;
     * ?range=week|month or explicit ?from=&to= widen it.
     */
    public function income(Request $request): Response
    {
        if ($denied = $this->requireBilling()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $today = date('Y-m-d');

        $range = (string) ($request->query['range'] ?? '');
        [$from, $to] = match ($range) {
            'week' => [date('Y-m-d', strtotime('monday this week')), $today],
            'month' => [date('Y-m-01'), $today],
            'year' => [date('Y-01-01'), $today],
            default => [
                self::validDate($request->query['from'] ?? null) ?? $today,
                self::validDate($request->query['to'] ?? null) ?? $today,
            ],
        };
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return Response::html(Layout::page('reports/income', [
            'from' => $from,
            'to' => $to,
            'range' => $range,
            'isToday' => $from === $today && $to === $today,
            'summary' => IncomeReportService::summary($clinicId, $from, $to),
            'gst' => IncomeReportService::gstBreakdown($clinicId, $from, $to),
            'daily' => IncomeReportService::daily($clinicId, $from, $to),
            'invoices' => IncomeReportService::invoices($clinicId, $from, $to),
        ], 'Income report'));
    }

    /** GET /reports/income/export — the same rows as CSV. */
    public function incomeCsv(Request $request): Response
    {
        if ($denied = $this->requireBilling()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $today = date('Y-m-d');
        $from = self::validDate($request->query['from'] ?? null) ?? $today;
        $to = self::validDate($request->query['to'] ?? null) ?? $today;

        $rows = IncomeReportService::invoices($clinicId, $from, $to, 5000);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Invoice', 'Date', 'Patient', 'UHID', 'Doctor', 'Subtotal', 'Discount', 'Tax %', 'Tax', 'Total', 'Paid', 'Due', 'Mode', 'Status']);
        foreach ($rows as $r) {
            $paid = (float) ($r['amount_paid'] ?? 0) + (float) ($r['advance_paid'] ?? 0);
            fputcsv($out, [
                $r['invoice_number'] ?? '',
                substr((string) ($r['created_at'] ?? ''), 0, 10),
                $r['patient_name'] ?? '',
                $r['uhid'] ?? '',
                $r['doctor_name'] ?? '',
                number_format((float) ($r['subtotal'] ?? 0), 2, '.', ''),
                number_format((float) ($r['discount_amount'] ?? 0), 2, '.', ''),
                (float) ($r['tax_percent'] ?? 0),
                number_format((float) ($r['tax_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($r['total'] ?? 0), 2, '.', ''),
                number_format($paid, 2, '.', ''),
                number_format(max(0, (float) ($r['total'] ?? 0) - $paid), 2, '.', ''),
                $r['payment_mode'] ?? '',
                $r['status'] ?? '',
            ]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="income-' . $from . '-to-' . $to . '.csv"',
        ]);
    }

    private static function validDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /** Same gate as Patient Bills — the report is just its summary. */
    private function requireBilling(): ?Response
    {
        if (!ModuleGate::check('invoicing_basic') && !ModuleGate::check('billing_pro')) {
            return Response::html(Layout::page('errors/module', [
                'module' => 'invoicing_basic',
                'label' => 'Patient Bills',
            ], 'Module inactive'), 402);
        }

        return null;
    }
}
