<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * The company's view of screening cases (design §3.1 S6, S8, S11): what is
 * overdue / due soon / done, and the two things HR can do in 1B: record a
 * check as done elsewhere (date only) or waive it (with a reason).
 * No clinical data here; results arrive with 1C behind the firewall.
 *
 * Every read takes the branch scope (Branch Managers see their branches).
 */
final class ScreeningCaseService
{
    public const BUCKETS = ['overdue' => 'Overdue', 'soon' => 'Due in 30 days', 'later' => 'Later', 'done' => 'Done', 'waived' => 'Waived'];
    public const CATEGORIES = ['pre_employment' => 'Pre-employment', 'periodic' => 'Periodic', 'exit' => 'Exit', 'return_to_work' => 'Return to work', 'post_incident' => 'Post-incident'];
    public const SOON_DAYS = 30;
    public const PER_PAGE = 50;
    public const MIN_REASON = 10;

    /**
     * @param array<string, mixed> $f  bucket, q, branch, job_role, programme, exam
     * @param list<int>|null $scope
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function search(int $companyId, array $f, ?array $scope, string $today, int $page = 1): array
    {
        [$where, $params] = self::filterSql($f, $scope, $today);
        $pdo = Database::connection();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM corp_screening_cases c JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
              LEFT JOIN corp_programme_items pi ON pi.id = c.programme_item_id AND pi.company_id = c.company_id
              WHERE c.company_id = :cid AND ' . $where
        );
        $count->execute($params + ['cid' => $companyId]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $closed = in_array($f['bucket'] ?? '', ['done', 'waived'], true);
        $st = $pdo->prepare(
            'SELECT c.*, e.full_name, e.employee_no, e.status AS employee_status, b.name AS branch_name, j.title AS job_title,
                    et.name AS exam_name, et.kind AS exam_kind, p.name AS programme_name, p.id AS programme_id
               FROM corp_screening_cases c
               JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
               JOIN oh_exam_types et ON et.id = c.exam_type_id
               LEFT JOIN corp_branches b ON b.id = e.branch_id AND b.company_id = e.company_id
               LEFT JOIN corp_job_roles j ON j.id = e.job_role_id AND j.company_id = e.company_id
               LEFT JOIN corp_programme_items pi ON pi.id = c.programme_item_id AND pi.company_id = c.company_id
               LEFT JOIN corp_programmes p ON p.id = pi.programme_id AND p.company_id = c.company_id
              WHERE c.company_id = :cid AND ' . $where . '
              ORDER BY ' . ($closed ? 'COALESCE(c.completed_on, c.due_on) DESC' : 'c.due_on') . ', e.full_name, c.id
              LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE)
        );
        $st->execute($params + ['cid' => $companyId]);

        return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Tile numbers. up_to_date_pct = current employees with at least one
     * screening check, none of them overdue.
     *
     * @param list<int>|null $scope
     * @return array{overdue:int, soon:int, later:int, done_12m:int, waived:int, people:int, people_ok:int, up_to_date_pct:?int}
     */
    public static function counts(int $companyId, ?array $scope, string $today): array
    {
        $scopeSql = self::scopeSql($scope);
        $st = Database::connection()->prepare(
            "SELECT SUM(c.status IN ('not_scheduled','scheduled') AND c.due_on < :t1) AS overdue,
                    SUM(c.status IN ('not_scheduled','scheduled') AND c.due_on >= :t2 AND c.due_on <= :soon1) AS soon,
                    SUM(c.status IN ('not_scheduled','scheduled') AND c.due_on > :soon2) AS later,
                    SUM(c.status = 'completed' AND c.completed_on >= :year) AS done_12m,
                    SUM(c.status = 'waived') AS waived
               FROM corp_screening_cases c JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
              WHERE c.company_id = :cid AND e.status <> 'terminated'" . $scopeSql
        );
        $soon = LocalDate::addDays($today, self::SOON_DAYS);
        $st->execute(['t1' => $today, 't2' => $today, 'soon1' => $soon, 'soon2' => $soon, 'year' => LocalDate::addDays($today, -365), 'cid' => $companyId]);
        $r = $st->fetch() ?: [];

        $pp = Database::connection()->prepare(
            "SELECT COUNT(*) AS people,
                    SUM(NOT EXISTS (SELECT 1 FROM corp_screening_cases o WHERE o.company_id = e.company_id AND o.employment_id = e.id
                                     AND o.status IN ('not_scheduled','scheduled') AND o.due_on < :t)) AS people_ok
               FROM corp_employments e
              WHERE e.company_id = :cid AND e.status <> 'terminated'
                AND EXISTS (SELECT 1 FROM corp_screening_cases x WHERE x.company_id = e.company_id AND x.employment_id = e.id AND x.status <> 'cancelled')" . $scopeSql
        );
        $pp->execute(['t' => $today, 'cid' => $companyId]);
        $q = $pp->fetch() ?: [];
        $people = (int) ($q['people'] ?? 0);
        $ok = (int) ($q['people_ok'] ?? 0);

        return [
            'overdue' => (int) ($r['overdue'] ?? 0), 'soon' => (int) ($r['soon'] ?? 0), 'later' => (int) ($r['later'] ?? 0),
            'done_12m' => (int) ($r['done_12m'] ?? 0), 'waived' => (int) ($r['waived'] ?? 0),
            'people' => $people, 'people_ok' => $ok, 'up_to_date_pct' => $people > 0 ? (int) floor($ok * 100 / $people) : null,
        ];
    }

    /** @return list<array<string, mixed>> open first (by due date), then the last 10 closed */
    public static function forEmployee(int $companyId, int $employmentId): array
    {
        $st = Database::connection()->prepare(
            "SELECT c.*, et.name AS exam_name FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
              WHERE c.company_id = :cid AND c.employment_id = :eid AND c.status <> 'cancelled'
              ORDER BY c.status IN ('not_scheduled','scheduled') DESC, CASE WHEN c.status IN ('not_scheduled','scheduled') THEN c.due_on END,
                       COALESCE(c.completed_on, c.due_on) DESC
              LIMIT 40"
        );
        $st->execute(['cid' => $companyId, 'eid' => $employmentId]);
        $rows = $st->fetchAll();
        $open = array_values(array_filter($rows, static fn ($r) => in_array($r['status'], ScreeningPlanner::OPEN, true)));
        $closed = array_slice(array_values(array_filter($rows, static fn ($r) => !in_array($r['status'], ScreeningPlanner::OPEN, true))), 0, 10);

        return array_merge($open, $closed);
    }

    // ------------------------------------------------------------------
    // Actions
    // ------------------------------------------------------------------

    /**
     * Done outside eClinicPro (date only, no results). Starts the next cycle.
     *
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string}
     */
    public static function doneElsewhere(int $companyId, int $actorId, int $caseId, string $date, string $note, ?array $scope, string $today): array
    {
        $case = self::openCase($companyId, $caseId, $scope);
        if (isset($case['error'])) {
            return ['ok' => false, 'error' => $case['error']];
        }
        $date = trim($date);
        $note = mb_substr(trim($note), 0, 500);
        if (!EmployeeService::isYmd($date)) {
            return ['ok' => false, 'error' => 'Enter the date the check was done.'];
        }
        if ($date > $today) {
            return ['ok' => false, 'error' => 'The date can\'t be in the future.'];
        }
        if ($date < LocalDate::addDays($today, -365 * 5)) {
            return ['ok' => false, 'error' => 'That date is more than five years ago. Please check it.'];
        }
        $st = Database::connection()->prepare(
            "UPDATE corp_screening_cases SET status = 'completed', completed_on = :d, completion_source = 'elsewhere', note = :n, updated_at = :up
              WHERE id = :id AND company_id = :cid AND status IN ('not_scheduled','scheduled')"
        );
        $st->execute(['d' => $date, 'n' => $note !== '' ? $note : null, 'up' => LocalDate::nowUtc(), 'id' => $caseId, 'cid' => $companyId]);
        if ($st->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'This check was already closed.'];
        }
        self::event($companyId, $caseId, 'done_elsewhere', ['completed_on' => $date, 'note' => $note], $actorId);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'screening_done_elsewhere', 'corp_screening_case', $caseId,
            ['employee_no' => $case['employee_no'], 'exam' => $case['exam_name'], 'completed_on' => $date]);
        ScreeningEngine::safeSyncEmployee($companyId, (int) $case['employment_id']);   // next cycle

        return ['ok' => true];
    }

    /**
     * Skip this cycle, with a reason (audited; the next periodic cycle counts from this due date).
     *
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string}
     */
    public static function waive(int $companyId, int $actorId, int $caseId, string $reason, ?array $scope): array
    {
        $case = self::openCase($companyId, $caseId, $scope);
        if (isset($case['error'])) {
            return ['ok' => false, 'error' => $case['error']];
        }
        $reason = mb_substr(trim($reason), 0, 500);
        if (mb_strlen($reason) < self::MIN_REASON) {
            return ['ok' => false, 'error' => 'Write why this check is waived (at least ' . self::MIN_REASON . ' characters).'];
        }
        $st = Database::connection()->prepare(
            "UPDATE corp_screening_cases SET status = 'waived', closed_reason = :r, updated_at = :up
              WHERE id = :id AND company_id = :cid AND status IN ('not_scheduled','scheduled')"
        );
        $st->execute(['r' => $reason, 'up' => LocalDate::nowUtc(), 'id' => $caseId, 'cid' => $companyId]);
        if ($st->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'This check was already closed.'];
        }
        self::event($companyId, $caseId, 'waived', ['reason' => $reason], $actorId);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'screening_waived', 'corp_screening_case', $caseId,
            ['employee_no' => $case['employee_no'], 'exam' => $case['exam_name'], 'reason' => $reason]);
        ScreeningEngine::safeSyncEmployee($companyId, (int) $case['employment_id']);

        return ['ok' => true];
    }

    /**
     * CSV of the filtered list. Dates only, no clinical data.
     *
     * @param array<string, mixed> $f
     * @param list<int>|null $scope
     */
    public static function exportCsv(int $companyId, array $f, ?array $scope, string $today): string
    {
        [$where, $params] = self::filterSql($f, $scope, $today);
        $st = Database::connection()->prepare(
            'SELECT e.employee_no, e.full_name, b.name AS branch_name, j.title AS job_title, et.name AS exam_name, c.category,
                    p.name AS programme_name, c.due_on, c.status, c.completed_on, c.completion_source, c.closed_reason
               FROM corp_screening_cases c
               JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
               JOIN oh_exam_types et ON et.id = c.exam_type_id
               LEFT JOIN corp_branches b ON b.id = e.branch_id AND b.company_id = e.company_id
               LEFT JOIN corp_job_roles j ON j.id = e.job_role_id AND j.company_id = e.company_id
               LEFT JOIN corp_programme_items pi ON pi.id = c.programme_item_id AND pi.company_id = c.company_id
               LEFT JOIN corp_programmes p ON p.id = pi.programme_id AND p.company_id = c.company_id
              WHERE c.company_id = :cid AND ' . $where . ' ORDER BY c.due_on, e.employee_no LIMIT 100000'
        );
        $st->execute($params + ['cid' => $companyId]);
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['employee_no', 'name', 'branch', 'job_role', 'check', 'when', 'programme', 'due_on', 'status', 'days_overdue', 'done_on', 'done_where', 'reason'], ',', '"', '');
        while (($r = $st->fetch()) !== false) {
            $open = in_array($r['status'], ScreeningPlanner::OPEN, true);
            $overdue = $open && $r['due_on'] < $today ? (int) (new \DateTimeImmutable($r['due_on']))->diff(new \DateTimeImmutable($today))->days : '';
            $line = [$r['employee_no'], $r['full_name'], $r['branch_name'], $r['job_title'], $r['exam_name'], self::CATEGORIES[$r['category']] ?? $r['category'],
                $r['programme_name'], $r['due_on'], $open ? ($r['due_on'] < $today ? 'overdue' : 'due') : $r['status'], $overdue, $r['completed_on'],
                $r['completion_source'] === 'elsewhere' ? 'elsewhere' : ($r['completion_source'] ? 'eClinicPro' : ''), $r['closed_reason']];
            fputcsv($out, array_map(static fn ($v) => EmployeeService::csvSafe((string) ($v ?? '')), $line), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    // ------------------------------------------------------------------

    /**
     * Filters (without the company condition, which each query holds itself).
     * Each placeholder is used once (EMULATE_PREPARES=false).
     *
     * @param array<string, mixed> $f
     * @param list<int>|null $scope
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function filterSql(array $f, ?array $scope, string $today): array
    {
        // People who left only keep their exit checks in the lists.
        $where = ["(e.status <> 'terminated' OR c.category = 'exit')"];
        $params = [];
        $soon = LocalDate::addDays($today, self::SOON_DAYS);
        switch ((string) ($f['bucket'] ?? 'overdue')) {
            case 'soon':
                $where[] = "c.status IN ('not_scheduled','scheduled') AND c.due_on >= :b_t AND c.due_on <= :b_soon";
                $params += ['b_t' => $today, 'b_soon' => $soon];
                break;
            case 'later':
                $where[] = "c.status IN ('not_scheduled','scheduled') AND c.due_on > :b_soon";
                $params['b_soon'] = $soon;
                break;
            case 'open':
                $where[] = "c.status IN ('not_scheduled','scheduled')";
                break;
            case 'done':
                $where[] = "c.status = 'completed'";
                break;
            case 'waived':
                $where[] = "c.status = 'waived'";
                break;
            default: // overdue
                $where[] = "c.status IN ('not_scheduled','scheduled') AND c.due_on < :b_t";
                $params['b_t'] = $today;
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 80), '%_\\') . '%';
            $where[] = '(e.full_name LIKE :q1 OR e.employee_no LIKE :q2)';
            $params += ['q1' => $like, 'q2' => $like];
        }
        foreach (['branch' => 'e.branch_id', 'job_role' => 'e.job_role_id', 'exam' => 'c.exam_type_id', 'programme' => 'pi.programme_id'] as $key => $col) {
            if ((int) ($f[$key] ?? 0) > 0) {
                $where[] = "{$col} = :f_{$key}";
                $params['f_' . $key] = (int) $f[$key];
            }
        }
        $s = self::scopeSql($scope);
        if ($s !== '') {
            $where[] = substr($s, 5); // drop leading " AND "
        }

        return [implode(' AND ', $where), $params];
    }

    /** @param list<int>|null $scope */
    private static function scopeSql(?array $scope): string
    {
        if ($scope === null) {
            return '';
        }

        return $scope === [] ? ' AND 1 = 0' : ' AND e.branch_id IN (' . implode(',', array_map('intval', $scope)) . ')';
    }

    /**
     * @param list<int>|null $scope
     * @return array<string, mixed>
     */
    private static function openCase(int $companyId, int $caseId, ?array $scope): array
    {
        $st = Database::connection()->prepare(
            'SELECT c.*, e.employee_no, e.branch_id, et.name AS exam_name
               FROM corp_screening_cases c
               JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
               JOIN oh_exam_types et ON et.id = c.exam_type_id
              WHERE c.id = :id AND c.company_id = :cid LIMIT 1'
        );
        $st->execute(['id' => $caseId, 'cid' => $companyId]);
        $c = $st->fetch();
        if (!$c || !EmployeeService::inScope($c['branch_id'] !== null ? (int) $c['branch_id'] : null, $scope)) {
            return ['error' => 'Check not found.'];
        }
        if (!in_array($c['status'], ScreeningPlanner::OPEN, true)) {
            return ['error' => 'This check was already closed.'];
        }

        return $c;
    }

    /** @param array<string, mixed> $detail */
    private static function event(int $companyId, int $caseId, string $event, array $detail, int $actorId): void
    {
        Database::connection()->prepare(
            'INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
             VALUES (:cid, :case, :ev, :d, \'corp_user\', :aid, :at)'
        )->execute(['cid' => $companyId, 'case' => $caseId, 'ev' => $event, 'd' => json_encode($detail, JSON_UNESCAPED_UNICODE), 'aid' => $actorId, 'at' => LocalDate::nowUtc()]);
    }
}
