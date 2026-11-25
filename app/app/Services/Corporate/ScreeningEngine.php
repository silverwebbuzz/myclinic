<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Keeps corp_screening_cases in line with the programmes (design S9).
 * Loads a company's rules once, asks ScreeningPlanner what each employee
 * needs, and applies the plan. Idempotent: run it as often as you like.
 *
 * Called after employee add/edit/leave/rehire and import rows
 * (EmployeeService), after programme / assignment changes, and nightly by
 * workers/corp_screening_sync.php. Never throws from a hook: a screening
 * problem must not stop HR saving an employee (errors go to the log).
 */
final class ScreeningEngine
{
    /** Terminated people are re-checked only for this long after leaving (exit checks). */
    private const LEAVER_DAYS = 120;

    /** @return array{employees:int, created:int, redated:int, cancelled:int} */
    public static function syncCompany(int $companyId): array
    {
        @set_time_limit(600);
        $tz = self::timezone($companyId);
        $rules = self::rules($companyId, $tz);
        $pdo = Database::connection();
        $st = $pdo->prepare(
            'SELECT e.id, e.status, e.joined_on, e.left_on, e.created_at, e.job_role_id, e.department_id, e.branch_id
               FROM corp_employments e
              WHERE e.company_id = :cid AND (e.status <> \'terminated\' OR e.left_on >= :since
                    OR EXISTS (SELECT 1 FROM corp_screening_cases c WHERE c.company_id = e.company_id AND c.employment_id = e.id AND c.status = \'not_scheduled\'))'
        );
        $st->execute(['cid' => $companyId, 'since' => LocalDate::addDays(LocalDate::today($tz), -self::LEAVER_DAYS)]);
        $employees = $st->fetchAll();

        $casesByEmp = [];
        $cs = $pdo->prepare(
            'SELECT id, employment_id, exam_type_id, category, status, due_on, completed_on, programme_item_id
               FROM corp_screening_cases WHERE company_id = :cid'
        );
        $cs->execute(['cid' => $companyId]);
        while (($c = $cs->fetch()) !== false) {
            $casesByEmp[(int) $c['employment_id']][] = self::caseRow($c);
        }

        $total = ['employees' => 0, 'created' => 0, 'redated' => 0, 'cancelled' => 0];
        foreach ($employees as $e) {
            $done = self::apply($companyId, self::empRow($e, $tz), $rules, $casesByEmp[(int) $e['id']] ?? []);
            $total['employees']++;
            foreach ($done as $k => $n) {
                $total[$k] += $n;
            }
        }

        return $total;
    }

    /** @return array{created:int, redated:int, cancelled:int} */
    public static function syncEmployee(int $companyId, int $employmentId): array
    {
        $tz = self::timezone($companyId);
        $pdo = Database::connection();
        $st = $pdo->prepare(
            'SELECT id, status, joined_on, left_on, created_at, job_role_id, department_id, branch_id
               FROM corp_employments WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $st->execute(['id' => $employmentId, 'cid' => $companyId]);
        $e = $st->fetch();
        if (!$e) {
            return ['created' => 0, 'redated' => 0, 'cancelled' => 0];
        }
        $cs = $pdo->prepare(
            'SELECT id, employment_id, exam_type_id, category, status, due_on, completed_on, programme_item_id
               FROM corp_screening_cases WHERE company_id = :cid AND employment_id = :eid'
        );
        $cs->execute(['cid' => $companyId, 'eid' => $employmentId]);

        return self::apply($companyId, self::empRow($e, $tz), self::rules($companyId, $tz), array_map([self::class, 'caseRow'], $cs->fetchAll()));
    }

    /** Hook for EmployeeService etc.: never throws (the 1B patch may not be run yet). */
    public static function safeSyncEmployee(int $companyId, int $employmentId): void
    {
        try {
            self::syncEmployee($companyId, $employmentId);
        } catch (\Throwable $e) {
            error_log('[ScreeningEngine] employee ' . $employmentId . ': ' . $e->getMessage());
        }
    }

    /** Hook after programme changes. */
    public static function safeSyncCompany(int $companyId): void
    {
        try {
            self::syncCompany($companyId);
        } catch (\Throwable $e) {
            error_log('[ScreeningEngine] company ' . $companyId . ': ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $emp
     * @param list<array<string, mixed>> $rules
     * @param list<array<string, mixed>> $cases
     * @return array{created:int, redated:int, cancelled:int}
     */
    private static function apply(int $companyId, array $emp, array $rules, array $cases): array
    {
        $count = ['created' => 0, 'redated' => 0, 'cancelled' => 0];
        $actions = ScreeningPlanner::plan($emp, $rules, $cases);
        if ($actions === []) {
            return $count;
        }
        $pdo = Database::connection();
        $now = LocalDate::nowUtc();
        $pdo->beginTransaction();
        try {
            foreach ($actions as $a) {
                if ($a['action'] === 'create') {
                    $pdo->prepare(
                        'INSERT INTO corp_screening_cases (company_id, employment_id, exam_type_id, category, programme_item_id, due_on, status, created_at, updated_at)
                         VALUES (:cid, :eid, :ex, :cat, :item, :due, \'not_scheduled\', :cr, :up)'
                    )->execute(['cid' => $companyId, 'eid' => $emp['id'], 'ex' => $a['exam'], 'cat' => $a['category'], 'item' => $a['item_id'], 'due' => $a['due_on'], 'cr' => $now, 'up' => $now]);
                    self::event($companyId, (int) $pdo->lastInsertId(), 'created', ['due_on' => $a['due_on'], 'category' => $a['category']], $now);
                    $count['created']++;
                } elseif ($a['action'] === 'redate') {
                    $st = $pdo->prepare(
                        'UPDATE corp_screening_cases SET due_on = :due, programme_item_id = :item, updated_at = :up
                          WHERE id = :id AND company_id = :cid AND status = \'not_scheduled\''
                    );
                    $st->execute(['due' => $a['due_on'], 'item' => $a['item_id'], 'up' => $now, 'id' => $a['case_id'], 'cid' => $companyId]);
                    if ($st->rowCount() > 0 && $a['from'] !== $a['due_on']) {
                        self::event($companyId, (int) $a['case_id'], 'redated', ['from' => $a['from'], 'to' => $a['due_on']], $now);
                        $count['redated']++;
                    }
                } elseif ($a['action'] === 'cancel') {
                    $st = $pdo->prepare(
                        "UPDATE corp_screening_cases SET status = 'cancelled', closed_reason = :r, updated_at = :up
                          WHERE id = :id AND company_id = :cid AND status = 'not_scheduled'"
                    );
                    $st->execute(['r' => $a['reason'], 'up' => $now, 'id' => $a['case_id'], 'cid' => $companyId]);
                    if ($st->rowCount() > 0) {
                        self::event($companyId, (int) $a['case_id'], 'cancelled', ['reason' => $a['reason']], $now);
                        $count['cancelled']++;
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $count;
    }

    /**
     * Active programmes with their active items and assignments (dates in company time).
     *
     * @return list<array<string, mixed>>
     */
    private static function rules(int $companyId, string $tz): array
    {
        $pdo = Database::connection();
        $ps = $pdo->prepare("SELECT id, first_round_days FROM corp_programmes WHERE company_id = :cid AND status = 'active'");
        $ps->execute(['cid' => $companyId]);
        $programmes = [];
        foreach ($ps->fetchAll() as $p) {
            $programmes[(int) $p['id']] = ['id' => (int) $p['id'], 'first_round_days' => (int) $p['first_round_days'], 'assignments' => [], 'items' => []];
        }
        if ($programmes === []) {
            return [];
        }
        $is = $pdo->prepare(
            'SELECT pi.id, pi.programme_id, pi.exam_type_id, pi.category, pi.frequency_months
               FROM corp_programme_items pi JOIN oh_exam_types et ON et.id = pi.exam_type_id AND et.is_active = 1
              WHERE pi.company_id = :cid AND pi.is_removed = 0'
        );
        $is->execute(['cid' => $companyId]);
        foreach ($is->fetchAll() as $i) {
            if (isset($programmes[(int) $i['programme_id']]) && !($i['category'] === 'periodic' && (int) $i['frequency_months'] < 1)) {
                $programmes[(int) $i['programme_id']]['items'][] = [
                    'id' => (int) $i['id'], 'exam' => (int) $i['exam_type_id'], 'category' => (string) $i['category'],
                    'months' => $i['frequency_months'] !== null ? (int) $i['frequency_months'] : null,
                ];
            }
        }
        $as = $pdo->prepare('SELECT programme_id, target_type, target_id, created_at FROM corp_programme_assignments WHERE company_id = :cid');
        $as->execute(['cid' => $companyId]);
        foreach ($as->fetchAll() as $a) {
            if (isset($programmes[(int) $a['programme_id']])) {
                $programmes[(int) $a['programme_id']]['assignments'][] = [
                    'type' => (string) $a['target_type'], 'target' => (int) $a['target_id'], 'on' => self::localDate((string) $a['created_at'], $tz),
                ];
            }
        }

        return array_values(array_filter($programmes, static fn (array $p): bool => $p['items'] !== [] && $p['assignments'] !== []));
    }

    /** @param array<string, mixed> $e @return array<string, mixed> */
    private static function empRow(array $e, string $tz): array
    {
        return [
            'id' => (int) $e['id'], 'status' => (string) $e['status'],
            'joined_on' => $e['joined_on'] !== null ? (string) $e['joined_on'] : null,
            'left_on' => $e['left_on'] !== null ? (string) $e['left_on'] : null,
            'created_on' => self::localDate((string) $e['created_at'], $tz),
            'job_role_id' => $e['job_role_id'] !== null ? (int) $e['job_role_id'] : null,
            'department_id' => $e['department_id'] !== null ? (int) $e['department_id'] : null,
            'branch_id' => $e['branch_id'] !== null ? (int) $e['branch_id'] : null,
        ];
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private static function caseRow(array $c): array
    {
        return [
            'id' => (int) $c['id'], 'exam' => (int) $c['exam_type_id'], 'category' => (string) $c['category'], 'status' => (string) $c['status'],
            'due_on' => (string) $c['due_on'], 'completed_on' => $c['completed_on'] !== null ? (string) $c['completed_on'] : null,
            'programme_item_id' => (int) $c['programme_item_id'],
        ];
    }

    /** @param array<string, mixed> $detail */
    private static function event(int $companyId, int $caseId, string $event, array $detail, string $now): void
    {
        Database::connection()->prepare(
            'INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
             VALUES (:cid, :case, :ev, :d, \'system\', NULL, :at)'
        )->execute(['cid' => $companyId, 'case' => $caseId, 'ev' => $event, 'd' => json_encode($detail), 'at' => $now]);
    }

    private static function timezone(int $companyId): string
    {
        $st = Database::connection()->prepare('SELECT timezone FROM corp_companies WHERE id = :id');
        $st->execute(['id' => $companyId]);
        $tz = (string) ($st->fetchColumn() ?: 'UTC');

        return LocalDate::isValidZone($tz) ? $tz : 'UTC';
    }

    /** UTC DATETIME → Y-m-d in $tz. */
    private static function localDate(string $utc, string $tz): string
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz))->format('Y-m-d');
    }
}
