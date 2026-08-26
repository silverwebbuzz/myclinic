<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AccountRecoveryService;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CsrfService;
use App\Services\DoctorOtpService;
use App\Services\GoogleOAuthService;
use App\Services\JwtService;
use App\Services\OnboardingService;
use App\Services\PartnerReferralService;
use App\Services\PasswordResetService;
use App\Services\RecaptchaService;
use App\Services\RoleAccessService;
use App\Services\SessionService;
use App\Services\UsernameService;
use App\Support\SessionFlash;
use App\Support\View;

final class AuthController
{
    public function showRegister(Request $request): Response
    {
        $verifiedPhone = DoctorOtpService::verifiedRegisterPhone();

        $phoneStep = 'phone'; // phone | code | details
        if ($verifiedPhone !== null) {
            $phoneStep = 'details';
        } elseif (!empty($request->query['phone'])) {
            $phoneStep = 'code';
        }

        $pendingPhone = (string) ($request->query['phone'] ?? '');
        $phoneDigits = preg_replace('/\D/', '', (string) ($verifiedPhone ?: $pendingPhone)) ?? '';
        if (str_starts_with($phoneDigits, '91') && strlen($phoneDigits) === 12) {
            $phoneDigits = substr($phoneDigits, 2);
        }
        $defaultUsername = $phoneDigits;

        return Response::html($this->view('auth/register', [
            'csrf' => CsrfService::token(),
            'error' => SessionFlash::pull('register_error') ?? null,
            'info' => SessionFlash::pull('register_info') ?? null,
            'phoneStep' => $phoneStep,
            'verifiedPhone' => $verifiedPhone,
            'pendingPhone' => $pendingPhone,
            'devCode' => SessionFlash::pull('register_dev_code'),
            'old' => [],
            'defaultUsername' => $defaultUsername,
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ]));
    }

    /** POST /register/send-otp */
    public function sendRegisterOtp(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            SessionFlash::put('register_error', 'Session expired. Please try again.');
            return Response::redirect('/register');
        }
        if (!$this->verifyRecaptcha($request)) {
            SessionFlash::put('register_error', 'Please complete the CAPTCHA and try again.');
            return Response::redirect('/register');
        }

        $phone = (string) ($request->post['phone'] ?? '');
        $res = DoctorOtpService::issueRegister($phone);
        $normalized = DoctorOtpService::normalizePhone($phone);

        if (!$res['ok']) {
            SessionFlash::put('register_error', match ($res['error'] ?? '') {
                'already_registered' => 'This mobile number is already registered. Please log in.',
                'invalid_phone' => 'Please enter a valid 10-digit mobile number.',
                'resend_too_soon' => 'Please wait a moment before requesting another code.',
                'not_whatsapp' => 'This number does not appear to have WhatsApp active. Please enter a WhatsApp number.',
                'whatsapp_unavailable' => 'WhatsApp OTP is not configured yet. Please contact support.',
                'wa_send_failed' => 'Could not send WhatsApp code right now. Please try again.',
                default => 'Could not send the code. Please try again.',
            });
            return Response::redirect('/register');
        }

        if (!empty($res['dev_code'])) {
            SessionFlash::put('register_dev_code', $res['dev_code']);
        }
        return Response::redirect('/register?phone=' . rawurlencode($normalized));
    }

    /** POST /register/verify-otp */
    public function verifyRegisterOtp(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            SessionFlash::put('register_error', 'Session expired. Please try again.');
            return Response::redirect('/register');
        }
        if (!$this->verifyRecaptcha($request)) {
            SessionFlash::put('register_error', 'Please complete the CAPTCHA and try again.');
            return Response::redirect('/register?phone=' . rawurlencode(DoctorOtpService::normalizePhone((string) ($request->post['phone'] ?? ''))));
        }

        $phone = (string) ($request->post['phone'] ?? '');
        $code = (string) ($request->post['code'] ?? '');
        $res = DoctorOtpService::verifyRegister($phone, $code);
        $normalized = DoctorOtpService::normalizePhone($phone);

        if (!$res['ok']) {
            SessionFlash::put('register_error', match ($res['error'] ?? '') {
                'invalid_code' => 'That code is incorrect. Try again.',
                'expired' => 'Code expired. Request a new one.',
                'too_many_attempts' => 'Too many attempts. Request a new code.',
                'already_registered' => 'This mobile number is already registered. Please log in.',
                'no_code_issued' => 'No active code. Send a new one.',
                default => 'Could not verify the code. Please try again.',
            });
            return Response::redirect('/register?phone=' . rawurlencode($normalized));
        }

        SessionFlash::put('register_info', 'Phone verified. Complete your clinic details below.');
        return Response::redirect('/register');
    }

    public function register(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::json(['error' => 'Database unavailable'], 503);
        }
        return $this->registerViaPhone($request);
    }

    private function registerViaPhone(Request $request): Response
    {
        $verifiedPhone = DoctorOtpService::verifiedRegisterPhone();
        if ($verifiedPhone === null) {
            SessionFlash::put('register_error', 'Please verify your mobile number before creating an account.');
            return Response::redirect('/register');
        }

        $clinicName = trim($request->post['clinic_name'] ?? '');
        $ownerName = trim($request->post['owner_name'] ?? '');
        $rawUsername = trim($request->post['username'] ?? '');
        $slug = strtolower(trim($request->post['slug'] ?? ''));
        $optionalEmail = strtolower(trim((string) ($request->post['email'] ?? '')));
        $password = (string) ($request->post['password'] ?? '');
        $confirm = (string) ($request->post['password_confirm'] ?? '');

        if ($slug === '' && $clinicName !== '') {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($clinicName)) ?: '';
            $slug = trim($slug, '-');
            $slug = substr($slug, 0, 56);
            if (strlen($slug) < 3) {
                $slug = 'clinic-' . substr((string) time(), -5);
            }
        }
        $slug = $this->resolveUniqueSlug($slug);
        $resolvedUsername = null;
        $error = null;

        if ($ownerName === '' || strlen($ownerName) < 2) {
            $error = 'Your name is required.';
        } elseif ($clinicName === '' || strlen($clinicName) < 2) {
            $error = 'Clinic name is required.';
        } elseif (!preg_match('/^[a-z0-9-]{3,60}$/', $slug)) {
            $error = 'Clinic name needs at least 3 letters or numbers.';
        } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must be 8+ characters with 1 uppercase and 1 number.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif ($optionalEmail !== '' && !filter_var($optionalEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email address looks invalid.';
        } elseif ($optionalEmail !== '' && AuthService::emailRegistered($optionalEmail)) {
            $error = 'An account with this email already exists. Use a different email.';
        } elseif (DoctorOtpService::phoneRegistered($verifiedPhone)) {
            $error = 'This mobile number is already registered. Please log in.';
        } else {
            try {
                $resolvedUsername = UsernameService::resolveForRegistration($rawUsername, $verifiedPhone, $ownerName);
                $error = null;
            } catch (\InvalidArgumentException $e) {
                $error = match ($e->getMessage()) {
                    'username_taken' => 'That username is already taken. Choose another.',
                    default => 'Username must be 3–30 characters: letters, numbers, underscore (or your 10-digit mobile).',
                };
                $resolvedUsername = null;
            }
        }

        $defaultUsername = UsernameService::defaultFromPhone($verifiedPhone);
        $registerViewData = static fn (?string $err) => [
            'csrf' => CsrfService::token(),
            'error' => $err,
            'info' => null,
            'old' => [
                'clinicName' => $clinicName,
                'ownerName' => $ownerName,
                'slug' => $slug,
                'email' => $optionalEmail,
                'username' => $rawUsername !== '' ? $rawUsername : $defaultUsername,
            ],
            'phoneStep' => 'details',
            'verifiedPhone' => $verifiedPhone,
            'pendingPhone' => '',
            'devCode' => null,
            'defaultUsername' => $defaultUsername,
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ];

        if ($error !== null) {
            return Response::html($this->view('auth/register', $registerViewData($error)), 422);
        }
        if (!$this->verifyRecaptcha($request)) {
            return Response::html($this->view('auth/register', $registerViewData('Please complete the CAPTCHA and try again.')), 422);
        }

        try {
            $result = AuthService::registerClinicViaPhone(
                $clinicName,
                $ownerName,
                $slug,
                $verifiedPhone,
                $password,
                $optionalEmail !== '' ? $optionalEmail : null,
                $resolvedUsername,
            );
        } catch (\Throwable $e) {
            error_log('[register] registerClinicViaPhone failed: ' . $e->getMessage());
            return Response::html($this->view('auth/register', $registerViewData('We could not create your account right now. Please try again.')), 422);
        }

        DoctorOtpService::clearPhoneVerified();
        $this->attributePartner($request, $result);

        return $this->finishRegistration($request, $result);
    }

    /**
     * @param array{tenant_id: int, user_id: int, username?: string} $result
     */
    private function finishRegistration(Request $request, array $result): Response
    {
        $user = QueryBuilder::table('users')->where('id', '=', $result['user_id'])->first();
        $token = JwtService::issue($user, $result['tenant_id']);
        $refresh = AuthService::establishSession($user, $request, true);
        JwtService::setAuthCookies($token, $refresh);
        JwtService::clearImpersonationCookie();

        AuditService::log($request, 'INSERT', 'users', $result['user_id']);

        $username = (string) ($result['username'] ?? $user['username'] ?? '');
        if ($username !== '') {
            SessionFlash::put('new_username', $username);
        }

        return Response::redirect('/onboarding/clinic-setup');
    }

    /**
     * @param array{tenant_id: int, user_id: int} $result
     */
    private function attributePartner(Request $request, array $result): void
    {
        try {
            $referral = PartnerReferralService::resolveForRegistration(
                $request->post['referral_code'] ?? null,
                $request->cookies,
            );
            if ($referral !== null) {
                PartnerReferralService::attribute(
                    (int) $referral['partner']['id'],
                    (int) $result['tenant_id'],
                    $referral['code'],
                    $referral['via'],
                );
            }
        } catch (\Throwable $e) {
            error_log('[register] partner attribution failed: ' . $e->getMessage());
        }
    }

    public function showLogin(Request $request): Response
    {
        return Response::html($this->view('auth/login', [
            'csrf' => CsrfService::token(),
            'error' => SessionFlash::pull('login_error') ?? ($request->query['error'] ?? null),
            'captchaRequired' => false,
            'googleEnabled' => false,
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ]));
    }

    public function login(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::json(['error' => 'Database unavailable'], 503);
        }

        $username = strtolower(trim((string) ($request->post['username'] ?? '')));
        $password = $request->post['password'] ?? '';
        $remember = !empty($request->post['remember_me']);
        $attemptFailures = AuthService::recordFailedLogin($username);
        if (!$this->verifyRecaptcha($request)) {
            return Response::html($this->view('auth/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Please complete the CAPTCHA and try again.',
                'captchaRequired' => false,
                'googleEnabled' => false,
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
            ]), 422);
        }
        if ($attemptFailures >= 5) {
            return Response::html($this->view('auth/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Too many attempts. Try again in 15 minutes.',
                'captchaRequired' => true,
                'googleEnabled' => false,
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
            ]), 429);
        }

        $user = AuthService::findUserByUsername($username);
        if ($user === null || !password_verify($password, $user['password_hash'] ?? '')) {
            return Response::html($this->view('auth/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Invalid username or password.',
                'captchaRequired' => $attemptFailures >= 3,
                'googleEnabled' => false,
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
            ]), 401);
        }

        AuthService::clearFailedLogins($username);
        $clinicId = (int) $user['clinic_id'];
        $token = JwtService::issue($user, $clinicId);
        $refresh = AuthService::establishSession($user, $request, $remember);
        JwtService::setAuthCookies($token, $refresh);
        JwtService::clearImpersonationCookie();

        QueryBuilder::table('users')->where('id', '=', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
            'last_staff_login_at' => date('Y-m-d H:i:s'),
        ]);

        AuditService::log($request, 'LOGIN', 'users', (int) $user['id']);

        $tenant = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();

        return Response::redirect($this->postLoginRedirect($tenant, $user));
    }

    public function logout(Request $request): Response
    {
        $payload = isset($request->cookies['mc_token'])
            ? JwtService::decode($request->cookies['mc_token'])
            : null;

        if ($payload !== null && isset($payload['sub'])) {
            AuditService::log($request, 'LOGOUT', 'users', (int) $payload['sub']);
        }

        SessionService::revokeByRefreshToken($request->cookies['mc_refresh'] ?? null);
        JwtService::clearAuthCookies();

        return Response::redirect('/login');
    }

    public function showForgotPassword(Request $request): Response
    {
        $step = !empty($request->query['phone']) ? 'reset' : 'phone';
        $verifiedPhone = AccountRecoveryService::verifiedPasswordResetPhone();

        return Response::html($this->view('auth/forgot-password', [
            'csrf' => CsrfService::token(),
            'step' => $verifiedPhone !== null ? 'new_password' : $step,
            'pendingPhone' => (string) ($request->query['phone'] ?? ''),
            'devCode' => SessionFlash::pull('forgot_password_dev_code'),
            'error' => SessionFlash::pull('forgot_password_error'),
            'info' => SessionFlash::pull('forgot_password_info'),
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ]));
    }

    public function sendForgotPasswordOtp(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            SessionFlash::put('forgot_password_error', 'Session expired. Please try again.');
            return Response::redirect('/forgot-password');
        }
        if (!$this->verifyRecaptcha($request)) {
            SessionFlash::put('forgot_password_error', 'Please complete the CAPTCHA and try again.');
            return Response::redirect('/forgot-password');
        }

        $phone = (string) ($request->post['phone'] ?? '');
        $res = AccountRecoveryService::sendPasswordResetOtp($phone);
        $normalized = DoctorOtpService::normalizePhone($phone);

        if (!$res['ok']) {
            SessionFlash::put('forgot_password_error', match ($res['error'] ?? '') {
                'invalid_phone' => 'Please enter a valid 10-digit mobile number.',
                'resend_too_soon' => 'Please wait a moment before requesting another code.',
                default => 'Could not send the code. Please try again.',
            });
            return Response::redirect('/forgot-password');
        }

        if (!empty($res['dev_code'])) {
            SessionFlash::put('forgot_password_dev_code', $res['dev_code']);
        }
        SessionFlash::put('forgot_password_info', 'If an account exists for this number, we sent a reset code.');
        return Response::redirect('/forgot-password?phone=' . rawurlencode($normalized));
    }

    public function verifyForgotPasswordOtp(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            SessionFlash::put('forgot_password_error', 'Session expired. Please try again.');
            return Response::redirect('/forgot-password');
        }
        if (!$this->verifyRecaptcha($request)) {
            SessionFlash::put('forgot_password_error', 'Please complete the CAPTCHA and try again.');
            return Response::redirect('/forgot-password?phone=' . rawurlencode(DoctorOtpService::normalizePhone((string) ($request->post['phone'] ?? ''))));
        }

        $phone = (string) ($request->post['phone'] ?? '');
        $code = (string) ($request->post['code'] ?? '');
        $res = AccountRecoveryService::verifyPasswordResetOtp($phone, $code);

        if (!$res['ok']) {
            SessionFlash::put('forgot_password_error', match ($res['error'] ?? '') {
                'invalid_code' => 'That code is incorrect. Try again.',
                'expired' => 'Code expired. Request a new one.',
                'too_many_attempts' => 'Too many attempts. Request a new code.',
                'no_code_issued' => 'No active code. Send a new one.',
                default => 'Could not verify the code. Please try again.',
            });
            return Response::redirect('/forgot-password?phone=' . rawurlencode(DoctorOtpService::normalizePhone($phone)));
        }

        return Response::redirect('/forgot-password');
    }

    public function resetPasswordViaPhone(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            SessionFlash::put('forgot_password_error', 'Session expired. Please try again.');
            return Response::redirect('/forgot-password');
        }
        if (!$this->verifyRecaptcha($request)) {
            SessionFlash::put('forgot_password_error', 'Please complete the CAPTCHA and try again.');
            return Response::redirect('/forgot-password');
        }

        $password = (string) ($request->post['password'] ?? '');
        $confirm = (string) ($request->post['password_confirm'] ?? '');
        $passwordError = $this->validatePassword($password, $confirm);

        if ($passwordError !== null) {
            SessionFlash::put('forgot_password_error', $passwordError);
            return Response::redirect('/forgot-password');
        }

        $res = AccountRecoveryService::resetPassword($password);
        if (!$res['ok']) {
            SessionFlash::put('forgot_password_error', match ($res['error'] ?? '') {
                'session_expired' => 'Your reset session expired. Request a new code.',
                default => 'Could not reset your password. Please try again.',
            });
            return Response::redirect('/forgot-password');
        }

        return Response::redirect('/login?error=' . urlencode('Password updated. Please sign in with your username.'));
    }

    public function showForgotUsername(Request $request): Response
    {
        return Response::html($this->view('auth/forgot-username', [
            'csrf' => CsrfService::token(),
            'sent' => !empty($request->query['sent']),
            'devUsername' => SessionFlash::pull('forgot_username_dev'),
            'error' => SessionFlash::pull('forgot_username_error'),
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ]));
    }

    public function forgotUsername(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            SessionFlash::put('forgot_username_error', 'Session expired. Please try again.');
            return Response::redirect('/forgot-username');
        }
        if (!$this->verifyRecaptcha($request)) {
            SessionFlash::put('forgot_username_error', 'Please complete the CAPTCHA and try again.');
            return Response::redirect('/forgot-username');
        }

        $phone = (string) ($request->post['phone'] ?? '');
        $res = AccountRecoveryService::sendUsernameReminder($phone);

        if (!$res['ok']) {
            SessionFlash::put('forgot_username_error', match ($res['error'] ?? '') {
                'invalid_phone' => 'Please enter a valid 10-digit mobile number.',
                default => 'Could not send your username right now. Please try again.',
            });
            return Response::redirect('/forgot-username');
        }

        if (!empty($res['dev_username'])) {
            SessionFlash::put('forgot_username_dev', $res['dev_username']);
        }

        return Response::redirect('/forgot-username?sent=1');
    }

    /** @deprecated Email reset kept for staff with email; clinic login uses mobile OTP flow. */
    public function forgotPassword(Request $request): Response
    {
        return Response::redirect('/forgot-password');
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        $valid = PasswordResetService::findValidEmail($token) !== null;

        return Response::html($this->view('auth/reset-password', [
            'csrf' => CsrfService::token(),
            'token' => $token,
            'valid' => $valid,
            'error' => null,
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ]));
    }

    public function resetPassword(Request $request, string $token): Response
    {
        if (!$this->verifyRecaptcha($request)) {
            return Response::html($this->view('auth/reset-password', [
                'csrf' => CsrfService::token(),
                'token' => $token,
                'valid' => true,
                'error' => 'Please complete the CAPTCHA and try again.',
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
            ]), 422);
        }

        $password = $request->post['password'] ?? '';
        $confirm = $request->post['password_confirm'] ?? '';

        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return Response::html($this->view('auth/reset-password', [
                'csrf' => CsrfService::token(),
                'token' => $token,
                'valid' => true,
                'error' => 'Password must be 8+ characters with 1 uppercase and 1 number.',
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
            ]), 422);
        }

        if ($password !== $confirm) {
            return Response::html($this->view('auth/reset-password', [
                'csrf' => CsrfService::token(),
                'token' => $token,
                'valid' => true,
                'error' => 'Passwords do not match.',
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
            ]), 422);
        }

        if (!PasswordResetService::reset($token, $password)) {
            return Response::html($this->view('auth/reset-password', [
                'csrf' => CsrfService::token(),
                'token' => $token,
                'valid' => false,
                'error' => 'This reset link is invalid or has expired.',
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
            ]), 410);
        }

        return Response::redirect('/login?error=' . urlencode('Password updated. Please sign in.'));
    }

    public function showChangePassword(Request $request): Response
    {
        $user = RequestContext::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        return Response::html($this->view('auth/change-password', [
            'csrf' => CsrfService::token(),
            'error' => null,
            'required' => !empty($user['must_change_password']),
        ]));
    }

    public function changePassword(Request $request): Response
    {
        $user = RequestContext::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $password = $request->post['password'] ?? '';
        $confirm = $request->post['password_confirm'] ?? '';

        $passwordError = $this->validatePassword($password, $confirm);
        if ($passwordError !== null) {
            return Response::html($this->view('auth/change-password', [
                'csrf' => CsrfService::token(),
                'error' => $passwordError,
                'required' => !empty($user['must_change_password']),
            ]), 422);
        }

        AuthService::updatePassword((int) $user['id'], $password);
        QueryBuilder::table('users')->where('id', '=', (int) $user['id'])->update([
            'must_change_password' => 0,
        ]);

        AuditService::log($request, 'UPDATE', 'users', (int) $user['id']);

        $tenant = QueryBuilder::table('tenants')->where('id', '=', (int) $user['clinic_id'])->first();

        return Response::redirect($this->postLoginRedirect($tenant, ['must_change_password' => 0]));
    }

    public function googleRedirect(Request $request): Response
    {
        if (!GoogleOAuthService::isConfigured()) {
            return Response::redirect('/login?error=' . urlencode('Google sign-in is not configured.'));
        }

        return Response::redirect(GoogleOAuthService::authorizationUrl());
    }

    public function googleCallback(Request $request): Response
    {
        if (!GoogleOAuthService::isConfigured()) {
            return Response::redirect('/login');
        }

        $code = $request->query['code'] ?? '';
        $state = $request->query['state'] ?? '';
        if ($code === '' || $state === '') {
            return Response::redirect('/login?error=' . urlencode('Google sign-in was cancelled.'));
        }

        $profile = GoogleOAuthService::fetchUserFromCallback($code, $state);
        if ($profile === null) {
            return Response::redirect('/login?error=' . urlencode('Google sign-in failed.'));
        }

        $user = GoogleOAuthService::findUserByGoogleId($profile['google_id'])
            ?? AuthService::findUserByEmail($profile['email']);

        if ($user === null) {
            GoogleOAuthService::storePendingRegistration($profile);

            return Response::redirect('/register');
        }

        if (empty($user['google_id'])) {
            GoogleOAuthService::linkGoogleAccount((int) $user['id'], $profile['google_id']);
        }

        $token = JwtService::issue($user, (int) $user['clinic_id']);
        $refresh = AuthService::establishSession($user, $request, true);
        JwtService::setAuthCookies($token, $refresh);
        JwtService::clearImpersonationCookie();

        AuditService::log($request, 'LOGIN', 'users', (int) $user['id']);

        $tenant = QueryBuilder::table('tenants')->where('id', '=', (int) $user['clinic_id'])->first();

        return Response::redirect($this->postLoginRedirect($tenant, $user));
    }

    public function refreshToken(Request $request): Response
    {
        $refresh = $request->cookies['mc_refresh'] ?? null;
        if ($refresh === null) {
            return Response::json(['error' => 'No refresh token'], 401);
        }

        $session = SessionService::findByRefreshToken($refresh);
        if ($session === null) {
            return Response::json(['error' => 'Invalid session'], 401);
        }

        $user = QueryBuilder::table('users')->where('id', '=', (int) $session['user_id'])->first();
        if ($user === null) {
            return Response::json(['error' => 'User not found'], 401);
        }

        $newRefresh = AuthService::generateRefreshToken();
        SessionService::rotateRefreshToken((int) $session['id'], $newRefresh);
        $jwt = JwtService::issue($user, (int) $user['clinic_id']);
        JwtService::setAuthCookies($jwt, $newRefresh);

        return Response::json(['ok' => true]);
    }

    public function checkSlug(Request $request): Response
    {
        $slug = strtolower(trim($request->query['slug'] ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            return Response::json(['available' => false, 'reason' => 'invalid']);
        }

        return Response::json(['available' => AuthService::slugAvailable($slug)]);
    }

    public function checkUsername(Request $request): Response
    {
        $raw = trim((string) ($request->query['username'] ?? ''));
        if ($raw === '') {
            return Response::json(['available' => true, 'reason' => 'blank']);
        }

        return Response::json(UsernameService::check($raw));
    }

    /** @param array<string, mixed> $data */
    private function view(string $name, array $data = []): string
    {
        return View::render($name, $data);
    }

    /**
     * Take a base slug and return an available one. If "sunrise-dental" is
     * taken, returns "sunrise-dental-2", "sunrise-dental-3", etc.
     * Stops after 99 attempts (then appends a timestamp fragment).
     */
    private function resolveUniqueSlug(string $base): string
    {
        $base = trim($base, '-');
        if ($base === '') return 'clinic-' . substr((string) time(), -5);

        if (AuthService::slugAvailable($base)) return $base;

        for ($i = 2; $i <= 99; $i++) {
            $candidate = $base . '-' . $i;
            if (strlen($candidate) > 60) $candidate = substr($base, 0, 60 - strlen('-' . $i)) . '-' . $i;
            if (AuthService::slugAvailable($candidate)) return $candidate;
        }
        // Extremely unlikely fallback.
        return substr($base, 0, 50) . '-' . substr((string) time(), -5);
    }

    private function validateRegistration(
        string $clinicName,
        string $ownerName,
        string $slug,
        string $email,
        string $password,
        string $confirm,
        bool $fromGoogle,
    ): ?string {
        if ($ownerName === '' || strlen($ownerName) < 2) {
            return 'Your name is required.';
        }
        if ($clinicName === '' || strlen($clinicName) < 2) {
            return 'Clinic name is required.';
        }
        if (!preg_match('/^[a-z0-9-]{3,60}$/', $slug)) {
            // The slug is auto-generated server-side from clinic name now,
            // so reaching here means the clinic name was unusable.
            return 'Clinic name needs at least 3 letters or numbers.';
        }
        // Collision check is no longer "fail" — we resolve it in resolveUniqueSlug().
        // Defensive double-check kept in case the slug came in pre-resolved.
        if (!AuthService::slugAvailable($slug)) {
            return 'Could not assign a clinic URL. Please try a different clinic name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Valid email is required.';
        }
        if (AuthService::emailRegistered($email)) {
            return 'An account with this email already exists. Please log in instead.';
        }
        if (!$fromGoogle) {
            if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                return 'Password must be 8+ characters with 1 uppercase and 1 number.';
            }
            if ($password !== $confirm) {
                return 'Passwords do not match.';
            }
        }

        return null;
    }

    /** @param array<string, mixed>|null $tenant @param array<string, mixed> $user */
    private function postLoginRedirect(?array $tenant, array $user = []): string
    {
        if (!empty($user['must_change_password'])) {
            return '/change-password';
        }

        if (!RoleAccessService::isClinicAdmin($user)) {
            return '/dashboard';
        }

        if ($tenant === null) {
            return '/dashboard';
        }

        $step = (int) ($tenant['onboarding_step'] ?? 1);
        if ($step < 5) {
            SessionFlash::put('onboarding_resume', true);
        }

        return OnboardingService::resumeUrl((int) $tenant['id']);
    }

    private function validatePassword(string $password, string $confirm): ?string
    {
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Password must be 8+ characters with 1 uppercase and 1 number.';
        }
        if ($password !== $confirm) {
            return 'Passwords do not match.';
        }

        return null;
    }

    private function verifyRecaptcha(Request $request): bool
    {
        if (!RecaptchaService::enabled()) {
            return true;
        }

        $token = $request->post['g-recaptcha-response'] ?? null;
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? null;

        return RecaptchaService::verify(is_string($token) ? $token : null, is_string($remoteIp) ? $remoteIp : null);
    }
}
