<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\PartnerAuthService;
use App\Services\PartnerJwtService;
use App\Services\PartnerService;
use App\Support\View;

/**
 * Partner registration & login. A separate guard from clinic users and
 * platform admins (cookie: mc_partner_token, path /partner).
 */
final class PartnerAuthController
{
    public function showRegister(Request $request): Response
    {
        return Response::html(View::render('partner/register', [
            'csrf' => CsrfService::token(),
            'error' => null,
            'old' => [],
        ]));
    }

    public function register(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::json(['error' => 'Database unavailable'], 503);
        }

        $name = trim($request->post['name'] ?? '');
        $email = strtolower(trim($request->post['email'] ?? ''));
        $phone = trim($request->post['phone'] ?? '');
        $country = trim($request->post['country_code'] ?? 'IN');
        $city = trim($request->post['city'] ?? '');
        $state = trim($request->post['state'] ?? '');
        $password = $request->post['password'] ?? '';
        $confirm = $request->post['password_confirm'] ?? '';

        $error = $this->validate($name, $email, $password, $confirm);
        if ($error !== null) {
            return Response::html(View::render('partner/register', [
                'csrf' => CsrfService::token(),
                'error' => $error,
                'old' => compact('name', 'email', 'phone', 'country', 'city', 'state'),
            ]), 422);
        }

        $result = PartnerService::register($name, $email, $phone, $password, $country, $city, $state);

        $token = PartnerJwtService::issue($result['id'], $email);
        PartnerJwtService::setCookie($token);

        // New partners land on documents upload — KYC is required before approval.
        return Response::redirect('/partner/documents?welcome=1');
    }

    public function showLogin(Request $request): Response
    {
        return Response::html(View::render('partner/login', [
            'csrf' => CsrfService::token(),
            'error' => $request->query['error'] ?? null,
        ]));
    }

    public function login(Request $request): Response
    {
        if (!Database::ping()) {
            return Response::json(['error' => 'Database unavailable'], 503);
        }

        $partner = PartnerAuthService::attempt(
            $request->post['email'] ?? '',
            $request->post['password'] ?? '',
        );

        if ($partner === null) {
            return Response::html(View::render('partner/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Invalid credentials.',
            ]), 401);
        }

        $token = PartnerJwtService::issue((int) $partner['id'], (string) $partner['email']);
        PartnerJwtService::setCookie($token);

        return Response::redirect('/partner/dashboard');
    }

    public function logout(Request $request): Response
    {
        PartnerJwtService::clearCookie();

        return Response::redirect('/partner/login');
    }

    private function validate(string $name, string $email, string $password, string $confirm): ?string
    {
        if ($name === '' || mb_strlen($name) < 2) {
            return 'Please enter your full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }
        if (PartnerService::findByEmail($email) !== null) {
            return 'An account with this email already exists.';
        }
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            return 'Passwords do not match.';
        }

        return null;
    }
}
