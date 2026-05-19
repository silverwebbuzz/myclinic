<?php

declare(strict_types=1);

namespace Tests;

use App\Services\PatientService;
use App\Services\VisitService;
use App\Services\InvoiceService;
use Tests\Support\DatabaseTestCase;

final class MultiTenantIsolationTest extends DatabaseTestCase
{
    public function testClinicACannotReadClinicBPatient(): void
    {
        $this->requireDatabase();
        $a = $this->createClinic('-isoA');
        $b = $this->createClinic('-isoB');

        $patientB = PatientService::create($b['clinic_id'], [
            'name' => 'Clinic B Patient',
            'phone' => '9111111111',
            'gender' => 'F',
        ]);

        $found = PatientService::find($a['clinic_id'], (int) $patientB['id']);
        $this->assertNull($found);
    }

    public function testClinicACannotReadClinicBVisit(): void
    {
        $this->requireDatabase();
        $a = $this->createClinic('-isoA2');
        $b = $this->createClinic('-isoB2');

        $patientB = PatientService::create($b['clinic_id'], [
            'name' => 'Visit Patient B',
            'phone' => '9222222222',
            'gender' => 'M',
        ]);

        $visitId = \App\Core\QueryBuilder::table('visits')->insert([
            'clinic_id' => $b['clinic_id'],
            'patient_id' => (int) $patientB['id'],
            'doctor_id' => $b['user_id'],
            'visit_number' => 1,
            'status' => 'in_progress',
        ]);

        $this->assertNull(VisitService::find($a['clinic_id'], $visitId));
    }

    public function testClinicACannotReadClinicBInvoice(): void
    {
        $this->requireDatabase();
        $a = $this->createClinic('-isoA3');
        $b = $this->createClinic('-isoB3');

        $patientB = PatientService::create($b['clinic_id'], [
            'name' => 'Invoice Patient B',
            'phone' => '9333333333',
            'gender' => 'M',
        ]);

        $invoiceId = InvoiceService::create($b['clinic_id'], [
            'patient_id' => (int) $patientB['id'],
            'status' => 'draft',
        ]);

        $this->assertNull(InvoiceService::find($a['clinic_id'], $invoiceId));
    }
}
