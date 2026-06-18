<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\PlanService;
use App\Support\View;
use PDO;

/**
 * PlanAdminController — /admin/plans (super-admin only).
 *
 * CRUD over the `plans` catalog (migration 030). This is the source of truth
 * PlanService reads; config/plans.php is only a fallback when the table is
 * empty/missing. Edits here flow straight into onboarding, the pricing page,
 * and Cashfree checkout.
 *
 * `modules` accepts the literal "all_paid" (every core module) or a
 * comma/newline list of module ids. highlights/limits are one-per-line.
 */
final class PlanAdminController
{
    public function index(Request $request): Response
    {
        $rows = [];
        try {
            $rows = Database::connection()
                ->query('SELECT * FROM plans ORDER BY sort_order ASC, name ASC')
                ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { /* table not migrated yet */ }

        return Response::html(View::render('admin/plans', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'plans' => $rows,
            'tableMissing' => $rows === [] && !$this->tableExists(),
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** POST /admin/plans — create or update one plan. */
    public function save(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/plans');
        }

        $planId = $this->slugify((string) ($request->post['plan_id'] ?? ''));
        $name = trim((string) ($request->post['name'] ?? ''));
        if ($planId === '' || $name === '') {
            return Response::redirect('/admin/plans?message=missing_fields');
        }

        $isEdit = ($request->post['mode'] ?? '') === 'edit';

        $data = [
            'name' => $name,
            'tagline' => trim((string) ($request->post['tagline'] ?? '')),
            'monthly_inr' => max(0, (float) ($request->post['monthly_inr'] ?? 0)),
            'yearly_inr' => max(0, (float) ($request->post['yearly_inr'] ?? 0)),
            'seat_limit' => max(0, min(255, (int) ($request->post['seat_limit'] ?? 2))),
            'patient_limit' => $request->post['patient_limit'] === '' || !isset($request->post['patient_limit'])
                ? null : max(0, (int) $request->post['patient_limit']),
            'trial_days' => max(0, (int) ($request->post['trial_days'] ?? 0)),
            'modules' => $this->encodeModules((string) ($request->post['modules'] ?? '')),
            'highlights' => $this->encodeList((string) ($request->post['highlights'] ?? '')),
            'limits' => $this->encodeList((string) ($request->post['limits'] ?? '')),
            'featured' => isset($request->post['featured']) ? 1 : 0,
            'is_active' => isset($request->post['is_active']) ? 1 : 0,
            'sort_order' => (int) ($request->post['sort_order'] ?? 100),
        ];

        $pdo = Database::connection();
        try {
            if ($isEdit) {
                // plan_id is immutable on edit — it's referenced by tenants.plan
                // and saas_invoices.plan_id.
                $sets = implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($data)));
                $stmt = $pdo->prepare("UPDATE plans SET $sets WHERE plan_id = :plan_id");
                $data['plan_id'] = $planId;
                $stmt->execute($data);
            } else {
                $data['plan_id'] = $planId;
                $cols = implode(', ', array_keys($data));
                $vals = implode(', ', array_map(static fn ($k) => ":$k", array_keys($data)));
                $stmt = $pdo->prepare("INSERT INTO plans ($cols) VALUES ($vals)");
                $stmt->execute($data);
            }
        } catch (\Throwable $e) {
            return Response::redirect('/admin/plans?message=save_error');
        }

        PlanService::flushCache();
        return Response::redirect('/admin/plans?message=saved');
    }

    /** POST /admin/plans/{id}/toggle — flip is_active. */
    public function toggle(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/plans');
        }
        try {
            Database::connection()
                ->prepare('UPDATE plans SET is_active = 1 - is_active WHERE plan_id = ?')
                ->execute([$id]);
            PlanService::flushCache();
        } catch (\Throwable $e) { /* ignore */ }

        return Response::redirect('/admin/plans?message=toggled');
    }

    /** POST /admin/plans/{id}/delete — remove a plan. */
    public function delete(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/plans');
        }
        // Guard: don't delete a plan that tenants are still on — they'd lose
        // their pricing reference. Deactivate instead.
        try {
            $inUse = Database::connection()
                ->prepare('SELECT COUNT(*) FROM tenants WHERE plan = ?');
            $inUse->execute([$id]);
            if ((int) $inUse->fetchColumn() > 0) {
                return Response::redirect('/admin/plans?message=in_use');
            }
            Database::connection()->prepare('DELETE FROM plans WHERE plan_id = ?')->execute([$id]);
            PlanService::flushCache();
        } catch (\Throwable $e) {
            return Response::redirect('/admin/plans?message=save_error');
        }

        return Response::redirect('/admin/plans?message=deleted');
    }

    /** "all_paid" stays a JSON string; otherwise a JSON array of module ids. */
    private function encodeModules(string $raw): string
    {
        if (strtolower(trim($raw)) === 'all_paid') {
            return json_encode('all_paid');
        }

        return json_encode($this->splitLines($raw));
    }

    private function encodeList(string $raw): string
    {
        return json_encode($this->splitLines($raw));
    }

    /** @return list<string> */
    private function splitLines(string $raw): array
    {
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($s) => $s !== ''));
    }

    private function tableExists(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM plans LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function slugify(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
        return trim($s, '_');
    }
}
