<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Roles = named bundles of permissions. System roles (company_id = 0) are
 * shared and read-only; companies can add custom roles.
 *
 * Two anti-escalation rules, enforced here AND in UserAdminService:
 *  1. Nobody can create a role, or give someone a role, with a permission
 *     they don't hold themselves (clinical permissions excepted for the one
 *     system clinical role — they are never grantable to custom roles).
 *  2. Clinical permissions can never be added to a custom role.
 */
final class RoleService
{
    /** @return list<array<string, mixed>> all permissions, catalogue order */
    public static function allPermissions(): array
    {
        return Database::connection()->query(
            'SELECT code, module, label, is_clinical, phase FROM corp_permissions ORDER BY sort_order, code'
        )->fetchAll();
    }

    /**
     * System + this company's custom roles, with user counts.
     *
     * @return list<array<string, mixed>>
     */
    public static function rolesForCompany(int $companyId): array
    {
        $st = Database::connection()->prepare(
            'SELECT r.*, (SELECT COUNT(*) FROM corp_user_roles ur WHERE ur.role_id = r.id AND ur.company_id = :cid) AS user_count
               FROM corp_roles r
              WHERE r.company_id IN (0, :cid2)
              ORDER BY r.is_system DESC, r.sort_order, r.name'
        );
        $st->execute(['cid' => $companyId, 'cid2' => $companyId]);
        $roles = $st->fetchAll();
        $perms = self::permissionsByRole(array_map(static fn (array $r): int => (int) $r['id'], $roles));
        foreach ($roles as &$r) {
            $r['permissions'] = $perms[(int) $r['id']] ?? [];
        }
        unset($r);

        return $roles;
    }

    /** @return array<string, mixed>|null a system role or this company's own */
    public static function findRole(int $companyId, int $roleId): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_roles WHERE id = :id AND company_id IN (0, :cid) LIMIT 1');
        $st->execute(['id' => $roleId, 'cid' => $companyId]);
        $role = $st->fetch();
        if (!$role) {
            return null;
        }
        $role['permissions'] = self::permissionsByRole([(int) $role['id']])[(int) $role['id']] ?? [];

        return $role;
    }

    /**
     * Can someone holding $actorPerms give out this role?
     *
     * @param array<string, mixed> $role with 'permissions'
     * @param array<string, true> $actorPerms
     */
    public static function canAssign(array $role, array $actorPerms): bool
    {
        // Branch-scoped roles (Branch Manager) are assignable; UserAdminService
        // requires a valid branch of the company for them.
        return self::isWithin($role['permissions'] ?? [], $actorPerms, (int) ($role['is_clinical'] ?? 0) === 1);
    }

    /**
     * Roles the actor may give out, for the invite / edit forms.
     *
     * @param array<string, true> $actorPerms
     * @return list<array<string, mixed>>
     */
    public static function assignableRoles(int $companyId, array $actorPerms): array
    {
        return array_values(array_filter(
            self::rolesForCompany($companyId),
            static fn (array $r): bool => self::canAssign($r, $actorPerms),
        ));
    }

    /**
     * Every permission in $wanted is held by the actor. Clinical permissions
     * are skipped when $clinicalRole (the system clinical role): giving the
     * company doctor their role doesn't require the admin to be a doctor.
     *
     * @param list<string> $wanted
     * @param array<string, true> $actorPerms
     */
    public static function isWithin(array $wanted, array $actorPerms, bool $clinicalRole = false): bool
    {
        $clinical = $clinicalRole ? self::clinicalCodes() : [];
        foreach ($wanted as $code) {
            if (!isset($actorPerms[$code]) && !in_array($code, $clinical, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create or update a custom role.
     *
     * @param list<string> $permissions
     * @param array<string, true> $actorPerms
     * @return array{ok: bool, error?: string, role_id?: int}
     */
    public static function saveCustomRole(int $companyId, ?int $roleId, string $name, string $description, array $permissions, array $actorPerms): array
    {
        $name = trim($name);
        $description = trim($description);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return ['ok' => false, 'error' => 'Please give the role a name (2–80 characters).'];
        }
        if (mb_strlen($description) > 255) {
            return ['ok' => false, 'error' => 'Description is too long.'];
        }

        $catalogue = [];
        foreach (self::allPermissions() as $p) {
            $catalogue[(string) $p['code']] = (int) $p['is_clinical'] === 1;
        }
        $permissions = array_values(array_unique(array_filter($permissions, static fn ($c): bool => is_string($c) && array_key_exists($c, $catalogue))));
        foreach ($permissions as $code) {
            if ($catalogue[$code]) {
                return ['ok' => false, 'error' => 'Clinical permissions can\'t be added to custom roles.'];
            }
        }
        if ($permissions === []) {
            return ['ok' => false, 'error' => 'Choose at least one permission.'];
        }
        if (!self::isWithin($permissions, $actorPerms)) {
            return ['ok' => false, 'error' => 'You can only include permissions you have yourself.'];
        }

        $pdo = Database::connection();
        if ($roleId !== null) {
            $existing = self::findRole($companyId, $roleId);
            if ($existing === null || (int) $existing['company_id'] !== $companyId || (int) $existing['is_system'] === 1) {
                return ['ok' => false, 'error' => 'Built-in roles can\'t be changed. Create a custom role instead.'];
            }
            if (!self::isWithin($existing['permissions'], $actorPerms)) {
                return ['ok' => false, 'error' => 'This role has permissions you don\'t have, so you can\'t edit it.'];
            }
        }
        if (self::nameTaken($companyId, $name, $roleId)) {
            return ['ok' => false, 'error' => 'A role with this name already exists.'];
        }

        $pdo->beginTransaction();
        try {
            if ($roleId === null) {
                $pdo->prepare(
                    'INSERT INTO corp_roles (company_id, code, name, description, scope_type, is_system, is_clinical, sort_order, created_at)
                     VALUES (:cid, :code, :name, :descr, \'company\', 0, 0, 500, :at)'
                )->execute([
                    'cid' => $companyId,
                    'code' => self::uniqueCode($companyId, $name),
                    'name' => $name,
                    'descr' => $description !== '' ? $description : null,
                    'at' => LocalDate::nowUtc(),
                ]);
                $roleId = (int) $pdo->lastInsertId();
                $action = 'role_created';
            } else {
                $pdo->prepare('UPDATE corp_roles SET name = :name, description = :descr WHERE id = :id AND company_id = :cid')
                    ->execute(['name' => $name, 'descr' => $description !== '' ? $description : null, 'id' => $roleId, 'cid' => $companyId]);
                $pdo->prepare('DELETE FROM corp_role_permissions WHERE role_id = :rid')->execute(['rid' => $roleId]);
                $action = 'role_updated';
            }
            $ins = $pdo->prepare('INSERT INTO corp_role_permissions (role_id, permission_code) VALUES (:rid, :code)');
            foreach ($permissions as $code) {
                $ins->execute(['rid' => $roleId, 'code' => $code]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[RoleService::saveCustomRole] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save the role. Please try again.'];
        }
        CorpAudit::byUser($action, 'corp_role', $roleId, ['name' => $name, 'permissions' => $permissions]);

        return ['ok' => true, 'role_id' => $roleId];
    }

    /** @return array{ok: bool, error?: string} */
    public static function deleteCustomRole(int $companyId, int $roleId): array
    {
        $role = self::findRole($companyId, $roleId);
        if ($role === null || (int) $role['company_id'] !== $companyId || (int) $role['is_system'] === 1) {
            return ['ok' => false, 'error' => 'Built-in roles can\'t be deleted.'];
        }
        $st = Database::connection()->prepare('SELECT COUNT(*) FROM corp_user_roles WHERE role_id = :rid AND company_id = :cid');
        $st->execute(['rid' => $roleId, 'cid' => $companyId]);
        if ((int) $st->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'This role is still given to someone. Change their roles first.'];
        }
        $st = Database::connection()->prepare(
            'SELECT COUNT(*) FROM corp_invitations WHERE role_id = :rid AND company_id = :cid AND accepted_at IS NULL AND revoked_at IS NULL'
        );
        $st->execute(['rid' => $roleId, 'cid' => $companyId]);
        if ((int) $st->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'A pending invitation uses this role. Cancel it first.'];
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM corp_role_permissions WHERE role_id = :rid')->execute(['rid' => $roleId]);
        $pdo->prepare('DELETE FROM corp_roles WHERE id = :id AND company_id = :cid AND is_system = 0')->execute(['id' => $roleId, 'cid' => $companyId]);
        CorpAudit::byUser('role_deleted', 'corp_role', $roleId, ['name' => $role['name']]);

        return ['ok' => true];
    }

    /** Permission codes of the user's current roles (used to protect higher-ranked users). */
    public static function userRoleIds(int $companyId, int $userId): array
    {
        $st = Database::connection()->prepare('SELECT role_id FROM corp_user_roles WHERE company_id = :cid AND user_id = :uid');
        $st->execute(['cid' => $companyId, 'uid' => $userId]);

        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    // ------------------------------------------------------------------

    /**
     * @param list<int> $roleIds
     * @return array<int, list<string>>
     */
    private static function permissionsByRole(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $roleIds));
        $out = [];
        foreach (Database::connection()->query("SELECT role_id, permission_code FROM corp_role_permissions WHERE role_id IN ($in)")->fetchAll() as $row) {
            $out[(int) $row['role_id']][] = (string) $row['permission_code'];
        }

        return $out;
    }

    /** @return list<string> */
    private static function clinicalCodes(): array
    {
        static $codes = null;
        if ($codes === null) {
            $codes = array_map('strval', Database::connection()->query('SELECT code FROM corp_permissions WHERE is_clinical = 1')->fetchAll(\PDO::FETCH_COLUMN));
        }

        return $codes;
    }

    private static function nameTaken(int $companyId, string $name, ?int $exceptId): bool
    {
        $st = Database::connection()->prepare(
            'SELECT id FROM corp_roles WHERE company_id IN (0, :cid) AND LOWER(name) = LOWER(:name) LIMIT 1'
        );
        $st->execute(['cid' => $companyId, 'name' => $name]);
        $id = $st->fetchColumn();

        return $id !== false && (int) $id !== $exceptId;
    }

    private static function uniqueCode(int $companyId, string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_') ?: 'role';
        $base = 'c_' . substr($base, 0, 30);
        $code = $base;
        $st = Database::connection()->prepare('SELECT 1 FROM corp_roles WHERE company_id = :cid AND code = :code LIMIT 1');
        for ($i = 2; $i < 100; $i++) {
            $st->execute(['cid' => $companyId, 'code' => $code]);
            if ($st->fetchColumn() === false) {
                return $code;
            }
            $code = $base . '_' . $i;
        }

        return $base . '_' . bin2hex(random_bytes(3));
    }
}
