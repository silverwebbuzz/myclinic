<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\RoleService;
use PHPUnit\Framework\TestCase;

/** Anti-escalation rules (no DB needed for these paths). */
final class CorpPermissionTest extends TestCase
{
    protected function tearDown(): void
    {
        CorpAuth::reset();
    }

    public function testCanReadsPrimedPermissions(): void
    {
        CorpAuth::prime(['users.view', 'company.view']);
        $this->assertTrue(CorpAuth::can('users.view'));
        $this->assertFalse(CorpAuth::can('users.manage'));
    }

    public function testIsWithin(): void
    {
        $hr = array_fill_keys(['company.view', 'users.view', 'employees.view', 'employees.manage'], true);
        $this->assertTrue(RoleService::isWithin(['employees.view', 'company.view'], $hr));
        $this->assertFalse(RoleService::isWithin(['employees.view', 'users.manage'], $hr), 'HR cannot hand out users.manage');
        $this->assertTrue(RoleService::isWithin([], $hr));
    }

    public function testBranchRolesNotAssignableYet(): void
    {
        $all = array_fill_keys(['company.view', 'employees.view'], true);
        $branch = ['scope_type' => 'branch', 'is_clinical' => 0, 'permissions' => ['company.view']];
        $company = ['scope_type' => 'company', 'is_clinical' => 0, 'permissions' => ['company.view']];
        $this->assertFalse(RoleService::canAssign($branch, $all));
        $this->assertTrue(RoleService::canAssign($company, $all));
        $this->assertFalse(RoleService::canAssign(['scope_type' => 'company', 'is_clinical' => 0, 'permissions' => ['roles.manage']], $all));
    }
}
