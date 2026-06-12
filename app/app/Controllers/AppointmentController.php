<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AppointmentService;
use App\Services\AppointmentSlipService;
use App\Services\AuditService;
use App\Services\SlotService;
use App\Support\Layout;

final class AppointmentController
{
    public function index(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctors = AppointmentService::doctorsForClinic($clinicId);
        $doctorId = !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null;

        $dateRaw = $request->query['date'] ?? date('Y-m-d');
        $ts = strtotime((string) $dateRaw);
        $date = $ts ? date('Y-m-d', $ts) : date('Y-m-d');

        $view = ($request->query['view'] ?? 'day') === 'week' ? 'week' : 'day';

        // Week view: Mon–Sun agenda around the selected date.
        $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $weekAppointments = [];
        if ($view === 'week') {
            foreach (AppointmentService::forRange($clinicId, $weekStart, $weekEnd, $doctorId) as $a) {
                $weekAppointments[date('Y-m-d', strtotime((string) $a['scheduled_at']))][] = $a;
            }
        }

        // Day view: slot timeline for the selected doctor (available / booked /
        // blocked / extended states at a glance).
        $daySlots = [];
        if ($view === 'day' && $doctorId !== null) {
            $daySlots = SlotService::available($clinicId, $doctorId, $date, true);
        }

        $statusFilter = $request->query['status'] ?? 'all';
        $appointments = AppointmentService::forDate($clinicId, $date, $doctorId);

        $counts = [
            'all' => count($appointments),
            'scheduled' => 0,
            'confirmed' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'no_show' => 0,
            'cancelled' => 0,
        ];
        foreach ($appointments as $a) {
            $s = (string) ($a['status'] ?? 'scheduled');
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }

        if ($statusFilter !== 'all') {
            $appointments = array_values(array_filter(
                $appointments,
                static fn (array $a) => ($a['status'] ?? '') === $statusFilter,
            ));
        }

        $clinic = RequestContext::clinic();

        return Response::html(Layout::page('appointments/index', [
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'date' => $date,
            'prevDate' => date('Y-m-d', strtotime($date . ($view === 'week' ? ' -7 days' : ' -1 day'))),
            'nextDate' => date('Y-m-d', strtotime($date . ($view === 'week' ? ' +7 days' : ' +1 day'))),
            'appointments' => $appointments,
            'counts' => $counts,
            'statusFilter' => $statusFilter,
            'clinicSlug' => $clinic['slug'] ?? 'demo',
            'view' => $view,
            'weekStart' => $weekStart,
            'weekAppointments' => $weekAppointments,
            'daySlots' => $daySlots,
        ], 'Appointments'));
    }

    public function create(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $prefill = [
            'patient_id' => $request->query['patient_id'] ?? '',
            'doctor_id' => $request->query['doctor_id'] ?? '',
            'date' => $request->query['date'] ?? date('Y-m-d'),
            'type' => $request->query['type'] ?? 'prebooked',
            'is_followup' => !empty($request->query['followup']),
        ];

        $patientHint = null;
        if (!empty($prefill['patient_id'])) {
            $patientHint = \App\Services\PatientService::find($clinicId, (int) $prefill['patient_id']);
        }

        return Response::html(Layout::page('appointments/form', [
            'appointment' => null,
            'doctors' => AppointmentService::doctorsForClinic($clinicId),
            'prefill' => $prefill,
            'patientHint' => $patientHint,
            'error' => null,
            'hasTelemedicine' => ModuleGate::check('telemedicine'),
        ], 'Book appointment'));
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();

        try {
            $patientId = $this->resolvePatientId($clinicId, $request);
            if ($patientId === 0) {
                throw new \RuntimeException('Please select an existing patient or enter a new patient name and phone.');
            }

            $data = $this->dataFromRequest($request);
            $data['patient_id'] = $patientId;

            if ((int) $data['doctor_id'] < 1) {
                throw new \RuntimeException('Please select a doctor.');
            }

            $appointment = AppointmentService::create($clinicId, $data);
            AuditService::log($request, 'INSERT', 'appointments', (int) $appointment['id']);

            return Response::redirect('/appointments/' . $appointment['id'] . '/slip?booked=1');
        } catch (\Throwable $e) {
            error_log('[appointments/store] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            try {
                return Response::html(Layout::page('appointments/form', [
                    'appointment' => null,
                    'doctors' => AppointmentService::doctorsForClinic($clinicId),
                    'prefill' => $request->post,
                    'patientHint' => null,
                    'error' => $e->getMessage(),
                    'hasTelemedicine' => ModuleGate::check('telemedicine'),
                ], 'Book appointment'), 422);
            } catch (\Throwable $renderError) {
                // The error re-render itself crashed — fall back to a plain debug page so we can see what's wrong.
                return Response::html(
                    '<pre style="padding:20px;font:13px monospace;color:#b00;white-space:pre-wrap;background:#fff5f5;">'
                    . '<strong>Booking failed:</strong>' . "\n\n"
                    . htmlspecialchars($e->getMessage()) . "\n\n"
                    . 'at ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n"
                    . htmlspecialchars($e->getTraceAsString())
                    . "\n\n----\n<strong>Re-render also failed:</strong>\n"
                    . htmlspecialchars($renderError->getMessage()) . "\n"
                    . 'at ' . htmlspecialchars($renderError->getFile()) . ':' . $renderError->getLine()
                    . '</pre>',
                    500
                );
            }
        }
    }

    /**
     * Resolves which patient the appointment belongs to.
     * Priority: explicit patient_id > inline new-patient (new_name + new_phone) > 0.
     * Inline create when name+phone provided and no existing record matches the phone.
     */
    private function resolvePatientId(int $clinicId, Request $request): int
    {
        $explicitId = (int) ($request->post['patient_id'] ?? 0);
        if ($explicitId > 0) {
            return $explicitId;
        }

        $newName = trim((string) ($request->post['new_patient_name'] ?? ''));
        $newPhone = trim((string) ($request->post['new_patient_phone'] ?? ''));
        if ($newName === '' || $newPhone === '') {
            return 0;
        }

        $existing = \App\Services\PatientService::findByPhone($clinicId, $newPhone);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $patient = \App\Services\PatientService::create($clinicId, [
            'name' => $newName,
            'phone' => $newPhone,
            'source' => 'walk_in',
        ]);

        return (int) ($patient['id'] ?? 0);
    }

    public function edit(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $appointment = AppointmentService::findDetailed($clinicId, (int) $id);
        if ($appointment === null) {
            return Response::html('Appointment not found', 404);
        }

        return Response::html(Layout::page('appointments/form', [
            'appointment' => $appointment,
            'doctors' => AppointmentService::doctorsForClinic($clinicId),
            'prefill' => [],
            'error' => null,
            'hasTelemedicine' => ModuleGate::check('telemedicine'),
        ], 'Edit appointment'));
    }

    public function update(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        try {
            $data = $this->dataFromRequest($request);
            AppointmentService::update($clinicId, (int) $id, $data);
            AuditService::log($request, 'UPDATE', 'appointments', (int) $id);

            return Response::redirect('/appointments?updated=1');
        } catch (\Throwable $e) {
            $appointment = AppointmentService::findDetailed($clinicId, (int) $id);

            return Response::html(Layout::page('appointments/form', [
                'appointment' => $appointment,
                'doctors' => AppointmentService::doctorsForClinic($clinicId),
                'prefill' => [],
                'error' => $e->getMessage(),
            ], 'Edit appointment'), 422);
        }
    }

    public function cancel(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        AppointmentService::cancel($clinicId, (int) $id);
        AuditService::log($request, 'UPDATE', 'appointments', (int) $id);

        return Response::redirect('/appointments?cancelled=1');
    }

    public function slip(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $appointment = AppointmentService::findDetailed($clinicId, (int) $id);
        if ($appointment === null) {
            return Response::html('Not found', 404);
        }

        $clinic = RequestContext::clinic();
        // generate() returns null when the PDF can't be produced (e.g. temp
        // dir perms) — the booking still succeeded, so always show the
        // confirmation page; it simply hides the "Download slip" button.
        $path = AppointmentSlipService::generate($appointment, $clinic ?? []);

        if (!empty($request->query['booked'])) {
            return Response::html(Layout::page('appointments/booked', [
                'appointment' => $appointment,
                'slipUrl' => $path,
            ], 'Appointment booked'));
        }

        if ($path === null) {
            return Response::redirect('/appointments?error=' . urlencode('Slip PDF could not be generated.'));
        }

        return Response::redirect($path);
    }

    public function slotsApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('appointments_basic')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctorId = (int) ($request->query['doctor_id'] ?? 0);
        $date = self::normalizeDate((string) ($request->query['date'] ?? ''));

        if ($doctorId < 1) {
            return Response::json(['slots' => []]);
        }

        $slots = SlotService::available($clinicId, $doctorId, $date, true);

        return Response::json([
            'slots' => $slots,
            'refreshed_at' => date('c'),
            'meta' => [
                'clinic_id' => $clinicId,
                'doctor_id' => $doctorId,
                'date' => $date,
                'count' => count($slots),
            ],
        ]);
    }

    private static function normalizeDate(string $date): string
    {
        $date = trim($date);
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        if ($date !== '') {
            $ts = strtotime($date);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }

        return date('Y-m-d');
    }

    public function calendarApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('appointments_basic')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $start = $request->query['start'] ?? date('Y-m-01');
        $end = $request->query['end'] ?? date('Y-m-t 23:59:59');
        $doctorId = !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null;

        return Response::json(AppointmentService::calendarEvents($clinicId, $start, $end, $doctorId));
    }

    /** @return array<string, mixed> */
    private function dataFromRequest(Request $request): array
    {
        $type = $request->post['type'] ?? 'prebooked';
        $date = trim((string) ($request->post['scheduled_date'] ?? ''));
        $time = trim((string) ($request->post['scheduled_time'] ?? ''));

        if ($date === '' || strtotime($date) === false) {
            $date = date('Y-m-d');
        }
        if ($time === '' || !preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time = $type === 'walkin' ? date('H:i') : '09:00';
        }

        return [
            'patient_id' => (int) ($request->post['patient_id'] ?? 0),
            'doctor_id' => (int) ($request->post['doctor_id'] ?? 0),
            'scheduled_at' => $date . ' ' . $time . ':00',
            'type' => $type,
            'chief_complaint' => $request->post['chief_complaint'] ?? '',
            'notes' => $request->post['notes'] ?? '',
            'is_followup' => !empty($request->post['is_followup']),
            'source' => 'reception',
        ];
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('appointments_basic')) {
            return Response::html(Layout::page('errors/module', [
                'module' => 'appointments_basic',
                'label' => 'Appointments',
            ], 'Module inactive'), 402);
        }

        return null;
    }
}
