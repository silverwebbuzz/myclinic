<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Subscription (SaaS) invoices for clinic/doctor plan purchases.
 *
 * Lifecycle:
 *   1. createPending() — at checkout, BEFORE redirecting to the gateway.
 *      Row is status='pending' so the doctor sees it in their timeline while
 *      the payment is in progress.
 *   2. markPaidByOrder() — on gateway success (Razorpay webhook OR return-URL
 *      verify; both call this, it's idempotent). Generates the PDF and emails it.
 *   2b. markFailedByOrder() — on Razorpay payment.failed, flags the pending row.
 *
 * The SELLER is the eClinicPro vendor entity (config/company.php), NOT the
 * clinic — these are the clinic's purchases FROM us.
 */
final class SaasInvoiceService
{
    /**
     * Create (or reuse) a pending invoice for a checkout attempt.
     * Returns the saas_invoices row id, or 0 on failure (non-fatal).
     */
    /**
     * @param array{base: float, tax: float, gross: float, percent: float} $price
     */
    public static function createPending(
        int $clinicId,
        string $planId,
        string $billingCycle,
        array $price,
        string $gateway,
        string $gatewayOrderId,
    ): int {
        if (!Database::ping()) {
            return 0;
        }

        $gross = (float) ($price['gross'] ?? 0);
        $base = (float) ($price['base'] ?? $gross);
        $tax = (float) ($price['tax'] ?? 0);
        $taxPct = (float) ($price['percent'] ?? 0);

        try {
            // Reuse an existing pending row for the same order id (retry-safe).
            $existing = QueryBuilder::table('saas_invoices')
                ->where('gateway_order_id', '=', $gatewayOrderId)
                ->first();
            if ($existing !== null) {
                return (int) $existing['id'];
            }

            $now = date('Y-m-d');
            $periodEnd = $billingCycle === 'yearly'
                ? date('Y-m-d', strtotime('+1 year'))
                : date('Y-m-d', strtotime('+1 month'));

            $row = [
                'clinic_id' => $clinicId,
                'invoice_no' => self::nextInvoiceNo(),
                'period_start' => $now,
                'period_end' => $periodEnd,
                'plan_id' => $planId,
                'billing_cycle' => $billingCycle,
                'amount' => $gross,
                'currency' => 'INR',
                'total_usd' => $gross, // legacy column is NOT NULL
                'gateway' => $gateway,
                'gateway_order_id' => $gatewayOrderId,
                'status' => 'pending',
            ];

            try {
                $id = QueryBuilder::table('saas_invoices')->insert(array_merge($row, [
                    'base_amount' => $base,
                    'tax_amount' => $tax,
                    'tax_percent' => $taxPct,
                ]));
            } catch (\Throwable $e) {
                // GST columns missing (pre-027) — store the gross only.
                $id = QueryBuilder::table('saas_invoices')->insert($row);
            }

            return (int) $id;
        } catch (\Throwable $e) {
            // Pre-migration-027 schema, or a transient DB issue. Payment still
            // works; the invoice row is best-effort.
            error_log('[SaasInvoiceService] createPending failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Mark the invoice for a gateway order as paid, generate the PDF and email
     * it. Idempotent: a second call (webhook after return-URL, or vice-versa)
     * is a no-op once the row is already paid.
     */
    public static function markPaidByOrder(string $gatewayOrderId, ?string $gatewayPaymentId = null): void
    {
        if (!Database::ping() || $gatewayOrderId === '') {
            return;
        }

        try {
            $invoice = QueryBuilder::table('saas_invoices')
                ->where('gateway_order_id', '=', $gatewayOrderId)
                ->first();
            if ($invoice === null || ($invoice['status'] ?? '') === 'paid') {
                return; // nothing to do / already settled
            }

            QueryBuilder::table('saas_invoices')
                ->where('id', '=', (int) $invoice['id'])
                ->update([
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'gateway_payment_id' => $gatewayPaymentId,
                ]);

            self::generateAndEmail((int) $invoice['id']);
        } catch (\Throwable $e) {
            error_log('[SaasInvoiceService] markPaidByOrder failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark the pending invoice for a gateway order as failed (Razorpay
     * payment.failed webhook). Only a still-pending row is touched — a paid
     * invoice is never regressed. Lets the doctor see the failure and retry.
     */
    public static function markFailedByOrder(string $gatewayOrderId): void
    {
        if (!Database::ping() || $gatewayOrderId === '') {
            return;
        }

        try {
            $invoice = QueryBuilder::table('saas_invoices')
                ->where('gateway_order_id', '=', $gatewayOrderId)
                ->first();
            if ($invoice === null || ($invoice['status'] ?? '') !== 'pending') {
                return; // never regress a paid invoice
            }

            QueryBuilder::table('saas_invoices')
                ->where('id', '=', (int) $invoice['id'])
                ->update(['status' => 'failed']);
        } catch (\Throwable $e) {
            error_log('[SaasInvoiceService] markFailedByOrder failed: ' . $e->getMessage());
        }
    }

    /**
     * Render the PDF (if not already) and email it to the clinic — only when
     * the clinic has an email on file (email is optional app-wide).
     */
    public static function generateAndEmail(int $invoiceId): void
    {
        try {
            $invoice = QueryBuilder::table('saas_invoices')->where('id', '=', $invoiceId)->first();
            if ($invoice === null) {
                return;
            }
            $clinic = QueryBuilder::table('tenants')->where('id', '=', (int) $invoice['clinic_id'])->first() ?? [];

            $pdfPath = $invoice['pdf_path'] ?? null;
            if (empty($pdfPath)) {
                $pdfPath = SaasInvoicePdfService::generate($invoice, $clinic);
                QueryBuilder::table('saas_invoices')->where('id', '=', $invoiceId)
                    ->update(['pdf_path' => $pdfPath]);
            }

            // Email the receipt (guarded — MailService also skips blank addresses).
            $to = trim((string) ($clinic['email'] ?? ''));
            if ($to !== '' && empty($invoice['emailed_at'])) {
                MailService::send($to, 'subscription_invoice', [
                    'clinic_name' => $clinic['name'] ?? 'your clinic',
                    'invoice_no' => $invoice['invoice_no'] ?? '',
                    'plan_id' => $invoice['plan_id'] ?? '',
                    'amount' => $invoice['amount'] ?? $invoice['total_usd'] ?? 0,
                    'currency' => $invoice['currency'] ?? 'INR',
                    'pdf_url' => $pdfPath,
                ], (int) $invoice['clinic_id']);

                QueryBuilder::table('saas_invoices')->where('id', '=', $invoiceId)
                    ->update(['emailed_at' => date('Y-m-d H:i:s')]);
            }
        } catch (\Throwable $e) {
            error_log('[SaasInvoiceService] generateAndEmail failed: ' . $e->getMessage());
        }
    }

    /** @return list<array<string, mixed>> */
    public static function forClinic(int $clinicId, int $limit = 20): array
    {
        if (!Database::ping()) {
            return [];
        }
        try {
            return QueryBuilder::table('saas_invoices')
                ->forClinic($clinicId)
                ->orderBy('created_at', 'DESC')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Sequential, year-prefixed invoice number — ECP-YYYY-NNNNN.
     * Uses the max existing sequence for the year (a unique index isn't present,
     * so this is best-effort under low concurrency, which subscription
     * purchases are).
     */
    private static function nextInvoiceNo(): string
    {
        $year = date('Y');
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_no, '-', -1) AS UNSIGNED)), 0) + 1 AS n
                 FROM saas_invoices WHERE invoice_no LIKE ?",
            );
            $stmt->execute(['ECP-' . $year . '-%']);
            $n = (int) ($stmt->fetch()['n'] ?? 1);
        } catch (\Throwable $e) {
            $n = (int) (substr((string) time(), -5));
        }

        return sprintf('ECP-%s-%05d', $year, $n);
    }
}
