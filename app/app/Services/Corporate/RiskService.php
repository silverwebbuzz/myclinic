<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Risk register (Phase 2E, H7): HSE's list of hazards per site / job, rated
 * likelihood × severity (1–5 each) before and after controls, with an owner
 * and a review date, and linked to the screening status of the people it
 * applies to. Non-clinical. Edit: risk_register.manage; view: also reports.view.
 */
final class RiskService
{
    public const CATEGORIES = ['physical' => 'Physical (noise, heat, vibration…)', 'chemical' => 'Chemical (dust, fumes, solvents…)', 'biological' => 'Biological',
        'ergonomic' => 'Ergonomic (lifting, posture…)', 'psychosocial' => 'Psychosocial (stress, shifts…)', 'safety' => 'Safety (height, machinery, vehicles…)'];
    public const STATUSES = ['open' => 'Open', 'controlled' => 'Controlled', 'closed' => 'Closed'];
    public const LIKELIHOOD = [1 => 'Rare', 2 => 'Unlikely', 3 => 'Possible', 4 => 'Likely', 5 => 'Almost certain'];
    public const SEVERITY = [1 => 'Negligible', 2 => 'Minor', 3 => 'Moderate', 4 => 'Major', 5 => 'Severe'];

    /** @return array{0: string, 1: string} label + colour class for a score 1–25 */
    public static function band(int $score): array
    {
        return match (true) {
            $score <= 0 => ['Not rated', 'bg-ntb text-nt'],
            $score <= 4 => ['Low', 'bg-okb text-ok'],
            $score <= 9 => ['Medium', 'bg-wnb text-wn'],
            $score <= 16 => ['High', 'bg-erb text-er'],
            default => ['Very high', 'bg-er text-white'],
        };
    }

    /**
     * @param array{status?: string, branch?: int, band?: string} $f
     * @param list<int>|null $scope
     * @return list<array<string, mixed>> + score, residual_score, review_overdue, coverage
     */
    public static function entries(int $companyId, array $f, ?array $scope, string $today): array
    {
        $where = ['r.company_id = :cid'];
        $params = ['cid' => $companyId];
        if (isset(self::STATUSES[$f['status'] ?? ''])) {
            $where[] = 'r.status = :st';
            $params['st'] = $f['status'];
        } else {
            $where[] = "r.status <> 'closed'";
        }
        if (!empty($f['branch'])) {
            $where[] = '(r.branch_id = :br OR r.branch_id = 0)';
            $params['br'] = (int) $f['branch'];
        }
        if ($scope !== null) {
            if ($scope === []) {
                return [];
            }
            $in = [];
            foreach (array_values($scope) as $k => $bid) {
                $in[] = ':sb' . $k;
                $params['sb' . $k] = $bid;
            }
            $where[] = '(r.branch_id = 0 OR r.branch_id IN (' . implode(',', $in) . '))';
        }
        $st = Database::connection()->prepare(
            'SELECT r.*, b.name AS branch_name, j.title AS job_title, u.name AS owner_name
               FROM corp_risk_entries r
               LEFT JOIN corp_branches b ON b.id = r.branch_id AND b.company_id = r.company_id
               LEFT JOIN corp_job_roles j ON j.id = r.job_role_id AND j.company_id = r.company_id
               LEFT JOIN corp_users u ON u.id = r.owner_user_id AND u.company_id = r.company_id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY r.likelihood * r.severity DESC, r.ref'
        );
        $st->execute($params);
        $rows = $st->fetchAll();
        $cov = self::coverageMap($companyId, $today);
        $out = [];
        foreach ($rows as $r) {
            $r['score'] = (int) $r['likelihood'] * (int) $r['severity'];
            $r['residual_score'] = (int) $r['residual_likelihood'] * (int) $r['residual_severity'];
            $r['review_overdue'] = $r['status'] !== 'closed' && !empty($r['review_on']) && (string) $r['review_on'] < $today;
            $r['coverage'] = self::coverageFor($cov, (int) $r['branch_id'], (int) $r['job_role_id']);
            $band = self::band($r['score'])[0];
            if (!empty($f['band']) && strtolower(str_replace(' ', '_', $band)) !== $f['band']) {
                continue;
            }
            $out[] = $r;
        }

        return $out;
    }

    /** Open (not closed) entries counted per likelihood × severity cell. @return array<int, array<int, int>> [likelihood][severity] => n */
    public static function matrix(array $entries): array
    {
        $m = [];
        foreach ($entries as $e) {
            if ($e['status'] === 'closed') {
                continue;
            }
            $m[(int) $e['likelihood']][(int) $e['severity']] = ($m[(int) $e['likelihood']][(int) $e['severity']] ?? 0) + 1;
        }

        return $m;
    }

    /** @return list<array{id:int, name:string}> active company users who can own a risk */
    public static function owners(int $companyId): array
    {
        $st = Database::connection()->prepare("SELECT id, name FROM corp_users WHERE company_id = :cid AND status = 'active' ORDER BY name");
        $st->execute(['cid' => $companyId]);

        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $st->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public static function find(int $companyId, int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_risk_entries WHERE id = :id AND company_id = :cid LIMIT 1');
        $st->execute(['id' => $id, 'cid' => $companyId]);

        return $st->fetch() ?: null;
    }

    /**
     * Create (id 0) or update.
     *
     * @param array<string, mixed> $post
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function save(int $companyId, int $userId, int $id, array $post, ?array $scope): array
    {
        $existing = $id > 0 ? self::find($companyId, $id) : null;
        if ($id > 0 && $existing === null) {
            return ['ok' => false, 'error' => 'Risk not found.'];
        }
        $v = [
            'activity' => mb_substr(trim((string) ($post['activity'] ?? '')), 0, 200),
            'hazard' => mb_substr(trim((string) ($post['hazard'] ?? '')), 0, 200),
            'category' => (string) ($post['category'] ?? ''),
            'who' => mb_substr(trim((string) ($post['who_exposed'] ?? '')), 0, 300),
            'l' => (int) ($post['likelihood'] ?? 0), 's' => (int) ($post['severity'] ?? 0),
            'rl' => (int) ($post['residual_likelihood'] ?? 0), 'rs' => (int) ($post['residual_severity'] ?? 0),
            'controls' => mb_substr(trim((string) ($post['controls'] ?? '')), 0, 2000),
            'actions' => mb_substr(trim((string) ($post['actions'] ?? '')), 0, 2000),
            'branch' => (int) ($post['branch_id'] ?? 0), 'job' => (int) ($post['job_role_id'] ?? 0),
            'owner' => (int) ($post['owner_user_id'] ?? 0), 'review' => trim((string) ($post['review_on'] ?? '')),
            'status' => (string) ($post['status'] ?? 'open'),
        ];
        $error = match (true) {
            $v['activity'] === '' || $v['hazard'] === '' => 'Enter the activity / area and the hazard.',
            !isset(self::CATEGORIES[$v['category']]) => 'Choose a hazard category.',
            $v['l'] < 1 || $v['l'] > 5 || $v['s'] < 1 || $v['s'] > 5 => 'Rate likelihood and severity (1–5).',
            ($v['rl'] === 0) !== ($v['rs'] === 0) || $v['rl'] < 0 || $v['rl'] > 5 || $v['rs'] < 0 || $v['rs'] > 5 => 'Rate both residual likelihood and severity, or neither.',
            $v['review'] !== '' && !EmployeeService::isYmd($v['review']) => 'Check the review date.',
            !isset(self::STATUSES[$v['status']]) => 'Choose a status.',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        if ($v['branch'] > 0 && (!in_array($v['branch'], array_map(static fn ($b) => (int) $b['id'], StructureService::branches($companyId)), true) || !EmployeeService::inScope($v['branch'], $scope))) {
            return ['ok' => false, 'error' => 'Choose one of your branches.'];
        }
        if ($v['branch'] === 0 && $scope !== null) {
            return ['ok' => false, 'error' => 'Choose a branch.'];
        }
        if ($v['job'] > 0 && !in_array($v['job'], array_map(static fn ($j) => (int) $j['id'], StructureService::jobRoles($companyId)), true)) {
            return ['ok' => false, 'error' => 'Choose a job role from the list.'];
        }
        if ($v['owner'] > 0) {
            $st = Database::connection()->prepare("SELECT id FROM corp_users WHERE id = :id AND company_id = :cid AND status = 'active'");
            $st->execute(['id' => $v['owner'], 'cid' => $companyId]);
            if ($st->fetchColumn() === false) {
                return ['ok' => false, 'error' => 'Choose an owner from your team.'];
            }
        }
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $data = ['br' => $v['branch'], 'job' => $v['job'], 'act' => $v['activity'], 'hz' => $v['hazard'], 'cat' => $v['category'], 'who' => $v['who'] ?: null,
            'l' => $v['l'], 's' => $v['s'], 'ctl' => $v['controls'] ?: null, 'acts' => $v['actions'] ?: null, 'rl' => $v['rl'], 'rs' => $v['rs'],
            'own' => $v['owner'], 'rev' => $v['review'] ?: null, 'st' => $v['status'], 'ub' => $userId, 'up' => $now, 'cid' => $companyId];
        if ($existing === null) {
            $pdo->prepare(
                'INSERT INTO corp_risk_entries (company_id, ref, branch_id, job_role_id, activity, hazard, category, who_exposed, likelihood, severity, controls, actions,
                        residual_likelihood, residual_severity, owner_user_id, review_on, status, created_by, updated_by, created_at, updated_at)
                 VALUES (:cid, :ref, :br, :job, :act, :hz, :cat, :who, :l, :s, :ctl, :acts, :rl, :rs, :own, :rev, :st, :cb, :ub, :cr, :up)'
            )->execute($data + ['ref' => 'TMP-' . bin2hex(random_bytes(6)), 'cb' => $userId, 'cr' => $now]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE corp_risk_entries SET ref = :ref WHERE id = :id AND company_id = :cid')
                ->execute(['ref' => 'R-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), 'id' => $id, 'cid' => $companyId]);
        } else {
            $pdo->prepare(
                'UPDATE corp_risk_entries SET branch_id = :br, job_role_id = :job, activity = :act, hazard = :hz, category = :cat, who_exposed = :who, likelihood = :l, severity = :s,
                        controls = :ctl, actions = :acts, residual_likelihood = :rl, residual_severity = :rs, owner_user_id = :own, review_on = :rev, status = :st,
                        updated_by = :ub, updated_at = :up
                  WHERE id = :id AND company_id = :cid'
            )->execute($data + ['id' => $id]);
        }
        CorpAudit::log($companyId, 'corp_user', $userId, $existing === null ? 'risk_created' : 'risk_updated', 'corp_risk_entry', $id,
            ['hazard' => $v['hazard'], 'rating' => $v['l'] * $v['s'], 'status' => $v['status']]);

        return ['ok' => true, 'id' => $id];
    }

    /** Rows for export, header first. @return list<list<string|int>> */
    public static function exportRows(array $entries): array
    {
        $rows = [['Ref', 'Branch', 'Job role', 'Activity / area', 'Hazard', 'Category', 'Who is exposed', 'Likelihood', 'Severity', 'Rating', 'Controls', 'Further actions',
            'Residual rating', 'Owner', 'Review by', 'Status', 'People', 'People overdue a check']];
        foreach ($entries as $r) {
            $rows[] = [(string) $r['ref'], (string) ($r['branch_name'] ?? 'All branches'), (string) ($r['job_title'] ?? ''), (string) $r['activity'], (string) $r['hazard'],
                self::CATEGORIES[$r['category']] ?? (string) $r['category'], (string) ($r['who_exposed'] ?? ''), (int) $r['likelihood'], (int) $r['severity'], $r['score'],
                (string) ($r['controls'] ?? ''), (string) ($r['actions'] ?? ''), $r['residual_score'] ?: '', (string) ($r['owner_name'] ?? ''),
                $r['review_on'] ? date('d M Y', strtotime((string) $r['review_on'])) : '', self::STATUSES[$r['status']] ?? (string) $r['status'],
                $r['coverage']['people'], $r['coverage']['overdue']];
        }

        return $rows;
    }

    // ------------------------------------------------------------------

    /** People and people-overdue per (branch, job role). @return array<string, array{people:int, overdue:int}> */
    private static function coverageMap(int $companyId, string $today): array
    {
        $st = Database::connection()->prepare(
            "SELECT e.branch_id, e.job_role_id, COUNT(DISTINCT e.id) AS people,
                    COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN e.id END) AS overdue
               FROM corp_employments e
               LEFT JOIN corp_screening_cases c ON c.company_id = e.company_id AND c.employment_id = e.id
                     AND c.status IN ('not_scheduled', 'scheduled') AND c.due_on < :t
              WHERE e.company_id = :cid AND e.status <> 'terminated'
              GROUP BY e.branch_id, e.job_role_id"
        );
        $st->execute(['t' => $today, 'cid' => $companyId]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['branch_id'] . ':' . (int) $r['job_role_id']] = ['people' => (int) $r['people'], 'overdue' => (int) $r['overdue']];
        }

        return $out;
    }

    /** @param array<string, array{people:int, overdue:int}> $map @return array{people:int, overdue:int} */
    private static function coverageFor(array $map, int $branchId, int $jobRoleId): array
    {
        $t = ['people' => 0, 'overdue' => 0];
        if ($branchId === 0 && $jobRoleId === 0) {
            return $t;   // company-wide risk not tied to a group: no head-count
        }
        foreach ($map as $key => $c) {
            [$b, $j] = array_map('intval', explode(':', $key));
            if (($branchId === 0 || $b === $branchId) && ($jobRoleId === 0 || $j === $jobRoleId)) {
                $t['people'] += $c['people'];
                $t['overdue'] += $c['overdue'];
            }
        }

        return $t;
    }
}
