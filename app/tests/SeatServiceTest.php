<?php

declare(strict_types=1);

namespace Tests;

use App\Core\QueryBuilder;
use App\Services\SeatService;
use Tests\Support\DatabaseTestCase;

final class SeatServiceTest extends DatabaseTestCase
{
    public function testGetSeatUsageCountsActiveStaffAndPendingInvites(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-seats');

        $usage = SeatService::getSeatUsage($clinic['clinic_id']);

        $this->assertGreaterThanOrEqual(1, $usage['used']);
        $this->assertSame(2, $usage['limit']);
        $this->assertGreaterThanOrEqual(0, $usage['available']);
    }

    public function testCanAddStaffRespectsLimit(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-seatlimit');

        $this->assertTrue(SeatService::canAddStaff($clinic['clinic_id']));

        QueryBuilder::table('tenants')->where('id', '=', $clinic['clinic_id'])->update(['seat_limit' => 1]);
        QueryBuilder::table('users')->insert([
            'clinic_id' => $clinic['clinic_id'],
            'name' => 'Extra Staff',
            'email' => 'extra-' . $clinic['slug'] . '@test.local',
            'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'role' => 'receptionist',
            'is_active' => 1,
        ]);

        $this->assertFalse(SeatService::canAddStaff($clinic['clinic_id']));
    }

    public function testAddExtraSeatIncreasesLimit(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-extraseat');
        $before = SeatService::getSeatUsage($clinic['clinic_id'])['limit'];

        SeatService::addExtraSeat($clinic['clinic_id'], 2);
        $after = SeatService::getSeatUsage($clinic['clinic_id'])['limit'];

        $this->assertSame($before + 2, $after);
    }
}
