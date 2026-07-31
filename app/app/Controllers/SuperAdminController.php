<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\ChurnOutreachService;
use App\Services\CsrfService;
use App\Services\ImpersonationService;
use App\Services\PlanService;
use App\Services\RecaptchaService;
use App\Services\SuperAdminAuthService;
use App\Services\SuperAdminJwtService;
use App\Services\SuperAdminClinicService;
use App\Services\SuperAdminMetricsService;
use App\Services\TenantDeletionService;
use App\Support\View;
use PDO;

final class SuperAdminController
{
    public function showLogin(Request $request): Response
    {
        return Response::html(View::render('admin/login', [
            'csrf' => CsrfService::token(),
            'error' => null,
            'captchaEnabled' => RecaptchaService::enabled(),
            'captchaSiteKey' => RecaptchaService::siteKey(),
        ]));
    }

    public function login(Request $request): Response
    {
        $captchaOk = !RecaptchaService::enabled()
            || RecaptchaService::verify(
                is_string($request->post['g-recaptcha-response'] ?? null)
                    ? $request->post['g-recaptcha-response']
                    : null,
                is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : null,
            );

        if (!$captchaOk) {
            return Response::html(View::render('admin/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Please complete the captcha.',
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
            ]), 400);
        }

        $admin = SuperAdminAuthService::attempt(
            $request->post['email'] ?? '',
            $request->post['password'] ?? '',
        );

        if ($admin === null) {
            return Response::html(View::render('admin/login', [
                'csrf' => CsrfService::token(),
                'error' => 'Invalid credentials.',
                'captchaEnabled' => RecaptchaService::enabled(),
                'captchaSiteKey' => RecaptchaService::siteKey(),
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

    /** GET /admin/patients — onboarded clinic patients across every clinic. */
    public function patients(Request $request): Response
    {
        $search = trim((string) ($request->query['q'] ?? ''));
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $data = \App\Services\PatientAdminService::patientsList($search, $page);

        return Response::html(View::render('admin/patients', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'patients' => $data['patients'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'search' => $search,
        ]));
    }

    /** GET /admin/signups — people who signed up from the public site. */
    public function signups(Request $request): Response
    {
        $search = trim((string) ($request->query['q'] ?? ''));
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $data = \App\Services\PatientAdminService::signupsList($search, $page);

        return Response::html(View::render('admin/signups', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'signups' => $data['signups'],
            'total' => $data['total'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'search' => $search,
        ]));
    }

    /** GET /admin/signups/{id} — one signup's cross-clinic activity. */
    public function signupDetail(Request $request, string $id): Response
    {
        $data = \App\Services\PatientAdminService::signupDetail((int) $id);
        if ($data === null) {
            return Response::redirect('/admin/signups?error=not_found');
        }

        return Response::html(View::render('admin/signup_detail', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'identity' => $data['identity'],
            'charts' => $data['charts'],
            'appointments' => $data['appointments'],
            'sessions' => $data['sessions'],
            'wishlist' => $data['wishlist'],
        ]));
    }

    /** GET /admin/patients/{id} — one patient's history and activity. */
    public function patientDetail(Request $request, string $id): Response
    {
        $data = \App\Services\PatientAdminService::patientDetail((int) $id);
        if ($data === null) {
            return Response::redirect('/admin/patients?error=not_found');
        }

        return Response::html(View::render('admin/patient_detail', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'patient' => $data['patient'],
            'visits' => $data['visits'],
            'appointments' => $data['appointments'],
            'activity' => $data['activity'],
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

    /** Phase 3 cron: auto-discover prescription templates. */
    public function runTemplateDiscovery(Request $request): Response
    {
        $created = \App\Support\TemplateDiscovery::run();
        return Response::json(['ok' => true, 'suggestions_created' => $created]);
    }

    /** Phase 4 cron: queue WhatsApp follow-up reminders (daily 09:00). */
    public function runFollowUpReminders(Request $request): Response
    {
        $queued = \App\Services\FollowUpService::runReminders();
        return Response::json(['ok' => true, 'reminders_queued' => $queued]);
    }

    /** Phase 4 cron: mark stale follow-ups missed (daily 03:00). */
    public function runFollowUpMarkMissed(Request $request): Response
    {
        $missed = \App\Services\FollowUpService::runMarkMissed();
        return Response::json(['ok' => true, 'marked_missed' => $missed]);
    }

    /**
     * Per-clinic detail page: trial extension, add-on management,
     * feature flag overrides.
     */
    public function clinicDetail(Request $request, string $id): Response
    {
        $clinicId = (int) $id;
        if ($clinicId < 1) {
            return Response::redirect('/admin/clinics');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $clinicId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tenant) {
            return Response::redirect('/admin/clinics?error=not_found');
        }

        // Active add-ons
        $stmt = $pdo->prepare(
            'SELECT cm.*, mc.name AS module_name
               FROM clinic_modules cm
          LEFT JOIN module_catalog mc ON mc.id = cm.module_id
              WHERE cm.clinic_id = :cid'
        );
        $stmt->execute([':cid' => $clinicId]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Available add-ons (for activation dropdown)
        $available = $pdo->query(
            "SELECT id, name, price_monthly_usd FROM module_catalog WHERE is_active = 1 ORDER BY sort_order"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Feature flags status for this clinic
        $flags = [];
        try {
            $rows = $pdo->query('SELECT flag_key, is_enabled, scope, beta_tenant_ids FROM feature_flags')
                ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $betaIds = $r['beta_tenant_ids'] ? json_decode((string) $r['beta_tenant_ids'], true) : [];
                $on = match ($r['scope']) {
                    'all' => (bool) $r['is_enabled'],
                    'beta' => $r['is_enabled'] && is_array($betaIds) && in_array($clinicId, $betaIds, true),
                    default => false,
                };
                $flags[] = ['key' => $r['flag_key'], 'scope' => $r['scope'], 'on' => $on];
            }
        } catch (\Throwable $e) {
            // feature_flags table doesn't exist yet — skip silently.
        }

        return Response::html(View::render('admin/clinic_detail', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'tenant' => $tenant,
            'overview' => SuperAdminClinicService::overview($clinicId),
            'planLabel' => PlanService::get((string) ($tenant['plan'] ?? 'standard'))['name'] ?? ucfirst((string) ($tenant['plan'] ?? '')),
            'billingStatus' => SuperAdminMetricsService::billingStatus($tenant),
            'specialties' => \App\Support\SpecialtyCatalog::all(),
            'modules' => $modules,
            'available' => $available,
            'flags' => $flags,
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    /** POST /admin/clinics/{id}/delete — permanently remove clinic and all data. */
    public function deleteClinic(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/clinics/' . (int) $id);
        }

        $clinicId = (int) $id;
        $confirm = trim((string) ($request->post['confirm_slug'] ?? ''));

        $tenant = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($tenant === null) {
            return Response::redirect('/admin/clinics?error=not_found');
        }

        if ($confirm !== (string) ($tenant['slug'] ?? '')) {
            return Response::redirect('/admin/clinics/' . $clinicId . '?error=confirm_slug');
        }

        try {
            TenantDeletionService::delete($clinicId);
        } catch (\Throwable $e) {
            return Response::redirect('/admin/clinics/' . $clinicId . '?error=delete_failed');
        }

        return Response::redirect('/admin/clinics?message=clinic_deleted');
    }

    /** POST /admin/clinics/{id}/extend-trial */
    public function extendTrial(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/clinics/' . (int) $id);
        }
        $clinicId = (int) $id;
        $admin = RequestContext::superAdmin();

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT trial_ends_at, trial_extension_granted
               FROM tenants WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $clinicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return Response::redirect('/admin/clinics?error=not_found');
        }
        if ((int) $row['trial_extension_granted'] === 1) {
            return Response::redirect('/admin/clinics/' . $clinicId . '?message=already_extended');
        }

        $current = $row['trial_ends_at'] ?: date('Y-m-d');
        $base = max($current, date('Y-m-d'));
        $newDate = date('Y-m-d', strtotime($base . ' +15 days'));

        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
            'trial_ends_at' => $newDate,
            'trial_extension_granted' => 1,
            'trial_extension_granted_at' => date('Y-m-d H:i:s'),
            'trial_extension_granted_by' => $admin['id'] ?? null,
        ]);

        return Response::redirect('/admin/clinics/' . $clinicId . '?message=trial_extended');
    }

    /** POST /admin/clinics/{id}/plan — assign free or standard (admin only). */
    public function setPlan(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/clinics/' . (int) $id);
        }

        $clinicId = (int) $id;
        $planId = (string) ($request->post['plan'] ?? 'standard');
        if (!in_array($planId, ['free', 'standard'], true)) {
            return Response::redirect('/admin/clinics/' . $clinicId . '?message=invalid_plan');
        }

        $exists = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->count() > 0;
        if (!$exists) {
            return Response::redirect('/admin/clinics?error=not_found');
        }

        PlanService::applyPlanToTenant($clinicId, $planId, false);

        return Response::redirect('/admin/clinics/' . $clinicId . '?message=plan_updated');
    }

    /** POST /admin/clinics/{id}/addon — manually activate or deactivate an addon */
    public function toggleAddon(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/clinics/' . (int) $id);
        }
        $clinicId = (int) $id;
        $moduleId = trim((string) ($request->post['module_id'] ?? ''));
        $activate = !empty($request->post['activate']);

        if ($moduleId === '') {
            return Response::redirect('/admin/clinics/' . $clinicId);
        }

        $pdo = Database::connection();
        if ($activate) {
            $stmt = $pdo->prepare(
                'INSERT INTO clinic_modules (clinic_id, module_id, activated_at, billing_cycle, is_active, is_trial)
                 VALUES (:cid, :mid, NOW(), :cycle, 1, 0)
                 ON DUPLICATE KEY UPDATE is_active = 1'
            );
            $stmt->execute([
                ':cid' => $clinicId,
                ':mid' => $moduleId,
                ':cycle' => 'monthly',
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE clinic_modules SET is_active = 0
                  WHERE clinic_id = :cid AND module_id = :mid'
            );
            $stmt->execute([':cid' => $clinicId, ':mid' => $moduleId]);
        }

        return Response::redirect('/admin/clinics/' . $clinicId . '?message=addon_updated');
    }

    /** GET /admin/feature-flags */
    public function featureFlags(Request $request): Response
    {
        $pdo = Database::connection();
        $rows = [];
        try {
            $rows = $pdo->query('SELECT * FROM feature_flags ORDER BY flag_key')
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // feature_flags table doesn't exist yet — show empty state.
        }

        return Response::html(View::render('admin/feature_flags', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'flags' => $rows,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /**
     * GET /admin/payment-gateway — read-only status of the subscription payment
     * gateway. Keys live in .env (never edited here); this just surfaces which
     * gateway is active, the mode, and whether keys are configured.
     */
    public function paymentGateway(Request $request): Response
    {
        return Response::html(View::render('admin/payment_gateway', [
            'admin' => RequestContext::superAdmin(),
            'gateway' => \App\Services\BillingGatewayService::status(),
        ]));
    }

    /** POST /admin/feature-flags/{key} */
    public function updateFeatureFlag(Request $request, string $key): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/feature-flags');
        }

        $enabled = !empty($request->post['is_enabled']) ? 1 : 0;
        $scope = $request->post['scope'] ?? 'all';
        if (!in_array($scope, ['all', 'beta', 'tenant'], true)) {
            $scope = 'all';
        }

        // beta_tenant_ids is a comma-separated string in the form → JSON array
        $betaIds = null;
        $raw = trim((string) ($request->post['beta_tenant_ids'] ?? ''));
        if ($scope === 'beta' && $raw !== '') {
            $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', $raw) ?: []));
            $betaIds = json_encode(array_values($ids));
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE feature_flags
                SET is_enabled = :en, scope = :sc, beta_tenant_ids = :bt
              WHERE flag_key = :k'
        );
        $stmt->execute([
            ':en' => $enabled,
            ':sc' => $scope,
            ':bt' => $betaIds,
            ':k' => $key,
        ]);

        return Response::redirect('/admin/feature-flags?message=updated');
    }

    /** GET /admin/email — SMTP / Mailgun status + test sender. */
    public function email(Request $request): Response
    {
        return Response::html(View::render('admin/email', [
            'admin' => RequestContext::superAdmin(),
            'email' => \App\Services\SmtpMailService::status(),
            'logLines' => \App\Services\SmtpMailService::recentLogLines(30),
            'testResult' => null,
            'csrf' => CsrfService::token(),
        ]));
    }

    /** POST /admin/email/test — send a diagnostic test email. */
    public function testEmail(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/email');
        }

        $to = trim((string) ($request->post['test_to'] ?? ''));
        $template = trim((string) ($request->post['test_template'] ?? 'welcome'));
        if (!in_array($template, ['welcome', 'doctor_approved', 'password_reset', 'staff_invite', 'appointment_reminder', 'rx_delivery', 'invoice_paid'], true)) {
            $template = 'welcome';
        }

        $result = \App\Services\MailService::sendTest($to, $template);

        return Response::html(View::render('admin/email', [
            'admin' => RequestContext::superAdmin(),
            'email' => \App\Services\SmtpMailService::status(),
            'logLines' => \App\Services\SmtpMailService::recentLogLines(30),
            'testResult' => $result,
            'testTo' => $to,
            'testTemplate' => $template,
            'csrf' => CsrfService::token(),
        ]));
    }

}
