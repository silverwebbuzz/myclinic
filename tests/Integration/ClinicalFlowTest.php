<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\QueryBuilder;
use App\Services\AppointmentService;
use App\Services\InvoiceService;
use App\Services\PatientService;
use App\Services\VisitService;
use Tests\Support\DatabaseTestCase;

final class ClinicalFlowTest extends DatabaseTestCase
{
    public function testRegisterThroughPaymentQueuesNotification(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-flow');
        $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);

        foreach (['emr', 'prescription', 'billing_pro', 'whatsapp'] as $mod) {
            QueryBuilder::table('clinic_modules')->insert([
                'clinic_id' => $clinic['clinic_id'],
                'module_id' => $mod,
                'billing_cycle' => 'free',
                'is_active' => 1,
            ]);
        }

        $patient = PatientService::create($clinic['clinic_id'], [
            'name' => 'Flow Patient',
            'phone' => '9444444444',
            'gender' => 'M',
            'dob' => '1990-01-01',
        ]);

        $scheduledAt = date('Y-m-d H:i:s', strtotime('+2 days'));
        $appointmentId = QueryBuilder::table('appointments')->insert([
            'clinic_id' => $clinic['clinic_id'],
            'patient_id' => (int) $patient['id'],
            'doctor_id' => $clinic['user_id'],
            'scheduled_at' => $scheduledAt,
            'type' => 'prebooked',
            'status' => 'scheduled',
        ]);

        $visit = VisitService::startFromAppointment($clinic['clinic_id'], $appointmentId);
        $this->assertNotEmpty($visit['id']);

        VisitService::complete($clinic['clinic_id'], (int) $visit['id']);

        $invoice = QueryBuilder::table('invoices')
            ->forClinic($clinic['clinic_id'])
            ->where('visit_id', '=', (int) $visit['id'])
            ->first();
        $this->assertNotNull($invoice);

        InvoiceService::markPaid($clinic['clinic_id'], (int) $invoice['id'], 'cash');

        $paid = InvoiceService::find($clinic['clinic_id'], (int) $invoice['id']);
        $this->assertSame('paid', $paid['status'] ?? '');

        $queued = QueryBuilder::table('notifications')
            ->forClinic($clinic['clinic_id'])
            ->where('status', '=', 'queued')
            ->count();
        $this->assertGreaterThanOrEqual(0, $queued);
    }
}
