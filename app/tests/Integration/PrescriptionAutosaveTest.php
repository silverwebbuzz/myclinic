<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\QueryBuilder;
use App\Services\PatientService;
use App\Services\VisitService;
use Tests\Support\DatabaseTestCase;

/**
 * Regression test for the prescription autosave bug:
 * PrescriptionService::syncForVisit used to drop any line that had no
 * drug_id/remedy_id/dosage — so a doctor-typed medicine (name + frequency,
 * no catalog pick) was silently lost. These tests pin the fixed behavior.
 */
final class PrescriptionAutosaveTest extends DatabaseTestCase
{
    /** A line with a drug_id persists (the happy path). */
    public function testAutosavePersistsPrescriptionWithDrugId(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-rx1');
        $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);
        $this->enableModules($clinic['clinic_id']);

        // Need a real drug row to reference.
        $drugId = QueryBuilder::table('drugs')->insert([
            'name' => 'TestDrug ' . bin2hex(random_bytes(3)),
            'generic_name' => 'Testium',
            'form' => 'tablet',
            'is_active' => 1,
        ]);

        $patient = PatientService::create($clinic['clinic_id'], [
            'name' => 'Rx Patient', 'phone' => '9333333331', 'gender' => 'M', 'dob' => '1990-01-01',
        ]);
        $visit = VisitService::startForPatient($clinic['clinic_id'], (int) $patient['id'], $clinic['user_id']);

        VisitService::autosave($clinic['clinic_id'], (int) $visit['id'], [
            'prescriptions' => [[
                'drug_id' => $drugId,
                'drug_name' => 'TestDrug',
                'frequency_preset' => '1-0-1',
                'duration_days' => 5,
                'dose_unit' => 'tablet',
                'dose_amount' => 1,
                'food_timing' => 'after',
            ]],
        ]);

        $count = QueryBuilder::table('prescriptions')
            ->forClinic($clinic['clinic_id'])
            ->where('visit_id', '=', (int) $visit['id'])
            ->count();
        $this->assertSame(1, $count, 'Prescription with drug_id should persist');
    }

    /** A FREE-TYPED line (name + frequency, no drug_id) must persist — the bug. */
    public function testAutosavePersistsTypedPrescriptionWithoutDrugId(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-rx2');
        $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);
        $this->enableModules($clinic['clinic_id']);

        $patient = PatientService::create($clinic['clinic_id'], [
            'name' => 'Rx Patient 2', 'phone' => '9333333332', 'gender' => 'F', 'dob' => '1992-01-01',
        ]);
        $visit = VisitService::startForPatient($clinic['clinic_id'], (int) $patient['id'], $clinic['user_id']);

        VisitService::autosave($clinic['clinic_id'], (int) $visit['id'], [
            'prescriptions' => [[
                'drug_id' => null,
                'remedy_id' => null,
                'drug_name' => 'Handwritten Syrup',
                'frequency_preset' => '1-1-1',
                'duration_days' => 3,
            ]],
        ]);

        $row = QueryBuilder::table('prescriptions')
            ->forClinic($clinic['clinic_id'])
            ->where('visit_id', '=', (int) $visit['id'])
            ->first();

        $this->assertNotNull($row, 'Typed prescription (no drug_id) should still persist');
        // Typed name preserved into dosage (no free-text name column exists).
        $this->assertSame('Handwritten Syrup', $row['dosage'] ?? null);
    }

    /** A genuinely empty line is still skipped (no junk rows). */
    public function testAutosaveSkipsEmptyPrescriptionLine(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-rx3');
        $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);
        $this->enableModules($clinic['clinic_id']);

        $patient = PatientService::create($clinic['clinic_id'], [
            'name' => 'Rx Patient 3', 'phone' => '9333333333', 'gender' => 'M', 'dob' => '1991-01-01',
        ]);
        $visit = VisitService::startForPatient($clinic['clinic_id'], (int) $patient['id'], $clinic['user_id']);

        VisitService::autosave($clinic['clinic_id'], (int) $visit['id'], [
            'prescriptions' => [
                ['drug_id' => null, 'remedy_id' => null, 'drug_name' => '', 'frequency_preset' => '', 'dose_amount' => ''],
            ],
        ]);

        $count = QueryBuilder::table('prescriptions')
            ->forClinic($clinic['clinic_id'])
            ->where('visit_id', '=', (int) $visit['id'])
            ->count();
        $this->assertSame(0, $count, 'Empty prescription line should be skipped');
    }

    private function enableModules(int $clinicId): void
    {
        foreach (['emr', 'prescription'] as $mod) {
            QueryBuilder::table('clinic_modules')->insert([
                'clinic_id' => $clinicId,
                'module_id' => $mod,
                'billing_cycle' => 'free',
                'is_active' => 1,
            ]);
        }
    }
}
