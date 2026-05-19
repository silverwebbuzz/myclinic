<?php

declare(strict_types=1);

namespace Tests;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Gates\ModuleGate;
use Tests\Support\DatabaseTestCase;

final class ModuleGateTest extends DatabaseTestCase
{
    public function testCheckReturnsFalseWithoutClinicContext(): void
    {
        RequestContext::reset();
        $this->assertFalse(ModuleGate::check('patients'));
    }

    public function testCheckReturnsTrueForActiveModule(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-mod');
        $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);

        ModuleGate::invalidateCache($clinic['clinic_id']);

        $this->assertTrue(ModuleGate::check('patients'));
    }

    public function testCheckReturnsFalseForInactiveModule(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-nomod');
        $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);

        QueryBuilder::table('clinic_modules')
            ->forClinic($clinic['clinic_id'])
            ->where('module_id', '=', 'pharmacy')
            ->update(['is_active' => 0]);

        ModuleGate::invalidateCache($clinic['clinic_id']);

        $this->assertFalse(ModuleGate::check('pharmacy'));
    }

    public function testRequireReturns402WhenInactive(): void
    {
        $this->requireDatabase();
        $clinic = $this->createClinic('-req');
        $this->setClinicContext($clinic['clinic_id'], $clinic['user_id']);

        $response = ModuleGate::require('nonexistent_module_xyz');
        $this->assertNotNull($response);
        $this->assertSame(402, $response->getStatus());
    }
}
