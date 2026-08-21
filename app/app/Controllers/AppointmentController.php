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
use App\Services\RoleAccessService;
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
        $user = RequestContext::user() ?? [];
        $doctors = AppointmentService::doctorsForClinic($clinicId);
        $doctorId = RoleAccessService::resolveAppointmentDoctorId(
            $user,
            !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null,
        );
        $canBookForAll = RoleAccessService::canBookAppointmentsForAllDoctors($user);
        $canBookAppointments = RoleAccessService::canBookAppointments($user);
        $isDoctorScoped = RoleAccessService::appointmentDoctorScope($user) !== null;

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
            'canBookForAll' => $canBookForAll,
            'canBookAppointments' => $canBookAppointments,
            'isDoctorScoped' => $isDoctorScoped,
        ], 'Appointments'));
    }

    public function create(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        if ($denied = $this->requireBookAccess()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorScope = RoleAccessService::appointmentDoctorScope($user);
        $tz = SlotService::clinicTimezone($clinicId);
        try {
            $todayLocal = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d');
        } catch (\Throwable) {
            $todayLocal = date('Y-m-d');
        }

        $prefill = [
            'patient_id' => $request->query['patient_id'] ?? '',
            'doctor_id' => $request->query['doctor_id'] ?? '',
            'date' => $request->query['date'] ?? $todayLocal,
            'time' => $request->query['time'] ?? '',
            'type' => $request->query['type'] ?? 'prebooked',
            'is_followup' => !empty($request->query['followup']),
        ];
        if ($doctorScope !== null) {
            $prefill['doctor_id'] = $doctorScope;
        }

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
            'clinicTimezone' => $tz,
            'todayLocal' => $todayLocal,
            'lockDoctorId' => $doctorScope,
        ], $prefill['type'] === 'walkin' ? 'Walk-in appointment' : 'Book appointment'));
    }

    public function calendar(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorScope = RoleAccessService::appointmentDoctorScope($user);
        $tz = SlotService::clinicTimezone($clinicId);
        try {
            $now = new \DateTime('now', new \DateTimeZone($tz));
        } catch (\Throwable) {
            $now = new \DateTime('now');
        }

        // Status → [colour, label]. Must match AppointmentService::calendarEvents
        // and the rest of the appointment UI so grid, pills and legend agree.
        $statusColors = [
            'scheduled' => ['#f59e0b', 'Waiting'],
            'confirmed' => ['#22c55e', 'Arrived'],
            'in_progress' => ['#3b82f6', 'In Consult'],
            'completed' => ['#10b981', 'Completed'],
            'no_show' => ['#ef4444', 'Not Arrived'],
            'cancelled' => ['#94a3b8', 'Cancelled'],
        ];

        return Response::html(Layout::page('appointments/calendar', [
            'doctors' => AppointmentService::doctorsForClinic($clinicId),
            'clinicTimezone' => $tz,
            'todayLocal' => $now->format('Y-m-d'),
            'nowLocal' => $now->format('Y-m-d\TH:i'),
            'statusColors' => $statusColors,
            'lockDoctorId' => $doctorScope,
        ], 'Calendar'));
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        if ($denied = $this->requireBookAccess()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctorScope = RoleAccessService::appointmentDoctorScope(RequestContext::user() ?? []);

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

            // Back to the listing (on the booking's own date) with a "Booking
            // added" flash — no interstitial thanks page. The id lets the flash
            // offer a one-click slip download.
            $bookedDate = date('Y-m-d', strtotime((string) $appointment['scheduled_at']));
            return Response::redirect('/appointments?booked=1&new_id=' . (int) $appointment['id'] . '&date=' . $bookedDate);
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
                    'lockDoctorId' => $doctorScope,
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

        if ($denied = $this->requireAppointmentAccess($appointment)) {
            return $denied;
        }

        return Response::html(Layout::page('appointments/form', [
            'appointment' => $appointment,
            'doctors' => AppointmentService::doctorsForClinic($clinicId),
            'prefill' => [],
            'error' => null,
            'hasTelemedicine' => ModuleGate::check('telemedicine'),
            'lockDoctorId' => RoleAccessService::appointmentDoctorScope(RequestContext::user() ?? []),
        ], 'Edit appointment'));
    }

    public function update(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $existing = AppointmentService::find($clinicId, (int) $id);
        if ($existing === null) {
            return Response::html('Appointment not found', 404);
        }
        if ($denied = $this->requireAppointmentAccess($existing)) {
            return $denied;
        }
        if ($denied = $this->requireManageAppointment($existing)) {
            return $denied;
        }

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
                'hasTelemedicine' => ModuleGate::check('telemedicine'),
                'lockDoctorId' => RoleAccessService::appointmentDoctorScope(RequestContext::user() ?? []),
            ], 'Edit appointment'), 422);
        }
    }

    public function cancel(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $existing = AppointmentService::find($clinicId, (int) $id);
        if ($existing !== null && ($denied = $this->requireAppointmentAccess($existing))) {
            return $denied;
        }
        if ($existing !== null && ($denied = $this->requireManageAppointment($existing))) {
            return $denied;
        }

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

        if ($denied = $this->requireAppointmentAccess($appointment)) {
            return $denied;
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

    /**
     * JSON endpoint that re-renders just the day-view results region (count
     * cards + status tabs + table) so the appointments page can poll for new
     * bookings without a full reload. Mirrors DashboardController::queueApi.
     *
     * Returns { html, total, refreshed_at }. `total` lets the client detect a
     * count increase and show a "new booking" toast.
     */
    public function listApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('appointments_basic')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::resolveAppointmentDoctorId(
            $user,
            !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null,
        );

        $ts = strtotime((string) ($request->query['date'] ?? date('Y-m-d')));
        $date = $ts ? date('Y-m-d', $ts) : date('Y-m-d');

        $statusFilter = $request->query['status'] ?? 'all';
        $appointments = AppointmentService::forDate($clinicId, $date, $doctorId);

        $counts = [
            'all' => count($appointments), 'scheduled' => 0, 'confirmed' => 0,
            'in_progress' => 0, 'completed' => 0, 'no_show' => 0, 'cancelled' => 0,
        ];
        foreach ($appointments as $a) {
            $s = (string) ($a['status'] ?? 'scheduled');
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }
        $total = $counts['all'];

        if ($statusFilter !== 'all') {
            $appointments = array_values(array_filter(
                $appointments,
                static fn (array $a) => ($a['status'] ?? '') === $statusFilter,
            ));
        }

        $html = \App\Support\View::render('appointments/_list_body', [
            'appointments' => $appointments,
            'counts' => $counts,
            'statusFilter' => $statusFilter,
            'date' => $date,
            'displayDate' => date('d M Y', strtotime($date)),
            'doctorId' => $doctorId,
            'csrf' => \App\Services\CsrfService::token(),
        ]);

        return Response::json([
            'html' => $html,
            'total' => $total,
            'refreshed_at' => date('c'),
        ]);
    }

    public function slotsApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('appointments_basic')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::resolveAppointmentDoctorId($user, (int) ($request->query['doctor_id'] ?? 0)) ?? 0;
        $tz = SlotService::clinicTimezone($clinicId);
        $date = self::normalizeDate((string) ($request->query['date'] ?? ''), $tz);

        if ($doctorId < 1) {
            return Response::json(['slots' => []]);
        }

        $slots = SlotService::available($clinicId, $doctorId, $date, true);
        try {
            $todayLocal = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d');
        } catch (\Throwable) {
            $todayLocal = date('Y-m-d');
        }

        return Response::json([
            'slots' => $slots,
            'refreshed_at' => date('c'),
            'meta' => [
                'clinic_id' => $clinicId,
                'doctor_id' => $doctorId,
                'date' => $date,
                'count' => count($slots),
                'timezone' => $tz,
                'today' => $todayLocal,
            ],
        ]);
    }

    private static function normalizeDate(string $date, ?string $tz = null): string
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

        if ($tz !== null && $tz !== '') {
            try {
                return (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d');
            } catch (\Throwable) {
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
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::resolveAppointmentDoctorId(
            $user,
            !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null,
        );

        return Response::json(AppointmentService::calendarEvents($clinicId, $start, $end, $doctorId));
    }

    /**
     * JSON status change for the calendar detail popup. Cancel routes through
     * AppointmentService::cancel (slot release + cancellation notice); every
     * other transition uses updateStatus (flow timestamps, token assignment).
     */
    public function statusApi(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('appointments_basic')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $existing = AppointmentService::find($clinicId, (int) $id);
        if ($existing === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $scope = RoleAccessService::appointmentDoctorScope($user);
        $outOfScope = $scope !== null && (int) $existing['doctor_id'] !== $scope;
        if (!RoleAccessService::canAccessAppointment($user, $existing) || $outOfScope) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $status = (string) ($request->post['status'] ?? '');
        try {
            $appointment = $status === 'cancelled'
                ? AppointmentService::cancel($clinicId, (int) $id)
                : AppointmentService::updateStatus($clinicId, (int) $id, $status);
        } catch (\InvalidArgumentException) {
            return Response::json(['ok' => false, 'error' => 'invalid_status'], 422);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'error' => 'failed'], 500);
        }

        AuditService::log($request, 'UPDATE', 'appointments', (int) $id);

        return Response::json(['ok' => true, 'status' => $appointment['status'] ?? $status]);
    }

    /**
     * JSON booking for the calendar "book" popup. Reuses the same patient
     * resolution + validation as the full-page store(), so slot availability
     * and the past-time guard still apply.
     */
    public function bookApi(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }
        if ($denied = $this->requireBookAccess()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        try {
            $patientId = $this->resolvePatientId($clinicId, $request);
            if ($patientId === 0) {
                throw new \RuntimeException('Select an existing patient, or enter a new patient name and phone.');
            }
            $data = $this->dataFromRequest($request);
            $data['patient_id'] = $patientId;
            if ((int) $data['doctor_id'] < 1) {
                throw new \RuntimeException('Please select a doctor.');
            }
            $appointment = AppointmentService::create($clinicId, $data);
            AuditService::log($request, 'INSERT', 'appointments', (int) $appointment['id']);

            return Response::json(['ok' => true, 'id' => (int) $appointment['id']], 201);
        } catch (\Throwable $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
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

        $user = RequestContext::user() ?? [];
        $doctorId = (int) ($request->post['doctor_id'] ?? 0);
        $scope = RoleAccessService::appointmentDoctorScope($user);
        if ($scope !== null) {
            $doctorId = $scope;
        }

        return [
            'patient_id' => (int) ($request->post['patient_id'] ?? 0),
            'doctor_id' => $doctorId,
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

  /** @param array<string, mixed> $appointment */
    private function requireAppointmentAccess(array $appointment): ?Response
    {
        $user = RequestContext::user() ?? [];
        if (!RoleAccessService::canAccessAppointment($user, $appointment)) {
            return Response::redirect('/appointments?error=' . urlencode('You can only view your own appointments.'));
        }

        return null;
    }

    /** @param array<string, mixed> $appointment */
    private function requireManageAppointment(array $appointment): ?Response
    {
        $user = RequestContext::user() ?? [];
        if (!RoleAccessService::canManageAppointment($user, $appointment)) {
            return Response::redirect('/appointments?error=' . urlencode('You can only edit your own appointments.'));
        }

        return null;
    }

    private function requireBookAccess(): ?Response
    {
        $user = RequestContext::user() ?? [];
        if (!RoleAccessService::canBookAppointments($user)) {
            return Response::redirect('/appointments?error=' . urlencode('You do not have permission to book appointments.'));
        }

        return null;
    }
}
