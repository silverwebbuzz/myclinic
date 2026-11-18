<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\CorpSessionService;
use App\Services\Corporate\RoleService;
use App\Services\Corporate\UserAdminService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/users — the company's own logins and invitations, plus the public invite-accept page. */
final class CorpUsersController
{
    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard('users.view')) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $canManage = CorpAuth::can('users.manage');

        return Response::html(View::render('corporate/users', [
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'users' => UserAdminService::listUsers($cid),
            'invites' => $canManage ? UserAdminService::pendingInvites($cid) : [],
            'assignable' => $canManage ? RoleService::assignableRoles($cid, CorpAuth::permissions()) : [],
            'branches' => $canManage ? \App\Services\Corporate\StructureService::branches($cid, true) : [],
            'canManage' => $canManage,
            'old' => SessionFlash::pull('corp_invite_old') ?? [],
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }

    public function invite(Request $request): Response
    {
        if ($deny = CorpAuth::guard('users.manage')) {
            return $deny;
        }
        $email = (string) ($request->post['email'] ?? '');
        $name = (string) ($request->post['name'] ?? '');
        $res = UserAdminService::invite((int) CorpContext::companyId(), (int) CorpContext::userId(), CorpAuth::permissions(),
            $email, $name, (int) ($request->post['role_id'] ?? 0), (int) ($request->post['branch_id'] ?? 0));
        if (!$res['ok']) {
            SessionFlash::put('corp_invite_old', ['email' => $email, 'name' => $name, 'role_id' => (int) ($request->post['role_id'] ?? 0), 'branch_id' => (int) ($request->post['branch_id'] ?? 0)]);
        }

        return $this->back($res, 'Invitation sent to ' . strtolower(trim($email)) . '.');
    }

    public function resendInvite(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('users.manage')) {
            return $deny;
        }

        return $this->back(UserAdminService::resendInvite((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id), 'Invitation sent again.');
    }

    public function revokeInvite(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('users.manage')) {
            return $deny;
        }

        return $this->back(UserAdminService::revokeInvite((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id), 'Invitation cancelled.');
    }

    public function updateRoles(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('users.manage')) {
            return $deny;
        }
        $roleIds = $request->post['role_ids'] ?? [];
        $branches = $request->post['branch_for'] ?? [];

        return $this->back(UserAdminService::setRoles((int) CorpContext::companyId(), (int) CorpContext::userId(), CorpAuth::permissions(),
            (int) $id, is_array($roleIds) ? $roleIds : [], is_array($branches) ? array_map('intval', $branches) : []), 'Roles updated.');
    }

    public function disable(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('users.manage')) {
            return $deny;
        }

        return $this->back(UserAdminService::setActive((int) CorpContext::companyId(), (int) CorpContext::userId(), CorpAuth::permissions(), (int) $id, false),
            'User disabled and signed out everywhere.');
    }

    public function enable(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('users.manage')) {
            return $deny;
        }

        return $this->back(UserAdminService::setActive((int) CorpContext::companyId(), (int) CorpContext::userId(), CorpAuth::permissions(), (int) $id, true),
            'User enabled.');
    }

    public function resetMfa(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('users.manage')) {
            return $deny;
        }

        return $this->back(UserAdminService::resetMfa((int) CorpContext::companyId(), (int) CorpContext::userId(), CorpAuth::permissions(), (int) $id),
            'Two-step sign-in reset. They\'ll set it up again next time they sign in, and we\'ve emailed them.');
    }

    // ------------------------------------------------------------------
    // Public: accept an invitation  (/company/invite?token=…)
    // ------------------------------------------------------------------

    public function showAccept(Request $request): Response
    {
        $token = (string) ($request->query['token'] ?? '');

        return $this->acceptPage($token, null, '', 200);
    }

    public function accept(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::html('Service temporarily unavailable. Please try again shortly.', 503);
        }
        $token = (string) ($request->post['token'] ?? '');
        $name = (string) ($request->post['name'] ?? '');
        $res = UserAdminService::acceptInvite($token, $name, (string) ($request->post['password'] ?? ''), (string) ($request->post['password_confirm'] ?? ''));
        if (!$res['ok']) {
            return $this->acceptPage($token, $res['error'] ?? 'Could not accept the invitation.', $name, 422);
        }
        CorpSessionService::start((int) $res['company_id'], (int) $res['user_id']);

        return Response::redirect('/company/dashboard?welcome=1');
    }

    // ------------------------------------------------------------------

    private function acceptPage(string $token, ?string $error, string $name, int $status): Response
    {
        $inv = UserAdminService::findInviteByToken($token);
        $role = $inv !== null ? RoleService::findRole((int) $inv['company_id'], (int) $inv['role_id']) : null;

        return Response::html(View::render('corporate/invite_accept', [
            'csrf' => CsrfService::token(),
            'token' => $token,
            'invite' => $inv,
            'roleName' => (string) ($role['name'] ?? ''),
            'name' => $name !== '' ? $name : (string) ($inv['name'] ?? ''),
            'error' => $error,
        ]), $inv === null && $error === null ? 410 : $status);
    }

    /** @param array{ok: bool, error?: string} $res */
    private function back(array $res, string $okMessage): Response
    {
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? $okMessage : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/company/users');
    }
}
