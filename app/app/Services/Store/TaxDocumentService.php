<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * GST documents for the marketplace.
 *
 *  - The SELLER is the seller of record: each package gets one tax invoice in the
 *    seller's name/GSTIN, issued when the package leaves (courier booked, picked up,
 *    marked shipped, or the seller prints it — whichever comes first).
 *  - Delivery charges are eClinicPro's own service: a separate eClinicPro invoice
 *    per package that paid shipping (needs the platform GSTIN in settings).
 *  - Any refund AFTER an invoice exists (cancel after dispatch, return, RTO, lost,
 *    missing item) creates a credit note pointing at that invoice, for exactly the
 *    refunded units, at the invoice's HSN/rate/tax split. Refunds before the invoice
 *    need no document.
 *  - Invoice value = what the SELLER sells for (price − seller-funded discount).
 *    A platform-funded coupon doesn't reduce the seller's invoice (VERIFY WITH CA).
 *  - Place of supply = delivery state: same state as the seller → CGST+SGST, else IGST.
 *  - Numbers: I{vendor}-{fy}-{n} / C{vendor}-{fy}-{n} for sellers,
 *    ECP-D{fy}-{n} / ECP-C{fy}-{n} for eClinicPro — ≤ 16 chars (rule 46), gap-free per FY.
 *
 * Documents are never edited or deleted.
 */
final class TaxDocumentService
{
    /** "2627" for any date from 1 Apr 2026 to 31 Mar 2027. */
    public static function fy(int $ts): string
    {
        $y = (int) date('Y', $ts);
        $start = (int) date('n', $ts) >= 4 ? $y : $y - 1;

        return substr((string) $start, 2, 2) . substr((string) ($start + 1), 2, 2);
    }

    /** eClinicPro's own invoicing details are filled in (needed for delivery-charge invoices). */
    public static function platformReady(): bool
    {
        return StoreSettings::get('store_platform_legal_name') !== ''
            && GstStates::validGstin(StoreSettings::get('store_platform_gstin'))
            && StoreSettings::get('store_platform_address') !== '';
    }

    /**
     * Issue the package's documents if not issued yet. Never throws (called from
     * shipping webhooks/crons); problems are logged.
     */
    public static function issueForPackage(int $vendorOrderId): void
    {
        try {
            self::issueInvoice($vendorOrderId);
        } catch (\Throwable $e) {
            error_log('[TaxDocs] invoice for package ' . $vendorOrderId . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * The seller's tax invoice for a package (+ eClinicPro's delivery invoice).
     * Idempotent: returns the existing invoice id if already issued.
     */
    public static function issueInvoice(int $vendorOrderId): ?int
    {
        $pdo = Database::connection();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            $st = $pdo->prepare('SELECT * FROM store_vendor_orders WHERE id = :id FOR UPDATE');
            $st->execute(['id' => $vendorOrderId]);
            $vo = $st->fetch();
            if (!$vo || in_array($vo['status'], ['pending_payment', 'new', 'accepted'], true)) {
                if ($own) {
                    $pdo->rollBack();
                }

                return null;   // not dispatched yet
            }
            $existing = self::find('vendor', 'invoice', $vendorOrderId);
            if ($existing !== null) {
                if ($own) {
                    $pdo->commit();
                }

                return (int) $existing['id'];
            }
            $order = QueryBuilder::table('store_orders')->where('id', '=', (int) $vo['order_id'])->first();
            $vendor = QueryBuilder::table('store_vendors')->where('id', '=', (int) $vo['vendor_id'])->first();
            if ($order === null || $vendor === null) {
                throw new \RuntimeException('order/vendor missing');
            }
            $st = $pdo->prepare('SELECT * FROM store_order_items WHERE vendor_order_id = :v ORDER BY id');
            $st->execute(['v' => $vendorOrderId]);
            $items = $st->fetchAll();

            $seller = self::sellerParty($vendor, $vo);
            $buyer = self::buyerParty($order);
            $pos = $buyer['state_code'];
            $intra = $seller['state_code'] !== null && $seller['state_code'] === $pos;
            $billOfSupply = empty($vendor['gstin']);

            $lines = [];
            foreach ($items as $it) {
                $units = (int) $it['qty'] - (int) $it['qty_cancelled'];
                if ($units <= 0) {
                    continue;   // cancelled before dispatch: never invoiced
                }
                $gross = PricingService::unitShare(PricingService::sellerRevenue($it), (int) $it['qty'], (int) $it['qty_cancelled'], $units);
                $bp = $billOfSupply ? 0 : (int) $it['gst_bp'];
                $lines[] = [
                    'order_item_id' => (int) $it['id'], 'kind' => 'goods',
                    'description' => mb_substr($it['name'] . ($it['variant_title'] ? ' — ' . $it['variant_title'] : '') . ' (SKU ' . $it['sku'] . ')', 0, 300),
                    'hsn_sac' => $it['hsn_code'] ?: null, 'qty' => $units, 'unit_from' => (int) $it['qty_cancelled'], 'gst_bp' => $bp,
                ] + self::split($gross, $bp, $intra);
            }
            if (!$lines) {
                if ($own) {
                    $pdo->rollBack();
                }

                return null;   // everything was cancelled
            }
            $id = self::insert([
                'doc_type' => 'invoice', 'issuer' => 'vendor', 'vendor_id' => (int) $vo['vendor_id'],
                'order_id' => (int) $vo['order_id'], 'vendor_order_id' => $vendorOrderId,
                'is_bill_of_supply' => $billOfSupply ? 1 : 0, 'supply_type' => $intra ? 'intra' : 'inter', 'place_of_supply' => $pos,
                'issuer_json' => $seller, 'buyer_json' => $buyer, 'dedupe_key' => 'inv:vo:' . $vendorOrderId,
            ], $lines);

            // eClinicPro's delivery-charge invoice for this package.
            $ship = (int) $vo['shipping_paise'];
            if ($ship > 0 && self::platformReady()) {
                $platform = self::platformParty();
                $pIntra = $platform['state_code'] === $pos;
                $bp = StoreSettings::int('store_delivery_gst_bp', 1800);
                self::insert([
                    'doc_type' => 'invoice', 'issuer' => 'platform', 'vendor_id' => (int) $vo['vendor_id'],
                    'order_id' => (int) $vo['order_id'], 'vendor_order_id' => $vendorOrderId,
                    'is_bill_of_supply' => 0, 'supply_type' => $pIntra ? 'intra' : 'inter', 'place_of_supply' => $pos,
                    'issuer_json' => $platform, 'buyer_json' => $buyer, 'dedupe_key' => 'pinv:vo:' . $vendorOrderId,
                ], [[
                    'order_item_id' => null, 'kind' => 'delivery',
                    'description' => 'Delivery charges: package ' . $vo['sub_order_no'] . ' (sold by ' . ($vendor['legal_name'] ?: $vendor['display_name']) . ')',
                    'hsn_sac' => StoreSettings::get('store_delivery_sac', '996812'), 'qty' => 1, 'unit_from' => 0, 'gst_bp' => $bp,
                ] + self::split($ship, $bp, $pIntra)]);
            } elseif ($ship > 0) {
                error_log('[TaxDocs] delivery charge on ' . $vo['sub_order_no'] . ' not invoiced: fill eClinicPro GST details in store settings');
            }
            if ($own) {
                $pdo->commit();
            }

            return $id;
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Cron safety net: dispatched packages that somehow have no invoice yet. @return int issued */
    public static function issueMissing(int $limit = 50): int
    {
        try {
            $st = Database::connection()->prepare(
                "SELECT vo.id FROM store_vendor_orders vo
                  WHERE vo.status IN ('ready_to_ship','shipped','delivered','completed')
                    AND NOT EXISTS (SELECT 1 FROM store_tax_documents d WHERE d.vendor_order_id = vo.id AND d.issuer = 'vendor' AND d.doc_type = 'invoice')
                  ORDER BY vo.id LIMIT " . max(1, min(500, $limit))
            );
            $st->execute();
            $n = 0;
            foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $voId) {
                try {
                    if (self::issueInvoice((int) $voId) !== null) {
                        $n++;
                    }
                } catch (\Throwable $e) {
                    error_log('[TaxDocs::issueMissing] package ' . $voId . ': ' . $e->getMessage());
                }
            }

            return $n;
        } catch (\Throwable) {
            return 0;   // patch not imported yet
        }
    }

    /**
     * Credit notes for a refund that was just recorded (store_refunds + store_refund_items).
     * Runs INSIDE the caller's transaction so the refund and its credit note commit together.
     * Only units that were on an invoice are credited; pre-dispatch cancellations need nothing.
     */
    public static function creditForRefund(int $refundId, string $reason): void
    {
        $pdo = Database::connection();
        $st = $pdo->prepare(
            'SELECT ri.qty AS rqty, ri.shipping_paise AS rship, oi.*
               FROM store_refund_items ri JOIN store_order_items oi ON oi.id = ri.order_item_id
              WHERE ri.refund_id = :r ORDER BY oi.id'
        );
        $st->execute(['r' => $refundId]);
        $byVo = [];
        foreach ($st->fetchAll() as $row) {
            $byVo[(int) $row['vendor_order_id']][] = $row;
        }
        foreach ($byVo as $voId => $rows) {
            $inv = self::find('vendor', 'invoice', $voId);
            if ($inv !== null) {
                $invLines = [];
                foreach (self::lines((int) $inv['id']) as $l) {
                    $invLines[(int) $l['order_item_id']] = $l;
                }
                $credited = self::creditedUnits((int) $inv['id']);
                $intra = $inv['supply_type'] === 'intra';
                $lines = [];
                foreach ($rows as $it) {
                    $il = $invLines[(int) $it['id']] ?? null;
                    if ($il === null) {
                        continue;
                    }
                    $done = $credited[(int) $it['id']] ?? 0;
                    $q = min((int) $it['rqty'], (int) $il['qty'] - $done);
                    if ($q <= 0) {
                        continue;
                    }
                    $gross = PricingService::unitShare(PricingService::sellerRevenue($it), (int) $it['qty'], (int) $il['unit_from'] + $done, $q);
                    $lines[] = [
                        'order_item_id' => (int) $it['id'], 'kind' => 'goods', 'description' => $il['description'],
                        'hsn_sac' => $il['hsn_sac'], 'qty' => $q, 'unit_from' => (int) $il['unit_from'] + $done, 'gst_bp' => (int) $il['gst_bp'],
                    ] + self::split($gross, (int) $il['gst_bp'], $intra);
                }
                if ($lines) {
                    self::insert([
                        'doc_type' => 'credit_note', 'issuer' => 'vendor', 'vendor_id' => (int) $inv['vendor_id'],
                        'order_id' => (int) $inv['order_id'], 'vendor_order_id' => $voId, 'refers_to_id' => (int) $inv['id'],
                        'refund_id' => $refundId, 'reason' => $reason, 'is_bill_of_supply' => (int) $inv['is_bill_of_supply'],
                        'supply_type' => $inv['supply_type'], 'place_of_supply' => $inv['place_of_supply'],
                        'issuer_json' => json_decode((string) $inv['issuer_json'], true), 'buyer_json' => json_decode((string) $inv['buyer_json'], true),
                        'dedupe_key' => 'cn:r' . $refundId . ':vo' . $voId,
                    ], $lines);
                }
            }

            // Delivery charge refunded → eClinicPro credit note against its delivery invoice.
            $ship = array_sum(array_map(static fn ($r) => (int) $r['rship'], $rows));
            $pinv = $ship > 0 ? self::find('platform', 'invoice', $voId) : null;
            if ($pinv !== null) {
                $already = self::creditedTotal((int) $pinv['id']);
                $amt = min($ship, (int) $pinv['total_paise'] - $already);
                if ($amt > 0) {
                    $pl = self::lines((int) $pinv['id'])[0];
                    self::insert([
                        'doc_type' => 'credit_note', 'issuer' => 'platform', 'vendor_id' => (int) $pinv['vendor_id'],
                        'order_id' => (int) $pinv['order_id'], 'vendor_order_id' => $voId, 'refers_to_id' => (int) $pinv['id'],
                        'refund_id' => $refundId, 'reason' => $reason, 'is_bill_of_supply' => 0,
                        'supply_type' => $pinv['supply_type'], 'place_of_supply' => $pinv['place_of_supply'],
                        'issuer_json' => json_decode((string) $pinv['issuer_json'], true), 'buyer_json' => json_decode((string) $pinv['buyer_json'], true),
                        'dedupe_key' => 'pcn:r' . $refundId . ':vo' . $voId,
                    ], [[
                        'order_item_id' => null, 'kind' => 'delivery', 'description' => $pl['description'], 'hsn_sac' => $pl['hsn_sac'],
                        'qty' => 1, 'unit_from' => 0, 'gst_bp' => (int) $pl['gst_bp'],
                    ] + self::split($amt, (int) $pl['gst_bp'], $pinv['supply_type'] === 'intra')]);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> documents on an order (optionally one seller's), oldest first */
    public static function forOrder(int $orderId, ?int $vendorId = null): array
    {
        try {
            $sql = 'SELECT * FROM store_tax_documents WHERE order_id = :o' . ($vendorId !== null ? ' AND vendor_id = :v' : '') . ' ORDER BY id';
            $st = Database::connection()->prepare($sql);
            $st->execute(['o' => $orderId] + ($vendorId !== null ? ['v' => $vendorId] : []));

            return $st->fetchAll();
        } catch (\Throwable) {
            return [];   // patch not imported yet
        }
    }

    /** Full document with lines and decoded parties, or null. */
    public static function load(int $id): ?array
    {
        $doc = QueryBuilder::table('store_tax_documents')->where('id', '=', $id)->first();
        if ($doc === null) {
            return null;
        }
        $doc['lines'] = self::lines($id);
        $doc['issuer_party'] = json_decode((string) $doc['issuer_json'], true) ?: [];
        $doc['buyer_party'] = json_decode((string) $doc['buyer_json'], true) ?: [];
        $doc['refers_to'] = $doc['refers_to_id'] ? QueryBuilder::table('store_tax_documents')->where('id', '=', (int) $doc['refers_to_id'])->first() : null;
        $o = QueryBuilder::table('store_orders')->where('id', '=', (int) $doc['order_id'])->first();
        $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', (int) $doc['vendor_order_id'])->first();
        $doc['order_no'] = $o['order_no'] ?? '';
        $doc['sub_order_no'] = $vo['sub_order_no'] ?? '';

        return $doc;
    }

    public static function title(array $doc): string
    {
        if ($doc['doc_type'] === 'credit_note') {
            return 'Credit note';
        }

        return (int) $doc['is_bill_of_supply'] === 1 ? 'Bill of supply' : 'Tax invoice';
    }

    /**
     * GST register for a month: every document (credit notes negative) + HSN/rate summary.
     *
     * @return array{docs: list<array<string, mixed>>, hsn: list<array<string, mixed>>, totals: array<string, int>}
     */
    public static function register(string $month, ?int $vendorId, string $issuer): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $from = $month . '-01 00:00:00';
        $to = date('Y-m-d H:i:s', (int) strtotime($from . ' +1 month'));
        $where = 'd.issued_at >= :f AND d.issued_at < :t AND d.issuer = :i';
        $params = ['f' => $from, 't' => $to, 'i' => $issuer === 'platform' ? 'platform' : 'vendor'];
        if ($vendorId !== null) {
            $where .= ' AND d.vendor_id = :v';
            $params['v'] = $vendorId;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT d.*, o.order_no, sv.display_name AS vendor_name, r.doc_no AS refers_to_no
               FROM store_tax_documents d
               JOIN store_orders o ON o.id = d.order_id
               LEFT JOIN store_vendors sv ON sv.id = d.vendor_id
               LEFT JOIN store_tax_documents r ON r.id = d.refers_to_id
              WHERE $where ORDER BY d.issued_at, d.id"
        );
        $st->execute($params);
        $docs = $st->fetchAll();

        $st = $pdo->prepare(
            "SELECT l.hsn_sac, l.gst_bp,
                    SUM(IF(d.doc_type = 'credit_note', -l.qty, l.qty)) AS qty,
                    SUM(IF(d.doc_type = 'credit_note', -l.taxable_paise, l.taxable_paise)) AS taxable,
                    SUM(IF(d.doc_type = 'credit_note', -l.cgst_paise, l.cgst_paise)) AS cgst,
                    SUM(IF(d.doc_type = 'credit_note', -l.sgst_paise, l.sgst_paise)) AS sgst,
                    SUM(IF(d.doc_type = 'credit_note', -l.igst_paise, l.igst_paise)) AS igst,
                    SUM(IF(d.doc_type = 'credit_note', -l.total_paise, l.total_paise)) AS total
               FROM store_tax_document_lines l JOIN store_tax_documents d ON d.id = l.document_id
              WHERE $where GROUP BY l.hsn_sac, l.gst_bp ORDER BY l.hsn_sac, l.gst_bp"
        );
        $st->execute($params);
        $hsn = $st->fetchAll();

        $totals = ['invoices' => 0, 'credit_notes' => 0, 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total' => 0];
        foreach ($docs as $d) {
            $sign = $d['doc_type'] === 'credit_note' ? -1 : 1;
            $totals[$sign > 0 ? 'invoices' : 'credit_notes']++;
            foreach (['taxable', 'cgst', 'sgst', 'igst', 'total'] as $k) {
                $totals[$k] += $sign * (int) $d[$k . '_paise'];
            }
        }

        return ['docs' => $docs, 'hsn' => $hsn, 'totals' => $totals, 'month' => $month];
    }

    /** Line-level CSV of a month's register (for the CA / GSTR-1). */
    public static function registerCsv(string $month, ?int $vendorId, string $issuer): string
    {
        $reg = self::register($month, $vendorId, $issuer);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Date', 'Document', 'Type', 'Against invoice', 'Seller', 'Seller GSTIN', 'Order', 'Buyer state', 'Place of supply',
            'Supply', 'HSN/SAC', 'Description', 'Qty', 'GST %', 'Taxable', 'CGST', 'SGST', 'IGST', 'Total']);
        foreach ($reg['docs'] as $d) {
            $sign = $d['doc_type'] === 'credit_note' ? -1 : 1;
            $issuerP = json_decode((string) $d['issuer_json'], true) ?: [];
            $buyerP = json_decode((string) $d['buyer_json'], true) ?: [];
            foreach (self::lines((int) $d['id']) as $l) {
                fputcsv($out, [
                    date('d-m-Y', (int) strtotime((string) $d['issued_at'])), $d['doc_no'], self::title($d), $d['refers_to_no'] ?? '',
                    $issuerP['legal_name'] ?? '', $issuerP['gstin'] ?? '', $d['order_no'], $buyerP['state'] ?? '',
                    ($d['place_of_supply'] ?? '') . ' ' . GstStates::name($d['place_of_supply']), $d['supply_type'] === 'intra' ? 'Intra-state' : 'Inter-state',
                    $l['hsn_sac'] ?? '', $l['description'], $sign * (int) $l['qty'], (int) $l['gst_bp'] / 100,
                    self::rs($sign * (int) $l['taxable_paise']), self::rs($sign * (int) $l['cgst_paise']),
                    self::rs($sign * (int) $l['sgst_paise']), self::rs($sign * (int) $l['igst_paise']), self::rs($sign * (int) $l['total_paise']),
                ]);
            }
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Split a GST-inclusive amount.
     *
     * @return array{taxable_paise: int, cgst_paise: int, sgst_paise: int, igst_paise: int, total_paise: int}
     */
    private static function split(int $gross, int $bp, bool $intra): array
    {
        $taxable = intdiv($gross * 10000 + intdiv(10000 + $bp, 2), 10000 + $bp);
        $tax = $gross - $taxable;
        $cgst = $intra ? intdiv($tax, 2) : 0;

        return [
            'taxable_paise' => $taxable,
            'cgst_paise' => $cgst,
            'sgst_paise' => $intra ? $tax - $cgst : 0,
            'igst_paise' => $intra ? 0 : $tax,
            'total_paise' => $gross,
        ];
    }

    /** @param list<array<string, mixed>> $lines */
    private static function insert(array $doc, array $lines): int
    {
        $pdo = Database::connection();
        $ts = time();
        $fy = self::fy($ts);
        $issuerKey = $doc['issuer'] === 'platform' ? 'p' : 'v:' . (int) $doc['vendor_id'];
        $pdo->prepare('INSERT IGNORE INTO store_tax_doc_sequences (issuer_key, doc_type, fy, last_seq) VALUES (:k, :t, :f, 0)')
            ->execute(['k' => $issuerKey, 't' => $doc['doc_type'], 'f' => $fy]);
        $st = $pdo->prepare('SELECT last_seq FROM store_tax_doc_sequences WHERE issuer_key = :k AND doc_type = :t AND fy = :f FOR UPDATE');
        $st->execute(['k' => $issuerKey, 't' => $doc['doc_type'], 'f' => $fy]);
        $seq = (int) $st->fetchColumn() + 1;
        $pdo->prepare('UPDATE store_tax_doc_sequences SET last_seq = :s WHERE issuer_key = :k AND doc_type = :t AND fy = :f')
            ->execute(['s' => $seq, 'k' => $issuerKey, 't' => $doc['doc_type'], 'f' => $fy]);
        $prefix = $doc['issuer'] === 'platform'
            ? ($doc['doc_type'] === 'invoice' ? 'ECP-D' : 'ECP-C')
            : ($doc['doc_type'] === 'invoice' ? 'I' : 'C') . (int) $doc['vendor_id'] . '-';
        $docNo = $prefix . $fy . '-' . $seq;

        $sum = ['taxable_paise' => 0, 'cgst_paise' => 0, 'sgst_paise' => 0, 'igst_paise' => 0, 'total_paise' => 0];
        foreach ($lines as $l) {
            foreach ($sum as $k => $_) {
                $sum[$k] += (int) $l[$k];
            }
        }
        $id = QueryBuilder::table('store_tax_documents')->insert([
            'doc_type' => $doc['doc_type'], 'issuer' => $doc['issuer'], 'vendor_id' => $doc['vendor_id'] ?? null,
            'doc_no' => $docNo, 'fy' => $fy, 'seq' => $seq,
            'order_id' => $doc['order_id'], 'vendor_order_id' => $doc['vendor_order_id'],
            'refers_to_id' => $doc['refers_to_id'] ?? null, 'refund_id' => $doc['refund_id'] ?? null, 'reason' => $doc['reason'] ?? null,
            'is_bill_of_supply' => $doc['is_bill_of_supply'], 'supply_type' => $doc['supply_type'], 'place_of_supply' => $doc['place_of_supply'],
            'issuer_json' => json_encode($doc['issuer_json'], JSON_UNESCAPED_UNICODE),
            'buyer_json' => json_encode($doc['buyer_json'], JSON_UNESCAPED_UNICODE),
            'dedupe_key' => $doc['dedupe_key'], 'issued_at' => date('Y-m-d H:i:s', $ts),
        ] + $sum);
        $ins = $pdo->prepare(
            'INSERT INTO store_tax_document_lines
                (document_id, order_item_id, kind, description, hsn_sac, qty, unit_from, gst_bp, taxable_paise, cgst_paise, sgst_paise, igst_paise, total_paise)
             VALUES (:d, :oi, :k, :ds, :h, :q, :uf, :bp, :tx, :cg, :sg, :ig, :tt)'
        );
        foreach ($lines as $l) {
            $ins->execute([
                'd' => $id, 'oi' => $l['order_item_id'], 'k' => $l['kind'], 'ds' => $l['description'], 'h' => $l['hsn_sac'],
                'q' => $l['qty'], 'uf' => $l['unit_from'], 'bp' => $l['gst_bp'], 'tx' => $l['taxable_paise'], 'cg' => $l['cgst_paise'],
                'sg' => $l['sgst_paise'], 'ig' => $l['igst_paise'], 'tt' => $l['total_paise'],
            ]);
        }

        return (int) $id;
    }

    private static function find(string $issuer, string $type, int $vendorOrderId): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT * FROM store_tax_documents WHERE issuer = :i AND doc_type = :t AND vendor_order_id = :v ORDER BY id LIMIT 1'
        );
        $st->execute(['i' => $issuer, 't' => $type, 'v' => $vendorOrderId]);

        return $st->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    private static function lines(int $documentId): array
    {
        $st = Database::connection()->prepare('SELECT * FROM store_tax_document_lines WHERE document_id = :d ORDER BY id');
        $st->execute(['d' => $documentId]);

        return $st->fetchAll();
    }

    /** @return array<int, int> order_item_id => units already credited against this invoice */
    private static function creditedUnits(int $invoiceId): array
    {
        $st = Database::connection()->prepare(
            "SELECT l.order_item_id, SUM(l.qty) AS q FROM store_tax_document_lines l
               JOIN store_tax_documents d ON d.id = l.document_id
              WHERE d.refers_to_id = :i AND d.doc_type = 'credit_note' AND l.order_item_id IS NOT NULL GROUP BY l.order_item_id"
        );
        $st->execute(['i' => $invoiceId]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['order_item_id']] = (int) $r['q'];
        }

        return $out;
    }

    private static function creditedTotal(int $invoiceId): int
    {
        $st = Database::connection()->prepare("SELECT COALESCE(SUM(total_paise), 0) FROM store_tax_documents WHERE refers_to_id = :i AND doc_type = 'credit_note'");
        $st->execute(['i' => $invoiceId]);

        return (int) $st->fetchColumn();
    }

    /** Seller block: registered address if they gave one, else the pickup address used. */
    private static function sellerParty(array $vendor, array $vo): array
    {
        $st = Database::connection()->prepare(
            "SELECT * FROM store_vendor_addresses WHERE vendor_id = :v AND type = 'registered' AND is_active = 1 ORDER BY is_default DESC, id DESC LIMIT 1"
        );
        $st->execute(['v' => (int) $vendor['id']]);
        $addr = $st->fetch() ?: (json_decode((string) ($vo['pickup_snapshot_json'] ?? ''), true) ?: []);
        $stateCode = GstStates::fromGstin($vendor['gstin'] ?? null)
            ?? (($addr['state_code'] ?? '') !== '' ? (string) $addr['state_code'] : GstStates::codeFor($addr['state'] ?? null));

        return [
            'legal_name' => (string) ($vendor['legal_name'] ?: $vendor['display_name']),
            'trade_name' => (string) $vendor['display_name'],
            'gstin' => (string) ($vendor['gstin'] ?? ''),
            'address' => self::addrLine($addr),
            'state' => GstStates::name($stateCode) ?: (string) ($addr['state'] ?? ''),
            'state_code' => $stateCode,
        ];
    }

    private static function platformParty(): array
    {
        $gstin = strtoupper(StoreSettings::get('store_platform_gstin'));
        $code = GstStates::fromGstin($gstin);

        return [
            'legal_name' => StoreSettings::get('store_platform_legal_name'),
            'trade_name' => 'eClinicPro Store',
            'gstin' => $gstin,
            'address' => StoreSettings::get('store_platform_address'),
            'state' => GstStates::name($code),
            'state_code' => $code,
        ];
    }

    private static function buyerParty(array $order): array
    {
        $a = json_decode((string) $order['ship_address_json'], true) ?: [];
        $code = GstStates::codeFor($a['state'] ?? null);

        return [
            'name' => (string) ($a['name'] ?? $order['contact_name']),
            'address' => self::addrLine($a),
            'state' => GstStates::name($code) ?: (string) ($a['state'] ?? ''),
            'state_code' => $code,
        ];
    }

    private static function addrLine(array $a): string
    {
        return implode(', ', array_filter([
            $a['line1'] ?? '', $a['line2'] ?? '', $a['landmark'] ?? '', $a['city'] ?? '',
            trim(($a['state'] ?? '') . ' ' . ($a['pincode'] ?? '')),
        ], static fn ($v) => trim((string) $v) !== ''));
    }

    private static function rs(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }
}
