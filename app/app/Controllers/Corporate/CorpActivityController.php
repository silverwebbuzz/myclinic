<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\CsrfService;
use App\Support\View;

/** /company/activity — the company's own audit trail (corp_audit_log). */
final class CorpActivityController
{
    public const PER_PAGE = 50;

    /** Plain-English labels for actions. Unknown actions fall back to the raw code. */
    public const LABELS = [
        'company_registered' => 'Registered the company',
        'company_approved' => 'Company approved by eClinicPro',
        'company_rejected' => 'Company registration rejected by eClinicPro',
        'company_suspended' => 'Company suspended by eClinicPro',
        'company_reactivated' => 'Company reactivated by eClinicPro',
        'trial_extended' => 'Trial extended by eClinicPro',
        'login' => 'Signed in',
        'logout' => 'Signed out',
        'login_failed' => 'Failed sign-in (wrong password)',
        'login_locked' => 'Sign-in locked after too many wrong passwords',
        'email_verified' => 'Confirmed email address',
        'password_reset_requested' => 'Asked for a password reset link',
        'password_reset' => 'Reset password with an email link',
        'password_changed' => 'Changed password',
        'timezone_changed' => 'Changed time zone',
        'mfa_enabled' => 'Turned on two-step sign-in',
        'mfa_disabled' => 'Turned off two-step sign-in',
        'mfa_passed' => 'Passed two-step sign-in',
        'mfa_failed' => 'Wrong two-step sign-in code',
        'mfa_recovery_used' => 'Signed in with a backup code',
        'mfa_recovery_regenerated' => 'Made new backup codes',
        'mfa_reset_by_admin' => 'Reset someone\'s two-step sign-in',
        'session_revoked' => 'Signed out a device',
        'sessions_revoked_others' => 'Signed out all other devices',
        'user_invited' => 'Invited a user',
        'invite_resent' => 'Resent an invitation',
        'invite_revoked' => 'Cancelled an invitation',
        'invite_accepted' => 'Accepted an invitation',
        'roles_changed' => 'Changed someone\'s roles',
        'user_disabled' => 'Disabled a user',
        'user_enabled' => 'Enabled a user',
        'role_created' => 'Created a custom role',
        'role_updated' => 'Edited a custom role',
        'role_deleted' => 'Deleted a custom role',
        'permission_denied' => 'Tried to open a page without permission',
        'profile_updated' => 'Updated the company profile',
        'document_uploaded' => 'Uploaded a document',
        'document_deleted' => 'Deleted a document',
        'document_approved' => 'Document approved by eClinicPro',
        'document_rejected' => 'Document not accepted by eClinicPro',
        'plan_set' => 'Plan updated by eClinicPro',
        'mfa_reset_by_eclinicpro' => 'Two-step sign-in reset by eClinicPro support',
    ];

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard('audit.view')) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $st = Database::connection()->prepare(
            "SELECT a.id, a.actor_type, a.actor_id, a.action, a.entity_type, a.entity_id, a.details, a.ip, a.created_at,
                    u.name AS actor_name, u.email AS actor_email, t.name AS target_name
               FROM corp_audit_log a
               LEFT JOIN corp_users u ON a.actor_type = 'corp_user' AND u.id = a.actor_id AND u.company_id = a.company_id
               LEFT JOIN corp_users t ON a.entity_type = 'corp_user' AND t.id = a.entity_id AND t.company_id = a.company_id
              WHERE a.company_id = :cid
              ORDER BY a.id DESC
              LIMIT " . (self::PER_PAGE + 1) . ' OFFSET ' . $offset
        );
        $st->execute(['cid' => $cid]);
        $rows = $st->fetchAll();
        $hasMore = count($rows) > self::PER_PAGE;

        return Response::html(View::render('corporate/activity', [
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'rows' => array_slice($rows, 0, self::PER_PAGE),
            'page' => $page,
            'hasMore' => $hasMore,
            'labels' => self::LABELS,
        ]));
    }
}
