<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Company compliance reports (design §6, R1). Compliance (due / overdue / done)
 * is shown per group in full. Fitness OUTCOME mix is shown per group only when
 * at least MIN_GROUP people in it have a shared outcome, so nobody's result can
 * be worked out from a small group. Outcomes not shared by the employee are
 * never counted. No clinical data is read (CorpFirewallGuardTest).
 */
final class ReportService
{
    public const GROUPS = ['branch' => 'Branch', 'department' => 'Department', 'job_role' => 'Job role', 'programme' => 'Programme'];
    public const MIN_GROUP = 5;
    private const SOON_DAYS = 30;
    private const OPEN = "('not_scheduled','scheduled')";

    /**
     * @param array{branch?: int, programme?: int} $f
     * @param list<int>|null $scope
     * @return array{rows: list<array<string, mixed>>, total: array<string, mixed>, by: string}
     */
    public static function compliance(int $companyId, string $by, array $f, ?array $scope, string $today): array
    {
        $by = isset(self::GROUPS[$by]) ? $by : 'branch';
        $soon = LocalDate::addDays($today, self::SOON_DAYS);
        $year = LocalDate::addDays($today, -365);
        $params = ['cid' => $companyId, 't1' => $today, 't2' => $today, 't3' => $soon, 'y' => $year];
        $where = ["e.company_id = :cid", "e.status <> 'terminated'"];
        $caseJoin = "LEFT JOIN corp_screening_cases c ON c.company_id = e.company_id AND c.employment_id = e.id AND c.status <> 'cancelled'";
        $groupExpr = match ($by) { 'department' => 'e.department_id', 'job_role' => 'e.job_role_id', 'programme' => 'pi.programme_id', default => 'e.branch_id' };
        if ($by === 'programme' || !empty($f['programme'])) {
            $caseJoin .= ' JOIN corp_programme_items pi ON pi.id = c.programme_item_id AND pi.company_id = c.company_id';
        }
        if (!empty($f['programme'])) {
            $where[] = 'pi.programme_id = :prog';
            $params['prog'] = (int) $f['programme'];
        }
        if (!empty($f['branch'])) {
            $where[] = 'e.branch_id = :br';
            $params['br'] = (int) $f['branch'];
        }
        if ($scope !== null) {
            if ($scope === []) {
                return ['rows' => [], 'total' => self::emptyRow('All'), 'by' => $by];
            }
            $in = [];
            foreach (array_values($scope) as $i => $bid) {
                $in[] = ':sb' . $i;
                $params['sb' . $i] = $bid;
            }
            $where[] = 'e.branch_id IN (' . implode(',', $in) . ')';
        }
        // One row per person (per person + programme when grouping by programme).
        $st = Database::connection()->prepare(
            "SELECT e.id AS emp, {$groupExpr} AS grp, COUNT(c.id) AS cases,
                    SUM(c.status IN " . self::OPEN . " AND c.due_on < :t1) AS overdue,
                    SUM(c.status IN " . self::OPEN . " AND c.due_on >= :t2 AND c.due_on <= :t3) AS soon,
                    SUM(c.status = 'completed' AND c.completed_on >= :y) AS done_12m
               FROM corp_employments e {$caseJoin}
              WHERE " . implode(' AND ', $where) . "
              GROUP BY e.id, {$groupExpr}"
        );
        $st->execute($params);
        $groups = [];
        $perEmp = [];
        foreach ($st->fetchAll() as $r) {
            $key = (int) ($r['grp'] ?? 0);
            $groups[$key] ??= self::emptyRow('');
            self::add($groups[$key], $r);
            // A person can be in several programme groups: the total counts them once, over all their checks.
            $e = (int) $r['emp'];
            $perEmp[$e] ??= ['cases' => 0, 'overdue' => 0, 'soon' => 0, 'done_12m' => 0];
            foreach (['cases', 'overdue', 'soon', 'done_12m'] as $k) {
                $perEmp[$e][$k] += (int) $r[$k];
            }
        }
        $total = self::emptyRow('All');
        foreach ($perEmp as $agg) {
            self::add($total, $agg);
        }
        $names = self::groupNames($companyId, $by);
        $rows = [];
        foreach ($groups as $key => $g) {
            $g['id'] = $key;
            $g['name'] = $names[$key] ?? ($key === 0 ? 'Not set' : 'Removed');
            $rows[] = self::finish($g);
        }
        usort($rows, static fn ($a, $b) => $b['overdue'] <=> $a['overdue'] ?: strcmp($a['name'], $b['name']));

        return ['rows' => $rows, 'total' => self::finish($total), 'by' => $by];
    }

    /**
     * Fitness outcome mix per group: latest valid, SHARED certificate per person. Groups under MIN_GROUP are suppressed.
     *
     * @param list<int>|null $scope
     * @return list<array{id:int, name:string, people:int, suppressed:bool, fit:int, fit_restricted:int, unfit:int, review:int, expiring:int}>
     */
    public static function outcomes(int $companyId, string $by, ?array $scope, string $today): array
    {
        $by = in_array($by, ['branch', 'department', 'job_role'], true) ? $by : 'branch';
        $col = ['branch' => 'e.branch_id', 'department' => 'e.department_id', 'job_role' => 'e.job_role_id'][$by];
        $params = ['cid' => $companyId, 't' => $today, 'soon' => LocalDate::addDays($today, self::SOON_DAYS)];
        $scopeSql = '';
        if ($scope !== null) {
            if ($scope === []) {
                return [];
            }
            $in = [];
            foreach (array_values($scope) as $i => $bid) {
                $in[] = ':sb' . $i;
                $params['sb' . $i] = $bid;
            }
            $scopeSql = ' AND e.branch_id IN (' . implode(',', $in) . ')';
        }
        $st = Database::connection()->prepare(
            "SELECT {$col} AS grp, f.outcome, f.valid_to
               FROM corp_fitness_certificates f
               JOIN corp_employments e ON e.id = f.employment_id AND e.company_id = f.company_id
              WHERE f.company_id = :cid AND f.status = 'valid' AND f.consent_shared = 1 AND e.status <> 'terminated'
                AND (f.valid_to IS NULL OR f.valid_to >= :t){$scopeSql}
                AND f.id = (SELECT MAX(f2.id) FROM corp_fitness_certificates f2 WHERE f2.company_id = f.company_id AND f2.employment_id = f.employment_id AND f2.status = 'valid')"
        );
        $st->execute($params);
        $groups = [];
        foreach ($st->fetchAll() as $r) {
            $k = (int) ($r['grp'] ?? 0);
            $groups[$k] ??= ['people' => 0, 'fit' => 0, 'fit_restricted' => 0, 'unfit' => 0, 'review' => 0, 'expiring' => 0];
            $g = &$groups[$k];
            $g['people']++;
            $bucket = match ((string) $r['outcome']) {
                'fit', 'fit_restricted', 'unfit' => (string) $r['outcome'],
                default => 'review',                       // temporarily unfit / further assessment
            };
            $g[$bucket]++;
            if (!empty($r['valid_to']) && (string) $r['valid_to'] <= $params['soon']) {
                $g['expiring']++;
            }
            unset($g);
        }
        $names = self::groupNames($companyId, $by);
        $out = [];
        foreach ($groups as $k => $g) {
            $suppressed = $g['people'] < self::MIN_GROUP;
            $out[] = ['id' => $k, 'name' => $names[$k] ?? ($k === 0 ? 'Not set' : 'Removed'), 'people' => $g['people'], 'suppressed' => $suppressed]
                + ($suppressed ? ['fit' => 0, 'fit_restricted' => 0, 'unfit' => 0, 'review' => 0, 'expiring' => 0] : $g);
        }
        usort($out, static fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /** Rows for export: one array per line, header first. @return list<list<string|int>> */
    public static function exportRows(array $report, string $groupLabel): array
    {
        $rows = [[$groupLabel, 'People', 'Covered by a programme', 'Up to date %', 'Checks overdue', 'People overdue', 'Due in 30 days', 'Done in 12 months']];
        foreach (array_merge($report['rows'], [$report['total']]) as $r) {
            $rows[] = [(string) $r['name'], $r['people'], $r['covered'], $r['up_to_date_pct'] ?? '', $r['overdue'], $r['people_overdue'], $r['soon'], $r['done_12m']];
        }

        return $rows;
    }

    // ------------------------------------------------------------------

    /** @return array<int, string> */
    private static function groupNames(int $companyId, string $by): array
    {
        $sql = match ($by) {
            'department' => 'SELECT id, name FROM corp_departments WHERE company_id = :cid',
            'job_role' => 'SELECT id, title AS name FROM corp_job_roles WHERE company_id = :cid',
            'programme' => 'SELECT id, name FROM corp_programmes WHERE company_id = :cid',
            default => 'SELECT id, name FROM corp_branches WHERE company_id = :cid',
        };
        $st = Database::connection()->prepare($sql);
        $st->execute(['cid' => $companyId]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function emptyRow(string $name): array
    {
        return ['name' => $name, 'people' => 0, 'covered' => 0, 'people_ok' => 0, 'people_overdue' => 0, 'overdue' => 0, 'soon' => 0, 'done_12m' => 0];
    }

    /** @param array<string, mixed> $g @param array<string, mixed> $r */
    private static function add(array &$g, array $r): void
    {
        $g['people']++;
        if ((int) $r['cases'] > 0) {
            $g['covered']++;
            if ((int) $r['overdue'] > 0) {
                $g['people_overdue']++;
            } else {
                $g['people_ok']++;
            }
        }
        $g['overdue'] += (int) $r['overdue'];
        $g['soon'] += (int) $r['soon'];
        $g['done_12m'] += (int) $r['done_12m'];
    }

    /** @param array<string, mixed> $g @return array<string, mixed> */
    private static function finish(array $g): array
    {
        $g['up_to_date_pct'] = $g['covered'] > 0 ? (int) floor($g['people_ok'] * 100 / $g['covered']) : null;

        return $g;
    }
}
