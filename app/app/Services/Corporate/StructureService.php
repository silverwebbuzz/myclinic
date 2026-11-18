<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\CountryRepo;
use App\Support\Locale\LocalDate;

/**
 * A company's structure: branches (work locations), departments, job roles.
 * Nothing is ever deleted (employees and screening history point at these);
 * items are deactivated instead, which hides them from pickers.
 */
final class StructureService
{
    public const BRANCH_TYPES = [
        'office' => 'Office', 'factory' => 'Factory / plant', 'warehouse' => 'Warehouse', 'site' => 'Project / construction site',
        'yard' => 'Yard / port', 'vessel' => 'Vessel / offshore', 'clinic' => 'Clinic / medical room', 'other' => 'Other',
    ];
    public const RISK_LEVELS = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * @param list<int>|null $onlyIds branch scope (null = all)
     * @return list<array<string, mixed>> + employee_count
     */
    public static function branches(int $companyId, bool $activeOnly = false, ?array $onlyIds = null): array
    {
        $sql = 'SELECT b.*, (SELECT COUNT(*) FROM corp_employments e WHERE e.company_id = b.company_id AND e.branch_id = b.id AND e.status <> \'terminated\') AS employee_count
                  FROM corp_branches b WHERE b.company_id = :cid';
        if ($activeOnly) {
            $sql .= ' AND b.is_active = 1';
        }
        if ($onlyIds !== null) {
            $sql .= $onlyIds === [] ? ' AND 1 = 0' : ' AND b.id IN (' . implode(',', array_map('intval', $onlyIds)) . ')';
        }
        $st = Database::connection()->prepare($sql . ' ORDER BY b.is_active DESC, b.name');
        $st->execute(['cid' => $companyId]);

        return $st->fetchAll();
    }

    /** @return list<array<string, mixed>> + employee_count */
    public static function departments(int $companyId, bool $activeOnly = false): array
    {
        $st = Database::connection()->prepare(
            'SELECT d.*, (SELECT COUNT(*) FROM corp_employments e WHERE e.company_id = d.company_id AND e.department_id = d.id AND e.status <> \'terminated\') AS employee_count
               FROM corp_departments d WHERE d.company_id = :cid' . ($activeOnly ? ' AND d.is_active = 1' : '') . ' ORDER BY d.is_active DESC, d.name'
        );
        $st->execute(['cid' => $companyId]);

        return $st->fetchAll();
    }

    /** @return list<array<string, mixed>> + employee_count */
    public static function jobRoles(int $companyId, bool $activeOnly = false): array
    {
        $st = Database::connection()->prepare(
            'SELECT j.*, (SELECT COUNT(*) FROM corp_employments e WHERE e.company_id = j.company_id AND e.job_role_id = j.id AND e.status <> \'terminated\') AS employee_count
               FROM corp_job_roles j WHERE j.company_id = :cid' . ($activeOnly ? ' AND j.is_active = 1' : '') . ' ORDER BY j.is_active DESC, j.title'
        );
        $st->execute(['cid' => $companyId]);

        return $st->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(string $kind, int $companyId, int $id): ?array
    {
        $table = self::table($kind);
        $st = Database::connection()->prepare("SELECT * FROM {$table} WHERE id = :id AND company_id = :cid LIMIT 1");
        $st->execute(['id' => $id, 'cid' => $companyId]);

        return $st->fetch() ?: null;
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Create (id 0) or update a branch.
     *
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function saveBranch(int $companyId, int $actorId, int $id, array $post): array
    {
        $s = static fn (string $k, int $max): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);
        $in = [
            'name' => $s('name', 150),
            'code' => strtoupper($s('code', 40)),
            'type' => (string) ($post['type'] ?? 'office'),
            'country_code' => strtoupper($s('country_code', 2)),
            'region_id' => (int) ($post['region_id'] ?? 0),
            'city' => $s('city', 100),
            'address_line1' => $s('address_line1', 200),
            'address_line2' => $s('address_line2', 200),
            'postal_code' => $s('postal_code', 20),
            'timezone' => $s('timezone', 50),
        ];
        $country = CountryRepo::country($in['country_code']);
        if ($in['code'] === '') {
            $in['code'] = self::codeFrom($in['name']);
        }
        if ($in['timezone'] === '' && $country !== null) {
            $in['timezone'] = (string) $country['default_timezone'];
            if ($in['region_id'] > 0 && ($r = CountryRepo::region($in['country_code'], $in['region_id'])) !== null && !empty($r['timezone'])) {
                $in['timezone'] = (string) $r['timezone'];
            }
        }
        $error = match (true) {
            mb_strlen($in['name']) < 2 => 'Please enter the branch name.',
            preg_match('/^[A-Z0-9][A-Z0-9_\-]{0,39}$/', $in['code']) !== 1 => 'Code can use letters, numbers, - and _ only.',
            !array_key_exists($in['type'], self::BRANCH_TYPES) => 'Choose a branch type.',
            $country === null => 'Choose the country this branch is in.',
            $in['region_id'] > 0 && CountryRepo::region($in['country_code'], $in['region_id']) === null => 'Choose a valid ' . strtolower((string) $country['region_label']) . '.',
            !LocalDate::isValidZone($in['timezone']) => 'Choose a valid time zone.',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        if (self::duplicate('corp_branches', 'code', $companyId, $in['code'], $id)) {
            return ['ok' => false, 'error' => 'Another branch already uses code ' . $in['code'] . '.'];
        }
        foreach (['city', 'address_line1', 'address_line2', 'postal_code'] as $k) {
            $in[$k] = $in[$k] !== '' ? $in[$k] : null;
        }
        $in['region_id'] = $in['region_id'] > 0 ? $in['region_id'] : null;

        return self::upsert('corp_branches', 'branch', $companyId, $actorId, $id, $in);
    }

    /** @return array{ok: bool, error?: string, id?: int} */
    public static function saveDepartment(int $companyId, int $actorId, int $id, array $post): array
    {
        $s = static fn (string $k, int $max): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);
        $in = ['name' => $s('name', 150), 'code' => strtoupper($s('code', 40)), 'cost_centre' => $s('cost_centre', 60), 'head_name' => $s('head_name', 120)];
        if (mb_strlen($in['name']) < 2) {
            return ['ok' => false, 'error' => 'Please enter the department name.'];
        }
        if (self::duplicate('corp_departments', 'name', $companyId, $in['name'], $id)) {
            return ['ok' => false, 'error' => 'A department with this name already exists.'];
        }
        foreach (['code', 'cost_centre', 'head_name'] as $k) {
            $in[$k] = $in[$k] !== '' ? $in[$k] : null;
        }

        return self::upsert('corp_departments', 'department', $companyId, $actorId, $id, $in);
    }

    /** @return array{ok: bool, error?: string, id?: int} */
    public static function saveJobRole(int $companyId, int $actorId, int $id, array $post): array
    {
        $s = static fn (string $k, int $max): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);
        $in = [
            'title' => $s('title', 150), 'code' => strtoupper($s('code', 40)), 'description' => $s('description', 500),
            'risk_level' => (string) ($post['risk_level'] ?? 'low'), 'isco_code' => $s('isco_code', 10),
        ];
        $error = match (true) {
            mb_strlen($in['title']) < 2 => 'Please enter the job title.',
            !array_key_exists($in['risk_level'], self::RISK_LEVELS) => 'Choose a risk level.',
            $in['isco_code'] !== '' && preg_match('/^\d{1,4}$/', $in['isco_code']) !== 1 => 'ISCO code is 1–4 digits (e.g. 8343).',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        if (self::duplicate('corp_job_roles', 'title', $companyId, $in['title'], $id)) {
            return ['ok' => false, 'error' => 'A job role with this title already exists.'];
        }
        foreach (['code', 'description', 'isco_code'] as $k) {
            $in[$k] = $in[$k] !== '' ? $in[$k] : null;
        }

        return self::upsert('corp_job_roles', 'job_role', $companyId, $actorId, $id, $in);
    }

    /** Activate / deactivate. Deactivating hides it from pickers; employees keep it. */
    public static function toggle(string $kind, int $companyId, int $actorId, int $id): array
    {
        $table = self::table($kind);
        $row = self::find($kind, $companyId, $id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Not found.'];
        }
        Database::connection()->prepare("UPDATE {$table} SET is_active = 1 - is_active, updated_at = :t WHERE id = :id AND company_id = :cid")
            ->execute(['t' => LocalDate::nowUtc(), 'id' => $id, 'cid' => $companyId]);
        CorpAudit::log($companyId, 'corp_user', $actorId, (int) $row['is_active'] === 1 ? $kind . '_deactivated' : $kind . '_activated', 'corp_' . $kind, $id,
            ['name' => $row['name'] ?? $row['title'] ?? '']);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------

    /** @param array<string, mixed> $in */
    private static function upsert(string $table, string $kind, int $companyId, int $actorId, int $id, array $in): array
    {
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        if ($id > 0) {
            if (self::find($kind, $companyId, $id) === null) {
                return ['ok' => false, 'error' => 'Not found.'];
            }
            $cols = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($in)));
            $pdo->prepare("UPDATE {$table} SET {$cols}, updated_at = :upd_v WHERE id = :id_v AND company_id = :cid_v")
                ->execute($in + ['upd_v' => $now, 'id_v' => $id, 'cid_v' => $companyId]);
            CorpAudit::log($companyId, 'corp_user', $actorId, $kind . '_updated', 'corp_' . $kind, $id, ['name' => $in['name'] ?? $in['title'] ?? '']);

            return ['ok' => true, 'id' => $id];
        }
        $cols = array_keys($in);
        $pdo->prepare(
            "INSERT INTO {$table} (company_id, " . implode(', ', $cols) . ', is_active, created_at, updated_at)
             VALUES (:cid_v, :' . implode(', :', $cols) . ', 1, :cr_v, :up_v)'
        )->execute($in + ['cid_v' => $companyId, 'cr_v' => $now, 'up_v' => $now]);
        $newId = (int) $pdo->lastInsertId();
        CorpAudit::log($companyId, 'corp_user', $actorId, $kind . '_created', 'corp_' . $kind, $newId, ['name' => $in['name'] ?? $in['title'] ?? '']);

        return ['ok' => true, 'id' => $newId];
    }

    private static function duplicate(string $table, string $column, int $companyId, string $value, int $exceptId): bool
    {
        $st = Database::connection()->prepare("SELECT id FROM {$table} WHERE company_id = :cid AND LOWER({$column}) = LOWER(:v) LIMIT 1");
        $st->execute(['cid' => $companyId, 'v' => $value]);
        $found = $st->fetchColumn();

        return $found !== false && (int) $found !== $exceptId;
    }

    private static function table(string $kind): string
    {
        return match ($kind) {
            'branch' => 'corp_branches',
            'department' => 'corp_departments',
            'job_role' => 'corp_job_roles',
            default => throw new \InvalidArgumentException('Unknown structure kind ' . $kind),
        };
    }

    public static function codeFrom(string $name): string
    {
        $code = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));

        return substr($code !== '' ? $code : 'BRANCH', 0, 40);
    }
}
