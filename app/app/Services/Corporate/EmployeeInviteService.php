<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Services\MessagingConsent;
use App\Services\SmsService;
use App\Support\Locale\LocalDate;

/**
 * Tells an employee their company added them, so they sign in at
 * eclinicpro.com/patient with that mobile and confirm the link (P3).
 *
 *  - Email when the company entered a work email.
 *  - SMS to Indian (+91) numbers through MSG91, unless the number opted out
 *    of SMS. India first: other countries get email only for now (Q1).
 *    The SMS text must match a DLT-registered template (see SMS_TEXT).
 *  - At most one invitation per person per 24 hours; never to someone who
 *    has left, already confirmed, or declined (HR fixes the number first,
 *    which puts the link back to "pending").
 */
final class EmployeeInviteService
{
    /** DLT template text. {#var#} = company name (max 30 characters). Register exactly this in MSG91 → DLT. */
    public const SMS_TEXT = '{#var#} has added you to eClinicPro Workforce Health. Sign in at eclinicpro.com/patient with this mobile number to confirm. -eClinicPro';
    public const BULK_LIMIT = 200;
    private const RESEND_SECONDS = 86400;

    /**
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string, channels?: list<string>}
     */
    public static function invite(int $companyId, int $actorId, int $employmentId, ?array $scope): array
    {
        $emp = EmployeeService::find($companyId, $employmentId, $scope);
        if ($emp === null) {
            return ['ok' => false, 'error' => 'Employee not found.'];
        }
        $why = self::cannotInvite($emp);
        if ($why !== null) {
            return ['ok' => false, 'error' => $why];
        }
        $channels = self::send($companyId, $emp);
        if ($channels === []) {
            return ['ok' => false, 'error' => 'We couldn\'t reach them: no work email, and SMS goes only to Indian (+91) numbers that haven\'t opted out. Ask them to sign in at eclinicpro.com/patient with this mobile number.'];
        }
        self::record($companyId, $actorId, $employmentId, $channels);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'employee_invited', 'corp_employment', $employmentId, ['employee_no' => $emp['employee_no'], 'channels' => $channels]);

        return ['ok' => true, 'channels' => $channels];
    }

    /**
     * Everyone in scope who is still unconfirmed and was never invited (up to BULK_LIMIT per click).
     *
     * @param list<int>|null $scope
     * @return array{sent: int, unreachable: int, remaining: int}
     */
    public static function inviteAll(int $companyId, int $actorId, ?array $scope): array
    {
        @set_time_limit(300);
        [$where, $params] = EmployeeService::filterSql($companyId, ['status' => 'current', 'link' => 'pending'], $scope);
        $pdo = Database::connection();
        $st = $pdo->prepare(
            'SELECT e.* FROM corp_employments e WHERE e.company_id = :cid AND e.invited_at IS NULL AND ' . $where . ' ORDER BY e.id LIMIT ' . self::BULK_LIMIT
        );
        $st->execute($params);
        $sent = $unreachable = 0;
        foreach ($st->fetchAll() as $emp) {
            $channels = self::send($companyId, $emp);
            // Mark unreachable ones too, so the next click moves on to new people.
            self::record($companyId, $actorId, (int) $emp['id'], $channels);
            $channels === [] ? $unreachable++ : $sent++;
        }
        CorpAudit::log($companyId, 'corp_user', $actorId, 'employees_invited', null, null, ['sent' => $sent, 'unreachable' => $unreachable]);

        return ['sent' => $sent, 'unreachable' => $unreachable, 'remaining' => self::notInvitedCount($companyId, $scope)];
    }

    /** @param list<int>|null $scope */
    public static function notInvitedCount(int $companyId, ?array $scope): int
    {
        [$where, $params] = EmployeeService::filterSql($companyId, ['status' => 'current', 'link' => 'pending'], $scope);
        $st = Database::connection()->prepare('SELECT COUNT(*) FROM corp_employments e WHERE e.company_id = :cid AND e.invited_at IS NULL AND ' . $where);
        $st->execute($params);

        return (int) $st->fetchColumn();
    }

    /** null = can invite; otherwise the reason not to. @param array<string, mixed> $emp */
    public static function cannotInvite(array $emp): ?string
    {
        return match (true) {
            $emp['status'] === 'terminated' => 'They have left the company.',
            $emp['link_status'] === 'confirmed' => 'They have already confirmed.',
            $emp['link_status'] === 'declined' => 'The person with this mobile said it isn\'t them. Correct the mobile number first.',
            !empty($emp['invited_at']) && strtotime((string) $emp['invited_at'] . ' UTC') > time() - self::RESEND_SECONDS => 'An invitation was sent in the last 24 hours.',
            default => null,
        };
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $emp
     * @return list<string> channels that were sent
     */
    private static function send(int $companyId, array $emp): array
    {
        $company = CompanyService::find($companyId) ?? [];
        $name = trim((string) (($company['trade_name'] ?? '') !== '' ? $company['trade_name'] : ($company['legal_name'] ?? 'Your employer')));
        $site = rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/');
        $channels = [];

        $email = (string) ($emp['work_email'] ?? '');
        if ($email !== '' && CorpMailer::send(
            $email,
            $name . ' added you to eClinicPro Workforce Health',
            'Your work health checks, in one place',
            [
                'Hello ' . $emp['full_name'] . ',',
                $name . ' uses eClinicPro to arrange the health checks your job needs (pre-employment, periodic and fitness certificates).',
                'Sign in with your mobile number ' . (string) $emp['work_phone'] . ' and confirm in the "Work health" tab that this is you. It\'s free, and your own health records stay private to you: your employer only sees whether you are fit for work.',
                'Not you, or don\'t work there? Choose "This isn\'t me" after signing in, or ignore this email.',
            ],
            $site . '/patient?tab=work',
            'Sign in and confirm',
        )) {
            $channels[] = 'email';
        }

        $phone = (string) ($emp['work_phone'] ?? '');
        if (str_starts_with($phone, '+91') && !MessagingConsent::isOptedOut($phone, 'sms')) {
            $text = str_replace('{#var#}', mb_substr($name, 0, 30), self::SMS_TEXT);
            $res = SmsService::send($phone, $text);
            if ($res['ok']) {
                $channels[] = 'sms';
            } else {
                error_log('[EmployeeInvite] SMS failed: ' . $res['message']);
            }
        }

        return $channels;
    }

    /** @param list<string> $channels */
    private static function record(int $companyId, int $actorId, int $employmentId, array $channels): void
    {
        $now = LocalDate::nowUtc();
        Database::connection()->prepare('UPDATE corp_employments SET invited_at = :t WHERE id = :id AND company_id = :cid')
            ->execute(['t' => $now, 'id' => $employmentId, 'cid' => $companyId]);
        EmployeeService::event($companyId, $employmentId, 'invited', null, ['channels' => $channels === [] ? 'none' : implode(', ', $channels)], null, 'corp_user', $actorId);
    }
}
