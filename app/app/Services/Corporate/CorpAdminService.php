<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * eClinicPro-side management of companies: review queue, approve / reject,
 * suspend / reactivate, trial and plan dates, reset a company user's
 * two-step sign-in. Every action is written to the company's own activity log
 * (actor = "eClinicPro team") and checked against PlatformAdminScope.
 */
final class CorpAdminService
{
    public const MAX_TRIAL_EXTENSION_DAYS = 90;

    /**
     * @param list<string>|null $countries null = all
     * @return list<array<string, mixed>>
     */
    public static function listCompanies(?array $countries, string $status = '', string $q = '', int $limit = 200): array
    {
        $where = ['c.is_demo IN (0, 1)'];
        $params = [];
        if ($status !== '' && in_array($status, ['pending', 'active', 'suspended', 'rejected', 'closed'], true)) {
            $where[] = 'c.status = :status';
            $params['status'] = $status;
        }
        if ($countries !== null) {
            if ($countries === []) {
                return [];
            }
            $in = [];
            foreach (array_values($countries) as $i => $cc) {
                $in[] = ':cc' . $i;
                $params['cc' . $i] = $cc;
            }
            $where[] = 'c.country_code IN (' . implode(',', $in) . ')';
        }
        if ($q !== '') {
            $where[] = '(c.legal_name LIKE :q1 OR c.trade_name LIKE :q2 OR c.public_ref LIKE :q3 OR c.contact_email LIKE :q4)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $sql = 'SELECT c.*, i.name AS industry_name,
                       (SELECT COUNT(*) FROM corp_users u WHERE u.company_id = c.id) AS user_count,
                       (SELECT COUNT(*) FROM corp_company_documents d WHERE d.company_id = c.id) AS doc_count,
                       (SELECT COUNT(*) FROM corp_company_documents d2 WHERE d2.company_id = c.id AND d2.status = \'pending\') AS docs_pending
                  FROM corp_companies c /* cross-company: admin */
                  LEFT JOIN corp_industries i ON i.id = c.industry_id
                 WHERE ' . implode(' AND ', $where) . "
                 ORDER BY c.status = 'pending' DESC, c.created_at DESC
                 LIMIT " . max(1, min(500, $limit));
        $st = Database::connection()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    /**
     * @param list<string>|null $countries
     * @return array<string, int> status => count
     */
    public static function statusCounts(?array $countries): array
    {
        $out = ['pending' => 0, 'active' => 0, 'suspended' => 0, 'rejected' => 0, 'closed' => 0];
        foreach (self::listCompanies($countries, '', '', 500) as $c) {
            $out[(string) $c['status']] = ($out[(string) $c['status']] ?? 0) + 1;
        }

        return $out;
    }

    public static function pendingCount(): int
    {
        try {
            return (int) Database::connection()->query("SELECT COUNT(*) FROM corp_companies /* cross-company: admin */ WHERE status = 'pending'")->fetchColumn();
        } catch (\Throwable) {
            return 0;   // tables not created yet
        }
    }

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------

    /** @return array{ok: bool, error?: string} */
    public static function approve(array $company, int $adminId): array
    {
        if (!in_array($company['status'], ['pending', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'Only companies waiting for review (or rejected) can be approved.'];
        }
        $days = max(1, (int) CompanyService::setting('corp_trial_days', '30'));
        $trialEnds = $company['trial_ends_at'] ?: LocalDate::addDays(LocalDate::today((string) $company['timezone']), $days);
        Database::connection()->prepare(
            "UPDATE corp_companies SET status = 'active', status_reason = NULL, approved_by = :by, approved_at = :at,
                    trial_ends_at = :trial, updated_at = :up WHERE id = :id"
        )->execute(['by' => $adminId, 'at' => LocalDate::nowUtc(), 'trial' => $trialEnds, 'up' => LocalDate::nowUtc(), 'id' => (int) $company['id']]);
        CorpAudit::log((int) $company['id'], 'platform_admin', $adminId, 'company_approved', 'company', (int) $company['id'], ['trial_ends' => $trialEnds]);

        $loc = \App\Support\Locale\Locale::forCompany($company);
        self::emailOwner($company, 'Your company account is approved', 'Welcome to eClinicPro Workforce Health', [
            'Good news: ' . $company['legal_name'] . ' is approved.',
            'Your free trial runs until ' . $loc->date($trialEnds) . '. You can now invite your team. Adding employees and screening programmes is coming next.',
        ], 'Open your company portal');

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function reject(array $company, int $adminId, string $reason): array
    {
        $reason = mb_substr(trim($reason), 0, 255);
        if ($reason === '') {
            return ['ok' => false, 'error' => 'Give a reason; the company will see it.'];
        }
        if ($company['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Only companies waiting for review can be rejected.'];
        }
        self::setStatus((int) $company['id'], 'rejected', $reason);
        CorpSessionService::revokeAllForCompany((int) $company['id']);
        CorpAudit::log((int) $company['id'], 'platform_admin', $adminId, 'company_rejected', 'company', (int) $company['id'], ['reason' => $reason]);
        self::emailOwner($company, 'About your eClinicPro company registration', 'We couldn\'t approve your registration', [
            'We reviewed ' . $company['legal_name'] . ' and couldn\'t approve it yet.',
            'Reason: ' . $reason,
            'Reply to hello@eclinicpro.com and we\'ll help you sort it out.',
        ], null);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function suspend(array $company, int $adminId, string $reason): array
    {
        $reason = mb_substr(trim($reason), 0, 255);
        if ($reason === '') {
            return ['ok' => false, 'error' => 'Give a reason for the suspension.'];
        }
        if ($company['status'] !== 'active') {
            return ['ok' => false, 'error' => 'Only active companies can be suspended.'];
        }
        self::setStatus((int) $company['id'], 'suspended', $reason);
        $ended = CorpSessionService::revokeAllForCompany((int) $company['id']);
        CorpAudit::log((int) $company['id'], 'platform_admin', $adminId, 'company_suspended', 'company', (int) $company['id'], ['reason' => $reason, 'sessions_ended' => $ended]);
        self::emailOwner($company, 'Your eClinicPro company account is suspended', 'Your account is suspended', [
            $company['legal_name'] . ' can\'t sign in to eClinicPro Workforce Health at the moment.',
            'Reason: ' . $reason,
            'Contact hello@eclinicpro.com to resolve this.',
        ], null);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function reactivate(array $company, int $adminId): array
    {
        if ($company['status'] !== 'suspended') {
            return ['ok' => false, 'error' => 'Only suspended companies can be reactivated.'];
        }
        self::setStatus((int) $company['id'], 'active', null);
        CorpAudit::log((int) $company['id'], 'platform_admin', $adminId, 'company_reactivated', 'company', (int) $company['id']);
        self::emailOwner($company, 'Your eClinicPro company account is active again', 'Your account is active again', [
            $company['legal_name'] . ' can sign in to eClinicPro Workforce Health again.',
        ], 'Sign in');

        return ['ok' => true];
    }

    /** Add days to the trial (from its end, or from today if already over). */
    public static function extendTrial(array $company, int $adminId, int $days): array
    {
        if ($days < 1 || $days > self::MAX_TRIAL_EXTENSION_DAYS) {
            return ['ok' => false, 'error' => 'Extend by 1–' . self::MAX_TRIAL_EXTENSION_DAYS . ' days.'];
        }
        if ($company['status'] !== 'active') {
            return ['ok' => false, 'error' => 'Approve the company first; the trial starts on approval.'];
        }
        $today = LocalDate::today((string) $company['timezone']);
        $from = (!empty($company['trial_ends_at']) && $company['trial_ends_at'] > $today) ? (string) $company['trial_ends_at'] : $today;
        $new = LocalDate::addDays($from, $days);
        Database::connection()->prepare(
            'UPDATE corp_companies SET trial_ends_at = :t, trial_extension_granted = 1, updated_at = :up WHERE id = :id'
        )->execute(['t' => $new, 'up' => LocalDate::nowUtc(), 'id' => (int) $company['id']]);
        CorpAudit::log((int) $company['id'], 'platform_admin', $adminId, 'trial_extended', 'company', (int) $company['id'], ['days' => $days, 'trial_ends' => $new]);

        return ['ok' => true];
    }

    /** Paid-until date after payment (bank transfer / Razorpay link). Empty clears it. */
    public static function setPlan(array $company, int $adminId, string $planExpires, string $planId): array
    {
        $planExpires = trim($planExpires);
        $planId = mb_substr(trim($planId), 0, 40);
        if ($planExpires !== '' && \DateTimeImmutable::createFromFormat('!Y-m-d', $planExpires) === false) {
            return ['ok' => false, 'error' => 'Paid-until must be a valid date.'];
        }
        Database::connection()->prepare(
            'UPDATE corp_companies SET plan_expires_at = :p, plan_id = :pid, updated_at = :up WHERE id = :id'
        )->execute(['p' => $planExpires !== '' ? $planExpires : null, 'pid' => $planId !== '' ? $planId : null, 'up' => LocalDate::nowUtc(), 'id' => (int) $company['id']]);
        CorpAudit::log((int) $company['id'], 'platform_admin', $adminId, 'plan_set', 'company', (int) $company['id'],
            ['paid_until' => $planExpires !== '' ? $planExpires : 'cleared', 'plan' => $planId]);

        return ['ok' => true];
    }

    /** Reset any company user's two-step sign-in (e.g. the only admin lost their phone). */
    public static function resetUserMfa(array $company, int $adminId, int $userId): array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_users WHERE id = :id AND company_id = :cid LIMIT 1');
        $st->execute(['id' => $userId, 'cid' => (int) $company['id']]);
        $user = $st->fetch();
        if (!$user) {
            return ['ok' => false, 'error' => 'User not found in this company.'];
        }
        Database::connection()->prepare(
            'UPDATE corp_users SET mfa_secret_enc = NULL, mfa_enabled_at = NULL, mfa_last_step = NULL, mfa_recovery = NULL, updated_at = :t
              WHERE id = :id AND company_id = :cid'
        )->execute(['t' => LocalDate::nowUtc(), 'id' => $userId, 'cid' => (int) $company['id']]);
        CorpSessionService::revokeAllForUser($userId);
        CorpAudit::log((int) $company['id'], 'platform_admin', $adminId, 'mfa_reset_by_eclinicpro', 'corp_user', $userId);
        CorpMailer::send((string) $user['email'], 'Your two-step sign-in was reset', 'Your two-step sign-in was reset', [
            'The eClinicPro support team reset two-step sign-in on your company login, as requested. You have been signed out everywhere.',
            'Next time you sign in you\'ll scan a new QR code with your authenticator app.',
            'If you didn\'t ask for this, contact hello@eclinicpro.com straight away.',
        ], CorpMailer::appUrl() . '/company/login', 'Sign in');

        return ['ok' => true];
    }

    /** @return list<array<string, mixed>> */
    public static function recentActivity(int $companyId, int $limit = 30): array
    {
        $st = Database::connection()->prepare(
            "SELECT a.*, u.name AS actor_name FROM corp_audit_log a
               LEFT JOIN corp_users u ON a.actor_type = 'corp_user' AND u.id = a.actor_id AND u.company_id = a.company_id
              WHERE a.company_id = :cid ORDER BY a.id DESC LIMIT " . max(1, min(200, $limit))
        );
        $st->execute(['cid' => $companyId]);

        return $st->fetchAll();
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    public static function saveSetting(string $key, string $value): void
    {
        Database::connection()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute(['k' => $key, 'v' => $value]);
    }

    // ------------------------------------------------------------------

    private static function setStatus(int $companyId, string $status, ?string $reason): void
    {
        Database::connection()->prepare('UPDATE corp_companies SET status = :s, status_reason = :r, updated_at = :up WHERE id = :id')
            ->execute(['s' => $status, 'r' => $reason, 'up' => LocalDate::nowUtc(), 'id' => $companyId]);
    }

    /** @param list<string> $paragraphs */
    private static function emailOwner(array $company, string $subject, string $heading, array $paragraphs, ?string $cta): void
    {
        $st = Database::connection()->prepare('SELECT name, email FROM corp_users WHERE company_id = :cid AND is_owner = 1 LIMIT 1');
        $st->execute(['cid' => (int) $company['id']]);
        $owner = $st->fetch() ?: ['name' => (string) ($company['contact_name'] ?? ''), 'email' => (string) ($company['contact_email'] ?? '')];
        $first = trim((string) strtok((string) $owner['name'], ' ')) ?: 'there';
        CorpMailer::send((string) $owner['email'], $subject, $heading, array_merge(['Hi ' . $first . ','], $paragraphs),
            $cta !== null ? CorpMailer::appUrl() . '/company/login' : null, $cta);
    }
}
