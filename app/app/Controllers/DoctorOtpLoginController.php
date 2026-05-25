<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CsrfService;
use App\Services\DoctorOtpService;
use App\Services\JwtService;
use App\Support\View;

/**
 * Passwordless OTP login for doctors approved via the claim queue.
 * Lives next to the regular /login (which is email + password) so
 * approved doctors get a friction-free sign-in without an email
 * being provisioned.
 */
final class DoctorOtpLoginController
{
    public function show(Request $request): Response
    {
        return Response::html(View::render('auth/doctor_otp_login', [
            'csrf'    => CsrfService::token(),
            'step'    => 'phone',
            'phone'   => '',
            'error'   => null,
            'devCode' => null,
        ]));
    }

    public function sendOtp(Request $request): Response
    {
        $phone = (string) ($request->post['phone'] ?? '');
        $res   = DoctorOtpService::issue($phone);

        if (!$res['ok']) {
            // Render the same form with an error; keep the phone so the user
            // doesn't retype.
            return Response::html(View::render('auth/doctor_otp_login', [
                'csrf'    => CsrfService::token(),
                'step'    => 'phone',
                'phone'   => $phone,
                'error'   => $this->errorText($res['error'] ?? 'unknown', $res['retry_after'] ?? null),
                'devCode' => null,
            ]), 400);
        }

        return Response::html(View::render('auth/doctor_otp_login', [
            'csrf'    => CsrfService::token(),
            'step'    => 'code',
            'phone'   => DoctorOtpService::normalizePhone($phone),
            'error'   => null,
            'devCode' => $res['dev_code'] ?? null,
        ]));
    }

    public function verifyOtp(Request $request): Response
    {
        $phone = (string) ($request->post['phone'] ?? '');
        $code  = (string) ($request->post['code']  ?? '');
        $res   = DoctorOtpService::verify($phone, $code);

        if (!$res['ok']) {
            return Response::html(View::render('auth/doctor_otp_login', [
                'csrf'    => CsrfService::token(),
                'step'    => 'code',
                'phone'   => DoctorOtpService::normalizePhone($phone),
                'error'   => $this->errorText($res['error'] ?? 'unknown'),
                'devCode' => null,
            ]), 400);
        }

        $user = $res['user'];
        $clinicId = (int) $user['clinic_id'];

        // Issue the same cookies the password-based login uses.
        $token   = JwtService::issue($user, $clinicId);
        $refresh = AuthService::establishSession($user, $request, true);
        JwtService::setAuthCookies($token, $refresh);

        QueryBuilder::table('users')->where('id', '=', (int) $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        AuditService::log($request, 'LOGIN_OTP', 'users', (int) $user['id']);

        return Response::redirect('/dashboard');
    }

    private function errorText(string $code, ?int $retryAfter = null): string
    {
        return match ($code) {
            'invalid_phone'     => 'That number doesn\'t look right.',
            'no_account'        => 'No doctor account found for that number. Have you been approved yet?',
            'resend_too_soon'   => $retryAfter
                                    ? "Please wait {$retryAfter}s before requesting another code."
                                    : 'Please wait before requesting another code.',
            'invalid_code'      => 'That code is incorrect. Try again.',
            'expired'           => 'Code expired. Tap Resend.',
            'too_many_attempts' => 'Too many attempts. Request a new code.',
            'no_code_issued'    => 'No active code. Tap Send code.',
            'invalid_input'     => 'Enter the 6-digit code.',
            default             => 'Something went wrong. Please try again.',
        };
    }
}
