<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Automatic reminders (design §6, R3–R5). Email only (CorpMailer), from the
 * hourly worker once a company's local clock passes 08:00. Every send is
 * logged in corp_reminder_log, so a re-run never sends twice:
 *   due_30 / due_7 / booking / cert_expiry  once per check / booking / certificate
 *   overdue                                 every 14 days, at most 3 times
 *   hr_digest                               Mondays, once per HR user
 * Employee emails go to the work email on file, grouped per person (one email
 * listing all their checks), and contain check names and dates only.
 */
final class ReminderService
{
    public const KINDS = [
        'due_30' => ['Check due in 30 days', 'Employees get one email about 30 days before a check is due.'],
        'due_7' => ['Check due in 7 days', 'A second email in the last week before the due date.'],
        'overdue' => ['Check overdue', 'Sent when a check is overdue and not booked, then every 14 days (at most 3 times).'],
        'booking' => ['Booking tomorrow', 'The day before a booked health check: clinic, address and time.'],
        'cert_expiry' => ['Certificate expiring', 'About 30 days before the employee\'s fitness certificate expires.'],
        'hr_digest' => ['Monday digest for HR', 'Every Monday, people who can see screening status get overdue / due-soon counts for their branches.'],
    ];
    public const SEND_FROM_HOUR = 8;
    private const OVERDUE_EVERY_DAYS = 14;
    private const OVERDUE_MAX = 3;

    /** @return array<string, bool> kind => on (no row = all on) */
    public static function settings(int $companyId): array
    {
        $out = array_fill_keys(array_keys(self::KINDS), true);
        try {
            $st = Database::connection()->prepare('SELECT * FROM corp_reminder_settings WHERE company_id = :cid');
            $st->execute(['cid' => $companyId]);
            if ($row = $st->fetch()) {
                foreach ($out as $k => $_) {
                    $out[$k] = (int) $row[$k] === 1;
                }
            }
        } catch (\Throwable $e) {
            // 1E patch not run yet: defaults
        }

        return $out;
    }

    /** @param array<string, mixed> $post on[kind]=1 @return array{ok: bool} */
    public static function saveSettings(int $companyId, int $userId, array $post): array
    {
        $on = is_array($post['on'] ?? null) ? $post['on'] : [];
        $vals = [];
        foreach (array_keys(self::KINDS) as $k) {
            $vals[$k] = !empty($on[$k]) ? 1 : 0;
        }
        Database::connection()->prepare(
            'INSERT INTO corp_reminder_settings (company_id, due_30, due_7, overdue, booking, cert_expiry, hr_digest, updated_by, updated_at)
             VALUES (:cid, :a, :b, :c, :d, :e, :f, :by, :at)
             ON DUPLICATE KEY UPDATE due_30 = VALUES(due_30), due_7 = VALUES(due_7), overdue = VALUES(overdue), booking = VALUES(booking),
                                     cert_expiry = VALUES(cert_expiry), hr_digest = VALUES(hr_digest), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        )->execute(['cid' => $companyId, 'a' => $vals['due_30'], 'b' => $vals['due_7'], 'c' => $vals['overdue'], 'd' => $vals['booking'],
            'e' => $vals['cert_expiry'], 'f' => $vals['hr_digest'], 'by' => $userId, 'at' => LocalDate::nowUtc()]);
        CorpAudit::log($companyId, 'corp_user', $userId, 'reminders_updated', 'company', $companyId, ['off' => implode(', ', array_keys(array_filter($vals, static fn ($v) => $v === 0))) ?: 'none']);

        return ['ok' => true];
    }

    /**
     * One company's reminders for today (company local date). $cap = most emails this run (the rest go next hour).
     *
     * @return array<string, int> emails per kind
     */
    public static function run(int $companyId, string $today, bool $dryRun = false, int $cap = 300): array
    {
        $on = self::settings($companyId);
        $company = self::company($companyId);
        $sent = array_fill_keys(array_keys(self::KINDS), 0);
        $budget = $cap;
        foreach (['overdue', 'due_7', 'due_30'] as $kind) {
            if ($on[$kind] && $budget > 0) {
                $sent[$kind] = self::dueReminders($companyId, $company, $kind, $today, $dryRun, $budget);
                $budget -= $sent[$kind];
            }
        }
        if ($on['booking'] && $budget > 0) {
            $sent['booking'] = self::bookingReminders($companyId, $company, $today, $dryRun, $budget);
            $budget -= $sent['booking'];
        }
        if ($on['cert_expiry'] && $budget > 0) {
            $sent['cert_expiry'] = self::certReminders($companyId, $company, $today, $dryRun, $budget);
            $budget -= $sent['cert_expiry'];
        }
        if ($on['hr_digest'] && $budget > 0 && (int) date('N', strtotime($today)) === 1) {
            $sent['hr_digest'] = self::hrDigest($companyId, $company, $today, $dryRun, $budget);
        }

        return $sent;
    }

    // ------------------------------------------------------------------

    private static function dueReminders(int $companyId, array $company, string $kind, string $today, bool $dryRun, int $budget): int
    {
        $params = ['cid' => $companyId, 'kind' => $kind];
        $window = match ($kind) {
            'due_30' => 'c.due_on > :d1 AND c.due_on <= :d2',
            'due_7' => 'c.due_on >= :d1 AND c.due_on <= :d2',
            default => 'c.due_on < :d1',
        };
        $params += match ($kind) {
            'due_30' => ['d1' => LocalDate::addDays($today, 7), 'd2' => LocalDate::addDays($today, 30)],
            'due_7' => ['d1' => $today, 'd2' => LocalDate::addDays($today, 7)],
            default => ['d1' => $today],
        };
        $logCond = $kind === 'overdue'
            ? '(SELECT COUNT(*) FROM corp_reminder_log l WHERE l.company_id = c.company_id AND l.kind = :kind AND l.ref_type = \'case\' AND l.ref_id = c.id) < ' . self::OVERDUE_MAX
              . ' AND NOT EXISTS (SELECT 1 FROM corp_reminder_log l2 WHERE l2.company_id = c.company_id AND l2.kind = :kind2 AND l2.ref_type = \'case\' AND l2.ref_id = c.id AND l2.sent_on > :since)'
            : 'NOT EXISTS (SELECT 1 FROM corp_reminder_log l WHERE l.company_id = c.company_id AND l.kind = :kind AND l.ref_type = \'case\' AND l.ref_id = c.id)';
        if ($kind === 'overdue') {
            $params += ['kind2' => $kind, 'since' => LocalDate::addDays($today, -self::OVERDUE_EVERY_DAYS)];
        }
        $st = Database::connection()->prepare(
            "SELECT c.id, c.due_on, c.employment_id, et.name AS exam_name, e.full_name, e.work_email
               FROM corp_screening_cases c
               JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
               JOIN oh_exam_types et ON et.id = c.exam_type_id
              WHERE c.company_id = :cid AND c.status = 'not_scheduled' AND e.status = 'active'
                AND e.work_email IS NOT NULL AND e.work_email <> '' AND {$window} AND {$logCond}
              ORDER BY c.employment_id, c.due_on"
        );
        $st->execute($params);
        $byEmp = [];
        foreach ($st->fetchAll() as $r) {
            $byEmp[(int) $r['employment_id']][] = $r;
        }
        $n = 0;
        foreach ($byEmp as $cases) {
            if ($n >= $budget) {
                break;
            }
            $emp = $cases[0];
            $lines = array_map(static fn ($c) => '• ' . $c['exam_name'] . ' (due ' . date('d M Y', strtotime((string) $c['due_on'])) . ')', $cases);
            [$subject, $heading, $intro] = match ($kind) {
                'overdue' => [count($cases) === 1 ? 'Health check overdue: ' . $cases[0]['exam_name'] : 'Health checks overdue', 'Your work health checks are overdue',
                    $company['name'] . ' needs these health checks for your job, and they are now overdue:'],
                'due_7' => ['Health check due this week', 'Health check due soon', $company['name'] . ' needs these health checks for your job, due within a week:'],
                default => ['Health check due next month', 'Upcoming work health check', $company['name'] . ' needs these health checks for your job in the coming weeks:'],
            };
            $paras = array_merge(['Dear ' . $emp['full_name'] . ',', $intro . "\n" . implode("\n", $lines)], self::employeeFooter());
            if (self::deliver($companyId, $kind, 'case', array_map(static fn ($c) => (int) $c['id'], $cases), (string) $emp['work_email'], $today, $dryRun,
                $subject, $heading, $paras, self::patientUrl(), 'Open Work health')) {
                $n++;
            }
        }

        return $n;
    }

    private static function bookingReminders(int $companyId, array $company, string $today, bool $dryRun, int $budget): int
    {
        $tomorrow = LocalDate::addDays($today, 1);
        $st = Database::connection()->prepare(
            "SELECT b.id, b.scheduled_at, e.full_name, e.work_email, t.name AS clinic_name, t.address AS clinic_address, t.phone AS clinic_phone,
                    (SELECT GROUP_CONCAT(et.name ORDER BY et.sort_order SEPARATOR ', ') FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
                      WHERE c.company_id = b.company_id AND c.booking_id = b.id) AS checks
               FROM corp_bookings b
               JOIN corp_employments e ON e.id = b.employment_id AND e.company_id = b.company_id
               JOIN tenants t ON t.id = b.clinic_id
              WHERE b.company_id = :cid AND b.status = 'booked' AND b.scheduled_at >= :d1 AND b.scheduled_at < :d2
                AND e.work_email IS NOT NULL AND e.work_email <> ''
                AND NOT EXISTS (SELECT 1 FROM corp_reminder_log l WHERE l.company_id = b.company_id AND l.kind = 'booking' AND l.ref_type = 'booking' AND l.ref_id = b.id)"
        );
        $st->execute(['cid' => $companyId, 'd1' => $tomorrow . ' 00:00:00', 'd2' => LocalDate::addDays($tomorrow, 1) . ' 00:00:00']);
        $n = 0;
        foreach ($st->fetchAll() as $b) {
            if ($n >= $budget) {
                break;
            }
            $when = date('l, d M Y \a\t h:i A', strtotime((string) $b['scheduled_at']));
            $paras = ['Dear ' . $b['full_name'] . ',',
                'Reminder: your work health check is tomorrow.' . "\nWhen: " . $when . " (clinic's local time)\nWhere: " . $b['clinic_name']
                    . (!empty($b['clinic_address']) ? ', ' . $b['clinic_address'] : '') . (!empty($b['clinic_phone']) ? "\nClinic phone: " . $b['clinic_phone'] : '')
                    . (!empty($b['checks']) ? "\nChecks: " . $b['checks'] : ''),
                'Please bring a photo ID and arrive 10 minutes early. If you can\'t make it, tell your HR team today.'];
            if (self::deliver($companyId, 'booking', 'booking', [(int) $b['id']], (string) $b['work_email'], $today, $dryRun,
                'Your health check is tomorrow', 'Health check tomorrow', $paras, self::patientUrl(), 'Open Work health')) {
                $n++;
            }
        }

        return $n;
    }

    private static function certReminders(int $companyId, array $company, string $today, bool $dryRun, int $budget): int
    {
        $st = Database::connection()->prepare(
            "SELECT f.id, f.cert_no, f.valid_to, e.full_name, e.work_email
               FROM corp_fitness_certificates f
               JOIN corp_employments e ON e.id = f.employment_id AND e.company_id = f.company_id
              WHERE f.company_id = :cid AND f.status = 'valid' AND f.valid_to >= :d1 AND f.valid_to <= :d2 AND e.status = 'active'
                AND e.work_email IS NOT NULL AND e.work_email <> ''
                AND f.id = (SELECT MAX(f2.id) FROM corp_fitness_certificates f2 WHERE f2.company_id = f.company_id AND f2.employment_id = f.employment_id AND f2.status = 'valid')
                AND NOT EXISTS (SELECT 1 FROM corp_reminder_log l WHERE l.company_id = f.company_id AND l.kind = 'cert_expiry' AND l.ref_type = 'certificate' AND l.ref_id = f.id)"
        );
        $st->execute(['cid' => $companyId, 'd1' => $today, 'd2' => LocalDate::addDays($today, 30)]);
        $n = 0;
        foreach ($st->fetchAll() as $c) {
            if ($n >= $budget) {
                break;
            }
            $paras = ['Dear ' . $c['full_name'] . ',',
                'Your fitness certificate ' . $c['cert_no'] . ' expires on ' . date('d M Y', strtotime((string) $c['valid_to'])) . '. '
                    . $company['name'] . ' will arrange your next check; your HR team will let you know the date.'];
            if (self::deliver($companyId, 'cert_expiry', 'certificate', [(int) $c['id']], (string) $c['work_email'], $today, $dryRun,
                'Your fitness certificate expires soon', 'Fitness certificate expiring', $paras, self::patientUrl(), 'Open Work health')) {
                $n++;
            }
        }

        return $n;
    }

    private static function hrDigest(int $companyId, array $company, string $today, bool $dryRun, int $budget): int
    {
        $st = Database::connection()->prepare(
            "SELECT u.id, u.name, u.email FROM corp_users u
              WHERE u.company_id = :cid AND u.status = 'active'
                AND NOT EXISTS (SELECT 1 FROM corp_reminder_log l WHERE l.company_id = u.company_id AND l.kind = 'hr_digest' AND l.ref_type = 'user' AND l.ref_id = u.id AND l.sent_on = :today)"
        );
        $st->execute(['cid' => $companyId, 'today' => $today]);
        $n = 0;
        foreach ($st->fetchAll() as $u) {
            if ($n >= $budget) {
                break;
            }
            if (!isset(CorpAuth::permissionsFor($companyId, (int) $u['id'])['screening.view_status'])) {
                continue;
            }
            $scope = CorpAuth::branchScopeFor($companyId, (int) $u['id'], 'screening.view_status');
            $counts = ScreeningCaseService::counts($companyId, $scope, $today);
            if ($counts['people'] === 0) {
                continue;   // nothing to report yet
            }
            $lines = [];
            foreach (ReportService::compliance($companyId, 'branch', [], $scope, $today)['rows'] as $r) {
                if ((int) $r['overdue'] > 0 || (int) $r['soon'] > 0) {
                    $lines[] = '• ' . $r['name'] . ': ' . $r['overdue'] . ' overdue, ' . $r['soon'] . ' due in 30 days' . ($r['up_to_date_pct'] !== null ? ', ' . $r['up_to_date_pct'] . '% up to date' : '');
                }
            }
            $expiring = CertificateService::search($companyId, ['view' => 'expiring'], $scope, $today)['counts']['expiring'] ?? 0;
            $paras = ['Hello ' . $u['name'] . ',',
                'Screening this week at ' . $company['name'] . ($scope !== null ? ' (your branches)' : '') . ":\n"
                    . '• ' . $counts['overdue'] . ' checks overdue' . "\n" . '• ' . $counts['soon'] . ' due in the next 30 days' . "\n"
                    . '• ' . ($counts['up_to_date_pct'] ?? 0) . '% of covered people up to date' . "\n" . '• ' . $expiring . ' fitness certificates expiring in 60 days',
            ];
            if ($lines !== []) {
                $paras[] = "By branch:\n" . implode("\n", array_slice($lines, 0, 15));
            }
            $paras[] = 'You can switch this weekly email off in Settings › Reminders.';
            if (self::deliver($companyId, 'hr_digest', 'user', [(int) $u['id']], (string) $u['email'], $today, $dryRun,
                'Weekly screening summary: ' . $counts['overdue'] . ' overdue', 'Your weekly screening summary', $paras,
                CorpMailer::appUrl() . '/company/screening?bucket=overdue', 'Open the due list')) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Log first (so a crash can't cause a resend storm), then send. Returns true when an email went (or would go, in a dry run).
     *
     * @param list<int> $refIds
     * @param list<string> $paras
     */
    private static function deliver(int $companyId, string $kind, string $refType, array $refIds, string $to, string $today, bool $dryRun,
        string $subject, string $heading, array $paras, string $ctaUrl, string $ctaLabel): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if ($dryRun) {
            echo "    [dry run] {$kind} → {$to}: {$subject}\n";

            return true;
        }
        $ins = Database::connection()->prepare(
            'INSERT IGNORE INTO corp_reminder_log (company_id, kind, ref_type, ref_id, recipient, sent_on, created_at)
             VALUES (:cid, :kind, :rt, :rid, :to, :on, :at)'
        );
        $fresh = 0;
        foreach ($refIds as $id) {
            $ins->execute(['cid' => $companyId, 'kind' => $kind, 'rt' => $refType, 'rid' => $id, 'to' => mb_substr($to, 0, 190), 'on' => $today, 'at' => LocalDate::nowUtc()]);
            $fresh += $ins->rowCount();
        }
        if ($fresh === 0) {
            return false;   // another run got there first
        }

        return CorpMailer::send($to, $subject, $heading, $paras, $ctaUrl, $ctaLabel);
    }

    /** @return list<string> */
    private static function employeeFooter(): array
    {
        return [
            'Your HR team usually books these for you. Had a test done at a lab already? Upload the report in eClinicPro under Work health: only the examining doctor sees it, your employer is told just that it arrived.',
            'Your medical details stay between you and the doctor. Your employer only receives the fitness outcome, and only if you agree.',
        ];
    }

    private static function patientUrl(): string
    {
        return rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/') . '/patient?tab=work';
    }

    /** @return array{name: string, timezone: string} */
    private static function company(int $companyId): array
    {
        $st = Database::connection()->prepare('SELECT legal_name, trade_name, timezone FROM corp_companies WHERE id = :cid');
        $st->execute(['cid' => $companyId]);
        $c = $st->fetch() ?: [];

        return ['name' => (string) (($c['trade_name'] ?? '') ?: ($c['legal_name'] ?? 'Your employer')), 'timezone' => (string) ($c['timezone'] ?? 'UTC')];
    }
}
