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
use App\Services\PatientService;
use App\Support\Layout;
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
            return Response::html(Layout::page('patients/wizard', $this->wizardData($payload) + [
                'duplicate' => $existing,
                'error' => 'A patient with this phone number already exists.',
            ], 'New patient'), 409);
        }

        try {
            $patient = PatientService::create($clinicId, $payload, $_FILES['photo'] ?? null);
            AuditService::log($request, 'INSERT', 'patients', (int) $patient['id']);

            return Response::redirect('/patients/' . $patient['id'] . '?created=1');
        } catch (\Throwable $e) {
            return Response::html(Layout::page('patients/wizard', $this->wizardData($payload) + [
                'error' => 'Could not save patient: ' . $e->getMessage(),
            ], 'New patient'), 500);
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

        $tab = $request->query['tab'] ?? 'overview';

        return Response::html(Layout::page('patients/show', [
            'patient' => $patient,
            'tab' => $tab,
            'allergies' => PatientService::decodeTags($patient['allergies'] ?? null),
            'chronic' => PatientService::decodeTags($patient['chronic_conditions'] ?? null),
            'specialtyData' => json_decode($patient['specialty_data'] ?? '{}', true) ?: [],
            'visits' => PatientService::visits($clinicId, (int) $id),
            'vitals' => PatientService::vitals($clinicId, (int) $id),
            'prescriptions' => PatientService::prescriptions($clinicId, (int) $id),
            'invoices' => PatientService::invoices($clinicId, (int) $id),
            'documents' => PatientService::documents($clinicId, (int) $id),
            'hasLab' => ModuleGate::check('lab'),
            'hasRadiology' => ModuleGate::check('radiology'),
            'hasVitals' => ModuleGate::check('vitals'),
            'hasPhotos' => ModuleGate::check('before_after'),
            'photos' => ModuleGate::check('before_after') ? \App\Services\PatientPhotoService::forPatient($clinicId, (int) $id) : [],
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

        return Response::html(Layout::page('patients/wizard', $this->wizardData($payload) + [
            'patient' => $patient,
            'editId' => (int) $id,
        ], 'Edit patient'));
    }

    public function update(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $patientId = (int) $id;
        $payload = $this->payloadFromRequest($request);

        try {
            PatientService::update($clinicId, $patientId, $payload, $_FILES['photo'] ?? null);
            AuditService::log($request, 'UPDATE', 'patients', $patientId);

            return Response::redirect('/patients/' . $patientId . '?updated=1');
        } catch (\Throwable $e) {
            return Response::html(Layout::page('patients/wizard', $this->wizardData($payload) + [
                'patient' => PatientService::find($clinicId, $patientId),
                'editId' => $patientId,
                'error' => $e->getMessage(),
            ], 'Edit patient'), 500);
        }
    }

    public function regenerateQr(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        try {
            PatientService::regenerateQrToken($clinicId, (int) $id);
            AuditService::log($request, 'UPDATE', 'patients', (int) $id);

            return Response::redirect('/patients/' . $id . '?tab=overview&qr=regenerated');
        } catch (\Throwable $e) {
            error_log('[patients/regenerateQr id=' . $id . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

            return Response::redirect('/patients/' . $id . '?tab=overview&error=' . urlencode('Could not regenerate QR: ' . $e->getMessage()));
        }
    }

    public function qrCard(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $patient = PatientService::find($clinicId, (int) $id);
        if ($patient === null) {
            return Response::html('Not found', 404);
        }

        $path = $patient['qr_card_path'] ?? null;
        if ($path === null || !is_file(dirname(__DIR__, 2) . '/public' . $path)) {
            $clinic = RequestContext::clinic();
            if ($clinic !== null) {
                $path = \App\Services\QrCardService::generateForPatient($patient, $clinic);
            }
        }

        if ($path === null) {
            return Response::html('QR card not available', 404);
        }

        return Response::redirect($path);
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

        // Smart lookup — checks this clinic first, then global identity,
        // then other clinics. See PatientService::findOrPreFillByPhone()
        // for the matrix of return shapes.
        $res = PatientService::findOrPreFillByPhone($clinicId, $phone);

        // If we're editing an existing patient and the chart we found IS
        // the one being edited, treat as not-found (no duplicate warning).
        if ($res['status'] === 'existing_chart'
            && $excludeId > 0
            && (int) ($res['patient']['id'] ?? 0) === $excludeId
        ) {
            $res = ['status' => 'unknown'];
        }

        if ($res['status'] === 'existing_chart') {
            $p = $res['patient'];
            return Response::json([
                'status'  => 'existing_chart',
                'exists'  => true,                  // backward-compat with old UI
                'patient' => [
                    'id'   => $p['id'],
                    'name' => $p['name'],
                    'uhid' => $p['uhid'],
                ],
            ]);
        }

        if ($res['status'] === 'identity_only') {
            // Pre-filled new-patient form data. UI shows a banner like:
            // "This person is registered on eClinicPro — we've pre-filled their basic info."
            return Response::json([
                'status'  => 'identity_only',
                'exists'  => false,
                'prefill' => $res['prefill'],
                'source'  => $res['prefill']['_source'] ?? 'identity',
            ]);
        }

        return Response::json([
            'status'  => 'unknown',
            'exists'  => false,
            'patient' => null,
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
