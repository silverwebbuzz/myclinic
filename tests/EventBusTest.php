<?php

declare(strict_types=1);

namespace Tests;

use App\Core\QueryBuilder;
use App\Services\EventBus;
use Tests\Support\DatabaseTestCase;

final class EventBusTest extends DatabaseTestCase
{
    public function testFirePersistsEventAndRunsSubscribers(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-event');
        $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);

        $patient = \App\Services\PatientService::create($clinic['clinic_id'], [
            'name' => 'Event Patient',
            'phone' => '9876543210',
            'gender' => 'M',
        ]);

        $visitId = QueryBuilder::table('visits')->insert([
            'clinic_id' => $clinic['clinic_id'],
            'patient_id' => (int) $patient['id'],
            'doctor_id' => $clinic['user_id'],
            'visit_number' => 1,
            'status' => 'completed',
        ]);

        $eventId = EventBus::fire('visit.completed', [
            'visit_id' => $visitId,
            'patient_id' => (int) $patient['id'],
            'clinic_id' => $clinic['clinic_id'],
        ], 'visits', $visitId);

        $this->assertGreaterThan(0, $eventId);

        $row = QueryBuilder::table('events')->where('id', '=', $eventId)->first();
        $this->assertNotNull($row);
        $this->assertSame('visit.completed', $row['event_name']);

        $invoice = QueryBuilder::table('invoices')
            ->forClinic($clinic['clinic_id'])
            ->where('visit_id', '=', $visitId)
            ->first();
        $this->assertNotNull($invoice);
    }
}
