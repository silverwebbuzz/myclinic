<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\VendorJwtService;
use App\Services\Store\VendorService;
use App\Support\View;

/**
 * Store seller registration & login (app.eclinicpro.com/vendor/*).
 * Separate guard from clinic users, admins and partners.
 */
final class VendorAuthController
{
    public function showLogin(Request $request): Response
    {
        return Response::html(View::render('store_vendor/login', [
            'csrf' => CsrfService::token(),
            'error' => null,
            'email' => '',
        ]));
    }

    public function login(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::html('Database unavailable', 503);
        }
        $email = (string) ($request->post['email'] ?? '');
        $result = VendorService::attemptLogin($email, (string) ($request->post['password'] ?? ''));
        if (!$result['ok']) {
            return Response::html(View::render('store_vendor/login', [
                'csrf' => CsrfService::token(),
                'error' => $result['error'] ?? 'Login failed.',
                'email' => $email,
            ]), 401);
        }

        VendorJwtService::setCookie(VendorJwtService::issue((int) $result['user']['id'], (int) $result['vendor']['id']));

        return Response::redirect('/vendor/dashboard');
    }

    public function showRegister(Request $request): Response
    {
        return Response::html(View::render('store_vendor/register', [
            'csrf' => CsrfService::token(),
            'error' => null,
            'old' => [],
        ]));
    }

    public function register(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::html('Database unavailable', 503);
        }
        $in = [
            'business_name' => trim((string) ($request->post['business_name'] ?? '')),
            'contact_name' => trim((string) ($request->post['contact_name'] ?? '')),
            'email' => strtolower(trim((string) ($request->post['email'] ?? ''))),
            'phone' => trim((string) ($request->post['phone'] ?? '')),
            'password' => (string) ($request->post['password'] ?? ''),
        ];
        $error = $this->validate($in, (string) ($request->post['password_confirm'] ?? ''), !empty($request->post['accept_terms']));
        $result = $error === null ? VendorService::register($in) : ['ok' => false, 'error' => $error];

        if (!$result['ok']) {
            unset($in['password']);

            return Response::html(View::render('store_vendor/register', [
                'csrf' => CsrfService::token(),
                'error' => $result['error'] ?? 'Registration failed.',
                'old' => $in,
            ]), 422);
        }

        VendorJwtService::setCookie(VendorJwtService::issue((int) $result['user_id'], (int) $result['vendor_id']));

        return Response::redirect('/vendor/dashboard?welcome=1');
    }

    public function showForgot(Request $request): Response
    {
        return Response::html(View::render('store_vendor/forgot_password', [
            'csrf' => CsrfService::token(),
            'sent' => false,
            'email' => '',
        ]));
    }

    /** Same response whether or not the email has an account (no account discovery). */
    public function forgot(Request $request): Response
    {
        $email = strtolower(trim((string) ($request->post['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && Database::ping()) {
            VendorService::startPasswordReset($email, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        }

        return Response::html(View::render('store_vendor/forgot_password', [
            'csrf' => CsrfService::token(),
            'sent' => true,
            'email' => $email,
        ]));
    }

    public function showReset(Request $request): Response
    {
        $token = (string) ($request->query['token'] ?? '');

        return Response::html(View::render('store_vendor/reset_password', [
            'csrf' => CsrfService::token(),
            'token' => $token,
            'valid' => VendorService::findResetToken($token) !== null,
            'error' => null,
            'done' => false,
        ]));
    }

    public function reset(Request $request): Response
    {
        $token = (string) ($request->post['token'] ?? '');
        $res = VendorService::completePasswordReset($token, (string) ($request->post['password'] ?? ''), (string) ($request->post['password_confirm'] ?? ''));

        return Response::html(View::render('store_vendor/reset_password', [
            'csrf' => CsrfService::token(),
            'token' => $token,
            'valid' => $res['ok'] || VendorService::findResetToken($token) !== null,
            'error' => $res['ok'] ? null : ($res['error'] ?? 'Could not reset the password.'),
            'done' => $res['ok'],
        ]), $res['ok'] ? 200 : 422);
    }

    public function logout(Request $request): Response
    {
        VendorJwtService::clearCookie();

        return Response::redirect('/vendor/login');
    }

    /** @param array<string, string> $in */
    private function validate(array $in, string $confirm, bool $acceptedTerms): ?string
    {
        if (mb_strlen($in['business_name']) < 2) {
            return 'Please enter your store / business name.';
        }
        if (mb_strlen($in['contact_name']) < 2) {
            return 'Please enter the contact person\'s name.';
        }
        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }
        if (!preg_match('/^\+91[6-9]\d{9}$/', VendorService::normalizePhone($in['phone']))) {
            return 'Please enter a valid 10-digit Indian mobile number.';
        }
        if (strlen($in['password']) < 8) {
            return 'Password must be at least 8 characters.';
        }
        if ($in['password'] !== $confirm) {
            return 'Passwords do not match.';
        }
        if (!$acceptedTerms) {
            return 'Please accept the seller terms to continue.';
        }

        return null;
    }
}
