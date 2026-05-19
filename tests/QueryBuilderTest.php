<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    public function testForClinicAppendsClinicIdToWhere(): void
    {
        if (!Database::ping()) {
            $this->markTestSkipped('Database not available');
        }

        $qb = QueryBuilder::table('patients')->forClinic(42);
        $this->assertSame(42, $qb->getAppliedClinicId());
    }

    public function testForClinicAutoSetsClinicIdOnInsert(): void
    {
        if (!Database::ping()) {
            $this->markTestSkipped('Database not available');
        }

        $slug = 'test-qb-' . bin2hex(random_bytes(3));
        $reg = \App\Services\AuthService::registerClinic('QB Test', $slug, $slug . '@t.local', 'TestPass123!', null);
        $clinic = ['clinic_id' => $reg['tenant_id']];
        $qb = QueryBuilder::table('notifications')->forClinic($clinic['clinic_id']);
        $id = $qb->insert([
            'channel' => 'email',
            'template' => 'test',
            'payload' => '{}',
            'status' => 'queued',
            'scheduled_at' => date('Y-m-d H:i:s'),
        ]);

        $row = QueryBuilder::table('notifications')->where('id', '=', $id)->first();
        $this->assertSame($clinic['clinic_id'], (int) ($row['clinic_id'] ?? 0));
    }
}
