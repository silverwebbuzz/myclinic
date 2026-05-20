<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\ChurnOutreachService;
use App\Services\ImpersonationService;
use App\Services\SuperAdminAuthService;
use App\Services\SuperAdminJwtService;
use App\Services\SuperAdminMetricsService;
use App\Services\CsrfService;
use App\Support\View;

final class SuperAdminController
{
    public function showLogin(Request $request): Response
    {
        return Response::html(View::render('admin/login', [
            'csrf' => CsrfService::token(),
            'error' => null,
        ]));
    }

    public function login(Request $request): Response
    {
        $admin = SuperAdminAuthService::attempt(
            $request->post['email'] ?? '',
            $request->post['password'] ?? '',
        );

        if ($admin === null) {
            return Response::html(View::render('admin/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Invalid credentials.',
            ]), 401);
        }

        $token = SuperAdminJwtService::issue((int) $admin['id'], (string) $admin['email']);
        SuperAdminJwtService::setCookie($token);

        return Response::redirect('/admin/dashboard');
    }

    public function logout(Request $request): Response
    {
        SuperAdminJwtService::clearCookie();

        return Response::redirect('/admin/login');
    }

    public function dashboard(Request $request): Response
    {
        $metrics = SuperAdminMetricsService::dashboard();

        return Response::html(View::render('admin/dashboard', [
            'admin' => RequestContext::superAdmin(),
            'metrics' => $metrics,
            'csrf' => CsrfService::token(),
        ]));
    }

    public function clinics(Request $request): Response
    {
        return Response::html(View::render('admin/clinics', [
            'admin' => RequestContext::superAdmin(),
            'clinics' => SuperAdminMetricsService::clinicsList(),
            'csrf' => CsrfService::token(),
            'message' => $request->query['message'] ?? null,
        ]));
    }

    public function impersonate(Request $request): Response
    {
        $clinicId = (int) ($request->post['clinic_id'] ?? 0);
        $admin = RequestContext::superAdmin();
        if ($admin === null || $clinicId < 1) {
            return Response::redirect('/admin/clinics?error=invalid');
        }

        $token = ImpersonationService::createToken((int) $admin['id'], $clinicId);
        if ($token === null) {
            return Response::redirect('/admin/clinics?error=no_user');
        }

        $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');

        return Response::redirect($base . '/impersonate/' . $token);
    }

    public function reviews(Request $request): Response
    {
        return Response::html(View::render('admin/reviews', [
            'admin' => RequestContext::superAdmin(),
            'reviews' => \App\Services\DirectoryReviewService::pending(),
            'csrf' => CsrfService::token(),
        ]));
    }

    public function approveReview(Request $request): Response
    {
        \App\Services\DirectoryReviewService::approve((int) ($request->post['review_id'] ?? 0));

        return Response::redirect('/admin/reviews?message=approved');
    }

    public function rejectReview(Request $request): Response
    {
        \App\Services\DirectoryReviewService::reject((int) ($request->post['review_id'] ?? 0));

        return Response::redirect('/admin/reviews?message=rejected');
    }

    public function runChurn(Request $request): Response
    {
        $flagged = \App\Services\ChurnRiskService::run();
        $sent = ChurnOutreachService::sendOutreach();

        return Response::redirect('/admin/dashboard?message=churn_' . $flagged . '_emails_' . $sent);
    }
}
