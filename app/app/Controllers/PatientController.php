<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\OnboardingService;
use App\Services\InvoiceService;
use App\Services\GdprService;
use App\Services\PatientImmunizationService;
use App\Services\PatientService;
use App\Support\Layout;
use App\Support\PediatricVaccineSchedule;
use App\Support\View;

final class PatientController
{
    public function index(Request $request): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $sort = $request->query['sort'] ?? 'name';
        $dir = $request->query['dir'] ?? 'asc';

        $filters = [
            'q' => $request->query['q'] ?? '',
            'gender' => $request->query['gender'] ?? '',
            'blood_group' => $request->query['blood'] ?? '',
            'veg_type' => $request->query['veg'] ?? '',
            'source' => $request->query['source'] ?? '',
            'referred_by' => $request->query['referred_by'] ?? '',
            'last_visit' => $request->query['last_visit'] ?? '',
        ];

        $result = PatientService::search($clinicId, $filters, $page, $sort, $dir);

        return Response::html(Layout::page('patients/index', [
            'patients' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
        ], 'Patients'));
    }

    public function create(Request $request): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        return Response::html(Layout::page('patients/wizard', $this->wizardData(null), 'New patient'));
    }

    public function store(Request $request): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $payload = $this->payloadFromRequest($request);
        $force = !empty($request->post['force_duplicate']);

        $existing = PatientService::findByPhone($clinicId, $payload['phone'] ?? '');
        if ($existing !== null && !$force) {
            return Response::html(Layout::page('patients/wizard', array_merge($this->wizardData($payload), [
                'duplicate' => $existing,
                'error' => 'A patient with this phone number already exists.',
            ]), 'New patient'), 409);
        }

        try {
            $patient = PatientService::create($clinicId, $payload, $_FILES['photo'] ?? null);
            AuditService::log($request, 'INSERT', 'patients', (int) $patient['id']);

            return Response::redirect('/patients/' . $patient['id'] . '?created=1');
        } catch (\Throwable $e) {
            return Response::html(Layout::page('patients/wizard', array_merge($this->wizardData($payload), [
                'error' => 'Could not save patient: ' . $e->getMessage(),
            ]), 'New patient'), 500);
        }
    }

    public function show(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $patient = PatientService::find($clinicId, (int) $id);
        if ($patient === null) {
            return Response::html('Patient not found', 404);
        }

        $clinic = RequestContext::clinic() ?? [];
        $showImmunizations = PediatricVaccineSchedule::shouldManageImmunizations(
            (string) ($clinic['specialty'] ?? ''),
            null,
            PediatricVaccineSchedule::patientAgeYears($patient),
        );

        return Response::html(Layout::page('patients/show', [
            'patient' => $patient,
            'allergies' => PatientService::decodeTags($patient['allergies'] ?? null),
            'chronic' => PatientService::decodeTags($patient['chronic_conditions'] ?? null),
            'specialtyData' => json_decode($patient['specialty_data'] ?? '{}', true) ?: [],
            'visits' => PatientService::visits($clinicId, (int) $id),
            'vitals' => PatientService::vitals($clinicId, (int) $id),
            'prescriptions' => PatientService::prescriptions($clinicId, (int) $id),
            'invoices' => PatientService::invoices($clinicId, (int) $id),
            'documents' => PatientService::documents($clinicId, (int) $id),
            'hasVitals' => ModuleGate::check('vitals'),
            'showImmunizations' => $showImmunizations,
            'created' => $request->query['created'] ?? null,
        ], $patient['name']));
    }

    public function edit(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $patient = PatientService::find($clinicId, (int) $id);
        if ($patient === null) {
            return Response::html('Patient not found', 404);
        }

        $payload = $this->patientToPayload($patient);

        // array_merge (not +) so editId/patient OVERRIDE the defaults from
        // wizardData(); with + the existing 'editId' => null would win and the
        // wizard would wrongly start at the phone-lookup step.
        return Response::html(Layout::page('patients/wizard', array_merge($this->wizardData($payload), [
            'patient' => $patient,
            'editId' => (int) $id,
        ]), 'Edit patient'));
    }

    public function update(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $patientId = (int) $id;
        $payload = $this->payloadFromRequest($request);

        // The inline "Edit patient" panel (visit screen, patient header) posts a
        // return_to so the doctor lands back where they were editing. Only a
        // local path is honoured — never an absolute/off-site URL.
        $returnTo = (string) ($request->post['return_to'] ?? '');
        $returnTo = (str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//')) ? $returnTo : '';

        try {
            PatientService::update($clinicId, $patientId, $payload, $_FILES['photo'] ?? null);
            AuditService::log($request, 'UPDATE', 'patients', $patientId);

            if ($returnTo !== '') {
                return Response::redirect($this->withFlag($returnTo, 'patient_updated=1'));
            }

            return Response::redirect('/patients/' . $patientId . '?updated=1');
        } catch (\Throwable $e) {
            if ($returnTo !== '') {
                return Response::redirect($this->withFlag($returnTo, 'patient_error=' . urlencode($e->getMessage())));
            }
            return Response::html(Layout::page('patients/wizard', array_merge($this->wizardData($payload), [
                'patient' => PatientService::find($clinicId, $patientId),
                'editId' => $patientId,
                'error' => $e->getMessage(),
            ]), 'Edit patient'), 500);
        }
    }

    public function recordAdvance(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $amount = (float) ($request->post['amount'] ?? 0);
        $method = $request->post['method'] ?? 'cash';

        try {
            InvoiceService::recordAdvance($clinicId, (int) $id, $amount, $method);
            AuditService::log($request, 'UPDATE', 'patients', (int) $id);

            return Response::redirect('/patients/' . $id . '?tab=invoices&advance=1');
        } catch (\Throwable $e) {
            return Response::redirect('/patients/' . $id . '?tab=invoices&error=' . urlencode($e->getMessage()));
        }
    }

    public function searchApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $filters = [
            'q' => $request->query['q'] ?? '',
            'gender' => $request->query['gender'] ?? '',
            'blood_group' => $request->query['blood'] ?? '',
            'veg_type' => $request->query['veg'] ?? '',
            'source' => $request->query['source'] ?? '',
            'referred_by' => $request->query['referred_by'] ?? '',
            'last_visit' => $request->query['last_visit'] ?? '',
        ];
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $sort = $request->query['sort'] ?? 'name';
        $dir = $request->query['dir'] ?? 'asc';

        $result = PatientService::search($clinicId, $filters, $page, $sort, $dir);

        // Whitelist columns exposed to quick-search / patient picker consumers.
        $result['rows'] = array_map(static fn (array $p): array => [
            'id' => (int) $p['id'],
            'uhid' => $p['uhid'] ?? '',
            'name' => $p['name'] ?? '',
            'phone' => $p['phone'] ?? '',
            'gender' => $p['gender'] ?? null,
            'dob' => $p['dob'] ?? null,
            'last_visit' => $p['last_visit'] ?? null,
        ], $result['rows']);

        return Response::json($result);
    }

    public function checkPhoneApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId  = (int) RequestContext::clinicId();
        $phone     = (string) ($request->query['phone'] ?? '');
        $excludeId = (int) ($request->query['exclude_id'] ?? 0);

        // DUPLICATE CHECK ONLY — scoped to THIS clinic's own records.
        //
        // We deliberately do NOT read or pre-fill from frontend patient
        // identities (patient_identities) here. A clinic's chart and the
        // platform directory carry different consent bases, so mixing them
        // into the doctor's add-patient form blurs that legal boundary. The
        // clinic enters its patient's data with its own consent.
        $existing = PatientService::findByPhone($clinicId, $phone);

        // When editing, finding the very chart being edited is not a duplicate.
        if ($existing !== null
            && $excludeId > 0
            && (int) ($existing['id'] ?? 0) === $excludeId
        ) {
            $existing = null;
        }

        if ($existing !== null) {
            return Response::json([
                'status'  => 'existing_chart',
                'exists'  => true,
                'patient' => [
                    'id'   => $existing['id'],
                    'name' => $existing['name'],
                    'uhid' => $existing['uhid'],
                ],
            ]);
        }

        return Response::json([
            'status'  => 'unknown',
            'exists'  => false,
            'patient' => null,
        ]);
    }

    public function immunizationsApi(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $patientId = (int) $id;
        $patient = PatientService::find($clinicId, $patientId);
        if ($patient === null) {
            return Response::json(['error' => 'Patient not found'], 404);
        }

        if ($request->method === 'POST') {
            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
            $rows = PatientImmunizationService::saveBatch(
                $clinicId,
                $patientId,
                is_array($body['items'] ?? null) ? $body['items'] : [],
            );
            AuditService::log($request, 'UPDATE', 'patient_immunizations', $patientId);

            return Response::json(['ok' => true, 'items' => $rows]);
        }

        return Response::json([
            'ok' => true,
            'items' => PatientImmunizationService::forPatient($clinicId, $patientId),
        ]);
    }

    /** @return array<string, mixed> */
    private function wizardData(?array $payload): array
    {
        $clinic = RequestContext::clinic();
        $specialty = $clinic['specialty'] ?? 'gp';
        $config = OnboardingService::specialtyConfig((int) ($clinic['id'] ?? 0)) ?? [];

        return [
            'payload' => $payload ?? [],
            'specialty' => $specialty,
            'specialties' => \App\Support\SpecialtyCatalog::all(),
            'duplicate' => null,
            'error' => null,
            'editId' => null,
            'patient' => null,
        ];
    }

    /** @return array<string, mixed> */
    /** Append a query flag to a local path, dropping any previous patient_* flag. */
    private function withFlag(string $path, string $flag): string
    {
        [$base, $query] = array_pad(explode('?', $path, 2), 2, '');
        parse_str($query, $params);
        unset($params['patient_updated'], $params['patient_error']);
        $params = array_merge($params, [strstr($flag, '=', true) => substr(strstr($flag, '='), 1)]);

        return $base . '?' . http_build_query($params);
    }

    private function payloadFromRequest(Request $request): array
    {
        $specialtyData = [];
        foreach ($request->post as $key => $value) {
            if (str_starts_with($key, 'sp_')) {
                $specialtyData[substr($key, 3)] = $value;
            }
        }

        return array_merge($request->post, [
            'specialty_data' => $specialtyData,
            'allergies' => $request->post['allergies'] ?? '',
            'chronic_conditions' => $request->post['chronic_conditions'] ?? '',
        ]);
    }

    public function exportGdpr(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $user = RequestContext::user() ?? [];
        if (empty($user['is_owner']) && ($user['role'] ?? '') !== 'admin') {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $clinicId = (int) RequestContext::clinicId();
        $zip = GdprService::exportPatientZip($clinicId, (int) $id);
        if ($zip === null) {
            return Response::html('Export failed', 404);
        }

        AuditService::log($request, 'INSERT', 'gdpr_export', (int) $id);

        return Response::download($zip, 'patient-' . $id . '-gdpr.zip');
    }

    public function anonymizeGdpr(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $user = RequestContext::user() ?? [];
        if (empty($user['is_owner']) && ($user['role'] ?? '') !== 'admin') {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $clinicId = (int) RequestContext::clinicId();
        if (!GdprService::anonymizePatient($clinicId, (int) $id)) {
            return Response::redirect('/patients?error=anonymize_failed');
        }

        AuditService::log($request, 'UPDATE', 'patients', (int) $id);

        return Response::redirect('/patients?message=patient_anonymized');
    }

    /** @param array<string, mixed> $patient @return array<string, mixed> */
    private function patientToPayload(array $patient): array
    {
        $spec = json_decode($patient['specialty_data'] ?? '{}', true) ?: [];
        $med = $spec['medical_history'] ?? [];

        return array_merge($patient, [
            'allergies' => implode(', ', PatientService::decodeTags($patient['allergies'] ?? null)),
            'chronic_conditions' => implode(', ', PatientService::decodeTags($patient['chronic_conditions'] ?? null)),
            'surgeries' => $med['surgeries'] ?? '',
            'family_history' => $med['family_history'] ?? '',
            'specialty_data' => $spec,
        ]);
    }
}
