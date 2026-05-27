<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
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
use PDO;

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

    /**
     * Per-clinic detail page: trial extension, founding clinic toggle,
     * add-on management, feature flag overrides.
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
            'modules' => $modules,
            'available' => $available,
            'flags' => $flags,
            'message' => $request->query['message'] ?? null,
        ]));
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

    /** POST /admin/clinics/{id}/founding — toggle founding clinic status */
    public function toggleFounding(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/clinics/' . (int) $id);
        }
        $clinicId = (int) $id;
        $enable = !empty($request->post['enable']);
        $until = trim((string) ($request->post['locked_until'] ?? ''));

        if ($enable) {
            $lockedUntil = $until !== '' ? $until : date('Y-m-d', strtotime('+24 months'));
            QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
                'is_founding_clinic' => 1,
                'founding_clinic_locked_at' => date('Y-m-d H:i:s'),
                'founding_clinic_locked_until' => $lockedUntil,
            ]);
        } else {
            QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
                'is_founding_clinic' => 0,
                'founding_clinic_locked_until' => null,
            ]);
        }

        return Response::redirect('/admin/clinics/' . $clinicId . '?message=founding_updated');
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

    /** GET /admin/founding-clinics */
    public function foundingClinics(Request $request): Response
    {
        $pdo = Database::connection();
        $state = ['cap' => 100, 'claimed' => 0, 'closed_at' => null];
        $clinics = [];
        try {
            $row = $pdo->query('SELECT cap, claimed, closed_at FROM founding_clinic_state WHERE id = 1')
                ->fetch(PDO::FETCH_ASSOC);
            if ($row) $state = $row;

            $clinics = $pdo->query(
                "SELECT id, name, slug, founding_clinic_locked_at, founding_clinic_locked_until,
                        DATEDIFF(founding_clinic_locked_until, CURDATE()) AS days_left
                   FROM tenants
                  WHERE is_founding_clinic = 1
                  ORDER BY founding_clinic_locked_until ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // tables don't exist yet
        }

        return Response::html(View::render('admin/founding_clinics', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'state' => $state,
            'clinics' => $clinics,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** POST /admin/founding-clinics — adjust cap */
    public function updateFoundingState(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/founding-clinics');
        }
        $cap = max(0, (int) ($request->post['cap'] ?? 100));
        $close = !empty($request->post['close']);

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE founding_clinic_state
                SET cap = :cap, closed_at = :closed
              WHERE id = 1'
        );
        $stmt->execute([
            ':cap' => $cap,
            ':closed' => $close ? date('Y-m-d H:i:s') : null,
        ]);

        return Response::redirect('/admin/founding-clinics?message=updated');
    }
}
