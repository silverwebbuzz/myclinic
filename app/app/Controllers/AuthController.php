<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CsrfService;
use App\Services\GoogleOAuthService;
use App\Services\JwtService;
use App\Services\PasswordResetService;
use App\Services\SessionService;
use App\Support\View;

final class AuthController
{
    public function showRegister(Request $request): Response
    {
        $google = GoogleOAuthService::pendingRegistration();

        return Response::html($this->view('auth/register', [
            'csrf' => CsrfService::token(),
            'error' => null,
            'google' => $google,
            'googleEnabled' => GoogleOAuthService::isConfigured(),
        ]));
    }

    public function register(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::json(['error' => 'Database unavailable'], 503);
        }

        $clinicName = trim($request->post['clinic_name'] ?? '');
        $slug = strtolower(trim($request->post['slug'] ?? ''));
        $email = strtolower(trim($request->post['email'] ?? ''));
        $password = $request->post['password'] ?? '';
        $confirm = $request->post['password_confirm'] ?? '';
        $google = GoogleOAuthService::pendingRegistration();
        $googleId = $google['google_id'] ?? null;

        if ($google !== null) {
            $email = $google['email'];
            if ($password === '') {
                $password = bin2hex(random_bytes(16)) . 'A1';
                $confirm = $password;
            }
        }

        // Slug is auto-derived from the clinic name (hidden field in the form).
        // If the client didn't send one, derive it now. Then resolve collisions
        // by appending -2, -3, ... so the user never sees "already taken".
        if ($slug === '' && $clinicName !== '') {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($clinicName)) ?: '';
            $slug = trim($slug, '-');
            $slug = substr($slug, 0, 56);   // leave room for "-NN" suffix
            if (strlen($slug) < 3) $slug = 'clinic-' . substr((string) time(), -5);
        }
        $slug = $this->resolveUniqueSlug($slug);

        $error = $this->validateRegistration($clinicName, $slug, $email, $password, $confirm, $google !== null);
        if ($error !== null) {
            return Response::html($this->view('auth/register', [
                'csrf' => CsrfService::token(),
                'error' => $error,
                'old' => compact('clinicName', 'slug', 'email'),
                'google' => $google,
                'googleEnabled' => GoogleOAuthService::isConfigured(),
            ]), 422);
        }

        $result = AuthService::registerClinic($clinicName, $slug, $email, $password, $googleId);
        GoogleOAuthService::clearPendingRegistration();

        $user = QueryBuilder::table('users')->where('id', '=', $result['user_id'])->first();
        $token = JwtService::issue($user, $result['tenant_id']);
        $refresh = AuthService::establishSession($user, $request, true);
        JwtService::setAuthCookies($token, $refresh);

        AuditService::log($request, 'INSERT', 'users', $result['user_id']);

        return Response::redirect('/onboarding/plan-selection');
    }

    public function showLogin(Request $request): Response
    {
        $email = strtolower(trim($request->query['email'] ?? ''));
        $failures = $email !== '' ? AuthService::failedLoginCount($email) : 0;

        return Response::html($this->view('auth/login', [
            'csrf' => CsrfService::token(),
            'error' => $request->query['error'] ?? null,
            'captchaRequired' => $failures >= 3,
            'googleEnabled' => GoogleOAuthService::isConfigured(),
        ]));
    }

    public function login(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::json(['error' => 'Database unavailable'], 503);
        }

        $email = strtolower(trim($request->post['email'] ?? ''));
        $password = $request->post['password'] ?? '';
        $remember = !empty($request->post['remember_me']);
        $failures = AuthService::failedLoginCount($email);

        if ($failures >= 3 && empty($request->post['captcha_confirm'])) {
            return Response::html($this->view('auth/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Please confirm you are not a robot.',
                'captchaRequired' => true,
                'googleEnabled' => GoogleOAuthService::isConfigured(),
            ]), 422);
        }

        $attemptFailures = AuthService::recordFailedLogin($email);
        if ($attemptFailures >= 5) {
            return Response::html($this->view('auth/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Too many attempts. Try again in 15 minutes.',
                'captchaRequired' => true,
                'googleEnabled' => GoogleOAuthService::isConfigured(),
            ]), 429);
        }

        $user = AuthService::findUserByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'] ?? '')) {
            return Response::html($this->view('auth/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Invalid email or password.',
                'captchaRequired' => $attemptFailures >= 3,
                'googleEnabled' => GoogleOAuthService::isConfigured(),
            ]), 401);
        }

        AuthService::clearFailedLogins($email);
        $clinicId = (int) $user['clinic_id'];
        $token = JwtService::issue($user, $clinicId);
        $refresh = AuthService::establishSession($user, $request, $remember);
        JwtService::setAuthCookies($token, $refresh);

        QueryBuilder::table('users')->where('id', '=', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
            'last_staff_login_at' => date('Y-m-d H:i:s'),
        ]);

        AuditService::log($request, 'LOGIN', 'users', (int) $user['id']);

        $tenant = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();

        return Response::redirect($this->postLoginRedirect($tenant));
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
        return Response::html($this->view('auth/forgot-password', [
            'csrf' => CsrfService::token(),
            'sent' => !empty($request->query['sent']),
        ]));
    }

    public function forgotPassword(Request $request): Response
    {
        $email = strtolower(trim($request->post['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            PasswordResetService::request($email);
        }

        return Response::redirect('/forgot-password?sent=1');
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        $valid = PasswordResetService::findValidEmail($token) !== null;

        return Response::html($this->view('auth/reset-password', [
            'csrf' => CsrfService::token(),
            'token' => $token,
            'valid' => $valid,
            'error' => null,
        ]));
    }

    public function resetPassword(Request $request, string $token): Response
    {
        $password = $request->post['password'] ?? '';
        $confirm = $request->post['password_confirm'] ?? '';

        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return Response::html($this->view('auth/reset-password', [
                'csrf' => CsrfService::token(),
                'token' => $token,
                'valid' => true,
                'error' => 'Password must be 8+ characters with 1 uppercase and 1 number.',
            ]), 422);
        }

        if ($password !== $confirm) {
            return Response::html($this->view('auth/reset-password', [
                'csrf' => CsrfService::token(),
                'token' => $token,
                'valid' => true,
                'error' => 'Passwords do not match.',
            ]), 422);
        }

        if (!PasswordResetService::reset($token, $password)) {
            return Response::html($this->view('auth/reset-password', [
                'csrf' => CsrfService::token(),
                'token' => $token,
                'valid' => false,
                'error' => 'This reset link is invalid or has expired.',
            ]), 410);
        }

        return Response::redirect('/login?error=' . urlencode('Password updated. Please sign in.'));
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

        AuditService::log($request, 'LOGIN', 'users', (int) $user['id']);

        $tenant = QueryBuilder::table('tenants')->where('id', '=', (int) $user['clinic_id'])->first();

        return Response::redirect($this->postLoginRedirect($tenant));
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
        string $slug,
        string $email,
        string $password,
        string $confirm,
        bool $fromGoogle,
    ): ?string {
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

    /** @param array<string, mixed>|null $tenant */
    private function postLoginRedirect(?array $tenant): string
    {
        if ($tenant === null) {
            return '/dashboard';
        }
        $step = (int) ($tenant['onboarding_step'] ?? 5);
        if ($step < 2) {
            return '/onboarding/plan-selection';
        }
        if ($step < 5) {
            return '/onboarding/clinic-setup';
        }

        return '/dashboard';
    }
}
