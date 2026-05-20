<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\SessionService;
use App\Support\Layout;

final class SettingsController
{
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
        $current = $request->post['current_password'] ?? '';
        $password = $request->post['password'] ?? '';
        $confirm = $request->post['password_confirm'] ?? '';

        if (!password_verify($current, $user['password_hash'] ?? '')) {
            return Response::html(Layout::page('settings/password', [
                'error' => 'Current password is incorrect.',
                'success' => null,
            ], 'Change password'), 422);
        }

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
