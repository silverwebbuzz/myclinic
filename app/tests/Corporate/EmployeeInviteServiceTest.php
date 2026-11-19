<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\EmployeeInviteService;
use PHPUnit\Framework\TestCase;

final class EmployeeInviteServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function emp(array $over = []): array
    {
        return $over + ['status' => 'active', 'link_status' => 'pending', 'invited_at' => null];
    }

    public function testWhoCanBeInvited(): void
    {
        $this->assertNull(EmployeeInviteService::cannotInvite($this->emp()));
        $this->assertNull(EmployeeInviteService::cannotInvite($this->emp(['status' => 'on_leave'])));
        $this->assertNull(EmployeeInviteService::cannotInvite($this->emp(['invited_at' => gmdate('Y-m-d H:i:s', time() - 90000)])));
        $this->assertNotNull(EmployeeInviteService::cannotInvite($this->emp(['status' => 'terminated'])));
        $this->assertNotNull(EmployeeInviteService::cannotInvite($this->emp(['link_status' => 'confirmed'])));
        $this->assertNotNull(EmployeeInviteService::cannotInvite($this->emp(['link_status' => 'declined'])));
        $this->assertNotNull(EmployeeInviteService::cannotInvite($this->emp(['invited_at' => gmdate('Y-m-d H:i:s', time() - 3600)])));
    }

    public function testSmsFitsOneMessageWithALongCompanyName(): void
    {
        $text = str_replace('{#var#}', str_repeat('X', 30), EmployeeInviteService::SMS_TEXT);
        $this->assertLessThanOrEqual(160, strlen($text));
        $this->assertSame(1, substr_count(EmployeeInviteService::SMS_TEXT, '{#var#}'));
    }
}
