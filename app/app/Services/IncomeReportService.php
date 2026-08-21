<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Income report: what the clinic billed and what it actually collected over a
 * date range, plus the GST split when the clinic charges tax.
 *
 * "Billed" is driven by invoices raised in the range; "collected" by payments
 * recorded in the range — a bill raised yesterday and settled today counts as
 * yesterday's billing and today's collection, which is how a clinic reads its
 * day book.
 */
final class IncomeReportService
{
    /**
     * @return array{
     *   invoices: int, gross: float, discount: float, taxable: float,
     *   tax: float, billed: float, collected: float, due: float,
     *   modes: array<string, float>, tax_label: string
     * }
     */
    public static function summary(int $clinicId, string $from, string $to): array
    {
        $empty = [
            'invoices' => 0, 'gross' => 0.0, 'discount' => 0.0, 'taxable' => 0.0,
            'tax' => 0.0, 'billed' => 0.0, 'collected' => 0.0, 'due' => 0.0,
            'modes' => [], 'tax_label' => 'GST',
        ];
        if (!Database::ping()) {
            return $empty;
        }

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS invoices,
                    COALESCE(SUM(subtotal), 0) AS gross,
                    COALESCE(SUM(discount_amount), 0) AS discount,
                    COALESCE(SUM(tax_amount), 0) AS tax,
                    COALESCE(SUM(total), 0) AS billed,
                    COALESCE(SUM(COALESCE(amount_paid, 0) + COALESCE(advance_paid, 0)), 0) AS paid,
                    MAX(tax_label) AS tax_label
               FROM invoices
              WHERE clinic_id = ? AND status != 'cancelled'
                    AND DATE(created_at) BETWEEN ? AND ?",
        );
        $stmt->execute([$clinicId, $from, $to]);
        $row = $stmt->fetch() ?: [];

        $gross = (float) ($row['gross'] ?? 0);
        $discount = (float) ($row['discount'] ?? 0);
        $billed = (float) ($row['billed'] ?? 0);
        $paidOnThose = (float) ($row['paid'] ?? 0);

        return [
            'invoices' => (int) ($row['invoices'] ?? 0),
            'gross' => round($gross, 2),
            'discount' => round($discount, 2),
            'taxable' => round($gross - $discount, 2),
            'tax' => round((float) ($row['tax'] ?? 0), 2),
            'billed' => round($billed, 2),
            'collected' => self::collected($clinicId, $from, $to),
            'due' => round(max(0, $billed - $paidOnThose), 2),
            'modes' => self::collectedByMode($clinicId, $from, $to),
            'tax_label' => (string) ($row['tax_label'] ?? '') ?: 'GST',
        ];
    }

    /** Money actually received in the range (payments table). */
    public static function collected(int $clinicId, string $from, string $to): float
    {
        if (!Database::ping()) {
            return 0.0;
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT COALESCE(SUM(amount), 0) AS c FROM payments
                  WHERE clinic_id = ? AND DATE(paid_at) BETWEEN ? AND ?',
            );
            $stmt->execute([$clinicId, $from, $to]);

            return round((float) ($stmt->fetch()['c'] ?? 0), 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /** @return array<string, float> method => amount received */
    public static function collectedByMode(int $clinicId, string $from, string $to): array
    {
        if (!Database::ping()) {
            return [];
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT method, COALESCE(SUM(amount), 0) AS c FROM payments
                  WHERE clinic_id = ? AND DATE(paid_at) BETWEEN ? AND ?
                  GROUP BY method ORDER BY c DESC',
            );
            $stmt->execute([$clinicId, $from, $to]);
            $out = [];
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $out[(string) ($row['method'] ?? 'cash')] = round((float) $row['c'], 2);
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * GST split per rate — what a CA needs: taxable value and tax collected at
     * each percentage in use. Empty when the clinic bills no tax.
     *
     * @return list<array{percent: float, taxable: float, tax: float, invoices: int}>
     */
    public static function gstBreakdown(int $clinicId, string $from, string $to): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT tax_percent,
                    COUNT(*) AS invoices,
                    COALESCE(SUM(subtotal - COALESCE(discount_amount, 0)), 0) AS taxable,
                    COALESCE(SUM(tax_amount), 0) AS tax
               FROM invoices
              WHERE clinic_id = ? AND status != 'cancelled'
                    AND COALESCE(tax_amount, 0) > 0
                    AND DATE(created_at) BETWEEN ? AND ?
              GROUP BY tax_percent
              ORDER BY tax_percent ASC",
        );
        $stmt->execute([$clinicId, $from, $to]);

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'percent' => (float) $row['tax_percent'],
                'invoices' => (int) $row['invoices'],
                'taxable' => round((float) $row['taxable'], 2),
                'tax' => round((float) $row['tax'], 2),
            ];
        }

        return $rows;
    }

    /**
     * Invoice-level rows for the range.
     *
     * @return list<array<string, mixed>>
     */
    public static function invoices(int $clinicId, string $from, string $to, int $limit = 500): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT i.*, p.name AS patient_name, p.uhid, u.name AS doctor_name
               FROM invoices i
               INNER JOIN patients p ON p.id = i.patient_id
               LEFT JOIN users u ON u.id = i.attributed_doctor_id
              WHERE i.clinic_id = ? AND i.status != 'cancelled'
                    AND DATE(i.created_at) BETWEEN ? AND ?
              ORDER BY i.created_at DESC, i.id DESC
              LIMIT " . (int) $limit,
        );
        $stmt->execute([$clinicId, $from, $to]);

        return $stmt->fetchAll() ?: [];
    }

    /** Day-by-day totals for the range (billed vs collected). */
    public static function daily(int $clinicId, string $from, string $to): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS d,
                    COUNT(*) AS invoices,
                    COALESCE(SUM(total), 0) AS billed,
                    COALESCE(SUM(tax_amount), 0) AS tax
               FROM invoices
              WHERE clinic_id = ? AND status != 'cancelled'
                    AND DATE(created_at) BETWEEN ? AND ?
              GROUP BY DATE(created_at)
              ORDER BY d DESC",
        );
        $stmt->execute([$clinicId, $from, $to]);

        return $stmt->fetchAll() ?: [];
    }
}
