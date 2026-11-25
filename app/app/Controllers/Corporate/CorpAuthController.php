<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CompanyService;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpAuthService;
use App\Services\Corporate\CorpJwtService;
use App\Services\Corporate\CorpSessionService;
use App\Services\CsrfService;
use App\Services\RecaptchaService;
use App\Support\Locale\CountryRepo;
use App\Support\View;

/**
 * Company portal sign-in, registration, password reset, email verification
 * (app.eclinicpro.com/company/*). Own guard: CorpAuthMiddleware.
 */
final class CorpAuthController
{
    public function showLogin(Request $request): Response
    {
        if ($this->alreadySignedIn($request)) {
            return Response::redirect('/company/dashboard');
        }

        return $this->loginPage($request, null, '', 200);
    }

    public function login(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::html('Service temporarily unavailable. Please try again shortly.', 503);
        }
        $email = strtolower(trim((string) ($request->post['email'] ?? '')));
        if (!RecaptchaService::verify((string) ($request->post['g-recaptcha-response'] ?? ''), $request->ip())) {
            return $this->loginPage($request, 'Please complete the "I\'m not a robot" check.', $email, 422);
        }
        $result = CorpAuthService::attemptLogin($email, (string) ($request->post['password'] ?? ''));
        if (!$result['ok']) {
            return $this->loginPage($request, $result['error'] ?? 'Sign-in failed.', $email, 401);
        }

        CorpSessionService::start((int) $result['company']['id'], (int) $result['user']['id']);

        return Response::redirect($this->safeNext((string) ($request->post['next'] ?? '')));
    }

    public function showRegister(Request $request): Response
    {
        if (!CompanyService::signupEnabled()) {
            return $this->registerClosed();
        }

        return $this->registerPage([], null, 200);
    }

    public function register(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::html('Service temporarily unavailable. Please try again shortly.', 503);
        }
        if (!CompanyService::signupEnabled()) {
            return $this->registerClosed();
        }
        [$in, $error] = CompanyService::validateRegistration($request->post);
        if ($error === null && !RecaptchaService::verify((string) ($request->post['g-recaptcha-response'] ?? ''), $request->ip())) {
            $error = 'Please complete the "I\'m not a robot" check.';
        }
        $result = $error === null ? CompanyService::register($in) : ['ok' => false, 'error' => $error];
        if (!$result['ok']) {
            unset($in['password']);

            return $this->registerPage($in, $result['error'] ?? 'Registration failed.', 422);
        }

        $user = CompanyService::findUser((int) $result['user_id']);
        if ($user !== null) {
            CorpAuthService::sendVerifyEmail($user);
        }
        CompanyService::notifyOpsNewCompany((int) $result['company_id']);
        CorpSessionService::start((int) $result['company_id'], (int) $result['user_id']);

        return Response::redirect('/company/dashboard?welcome=1');
    }

    public function showForgot(Request $request): Response
    {
        return Response::html(View::render('corporate/forgot_password', [
            'csrf' => CsrfService::token(),
            'sent' => false,
            'email' => '',
        ]));
    }

    /** Same response whether or not the email has an account. */
    public function forgot(Request $request): Response
    {
        $email = strtolower(trim((string) ($request->post['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && Database::ping()) {
            CorpAuthService::startPasswordReset($email);
        }

        return Response::html(View::render('corporate/forgot_password', [
            'csrf' => CsrfService::token(),
            'sent' => true,
            'email' => $email,
        ]));
    }

    public function showReset(Request $request): Response
    {
        $token = (string) ($request->query['token'] ?? '');

        return Response::html(View::render('corporate/reset_password', [
            'csrf' => CsrfService::token(),
            'token' => $token,
            'valid' => CorpAuthService::findToken($token, 'reset') !== null,
            'error' => null,
            'done' => false,
        ]));
    }

    public function reset(Request $request): Response
    {
        $token = (string) ($request->post['token'] ?? '');
        $res = CorpAuthService::completePasswordReset(
            $token,
            (string) ($request->post['password'] ?? ''),
            (string) ($request->post['password_confirm'] ?? ''),
        );

        return Response::html(View::render('corporate/reset_password', [
            'csrf' => CsrfService::token(),
            'token' => $token,
            'valid' => $res['ok'] || CorpAuthService::findToken($token, 'reset') !== null,
            'error' => $res['ok'] ? null : ($res['error'] ?? 'Could not reset the password.'),
            'done' => $res['ok'],
        ]), $res['ok'] ? 200 : 422);
    }

    /** Link from the confirmation email. Works signed in or not. */
    public function verifyEmail(Request $request): Response
    {
        $res = CorpAuthService::verifyEmail((string) ($request->query['token'] ?? ''));

        return Response::html(View::render('corporate/verify_email', [
            'ok' => $res['ok'],
            'error' => $res['error'] ?? null,
            'signedIn' => $this->alreadySignedIn($request),
        ]), $res['ok'] ? 200 : 410);
    }

    public function logout(Request $request): Response
    {
        $token = $request->cookies[CorpJwtService::COOKIE] ?? null;
        $payload = is_string($token) && $token !== '' ? CorpJwtService::decode($token) : null;
        if ($payload !== null && !empty($payload['jti'])) {
            try {
                CorpSessionService::revoke((string) $payload['jti']);
                CorpAudit::log((int) ($payload['cid'] ?? 0), 'corp_user', (int) ($payload['sub'] ?? 0), 'logout');
            } catch (\Throwable $e) {
                error_log('[CorpAuthController::logout] ' . $e->getMessage());
            }
        }
        CorpJwtService::clearCookie();

        return Response::redirect('/company/login?signed_out=1');
    }

    // ------------------------------------------------------------------

    private function loginPage(Request $request, ?string $error, string $email, int $status): Response
    {
        $notice = null;
        if (!empty($request->query['signed_out'])) {
            $notice = 'You have been signed out.';
        } elseif (!empty($request->query['reset'])) {
            $notice = 'Password updated. Please sign in with your new password.';
        }

        return Response::html(View::render('corporate/login', [
            'csrf' => CsrfService::token(),
            'error' => $error ?? (isset($request->query['error']) ? mb_substr((string) $request->query['error'], 0, 200) : null),
            'notice' => $notice,
            'email' => $email,
            'next' => $this->safeNext((string) ($request->post['next'] ?? $request->query['next'] ?? '')),
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ]), $status);
    }

    /** @param array<string, mixed> $old */
    private function registerPage(array $old, ?string $error, int $status): Response
    {
        $countries = CountryRepo::activeCountries();
        $regions = [];
        foreach ($countries as $c) {
            $regions[(string) $c['code']] = array_map(
                static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']],
                CountryRepo::regions((string) $c['code']),
            );
        }

        return Response::html(View::render('corporate/register', [
            'csrf' => CsrfService::token(),
            'error' => $error,
            'old' => $old,
            'countries' => $countries,
            'regions' => $regions,
            'industries' => CompanyService::industries(),
            'sizeBands' => CompanyService::sizeBands(),
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ]), $status);
    }

    private function registerClosed(): Response
    {
        return Response::html(View::render('corporate/register_closed', []), 403);
    }

    /** Only same-portal relative paths; never an open redirect. */
    private function safeNext(string $next): string
    {
        if ($next !== '' && preg_match('#^/company(/[A-Za-z0-9/_\-]*)?(\?[A-Za-z0-9=&_\-%]*)?$#', $next) === 1
            && !str_starts_with($next, '/company/login') && !str_starts_with($next, '/company/logout')) {
            return $next;
        }

        return '/company/dashboard';
    }

    /** True when the cookie belongs to a live session (used to skip the login page). */
    private function alreadySignedIn(Request $request): bool
    {
        $token = $request->cookies[CorpJwtService::COOKIE] ?? null;
        $payload = is_string($token) && $token !== '' ? CorpJwtService::decode($token) : null;
        if ($payload === null || empty($payload['jti']) || empty($payload['sub'])) {
            return false;
        }
        try {
            return CorpSessionService::validate((string) $payload['jti'], (int) $payload['sub']) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
