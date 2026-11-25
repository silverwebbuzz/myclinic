<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\CorpJwtService;
use App\Services\Corporate\CorpMfaService;
use App\Services\Corporate\CorpPasswordPolicy;
use App\Services\Corporate\CorpSessionService;
use App\Services\Corporate\TotpService;
use App\Services\CsrfService;
use App\Support\Locale\LocalDate;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Two-step sign-in (setup + challenge) and the Security page: active devices,
 * change password, personal timezone, backup codes.
 */
final class CorpSecurityController
{
    // ------------------------------------------------------------------
    // MFA setup  (/company/mfa/setup)
    // ------------------------------------------------------------------

    public function showSetup(Request $request): Response
    {
        $user = CorpContext::user() ?? [];
        if (CorpMfaService::isEnabled($user)) {
            return Response::redirect('/company/security');
        }

        return $this->setupPage($request, null, 200);
    }

    public function confirmSetup(Request $request): Response
    {
        $user = CorpContext::user() ?? [];
        if (CorpMfaService::isEnabled($user)) {
            return Response::redirect('/company/security');
        }
        $res = CorpMfaService::confirmSetup($user, (string) ($request->post['code'] ?? ''), $this->jti());
        if (!$res['ok']) {
            return $this->setupPage($request, $res['error'] ?? 'Could not turn on two-step sign-in.', 422);
        }

        return $this->codesPage($res['codes'] ?? [], $this->safeNext((string) ($request->post['next'] ?? '')), true);
    }

    // ------------------------------------------------------------------
    // MFA challenge  (/company/mfa/verify)
    // ------------------------------------------------------------------

    public function showVerify(Request $request): Response
    {
        $user = CorpContext::user() ?? [];
        $session = CorpContext::session() ?? [];
        if (!CorpMfaService::isEnabled($user) || (int) ($session['mfa_passed'] ?? 0) === 1) {
            return Response::redirect($this->safeNext((string) ($request->query['next'] ?? '')));
        }

        return $this->verifyPage($request, null, 200);
    }

    public function verify(Request $request): Response
    {
        $user = CorpContext::user() ?? [];
        $next = $this->safeNext((string) ($request->post['next'] ?? ''));
        if (!CorpMfaService::isEnabled($user)) {
            return Response::redirect($next);
        }
        $res = CorpMfaService::verifyChallenge($user, (string) ($request->post['code'] ?? ''), $this->jti());
        if (!empty($res['locked'])) {
            CorpJwtService::clearCookie();

            return Response::redirect('/company/login?error=' . rawurlencode($res['error'] ?? 'Please sign in again.'));
        }
        if (!$res['ok']) {
            return $this->verifyPage($request, $res['error'] ?? 'That code didn\'t match.', 422);
        }
        if (!empty($res['used_recovery'])) {
            $left = (int) ($res['recovery_left'] ?? 0);
            SessionFlash::put($left <= 3 ? 'corp_err' : 'corp_ok', 'You signed in with a backup code. ' . $left . ' backup code' . ($left === 1 ? '' : 's') . ' left'
                . ($left <= 3 ? '. Make a new set on the Security page.' : '.'));
        }

        return Response::redirect($next);
    }

    // ------------------------------------------------------------------
    // Security page  (/company/security)
    // ------------------------------------------------------------------

    public function index(Request $request): Response
    {
        return $this->securityPage(null);
    }

    public function changePassword(Request $request): Response
    {
        $user = CorpContext::user() ?? [];
        $current = (string) ($request->post['current_password'] ?? '');
        $new = (string) ($request->post['password'] ?? '');
        if (!password_verify($current, (string) ($user['password_hash'] ?? ''))) {
            return $this->flashBack('corp_err', 'Your current password is not correct.');
        }
        if ($current === $new) {
            return $this->flashBack('corp_err', 'The new password must be different from the current one.');
        }
        $error = CorpPasswordPolicy::check($new, (string) ($request->post['password_confirm'] ?? ''), (string) $user['email'], (string) $user['name']);
        if ($error !== null) {
            return $this->flashBack('corp_err', $error);
        }
        Database::connection()->prepare('UPDATE corp_users SET password_hash = :h, updated_at = :t WHERE id = :id')
            ->execute(['h' => CorpPasswordPolicy::hash($new), 't' => LocalDate::nowUtc(), 'id' => (int) $user['id']]);
        $ended = CorpSessionService::revokeAllForUser((int) $user['id'], $this->jti());
        CorpAudit::byUser('password_changed', 'corp_user', (int) $user['id'], ['other_sessions_ended' => $ended]);

        return $this->flashBack('corp_ok', 'Password changed.' . ($ended > 0 ? ' You were signed out on ' . $ended . ' other device' . ($ended === 1 ? '' : 's') . '.' : ''));
    }

    public function saveTimezone(Request $request): Response
    {
        $user = CorpContext::user() ?? [];
        $tz = trim((string) ($request->post['timezone'] ?? ''));
        if ($tz !== '' && !LocalDate::isValidZone($tz)) {
            return $this->flashBack('corp_err', 'Please choose a time zone from the list.');
        }
        Database::connection()->prepare('UPDATE corp_users SET timezone = :tz, updated_at = :t WHERE id = :id')
            ->execute(['tz' => $tz !== '' ? $tz : null, 't' => LocalDate::nowUtc(), 'id' => (int) $user['id']]);
        CorpAudit::byUser('timezone_changed', 'corp_user', (int) $user['id'], ['timezone' => $tz !== '' ? $tz : 'company default']);

        return $this->flashBack('corp_ok', 'Time zone saved.');
    }

    public function revokeSession(Request $request, string $id): Response
    {
        $user = CorpContext::user() ?? [];
        $st = Database::connection()->prepare(
            'SELECT jti FROM corp_sessions WHERE id = :id AND user_id = :uid AND company_id = :cid AND revoked_at IS NULL LIMIT 1'
        );
        $st->execute(['id' => (int) $id, 'uid' => (int) $user['id'], 'cid' => (int) $user['company_id']]);
        $jti = $st->fetchColumn();
        if ($jti === false) {
            return $this->flashBack('corp_err', 'That session has already ended.');
        }
        if ((string) $jti === $this->jti()) {
            return $this->flashBack('corp_err', 'To end this session, use Sign out.');
        }
        CorpSessionService::revoke((string) $jti);
        CorpAudit::byUser('session_revoked', 'corp_session', (int) $id);

        return $this->flashBack('corp_ok', 'Device signed out.');
    }

    public function revokeOthers(Request $request): Response
    {
        $user = CorpContext::user() ?? [];
        $n = CorpSessionService::revokeAllForUser((int) $user['id'], $this->jti());
        CorpAudit::byUser('sessions_revoked_others', null, null, ['count' => $n]);

        return $this->flashBack('corp_ok', $n > 0 ? 'Signed out on ' . $n . ' other device' . ($n === 1 ? '' : 's') . '.' : 'No other devices were signed in.');
    }

    public function regenerateCodes(Request $request): Response
    {
        $res = CorpMfaService::regenerateRecoveryCodes(CorpContext::user() ?? [], (string) ($request->post['code'] ?? ''));
        if (!$res['ok']) {
            return $this->flashBack('corp_err', $res['error'] ?? 'Could not make new backup codes.');
        }

        return $this->codesPage($res['codes'] ?? [], '/company/security', false);
    }

    public function disableMfa(Request $request): Response
    {
        $res = CorpMfaService::disable(
            CorpContext::user() ?? [],
            (string) ($request->post['current_password'] ?? ''),
            (string) ($request->post['code'] ?? ''),
        );

        return $this->flashBack($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Two-step sign-in is turned off.' : ($res['error'] ?? 'Could not turn it off.'));
    }

    // ------------------------------------------------------------------

    private function setupPage(Request $request, ?string $error, int $status): Response
    {
        $user = CorpContext::user() ?? [];
        $secret = CorpMfaService::isConfigured() ? CorpMfaService::pendingSecret((int) $user['id']) : null;

        return Response::html(View::render('corporate/mfa_setup', [
            'csrf' => CsrfService::token(),
            'error' => $error,
            'configured' => $secret !== null,
            'secretGrouped' => $secret !== null ? TotpService::groupForDisplay($secret) : '',
            'otpauth' => $secret !== null ? TotpService::uri($secret, (string) $user['email'], 'eClinicPro Workforce') : '',
            'email' => (string) ($user['email'] ?? ''),
            'required' => CorpMfaService::required(),
            'next' => $this->safeNext((string) ($request->post['next'] ?? $request->query['next'] ?? '')),
        ]), $status);
    }

    private function verifyPage(Request $request, ?string $error, int $status): Response
    {
        return Response::html(View::render('corporate/mfa_verify', [
            'csrf' => CsrfService::token(),
            'error' => $error,
            'email' => (string) ((CorpContext::user() ?? [])['email'] ?? ''),
            'next' => $this->safeNext((string) ($request->post['next'] ?? $request->query['next'] ?? '')),
        ]), $status);
    }

    /** @param list<string> $codes */
    private function codesPage(array $codes, string $next, bool $firstTime): Response
    {
        $company = CorpContext::company() ?? [];

        return Response::html(View::render('corporate/mfa_codes', [
            'codes' => $codes,
            'next' => $next,
            'firstTime' => $firstTime,
            'email' => (string) ((CorpContext::user() ?? [])['email'] ?? ''),
            'companyName' => (string) ($company['legal_name'] ?? ''),
        ]));
    }

    private function securityPage(?string $error): Response
    {
        $user = CorpContext::user() ?? [];
        $loc = CorpContext::locale();
        $st = Database::connection()->prepare(
            'SELECT id, jti, ip, user_agent, created_at, last_seen_at
               FROM corp_sessions
              WHERE user_id = :uid AND company_id = :cid AND revoked_at IS NULL AND expires_at > :now AND last_seen_at > :idle
              ORDER BY last_seen_at DESC LIMIT 50'
        );
        $st->execute([
            'uid' => (int) $user['id'],
            'cid' => (int) $user['company_id'],
            'now' => LocalDate::nowUtc(),
            'idle' => LocalDate::utcPlusSeconds(-CorpSessionService::idleSeconds()),
        ]);
        $current = $this->jti();
        $sessions = array_map(static fn (array $s): array => $s + [
            'device' => self::deviceLabel((string) ($s['user_agent'] ?? '')),
            'is_current' => hash_equals((string) $s['jti'], $current),
        ], $st->fetchAll());

        return Response::html(View::render('corporate/security', [
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => $user,
            'loc' => $loc,
            'sessions' => $sessions,
            'mfaEnabled' => CorpMfaService::isEnabled($user),
            'mfaRequired' => CorpMfaService::required(),
            'mfaConfigured' => CorpMfaService::isConfigured(),
            'recoveryLeft' => CorpMfaService::recoveryCodesLeft($user),
            'companyTz' => (string) ((CorpContext::company() ?? [])['timezone'] ?? 'UTC'),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => $error ?? SessionFlash::pull('corp_err'),
        ]));
    }

    /** "Chrome on Windows" from a user-agent string (rough, for display only). */
    public static function deviceLabel(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return $browser . ' on ' . $os;
    }

    private function flashBack(string $key, string $message): Response
    {
        SessionFlash::put($key, $message);

        return Response::redirect('/company/security');
    }

    private function jti(): string
    {
        return (string) ((CorpContext::session() ?? [])['jti'] ?? '');
    }

    private function safeNext(string $next): string
    {
        if ($next !== '' && preg_match('#^/company(/[A-Za-z0-9/_\-]*)?(\?[A-Za-z0-9=&_\-%]*)?$#', $next) === 1
            && !preg_match('#^/company/(login|logout|mfa)#', $next)) {
            return $next;
        }

        return '/company/dashboard';
    }
}
