<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Payout workflow. The partner requests a payout of their available balance;
 * an admin processes it (manually paying via UPI/bank) and moves the status
 * through processing -> paid (or rejects it). Target SLA: 7 days.
 */
final class PartnerPayoutService
{
    /**
     * Create a payout request for a partner's full available balance.
     * Locks the included commissions to this request.
     *
     * @return array{ok: bool, error?: string, request_id?: int}
     */
    public static function requestPayout(int $partnerId): array
    {
        $partner = PartnerService::find($partnerId);
        if ($partner === null) {
            return ['ok' => false, 'error' => 'Partner not found.'];
        }
        if (empty($partner['payout_method'])) {
            return ['ok' => false, 'error' => 'Add your payout (UPI/bank) details first.'];
        }

        $summary = PartnerService::earningsSummary($partnerId);
        $available = $summary['available'];
        $min = PartnerSettingsService::minPayoutAmount();

        if ($available < $min) {
            return ['ok' => false, 'error' => sprintf('Minimum payout is %.2f. Available: %.2f.', $min, $available)];
        }

        // Block a second request while one is still open.
        $open = QueryBuilder::table('partner_payout_requests')
            ->where('partner_id', '=', $partnerId)
            ->where('status', '=', 'requested')
            ->first();
        if ($open !== null) {
            return ['ok' => false, 'error' => 'You already have a payout request in progress.'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $requestId = QueryBuilder::table('partner_payout_requests')->insert([
                'partner_id' => $partnerId,
                'amount' => $available,
                'currency' => 'INR',
                'status' => 'requested',
                'payout_method' => $partner['payout_method'],
            ]);

            // Attach all currently-available approved commissions to this request.
            $stmt = $pdo->prepare(
                'UPDATE partner_commissions
                 SET payout_request_id = :rid
                 WHERE partner_id = :pid AND status = \'approved\' AND payout_request_id IS NULL'
            );
            $stmt->execute(['rid' => $requestId, 'pid' => $partnerId]);

            $pdo->commit();

            return ['ok' => true, 'request_id' => $requestId];
        } catch (\Throwable $e) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'Could not create payout request.'];
        }
    }

    /**
     * Admin updates a payout request status. When marked paid, the attached
     * commissions are flipped to `paid`. When rejected, they're released back
     * to the available pool.
     */
    public static function updateStatus(int $requestId, string $status, int $adminId, ?string $reference = null, ?string $note = null): bool
    {
        if (!in_array($status, ['processing', 'paid', 'rejected'], true)) {
            return false;
        }

        $req = QueryBuilder::table('partner_payout_requests')->where('id', '=', $requestId)->first();
        if ($req === null) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            QueryBuilder::table('partner_payout_requests')->where('id', '=', $requestId)->update([
                'status' => $status,
                'payment_reference' => $reference,
                'admin_note' => $note,
                'processed_by' => $adminId,
                'processed_at' => date('Y-m-d H:i:s'),
            ]);

            if ($status === 'paid') {
                $stmt = $pdo->prepare(
                    'UPDATE partner_commissions SET status = \'paid\' WHERE payout_request_id = :rid'
                );
                $stmt->execute(['rid' => $requestId]);
            } elseif ($status === 'rejected') {
                // Release commissions back to available.
                $stmt = $pdo->prepare(
                    'UPDATE partner_commissions SET payout_request_id = NULL WHERE payout_request_id = :rid'
                );
                $stmt->execute(['rid' => $requestId]);
            }

            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function forPartner(int $partnerId): array
    {
        return QueryBuilder::table('partner_payout_requests')
            ->where('partner_id', '=', $partnerId)
            ->orderBy('requested_at', 'DESC')
            ->get();
    }

    /** Admin queue: all payout requests with partner info, newest first. */
    public static function queue(?string $status = null): array
    {
        $pdo = Database::connection();
        $sql =
            'SELECT pr.*, p.name AS partner_name, p.email AS partner_email,
                    p.upi_id, p.bank_account_name, p.bank_account_no, p.bank_ifsc
             FROM partner_payout_requests pr
             JOIN partners p ON p.id = pr.partner_id';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE pr.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY pr.requested_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }
}
