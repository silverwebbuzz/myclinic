<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CompanyService;
use App\Services\Corporate\CorpAuthService;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\CorpJwtService;
use App\Services\Corporate\CorpMfaService;
use App\Services\Corporate\CorpSessionService;

/**
 * Guards the company portal (app.eclinicpro.com/company/*). The public auth
 * pages pass through; everything else needs a valid mc_corp_token whose
 * corp_sessions row is live, an active login, and a company that isn't
 * suspended / rejected / closed. Sets CorpContext.
 */
final class CorpAuthMiddleware implements MiddlewareInterface
{
    /** @var list<string> exact paths reachable without signing in */
    private const PUBLIC_PATHS = [
        '/company/login',
        '/company/register',
        '/company/forgot-password',
        '/company/reset-password',
        '/company/verify-email',
    ];

    public function handle(Request $request, callable $next): Response
    {
        $path = rtrim($request->uri, '/') ?: '/';
        if (in_array($path, self::PUBLIC_PATHS, true)) {
            return $next();
        }

        $token = $request->cookies[CorpJwtService::COOKIE] ?? null;
        $payload = is_string($token) && $token !== '' ? CorpJwtService::decode($token) : null;
        if ($payload === null || empty($payload['sub']) || empty($payload['jti'])) {
            return $this->toLogin($request, $token !== null);
        }

        try {
            $session = CorpSessionService::validate((string) $payload['jti'], (int) $payload['sub']);
            $user = $session !== null ? CompanyService::findUser((int) $payload['sub']) : null;
            $company = $user !== null ? CompanyService::find((int) $user['company_id']) : null;
        } catch (\Throwable $e) {
            error_log('[CorpAuthMiddleware] ' . $e->getMessage());

            return Response::html('Service temporarily unavailable. Please try again shortly.', 503);
        }

        if ($session === null || $user === null || $company === null
            || ($user['status'] ?? '') !== 'active'
            || (int) $company['id'] !== (int) ($payload['cid'] ?? 0)
            || (int) $session['company_id'] !== (int) $company['id']
        ) {
            return $this->toLogin($request, true);
        }

        $blocked = CorpAuthService::companyBlockMessage($company);
        if ($blocked !== null) {
            CorpSessionService::revoke((string) $payload['jti']);

            return $this->toLogin($request, true, $blocked);
        }

        CorpContext::set($company, $user, $session);

        // Two-step sign-in gate: until it's passed (or set up, when required),
        // only the MFA pages and sign-out are reachable.
        $step = CorpMfaService::pendingStep($user, $session);
        if ($step !== null && !in_array($path, self::MFA_PATHS, true)) {
            if ($request->isJson()) {
                return Response::json(['error' => 'Two-step sign-in required.', 'code' => 'mfa_' . $step], 401);
            }
            $next = $request->method === 'GET' && !in_array($path, ['/company', '/company/dashboard'], true)
                ? '?next=' . rawurlencode($request->uri)
                : '';

            return Response::redirect('/company/mfa/' . $step . $next);
        }

        return $next();
    }

    /** Reachable while two-step sign-in is still pending. */
    private const MFA_PATHS = ['/company/mfa/setup', '/company/mfa/verify', '/company/logout'];

    private function toLogin(Request $request, bool $clearCookie, ?string $message = null): Response
    {
        if ($clearCookie) {
            CorpJwtService::clearCookie();
        }
        if ($request->isJson()) {
            return Response::json(['error' => $message ?? 'Please sign in again.', 'code' => 'auth_required'], 401);
        }
        $url = '/company/login';
        if ($message !== null) {
            $url .= '?error=' . rawurlencode($message);
        } elseif ($request->method === 'GET' && $request->uri !== '/company' && $request->uri !== '/company/dashboard') {
            $url .= '?next=' . rawurlencode($request->uri);
        }

        return Response::redirect($url);
    }
}
