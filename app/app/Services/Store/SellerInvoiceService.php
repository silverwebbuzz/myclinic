<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * eClinicPro's monthly GST invoice TO each seller, for eClinicPro's services to them:
 *   - commission (taxable value + 18% GST, exactly as deducted in the ledger)
 *   - courier & logistics recovered from the seller (forward courier, weight disputes,
 *     return pickups, seller-caused failed deliveries; amounts include 18% GST)
 *   - other charges under the seller rules
 *
 * Built from store_vendor_ledger entries dated in the month, net of reversals
 * (returned items give back commission; reversed charges). Net positive lines go on
 * one invoice; net negative lines on one credit note. Issued once per seller per month
 * (dedupe key), after the month has ended. VERIFY WITH CA: SAC codes, monthly netting.
 */
final class SellerInvoiceService
{
    public const GST_BP = 1800;

    /** Previous month's invoices, once (maintenance worker). @return int documents issued */
    public static function issueDue(): int
    {
        $period = date('Y-m', (int) strtotime(date('Y-m-01') . ' -1 month'));
        try {
            if (StoreSettings::get('store_seller_invoices_done') === $period || !TaxDocumentService::platformReady()) {
                return 0;
            }
            $res = self::issueMonth($period);
            if (!$res['errors']) {
                StoreSettings::set('store_seller_invoices_done', $period);
            }

            return $res['issued'];
        } catch (\Throwable $e) {
            error_log('[SellerInvoice::issueDue] ' . $e->getMessage());   // patch not imported yet

            return 0;
        }
    }

    /**
     * Issue every seller's documents for a finished month (idempotent).
     *
     * @return array{issued: int, errors: list<string>}
     */
    public static function issueMonth(string $period): array
    {
        if (!self::validPeriod($period) || $period >= date('Y-m')) {
            return ['issued' => 0, 'errors' => ['Invoices can be issued only for a month that has ended.']];
        }
        if (!TaxDocumentService::platformReady()) {
            return ['issued' => 0, 'errors' => ['Fill in eClinicPro\'s legal name, GSTIN and address in Store settings → Invoicing first.']];
        }
        [$from, $to] = self::range($period);
        $st = Database::connection()->prepare('SELECT DISTINCT vendor_id FROM store_vendor_ledger WHERE created_at >= :f AND created_at < :t');
        $st->execute(['f' => $from, 't' => $to]);
        $issued = 0;
        $errors = [];
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $vid) {
            try {
                $r = self::issueFor((int) $vid, $period);
                $issued += ($r['invoice'] !== null ? 1 : 0) + ($r['credit'] !== null ? 1 : 0);
            } catch (\Throwable $e) {
                error_log('[SellerInvoice] seller ' . $vid . ' ' . $period . ': ' . $e->getMessage());
                $errors[] = 'Seller #' . $vid . ': ' . $e->getMessage();
            }
        }

        return ['issued' => $issued, 'errors' => $errors];
    }

    /**
     * One seller's invoice (+ credit note if needed) for a month. Already issued → returns the existing ids.
     *
     * @return array{invoice: ?int, credit: ?int}
     */
    public static function issueFor(int $vendorId, string $period): array
    {
        $vendor = QueryBuilder::table('store_vendors')->where('id', '=', $vendorId)->first();
        if ($vendor === null || !self::validPeriod($period)) {
            return ['invoice' => null, 'credit' => null];
        }
        $platform = self::platformParty();
        $seller = self::sellerParty($vendor);
        $intra = $platform['state_code'] !== null && $platform['state_code'] === $seller['state_code'];
        $plus = $minus = [];
        foreach (self::figures($vendorId, $period) as $kind => $f) {
            if ($f['taxable'] === 0 && $f['gst'] === 0) {
                continue;
            }
            $sign = $f['taxable'] + $f['gst'] >= 0 ? 1 : -1;
            $line = [
                'kind' => $kind, 'description' => $f['label'] . ' — ' . self::monthName($period), 'sac' => $f['sac'], 'gst_bp' => self::GST_BP,
            ] + self::taxSplit(abs($f['taxable']), abs($f['gst']), $intra);
            if ($sign > 0) {
                $plus[] = $line;
            } else {
                $minus[] = $line;
            }
        }
        $out = ['invoice' => null, 'credit' => null];
        $base = [
            'vendor_id' => $vendorId, 'period' => $period, 'supply_type' => $intra ? 'intra' : 'inter',
            'place_of_supply' => $seller['state_code'], 'issuer_json' => $platform, 'buyer_json' => $seller,
        ];
        if ($plus) {
            $out['invoice'] = self::insert($base + ['doc_type' => 'invoice', 'dedupe_key' => 'sinv:v' . $vendorId . ':' . $period], $plus);
        }
        if ($minus) {
            $prior = Database::connection()->prepare(
                "SELECT id FROM store_seller_invoices WHERE vendor_id = :v AND doc_type = 'invoice' AND period <= :p ORDER BY period DESC, id DESC LIMIT 1"
            );
            $prior->execute(['v' => $vendorId, 'p' => $period]);
            $out['credit'] = self::insert($base + ['doc_type' => 'credit_note', 'dedupe_key' => 'scn:v' . $vendorId . ':' . $period,
                'refers_to_id' => ($p = $prior->fetchColumn()) !== false ? (int) $p : null], $minus);
        }

        return $out;
    }

    /**
     * The month's net amounts per invoice line, from the ledger (one seller, or all when null). Positive = sellers were charged.
     *
     * @return array<string, array{label: string, sac: string, taxable: int, gst: int}>
     */
    public static function figures(?int $vendorId, string $period): array
    {
        [$from, $to] = self::range($period);
        $st = Database::connection()->prepare(
            "SELECT l.entry_type, l.amount_paise, l.dedupe_key, o.entry_type AS reversed_type
               FROM store_vendor_ledger l
               LEFT JOIN store_vendor_ledger o ON l.dedupe_key LIKE 'rev:%' AND o.id = CAST(SUBSTRING(l.dedupe_key, 5) AS UNSIGNED)
              WHERE l.created_at >= :f AND l.created_at < :t" . ($vendorId !== null ? ' AND l.vendor_id = :v' : '') . "
                AND l.entry_type IN ('commission_debit','commission_gst_debit','shipping_debit','rto_charge','penalty','adjustment')"
        );
        $st->execute(['f' => $from, 't' => $to] + ($vendorId !== null ? ['v' => $vendorId] : []));
        $commission = $commissionGst = $courier = $other = 0;   // charged to the seller, GST-inclusive for courier/other
        foreach ($st->fetchAll() as $r) {
            $charged = -(int) $r['amount_paise'];
            $type = (string) $r['entry_type'];
            if ($type === 'adjustment') {
                // Only reversals/corrections of charges are part of the supply; bonuses and compensation are not.
                $key = (string) $r['dedupe_key'];
                if (str_starts_with($key, 'shipfix:')) {
                    $type = 'shipping_debit';
                } elseif (str_starts_with($key, 'rev:') && $r['reversed_type'] !== null) {
                    $type = (string) $r['reversed_type'];
                } else {
                    continue;
                }
            }
            match ($type) {
                'commission_debit' => $commission += $charged,
                'commission_gst_debit' => $commissionGst += $charged,
                'shipping_debit', 'rto_charge' => $courier += $charged,
                'penalty' => $other += $charged,
                default => null,
            };
        }
        $gstOf = static fn (int $gross): int => $gross - intdiv($gross * 10000 + intdiv(10000 + self::GST_BP, 2), 10000 + self::GST_BP);

        return [
            'commission' => ['label' => 'Marketplace commission on your sales', 'sac' => StoreSettings::get('store_commission_sac', '998599'),
                'taxable' => $commission, 'gst' => $commissionGst],
            'courier' => ['label' => 'Courier & logistics charges (delivery, return pickups, weight differences)', 'sac' => StoreSettings::get('store_delivery_sac', '996812'),
                'taxable' => $courier - $gstOf($courier), 'gst' => $gstOf($courier)],
            'other' => ['label' => 'Other charges under the seller rules', 'sac' => StoreSettings::get('store_other_charges_sac', '998599'),
                'taxable' => $other - $gstOf($other), 'gst' => $gstOf($other)],
        ];
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public static function listing(?string $period, ?int $vendorId, int $limit = 200): array
    {
        try {
            $where = [];
            $params = [];
            if ($period !== null && self::validPeriod($period)) {
                $where[] = 'i.period = :p';
                $params['p'] = $period;
            }
            if ($vendorId !== null) {
                $where[] = 'i.vendor_id = :v';
                $params['v'] = $vendorId;
            }
            $st = Database::connection()->prepare(
                'SELECT i.*, v.display_name AS vendor_name FROM store_seller_invoices i JOIN store_vendors v ON v.id = i.vendor_id'
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.period DESC, i.id DESC LIMIT ' . max(1, min(1000, $limit))
            );
            $st->execute($params);

            return $st->fetchAll();
        } catch (\Throwable) {
            return [];   // patch not imported yet
        }
    }

    /**
     * Document shaped for components/store_tax_document.php.
     *
     * @return array<string, mixed>|null
     */
    public static function load(int $id): ?array
    {
        $doc = QueryBuilder::table('store_seller_invoices')->where('id', '=', $id)->first();
        if ($doc === null) {
            return null;
        }
        $st = Database::connection()->prepare('SELECT * FROM store_seller_invoice_lines WHERE invoice_id = :i ORDER BY id');
        $st->execute(['i' => $id]);
        $doc['lines'] = array_map(static fn ($l) => $l + ['qty' => 1, 'hsn_sac' => $l['sac']], $st->fetchAll());
        $doc['issuer_party'] = json_decode((string) $doc['issuer_json'], true) ?: [];
        $doc['buyer_party'] = json_decode((string) $doc['buyer_json'], true) ?: [];
        $doc['refers_to'] = $doc['refers_to_id'] ? QueryBuilder::table('store_seller_invoices')->where('id', '=', (int) $doc['refers_to_id'])->first() : null;
        $doc['is_bill_of_supply'] = 0;
        $doc['issuer'] = 'platform';
        // Labels for the shared printable template.
        $doc['ref_line'] = 'For ' . self::monthName((string) $doc['period']) . ' (marketplace services)';
        $doc['issuer_label'] = 'Supplier (marketplace services)';
        $doc['buyer_label'] = 'Recipient (seller)';
        $doc['code_label'] = 'SAC';
        $doc['note_text'] = 'Services by eClinicPro to the seller for sales on eClinicPro Store in ' . self::monthName((string) $doc['period'])
            . '. These amounts were deducted from your payouts; the details are in your payout statements. Claim the GST as input tax credit in your GST return.'
            . ' Tax payable on reverse charge: No.';

        return $doc;
    }

    /** CSV of all seller invoices for a month (for eClinicPro's GSTR-1). */
    public static function csv(string $period): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Date', 'Document', 'Type', 'Against', 'Period', 'Seller', 'Seller GSTIN', 'Place of supply', 'Supply', 'SAC', 'Description',
            'Taxable', 'CGST', 'SGST', 'IGST', 'Total']);
        foreach (self::listing($period, null, 1000) as $d) {
            $doc = self::load((int) $d['id']);
            if ($doc === null) {
                continue;
            }
            $sign = $doc['doc_type'] === 'credit_note' ? -1 : 1;
            foreach ($doc['lines'] as $l) {
                fputcsv($out, [
                    date('d-m-Y', (int) strtotime((string) $doc['issued_at'])), $doc['doc_no'], $sign > 0 ? 'Tax invoice' : 'Credit note',
                    $doc['refers_to']['doc_no'] ?? '', $doc['period'], $doc['buyer_party']['legal_name'] ?? '', $doc['buyer_party']['gstin'] ?? '',
                    trim(($doc['place_of_supply'] ?? '') . ' ' . GstStates::name($doc['place_of_supply'])), $doc['supply_type'] === 'intra' ? 'Intra-state' : 'Inter-state',
                    $l['sac'], $l['description'],
                    self::rs($sign * (int) $l['taxable_paise']), self::rs($sign * (int) $l['cgst_paise']), self::rs($sign * (int) $l['sgst_paise']),
                    self::rs($sign * (int) $l['igst_paise']), self::rs($sign * (int) $l['total_paise']),
                ]);
            }
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public static function validPeriod(string $p): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p);
    }

    public static function monthName(string $period): string
    {
        return self::validPeriod($period) ? date('F Y', (int) strtotime($period . '-01')) : $period;
    }

    /** @return array{0: string, 1: string} [from, to) timestamps of a YYYY-MM period */
    public static function range(string $period): array
    {
        $from = $period . '-01 00:00:00';

        return [$from, date('Y-m-d H:i:s', (int) strtotime($from . ' +1 month'))];
    }

    // ------------------------------------------------------------------

    /** @return array{taxable_paise: int, cgst_paise: int, sgst_paise: int, igst_paise: int, total_paise: int} */
    private static function taxSplit(int $taxable, int $gst, bool $intra): array
    {
        $cgst = $intra ? intdiv($gst, 2) : 0;

        return [
            'taxable_paise' => $taxable, 'cgst_paise' => $cgst, 'sgst_paise' => $intra ? $gst - $cgst : 0,
            'igst_paise' => $intra ? 0 : $gst, 'total_paise' => $taxable + $gst,
        ];
    }

    /** @param list<array<string, mixed>> $lines */
    private static function insert(array $doc, array $lines): int
    {
        $existing = QueryBuilder::table('store_seller_invoices')->where('dedupe_key', '=', $doc['dedupe_key'])->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $ts = time();
            $fy = TaxDocumentService::fy($ts);
            $pdo->prepare("INSERT IGNORE INTO store_tax_doc_sequences (issuer_key, doc_type, fy, last_seq) VALUES ('ps', :t, :f, 0)")
                ->execute(['t' => $doc['doc_type'], 'f' => $fy]);
            $st = $pdo->prepare("SELECT last_seq FROM store_tax_doc_sequences WHERE issuer_key = 'ps' AND doc_type = :t AND fy = :f FOR UPDATE");
            $st->execute(['t' => $doc['doc_type'], 'f' => $fy]);
            $seq = (int) $st->fetchColumn() + 1;
            $pdo->prepare("UPDATE store_tax_doc_sequences SET last_seq = :s WHERE issuer_key = 'ps' AND doc_type = :t AND fy = :f")
                ->execute(['s' => $seq, 't' => $doc['doc_type'], 'f' => $fy]);
            $sum = ['taxable_paise' => 0, 'cgst_paise' => 0, 'sgst_paise' => 0, 'igst_paise' => 0, 'total_paise' => 0];
            foreach ($lines as $l) {
                foreach ($sum as $k => $_) {
                    $sum[$k] += (int) $l[$k];
                }
            }
            $id = (int) QueryBuilder::table('store_seller_invoices')->insert([
                'doc_type' => $doc['doc_type'],
                'doc_no' => ($doc['doc_type'] === 'invoice' ? 'ECP-S' : 'ECP-SC') . $fy . '-' . $seq,
                'fy' => $fy, 'seq' => $seq, 'vendor_id' => $doc['vendor_id'], 'period' => $doc['period'],
                'refers_to_id' => $doc['refers_to_id'] ?? null, 'supply_type' => $doc['supply_type'], 'place_of_supply' => $doc['place_of_supply'],
                'issuer_json' => json_encode($doc['issuer_json'], JSON_UNESCAPED_UNICODE),
                'buyer_json' => json_encode($doc['buyer_json'], JSON_UNESCAPED_UNICODE),
                'dedupe_key' => $doc['dedupe_key'], 'issued_at' => date('Y-m-d H:i:s', $ts),
            ] + $sum);
            $ins = $pdo->prepare(
                'INSERT INTO store_seller_invoice_lines (invoice_id, kind, description, sac, gst_bp, taxable_paise, cgst_paise, sgst_paise, igst_paise, total_paise)
                 VALUES (:i, :k, :d, :s, :bp, :tx, :cg, :sg, :ig, :tt)'
            );
            foreach ($lines as $l) {
                $ins->execute([
                    'i' => $id, 'k' => $l['kind'], 'd' => mb_substr((string) $l['description'], 0, 300), 's' => $l['sac'], 'bp' => $l['gst_bp'],
                    'tx' => $l['taxable_paise'], 'cg' => $l['cgst_paise'], 'sg' => $l['sgst_paise'], 'ig' => $l['igst_paise'], 'tt' => $l['total_paise'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        StoreAudit::log('seller_invoice.issue', 'vendor', (int) $doc['vendor_id'], null, ['period' => $doc['period'], 'type' => $doc['doc_type'], 'id' => $id]);

        return $id;
    }

    private static function platformParty(): array
    {
        $gstin = strtoupper(StoreSettings::get('store_platform_gstin'));
        $code = GstStates::fromGstin($gstin);

        return [
            'legal_name' => StoreSettings::get('store_platform_legal_name'), 'trade_name' => 'eClinicPro Store',
            'gstin' => $gstin, 'address' => StoreSettings::get('store_platform_address'), 'state' => GstStates::name($code), 'state_code' => $code,
        ];
    }

    /** The seller as the RECIPIENT: registered address, else pickup address; state from GSTIN. */
    private static function sellerParty(array $vendor): array
    {
        $st = Database::connection()->prepare(
            "SELECT * FROM store_vendor_addresses WHERE vendor_id = :v AND is_active = 1 AND type IN ('registered','pickup')
              ORDER BY type = 'registered' DESC, is_default DESC, id DESC LIMIT 1"
        );
        $st->execute(['v' => (int) $vendor['id']]);
        $a = $st->fetch() ?: [];
        $code = GstStates::fromGstin($vendor['gstin'] ?? null) ?? GstStates::codeFor($a['state'] ?? null);
        $addr = implode(', ', array_filter([$a['line1'] ?? '', $a['line2'] ?? '', $a['city'] ?? '', trim(($a['state'] ?? '') . ' ' . ($a['pincode'] ?? ''))],
            static fn ($v) => trim((string) $v) !== ''));

        return [
            'legal_name' => (string) ($vendor['legal_name'] ?: $vendor['display_name']), 'name' => (string) ($vendor['legal_name'] ?: $vendor['display_name']),
            'trade_name' => (string) $vendor['display_name'], 'gstin' => (string) ($vendor['gstin'] ?? ''),
            'address' => $addr, 'state' => GstStates::name($code) ?: (string) ($a['state'] ?? ''), 'state_code' => $code,
        ];
    }

    private static function rs(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }
}
