<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\ImpersonationService;
use App\Services\JwtService;

final class ImpersonateController
{
    public function enter(Request $request, string $token): Response
    {
        $result = ImpersonationService::consume($token);
        if ($result === null) {
            return Response::html('<p class="p-8 font-sans text-red-600">Impersonation link expired or invalid.</p>', 410);
        }

        $user = $result['user'];
        $clinicId = (int) $user['clinic_id'];
        $access = JwtService::issue($user, $clinicId);
        $refresh = AuthService::establishSession($user, $request, true);
        JwtService::setAuthCookies($access, $refresh);

        $secure = ($_ENV['APP_ENV'] ?? 'local') !== 'local';
        setcookie('mc_impersonate', json_encode([
            'by' => $result['admin_name'],
            'until' => time() + 1800,
        ]), [
            'expires' => time() + 1800,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Strict',
        ]);

        return Response::redirect('/dashboard');
    }

    public function exit(Request $request): Response
    {
        JwtService::clearAuthCookies();
        setcookie('mc_impersonate', '', ['expires' => time() - 3600, 'path' => '/']);

        return Response::redirect('/admin/clinics');
    }
}
