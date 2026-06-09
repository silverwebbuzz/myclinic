<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * The heart of the partner program: turn a *paid* clinic subscription into a
 * commission ledger entry for the referring partner.
 *
 * Called from the genuinely-paid conversion sites only (never on trials or
 * the free plan). The first paid invoice for a clinic is an `initial`
 * commission; every later paid invoice (a yearly renewal creates a new
 * saas_invoice row) is a `renewal` commission, recorded only when
 * commission_on_renewals is enabled.
 */
final class PartnerCommissionService
{
    /**
     * Record commission for a paid subscription, if the clinic was referred.
     *
     * @param int        $clinicId      the paying tenant
     * @param float      $baseAmount    amount actually paid (commission base)
     * @param string     $currency      ISO currency, defaults INR
     * @param int|null   $saasInvoiceId the saas_invoices row, when one exists
     * @param string|null $reference    external ref (e.g. razorpay id) when no invoice row
     *
     * @return int|null the commission id, or null when nothing was recorded
     */
    public static function recordPaidConversion(
        int $clinicId,
        float $baseAmount,
        string $currency = 'INR',
        ?int $saasInvoiceId = null,
        ?string $reference = null
    ): ?int {
        if ($clinicId < 1 || $baseAmount <= 0) {
            return null;
        }

        $referral = PartnerReferralService::forTenant($clinicId);
        if ($referral === null) {
            return null; // clinic wasn't referred by a partner
        }

        $partner = PartnerService::find((int) $referral['partner_id']);
        if ($partner === null || ($partner['status'] ?? '') !== 'active') {
            return null; // suspended/rejected partners don't accrue
        }

        // Idempotency: never double-count the same saas invoice.
        if ($saasInvoiceId !== null) {
            $dupe = QueryBuilder::table('partner_commissions')
                ->where('saas_invoice_id', '=', $saasInvoiceId)
                ->first();
            if ($dupe !== null) {
                return null;
            }
        }

        // Has this clinic ever produced a commission? Decides initial vs renewal.
        $priorCount = self::commissionCountForReferral((int) $referral['id']);
        $type = $priorCount === 0 ? 'initial' : 'renewal';

        if ($type === 'renewal' && !PartnerSettingsService::commissionOnRenewals()) {
            return null;
        }

        $percent = PartnerService::effectivePercent($partner);
        $commission = round($baseAmount * $percent / 100, 2);
        if ($commission <= 0) {
            return null;
        }

        $id = QueryBuilder::table('partner_commissions')->insert([
            'partner_id' => (int) $partner['id'],
            'referral_id' => (int) $referral['id'],
            'tenant_id' => $clinicId,
            'saas_invoice_id' => $saasInvoiceId,
            'source' => 'subscription',
            'reference' => $reference,
            'base_amount' => $baseAmount,
            'commission_percent' => $percent,
            'commission_amount' => $commission,
            'currency' => $currency,
            'type' => $type,
            'status' => 'pending',
        ]);

        // First paid conversion flips the referral pending -> converted.
        if ($type === 'initial') {
            QueryBuilder::table('partner_referrals')
                ->where('id', '=', (int) $referral['id'])
                ->update(['status' => 'converted', 'converted_at' => date('Y-m-d H:i:s')]);
        }

        return $id;
    }

    /**
     * Promote commissions past their clearance window from pending -> approved.
     * Intended to be called from a daily worker/cron.
     *
     * @return int number of commissions approved
     */
    public static function approveCleared(): int
    {
        $days = PartnerSettingsService::clearanceDays();
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE partner_commissions
             SET status = \'approved\', approved_at = NOW()
             WHERE status = \'pending\'
               AND earned_at <= (NOW() - INTERVAL :days DAY)'
        );
        $stmt->execute(['days' => $days]);

        return $stmt->rowCount();
    }

    private static function commissionCountForReferral(int $referralId): int
    {
        return QueryBuilder::table('partner_commissions')
            ->where('referral_id', '=', $referralId)
            ->count();
    }

    /** @return list<array<string, mixed>> */
    public static function ledgerForPartner(int $partnerId, int $limit = 100): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT c.*, t.name AS clinic_name
             FROM partner_commissions c
             JOIN tenants t ON t.id = c.tenant_id
             WHERE c.partner_id = :pid
             ORDER BY c.earned_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('pid', $partnerId, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }
}
