<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\ScreeningCaseService;
use PHPUnit\Framework\TestCase;

final class ScreeningCaseFilterTest extends TestCase
{
    public function testEveryBucketUsesEachPlaceholderOnceAndPassesAllParams(): void
    {
        foreach (['overdue', 'soon', 'later', 'open', 'done', 'waived'] as $bucket) {
            [$where, $params] = ScreeningCaseService::filterSql(
                ['bucket' => $bucket, 'q' => 'E-10', 'branch' => '2', 'job_role' => '3', 'exam' => '4', 'programme' => '5'], [2, 9], '2026-11-20');
            preg_match_all('/(?<!:):([a-z_0-9]+)/', 'c.company_id = :cid AND ' . $where, $m);
            $this->assertSame($m[1], array_values(array_unique($m[1])), $bucket);
            $this->assertEqualsCanonicalizing(array_diff($m[1], ['cid']), array_keys($params), $bucket);
            $this->assertStringContainsString('e.branch_id IN (2,9)', $where);
        }
    }

    public function testSoonWindowIsThirtyDays(): void
    {
        [, $params] = ScreeningCaseService::filterSql(['bucket' => 'soon'], null, '2026-11-20');
        $this->assertSame('2026-12-20', $params['b_soon']);
    }
}
