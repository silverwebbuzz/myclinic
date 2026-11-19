<?php

declare(strict_types=1);

namespace App\Services\Corporate;

/**
 * The due-date rules, as a pure function (no database): given one employee,
 * the company's programmes and the employee's existing cases, what should be
 * created, re-dated or cancelled. ScreeningEngine loads the data and applies
 * the plan. Design: workforce-health-phase1-design.md §3.1 S5–S10.
 *
 *  - Programmes cover an employee through their assignments (everyone /
 *    job role / department / branch / the employee).
 *  - Same exam + category from several programmes = one requirement; for
 *    periodic the most frequent wins (S5).
 *  - "Start date" = joining date, or the day the record was created.
 *    A NEW JOINER is someone whose start date is on/after the day the
 *    programme was applied to them; everyone else is EXISTING STAFF.
 *  - Pre-employment: new joiners only, due on the start date, once.
 *  - Periodic: last done (or waived cycle) + N months. With no history:
 *    new joiner → start date (+ N months if there is a pre-employment check
 *    for the same exam); existing staff → applied date + first-round days (S7).
 *  - Exit: due on the leaving date, when they leave.
 *  - Only open, NOT-SCHEDULED cases are ever re-dated or cancelled (S10).
 *  - Every input date is a Y-m-d in the company's time zone; nothing here
 *    depends on "today", so running the engine every night changes nothing
 *    unless the data changed (idempotent, S9).
 */
final class ScreeningPlanner
{
    public const OPEN = ['not_scheduled', 'scheduled'];

    /**
     * @param array{id:int, status:string, joined_on:?string, left_on:?string, created_on:string, job_role_id:?int, department_id:?int, branch_id:?int} $emp
     * @param list<array{id:int, first_round_days:int, assignments: list<array{type:string, target:int, on:string}>,
     *                   items: list<array{id:int, exam:int, category:string, months:?int}>}> $programmes  active programmes, active items
     * @param list<array{id:int, exam:int, category:string, status:string, due_on:string, completed_on:?string, programme_item_id:int}> $cases
     * @return list<array<string, mixed>> actions:
     *         ['create', exam, category, due_on, item_id] · ['redate', case_id, due_on, item_id] · ['cancel', case_id, reason]
     */
    public static function plan(array $emp, array $programmes, array $cases): array
    {
        $required = self::required($emp, $programmes);
        $actions = [];
        $openNotScheduled = static fn (array $c): bool => $c['status'] === 'not_scheduled';

        if ($emp['status'] === 'terminated') {
            foreach ($cases as $c) {
                if ($openNotScheduled($c) && in_array($c['category'], ['pre_employment', 'periodic'], true)) {
                    $actions[] = ['action' => 'cancel', 'case_id' => $c['id'], 'reason' => 'Left the company'];
                }
            }
            if (!empty($emp['left_on'])) {
                foreach ($required as $r) {
                    if ($r['category'] !== 'exit') {
                        continue;
                    }
                    $exists = array_filter($cases, static fn (array $c): bool => $c['category'] === 'exit' && $c['exam'] === $r['exam'] && $c['due_on'] === $emp['left_on']);
                    if ($exists === []) {
                        $actions[] = ['action' => 'create', 'exam' => $r['exam'], 'category' => 'exit', 'due_on' => $emp['left_on'], 'item_id' => $r['item_id']];
                    }
                }
            }

            return $actions;
        }

        foreach ($cases as $c) {
            if (!$openNotScheduled($c)) {
                continue;
            }
            if ($c['category'] === 'exit') {
                $actions[] = ['action' => 'cancel', 'case_id' => $c['id'], 'reason' => 'Back at work'];
            } elseif (in_array($c['category'], ['pre_employment', 'periodic'], true) && !isset($required[$c['exam'] . '|' . $c['category']])) {
                $actions[] = ['action' => 'cancel', 'case_id' => $c['id'], 'reason' => 'No longer required for this person'];
            }
        }

        $start = (string) ($emp['joined_on'] ?: $emp['created_on']);
        foreach ($required as $key => $r) {
            $newJoiner = $start >= $r['applied_on'];
            if ($r['category'] === 'pre_employment') {
                $had = array_filter($cases, static fn (array $c): bool => $c['category'] === 'pre_employment' && $c['exam'] === $r['exam']);
                if ($had === [] && $newJoiner) {
                    $actions[] = ['action' => 'create', 'exam' => $r['exam'], 'category' => 'pre_employment', 'due_on' => $start, 'item_id' => $r['item_id']];
                }
                continue;
            }
            if ($r['category'] !== 'periodic') {
                continue;
            }
            $due = self::periodicDue($r, $cases, $start, $newJoiner, isset($required[$r['exam'] . '|pre_employment']));
            $open = array_values(array_filter($cases, static fn (array $c): bool => $c['category'] === 'periodic' && $c['exam'] === $r['exam'] && in_array($c['status'], self::OPEN, true)));
            if ($open !== []) {
                $c = $open[0];
                if ($c['status'] === 'not_scheduled' && ($c['due_on'] !== $due || $c['programme_item_id'] !== $r['item_id'])) {
                    $actions[] = ['action' => 'redate', 'case_id' => $c['id'], 'due_on' => $due, 'item_id' => $r['item_id'], 'from' => $c['due_on']];
                }
                continue;
            }
            $actions[] = ['action' => 'create', 'exam' => $r['exam'], 'category' => 'periodic', 'due_on' => $due, 'item_id' => $r['item_id']];
        }

        return $actions;
    }

    /**
     * What this employee needs: "exam|category" => [exam, category, months, item_id, applied_on, first_round_days].
     *
     * @return array<string, array{exam:int, category:string, months:?int, item_id:int, applied_on:string, first_round_days:int}>
     */
    public static function required(array $emp, array $programmes): array
    {
        $out = [];
        foreach ($programmes as $p) {
            $on = null;
            foreach ($p['assignments'] as $a) {
                $hit = match ($a['type']) {
                    'all' => true,
                    'job_role' => (int) ($emp['job_role_id'] ?? 0) === $a['target'],
                    'department' => (int) ($emp['department_id'] ?? 0) === $a['target'],
                    'branch' => (int) ($emp['branch_id'] ?? 0) === $a['target'],
                    'employee' => (int) $emp['id'] === $a['target'],
                    default => false,
                };
                if ($hit && ($on === null || $a['on'] < $on)) {
                    $on = $a['on'];
                }
            }
            if ($on === null) {
                continue;
            }
            foreach ($p['items'] as $it) {
                $key = $it['exam'] . '|' . $it['category'];
                $cand = ['exam' => $it['exam'], 'category' => $it['category'], 'months' => $it['months'], 'item_id' => $it['id'],
                    'applied_on' => $on, 'first_round_days' => (int) $p['first_round_days']];
                if (!isset($out[$key])) {
                    $out[$key] = $cand;
                    continue;
                }
                $cur = $out[$key];
                $better = $it['category'] === 'periodic'
                    ? ((int) $cand['months'] < (int) $cur['months'] || ((int) $cand['months'] === (int) $cur['months'] && $cand['applied_on'] < $cur['applied_on']))
                    : $cand['applied_on'] < $cur['applied_on'];
                if ($better) {
                    $out[$key] = $cand;
                }
            }
        }

        return $out;
    }

    /**
     * @param array{exam:int, months:?int, applied_on:string, first_round_days:int} $r
     * @param list<array<string, mixed>> $cases
     */
    public static function periodicDue(array $r, array $cases, string $start, bool $newJoiner, bool $hasPreEmployment): string
    {
        $months = max(1, (int) $r['months']);
        $last = null;
        foreach ($cases as $c) {
            if ($c['exam'] !== $r['exam']) {
                continue;
            }
            $ref = match (true) {
                $c['status'] === 'completed' && !empty($c['completed_on']) => (string) $c['completed_on'],
                $c['status'] === 'waived' && $c['category'] === 'periodic' => (string) $c['due_on'],
                default => null,
            };
            if ($ref !== null && ($last === null || $ref > $last)) {
                $last = $ref;
            }
        }
        if ($last !== null) {
            return self::addMonths($last, $months);
        }
        if ($newJoiner) {
            return $hasPreEmployment ? self::addMonths($start, $months) : $start;
        }

        return self::addDays($r['applied_on'], (int) $r['first_round_days']);
    }

    /** 31 Jan + 1 month = 28/29 Feb (end of month is kept, never spills over). */
    public static function addMonths(string $ymd, int $months): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $ymd));
        $total = $y * 12 + ($m - 1) + $months;
        $ny = intdiv($total, 12);
        $nm = $total % 12 + 1;
        $last = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $ny, $nm)))->format('t');

        return sprintf('%04d-%02d-%02d', $ny, $nm, min($d, $last));
    }

    public static function addDays(string $ymd, int $days): string
    {
        return (new \DateTimeImmutable($ymd))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }
}
