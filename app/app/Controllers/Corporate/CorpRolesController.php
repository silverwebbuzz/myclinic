<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\RoleService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/roles — see what each role can do; create custom roles. */
final class CorpRolesController
{
    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard('users.view')) {
            return $deny;
        }

        return $this->render('corporate/roles', [
            'roles' => RoleService::rolesForCompany((int) CorpContext::companyId()),
        ]);
    }

    public function create(Request $request): Response
    {
        if ($deny = CorpAuth::guard('roles.manage')) {
            return $deny;
        }

        return $this->form(null, (array) (SessionFlash::pull('corp_role_old') ?? []));
    }

    public function edit(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('roles.manage')) {
            return $deny;
        }
        $role = RoleService::findRole((int) CorpContext::companyId(), (int) $id);
        if ($role === null || (int) $role['is_system'] === 1) {
            SessionFlash::put('corp_err', 'Built-in roles can\'t be changed. Create a custom role instead.');

            return Response::redirect('/company/roles');
        }

        return $this->form($role, (array) (SessionFlash::pull('corp_role_old') ?? []));
    }

    public function save(Request $request): Response
    {
        if ($deny = CorpAuth::guard('roles.manage')) {
            return $deny;
        }
        $roleId = (int) ($request->post['role_id'] ?? 0);
        $perms = $request->post['permissions'] ?? [];
        $res = RoleService::saveCustomRole(
            (int) CorpContext::companyId(),
            $roleId > 0 ? $roleId : null,
            (string) ($request->post['name'] ?? ''),
            (string) ($request->post['description'] ?? ''),
            is_array($perms) ? array_values($perms) : [],
            CorpAuth::permissions(),
        );
        if (!$res['ok']) {
            SessionFlash::put('corp_err', $res['error'] ?? 'Could not save the role.');
            SessionFlash::put('corp_role_old', [
                'name' => (string) ($request->post['name'] ?? ''),
                'description' => (string) ($request->post['description'] ?? ''),
                'permissions' => is_array($perms) ? array_values($perms) : [],
            ]);

            return Response::redirect($roleId > 0 ? '/company/roles/' . $roleId : '/company/roles/new');
        }
        SessionFlash::put('corp_ok', 'Role saved.');

        return Response::redirect('/company/roles');
    }

    public function delete(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('roles.manage')) {
            return $deny;
        }
        $res = RoleService::deleteCustomRole((int) CorpContext::companyId(), (int) $id);
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Role deleted.' : ($res['error'] ?? 'Could not delete the role.'));

        return Response::redirect('/company/roles');
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $role
     * @param array<string, mixed> $old
     */
    private function form(?array $role, array $old): Response
    {
        $mine = CorpAuth::permissions();
        $perms = array_values(array_filter(RoleService::allPermissions(), static fn (array $p): bool => (int) $p['is_clinical'] === 0));

        return $this->render('corporate/role_form', [
            'role' => $role,
            'name' => (string) ($old['name'] ?? $role['name'] ?? ''),
            'description' => (string) ($old['description'] ?? $role['description'] ?? ''),
            'selected' => (array) ($old['permissions'] ?? $role['permissions'] ?? []),
            'permissions' => $perms,
            'mine' => $mine,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }
}
