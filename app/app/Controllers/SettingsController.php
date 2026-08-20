<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\RoleAccessService;
use App\Services\SessionService;
use App\Services\UserProfileService;
use App\Support\Layout;

final class SettingsController
{
    public function showProfile(Request $request): Response
    {
        $user = RequestContext::user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        return Response::html(Layout::page('settings/profile', [
            'error' => null,
            'success' => $request->query['success'] ?? null,
            'userName' => $userId > 0 ? UserProfileService::displayName($userId) : '',
            'username' => (string) ($user['username'] ?? ''),
            'roleLabel' => RoleAccessService::panelRoleLabel($user),
        ], 'My profile'));
    }

    public function updateProfile(Request $request): Response
    {
        $user = RequestContext::user();
        $clinicId = RequestContext::clinicId();
        if ($user === null || $clinicId === null) {
            return Response::redirect('/login');
        }

        $name = (string) ($request->post['name'] ?? '');
        $result = UserProfileService::updateName((int) $user['id'], $clinicId, $name);
        if (!$result['ok']) {
            return Response::html(Layout::page('settings/profile', [
                'error' => $result['error'],
                'success' => null,
                'userName' => $name,
                'username' => (string) ($user['username'] ?? ''),
                'roleLabel' => RoleAccessService::panelRoleLabel($user),
            ], 'My profile'), 422);
        }

        return Response::redirect('/settings/profile?success=1');
    }

    public function showPassword(Request $request): Response
    {
        return Response::html(Layout::page('settings/password', [
            'error' => null,
            'success' => $request->query['success'] ?? null,
        ], 'Change password'));
    }

    public function updatePassword(Request $request): Response
    {
        $user = RequestContext::user();
        $password = $request->post['password'] ?? '';
        $confirm = $request->post['password_confirm'] ?? '';

        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return Response::html(Layout::page('settings/password', [
                'error' => 'New password must be 8+ characters with 1 uppercase and 1 number.',
                'success' => null,
            ], 'Change password'), 422);
        }

        if ($password !== $confirm) {
            return Response::html(Layout::page('settings/password', [
                'error' => 'Passwords do not match.',
                'success' => null,
            ], 'Change password'), 422);
        }

        AuthService::updatePassword((int) $user['id'], $password);

        return Response::redirect('/settings/password?success=1');
    }

    public function showSessions(Request $request): Response
    {
        $user = RequestContext::user();
        $refresh = $request->cookies['mc_refresh'] ?? null;
        $sessions = SessionService::listForUser((int) $user['id'], $refresh);

        return Response::html(Layout::page('settings/sessions', [
            'sessions' => $sessions,
            'message' => $request->query['message'] ?? null,
        ], 'Active sessions'));
    }

    public function revokeSession(Request $request, string $id): Response
    {
        $user = RequestContext::user();
        $currentRefresh = $request->cookies['mc_refresh'] ?? null;
        $session = SessionService::findByRefreshToken($currentRefresh ?? '');

        if ($session !== null && (int) $session['id'] === (int) $id) {
            SessionService::revokeByRefreshToken($currentRefresh);
            JwtService::clearAuthCookies();

            return Response::redirect('/login');
        }

        SessionService::revoke((int) $id, (int) $user['id']);

        return Response::redirect('/settings/sessions?message=revoked');
    }

    public function revokeOtherSessions(Request $request): Response
    {
        $user = RequestContext::user();
        $currentRefresh = $request->cookies['mc_refresh'] ?? null;
        SessionService::revokeAllExcept((int) $user['id'], $currentRefresh);

        return Response::redirect('/settings/sessions?message=others_revoked');
    }
}
