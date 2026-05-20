<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\StaffInvitationService;
use App\Support\View;

final class AcceptInviteController
{
    public function show(Request $request, string $token): Response
    {
        $invite = StaffInvitationService::findByToken($token);
        if ($invite === null) {
            return Response::html(View::render('auth/invite-invalid', []), 404);
        }

        if ($invite['clinic'] !== null) {
            RequestContext::setClinic($invite['clinic']);
        }

        return Response::html(View::render('auth/accept-invite', [
            'invite' => $invite,
            'token' => $token,
            'csrf' => \App\Services\CsrfService::token(),
            'error' => null,
        ]));
    }

    public function accept(Request $request, string $token): Response
    {
        $invite = StaffInvitationService::findByToken($token);
        if ($invite === null) {
            return Response::html(View::render('auth/invite-invalid', []), 404);
        }

        $password = $request->post['password'] ?? '';
        $confirm = $request->post['password_confirmation'] ?? '';
        if (strlen($password) < 8 || $password !== $confirm) {
            return Response::html(View::render('auth/accept-invite', [
                'invite' => $invite,
                'token' => $token,
                'csrf' => \App\Services\CsrfService::token(),
                'error' => 'Password must be at least 8 characters and match confirmation.',
            ]), 422);
        }

        try {
            $user = StaffInvitationService::accept($token, $password);
            $clinic = QueryBuilder::table('tenants')->where('id', '=', (int) $user['clinic_id'])->first();
            if ($clinic !== null) {
                RequestContext::setClinic($clinic);
            }
            $token = JwtService::issue($user, (int) $user['clinic_id']);
            $refresh = AuthService::establishSession($user, $request, true);
            JwtService::setAuthCookies($token, $refresh);

            return Response::redirect('/dashboard');
        } catch (\Throwable $e) {
            return Response::html(View::render('auth/accept-invite', [
                'invite' => $invite,
                'token' => $token,
                'csrf' => \App\Services\CsrfService::token(),
                'error' => $e->getMessage(),
            ]), 422);
        }
    }
}
