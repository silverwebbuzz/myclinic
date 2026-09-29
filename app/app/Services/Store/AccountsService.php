<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Monthly accounts for eClinicPro's CA, in marketplace terms:
 *   - customers' money is collected ON BEHALF OF sellers (a liability, not income);
 *   - eClinicPro's income = commission + delivery fees + courier/charges recovered from sellers;
 *   - eClinicPro's costs = courier bills, eClinicPro-funded coupons, compensation to sellers.
 *
 * Figures come from the same tables the store runs on (orders, refunds, ledger, payouts,
 * GST documents), for one calendar month. Payment-gateway fees are not tracked here
 * (take them from the Razorpay settlement report).
 */
final class AccountsService
{
    /**
     * @return array{period: string, sections: list<array{title: string, note: string, rows: list<array{label: string, amount: int, hint?: string, strong?: bool}>}>, sellers: list<array<string, mixed>>}
     */
    public static function month(string $period): array
    {
        if (!SellerInvoiceService::validPeriod($period)) {
            $period = date('Y-m');
        }
        [$from, $to] = SellerInvoiceService::range($period);
        $pdo = Database::connection();
        $one = static function (string $sql, array $p) use ($pdo): int {
            $st = $pdo->prepare($sql);
            $st->execute($p);

            return (int) $st->fetchColumn();
        };
        $range = ['f' => $from, 't' => $to];

        // ---- Money in / out (bank) ----
        $collected = $one('SELECT COALESCE(SUM(grand_total_paise), 0) FROM store_orders WHERE paid_at >= :f AND paid_at < :t', $range);
        $collectedDelivery = $one('SELECT COALESCE(SUM(shipping_paise), 0) FROM store_orders WHERE paid_at >= :f AND paid_at < :t', $range);
        $refunded = $one("SELECT COALESCE(SUM(amount_paise), 0) FROM store_refunds WHERE status <> 'failed' AND created_at >= :f AND created_at < :t", $range);
        $refundedDelivery = $one(
            "SELECT COALESCE(SUM(ri.shipping_paise), 0) FROM store_refund_items ri JOIN store_refunds r ON r.id = ri.refund_id
              WHERE r.status <> 'failed' AND r.created_at >= :f AND r.created_at < :t", $range
        );
        $paidOut = $one("SELECT COALESCE(SUM(net_paise), 0) FROM store_payouts WHERE status = 'paid' AND paid_at >= :f AND paid_at < :t", $range);

        // ---- Ledger (seller side), this month ----
        $led = static function (string $where) use ($one, $range): int {
            return $one("SELECT COALESCE(SUM(amount_paise), 0) FROM store_vendor_ledger WHERE created_at >= :f AND created_at < :t AND ($where)", $range);
        };
        $sales = $led("entry_type = 'sale_credit'");
        $salesReversed = -$led("entry_type = 'refund_reversal'");
        // Same classification as eClinicPro's monthly invoices to sellers, so the two always agree.
        $fig = SellerInvoiceService::figures(null, $period);
        $compensation = $led("entry_type = 'adjustment' AND dedupe_key LIKE 'lost:%'");
        $otherAdjustments = $led("entry_type = 'adjustment' AND dedupe_key NOT LIKE 'lost:%' AND dedupe_key NOT LIKE 'shipfix:%' AND dedupe_key NOT LIKE 'rev:%'");

        // ---- eClinicPro's own delivery-fee GST documents ----
        $del = $pdo->prepare(
            "SELECT COALESCE(SUM(IF(doc_type = 'credit_note', -taxable_paise, taxable_paise)), 0) AS taxable,
                    COALESCE(SUM(IF(doc_type = 'credit_note', -(cgst_paise + sgst_paise + igst_paise), cgst_paise + sgst_paise + igst_paise)), 0) AS gst
               FROM store_tax_documents WHERE issuer = 'platform' AND issued_at >= :f AND issued_at < :t"
        );
        $del->execute($range);
        $delivery = $del->fetch() ?: ['taxable' => 0, 'gst' => 0];

        // ---- Costs ----
        $platformCoupons = $one(
            'SELECT COALESCE(SUM(vo.platform_discount_paise), 0) FROM store_vendor_orders vo JOIN store_orders o ON o.id = vo.order_id
              WHERE o.paid_at >= :f AND o.paid_at < :t', $range
        );
        $courierCost = 0;
        try {
            $courierCost = $one(
                "SELECT COALESCE(SUM(courier_charge_paise), 0) FROM store_vendor_orders WHERE delivered_at >= :f AND delivered_at < :t", $range
            );
        } catch (\Throwable) {
            // patch 2026_10_04 not imported
        }

        // ---- Owed to sellers at month end (earned, not yet paid) ----
        $owed = $one('SELECT COALESCE(SUM(amount_paise), 0) FROM store_vendor_ledger WHERE created_at < :t', ['t' => $to])
            - $one("SELECT COALESCE(SUM(net_paise), 0) FROM store_payouts WHERE status = 'paid' AND paid_at < :t", ['t' => $to]);

        $sections = [
            [
                'title' => '1. Money in and out of the bank',
                'note' => 'Customers pay eClinicPro; most of it belongs to the sellers. Razorpay fees are not included (see the Razorpay settlement report).',
                'rows' => [
                    ['label' => 'Received from customers (orders paid this month)', 'amount' => $collected, 'hint' => 'of which delivery fees ' . self::rs($collectedDelivery)],
                    ['label' => 'Refunded to customers', 'amount' => -$refunded, 'hint' => $refundedDelivery > 0 ? 'of which delivery fees ' . self::rs($refundedDelivery) : null],
                    ['label' => 'Paid to sellers (payouts marked paid)', 'amount' => -$paidOut],
                    ['label' => 'Net movement', 'amount' => $collected - $refunded - $paidOut, 'strong' => true],
                ],
            ],
            [
                'title' => '2. eClinicPro\'s income (goes in your profit & loss)',
                'note' => 'Only these amounts are eClinicPro\'s income. The GST on them is payable by eClinicPro (declare in GSTR-1 / GSTR-3B).',
                'rows' => [
                    ['label' => 'Commission from sellers (before GST, net of returns)', 'amount' => $fig['commission']['taxable'], 'hint' => 'GST on it ' . self::rs($fig['commission']['gst'])],
                    ['label' => 'Delivery fees from customers (before GST)', 'amount' => (int) $delivery['taxable'], 'hint' => 'GST on it ' . self::rs((int) $delivery['gst'])],
                    ['label' => 'Courier & logistics recovered from sellers (before GST)', 'amount' => $fig['courier']['taxable'], 'hint' => 'GST on it ' . self::rs($fig['courier']['gst'])],
                    ['label' => 'Other charges to sellers (before GST)', 'amount' => $fig['other']['taxable'], 'hint' => 'GST on it ' . self::rs($fig['other']['gst'])],
                    ['label' => 'Total income (before GST)', 'amount' => $fig['commission']['taxable'] + (int) $delivery['taxable'] + $fig['courier']['taxable'] + $fig['other']['taxable'], 'strong' => true],
                    ['label' => 'GST collected on this income (payable)', 'amount' => $fig['commission']['gst'] + (int) $delivery['gst'] + $fig['courier']['gst'] + $fig['other']['gst']],
                ],
            ],
            [
                'title' => '3. eClinicPro\'s costs',
                'note' => 'Courier cost is what was recorded per delivered package; match it with Shiprocket\'s invoices (their GST is your input credit).',
                'rows' => [
                    ['label' => 'Courier charges for packages delivered this month (incl. GST)', 'amount' => -$courierCost],
                    ['label' => 'Coupons funded by eClinicPro', 'amount' => -$platformCoupons],
                    ['label' => 'Compensation paid to sellers (lost / damaged in transit)', 'amount' => -$compensation],
                    ['label' => 'Other adjustments to sellers (bonus / corrections)', 'amount' => -$otherAdjustments],
                ],
            ],
            [
                'title' => '4. Sellers\' money',
                'note' => 'Sales belong to the sellers (their invoices, their GST). eClinicPro only holds the money until payout.',
                'rows' => [
                    ['label' => 'Sellers\' sales delivered this month (incl. their GST)', 'amount' => $sales],
                    ['label' => 'Less: returned after delivery', 'amount' => -$salesReversed],
                    ['label' => 'Owed to sellers at month end (earned, not yet paid)', 'amount' => $owed, 'strong' => true,
                        'hint' => 'Plus the money for paid orders not yet delivered, which becomes theirs on delivery.'],
                ],
            ],
        ];

        return ['period' => $period, 'sections' => $sections, 'sellers' => self::sellers($from, $to)];
    }

    /** Per-seller view of the month. @return list<array<string, mixed>> */
    private static function sellers(string $from, string $to): array
    {
        $st = Database::connection()->prepare(
            "SELECT v.id, v.display_name, v.gstin,
                    SUM(CASE WHEN l.entry_type IN ('sale_credit','refund_reversal') THEN l.amount_paise ELSE 0 END) AS sales,
                    -SUM(CASE WHEN l.entry_type = 'commission_debit' THEN l.amount_paise ELSE 0 END) AS commission,
                    -SUM(CASE WHEN l.entry_type = 'commission_gst_debit' THEN l.amount_paise ELSE 0 END) AS commission_gst,
                    -SUM(CASE WHEN l.entry_type IN ('shipping_debit','rto_charge','penalty') THEN l.amount_paise ELSE 0 END) AS charges,
                    SUM(CASE WHEN l.entry_type = 'adjustment' THEN l.amount_paise ELSE 0 END) AS adjustments,
                    SUM(l.amount_paise) AS net
               FROM store_vendor_ledger l JOIN store_vendors v ON v.id = l.vendor_id
              WHERE l.created_at >= :f AND l.created_at < :t
              GROUP BY v.id, v.display_name, v.gstin ORDER BY v.display_name"
        );
        $st->execute(['f' => $from, 't' => $to]);

        return $st->fetchAll();
    }

    /** One CSV with the summary and the per-seller table (for the CA). */
    public static function csv(string $period): string
    {
        $m = self::month($period);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['eClinicPro Store accounts', SellerInvoiceService::monthName($m['period'])]);
        fputcsv($out, ['All amounts in INR. eClinicPro is the e-commerce operator (marketplace): customer payments are collected on behalf of sellers.']);
        foreach ($m['sections'] as $sec) {
            fputcsv($out, []);
            fputcsv($out, [$sec['title']]);
            foreach ($sec['rows'] as $r) {
                fputcsv($out, [$r['label'], self::num($r['amount']), $r['hint'] ?? '']);
            }
        }
        fputcsv($out, []);
        fputcsv($out, ['Per seller (ledger this month)']);
        fputcsv($out, ['Seller', 'GSTIN', 'Sales (incl. GST, net of returns)', 'Commission', 'GST on commission', 'Courier & charges (incl. GST)', 'Adjustments', 'Net earned this month']);
        foreach ($m['sellers'] as $s) {
            fputcsv($out, [$s['display_name'], $s['gstin'] ?? '', self::num((int) $s['sales']), self::num((int) $s['commission']), self::num((int) $s['commission_gst']),
                self::num((int) $s['charges']), self::num((int) $s['adjustments']), self::num((int) $s['net'])]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Every ledger line of the month (detail for the CA). */
    public static function ledgerCsv(string $period): string
    {
        [$from, $to] = SellerInvoiceService::range(SellerInvoiceService::validPeriod($period) ? $period : date('Y-m'));
        $st = Database::connection()->prepare(
            'SELECT l.created_at, v.display_name, vo.sub_order_no, l.entry_type, l.memo, l.amount_paise, l.status, p.payout_no
               FROM store_vendor_ledger l JOIN store_vendors v ON v.id = l.vendor_id
               LEFT JOIN store_vendor_orders vo ON vo.id = l.vendor_order_id
               LEFT JOIN store_payouts p ON p.id = l.payout_id
              WHERE l.created_at >= :f AND l.created_at < :t ORDER BY l.created_at, l.id'
        );
        $st->execute(['f' => $from, 't' => $to]);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Date', 'Seller', 'Package', 'Type', 'Description', 'Amount (INR, + owed to seller / − deducted)', 'Status', 'Payout']);
        foreach ($st->fetchAll() as $r) {
            fputcsv($out, [substr((string) $r['created_at'], 0, 10), $r['display_name'], $r['sub_order_no'] ?? '', $r['entry_type'], $r['memo'] ?? '',
                self::num((int) $r['amount_paise']), $r['status'], $r['payout_no'] ?? '']);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public static function rs(int $paise): string
    {
        return ($paise < 0 ? '−₹' : '₹') . number_format(abs($paise) / 100, 2);
    }

    private static function num(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }
}
