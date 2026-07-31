<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\PatientIdentityAuthService;
use App\Services\RecaptchaService;

/** Public patient identity OTP — same flow as eclinicpro.com/api/patient_auth.php */
final class PatientAuthController
{
    public function me(Request $request): Response
    {
        return Response::json([
            'ok' => true,
            'patient' => PatientIdentityAuthService::publicPatient(PatientIdentityAuthService::current()),
        ]);
    }

    public function sendOtp(Request $request): Response
    {
        $payload = $this->jsonBody($request);
        $phone = (string) ($payload['phone'] ?? '');
        $intent = (string) ($payload['intent'] ?? '');

        if ($phone === '') {
            return Response::json(['ok' => false, 'error' => 'phone_required'], 400);
        }

        $captchaToken = (string) ($payload['g-recaptcha-response'] ?? $payload['captcha_token'] ?? '');
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!RecaptchaService::verify($captchaToken !== '' ? $captchaToken : null, is_string($remoteIp) ? $remoteIp : null)) {
            return Response::json(['ok' => false, 'error' => 'captcha_failed'], 400);
        }

        $res = PatientIdentityAuthService::sendOtp($phone, $intent);
        if (!$res['ok']) {
            $status = match ($res['error'] ?? '') {
                'invalid_phone' => 400,
                'account_not_found' => 404,
                'account_exists' => 409,
                'resend_too_soon' => 429,
                'not_whatsapp' => 400,
                'wa_template_missing', 'wa_template_unapproved', 'wa_send_failed' => 503,
                default => 500,
            };

            return Response::json($res, $status);
        }

        return Response::json([
            'ok' => true,
            'mode' => $res['mode'] ?? 'live',
            'exists' => $res['exists'] ?? false,
            'name_hint' => $res['name_hint'] ?? null,
            'dev_code' => $res['dev_code'] ?? null,
        ]);
    }

    public function verifyOtp(Request $request): Response
    {
        $payload = $this->jsonBody($request);
        $phone = (string) ($payload['phone'] ?? '');
        $code = (string) ($payload['code'] ?? '');
        $name = isset($payload['name']) ? (string) $payload['name'] : null;

        if ($phone === '' || $code === '') {
            return Response::json(['ok' => false, 'error' => 'phone_and_code_required'], 400);
        }

        $captchaToken = (string) ($payload['g-recaptcha-response'] ?? $payload['captcha_token'] ?? '');
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!RecaptchaService::verify($captchaToken !== '' ? $captchaToken : null, is_string($remoteIp) ? $remoteIp : null)) {
            return Response::json(['ok' => false, 'error' => 'captcha_failed'], 400);
        }

        $res = PatientIdentityAuthService::verifyOtp($phone, $code, $name);
        if (!$res['ok']) {
            $status = match ($res['error'] ?? '') {
                'invalid_code', 'invalid_input' => 400,
                'expired', 'no_code_issued' => 410,
                'too_many_attempts' => 429,
                default => 500,
            };

            return Response::json(['ok' => false, 'error' => $res['error']], $status);
        }

        return Response::json([
            'ok' => true,
            'is_new' => $res['is_new'] ?? false,
            'patient' => PatientIdentityAuthService::publicPatient($res['identity'] ?? null),
        ]);
    }

    public function logout(Request $request): Response
    {
        PatientIdentityAuthService::logout();

        return Response::json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        $raw = $request->rawBody ?? '';
        if ($raw !== '' && str_starts_with(trim($raw), '{')) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($request->post) ? $request->post : [];
    }
}
