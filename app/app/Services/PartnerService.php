<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Partner accounts: registration, approval, referral-code generation, and
 * the aggregate earnings/balance figures shown on the partner dashboard.
 *
 * Partners are a platform-level entity — never tenant-scoped.
 */
final class PartnerService
{
    public static function findByEmail(string $email): ?array
    {
        return QueryBuilder::table('partners')
            ->where('email', '=', strtolower(trim($email)))
            ->first();
    }

    public static function find(int $id): ?array
    {
        return QueryBuilder::table('partners')->where('id', '=', $id)->first();
    }

    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        return QueryBuilder::table('partners')
            ->where('referral_code', '=', $code)
            ->first();
    }

    /**
     * Register a new partner in `pending` status with a unique referral code.
     *
     * @return array{id: int, referral_code: string}
     */
    public static function register(string $name, string $email, string $phone, string $password, string $country, string $city, string $state): array
    {
        $code = self::generateUniqueCode($name);

        $id = QueryBuilder::table('partners')->insert([
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'phone' => trim($phone),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'country_code' => strtoupper(substr($country ?: 'IN', 0, 2)),
            'city' => trim($city) ?: null,
            'state' => trim($state) ?: null,
            'referral_code' => $code,
            'status' => 'pending',
        ]);

        return ['id' => $id, 'referral_code' => $code];
    }

    public static function approve(int $partnerId, int $adminId): void
    {
        QueryBuilder::table('partners')->where('id', '=', $partnerId)->update([
            'status' => 'active',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $adminId,
        ]);
    }

    public static function setStatus(int $partnerId, string $status): void
    {
        if (!in_array($status, ['pending', 'active', 'suspended', 'rejected'], true)) {
            return;
        }
        QueryBuilder::table('partners')->where('id', '=', $partnerId)->update(['status' => $status]);
    }

    public static function setCommissionOverride(int $partnerId, ?float $percent): void
    {
        QueryBuilder::table('partners')->where('id', '=', $partnerId)->update([
            'commission_percent_override' => $percent,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function updatePayoutDetails(int $partnerId, array $data): void
    {
        $allowed = ['payout_method', 'upi_id', 'bank_account_name', 'bank_account_no', 'bank_ifsc', 'pan_number'];
        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }
        QueryBuilder::table('partners')->where('id', '=', $partnerId)->update($payload);
    }

    public static function touchLogin(int $partnerId): void
    {
        QueryBuilder::table('partners')->where('id', '=', $partnerId)->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * The effective commission rate for a partner: their override, else the
     * global default.
     */
    public static function effectivePercent(array $partner): float
    {
        $override = $partner['commission_percent_override'] ?? null;
        if ($override !== null && $override !== '') {
            return (float) $override;
        }

        return PartnerSettingsService::defaultPercent();
    }

    /** @return list<array<string, mixed>> */
    public static function all(?string $status = null): array
    {
        $q = QueryBuilder::table('partners')->orderBy('created_at', 'DESC');
        if ($status !== null) {
            $q->where('status', '=', $status);
        }

        return $q->get();
    }

    /**
     * Dashboard money summary for one partner.
     *
     * - lifetime: every approved/paid commission ever earned
     * - available: approved commissions not yet attached to a payout request
     * - pending: commissions still inside the clearance window
     * - paid: commissions already paid out
     *
     * @return array{lifetime: float, available: float, pending: float, paid: float}
     */
    public static function earningsSummary(int $partnerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN status IN (\'approved\',\'paid\') THEN commission_amount END), 0) AS lifetime,
                COALESCE(SUM(CASE WHEN status = \'approved\' AND payout_request_id IS NULL THEN commission_amount END), 0) AS available,
                COALESCE(SUM(CASE WHEN status = \'pending\' THEN commission_amount END), 0) AS pending,
                COALESCE(SUM(CASE WHEN status = \'paid\' THEN commission_amount END), 0) AS paid
             FROM partner_commissions
             WHERE partner_id = :pid'
        );
        $stmt->execute(['pid' => $partnerId]);
        $row = $stmt->fetch() ?: [];

        return [
            'lifetime' => (float) ($row['lifetime'] ?? 0),
            'available' => (float) ($row['available'] ?? 0),
            'pending' => (float) ($row['pending'] ?? 0),
            'paid' => (float) ($row['paid'] ?? 0),
        ];
    }

    /**
     * The "relationship" view: referred clinics with their subscription state.
     *
     * @return list<array<string, mixed>>
     */
    public static function referredClinics(int $partnerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT r.id AS referral_id, r.status AS referral_status, r.attributed_via,
                    r.registered_at, r.converted_at,
                    t.id AS tenant_id, t.name AS clinic_name, t.plan, t.plan_expires_at,
                    t.trial_ends_at, t.country_code,
                    COALESCE(SUM(CASE WHEN c.status IN (\'approved\',\'paid\') THEN c.commission_amount END), 0) AS earned
             FROM partner_referrals r
             JOIN tenants t ON t.id = r.tenant_id
             LEFT JOIN partner_commissions c ON c.referral_id = r.id
             WHERE r.partner_id = :pid
             GROUP BY r.id
             ORDER BY r.registered_at DESC'
        );
        $stmt->execute(['pid' => $partnerId]);

        return $stmt->fetchAll() ?: [];
    }

    private static function generateUniqueCode(string $name): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z]/', '', $name) ?: 'PTNR');
        $base = substr($base, 0, 4);
        if (strlen($base) < 3) {
            $base = 'PTNR';
        }

        for ($i = 0; $i < 12; $i++) {
            $code = $base . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $exists = QueryBuilder::table('partners')->where('referral_code', '=', $code)->first();
            if ($exists === null) {
                return $code;
            }
        }

        // Extremely unlikely fallback.
        return 'PTNR' . strtoupper(bin2hex(random_bytes(4)));
    }
}
