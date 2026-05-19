<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected function requireDatabase(): void
    {
        if (!Database::ping()) {
            $this->markTestSkipped('Database not available');
        }
    }

    /** @return array{clinic_id: int, user_id: int, slug: string} */
    protected function createClinic(string $suffix = ''): array
    {
        $slug = 'test-' . bin2hex(random_bytes(4)) . $suffix;
        $email = $slug . '@test.manageclinic.local';
        $result = AuthService::registerClinic('Test Clinic ' . $slug, $slug, $email, 'TestPass123!', null);

        return [
            'clinic_id' => $result['tenant_id'],
            'user_id' => $result['user_id'],
            'slug' => $slug,
        ];
    }

    protected function setClinicContext(int $clinicId, int $userId): void
    {
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        $user = QueryBuilder::table('users')->where('id', '=', $userId)->first();
        RequestContext::setClinic($clinic ?? []);
        RequestContext::setUser($user);
    }

    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }
}
