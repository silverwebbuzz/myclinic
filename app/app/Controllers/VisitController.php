<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\ConsentService;
use App\Services\ConsentTemplateService;
use App\Services\DietService;
use App\Services\DischargeService;
use App\Services\PatientPhotoService;
use App\Services\DrugService;
use App\Services\LabCatalogService;
use App\Services\LabOrderService;
use App\Services\Icd10Service;
use App\Services\PatientService;
use App\Services\PrescriptionService;
use App\Services\RemedyService;
use App\Services\VitalsService;
use App\Services\VisitService;
use App\Support\Layout;
use App\Support\SpecialtyAdapter;
use App\Support\View;
use App\Support\VisitView;

final class VisitController
{
    public function index(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();

        return Response::html(Layout::page('visits/index', [
            'visits' => VisitService::listRecent($clinicId),
        ], 'Visits'));
    }

    public function start(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();

        try {
            if (!empty($request->query['appointment_id'])) {
                $visit = VisitService::startFromAppointment($clinicId, (int) $request->query['appointment_id']);
            } elseif (!empty($request->query['patient_id'])) {
                $visit = VisitService::startForPatient($clinicId, (int) $request->query['patient_id']);
            } else {
                return Response::html('patient_id or appointment_id required', 400);
            }

            AuditService::log($request, 'INSERT', 'visits', (int) $visit['id']);

            return Response::redirect('/visits/' . $visit['id']);
        } catch (\Throwable $e) {
            return Response::html($e->getMessage(), 422);
        }
    }

    public function show(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visit = VisitService::findDetailed($clinicId, (int) $id);
        if ($visit === null) {
            return Response::html('Visit not found', 404);
        }

        $patient = PatientService::find($clinicId, (int) $visit['patient_id']);
        if ($patient === null) {
            return Response::html('Patient not found', 404);
        }

        $vitals = VitalsService::forVisit($clinicId, (int) $id);
        $prescriptions = PrescriptionService::forVisit($clinicId, (int) $id);
        $allergies = PatientService::decodeTags($patient['allergies'] ?? null);

        $user = RequestContext::user();
        $clinic = RequestContext::clinic() ?? [];
        $visibleModules = VisitView::visibleModules($clinicId, (string) ($clinic['specialty'] ?? ''));

        $viewData = [
            'visit' => $visit,
            'patient' => $patient,
            'canUnlock' => !empty($user['is_owner']) || ($user['role'] ?? '') === 'admin',
            'vitals' => $vitals ?? [],
            'prescriptions' => $prescriptions,
            'allergies' => $allergies,
            'recentVisits' => VisitService::recentForPatient($clinicId, (int) $patient['id'], 5, (int) $id),
            'vitalsFields' => SpecialtyAdapter::vitalsFields(),
            'casePartial' => SpecialtyAdapter::caseTakingPartial(),
            'rxMode' => SpecialtyAdapter::prescriptionMode(),
            'useHomeo' => SpecialtyAdapter::usesHomeopathicRx(),
            'editable' => VisitService::isEditable($visit),
            'vitalsWarnings' => $vitals ? VitalsService::rangeWarnings($vitals) : [],
            'chartSeries' => VitalsService::chartSeries($clinicId, (int) $patient['id']),
            'completed' => $request->query['completed'] ?? null,
            'hasLab' => ModuleGate::check('lab'),
            'hasConsent' => ModuleGate::check('consent'),
            'hasDischarge' => ModuleGate::check('discharge'),
            'labOrders' => ModuleGate::check('lab') ? LabOrderService::forVisit($clinicId, (int) $id) : [],
            'labTests' => ModuleGate::check('lab') ? LabCatalogService::listForClinic($clinicId) : [],
            'consent' => ModuleGate::check('consent') ? ConsentService::forVisit($clinicId, (int) $id) : null,
            'consentTemplates' => ModuleGate::check('consent') ? ConsentTemplateService::list($clinicId) : [],
            'discharge' => ModuleGate::check('discharge') ? DischargeService::forVisit($clinicId, (int) $id) : null,
            'hasDiet' => ModuleGate::check('diet'),
            'hasPhotos' => ModuleGate::check('before_after'),
            'dietPlan' => ModuleGate::check('diet') ? DietService::forVisit($clinicId, (int) $id) : null,
            'visitPhotos' => ModuleGate::check('before_after') ? PatientPhotoService::forVisit($clinicId, (int) $id) : [],
            'defaultDietWeek' => DietService::defaultWeekPlan(),
            'visibleModules' => $visibleModules,
            'clinic' => $clinic,
        ];

        // Single-screen consultation layout (the only visit screen).
        $viewData['visitSymptoms'] = self::fetchVisitSymptoms($clinicId, (int) $id);
        $viewData['followUpReasons'] = self::fetchFollowUpReasons($clinicId);
        $viewData['pendingFollowUp'] = self::fetchPendingFollowUp($clinicId, (int) ($visit['patient_id'] ?? 0));
        $viewData['voiceLang'] = self::fetchVoiceLang($clinicId);

        return Response::html(Layout::page('visits/show_v2', $viewData, 'Consultation'));
    }

    public function saveDiet(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('diet')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visit = VisitService::find($clinicId, (int) $id);
        if ($visit === null) {
            return Response::html('Visit not found', 404);
        }

        DietService::save($clinicId, (int) $id, (int) $visit['patient_id'], $request->post);

        return Response::redirect('/visits/' . $id . '?diet_saved=1');
    }

    public function shareDiet(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('diet')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $plan = DietService::forVisit($clinicId, (int) $id);
        if ($plan === null) {
            return Response::redirect('/visits/' . $id . '?diet_error=1');
        }

        DietService::share($clinicId, (int) $plan['id']);

        return Response::redirect('/visits/' . $id . '?diet_shared=1');
    }

    public function uploadPhoto(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('before_after')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visit = VisitService::find($clinicId, (int) $id);
        if ($visit === null) {
            return Response::html('Not found', 404);
        }

        $file = $_FILES['photo'] ?? null;
        if (!is_array($file)) {
            return Response::redirect('/visits/' . $id . '?photo_error=1');
        }

        PatientPhotoService::upload(
            $clinicId,
            (int) $visit['patient_id'],
            (int) $id,
            $file,
            $request->post['type'] ?? 'progress',
            $request->post['condition_label'] ?? null,
            !empty($request->post['is_public']),
        );

        return Response::redirect('/visits/' . $id . '?photo_uploaded=1');
    }

    public function signConsent(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }
        if ($denied = ModuleGate::require('consent')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visit = VisitService::find($clinicId, (int) $id);
        if ($visit === null) {
            return Response::html('Visit not found', 404);
        }

        $sig = (string) ($request->post['signature'] ?? '');
        if ($sig === '') {
            return Response::redirect('/visits/' . $id . '?consent_error=1');
        }

        ConsentService::sign($clinicId, (int) $id, (int) $visit['patient_id'], $request->post, $sig);
        AuditService::log($request, 'INSERT', 'consent_forms', (int) $id);

        return Response::redirect('/visits/' . $id . '?consent_signed=1');
    }

    public function saveDischarge(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }
        if ($denied = ModuleGate::require('discharge')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visit = VisitService::find($clinicId, (int) $id);
        if ($visit === null) {
            return Response::html('Visit not found', 404);
        }

        DischargeService::saveDraft($clinicId, (int) $id, (int) $visit['patient_id'], $request->post);

        return Response::redirect('/visits/' . $id . '?discharge_saved=1');
    }

    public function finalizeDischarge(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }
        if ($denied = ModuleGate::require('discharge')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        DischargeService::finalize($clinicId, (int) $id, $request->post['signature'] ?? null);
        AuditService::log($request, 'UPDATE', 'discharge_summaries', (int) $id);

        return Response::redirect('/visits/' . $id . '?discharge_finalized=1');
    }

    public function complete(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        VisitService::complete($clinicId, (int) $id);
        AuditService::log($request, 'UPDATE', 'visits', (int) $id);

        $visit = VisitService::find($clinicId, (int) $id);

        return Response::redirect('/patients/' . ($visit['patient_id'] ?? '') . '?tab=visits&visit_completed=1');
    }

    public function unlock(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        VisitService::unlock($clinicId, (int) $id);
        AuditService::log($request, 'UPDATE', 'visits', (int) $id);

        return Response::redirect('/visits/' . $id . '?unlocked=1');
    }

    public function autosaveApi(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $payload = json_decode($request->rawBody ?? '{}', true);
        if (!is_array($payload)) {
            return Response::json(['error' => 'Invalid JSON'], 400);
        }

        try {
            $visit = VisitService::autosave($clinicId, (int) $id, $payload);
            $vitals = VitalsService::forVisit($clinicId, (int) $id);

            return Response::json([
                'ok' => true,
                'saved_at' => date('c'),
                'warnings' => $vitals ? VitalsService::rangeWarnings($vitals) : [],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function tabApi(Request $request, string $id, string $tab): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visit = VisitService::findDetailed($clinicId, (int) $id);
        if ($visit === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $patient = PatientService::find($clinicId, (int) $visit['patient_id']);
        $view = 'visits/tabs/' . preg_replace('/[^a-z_]/', '', $tab);
        if (!is_file(dirname(__DIR__, 2) . '/views/' . $view . '.php')) {
            return Response::json(['html' => '<p class="text-sm text-slate-500">Tab not found.</p>']);
        }

        return Response::json([
            'html' => View::render($view, [
                'visit' => $visit,
                'patient' => $patient,
                'editable' => VisitService::isEditable($visit),
            ]),
        ]);
    }

    public function drugsApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }

        return Response::json(['drugs' => DrugService::search($request->query['q'] ?? '')]);
    }

    public function remediesApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('prescription')) {
            return $denied;
        }

        return Response::json(['remedies' => RemedyService::search($request->query['q'] ?? '')]);
    }

    public function icd10Api(Request $request): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        return Response::json(['codes' => Icd10Service::search($request->query['q'] ?? '')]);
    }

    public function vitalsChartApi(Request $request, string $patientId): Response
    {
        if ($denied = ModuleGate::require('vitals')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();

        return Response::json(VitalsService::chartSeries($clinicId, (int) $patientId));
    }

    /**
     * GET  /api/visits/{visitId}/last-visit
     *   Returns the patient's most recent completed visit (excluding the
     *   current one) as JSON so the front-end can preview a "Same as last
     *   visit" clone before applying.
     *
     * POST /api/visits/{visitId}/clone-last
     *   Copies symptoms/diagnosis/prescriptions/notes from the patient's
     *   last completed visit into the current draft. Doctor edits before save.
     */
    public function cloneLastVisit(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visitId = (int) $id;

        $current = VisitService::find($clinicId, $visitId);
        if ($current === null) {
            return Response::json(['error' => 'Visit not found'], 404);
        }
        if (!VisitService::isEditable($current)) {
            return Response::json(['error' => 'Visit is read-only'], 422);
        }

        // Patient's most recent completed visit (excluding this one).
        $recent = VisitService::recentForPatient(
            $clinicId,
            (int) $current['patient_id'],
            1,
            $visitId
        );
        $last = $recent[0] ?? null;

        if ($last === null) {
            return Response::json(['error' => 'No previous visit to clone'], 404);
        }

        // Pull last visit's prescriptions + symptoms (Phase 3).
        $prescriptions = PrescriptionService::forVisit($clinicId, (int) $last['id']);
        $symptoms = self::fetchVisitSymptoms($clinicId, (int) $last['id']);

        // GET — preview only, don't persist
        if ($request->method !== 'POST') {
            return Response::json([
                'last_visit' => [
                    'id' => (int) $last['id'],
                    'visited_at' => $last['visited_at'] ?? null,
                    'chief_complaint' => $last['chief_complaint'] ?? '',
                    'diagnosis' => $last['diagnosis'] ?? '',
                    'icd10_code' => $last['icd10_code'] ?? '',
                    'clinical_notes' => $last['clinical_notes'] ?? '',
                    'prescriptions' => $prescriptions,
                    'symptoms' => $symptoms,
                ],
            ]);
        }

        // POST — apply to the current draft visit.
        $payload = [
            'chief_complaint' => $last['chief_complaint'] ?? null,
            'diagnosis' => $last['diagnosis'] ?? null,
            'icd10_code' => $last['icd10_code'] ?? null,
            'clinical_notes' => $last['clinical_notes'] ?? null,
            'prescriptions' => $prescriptions,
        ];

        try {
            VisitService::autosave($clinicId, $visitId, $payload);
            // Replicate symptoms separately — they live in visit_symptoms,
            // not in the autosave payload. Wrapped in try so a missing
            // table (pre-Phase-3 migration) never breaks the clone.
            self::cloneSymptomsBetween($clinicId, (int) $last['id'], $visitId);
            AuditService::log($request, 'UPDATE', 'visits', $visitId);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }

        return Response::json([
            'ok' => true,
            'cloned_from' => (int) $last['id'],
            'visited_at' => $last['visited_at'] ?? null,
            'symptom_count' => count($symptoms),
            'prescription_count' => count($prescriptions),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function fetchVisitSymptoms(int $clinicId, int $visitId): array
    {
        try {
            $stmt = \App\Core\Database::connection()->prepare(
                'SELECT id, master_id, label, source, severity, duration, sort_order
                   FROM visit_symptoms
                  WHERE visit_id = :v AND clinic_id = :c
                  ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([':v' => $visitId, ':c' => $clinicId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // visit_symptoms table doesn't exist yet (pre-Phase-3 migration).
            return [];
        }
    }

    /** @return list<array{reason_key: string, label: string}> */
    private static function fetchFollowUpReasons(int $clinicId): array
    {
        try {
            return \App\Services\FollowUpService::reasons($clinicId);
        } catch (\Throwable $e) {
            return []; // follow_up_reasons table doesn't exist yet.
        }
    }

    private static function fetchPendingFollowUp(int $clinicId, int $patientId): ?array
    {
        if ($patientId < 1) return null;
        try {
            return \App\Services\FollowUpService::pendingForPatient($clinicId, $patientId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function fetchVoiceLang(int $clinicId): string
    {
        try {
            $stmt = \App\Core\Database::connection()->prepare(
                'SELECT voice_lang FROM clinic_settings WHERE clinic_id = :c LIMIT 1'
            );
            $stmt->execute([':c' => $clinicId]);
            $lang = $stmt->fetchColumn();
            return $lang ?: 'en-IN';
        } catch (\Throwable $e) {
            return 'en-IN';
        }
    }

    private static function cloneSymptomsBetween(int $clinicId, int $sourceVisitId, int $targetVisitId): void
    {
        try {
            $pdo = \App\Core\Database::connection();
            $pdo->prepare('DELETE FROM visit_symptoms WHERE visit_id = :v')
                ->execute([':v' => $targetVisitId]);
            $pdo->prepare(
                'INSERT INTO visit_symptoms
                    (visit_id, clinic_id, master_id, label, source, severity, duration, sort_order, created_at)
                 SELECT :tv, clinic_id, master_id, label, source, severity, duration, sort_order, NOW()
                   FROM visit_symptoms
                  WHERE visit_id = :sv AND clinic_id = :c'
            )->execute([
                ':tv' => $targetVisitId,
                ':sv' => $sourceVisitId,
                ':c' => $clinicId,
            ]);
        } catch (\Throwable $e) {
            // Best-effort — old visits or pre-Phase-3 DB. Don't fail the clone.
        }
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('emr')) {
            return Response::html(Layout::page('errors/module', [
                'module' => 'emr',
                'label' => 'Visits / EMR',
            ], 'Module inactive'), 402);
        }

        return null;
    }
}
