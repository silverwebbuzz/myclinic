<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\QueryBuilder;
use App\Services\PatientService;
use App\Services\VisitService;
use Tests\Support\DatabaseTestCase;

final class SpecialtySmokeTest extends DatabaseTestCase
{
    /** @return list<string> */
    private function specialties(): array
    {
        return ['gp', 'homeopathy', 'dental', 'derma'];
    }

    public function testVisitCreationForEachSpecialty(): void
    {
        $this->requireDatabase();

        foreach ($this->specialties() as $specialty) {
            $clinic = $this->createClinic('-' . $specialty);
            QueryBuilder::table('tenants')->where('id', '=', $clinic['clinic_id'])->update(['specialty' => $specialty]);
            $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);

            $patient = PatientService::create($clinic['clinic_id'], [
                'name' => ucfirst($specialty) . ' Patient',
                'phone' => '9555' . substr((string) crc32($specialty), 0, 6),
                'gender' => 'M',
            ]);

            $visit = VisitService::create($clinic['clinic_id'], [
                'patient_id' => (int) $patient['id'],
                'doctor_id' => $clinic['user_id'],
                'chief_complaint' => "Smoke test for {$specialty}",
            ]);

            $this->assertNotEmpty($visit['id'], "Visit should be created for {$specialty}");

            QueryBuilder::table('visits')
                ->forClinic($clinic['clinic_id'])
                ->where('id', '=', (int) $visit['id'])
                ->update([
                    'specialty_data' => json_encode(['smoke' => $specialty]),
                    'status' => 'completed',
                ]);

            $done = VisitService::find($clinic['clinic_id'], (int) $visit['id']);
            $this->assertSame('completed', $done['status'] ?? '');
        }
    }
}
