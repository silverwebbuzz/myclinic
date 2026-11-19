<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\ProgrammeService;
use PHPUnit\Framework\TestCase;

/** Company changes to adopted templates (design S4). */
final class ProgrammeRulesTest extends TestCase
{
    public function testMandatoryPeriodicCanOnlyGetMoreFrequent(): void
    {
        $item = ['requirement' => 'mandatory', 'template_frequency' => 12, 'template_item_id' => 5];
        $this->assertNull(ProgrammeService::frequencyRule($item, 12));
        $this->assertNull(ProgrammeService::frequencyRule($item, 6));
        $this->assertNotNull(ProgrammeService::frequencyRule($item, 24));
    }

    public function testRecommendedAndOwnItemsAreFree(): void
    {
        $this->assertNull(ProgrammeService::frequencyRule(['requirement' => 'recommended', 'template_frequency' => 12], 36));
        $this->assertNull(ProgrammeService::frequencyRule(['requirement' => 'company', 'template_frequency' => null], 60));
    }

    public function testCoverageSqlUsesEachPlaceholderOnce(): void
    {
        preg_match_all('/(?<!:):([a-z_]+)/', ProgrammeService::coverageSql(), $m);
        $this->assertSame(['cov_cid', 'cov_pid'], $m[1]);
    }
}
