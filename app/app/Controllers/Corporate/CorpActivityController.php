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
        'branch_created' => 'Added a branch', 'branch_updated' => 'Edited a branch', 'branch_activated' => 'Re-activated a branch', 'branch_deactivated' => 'Deactivated a branch',
        'department_created' => 'Added a department', 'department_updated' => 'Edited a department', 'department_activated' => 'Re-activated a department', 'department_deactivated' => 'Deactivated a department',
        'job_role_created' => 'Added a job role', 'job_role_updated' => 'Edited a job role', 'job_role_activated' => 'Re-activated a job role', 'job_role_deactivated' => 'Deactivated a job role',
        'employee_created' => 'Added an employee', 'employee_updated' => 'Edited an employee', 'employee_terminated' => 'Marked an employee as left',
        'employee_rehired' => 'Re-hired an employee', 'employee_on_leave' => 'Marked an employee as on leave', 'employee_returned' => 'Marked an employee as back at work',
        'employee_id_viewed' => 'Viewed an employee\'s full ID number', 'employees_exported' => 'Exported the employee list',
        'employees_imported' => 'Imported employees from a file', 'employee_invited' => 'Invited an employee to confirm', 'employees_invited' => 'Sent invitations to employees',
        'screening_done_elsewhere' => 'Recorded a check as done elsewhere', 'screening_waived' => 'Waived a screening check', 'screening_exported' => 'Exported the screening list',
        'programme_template_updated' => 'Updated a programme to a new template version',
        'provider_requested' => 'Asked a clinic to join the network', 'provider_request_withdrawn' => 'Withdrew a clinic request', 'provider_ended' => 'Ended a clinic arrangement',
        'provider_branches_changed' => 'Changed the branches a clinic serves', 'provider_rate_saved' => 'Saved an agreed clinic price', 'provider_rate_removed' => 'Removed an agreed clinic price',
        'booking_created' => 'Booked a health check', 'booking_moved' => 'Moved a booking', 'booking_cancelled' => 'Cancelled a booking',
        'booking_cancelled_by_clinic' => 'Cancelled the booking', 'booking_no_show' => 'Marked a booking as did not attend',
        'report_received' => 'Test report received (seen by the doctor only)',
        'certificate_issued' => 'Issued a fitness certificate', 'certificate_replaced' => 'Issued a corrected fitness certificate',
        'certificate_revoked' => 'Revoked a fitness certificate', 'certificate_downloaded' => 'Downloaded a fitness certificate',
        'provider_accepted' => 'Accepted the request to join the network', 'provider_declined' => 'Declined the request to join the network', 'provider_ended_by_clinic' => 'Ended the arrangement with the company',
        'programme_adopted' => 'Adopted a screening template', 'programme_created' => 'Created a screening programme', 'programme_updated' => 'Edited a screening programme',
        'programme_archived' => 'Archived a screening programme', 'programme_reactivated' => 'Re-activated a screening programme',
        'programme_item_added' => 'Added a check to a programme', 'programme_item_removed' => 'Removed a check from a programme',
        'programme_mandatory_removed' => 'Removed a MANDATORY check from a programme', 'programme_frequency_changed' => 'Changed how often a check is done',
        'programme_assigned' => 'Applied a programme to people', 'programme_unassigned' => 'Stopped a programme applying to people',
        'employee_link_confirmed' => 'Employee confirmed their link', 'employee_link_declined' => 'Employee said the record isn\'t them', 'employee_import_cancelled' => 'Cancelled an employee import',
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
