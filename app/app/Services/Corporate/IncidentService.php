<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Absences, work incidents and the return-to-work / post-incident checks they
 * trigger (Phase 2B, design H3/H6). Company-side records, NOT clinical: a
 * reason category and a short summary, never a diagnosis. The check is a
 * normal screening case (category return_to_work / post_incident, no
 * programme): it is booked and signed exactly like any other check. The
 * engine never re-dates or cancels these categories.
 */
final class IncidentService
{
    public const REASONS = ['illness' => 'Illness', 'injury' => 'Injury', 'maternity' => 'Maternity / paternity', 'other' => 'Other'];
    public const TYPES = ['injury' => 'Injury', 'near_miss' => 'Near miss', 'exposure' => 'Exposure (chemical, noise, heat…)', 'illness_at_work' => 'Illness at work', 'other' => 'Other'];
    private const MIN_SUMMARY = 10;

    // ------------------------------------------------------------------
    // Absences
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $post from_on, to_on, reason_category, note, needs_rtw
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string}
     */
    public static function recordAbsence(int $companyId, int $userId, int $employmentId, array $post, ?array $scope, string $today): array
    {
        $emp = EmployeeService::find($companyId, $employmentId, $scope);
        if ($emp === null || $emp['status'] === 'terminated') {
            return ['ok' => false, 'error' => 'Employee not found (or has left).'];
        }
        $from = trim((string) ($post['from_on'] ?? ''));
        $to = trim((string) ($post['to_on'] ?? ''));
        $reason = (string) ($post['reason_category'] ?? '');
        if (!EmployeeService::isYmd($from)) {
            return ['ok' => false, 'error' => 'Enter the first day of absence.'];
        }
        if ($to !== '' && (!EmployeeService::isYmd($to) || $to < $from)) {
            return ['ok' => false, 'error' => 'The return date must be on or after the first day.'];
        }
        if (!isset(self::REASONS[$reason])) {
            return ['ok' => false, 'error' => 'Choose a reason category.'];
        }
        $note = mb_substr(trim((string) ($post['note'] ?? '')), 0, 255);
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO corp_absences (company_id, employment_id, from_on, to_on, reason_category, note, status, created_by, created_at, updated_at)
                 VALUES (:cid, :eid, :f, :t, :r, :n, 'active', :by, :cr, :up)"
            )->execute(['cid' => $companyId, 'eid' => $employmentId, 'f' => $from, 't' => $to ?: null, 'r' => $reason, 'n' => $note ?: null, 'by' => $userId, 'cr' => $now, 'up' => $now]);
            $id = (int) $pdo->lastInsertId();
            $caseId = 0;
            if (!empty($post['needs_rtw'])) {
                $due = $to !== '' && $to > $today ? $to : $today;
                $caseId = self::openCase($companyId, $employmentId, 'rtw_assessment', 'return_to_work', $due, $userId, $now, ['absence_id' => $id]);
                $pdo->prepare('UPDATE corp_absences SET case_id = :case WHERE id = :id AND company_id = :cid')->execute(['case' => $caseId, 'id' => $id, 'cid' => $companyId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[IncidentService] absence: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save. Please try again.'];
        }
        CorpAudit::log($companyId, 'corp_user', $userId, 'absence_recorded', 'corp_employment', $employmentId,
            ['employee_no' => $emp['employee_no'], 'reason' => self::REASONS[$reason], 'from' => $from, 'to' => $to ?: 'open', 'rtw_check' => $caseId > 0 ? 'yes' : 'no']);

        return ['ok' => true];
    }

    /** @param list<int>|null $scope @return array{ok: bool, error?: string} */
    public static function removeAbsence(int $companyId, int $userId, int $absenceId, ?array $scope): array
    {
        $st = Database::connection()->prepare(
            "SELECT a.*, e.employee_no, e.branch_id FROM corp_absences a JOIN corp_employments e ON e.id = a.employment_id AND e.company_id = a.company_id
              WHERE a.id = :id AND a.company_id = :cid AND a.status = 'active' LIMIT 1"
        );
        $st->execute(['id' => $absenceId, 'cid' => $companyId]);
        $a = $st->fetch();
        if (!$a || !EmployeeService::inScope($a['branch_id'] !== null ? (int) $a['branch_id'] : null, $scope)) {
            return ['ok' => false, 'error' => 'Absence not found.'];
        }
        $now = LocalDate::nowUtc();
        Database::connection()->prepare("UPDATE corp_absences SET status = 'removed', updated_at = :up WHERE id = :id AND company_id = :cid")
            ->execute(['up' => $now, 'id' => $absenceId, 'cid' => $companyId]);
        $cancelled = (int) $a['case_id'] > 0 ? self::cancelCase($companyId, (int) $a['case_id'], 'Absence removed', $userId, $now) : false;
        CorpAudit::log($companyId, 'corp_user', $userId, 'absence_removed', 'corp_employment', (int) $a['employment_id'],
            ['employee_no' => $a['employee_no'], 'check_cancelled' => $cancelled ? 'yes' : 'no']);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Incidents
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $post employment_id or employee_no (optional), branch_id, occurred_on, type, days_lost, summary, needs_check
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function recordIncident(int $companyId, int $userId, array $post, ?array $scope, string $today): array
    {
        $emp = null;
        if (!empty($post['employment_id'])) {
            $emp = EmployeeService::find($companyId, (int) $post['employment_id'], $scope);
            if ($emp === null) {
                return ['ok' => false, 'error' => 'Employee not found.'];
            }
        } elseif (trim((string) ($post['employee_no'] ?? '')) !== '') {
            $st = Database::connection()->prepare('SELECT id FROM corp_employments WHERE company_id = :cid AND employee_no = :no LIMIT 1');
            $st->execute(['cid' => $companyId, 'no' => trim((string) $post['employee_no'])]);
            $id = (int) ($st->fetchColumn() ?: 0);
            $emp = $id > 0 ? EmployeeService::find($companyId, $id, $scope) : null;
            if ($emp === null) {
                return ['ok' => false, 'error' => 'No employee with that employee no. (leave it empty if nobody was involved).'];
            }
        }
        $on = trim((string) ($post['occurred_on'] ?? ''));
        $type = (string) ($post['type'] ?? '');
        $summary = mb_substr(trim((string) ($post['summary'] ?? '')), 0, 1000);
        $days = max(0, min(999, (int) ($post['days_lost'] ?? 0)));
        if (!EmployeeService::isYmd($on) || $on > $today) {
            return ['ok' => false, 'error' => 'Enter the date it happened (not in the future).'];
        }
        if (!isset(self::TYPES[$type])) {
            return ['ok' => false, 'error' => 'Choose the type of incident.'];
        }
        if (mb_strlen($summary) < self::MIN_SUMMARY) {
            return ['ok' => false, 'error' => 'Describe what happened in a sentence (no medical details).'];
        }
        $branchId = $emp !== null ? (int) ($emp['branch_id'] ?? 0) : (int) ($post['branch_id'] ?? 0);
        if ($branchId > 0 && !EmployeeService::inScope($branchId, $scope)) {
            return ['ok' => false, 'error' => 'Choose one of your branches.'];
        }
        if ($emp === null && $scope !== null && $branchId === 0) {
            return ['ok' => false, 'error' => 'Choose the branch where it happened.'];
        }
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO corp_incidents (company_id, ref, employment_id, branch_id, occurred_on, type, days_lost, summary, status, created_by, created_at, updated_at)
                 VALUES (:cid, :tmp, :eid, :bid, :on, :type, :days, :sum, 'open', :by, :cr, :up)"
            )->execute(['cid' => $companyId, 'tmp' => 'TMP-' . bin2hex(random_bytes(6)), 'eid' => $emp !== null ? (int) $emp['id'] : 0, 'bid' => $branchId,
                'on' => $on, 'type' => $type, 'days' => $days, 'sum' => $summary, 'by' => $userId, 'cr' => $now, 'up' => $now]);
            $id = (int) $pdo->lastInsertId();
            $ref = 'INC-' . date('ym', strtotime($on)) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $caseId = 0;
            if ($emp !== null && !empty($post['needs_check']) && $emp['status'] !== 'terminated') {
                $caseId = self::openCase($companyId, (int) $emp['id'], 'incident_assessment', 'post_incident', $today, $userId, $now, ['incident' => $ref]);
            }
            $pdo->prepare('UPDATE corp_incidents SET ref = :ref, case_id = :case WHERE id = :id AND company_id = :cid')
                ->execute(['ref' => $ref, 'case' => $caseId, 'id' => $id, 'cid' => $companyId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[IncidentService] incident: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save. Please try again.'];
        }
        CorpAudit::log($companyId, 'corp_user', $userId, 'incident_recorded', 'corp_incident', $id,
            ['incident' => $ref, 'type' => self::TYPES[$type], 'employee_no' => $emp['employee_no'] ?? '', 'check' => $caseId > 0 ? 'yes' : 'no']);

        return ['ok' => true, 'id' => $id];
    }

    /** close | reopen | remove. @param list<int>|null $scope @return array{ok: bool, error?: string} */
    public static function setIncidentStatus(int $companyId, int $userId, int $id, string $action, ?array $scope): array
    {
        $i = self::findIncident($companyId, $id, $scope);
        if ($i === null || $i['status'] === 'removed') {
            return ['ok' => false, 'error' => 'Incident not found.'];
        }
        $to = ['close' => 'closed', 'reopen' => 'open', 'remove' => 'removed'][$action] ?? null;
        if ($to === null) {
            return ['ok' => false, 'error' => 'Unknown action.'];
        }
        $now = LocalDate::nowUtc();
        Database::connection()->prepare('UPDATE corp_incidents SET status = :st, updated_at = :up WHERE id = :id AND company_id = :cid')
            ->execute(['st' => $to, 'up' => $now, 'id' => $id, 'cid' => $companyId]);
        if ($to === 'removed' && (int) $i['case_id'] > 0) {
            self::cancelCase($companyId, (int) $i['case_id'], 'Incident record removed', $userId, $now);
        }
        CorpAudit::log($companyId, 'corp_user', $userId, 'incident_' . $to, 'corp_incident', $id, ['incident' => $i['ref']]);

        return ['ok' => true];
    }

    /**
     * @param array{status?: string, type?: string, branch?: int} $f
     * @param list<int>|null $scope
     * @return list<array<string, mixed>>
     */
    public static function incidents(int $companyId, array $f, ?array $scope): array
    {
        $where = ['i.company_id = :cid', "i.status <> 'removed'"];
        $params = ['cid' => $companyId];
        if (in_array($f['status'] ?? '', ['open', 'closed'], true)) {
            $where[] = 'i.status = :st';
            $params['st'] = $f['status'];
        }
        if (isset(self::TYPES[$f['type'] ?? ''])) {
            $where[] = 'i.type = :type';
            $params['type'] = $f['type'];
        }
        if (!empty($f['branch'])) {
            $where[] = 'i.branch_id = :br';
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
            $where[] = 'i.branch_id IN (' . implode(',', $in) . ')';
        }
        $st = Database::connection()->prepare(
            'SELECT i.*, e.full_name, e.employee_no, b.name AS branch_name, c.status AS case_status, c.due_on AS case_due
               FROM corp_incidents i
               LEFT JOIN corp_employments e ON e.id = i.employment_id AND e.company_id = i.company_id
               LEFT JOIN corp_branches b ON b.id = i.branch_id AND b.company_id = i.company_id
               LEFT JOIN corp_screening_cases c ON c.id = i.case_id AND c.company_id = i.company_id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY i.occurred_on DESC, i.id DESC LIMIT 200'
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    /** @return array{absences: list<array<string, mixed>>, incidents: list<array<string, mixed>>} for the employee page */
    public static function forEmployee(int $companyId, int $employmentId): array
    {
        $pdo = Database::connection();
        $a = $pdo->prepare(
            "SELECT a.*, c.status AS case_status, c.due_on AS case_due FROM corp_absences a
               LEFT JOIN corp_screening_cases c ON c.id = a.case_id AND c.company_id = a.company_id
              WHERE a.company_id = :cid AND a.employment_id = :eid AND a.status = 'active' ORDER BY a.from_on DESC LIMIT 20"
        );
        $a->execute(['cid' => $companyId, 'eid' => $employmentId]);
        $i = $pdo->prepare(
            "SELECT i.*, c.status AS case_status FROM corp_incidents i
               LEFT JOIN corp_screening_cases c ON c.id = i.case_id AND c.company_id = i.company_id
              WHERE i.company_id = :cid AND i.employment_id = :eid AND i.status <> 'removed' ORDER BY i.occurred_on DESC LIMIT 20"
        );
        $i->execute(['cid' => $companyId, 'eid' => $employmentId]);

        return ['absences' => $a->fetchAll(), 'incidents' => $i->fetchAll()];
    }

    // ------------------------------------------------------------------

    /** @param list<int>|null $scope @return array<string, mixed>|null */
    private static function findIncident(int $companyId, int $id, ?array $scope): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_incidents WHERE id = :id AND company_id = :cid LIMIT 1');
        $st->execute(['id' => $id, 'cid' => $companyId]);
        $i = $st->fetch();
        if (!$i || ((int) $i['branch_id'] > 0 && !EmployeeService::inScope((int) $i['branch_id'], $scope)) || ((int) $i['branch_id'] === 0 && $scope !== null)) {
            return null;
        }

        return $i;
    }

    /**
     * An open check of this kind for the person, reusing one that is already open (no duplicates).
     *
     * @param array<string, mixed> $detail
     */
    private static function openCase(int $companyId, int $employmentId, string $examCode, string $category, string $due, int $userId, string $now, array $detail): int
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id FROM oh_exam_types WHERE code = :c');
        $st->execute(['c' => $examCode]);
        $exam = (int) ($st->fetchColumn() ?: 0);
        if ($exam === 0) {
            throw new \RuntimeException('exam type ' . $examCode . ' missing (run the 2B patch)');
        }
        $st = $pdo->prepare(
            "SELECT id FROM corp_screening_cases WHERE company_id = :cid AND employment_id = :eid AND category = :cat AND status IN ('not_scheduled', 'scheduled') LIMIT 1"
        );
        $st->execute(['cid' => $companyId, 'eid' => $employmentId, 'cat' => $category]);
        $existing = (int) ($st->fetchColumn() ?: 0);
        if ($existing > 0) {
            return $existing;
        }
        $pdo->prepare(
            "INSERT INTO corp_screening_cases (company_id, employment_id, exam_type_id, category, programme_item_id, due_on, status, created_at, updated_at)
             VALUES (:cid, :eid, :ex, :cat, 0, :due, 'not_scheduled', :cr, :up)"
        )->execute(['cid' => $companyId, 'eid' => $employmentId, 'ex' => $exam, 'cat' => $category, 'due' => $due, 'cr' => $now, 'up' => $now]);
        $caseId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
             VALUES (:cid, :case, 'created', :d, 'corp_user', :uid, :at)"
        )->execute(['cid' => $companyId, 'case' => $caseId, 'd' => json_encode($detail + ['due_on' => $due, 'category' => $category]), 'uid' => $userId, 'at' => $now]);

        return $caseId;
    }

    /** Cancel the check if it isn't booked or done yet. */
    private static function cancelCase(int $companyId, int $caseId, string $reason, int $userId, string $now): bool
    {
        $st = Database::connection()->prepare(
            "UPDATE corp_screening_cases SET status = 'cancelled', closed_reason = :r, updated_at = :up WHERE id = :id AND company_id = :cid AND status = 'not_scheduled'"
        );
        $st->execute(['r' => $reason, 'up' => $now, 'id' => $caseId, 'cid' => $companyId]);
        if ($st->rowCount() === 0) {
            return false;
        }
        Database::connection()->prepare(
            "INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
             VALUES (:cid, :case, 'cancelled', :d, 'corp_user', :uid, :at)"
        )->execute(['cid' => $companyId, 'case' => $caseId, 'd' => json_encode(['reason' => $reason]), 'uid' => $userId, 'at' => $now]);

        return true;
    }
}
