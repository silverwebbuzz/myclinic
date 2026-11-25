<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Services\OccHealth\OccExamService;
use App\Support\Locale\LocalDate;

/**
 * Fitness certificates as the EMPLOYER sees them (design §4, C9/C14): the
 * outcome, work restrictions and validity, and only when the employee agreed
 * to share them. Without consent the company sees "attended, outcome not
 * shared" and nothing else. Columns are listed explicitly (never *): the
 * clinic-side visit link is not read here. CorpFirewallGuardTest enforces it.
 */
final class CertificateService
{
    public const VIEWS = ['valid' => 'Valid', 'expiring' => 'Expiring in 60 days', 'expired' => 'Expired', 'restricted' => 'Not fully fit', 'not_shared' => 'Outcome not shared'];
    private const EXPIRING_DAYS = 60;

    /** The only certificate columns the company side reads. */
    private const COLS = 'f.id, f.company_id, f.employment_id, f.booking_id, f.cert_no, f.exam_date, f.outcome, f.restrictions_json, f.restrictions_note,
                          f.valid_from, f.valid_to, f.review_by, f.checks_json, f.consent_shared, f.status, f.signed_at, f.pdf_path';

    /** @return list<array<string, mixed>> shaped, newest first */
    public static function forEmployee(int $companyId, int $employmentId, string $today): array
    {
        $st = Database::connection()->prepare(
            'SELECT ' . self::COLS . ' FROM corp_fitness_certificates f WHERE f.company_id = :cid AND f.employment_id = :eid ORDER BY f.id DESC LIMIT 10'
        );
        $st->execute(['cid' => $companyId, 'eid' => $employmentId]);

        return array_map(static fn (array $r) => self::shape($r, $today), $st->fetchAll());
    }

    /**
     * Current certificates (one per person: the latest valid), filtered.
     *
     * @param array{view?: string, q?: string} $f
     * @param list<int>|null $scope
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int, counts: array<string, int>}
     */
    public static function search(int $companyId, array $f, ?array $scope, string $today, int $page = 1): array
    {
        if (!EmployeeService::isYmd($today)) {
            throw new \InvalidArgumentException('today must be Y-m-d');   // it is inlined below
        }
        $params = ['cid' => $companyId];
        $base = ["f.company_id = :cid", "f.status = 'valid'",
            'f.id = (SELECT MAX(f2.id) FROM corp_fitness_certificates f2 WHERE f2.company_id = f.company_id AND f2.employment_id = f.employment_id AND f2.status = \'valid\')'];
        if ($scope !== null) {
            if ($scope === []) {
                return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'counts' => []];
            }
            $in = [];
            foreach (array_values($scope) as $i => $bid) {
                $in[] = ':sb' . $i;
                $params['sb' . $i] = $bid;
            }
            $base[] = 'e.branch_id IN (' . implode(',', $in) . ')';
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($q, 0, 80)) . '%';
            $base[] = '(e.full_name LIKE :q1 OR e.employee_no LIKE :q2 OR f.cert_no LIKE :q3)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $soon = LocalDate::addDays($today, self::EXPIRING_DAYS);
        $viewSql = [
            'valid' => "f.consent_shared = 1 AND (f.valid_to IS NULL OR f.valid_to >= '" . $today . "')",
            'expiring' => "f.consent_shared = 1 AND f.valid_to >= '" . $today . "' AND f.valid_to <= '" . $soon . "'",
            'expired' => "f.consent_shared = 1 AND f.valid_to < '" . $today . "'",
            'restricted' => "f.consent_shared = 1 AND f.outcome <> 'fit'",
            'not_shared' => 'f.consent_shared = 0',
        ];
        $from = ' FROM corp_fitness_certificates f JOIN corp_employments e ON e.id = f.employment_id AND e.company_id = f.company_id WHERE ' . implode(' AND ', $base);
        $pdo = Database::connection();
        $counts = [];
        foreach ($viewSql as $k => $cond) {
            $c = $pdo->prepare('SELECT COUNT(*)' . $from . ' AND ' . $cond);
            $c->execute($params);
            $counts[$k] = (int) $c->fetchColumn();
        }
        $view = isset($viewSql[$f['view'] ?? '']) ? (string) $f['view'] : 'valid';
        $total = $counts[$view];
        $per = 50;
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($page, $pages));
        $st = $pdo->prepare(
            'SELECT ' . self::COLS . ', e.full_name, e.employee_no, e.status AS employee_status' . $from . ' AND ' . $viewSql[$view]
            . ' ORDER BY ' . ($view === 'expiring' || $view === 'expired' ? 'f.valid_to' : 'e.full_name') . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per)
        );
        $st->execute($params);
        $rows = array_map(static fn (array $r) => self::shape($r, $today) + ['full_name' => $r['full_name'], 'employee_no' => $r['employee_no'], 'employee_status' => $r['employee_status']], $st->fetchAll());

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'counts' => $counts];
    }

    /**
     * A certificate the company may download: in scope AND shared by the employee.
     *
     * @param list<int>|null $scope
     * @return array<string, mixed>|null raw row (for CertificatePdfService::response)
     */
    public static function downloadable(int $companyId, int $certId, ?array $scope): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT ' . self::COLS . ', e.branch_id FROM corp_fitness_certificates f JOIN corp_employments e ON e.id = f.employment_id AND e.company_id = f.company_id
              WHERE f.id = :id AND f.company_id = :cid AND f.consent_shared = 1 LIMIT 1'
        );
        $st->execute(['id' => $certId, 'cid' => $companyId]);
        $r = $st->fetch();
        if (!$r || !EmployeeService::inScope($r['branch_id'] !== null ? (int) $r['branch_id'] : null, $scope)) {
            return null;
        }

        return $r;
    }

    /**
     * What the employer may see of one certificate.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    public static function shape(array $r, string $today): array
    {
        $snap = json_decode((string) ($r['checks_json'] ?? ''), true) ?: [];
        $base = [
            'id' => (int) $r['id'], 'employment_id' => (int) $r['employment_id'], 'cert_no' => (string) $r['cert_no'],
            'exam_date' => (string) $r['exam_date'], 'status' => (string) $r['status'], 'shared' => (int) $r['consent_shared'] === 1,
            'clinic' => (string) ($snap['issuer']['clinic'] ?? ''),
        ];
        if (!$base['shared']) {
            return $base;   // attended only (C9)
        }
        $restr = array_map(static fn ($k) => OccExamService::RESTRICTIONS[$k] ?? (string) $k, json_decode((string) ($r['restrictions_json'] ?? '[]'), true) ?: []);
        if (!empty($r['restrictions_note'])) {
            $restr[] = (string) $r['restrictions_note'];
        }
        $expired = !empty($r['valid_to']) && (string) $r['valid_to'] < $today;

        return $base + [
            'outcome' => (string) $r['outcome'],
            'outcome_label' => OccExamService::OUTCOMES[$r['outcome']] ?? (string) $r['outcome'],
            'restrictions' => $restr,
            'valid_to' => $r['valid_to'] !== null ? (string) $r['valid_to'] : null,
            'review_by' => $r['review_by'] !== null ? (string) $r['review_by'] : null,
            'expired' => $expired,
            'checks' => $snap['checks'] ?? [],
            'has_pdf' => !empty($r['pdf_path']),
        ];
    }
}
