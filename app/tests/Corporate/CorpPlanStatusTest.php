<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\CorpPlanStatus;
use PHPUnit\Framework\TestCase;

final class CorpPlanStatusTest extends TestCase
{
    private const TODAY = '2026-11-18';

    public function testNoDatesIsNeverBlocked(): void
    {
        $s = CorpPlanStatus::for(['status' => 'pending', 'trial_ends_at' => null, 'plan_expires_at' => null], self::TODAY);
        $this->assertFalse($s['expired']);
        $this->assertSame('active', $s['state']);
        $this->assertSame('none', $s['reason']);
    }

    public function testTrialStates(): void
    {
        $this->assertSame('trial', CorpPlanStatus::for(['trial_ends_at' => '2026-12-18'], self::TODAY)['state']);
        $this->assertSame('expiring_soon', CorpPlanStatus::for(['trial_ends_at' => '2026-11-23'], self::TODAY)['state']);

        $lastDay = CorpPlanStatus::for(['trial_ends_at' => self::TODAY], self::TODAY);
        $this->assertFalse($lastDay['expired'], 'the end date itself is still usable');
        $this->assertSame(0, $lastDay['days_left']);

        $over = CorpPlanStatus::for(['trial_ends_at' => '2026-11-17'], self::TODAY);
        $this->assertTrue($over['expired']);
        $this->assertSame(-1, $over['days_left']);
        $this->assertSame('trial', $over['reason']);
    }

    public function testPaidPlanOverridesTrial(): void
    {
        $s = CorpPlanStatus::for(['trial_ends_at' => '2026-10-01', 'plan_expires_at' => '2027-11-17'], self::TODAY);
        $this->assertFalse($s['expired']);
        $this->assertSame('plan', $s['reason']);
        $this->assertSame('active', $s['state']);

        $lapsed = CorpPlanStatus::for(['trial_ends_at' => '2027-01-01', 'plan_expires_at' => '2026-11-01'], self::TODAY);
        $this->assertTrue($lapsed['expired'], 'a lapsed paid plan blocks even if an old trial date is later');
    }

    public function testUsesCompanyTimezoneWhenNoOverride(): void
    {
        $s = CorpPlanStatus::for(['trial_ends_at' => '2099-01-01', 'timezone' => 'Asia/Dubai']);
        $this->assertSame('trial', $s['state']);
        $this->assertGreaterThan(5, $s['days_left']);
    }

    public function testGarbageDatesIgnored(): void
    {
        $s = CorpPlanStatus::for(['trial_ends_at' => '0000-00-00', 'plan_expires_at' => 'not-a-date'], self::TODAY);
        $this->assertSame('none', $s['reason']);
        $this->assertFalse($s['expired']);
    }
}
