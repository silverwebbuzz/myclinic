<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Http\Response;
use App\Services\CsrfService;
use App\Support\View;

/**
 * Permission checks for the signed-in company user. Code asks for a
 * permission, never for a role:
 *
 *   if ($deny = CorpAuth::guard('users.manage')) { return $deny; }
 *   <?php if (CorpAuth::can('employees.import')): ?> … <?php endif; ?>
 *
 * Clinical permissions (corp_permissions.is_clinical) only count when they
 * come from a clinical role (corp_roles.is_clinical) — a second wall behind
 * RoleService, which never lets them be added to other roles.
 */
final class CorpAuth
{
    /** @var array<string, true>|null */
    private static ?array $perms = null;

    /** @var array{0: string, 1: list<int>|null}|null cached [permission, scope] */
    private static ?array $branchScope = null;

    /** @return array<string, true> permission code => true */
    public static function permissions(): array
    {
        if (self::$perms === null) {
            $cid = CorpContext::companyId();
            $uid = CorpContext::userId();
            self::$perms = ($cid !== null && $uid !== null) ? self::permissionsFor($cid, $uid) : [];
        }

        return self::$perms;
    }

    public static function can(string $permission): bool
    {
        return isset(self::permissions()[$permission]);
    }

    /** null when allowed; otherwise a 403 response to return. */
    public static function guard(string $permission): ?Response
    {
        if (self::can($permission)) {
            return null;
        }
        CorpAudit::byUser('permission_denied', null, null, ['permission' => $permission]);

        return Response::html(View::render('corporate/forbidden', [
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
        ]), 403);
    }

    /** @return array<string, true> */
    public static function permissionsFor(int $companyId, int $userId): array
    {
        $st = Database::connection()->prepare(
            'SELECT DISTINCT rp.permission_code, p.is_clinical, r.is_clinical AS role_is_clinical
               FROM corp_user_roles ur
               JOIN corp_roles r ON r.id = ur.role_id AND (r.company_id = 0 OR r.company_id = ur.company_id)
               JOIN corp_role_permissions rp ON rp.role_id = r.id
               JOIN corp_permissions p ON p.code = rp.permission_code
              WHERE ur.company_id = :cid AND ur.user_id = :uid'
        );
        $st->execute(['cid' => $companyId, 'uid' => $userId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            if ((int) $row['is_clinical'] === 1 && (int) $row['role_is_clinical'] !== 1) {
                continue;
            }
            $out[(string) $row['permission_code']] = true;
        }

        return $out;
    }

    /**
     * Which branches' employees the signed-in user may see for $permission:
     * null = every branch (some role giving it is company-wide), otherwise the
     * branch ids of their branch-scoped roles (Branch Manager). [] = none.
     *
     * @return list<int>|null
     */
    public static function branchScope(string $permission = 'employees.view'): ?array
    {
        if (self::$branchScope !== null && self::$branchScope[0] === $permission) {
            return self::$branchScope[1];
        }
        $cid = CorpContext::companyId();
        $uid = CorpContext::userId();
        $scope = ($cid !== null && $uid !== null) ? self::branchScopeFor($cid, $uid, $permission) : [];
        self::$branchScope = [$permission, $scope];

        return $scope;
    }

    /** @return list<int>|null */
    public static function branchScopeFor(int $companyId, int $userId, string $permission): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT r.scope_type, ur.scope_id
               FROM corp_user_roles ur
               JOIN corp_roles r ON r.id = ur.role_id AND (r.company_id = 0 OR r.company_id = ur.company_id)
               JOIN corp_role_permissions rp ON rp.role_id = r.id AND rp.permission_code = :perm
              WHERE ur.company_id = :cid AND ur.user_id = :uid'
        );
        $st->execute(['perm' => $permission, 'cid' => $companyId, 'uid' => $userId]);
        $ids = [];
        foreach ($st->fetchAll() as $row) {
            if ($row['scope_type'] !== 'branch') {
                return null;
            }
            if ((int) $row['scope_id'] > 0) {
                $ids[] = (int) $row['scope_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Test hook: pretend the current user has exactly these permissions. */
    public static function prime(array $codes): void
    {
        self::$perms = array_fill_keys($codes, true);
    }

    public static function reset(): void
    {
        self::$perms = null;
        self::$branchScope = null;
    }
}
