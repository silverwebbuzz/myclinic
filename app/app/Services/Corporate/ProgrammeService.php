<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Screening programmes: the platform's rule templates (oh_*) and each
 * company's own programmes adopted from them (corp_programme*).
 * Design: workforce-health-phase1-design.md §3 (S1–S5, S12, S13).
 *
 *  - Adopting copies a template into a company programme; later template
 *    versions are offered, never applied silently.
 *  - The company may add items, remove Recommended / own items freely, and
 *    remove a Mandatory item only with a written reason (kept, audited,
 *    reported as an exception). A Mandatory periodic item can only be made
 *    more frequent than the template says.
 *  - Who a programme covers: everyone, job roles, departments, branches or
 *    single employees.
 */
final class ProgrammeService
{
    public const CATEGORIES = ['pre_employment' => 'Pre-employment', 'periodic' => 'Periodic', 'exit' => 'Exit'];
    public const REQUIREMENTS = ['mandatory' => 'Mandatory', 'recommended' => 'Recommended', 'company' => 'Company'];
    public const TARGETS = ['all' => 'Everyone', 'job_role' => 'Job role', 'department' => 'Department', 'branch' => 'Branch', 'employee' => 'Employee'];
    public const KINDS = ['exam' => 'Examinations', 'lab_test' => 'Lab tests', 'imaging' => 'Imaging', 'questionnaire' => 'Questionnaires', 'vaccination' => 'Vaccinations'];
    public const MIN_REASON = 10;

    // ------------------------------------------------------------------
    // Platform library (read-only for companies)
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> active exam types, catalogue order */
    public static function examTypes(): array
    {
        return Database::connection()->query('SELECT * FROM oh_exam_types WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
    }

    /**
     * Latest published version of each template for these countries, this
     * industry or "any industry". Each with its items and, if the company
     * adopted it, the programme id.
     *
     * @param list<string> $countries
     * @return list<array<string, mixed>>
     */
    public static function library(int $companyId, array $countries, int $industryId): array
    {
        $countries = array_values(array_filter(array_unique(array_map('strtoupper', $countries)), static fn ($c) => preg_match('/^[A-Z]{2}$/', $c) === 1));
        if ($countries === []) {
            return [];
        }
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT t.*, i.name AS industry_name
               FROM oh_rule_templates t
               LEFT JOIN corp_industries i ON i.id = t.industry_id
              WHERE t.status = 'published' AND t.country_code IN ('" . implode("','", $countries) . "')
                AND (t.industry_id = 0 OR t.industry_id = :ind)
                AND t.version = (SELECT MAX(t2.version) FROM oh_rule_templates t2 WHERE t2.code = t.code AND t2.status = 'published')
              ORDER BY t.country_code, t.industry_id = 0, t.name"
        );
        $st->execute(['ind' => $industryId]);
        $templates = $st->fetchAll();
        $adopted = $pdo->prepare("SELECT template_code, id, template_version FROM corp_programmes WHERE company_id = :cid AND status = 'active' AND template_id > 0");
        $adopted->execute(['cid' => $companyId]);
        $byCode = [];
        foreach ($adopted->fetchAll() as $a) {
            $byCode[(string) $a['template_code']] = $a;
        }
        foreach ($templates as &$t) {
            $t['items'] = self::templateItems((int) $t['id']);
            $t['adopted_programme_id'] = isset($byCode[$t['code']]) ? (int) $byCode[$t['code']]['id'] : 0;
        }
        unset($t);

        return $templates;
    }

    /** @return list<array<string, mixed>> */
    public static function templateItems(int $templateId): array
    {
        $st = Database::connection()->prepare(
            'SELECT ti.*, et.name AS exam_name, et.kind FROM oh_template_items ti JOIN oh_exam_types et ON et.id = ti.exam_type_id
              WHERE ti.template_id = :tid ORDER BY FIELD(ti.category, \'pre_employment\', \'periodic\', \'exit\'), ti.sort_order'
        );
        $st->execute(['tid' => $templateId]);

        return $st->fetchAll();
    }

    // ------------------------------------------------------------------
    // Company programmes
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> + item_count, mandatory_removed, assignments (list), covered */
    public static function programmes(int $companyId, bool $includeArchived = false): array
    {
        $st = Database::connection()->prepare(
            'SELECT p.*, t.is_sample,
                    (SELECT COUNT(*) FROM corp_programme_items pi WHERE pi.company_id = p.company_id AND pi.programme_id = p.id AND pi.is_removed = 0) AS item_count,
                    (SELECT COUNT(*) FROM corp_programme_items pi WHERE pi.company_id = p.company_id AND pi.programme_id = p.id AND pi.is_removed = 1 AND pi.requirement = \'mandatory\') AS mandatory_removed
               FROM corp_programmes p
               LEFT JOIN oh_rule_templates t ON t.id = p.template_id
              WHERE p.company_id = :cid' . ($includeArchived ? '' : ' AND p.status = \'active\'') . '
              ORDER BY p.status, p.name'
        );
        $st->execute(['cid' => $companyId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['assignments'] = self::assignments($companyId, (int) $r['id']);
            $r['covered'] = self::coveredCount($companyId, (int) $r['id']);
        }
        unset($r);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $companyId, int $id): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT p.*, t.is_sample, t.source_note, t.audience FROM corp_programmes p LEFT JOIN oh_rule_templates t ON t.id = p.template_id
              WHERE p.id = :id AND p.company_id = :cid LIMIT 1'
        );
        $st->execute(['id' => $id, 'cid' => $companyId]);

        return $st->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> active and removed, with the template's own frequency for the "stricter only" rule */
    public static function items(int $companyId, int $programmeId): array
    {
        $st = Database::connection()->prepare(
            'SELECT pi.*, et.name AS exam_name, et.kind, ti.frequency_months AS template_frequency, u.name AS removed_by_name
               FROM corp_programme_items pi
               JOIN oh_exam_types et ON et.id = pi.exam_type_id
               LEFT JOIN oh_template_items ti ON ti.id = pi.template_item_id
               LEFT JOIN corp_users u ON u.id = pi.removed_by AND u.company_id = pi.company_id
              WHERE pi.company_id = :cid AND pi.programme_id = :pid
              ORDER BY pi.is_removed, FIELD(pi.category, \'pre_employment\', \'periodic\', \'exit\'), FIELD(pi.requirement, \'mandatory\', \'recommended\', \'company\'), et.sort_order'
        );
        $st->execute(['cid' => $companyId, 'pid' => $programmeId]);

        return $st->fetchAll();
    }

    /** @return list<array{id:int, target_type:string, target_id:int, label:string}> */
    public static function assignments(int $companyId, int $programmeId): array
    {
        $st = Database::connection()->prepare(
            'SELECT a.id, a.target_type, a.target_id,
                    CASE a.target_type
                        WHEN \'job_role\' THEN j.title WHEN \'department\' THEN d.name WHEN \'branch\' THEN b.name
                        WHEN \'employee\' THEN CONCAT(e.full_name, \' (\', e.employee_no, \')\') ELSE \'Everyone\' END AS label
               FROM corp_programme_assignments a
               LEFT JOIN corp_job_roles j ON a.target_type = \'job_role\' AND j.id = a.target_id AND j.company_id = a.company_id
               LEFT JOIN corp_departments d ON a.target_type = \'department\' AND d.id = a.target_id AND d.company_id = a.company_id
               LEFT JOIN corp_branches b ON a.target_type = \'branch\' AND b.id = a.target_id AND b.company_id = a.company_id
               LEFT JOIN corp_employments e ON a.target_type = \'employee\' AND e.id = a.target_id AND e.company_id = a.company_id
              WHERE a.company_id = :cid AND a.programme_id = :pid
              ORDER BY FIELD(a.target_type, \'all\', \'branch\', \'department\', \'job_role\', \'employee\'), label'
        );
        $st->execute(['cid' => $companyId, 'pid' => $programmeId]);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'target_type' => (string) $r['target_type'], 'target_id' => (int) $r['target_id'], 'label' => (string) ($r['label'] ?? '(removed)'),
        ], $st->fetchAll());
    }

    /**
     * SQL condition "employment e is covered by programme :pid" (shared with the engine).
     * Placeholders: :cov_cid, :cov_pid (each used once).
     */
    public static function coverageSql(): string
    {
        return 'EXISTS (SELECT 1 FROM corp_programme_assignments a
                         WHERE a.company_id = :cov_cid AND a.programme_id = :cov_pid
                           AND (a.target_type = \'all\'
                                OR (a.target_type = \'job_role\' AND a.target_id = e.job_role_id)
                                OR (a.target_type = \'department\' AND a.target_id = e.department_id)
                                OR (a.target_type = \'branch\' AND a.target_id = e.branch_id)
                                OR (a.target_type = \'employee\' AND a.target_id = e.id)))';
    }

    /** Current staff (not left) this programme applies to. */
    public static function coveredCount(int $companyId, int $programmeId): int
    {
        $st = Database::connection()->prepare(
            'SELECT COUNT(*) FROM corp_employments e WHERE e.company_id = :cid AND e.status <> \'terminated\' AND ' . self::coverageSql()
        );
        $st->execute(['cid' => $companyId, 'cov_cid' => $companyId, 'cov_pid' => $programmeId]);

        return (int) $st->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Copy a published template into a new company programme (no one assigned yet).
     *
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function adopt(int $companyId, int $actorId, int $templateId): array
    {
        $pdo = Database::connection();
        $st = $pdo->prepare("SELECT * FROM oh_rule_templates WHERE id = :id AND status = 'published' LIMIT 1");
        $st->execute(['id' => $templateId]);
        $tpl = $st->fetch();
        if (!$tpl) {
            return ['ok' => false, 'error' => 'That template is no longer available.'];
        }
        $dup = $pdo->prepare("SELECT id FROM corp_programmes WHERE company_id = :cid AND template_code = :code AND status = 'active' LIMIT 1");
        $dup->execute(['cid' => $companyId, 'code' => $tpl['code']]);
        if (($existing = $dup->fetchColumn()) !== false) {
            return ['ok' => false, 'error' => 'You already use this template.', 'id' => (int) $existing];
        }
        $now = LocalDate::nowUtc();
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO corp_programmes (company_id, name, description, template_id, template_code, template_version, first_round_days, status, created_by, created_at, updated_at)
                 VALUES (:cid, :name, :descr, :tid, :code, :ver, 60, \'active\', :by, :cr, :up)'
            )->execute([
                'cid' => $companyId, 'name' => mb_substr((string) $tpl['name'], 0, 150), 'descr' => mb_substr((string) ($tpl['description'] ?? ''), 0, 500) ?: null,
                'tid' => (int) $tpl['id'], 'code' => $tpl['code'], 'ver' => (int) $tpl['version'], 'by' => $actorId, 'cr' => $now, 'up' => $now,
            ]);
            $pid = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare(
                'INSERT INTO corp_programme_items (company_id, programme_id, exam_type_id, category, frequency_months, requirement, template_item_id, created_at, updated_at)
                 VALUES (:cid, :pid, :ex, :cat, :freq, :req, :tiid, :cr, :up)'
            );
            foreach (self::templateItems((int) $tpl['id']) as $ti) {
                $ins->execute([
                    'cid' => $companyId, 'pid' => $pid, 'ex' => (int) $ti['exam_type_id'], 'cat' => $ti['category'],
                    'freq' => $ti['frequency_months'], 'req' => $ti['requirement'], 'tiid' => (int) $ti['id'], 'cr' => $now, 'up' => $now,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ProgrammeService] adopt: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not adopt the template. Please try again.'];
        }
        CorpAudit::log($companyId, 'corp_user', $actorId, 'programme_adopted', 'corp_programme', $pid, ['template' => $tpl['code'], 'version' => (int) $tpl['version']]);

        return ['ok' => true, 'id' => $pid];
    }

    /** @return array{ok: bool, error?: string, id?: int} */
    public static function createOwn(int $companyId, int $actorId, string $name): array
    {
        $name = mb_substr(trim($name), 0, 150);
        if (mb_strlen($name) < 3) {
            return ['ok' => false, 'error' => 'Give the programme a name.'];
        }
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO corp_programmes (company_id, name, template_id, template_version, first_round_days, status, created_by, created_at, updated_at)
             VALUES (:cid, :name, 0, 0, 60, \'active\', :by, :cr, :up)'
        )->execute(['cid' => $companyId, 'name' => $name, 'by' => $actorId, 'cr' => $now, 'up' => $now]);
        $id = (int) $pdo->lastInsertId();
        CorpAudit::log($companyId, 'corp_user', $actorId, 'programme_created', 'corp_programme', $id, ['name' => $name]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Name, description, first-round window, archive / re-activate.
     *
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string}
     */
    public static function updateSettings(int $companyId, int $actorId, int $id, array $post): array
    {
        $p = self::find($companyId, $id);
        if ($p === null) {
            return ['ok' => false, 'error' => 'Programme not found.'];
        }
        $name = mb_substr(trim((string) ($post['name'] ?? '')), 0, 150);
        $descr = mb_substr(trim((string) ($post['description'] ?? '')), 0, 500);
        $days = (int) ($post['first_round_days'] ?? 60);
        $status = (string) ($post['status'] ?? $p['status']);
        $error = match (true) {
            mb_strlen($name) < 3 => 'Give the programme a name.',
            $days < 0 || $days > 365 => 'First round window must be between 0 and 365 days.',
            !in_array($status, ['active', 'archived'], true) => 'Unknown status.',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        Database::connection()->prepare(
            'UPDATE corp_programmes SET name = :name, description = :descr, first_round_days = :days, status = :st, updated_at = :up WHERE id = :id AND company_id = :cid'
        )->execute(['name' => $name, 'descr' => $descr !== '' ? $descr : null, 'days' => $days, 'st' => $status, 'up' => LocalDate::nowUtc(), 'id' => $id, 'cid' => $companyId]);
        CorpAudit::log($companyId, 'corp_user', $actorId, $status !== $p['status'] ? 'programme_' . ($status === 'archived' ? 'archived' : 'reactivated') : 'programme_updated',
            'corp_programme', $id, ['name' => $name]);

        return ['ok' => true];
    }

    /**
     * Add an exam (or bring a removed one back with new settings).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function addItem(int $companyId, int $actorId, int $programmeId, int $examTypeId, string $category, ?int $months): array
    {
        if (self::find($companyId, $programmeId) === null) {
            return ['ok' => false, 'error' => 'Programme not found.'];
        }
        $exam = self::exam($examTypeId);
        $error = match (true) {
            $exam === null => 'Choose an exam or test.',
            !array_key_exists($category, self::CATEGORIES) => 'Choose when it is done.',
            $category === 'periodic' && ($months === null || $months < 1 || $months > 120) => 'For a periodic check, give how often in months (1–120).',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        $months = $category === 'periodic' ? $months : null;
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT * FROM corp_programme_items WHERE company_id = :cid AND programme_id = :pid AND exam_type_id = :ex AND category = :cat LIMIT 1');
        $st->execute(['cid' => $companyId, 'pid' => $programmeId, 'ex' => $examTypeId, 'cat' => $category]);
        $row = $st->fetch();
        $now = LocalDate::nowUtc();
        if ($row && (int) $row['is_removed'] === 0) {
            return ['ok' => false, 'error' => $exam['name'] . ' (' . strtolower(self::CATEGORIES[$category]) . ') is already in this programme.'];
        }
        if ($row) {
            // Bring it back. A restored template item keeps its Mandatory/Recommended flag.
            if ($months !== null && ($why = self::frequencyRule($row, $months)) !== null) {
                return ['ok' => false, 'error' => $why];
            }
            $pdo->prepare(
                'UPDATE corp_programme_items SET is_removed = 0, removed_reason = NULL, removed_by = NULL, removed_at = NULL, frequency_months = :freq, updated_at = :up
                  WHERE id = :id AND company_id = :cid'
            )->execute(['freq' => $months, 'up' => $now, 'id' => (int) $row['id'], 'cid' => $companyId]);
            $itemId = (int) $row['id'];
        } else {
            $pdo->prepare(
                'INSERT INTO corp_programme_items (company_id, programme_id, exam_type_id, category, frequency_months, requirement, template_item_id, created_at, updated_at)
                 VALUES (:cid, :pid, :ex, :cat, :freq, \'company\', 0, :cr, :up)'
            )->execute(['cid' => $companyId, 'pid' => $programmeId, 'ex' => $examTypeId, 'cat' => $category, 'freq' => $months, 'cr' => $now, 'up' => $now]);
            $itemId = (int) $pdo->lastInsertId();
        }
        self::touch($companyId, $programmeId);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'programme_item_added', 'corp_programme', $programmeId,
            ['item_id' => $itemId, 'exam' => $exam['name'], 'category' => $category, 'months' => $months]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function removeItem(int $companyId, int $actorId, int $programmeId, int $itemId, string $reason): array
    {
        $item = self::item($companyId, $programmeId, $itemId);
        if ($item === null || (int) $item['is_removed'] === 1) {
            return ['ok' => false, 'error' => 'Item not found.'];
        }
        $reason = mb_substr(trim($reason), 0, 500);
        if ($item['requirement'] === 'mandatory' && mb_strlen($reason) < self::MIN_REASON) {
            return ['ok' => false, 'error' => 'This check is marked Mandatory. Write why you are removing it (at least ' . self::MIN_REASON . ' characters); it will show as an exception in compliance reports.'];
        }
        Database::connection()->prepare(
            'UPDATE corp_programme_items SET is_removed = 1, removed_reason = :r, removed_by = :by, removed_at = :at, updated_at = :up WHERE id = :id AND company_id = :cid'
        )->execute(['r' => $reason !== '' ? $reason : null, 'by' => $actorId, 'at' => LocalDate::nowUtc(), 'up' => LocalDate::nowUtc(), 'id' => $itemId, 'cid' => $companyId]);
        self::touch($companyId, $programmeId);
        CorpAudit::log($companyId, 'corp_user', $actorId, $item['requirement'] === 'mandatory' ? 'programme_mandatory_removed' : 'programme_item_removed', 'corp_programme', $programmeId,
            ['item_id' => $itemId, 'exam' => $item['exam_name'], 'category' => $item['category'], 'reason' => $reason]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function setFrequency(int $companyId, int $actorId, int $programmeId, int $itemId, int $months): array
    {
        $item = self::item($companyId, $programmeId, $itemId);
        if ($item === null || (int) $item['is_removed'] === 1 || $item['category'] !== 'periodic') {
            return ['ok' => false, 'error' => 'Only periodic checks have a frequency.'];
        }
        if ($months < 1 || $months > 120) {
            return ['ok' => false, 'error' => 'Frequency must be 1–120 months.'];
        }
        if (($why = self::frequencyRule($item, $months)) !== null) {
            return ['ok' => false, 'error' => $why];
        }
        Database::connection()->prepare('UPDATE corp_programme_items SET frequency_months = :m, updated_at = :up WHERE id = :id AND company_id = :cid')
            ->execute(['m' => $months, 'up' => LocalDate::nowUtc(), 'id' => $itemId, 'cid' => $companyId]);
        self::touch($companyId, $programmeId);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'programme_frequency_changed', 'corp_programme', $programmeId,
            ['item_id' => $itemId, 'exam' => $item['exam_name'], 'from' => (int) $item['frequency_months'], 'to' => $months]);

        return ['ok' => true];
    }

    /**
     * Mandatory periodic items may only become MORE frequent than the template.
     *
     * @param array<string, mixed> $item  needs requirement + template_frequency (or template_item_id)
     */
    public static function frequencyRule(array $item, int $months): ?string
    {
        if (($item['requirement'] ?? '') !== 'mandatory') {
            return null;
        }
        $limit = $item['template_frequency'] ?? null;
        if ($limit === null && (int) ($item['template_item_id'] ?? 0) > 0) {
            $st = Database::connection()->prepare('SELECT frequency_months FROM oh_template_items WHERE id = :id');
            $st->execute(['id' => (int) $item['template_item_id']]);
            $limit = $st->fetchColumn() ?: null;
        }

        return $limit !== null && $months > (int) $limit
            ? 'This check is Mandatory every ' . (int) $limit . ' months. You can make it more frequent, not less.'
            : null;
    }

    /** @return array{ok: bool, error?: string} */
    public static function addAssignment(int $companyId, int $actorId, int $programmeId, string $type, int $targetId): array
    {
        if (self::find($companyId, $programmeId) === null) {
            return ['ok' => false, 'error' => 'Programme not found.'];
        }
        if (!array_key_exists($type, self::TARGETS)) {
            return ['ok' => false, 'error' => 'Choose who this programme applies to.'];
        }
        if ($type === 'all') {
            $targetId = 0;
        } else {
            $table = ['job_role' => 'corp_job_roles', 'department' => 'corp_departments', 'branch' => 'corp_branches', 'employee' => 'corp_employments'][$type];
            $st = Database::connection()->prepare("SELECT id FROM {$table} WHERE id = :id AND company_id = :cid LIMIT 1");
            $st->execute(['id' => $targetId, 'cid' => $companyId]);
            if ($st->fetchColumn() === false) {
                return ['ok' => false, 'error' => 'Choose a ' . strtolower(self::TARGETS[$type]) . ' from the list.'];
            }
        }
        $st = Database::connection()->prepare(
            'INSERT IGNORE INTO corp_programme_assignments (company_id, programme_id, target_type, target_id, created_by, created_at)
             VALUES (:cid, :pid, :tt, :tid, :by, :at)'
        );
        $st->execute(['cid' => $companyId, 'pid' => $programmeId, 'tt' => $type, 'tid' => $targetId, 'by' => $actorId, 'at' => LocalDate::nowUtc()]);
        if ($st->rowCount() === 0) {
            return ['ok' => false, 'error' => 'It already applies to them.'];
        }
        self::touch($companyId, $programmeId);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'programme_assigned', 'corp_programme', $programmeId, ['target_type' => $type, 'target_id' => $targetId]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function removeAssignment(int $companyId, int $actorId, int $programmeId, int $assignmentId): array
    {
        $st = Database::connection()->prepare('DELETE FROM corp_programme_assignments WHERE id = :id AND company_id = :cid AND programme_id = :pid');
        $st->execute(['id' => $assignmentId, 'cid' => $companyId, 'pid' => $programmeId]);
        if ($st->rowCount() === 0) {
            return ['ok' => false, 'error' => 'Not found.'];
        }
        self::touch($companyId, $programmeId);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'programme_unassigned', 'corp_programme', $programmeId, ['assignment_id' => $assignmentId]);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    private static function item(int $companyId, int $programmeId, int $itemId): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT pi.*, et.name AS exam_name, ti.frequency_months AS template_frequency
               FROM corp_programme_items pi JOIN oh_exam_types et ON et.id = pi.exam_type_id
               LEFT JOIN oh_template_items ti ON ti.id = pi.template_item_id
              WHERE pi.id = :id AND pi.company_id = :cid AND pi.programme_id = :pid LIMIT 1'
        );
        $st->execute(['id' => $itemId, 'cid' => $companyId, 'pid' => $programmeId]);

        return $st->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    private static function exam(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM oh_exam_types WHERE id = :id AND is_active = 1');
        $st->execute(['id' => $id]);

        return $st->fetch() ?: null;
    }

    private static function touch(int $companyId, int $programmeId): void
    {
        Database::connection()->prepare('UPDATE corp_programmes SET updated_at = :up WHERE id = :id AND company_id = :cid')
            ->execute(['up' => LocalDate::nowUtc(), 'id' => $programmeId, 'cid' => $companyId]);
    }
}
