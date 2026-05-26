<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\JwtService;
use App\Support\View;

/**
 * GET / — public landing page on app.eclinicpro.com.
 *
 * Two doors:
 *   - "Already a member? Sign in" → /login (existing flow)
 *   - "New doctor? Claim or register" → /register
 *
 * If the visitor already has a valid JWT, we skip the landing and
 * send them straight to their dashboard.
 */
final class LandingController
{
    public function index(Request $request): Response
    {
        $token = $_COOKIE['mc_token'] ?? null;
        if ($token !== null) {
            $payload = JwtService::decode($token);
            if (!empty($payload['clinic_id'])) {
                return Response::redirect('/dashboard');
            }
        }

        return Response::html(View::render('landing/index', []));
    }
}
