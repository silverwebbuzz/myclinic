<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\DietService;
use App\Services\VisitService;
use PDO;

/**
 * DietTemplateController — Phase 4.
 *
 * Manages the diet_templates library (system + clinic + personal scope)
 * and applies a template into a visit's diet_plans row.
 *
 *   GET  /api/v1/diet-templates?scope=all|system|clinic|mine
 *   GET  /api/v1/diet-templates/{id}
 *   POST /api/v1/diet-templates                       — save current as template
 *   POST /api/v1/visits/{visitId}/apply-diet/{tid}    — instantiate into visit
 *   POST /api/v1/diet-templates/{id}/delete
 */
final class DietTemplateController
{
    public function index(Request $request): Response
    {
        if ($denied = ModuleGate::require('diet')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);
        $scope = (string) ($request->query['scope'] ?? 'all');

        $pdo = Database::connection();

        // System templates (clinic_id NULL) are always available.
        // Plus clinic-wide + this doctor's personal, per scope.
        $where = '(clinic_id IS NULL';
        $params = [];
        if ($scope === 'system') {
            $where = '(clinic_id IS NULL';
        } elseif ($scope === 'clinic') {
            $where = '(clinic_id = :c AND doctor_id IS NULL';
            $params[':c'] = $clinicId;
        } elseif ($scope === 'mine') {
            $where = '(clinic_id = :c AND doctor_id = :d';
            $params[':c'] = $clinicId;
            $params[':d'] = $doctorId;
        } else {
            // all: system + this clinic (clinic-wide or this doctor's)
            $where = '(clinic_id IS NULL OR (clinic_id = :c AND (doctor_id IS NULL OR doctor_id = :d))';
            $params[':c'] = $clinicId;
            $params[':d'] = $doctorId;
        }
        $where .= ') AND is_active = 1';

        $stmt = $pdo->prepare(
            "SELECT id, name, description, condition_tag, veg_type, use_count, clinic_id, doctor_id
               FROM diet_templates
              WHERE $where
              ORDER BY (clinic_id IS NULL) DESC, use_count DESC, name ASC
              LIMIT 60"
        );
        $stmt->execute($params);

        return Response::json(['templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function show(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('diet')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();

        $stmt = Database::connection()->prepare(
            'SELECT * FROM diet_templates
              WHERE id = :id AND (clinic_id IS NULL OR clinic_id = :c) LIMIT 1'
        );
        $stmt->execute([':id' => (int) $id, ':c' => $clinicId]);
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) {
            return Response::json(['error' => 'Not found'], 404);
        }
        $tpl['plan_json'] = json_decode((string) $tpl['plan_json'], true);
        return Response::json(['template' => $tpl]);
    }

    public function create(Request $request): Response
    {
        if ($denied = ModuleGate::require('diet')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);

        $body = json_decode($request->rawBody ?? '{}', true);
        $name = trim((string) ($body['name'] ?? ''));
        $scope = ($body['scope'] ?? 'mine') === 'clinic' ? 'clinic' : 'mine';
        $plan = $body['plan_json'] ?? null;

        if ($name === '' || !is_array($plan)) {
            return Response::json(['error' => 'name and plan_json required'], 422);
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO diet_templates
                (clinic_id, doctor_id, name, description, condition_tag, veg_type, plan_json, is_active)
             VALUES (:c, :d, :n, :desc, :tag, :veg, :plan, 1)'
        );
        $stmt->execute([
            ':c' => $clinicId,
            ':d' => $scope === 'clinic' ? null : $doctorId,
            ':n' => mb_substr($name, 0, 120),
            ':desc' => isset($body['description']) ? mb_substr((string) $body['description'], 0, 240) : null,
            ':tag' => $body['condition_tag'] ?? null,
            ':veg' => in_array($body['veg_type'] ?? 'any', ['veg','nonveg','vegan','eggetarian','any'], true)
                       ? ($body['veg_type'] ?? 'any') : 'any',
            ':plan' => json_encode($plan),
        ]);

        $tid = (int) Database::connection()->lastInsertId();
        AuditService::log($request, 'INSERT', 'diet_templates', $tid);
        return Response::json(['ok' => true, 'template_id' => $tid]);
    }

    /** Apply a template's plan_json into the visit's diet_plans row. */
    public function applyToVisit(Request $request, string $visitId, string $id): Response
    {
        if ($denied = ModuleGate::require('diet')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $vid = (int) $visitId;
        $tid = (int) $id;

        $visit = VisitService::find($clinicId, $vid);
        if ($visit === null) {
            return Response::json(['error' => 'Visit not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM diet_templates
              WHERE id = :id AND (clinic_id IS NULL OR clinic_id = :c) LIMIT 1'
        );
        $stmt->execute([':id' => $tid, ':c' => $clinicId]);
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) {
            return Response::json(['error' => 'Template not found'], 404);
        }

        $plan = json_decode((string) $tpl['plan_json'], true) ?: [];

        // Reuse DietService::save to write the diet_plans row.
        DietService::save($clinicId, $vid, (int) $visit['patient_id'], [
            'condition' => $tpl['condition_tag'] ?? null,
            'veg_type' => $tpl['veg_type'] ?? 'veg',
            'plan_json' => $plan,
        ]);

        // Stamp template_id + bump use_count (best-effort — columns are Phase 4).
        try {
            $pdo->prepare('UPDATE diet_plans SET template_id = :t WHERE visit_id = :v AND clinic_id = :c')
                ->execute([':t' => $tid, ':v' => $vid, ':c' => $clinicId]);
            $pdo->prepare('UPDATE diet_templates SET use_count = use_count + 1 WHERE id = :id')
                ->execute([':id' => $tid]);
        } catch (\Throwable $e) {
            // template_id column not present yet — ignore.
        }

        AuditService::log($request, 'UPDATE', 'diet_plans', $vid);
        return Response::json(['ok' => true, 'template_id' => $tid]);
    }

    public function delete(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('diet')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($user['id'] ?? 0);

        // Only the clinic's own templates can be deactivated, never system ones.
        Database::connection()->prepare(
            'UPDATE diet_templates SET is_active = 0
              WHERE id = :id AND clinic_id = :c AND (doctor_id IS NULL OR doctor_id = :d)'
        )->execute([':id' => (int) $id, ':c' => $clinicId, ':d' => $doctorId]);

        return Response::json(['ok' => true]);
    }
}
