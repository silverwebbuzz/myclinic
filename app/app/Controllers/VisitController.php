<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\ClinicSettingsService;
use App\Services\DietService;
use App\Services\DrugService;
use App\Services\Icd10Service;
use App\Core\QueryBuilder;
use App\Services\PatientImmunizationService;
use App\Services\PatientPrescriptionShareService;
use App\Services\PatientService;
use App\Services\PrescriptionService;
use App\Services\RemedyService;
use App\Services\VitalsService;
use App\Services\VisitService;
use App\Support\Layout;
use App\Support\PediatricVaccineSchedule;
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
        $editable = VisitService::isEditable($visit);
        $vitalsData = is_array($vitals)
            ? self::mergeFormDefaults(self::defaultVitalsData(), $vitals)
            : self::defaultVitalsData();

        // Vitals + Case taking must always be visible while creating/editing
        // an editable visit, even if clinic visible_modules was customized.
        if ($editable && !in_array('vitals', $visibleModules, true)) {
            $visibleModules[] = 'vitals';
        } elseif ($vitals !== null && !in_array('vitals', $visibleModules, true)) {
            $visibleModules[] = 'vitals';
        }
        if ($editable && !in_array('case_specialty', $visibleModules, true)) {
            $visibleModules[] = 'case_specialty';
        } elseif (self::hasMeaningfulData($visit['specialty_data']['case_taking'] ?? null)
            && !in_array('case_specialty', $visibleModules, true)) {
            $visibleModules[] = 'case_specialty';
        }

        // Case taking carries forward: a follow-up opens with the history the
        // doctor already recorded, editable as usual.
        $carry = VisitService::caseTakingWithCarryForward($clinicId, $visit);
        $caseTaking = $carry['case'];
        $caseCarriedFrom = $carry['carried_from'];
        $visit['specialty_data'] = array_merge($visit['specialty_data'] ?? [], ['case_taking' => $caseTaking]);

        $chargeData = self::chargesForVisit($clinicId, (int) $id, $editable);
        $visitInvoice = \App\Services\InvoiceService::findForVisit($clinicId, (int) $id);

        $doctorRow = \App\Core\QueryBuilder::table('users')
            ->where('id', '=', (int) ($visit['doctor_id'] ?? 0))
            ->first();
        $patientAgeYears = PediatricVaccineSchedule::patientAgeYears($patient);
        $patient['age'] = $patientAgeYears;
        $showImmunizations = PediatricVaccineSchedule::shouldManageImmunizations(
            (string) ($clinic['specialty'] ?? ''),
            isset($doctorRow['specialization']) ? (string) $doctorRow['specialization'] : null,
            $patientAgeYears,
        );
        $immunizationSummary = $showImmunizations
            ? PatientImmunizationService::visitSummary($clinicId, (int) $patient['id'])
            : ['overdue' => [], 'due_today' => [], 'due_soon' => [], 'upcoming' => []];
        $immunizationsGiven = $showImmunizations
            ? PatientImmunizationService::givenGroupedByVisit($clinicId, (int) $patient['id'], (int) $id)
            : [];

        $viewData = [
            'visit' => $visit,
            'patient' => $patient,
            'canUnlock' => self::canUnlockCompletedVisit($user),
            'vitals' => $vitalsData,
            'prescriptions' => $prescriptions,
            'allergies' => $allergies,
            'recentVisits' => VisitService::recentForPatient($clinicId, (int) $patient['id'], 5, (int) $id),
            'vitalsFields' => SpecialtyAdapter::vitalsFields(),
            'casePartial' => SpecialtyAdapter::caseTakingPartial(),
            'caseTaking' => $caseTaking,
            'caseCarriedFrom' => $caseCarriedFrom,
            'rxMode' => SpecialtyAdapter::prescriptionMode(),
            'useHomeo' => SpecialtyAdapter::usesHomeopathicRx(),
            'editable' => $editable,
            'vitalsWarnings' => $vitals ? VitalsService::rangeWarnings($vitals) : [],
            'chartSeries' => VitalsService::chartSeries($clinicId, (int) $patient['id']),
            'completed' => $request->query['completed'] ?? null,
            'hasDiet' => ModuleGate::check('diet'),
            'dietPlan' => ModuleGate::check('diet') ? DietService::forVisit($clinicId, (int) $id) : null,
            'defaultDietWeek' => DietService::defaultWeekPlan(),
            'visibleModules' => $visibleModules,
            'clinic' => $clinic,
            'charges' => $chargeData['items'],
            'chargesPrefilled' => $chargeData['prefilled'],
            'visitInvoice' => $visitInvoice,
            'payment' => self::paymentStateForVisit($clinicId, $visitInvoice, $chargeData['items']),
            'chargeSuggestions' => \App\Services\InvoiceService::chargeSuggestions($clinicId),
            'chronic' => PatientService::decodeTags($patient['chronic_conditions'] ?? null),
            'historySummary' => self::historySummaryForPatient($clinicId, $patient, (int) $id),
            'showImmunizations' => $showImmunizations,
            'immunizationSummary' => $immunizationSummary,
            'immunizationsGiven' => $immunizationsGiven,
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

    /**
     * POST /visits/{id}/send-rx — WhatsApp the prescription PDF link to the
     * patient. The same rx_delivery notification the app queues automatically
     * when a visit completes, on demand: for a re-send, for a patient who
     * changed their number, or when auto-delivery is switched off.
     */
    public function sendRxWhatsApp(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visitId = (int) $id;
        $back = '/visits/' . $visitId;

        $visit = VisitService::findDetailed($clinicId, $visitId);
        if ($visit === null) {
            return Response::redirect($back . '?rx_error=' . urlencode('Visit not found.'));
        }

        $patient = PatientService::find($clinicId, (int) $visit['patient_id']);
        $clinic = RequestContext::clinic() ?? [];
        $phone = preg_replace('/[^0-9]/', '', (string) ($patient['phone'] ?? '')) ?? '';
        if ($patient === null || strlen($phone) < 7) {
            return Response::redirect($back . '?rx_error=' . urlencode('This patient has no WhatsApp number on file.'));
        }

        $prescriptions = PrescriptionService::forVisit($clinicId, $visitId);
        if ($prescriptions === []) {
            return Response::redirect($back . '?rx_error=' . urlencode('No medicines on this visit yet.'));
        }

        // Regenerate rather than trust a stale file — the doctor may have
        // edited the prescription since the PDF was written.
        try {
            $visit['diagnosis'] = $visit['diagnosis'] ?? '';
            $rxPath = \App\Services\RxPdfService::generate($visit, $patient, $clinic, $prescriptions);
            QueryBuilder::table('visits')
                ->forClinic($clinicId)
                ->where('id', '=', $visitId)
                ->update(['rx_pdf_path' => $rxPath]);
        } catch (\Throwable $e) {
            return Response::redirect($back . '?rx_error=' . urlencode('Could not build the prescription PDF.'));
        }

        $rxUrl = $rxPath;
        if ($rxUrl !== '' && str_starts_with($rxUrl, '/')) {
            $rxUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . $rxUrl;
        }
        $payload = [
            'patient_name' => $patient['name'] ?? '',
            'clinic_name' => $clinic['name'] ?? '',
            'rx_url' => $rxUrl,
        ];

        \App\Services\NotificationService::queueWhatsApp(
            $clinicId,
            (int) $patient['id'],
            (string) $patient['phone'],
            'rx_delivery',
            $payload,
            date('Y-m-d H:i:s'),
        );
        if (!empty($patient['email'])) {
            \App\Services\NotificationService::queueEmail(
                $clinicId,
                (int) $patient['id'],
                (string) $patient['email'],
                'rx_delivery',
                $payload,
                date('Y-m-d H:i:s'),
            );
        }

        AuditService::log($request, 'INSERT', 'notifications', $visitId);

        return Response::redirect($back . '?rx_sent=1');
    }

    public function complete(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visitId = (int) $id;

        // Belt-and-suspenders: persist the form snapshot posted with the
        // completion request so medicines are saved even if the prior fetch
        // autosave was skipped or failed silently.
        $rawPayload = $request->post['_visit_payload'] ?? null;
        if (is_string($rawPayload) && $rawPayload !== '') {
            $payload = json_decode($rawPayload, true);
            if (is_array($payload)) {
                try {
                    VisitService::autosave($clinicId, $visitId, $payload);
                } catch (\Throwable $e) {
                    return Response::redirect('/visits/' . $visitId . '?complete_save_error=1');
                }
            }
        }

        VisitService::complete($clinicId, $visitId);
        AuditService::log($request, 'UPDATE', 'visits', $visitId);

        // Optionally push the prescription to the patient's eClinicPro panel.
        // Only fires when the doctor ticked the box AND the patient has a panel
        // account (identity_id). Failures here must never block completion.
        if (!empty($request->post['share_to_patient_app'])) {
            try {
                $visit = VisitService::findDetailed($clinicId, $visitId);
                $patient = $visit ? PatientService::find($clinicId, (int) $visit['patient_id']) : null;
                if ($visit && $patient && !empty($patient['identity_id'])) {
                    $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
                    $lines = PrescriptionService::forVisit($clinicId, $visitId);
                    PatientPrescriptionShareService::share($visit, $patient, $clinic, $lines);
                }
            } catch (\Throwable $e) {
                error_log('[visit complete] Rx share failed: ' . $e->getMessage());
            }
        }

        return Response::redirect('/dashboard?visit_completed=1');
    }

    public function unlock(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user();
        if (!self::canUnlockCompletedVisit($user)) {
            return Response::redirect('/visits/' . $id . '?unlock_error=1');
        }

        try {
            VisitService::unlock($clinicId, (int) $id);
            AuditService::log($request, 'UPDATE', 'visits', (int) $id);
        } catch (\RuntimeException $e) {
            return Response::redirect('/visits/' . $id . '?unlock_error=1');
        }

        return Response::redirect('/visits/' . $id . '?unlocked=1');
    }

    public function unlockGet(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user();
        if (!self::canUnlockCompletedVisit($user)) {
            return Response::redirect('/visits/' . $id . '?unlock_error=1');
        }

        try {
            VisitService::unlock($clinicId, (int) $id);
            AuditService::log($request, 'UPDATE', 'visits', (int) $id);
        } catch (\RuntimeException $e) {
            return Response::redirect('/visits/' . $id . '?unlock_error=1');
        }

        return Response::redirect('/visits/' . $id . '?unlocked=1');
    }

    /** @param array<string,mixed> $user */
    private static function canUnlockCompletedVisit(array $user): bool
    {
        if (!empty($user['is_owner'])) {
            return true;
        }

        $role = strtolower(trim((string) ($user['role'] ?? '')));

        return $role === 'admin';
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
            $sync = $visit['_prescription_sync'] ?? null;
            unset($visit['_prescription_sync']);

            return Response::json([
                'ok' => true,
                'saved_at' => date('c'),
                'warnings' => $vitals ? VitalsService::rangeWarnings($vitals) : [],
                'prescriptions_synced' => $sync['synced'] ?? null,
                'prescriptions_skipped' => !empty($sync['skipped']),
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    /** POST /api/v1/visits/{id}/immunizations/given — mark a dose given during this visit. */
    public function markImmunizationGiven(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visitId = (int) $id;
        $visit = VisitService::find($clinicId, $visitId);
        if ($visit === null) {
            return Response::json(['error' => 'Visit not found'], 404);
        }
        if (!VisitService::isEditable($visit)) {
            return Response::json(['error' => 'Visit is read-only'], 422);
        }

        $body = json_decode($request->rawBody ?? '{}', true);
        if (!is_array($body)) {
            return Response::json(['error' => 'Invalid JSON'], 400);
        }

        $immId = (int) ($body['immunization_id'] ?? 0);
        if ($immId <= 0) {
            return Response::json(['error' => 'immunization_id required'], 400);
        }

        $row = PatientImmunizationService::markGiven(
            $clinicId,
            (int) $visit['patient_id'],
            $immId,
            $visitId,
            !empty($body['given_date']) ? (string) $body['given_date'] : null,
            array_key_exists('notes', $body) ? (string) $body['notes'] : null,
        );
        if ($row === null) {
            return Response::json(['error' => 'Immunization not found'], 404);
        }

        AuditService::log($request, 'UPDATE', 'patient_immunizations', $immId);

        return Response::json(['ok' => true, 'item' => $row]);
    }

    /**
     * GET /api/v1/visits/{id}/summary — read-only summary of a past visit
     * (symptoms, diagnosis, prescriptions, notes). Used by the "peek" panel
     * in the current-visit screen. Never modifies anything.
     */
    public function summaryApi(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visit = VisitService::find($clinicId, (int) $id);
        if ($visit === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $symptoms = array_map(
            static fn ($s) => (string) ($s['label'] ?? ''),
            self::fetchVisitSymptoms($clinicId, (int) $id)
        );

        $rx = array_map(static function ($p) {
            $name = $p['drug']['name'] ?? $p['remedy']['name'] ?? $p['drug_name'] ?? '';
            $parts = array_filter([
                $p['frequency_preset'] ?? $p['frequency'] ?? null,
                ($p['duration_days'] ?? null) ? $p['duration_days'] . ' days' : null,
                $p['food_timing'] ?? null,
            ]);
            return [
                'name' => $name,
                'detail' => implode(' · ', $parts),
                'instructions' => $p['instructions'] ?? '',
            ];
        }, PrescriptionService::forVisit($clinicId, (int) $id));

        return Response::json([
            'id' => (int) $visit['id'],
            'visit_number' => (int) ($visit['visit_number'] ?? 0),
            'visited_at' => $visit['visited_at'] ?? null,
            'status' => $visit['status'] ?? '',
            'symptoms' => array_values(array_filter($symptoms)),
            'chief_complaint' => $visit['chief_complaint'] ?? '',
            'diagnosis' => $visit['diagnosis'] ?? '',
            'clinical_notes' => $visit['clinical_notes'] ?? '',
            'follow_up_notes' => $visit['follow_up_notes'] ?? '',
            'prescriptions' => $rx,
        ]);
    }

    /**
     * POST /api/v1/visits/{id}/charges — save the visit's charge line items.
     * Find-or-creates the visit's draft invoice and replaces its items, then
     * recalculates the total. Returns the new total.
     * Body: { items: [{ description, amount, qty? }, ...] }
     */
    public function saveCharges(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $visitId = (int) $id;
        $payload = json_decode($request->rawBody ?? '{}', true);
        $rows = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        // Map the simple {description, amount} rows to invoice_items shape.
        $items = [];
        foreach ($rows as $r) {
            $desc = trim((string) ($r['description'] ?? ''));
            $amount = (float) ($r['amount'] ?? 0);
            if ($desc === '' && $amount <= 0) {
                continue;
            }
            $items[] = [
                'description' => $desc !== '' ? $desc : 'Charge',
                'item_type' => $r['item_type'] ?? 'other',
                'qty' => max(1, (int) ($r['qty'] ?? 1)),
                'unit_price' => $amount,
                'discount' => 0,
            ];
        }

        try {
            $invoiceId = \App\Services\InvoiceService::createDraftFromVisit($clinicId, ['visit_id' => $visitId]);
            if ($invoiceId < 1) {
                return Response::json(['error' => 'Could not create invoice'], 422);
            }
            $invoice = \App\Services\InvoiceService::update($clinicId, $invoiceId, ['items' => $items]);

            // The Payment card posts alongside the charge lines so amount, GST,
            // mode and paid/due are settled in the same round-trip.
            if (is_array($payload['payment'] ?? null)) {
                $invoice = \App\Services\InvoiceService::applyVisitPayment($clinicId, $invoiceId, $payload['payment']);
            }

            $due = \App\Services\InvoiceService::balanceDue($invoice);

            return Response::json([
                'ok' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'] ?? null,
                'invoice_date' => !empty($invoice['created_at'])
                    ? date('d M Y', strtotime((string) $invoice['created_at']))
                    : date('d M Y'),
                'subtotal' => round((float) ($invoice['subtotal'] ?? 0) - (float) ($invoice['discount_amount'] ?? 0), 2),
                'tax_amount' => (float) ($invoice['tax_amount'] ?? 0),
                'total' => (float) ($invoice['total'] ?? 0),
                'due' => $due,
                'status' => $invoice['status'] ?? 'draft',
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

        $clinicId = (int) RequestContext::clinicId();
        $drugs = DrugService::search($request->query['q'] ?? '', 15, $clinicId);

        // Smart defaults: attach the clinic's last-used frequency/duration/dose
        // per drug so picking a medicine pre-fills the empty fields.
        $defaults = DrugService::lastUsedDefaults($clinicId, array_values(array_filter(
            array_map(static fn ($d) => !empty($d['id']) ? (int) $d['id'] : 0, $drugs),
        )));
        foreach ($drugs as &$drug) {
            $drug['defaults'] = !empty($drug['id']) ? ($defaults[(int) $drug['id']] ?? null) : null;
        }
        unset($drug);

        return Response::json(['drugs' => $drugs]);
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

    /** @return array{items: list<array{description: string, amount: float}>, prefilled: bool} */
    /**
     * Right-sidebar "History summary": the standing facts a doctor wants in
     * view during every consultation — chronic conditions, allergies, past
     * surgeries, the medicines from the last visit, and when vitals were last
     * taken. Everything here is read-only; it is edited on the patient record.
     *
     * @param array<string, mixed> $patient
     * @return array<string, mixed>
     */
    private static function historySummaryForPatient(int $clinicId, array $patient, int $currentVisitId): array
    {
        $patientId = (int) ($patient['id'] ?? 0);

        $specialty = $patient['specialty_data'] ?? [];
        if (is_string($specialty)) {
            $specialty = json_decode($specialty, true) ?: [];
        }
        $medicalHistory = is_array($specialty['medical_history'] ?? null) ? $specialty['medical_history'] : [];

        // The last three consultations, newest first. Excludes the visit being
        // written right now — otherwise "last visit" resolves to today's own
        // (still empty) consultation.
        $recent = VisitService::recentForPatient($clinicId, $patientId, 3, $currentVisitId);
        $medications = trim((string) ($recent[0]['medicines_summary'] ?? ''));

        $previousVisits = [];
        foreach ($recent as $v) {
            $previousVisits[] = [
                'id' => (int) ($v['id'] ?? 0),
                'visited_at' => (string) ($v['visited_at'] ?? ''),
                'visit_number' => (int) ($v['visit_number'] ?? 0),
                'complaint' => trim((string) ($v['chief_complaint'] ?? '')),
                'diagnosis' => trim((string) ($v['diagnosis'] ?? '')),
                'notes' => trim((string) ($v['clinical_notes'] ?? '')),
                'medicines' => trim((string) ($v['medicines_summary'] ?? '')),
            ];
        }

        // Newest reading from an EARLIER visit — today's numbers are already on
        // screen in the Vitals card, this block is the "last time" reference.
        $lastVitals = null;
        foreach (array_reverse(PatientService::vitals($clinicId, $patientId, 5)) as $row) {
            if ((int) ($row['visit_id'] ?? 0) === $currentVisitId) {
                continue;
            }
            $lastVitals = $row;
            break;
        }

        return [
            'chronic' => PatientService::decodeTags($patient['chronic_conditions'] ?? null),
            'allergies' => PatientService::decodeTags($patient['allergies'] ?? null),
            'surgeries' => trim((string) ($medicalHistory['surgeries'] ?? '')),
            'family_history' => trim((string) ($medicalHistory['family_history'] ?? '')),
            'medications' => $medications,
            'medications_date' => !empty($recent[0]['visited_at']) ? (string) $recent[0]['visited_at'] : '',
            'last_vitals_at' => $lastVitals['recorded_at'] ?? '',
            'last_vitals' => self::vitalSignPairs($lastVitals),
            // What the patient came in with last time — the doctor reads this
            // before writing today's complaint. Newest first.
            'previous_visits' => $previousVisits,
        ];
    }

    /**
     * The handful of vitals worth showing at a glance, formatted with units.
     *
     * @param array<string, mixed>|null $row
     * @return list<array{label: string, value: string}>
     */
    private static function vitalSignPairs(?array $row): array
    {
        if ($row === null) {
            return [];
        }

        $pairs = [];
        $sys = $row['bp_systolic'] ?? null;
        $dia = $row['bp_diastolic'] ?? null;
        if ($sys !== null && $dia !== null) {
            $pairs[] = ['label' => 'BP', 'value' => (int) $sys . '/' . (int) $dia . ' mmHg'];
        }
        foreach ([
            ['pulse_rate', 'Pulse', ' bpm'],
            ['temperature', 'Temp', ' °F'],
            ['spo2', 'SpO₂', '%'],
            ['blood_sugar', 'Sugar', ' mg/dL'],
            ['weight_kg', 'Weight', ' kg'],
        ] as [$col, $label, $unit]) {
            if (($row[$col] ?? null) === null || $row[$col] === '') {
                continue;
            }
            $value = (string) $row[$col];
            // 98.60 → 98.6, 120.00 → 120; never touch a plain integer (120).
            if (str_contains($value, '.')) {
                $value = rtrim(rtrim($value, '0'), '.');
            }
            $pairs[] = ['label' => $label, 'value' => $value . $unit];
        }

        return $pairs;
    }

    /**
     * Seed for the visit screen's Payment card. Reads back what was already
     * billed (amount, GST, mode, paid/due); for a visit with no invoice yet it
     * falls back to the charge lines the doctor is about to save.
     *
     * @param array<string, mixed>|null $invoice
     * @param list<array{description: string, amount: float}> $chargeItems
     * @return array<string, mixed>
     */
    private static function paymentStateForVisit(int $clinicId, ?array $invoice, array $chargeItems): array
    {
        $config = \App\Services\OnboardingService::specialtyConfig($clinicId) ?? [];
        $clinicTaxPercent = (float) ($config['invoice_tax_percent'] ?? 0) ?: 18.0;

        if ($invoice === null) {
            $amount = 0.0;
            foreach ($chargeItems as $line) {
                $amount += (float) ($line['amount'] ?? 0);
            }

            return [
                'amount' => round($amount, 2),
                'discount' => 0.0,
                'discount_on' => false,
                'gst' => false,
                'tax_percent' => $clinicTaxPercent,
                'type' => 'cash',
                'status' => 'due',
                'paid_amount' => 0.0,
                'due' => 0.0,
                'invoice_status' => null,
            ];
        }

        $taxPercent = (float) ($invoice['tax_percent'] ?? 0);
        $paid = (float) ($invoice['amount_paid'] ?? 0) + (float) ($invoice['advance_paid'] ?? 0);
        $discount = round((float) ($invoice['discount_amount'] ?? 0), 2);

        return [
            'amount' => round((float) ($invoice['subtotal'] ?? 0), 2),
            'discount' => $discount,
            'discount_on' => $discount > 0,
            'gst' => $taxPercent > 0,
            'tax_percent' => $taxPercent > 0 ? $taxPercent : $clinicTaxPercent,
            'type' => in_array($invoice['payment_mode'] ?? '', ['cash', 'online'], true) ? $invoice['payment_mode'] : 'cash',
            'status' => (string) ($invoice['status'] ?? '') === 'paid' ? 'paid' : 'due',
            'paid_amount' => round($paid, 2),
            'due' => \App\Services\InvoiceService::balanceDue($invoice),
            'invoice_status' => $invoice['status'] ?? null,
        ];
    }

    private static function chargesForVisit(int $clinicId, int $visitId, bool $editable): array
    {
        $existing = \App\Services\InvoiceService::chargeLinesForVisit($clinicId, $visitId);
        if ($existing !== []) {
            return ['items' => $existing, 'prefilled' => false];
        }

        if (!$editable) {
            return ['items' => [], 'prefilled' => false];
        }

        $fee = ClinicSettingsService::consultationFeeForClinic($clinicId);
        if ($fee > 0) {
            return [
                'items' => [
                    ['description' => 'Consultation fee', 'amount' => $fee],
                ],
                'prefilled' => true,
            ];
        }

        return ['items' => [], 'prefilled' => false];
    }

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

    /**
     * Fill only missing form keys — never replace saved values with empty defaults.
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $saved
     * @return array<string, mixed>
     */
    private static function mergeFormDefaults(array $defaults, array $saved): array
    {
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $saved)) {
                $saved[$key] = $value;
            }
        }

        return $saved;
    }

    /** @param mixed $value */
    private static function hasMeaningfulData($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasMeaningfulData($item)) {
                    return true;
                }
            }
            return false;
        }

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    /** @return array<string,mixed> */
    private static function defaultVitalsData(): array
    {
        return [
            'bp_systolic' => null,
            'bp_diastolic' => null,
            'blood_sugar' => null,
            'sugar_type' => null,
            'weight_kg' => null,
            'height_cm' => null,
            'temperature' => null,
            'spo2' => null,
            'pulse_rate' => null,
        ];
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
