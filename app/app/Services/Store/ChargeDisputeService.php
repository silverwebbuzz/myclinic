<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Seller disputes a deduction (courier, weight, return pickup, failed delivery, other charge).
 *
 *   seller: "Dispute" on the order page, within store_charge_dispute_days of the charge
 *   → open: the charge is left out of payouts until decided; team emailed; reply promised
 *     within store_charge_dispute_response_days
 *   → admin accepts (SettlementService::reverseCharge credits it back) or rejects with a reason
 *   → seller emailed either way.
 */
final class ChargeDisputeService
{
    public const TYPES = ['shipping_debit', 'rto_charge', 'penalty'];

    private static ?bool $ready = null;

    /** False until 2026_10_08_store_seller_trust.sql is imported (callers then skip disputes). */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                Database::connection()->query('SELECT 1 FROM store_charge_disputes LIMIT 1');
                self::$ready = true;
            } catch (\Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public static function windowDays(): int
    {
        return max(1, StoreSettings::int('store_charge_dispute_days', 7));
    }

    /** @return array<int, array<string, mixed>> ledger_id => dispute, for one package */
    public static function forPackage(int $vendorOrderId): array
    {
        if (!self::ready()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM store_charge_disputes WHERE vendor_order_id = :v');
        $st->execute(['v' => $vendorOrderId]);
        $out = [];
        foreach ($st->fetchAll() as $d) {
            $out[(int) $d['ledger_id']] = $d;
        }

        return $out;
    }

    /** Can the seller still dispute this ledger row (from SellerFeeService::chargesFor)? */
    public static function canDispute(array $charge, array $disputes): bool
    {
        return self::ready()
            && (int) $charge['amount_paise'] < 0
            && in_array($charge['entry_type'], self::TYPES, true)
            && !isset($disputes[(int) $charge['id']])
            && strtotime((string) $charge['created_at']) >= time() - self::windowDays() * 86400;
    }

    /** @return array{ok: bool, error?: string} */
    public static function open(int $vendorId, int $vendorUserId, int $vendorOrderId, int $ledgerId, string $reason): array
    {
        $reason = mb_substr(trim($reason), 0, 1000);
        if (mb_strlen($reason) < 10) {
            return ['ok' => false, 'error' => 'Please explain why the charge is wrong (at least a sentence).'];
        }
        if (!self::ready()) {
            return ['ok' => false, 'error' => 'Disputes are not available yet. Please contact us.'];
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT * FROM store_vendor_ledger WHERE id = :id AND vendor_id = :v AND vendor_order_id = :vo');
        $st->execute(['id' => $ledgerId, 'v' => $vendorId, 'vo' => $vendorOrderId]);
        $charge = $st->fetch();
        if (!$charge || !self::canDispute($charge, self::forPackage($vendorOrderId))) {
            return ['ok' => false, 'error' => 'This charge can no longer be disputed (already disputed, or older than ' . self::windowDays() . ' days).'];
        }
        $respondBy = date('Y-m-d H:i:s', time() + max(1, StoreSettings::int('store_charge_dispute_response_days', 7)) * 86400);
        try {
            $pdo->prepare(
                'INSERT INTO store_charge_disputes (vendor_id, vendor_order_id, ledger_id, amount_paise, reason, created_by, respond_by)
                 VALUES (:v, :vo, :l, :a, :r, :u, :rb)'
            )->execute(['v' => $vendorId, 'vo' => $vendorOrderId, 'l' => $ledgerId, 'a' => -(int) $charge['amount_paise'],
                'r' => $reason, 'u' => $vendorUserId, 'rb' => $respondBy]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'This charge has already been disputed.'];
            }
            throw $e;
        }
        $id = (int) $pdo->lastInsertId();
        StoreAudit::log('dispute.open', 'vendor_order', $vendorOrderId, null, ['dispute' => $id, 'ledger_id' => $ledgerId]);
        StoreNotifier::chargeDisputed($id);

        return ['ok' => true];
    }

    /** One dispute with its charge memo and package number. */
    public static function find(int $id): ?array
    {
        if (!self::ready()) {
            return null;
        }
        $st = Database::connection()->prepare(
            'SELECT d.*, l.memo, l.entry_type, vo.sub_order_no, vo.order_id, v.display_name
               FROM store_charge_disputes d
               JOIN store_vendor_ledger l ON l.id = d.ledger_id
               JOIN store_vendor_orders vo ON vo.id = d.vendor_order_id
               JOIN store_vendors v ON v.id = d.vendor_id
              WHERE d.id = :id'
        );
        $st->execute(['id' => $id]);

        return $st->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function adminList(string $status): array
    {
        if (!self::ready()) {
            return [];
        }
        $where = in_array($status, ['open', 'accepted', 'rejected'], true) ? 'WHERE d.status = :s' : '';
        $st = Database::connection()->prepare(
            "SELECT d.*, l.memo, vo.sub_order_no, vo.order_id, v.display_name
               FROM store_charge_disputes d
               JOIN store_vendor_ledger l ON l.id = d.ledger_id
               JOIN store_vendor_orders vo ON vo.id = d.vendor_order_id
               JOIN store_vendors v ON v.id = d.vendor_id
               $where
              ORDER BY d.status = 'open' DESC, d.respond_by, d.id DESC LIMIT 200"
        );
        $st->execute($where !== '' ? ['s' => $status] : []);

        return $st->fetchAll();
    }

    public static function openCount(): int
    {
        if (!self::ready()) {
            return 0;
        }

        return (int) Database::connection()->query("SELECT COUNT(*) FROM store_charge_disputes WHERE status = 'open'")->fetchColumn();
    }

    /**
     * accept → the charge is reversed (credited back); reject → it stays. A reply is required.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function resolve(int $id, string $action, string $note, int $adminId): array
    {
        $d = self::find($id);
        $note = mb_substr(trim($note), 0, 1000);
        if ($d === null || $d['status'] !== 'open') {
            return ['ok' => false, 'error' => 'This dispute is already closed.'];
        }
        if (!in_array($action, ['accept', 'reject'], true)) {
            return ['ok' => false, 'error' => 'Choose accept or reject.'];
        }
        if ($note === '') {
            return ['ok' => false, 'error' => 'Write a reply for the seller (it is emailed to them).'];
        }
        if ($action === 'accept') {
            $rev = SettlementService::reverseCharge((int) $d['ledger_id'], (int) $d['vendor_order_id'], $adminId);
            if (!$rev['ok'] && !str_contains((string) ($rev['error'] ?? ''), 'already reversed')) {
                return $rev;
            }
        }
        Database::connection()->prepare(
            "UPDATE store_charge_disputes SET status = :s, resolution_note = :n, resolved_by = :a, resolved_at = NOW() WHERE id = :id AND status = 'open'"
        )->execute(['s' => $action === 'accept' ? 'accepted' : 'rejected', 'n' => $note, 'a' => $adminId, 'id' => $id]);
        StoreAudit::log('dispute.' . $action, 'vendor_order', (int) $d['vendor_order_id'], ['status' => 'open'], ['dispute' => $id, 'note' => $note]);
        StoreNotifier::disputeResolved($id);

        return ['ok' => true];
    }

    /** SQL fragment: ledger rows held back from payouts because their dispute is open. */
    public static function heldLedgerSql(): string
    {
        return self::ready() ? " AND id NOT IN (SELECT ledger_id FROM store_charge_disputes WHERE status = 'open')" : '';
    }
}
