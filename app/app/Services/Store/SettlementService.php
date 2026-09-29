<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Seller money: an APPEND-ONLY ledger (store_vendor_ledger) + payout batches (store_payouts).
 *
 *   package delivered   → ledger: +sale  −commission  −GST on commission   (status pending, available_at = settle_after)
 *   return window over  → pending → available   (package → completed)
 *   admin payout batch  → available → in_payout (one store_payouts row per seller, net of everything, incl. negatives)
 *   marked paid (UTR)   → in_payout → paid
 *   payout failed/cancelled → back to available
 *
 * Every entry has a UNIQUE dedupe_key, so writers can run any number of times.
 * Amounts are never edited; corrections are new 'adjustment' rows.
 * Money reaches sellers ONLY for delivered packages, after the return window.
 */
final class SettlementService
{
    // ------------------------------------------------------------------
    // Ledger writers
    // ------------------------------------------------------------------

    /** Package delivered: book the seller's earnings as PENDING until the return window ends. */
    public static function recordDelivered(int $vendorOrderId): void
    {
        $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', $vendorOrderId)->first();
        if ($vo === null) {
            return;
        }
        // Seller revenue on the units actually delivered (cancelled units excluded;
        // seller-funded coupon discounts deducted, platform-funded ones not).
        $st = Database::connection()->prepare('SELECT * FROM store_order_items WHERE vendor_order_id = :v');
        $st->execute(['v' => $vendorOrderId]);
        $sale = 0;
        foreach ($st->fetchAll() as $it) {
            $rev = PricingService::sellerRevenue($it);
            $sale += $rev - PricingService::unitShare($rev, (int) $it['qty'], 0, (int) $it['qty_cancelled']);
        }
        $commission = max(0, (int) $vo['commission_paise']);
        $commissionGst = (int) round($commission * CommissionService::COMMISSION_GST_BP / 10000);
        $availableAt = $vo['settle_after'] ?? date('Y-m-d H:i:s', time() + 7 * 86400);
        $vid = (int) $vo['vendor_id'];

        self::entry($vid, $vendorOrderId, 'sale_credit', $sale, "sale:$vendorOrderId", 'Sale ' . $vo['sub_order_no'], $availableAt);
        self::entry($vid, $vendorOrderId, 'commission_debit', -$commission, "commission:$vendorOrderId", 'Commission ' . $vo['sub_order_no'], $availableAt);
        self::entry($vid, $vendorOrderId, 'commission_gst_debit', -$commissionGst, "commission_gst:$vendorOrderId", 'GST on commission ' . $vo['sub_order_no'], $availableAt);
        // TCS / TDS: columns exist; entries start once the CA confirms rates (plan D2).
        Database::connection()->prepare("UPDATE store_vendor_orders SET settlement_status = 'on_hold' WHERE id = :id AND settlement_status = 'unsettled'")
            ->execute(['id' => $vendorOrderId]);
    }

    /** Admin adjustment (bonus, penalty, correction). Positive = seller gets more. */
    public static function adjust(int $vendorId, int $amountPaise, string $memo, int $adminId): array
    {
        if ($amountPaise === 0 || trim($memo) === '') {
            return ['ok' => false, 'error' => 'Enter a non-zero amount and a note.'];
        }
        self::entry($vendorId, null, $amountPaise < 0 ? 'penalty' : 'adjustment', $amountPaise,
            'adj:' . $vendorId . ':' . bin2hex(random_bytes(6)), mb_substr(trim($memo), 0, 255), date('Y-m-d H:i:s'), 'available', 'admin', $adminId);
        StoreAudit::log('ledger.adjust', 'vendor', $vendorId, null, ['amount' => $amountPaise, 'memo' => $memo]);

        return ['ok' => true];
    }

    /**
     * Return windows that have ended: pending → available, package → completed.
     * Runs from the maintenance worker and lazily from payout screens.
     */
    public static function releaseMatured(): int
    {
        $pdo = Database::connection();
        try {
            // A package with an open return stays frozen until the return is settled.
            $openReturn = "SELECT r.vendor_order_id FROM store_returns r
                            WHERE r.status IN ('" . implode("','", ReturnService::OPEN) . "')";
            $n = $pdo->exec(
                "UPDATE store_vendor_ledger SET status = 'available'
                  WHERE status = 'pending' AND available_at IS NOT NULL AND available_at <= NOW()
                    AND (vendor_order_id IS NULL OR vendor_order_id NOT IN ($openReturn))"
            );
            $st = $pdo->query(
                "SELECT id, order_id FROM store_vendor_orders
                  WHERE status = 'delivered' AND settle_after IS NOT NULL AND settle_after <= NOW()
                    AND id NOT IN ($openReturn)"
            );
            foreach ($st->fetchAll() as $vo) {
                $pdo->prepare("UPDATE store_vendor_orders SET status = 'completed', settlement_status = 'eligible' WHERE id = :id AND status = 'delivered'")
                    ->execute(['id' => (int) $vo['id']]);
                OrderService::history((int) $vo['order_id'], (int) $vo['id'], 'vendor_order', (int) $vo['id'], 'delivered', 'completed', 'system', null, 'Return window closed');
                OrderService::recomputeStatus((int) $vo['order_id']);
            }

            return (int) $n;
        } catch (\Throwable $e) {
            error_log('[Settlement::releaseMatured] ' . $e->getMessage());

            return 0;
        }
    }

    // ------------------------------------------------------------------
    // Balances
    // ------------------------------------------------------------------

    /** @return array{pending: int, available: int, in_payout: int, paid: int} */
    public static function balances(int $vendorId): array
    {
        $out = ['pending' => 0, 'available' => 0, 'in_payout' => 0, 'paid' => 0];
        $st = Database::connection()->prepare('SELECT status, COALESCE(SUM(amount_paise), 0) s FROM store_vendor_ledger WHERE vendor_id = :v GROUP BY status');
        $st->execute(['v' => $vendorId]);
        foreach ($st->fetchAll() as $r) {
            if (isset($out[$r['status']])) {
                $out[$r['status']] = (int) $r['s'];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function ledger(int $vendorId, int $limit = 100, ?int $payoutId = null): array
    {
        $sql = 'SELECT l.*, vo.sub_order_no FROM store_vendor_ledger l
                  LEFT JOIN store_vendor_orders vo ON vo.id = l.vendor_order_id
                 WHERE l.vendor_id = :v' . ($payoutId !== null ? ' AND l.payout_id = :p' : '') . '
                 ORDER BY l.id DESC LIMIT ' . max(1, min(2000, $limit));
        $st = Database::connection()->prepare($sql);
        $params = ['v' => $vendorId];
        if ($payoutId !== null) {
            $params['p'] = $payoutId;
        }
        $st->execute($params);

        return $st->fetchAll();
    }

    /** Every seller with money in the ledger, for the admin overview. @return list<array<string, mixed>> */
    public static function allBalances(): array
    {
        return Database::connection()->query(
            "SELECT v.id, v.display_name, v.status AS vendor_status,
                    SUM(CASE WHEN l.status = 'pending' THEN l.amount_paise ELSE 0 END) AS pending,
                    SUM(CASE WHEN l.status = 'available' THEN l.amount_paise ELSE 0 END) AS available,
                    SUM(CASE WHEN l.status = 'in_payout' THEN l.amount_paise ELSE 0 END) AS in_payout,
                    SUM(CASE WHEN l.status = 'paid' THEN l.amount_paise ELSE 0 END) AS paid,
                    (SELECT b.status FROM store_vendor_bank_accounts b WHERE b.vendor_id = v.id AND b.is_primary = 1 LIMIT 1) AS bank_status
               FROM store_vendors v JOIN store_vendor_ledger l ON l.vendor_id = v.id
              GROUP BY v.id, v.display_name, v.status
              ORDER BY available DESC"
        )->fetchAll();
    }

    // ------------------------------------------------------------------
    // Payouts
    // ------------------------------------------------------------------

    /**
     * One draft payout per seller whose available balance ≥ minimum, with a
     * verified bank account and an approved (not suspended) account.
     *
     * @return array{created: int, skipped: list<string>}
     */
    public static function createBatch(int $adminId): array
    {
        self::releaseMatured();
        $min = StoreSettings::int('store_min_payout_paise', 10000);
        $created = 0;
        $skipped = [];
        foreach (self::allBalances() as $b) {
            $net = (int) $b['available'];
            if ($net <= 0) {
                continue;
            }
            $name = (string) $b['display_name'];
            if ($net < $min) {
                $skipped[] = "$name: ₹" . ProductService::rupees($net) . ' is below the minimum payout';
                continue;
            }
            if ($b['vendor_status'] !== 'approved') {
                $skipped[] = "$name: seller is {$b['vendor_status']} (payout on hold)";
                continue;
            }
            if ($b['bank_status'] !== 'verified') {
                $skipped[] = "$name: bank account not verified";
                continue;
            }
            $res = self::createForVendor((int) $b['id'], $adminId);
            if ($res['ok']) {
                $created++;
            } else {
                $skipped[] = "$name: " . ($res['error'] ?? 'failed');
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Seller clicked "Request payout": same checks as the admin batch, then a draft
     * payout for the whole available balance. Admin approves, pays from the bank and
     * marks it paid (UTR), or declines it (cancel), which returns the money to available.
     *
     * @return array{ok: bool, error?: string, payout_id?: int}
     */
    public static function requestPayout(int $vendorId, int $vendorUserId, string $note): array
    {
        self::releaseMatured();
        $vendor = VendorService::find($vendorId);
        if ($vendor === null || $vendor['status'] !== 'approved') {
            return ['ok' => false, 'error' => 'Your seller account must be active to request a payout.'];
        }
        $bank = VendorService::primaryBank($vendorId);
        if ($bank === null || ($bank['status'] ?? '') !== 'verified') {
            return ['ok' => false, 'error' => 'Your bank account must be verified before you can request a payout.'];
        }
        $open = Database::connection()->prepare("SELECT COUNT(*) FROM store_payouts WHERE vendor_id = :v AND status IN ('draft','approved','processing')");
        $open->execute(['v' => $vendorId]);
        if ((int) $open->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'You already have a payout in progress. You can request again once it is paid.'];
        }
        $available = (int) self::balances($vendorId)['available'];
        $min = StoreSettings::int('store_min_payout_paise', 10000);
        if ($available < $min) {
            return ['ok' => false, 'error' => 'The minimum payout is ₹' . ProductService::rupees($min) . '. Your available balance is ₹' . ProductService::rupees(max(0, $available)) . '.'];
        }
        $res = self::createForVendor($vendorId, 0);
        if (!$res['ok']) {
            return $res;
        }
        try {
            QueryBuilder::table('store_payout_requests')->insert([
                'payout_id' => (int) $res['payout_id'],
                'vendor_id' => $vendorId,
                'vendor_user_id' => $vendorUserId,
                'note' => mb_substr(trim($note), 0, 500) ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('[Settlement::requestPayout] ' . $e->getMessage());   // patch 2026_10_03 missing: payout still created
        }
        StoreAudit::log('payout.request', 'payout', (int) $res['payout_id'], null, ['vendor_id' => $vendorId, 'by' => $vendorUserId]);
        StoreNotifier::payoutRequested((int) $res['payout_id']);

        return $res;
    }

    /** @return array{vendor_user_id: ?int, note: ?string, decline_reason: ?string, requested_at: string}|null */
    public static function requestFor(int $payoutId): ?array
    {
        try {
            return QueryBuilder::table('store_payout_requests')->where('payout_id', '=', $payoutId)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{ok: bool, error?: string, payout_id?: int} */
    public static function createForVendor(int $vendorId, int $adminId): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("SELECT id, amount_paise, created_at FROM store_vendor_ledger WHERE vendor_id = :v AND status = 'available' FOR UPDATE");
            $st->execute(['v' => $vendorId]);
            $rows = $st->fetchAll();
            $gross = $deductions = 0;
            foreach ($rows as $r) {
                $amt = (int) $r['amount_paise'];
                if ($amt >= 0) {
                    $gross += $amt;
                } else {
                    $deductions += -$amt;
                }
            }
            $net = $gross - $deductions;
            if (!$rows || $net <= 0) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Nothing payable.'];
            }
            $bank = VendorService::primaryBank($vendorId);
            $dates = array_column($rows, 'created_at');
            $payoutId = QueryBuilder::table('store_payouts')->insert([
                'payout_no' => 'PO' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'vendor_id' => $vendorId,
                'period_from' => substr((string) min($dates), 0, 10),
                'period_to' => substr((string) max($dates), 0, 10),
                'gross_paise' => $gross,
                'deductions_paise' => $deductions,
                'net_paise' => $net,
                'status' => 'draft',
                'method' => 'manual_bank',
                'bank_last4' => $bank['account_last4'] ?? null,
                'bank_ifsc' => $bank['ifsc'] ?? null,
            ]);
            $ids = implode(',', array_map(static fn ($r) => (int) $r['id'], $rows));
            $pdo->exec("UPDATE store_vendor_ledger SET status = 'in_payout', payout_id = $payoutId WHERE id IN ($ids)");
            $pdo->prepare(
                "UPDATE store_vendor_orders SET settlement_status = 'in_payout'
                  WHERE id IN (SELECT DISTINCT vendor_order_id FROM store_vendor_ledger WHERE payout_id = :p AND vendor_order_id IS NOT NULL)"
            )->execute(['p' => $payoutId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Settlement::createForVendor] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not create the payout.'];
        }
        StoreAudit::log('payout.create', 'payout', $payoutId, null, ['vendor_id' => $vendorId, 'net' => $net]);

        return ['ok' => true, 'payout_id' => $payoutId];
    }

    /**
     * draft → approved → paid (with UTR). failed / cancelled release the entries back to available.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function transition(int $payoutId, string $action, string $reference, string $note, int $adminId): array
    {
        $p = QueryBuilder::table('store_payouts')->where('id', '=', $payoutId)->first();
        if ($p === null) {
            return ['ok' => false, 'error' => 'Payout not found.'];
        }
        $from = (string) $p['status'];
        $allowed = [
            'approve' => [['draft'], 'approved'],
            'paid' => [['approved', 'processing'], 'paid'],
            'failed' => [['approved', 'processing'], 'failed'],
            'cancel' => [['draft', 'approved'], 'cancelled'],
        ];
        if (!isset($allowed[$action]) || !in_array($from, $allowed[$action][0], true)) {
            return ['ok' => false, 'error' => "Can't $action a payout that is $from."];
        }
        $to = $allowed[$action][1];
        if ($to === 'paid' && trim($reference) === '') {
            return ['ok' => false, 'error' => 'Enter the bank UTR / transfer reference.'];
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $upd = ['status' => $to];
            if ($to === 'approved') {
                $upd['approved_by'] = $adminId;
                $upd['approved_at'] = date('Y-m-d H:i:s');
            } elseif ($to === 'paid') {
                $upd['reference'] = mb_substr(trim($reference), 0, 80);
                $upd['paid_at'] = date('Y-m-d H:i:s');
            } elseif ($to === 'failed') {
                $upd['failure_reason'] = mb_substr(trim($note) ?: 'Transfer failed', 0, 500);
            }
            QueryBuilder::table('store_payouts')->where('id', '=', $payoutId)->update($upd);
            if ($to === 'paid') {
                $pdo->prepare("UPDATE store_vendor_ledger SET status = 'paid' WHERE payout_id = :p")->execute(['p' => $payoutId]);
                $pdo->prepare(
                    "UPDATE store_vendor_orders SET settlement_status = 'paid'
                      WHERE id IN (SELECT DISTINCT vendor_order_id FROM store_vendor_ledger WHERE payout_id = :p AND vendor_order_id IS NOT NULL)"
                )->execute(['p' => $payoutId]);
            } elseif (in_array($to, ['failed', 'cancelled'], true)) {
                $pdo->prepare(
                    "UPDATE store_vendor_orders SET settlement_status = 'eligible'
                      WHERE id IN (SELECT DISTINCT vendor_order_id FROM store_vendor_ledger WHERE payout_id = :p AND vendor_order_id IS NOT NULL)"
                )->execute(['p' => $payoutId]);
                $pdo->prepare("UPDATE store_vendor_ledger SET status = 'available', payout_id = NULL WHERE payout_id = :p")->execute(['p' => $payoutId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Settlement::transition] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not update the payout.'];
        }
        StoreAudit::log('payout.' . $action, 'payout', $payoutId, ['status' => $from], $upd);
        if ($to === 'paid') {
            StoreNotifier::payoutPaid($payoutId);
        } elseif ($to === 'failed') {
            StoreNotifier::payoutNotPaid($payoutId, 'failed', trim($note) ?: 'Transfer failed');
        } elseif ($to === 'cancelled' && self::requestFor($payoutId) !== null) {
            // Cancelling a seller's REQUEST = declining it; the seller needs to know why.
            $reason = trim($note) ?: 'Please contact us for details.';
            try {
                QueryBuilder::table('store_payout_requests')->where('payout_id', '=', $payoutId)->update(['decline_reason' => mb_substr($reason, 0, 500)]);
            } catch (\Throwable) {
            }
            StoreNotifier::payoutNotPaid($payoutId, 'declined', $reason);
        }

        return ['ok' => true];
    }

    /** CSV statement for one payout (admin + the seller it belongs to). */
    public static function statementCsv(array $payout): string
    {
        $rows = self::ledger((int) $payout['vendor_id'], 2000, (int) $payout['id']);
        $f = fopen('php://temp', 'r+');
        fputcsv($f, ['Payout', $payout['payout_no'], 'Status', $payout['status'], 'UTR', $payout['reference'] ?? '']);
        fputcsv($f, ['Date', 'Package', 'Type', 'Description', 'Amount (INR)']);
        foreach (array_reverse($rows) as $r) {
            fputcsv($f, [substr((string) $r['created_at'], 0, 10), $r['sub_order_no'] ?? '', $r['entry_type'], $r['memo'] ?? '', number_format((int) $r['amount_paise'] / 100, 2, '.', '')]);
        }
        fputcsv($f, ['', '', '', 'Net payout', number_format((int) $payout['net_paise'] / 100, 2, '.', '')]);
        rewind($f);

        return (string) stream_get_contents($f);
    }

    /**
     * Monthly marketplace summary for admin: GMV, commission, refunds, payouts.
     *
     * @return list<array<string, mixed>>
     */
    public static function monthlyReport(int $months = 12): array
    {
        $months = max(1, min(36, $months));
        $rows = [];
        $pdo = Database::connection();
        $gmv = $pdo->query(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') m, COUNT(*) orders, SUM(grand_total_paise) collected, SUM(shipping_paise) shipping
               FROM store_orders WHERE paid_at IS NOT NULL AND paid_at >= DATE_SUB(CURDATE(), INTERVAL $months MONTH) GROUP BY m"
        )->fetchAll();
        foreach ($gmv as $r) {
            $rows[$r['m']] = ['month' => $r['m'], 'orders' => (int) $r['orders'], 'collected' => (int) $r['collected'], 'shipping' => (int) $r['shipping'],
                'refunded' => 0, 'commission' => 0, 'commission_gst' => 0, 'paid_out' => 0];
        }
        $blank = static fn (string $m) => ['month' => $m, 'orders' => 0, 'collected' => 0, 'shipping' => 0, 'refunded' => 0, 'commission' => 0, 'commission_gst' => 0, 'paid_out' => 0];
        foreach ($pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') m, SUM(amount_paise) s FROM store_refunds
              WHERE status <> 'failed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL $months MONTH) GROUP BY m"
        )->fetchAll() as $r) {
            $rows[$r['m']] ??= $blank($r['m']);
            $rows[$r['m']]['refunded'] = (int) $r['s'];
        }
        foreach ($pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') m,
                    -SUM(CASE WHEN entry_type = 'commission_debit' THEN amount_paise ELSE 0 END) c,
                    -SUM(CASE WHEN entry_type = 'commission_gst_debit' THEN amount_paise ELSE 0 END) g
               FROM store_vendor_ledger WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL $months MONTH) GROUP BY m"
        )->fetchAll() as $r) {
            $rows[$r['m']] ??= $blank($r['m']);
            $rows[$r['m']]['commission'] = (int) $r['c'];
            $rows[$r['m']]['commission_gst'] = (int) $r['g'];
        }
        foreach ($pdo->query(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') m, SUM(net_paise) s FROM store_payouts
              WHERE status = 'paid' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL $months MONTH) GROUP BY m"
        )->fetchAll() as $r) {
            $rows[$r['m']] ??= $blank($r['m']);
            $rows[$r['m']]['paid_out'] = (int) $r['s'];
        }
        krsort($rows);

        return array_values($rows);
    }

    // ------------------------------------------------------------------

    private static function entry(int $vendorId, ?int $vendorOrderId, string $type, int $amount, string $dedupe, string $memo,
        string $availableAt, string $status = 'pending', string $byType = 'system', ?int $byId = null): void
    {
        if ($amount === 0) {
            return;
        }
        Database::connection()->prepare(
            'INSERT IGNORE INTO store_vendor_ledger
                (vendor_id, vendor_order_id, entry_type, amount_paise, status, available_at, dedupe_key, memo, created_by_type, created_by_id)
             VALUES (:v, :vo, :t, :a, :s, :at, :k, :m, :bt, :bi)'
        )->execute([
            'v' => $vendorId, 'vo' => $vendorOrderId, 't' => $type, 'a' => $amount, 's' => $status, 'at' => $availableAt,
            'k' => $dedupe, 'm' => $memo, 'bt' => $byType, 'bi' => $byId,
        ]);
    }
}
